<?php

namespace Drupal\hs_trade;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;

class TransactionManager implements TransactionManagerInterface{

  protected $entityTypeManager;
  protected $csrfToken;
  protected $dateFormatter;
  protected $mailManager;
  protected $currentUser;
  protected $accessManager;
  function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    CsrfTokenGenerator $csrfToken,
    DateFormatterInterface $dateFormatter,
    MailManager $mailManager,
    AccountProxyInterface $currentUser,
    AccessManagerInterface $accessManager
  ){
    $this->entityTypeManager = $entityTypeManager;
    $this->csrfToken = $csrfToken;
    $this->dateFormatter = $dateFormatter;
    $this->mailManager = $mailManager;
    $this->currentUser = $currentUser;
    $this->accessManager = $accessManager;
  }

  /**
   * getTransaction() simplifies the process of getting a transaction entity
   */
  public function getTransaction($tid){
    return $this->entityTypeManager->getStorage('hs_trade_transaction')->load($tid);
  }

  /**
   * tradeItems() sets the status of items involved in a transaction to 'Traded' and unpublishes them
   */
  public function tradeItems($tid){
    $transaction = $this->getTransaction($tid);
    $responder_items = $transaction->getResponderItems();
    $requester_items = $transaction->getRequesterItems();
    $items = $this->entityTypeManager->getStorage('node')->loadMultiple(array_merge($responder_items, $requester_items));
    foreach($items as $item){
      $item->set('field_item_status', 'Traded')
            ->setPublished(FALSE)
            ->save();
    }
    return NULL;
  }

  /**
   * notifyResponder() updates the 'unread_transactions' field for a transaction's responder triggering an AJAX notification
   */
  public function notifyResponder($tid){
    $responder = $this->getTransaction($tid)->getResponder();
    $unread_transactions = array_map(function($value) {
      return $value['value'];
    },$responder->get('unread_transactions')->getValue());
    $unread_transactions[] = $tid;
    $responder->set('unread_transactions', $unread_transactions)->save();
  }

  /**
   * notifyRequester() updates the 'unread_transactions' field for a transaction's requester triggering an AJAX notification
   */
  public function notifyRequester($tid){
    $requester = $this->getTransaction($tid)->getRequester();
    $unread_transactions = array_map(function($value) {
      return $value['value'];
    },$requester->get('unread_transactions')->getValue());
    $unread_transactions[] = $tid;
    $requester->set('unread_transactions', $unread_transactions)->save();
  }

  /**
   * calculateResidual() retrieves HC values for a set of items and calculates the potential residual of a transaction
   */
  public function calculateResidual(array $responder_item_ids, array $requester_item_ids){
    $responder_items = $this->entityTypeManager->getStorage('node')->loadMultiple($responder_item_ids);
    $requester_items = $this->entityTypeManager->getStorage('node')->loadMultiple($requester_item_ids);
    $total_responder_value = 0;
    $total_requester_value = 0;
    foreach($responder_items as $item) {
      $total_responder_value += $item->get('field_item_value')->value;
    }
    foreach($requester_items as $item) {
      $total_requester_value += $item->get('field_item_value')->value;
    }
    //A negative trade residual indicates that it's owed by the requester, while positive indicates responder
    return $total_requester_value - $total_responder_value;
  }

  /**
   * makeNewOffer() Creates a new transaction entity based on an array of prepared values and notifies the responder
   * - The $data array should follow this structure:
   * [ 'requester_uid' => int,
   *   'responder_uid' => int,
   *   'responder_items' => array,
   *   'requester_items' => array,
   *   'enforce_residual' => bool ]
   */
  public function makeNewOffer(array $data){

    $responder = $this->entityTypeManager->getStorage('user')->load($data['responder_uid']);

    $new_message = $this->entityTypeManager->getStorage('private_message')->create([
      'message' => 'Hey '.$responder->getDisplayName().', I just made an offer on your items, and you should go check it out!',
      'owner' => $data['requester_uid']
    ]);
    $new_message->save();
    $new_thread = $this->entityTypeManager->getStorage('private_message_thread')
      ->create([
        'members' => [$data['responder_uid'], $data['requester_uid']],
        'private_messages' => [$new_message->id()],
      ]);
    $new_thread->save();

    $transaction = $this->entityTypeManager->getStorage('hs_trade_transaction')
      ->create([
        'responder_uid' => $data['responder_uid'],
        'requester_uid' => $data['requester_uid'],
        'responder_items' => $data['responder_items'],
        'requester_items' => $data['requester_items'],
        'residual' => $data['enforce_residual'] == TRUE ? $this->calculateResidual($data['responder_items'], $data['requester_items']) : 0,
        'status' => 'pending',
        'message_thread' => $new_thread->id(),
      ]);
    $transaction->save();
    $this->notifyResponder($transaction->id());
  }

  /**
   * makeCounterOffer() Alters an existing transaction depending on passed in data
   * - The $data array should follow this structure:
   * [ 'transaction_id' => int,
   *   'requester_uid' => int,
   *   'responder_uid' => int,
   *   'responder_items' => array,
   *   'requester_items' => array,
   *   'enforce_residual' => bool ]
   */
  public function makeCounterOffer(array $data){
    $transaction = $this->getTransaction($data['transaction_id']);
    $transaction
      ->setResponderUID($data['requester_uid'])
      ->setRequesterUID($data['responder_uid'])
      ->setResponderItems($data['responder_items'])
      ->setRequesterItems($data['requester_items'])
      ->setResidual($data['enforce_residual'] == TRUE ? $this->calculateResidual($data['responder_items'], $data['requester_items']) : 0)
      ->setStatus('countered')
      ->save();
    $this->notifyResponder($transaction->id());
    $this->sendTransactionPM(
      $data['transaction_id'],
      'I countered your initial offer, and it\'s pending your review',
      $data['responder_uid']
    );
  }

  /**
   * isItemInTransaction() takes a node ID and checks if it's involved in any active transactions
   * - Returns FALSE if the item isn't involved in any transactions, and returns an array of involved transaction IDs if it is
   */
  public function isItemInTransaction($nid){
    $query = $this->entityTypeManager->getStorage('hs_trade_transaction')->getQuery()->execute();
    $transactions = $this->entityTypeManager->getStorage('hs_trade_transaction')->loadMultiple($query);
    $involved_transactions = [];
    foreach($transactions as $transaction){
      foreach($transaction->getResponderItems() as $item){
        if($item === $nid && !($transaction->getStatus() === 'declined' || $transaction->getStatus() === 'confirmed')){
          $involved_transactions[] = $transaction->id();
        }
      }
      foreach($transaction->getRequesterItems() as $item){
        if($item === $nid && !($transaction->getStatus() === 'declined' || $transaction->getStatus() === 'confirmed')){
          $involved_transactions[] = $transaction->id();
        }
      }
    }
    if(!empty($involved_transactions)){
      return $involved_transactions;
    }
    return FALSE;
  }

  /**
   * getPMTransactionId() searches for the transaction related to the relevant private message thread
   * - Returns the related transaction's ID if found and returns NULL if thread isn't part of a transaction
   */
  public function getPMTransactionId($pmid){
    $results = $this->entityTypeManager->getStorage('hs_trade_transaction')
      ->getQuery()
      ->condition('message_thread', $pmid)
      ->execute();

    return !empty($results) ? reset($results) : NULL;
  }

  /**
   *sendTransactionPM() sends a message in the message thread related to the relevant transaction
   */
  public function sendTransactionPM($tid, $message, $owner){
    $transaction = $this->getTransaction($tid);
    $pmid = $transaction->get('message_thread')->value;
    $new_message = $this->entityTypeManager->getStorage('private_message')->create([
      'message' => $message,
      'owner' => $owner
    ]);
    $new_message->save();
    $thread = $this->entityTypeManager->getStorage('private_message_thread')->load($pmid);
    $thread->addMessageById($new_message->id());
    $thread->save();
  }

  /**
   * renderTransactions() generates a render array of transactions based on a given array of transaction IDs and mode
   * - The two current modes that function properly are 'incoming' and 'outgoing'
   * - Attaches library 'hs_trade/user-transactions-page' as well to ensure consistent styling
   */
  public function renderTransactions(array $tids, $mode){

    $trans_final = [];
    foreach($tids as $tid){
      $transaction = $this->getTransaction($tid);
      $transaction_url = $transaction->toUrl()->toString();
      $status = $transaction->getStatus();
      $responder = $transaction->getResponder();
      $requester = $transaction->getRequester();
      $created = $transaction->get('created')->value;

      //Store transaction specific values
      $trans_final[$tid]['id'] = $transaction->id();
      $trans_final[$tid]['url'] = $transaction_url;
      $trans_final[$tid]['status'] = $status;
      $trans_final[$tid]['residual'] = $transaction->get('residual')->value;
      $trans_final[$tid]['created'] = $this->dateFormatter->format($created, 'medium');
      $trans_final[$tid]['responder']['url'] = $responder->toUrl();
      $trans_final[$tid]['responder']['name'] = $mode === 'incoming' ? 'You' : $responder->getDisplayName();
      $trans_final[$tid]['requester']['url'] = $requester->toUrl();
      $trans_final[$tid]['requester']['name'] = $mode === 'outgoing' ? 'You' : $requester->getDisplayName();

      //Dynamically check if the current user has access to transaction actions and pass the necessary render data if they do
      $action_links = ['accept', 'decline', 'counter', 'confirm', 'message'];
      $action_links = array_filter($action_links, function($action) use ($tid){
        return $this->accessManager->checkNamedRoute('hs_trade.transaction_'.$action, ['hs_trade_transaction' => $tid], $this->currentUser);
      });
      $trans_final[$tid]['action_links'] = array_map(function($action) use ($tid){
        $url = Url::fromRoute('hs_trade.transaction_'.$action, ['hs_trade_transaction' => $tid])->toString();
        return [
          'url' => $url,
          'icon_path' => '/sites/default/files/icons/'.$action.'.svg',
          'text' => $action,
        ];
      }, $action_links);

      //If either user's account has been deleted, remove all action links
      if($requester->id() === 0 || $responder->id() === 0){
        unset($trans_final[$tid]['action_links']);
      }

      //Retrieve transaction items for processing and addition to returned array
      $owners = ['responder_items' => 'getResponderItems', 'requester_items' => 'getRequesterItems'];
      foreach($owners as $owner => $item_method){
        $item_ids = $transaction->{$item_method}();
        $items = $this->entityTypeManager->getStorage('node')->loadMultiple($item_ids);
        foreach($items as $item) {
          if(!empty($item)){
            $trans_final[$tid][$owner][] = [
              'id' => $item->id(),
              'name' => $item->getTitle(),
              'value' => $item->get('field_item_value')->value,
              'image' => $item->get('field_item_image')->entity->createFileUrl(),
              'url' => $item->toUrl(),
            ];
          }else{
            $trans_final[$tid][$owner][] = [
              'name' => 'ITEM DELETED',
              'value' => 0,
              'image' => '/themes/custom/hobbyswap/logo.png',
              'url' => 'javascript:void(0)',
            ];
          }
        }
      }
    }

    return[
      '#theme' => 'hs_transaction',
      '#mode' => $mode,
      '#transactions' => $trans_final,
      '#attached' => [
        'library' => ['hs_trade/hs-transactions'],
      ],
    ];
  }

  /**
   * mailTransactionSummary() emails a transaction summary to each user involved
   * - The $params array gets passed to the template 'email--transaction-summary' before being rendered and injected into the email's body
   */
  public function mailTransactionSummary($tid){
    $transaction = $this->getTransaction($tid);

    $params = [
      'tid' => $tid,
      'residual' => $transaction->getResidual(),
      'responder' => [
        'id' => $transaction->getResponder()->id(),
        'name' => $transaction->getResponder()->getDisplayName(),
        'email' => $transaction->getResponder()->getEmail(),
      ],
      'requester' => [
        'id' => $transaction->getRequester()->id(),
        'name' => $transaction->getRequester()->getDisplayName(),
        'email' => $transaction->getRequester()->getEmail(),
      ],
    ];

    $responder_items = $transaction->getResponderItems();
    $requester_items = $transaction->getRequesterItems();

    foreach($responder_items as $id){
      $item = $this->entityTypeManager->getStorage('node')->load($id);
      $params['responder']['items'][] = ['title' => $item->getTitle(), 'value' => $item->get('field_item_value')->value];
    }
    foreach($requester_items as $id){
      $item = $this->entityTypeManager->getStorage('node')->load($id);
      $params['requester']['items'][] = ['title' => $item->getTitle(), 'value' => $item->get('field_item_value')->value];
    }

    $this->mailManager->mail('hs_trade', 'transaction_summary', $transaction->getResponder()->getEmail(), 'en', $params, NULL, TRUE);
    $this->mailManager->mail('hs_trade', 'transaction_summary', $transaction->getRequester()->getEmail(), 'en', $params, NULL, TRUE);
  }

}
