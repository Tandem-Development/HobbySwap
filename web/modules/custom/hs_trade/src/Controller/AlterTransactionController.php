<?php

/**
 * A single controller that houses all the logic for users to interact with a transaction that they're involved in.
 * Also implements custom access controllers to ensure that the proper users are performing allowed actions
 */

namespace Drupal\hs_trade\Controller;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Psr\Container\ContainerInterface;
use Drupal\hs_trade\TransactionManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;

class AlterTransactionController extends ControllerBase {

  /**
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   * Boilerplate for cache invalidation service
   */
  protected $cacheTagsInvalidator;
  /**
   * @var \Drupal\hs_trade\TransactionManagerInterface
   */
  protected $transactionManager;
  /**
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;
  /**
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  function __construct(
    CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    TransactionManagerInterface $transactionManager,
    AccountProxyInterface $currentUser,
    FormBuilderInterface $formBuilder
  ) {
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
    $this->transactionManager = $transactionManager;
    $this->currentUser = $currentUser;
    $this->formBuilder = $formBuilder;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache_tags.invalidator'),
      $container->get('hs_trade.transaction_manager'),
      $container->get('current_user'),
      $container->get('form_builder')
    );
  }


  /**
   * Custom access handler for hs_trade.transaction_accept route
   */
  public function acceptAccess(AccountInterface $account, $hs_trade_transaction) {
    //Retrieve relevant transaction and get its status and responder_uid
    $transaction = $this->transactionManager->getTransaction($hs_trade_transaction);

    switch ($transaction->getStatus()){
      //Allow acceptance if transaction is pending or countered and the current user is the responder
      case 'pending':
      case 'countered':
      if($account->id() === $transaction->getResponder()->id()){
        return AccessResult::allowed();
      }
    }
    return AccessResult::forbidden();
  }

  /**
   * Custom access handler for hs_trade.transaction_decline route
   */
  public function declineAccess(AccountInterface $account, $hs_trade_transaction) {
    //Retrieve relevant transaction
    $transaction = $this->transactionManager->getTransaction($hs_trade_transaction);
    //Allow the responder to decline at any point unless the transaction has been finally confirmed
    switch($transaction->getStatus()){
      case 'pending':
      case 'countered':
      case 'requester confirmed':
        if($account->id() === $transaction->getResponder()->id()){
          return AccessResult::allowed();
        }
        break;
      case 'accepted':
        if($account->id() === $transaction->getRequester()->id()){
          return AccessResult::allowed();
        }
    }
    return AccessResult::forbidden();
  }

  /**
   * Custom access handler for hs_trade.transaction_counter route
   */
  public function counterAccess(AccountInterface $account, $hs_trade_transaction) {
    //Retrieve relevant transaction
    $transaction = $this->transactionManager->getTransaction($hs_trade_transaction);
    $status = $transaction->getStatus();
    //Behaves just like the decline access controller, but allows users to counter the offer even after declination
    switch($status){
      case 'pending':
      case 'countered':
      case 'requester confirmed':
        if($account->id() === $transaction->getResponder()->id()){
          return AccessResult::allowed();
        }
        break;
      case 'accepted':
        if($account->id() === $transaction->getRequester()->id()){
          return AccessResult::allowed();
        }
        break;
      case 'declined':
        return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  /**
   * Custom access handler for hs_trade.transaction_confirm
   */
  public function confirmAccess(AccountInterface $account, $hs_trade_transaction) {
    //Retrieve relevant transaction
    $transaction = $this->transactionManager->getTransaction($hs_trade_transaction);

    //Don't even consider transaction status if the current user doesn't have the right permission
    switch ($transaction->getStatus()){
      case 'accepted':
        //The requester performs initial confirmation
        if($account->id() === $transaction->getRequester()->id()){
          return AccessResult::allowed();
        }
        break;
      case 'requester confirmed':
        //The responder performs final confirmation
        if($account->id() === $transaction->getResponder()->id()){
          return AccessResult::allowed();
        }
    }
    //pending, countered, declined, and confirmed are always forbidden
    return AccessResult::forbidden();
  }

  /**
   * Controlling method for hs_trade.transaction_accept route
   */
  public function accept($hs_trade_transaction) {
    //Load the relevant transaction
    $transaction = $this->transactionManager->getTransaction($hs_trade_transaction);
    //Set transaction status to 'accepted'
    $transaction->setStatus('accepted');
    $transaction->save();

    //Notify the requester that their offer has been accepted, and send them a message in the transaction thread
    $this->transactionManager->notifyRequester($hs_trade_transaction);
    $this->transactionManager->sendTransactionPM(
      $hs_trade_transaction,
      'I have accepted your offer, and I am waiting for your confirmation!',
      $transaction->get('responder_uid')->value
    );

    //Invalidate user transaction caches
    $this->cacheTagsInvalidator->invalidateTags(['hs_trade.user_transactions_view']);

    //Redirect the user back to their transactions view page without displaying anything
    $user_url = Url::fromRoute('hs_trade.user_view_transactions', ['user' => $this->currentUser->id()]);
    $user_path = $user_url->getInternalPath();
    $response = new RedirectResponse('/'.$user_path);
    $response->send();

    return[];
  }

  /**
   * Controlling method for hs_trade.transaction_decline route
   */
  public function decline($hs_trade_transaction) {
    //Retrieve the transaction entity, set the status to 'declined', and save it
    $transaction = $this->transactionManager->getTransaction($hs_trade_transaction);
    $transaction->setStatus('declined');
    $transaction->save();

    //Invalidate user transaction caches
    $this->cacheTagsInvalidator->invalidateTags(['hs_trade.user_transactions_view']);

    //Notify the requester that their offer has been declined, and send them a message in the transaction thread
    $this->transactionManager->notifyRequester($hs_trade_transaction);
    $this->transactionManager->sendTransactionPM(
      $hs_trade_transaction,
      'I\'m sorry, but I have reviewed your offer and declined it',
      $transaction->get('responder_uid')->value
    );

    //Redirect the user back to their transactions view page without displaying anything
    $user_url = Url::fromRoute('hs_trade.user_view_transactions', ['user' => $this->currentUser->id()]);
    $user_path = $user_url->getInternalPath();
    $response = new RedirectResponse('/'.$user_path);
    $response->send();
    return[];
  }

  /**
   * Controlling method for hs_trade.transaction_counter route
   */
  public function counter($hs_trade_transaction) {
    //Load the transaction to be countered
    $transaction = $this->transactionManager->getTransaction($hs_trade_transaction);

    //Prepare controller data values to customize form generation and submission behavior
    $controller_data = [
      'transaction_id' => $hs_trade_transaction,
      'offer_type' => 'counter',
      'responder_uid' => $transaction->getResponder()->id(),
      'requester_uid' => $transaction->getRequester()->id(),
    ];

    //Transaction notifications are handled in the submitForm method of 'MakeOfferForm'

    //Load 'MakeOfferForm', pass in $controller_data, attach a library for styling, and display the form
    $form = $this->formBuilder->getForm('\Drupal\hs_trade\Form\MakeOfferForm', $controller_data);
    $form['#attached']['library'][] = 'hs_trade/trade-page';
    $form['#attributes']['class'][] = 'row';
    return[
      'form' => $form,
    ];
  }

  /**
   * Controlling method for hs_trade.transaction_confirm route
   */
  public function confirm($hs_trade_transaction) {

    //Load relevant transaction entity
    $transaction = $this->transactionManager->getTransaction($hs_trade_transaction);

    //Retrieve the responder, requester, their HobbyCoin balances, and the residual
    $responder = $transaction->getResponder();
    $requester = $transaction->getRequester();
    $responder_balance = $responder->get('hc_balance')->value;
    $requester_balance = $requester->get('hc_balance')->value;
    $residual = $transaction->getResidual();

    //Create RedirectResponse object to redirect users under various circumstances
    $user_url = Url::fromRoute('hs_trade.user_view_transactions', ['user' => $this->currentUser->id()]);
    $user_path = $user_url->getInternalPath();
    $user_response = new RedirectResponse('/'.$user_path);

    //Create RedirectResponse for HobbyCoin Purchase route
    $purchase_response = new RedirectResponse('/transaction/'.$hs_trade_transaction.'/purchase');

    //The access controller handles restricting user interaction, but checking the current user allows for custom behavior when the user is allowed
    //All following scenarios assume that the access controller appropriately forbade/allowed the user
    switch($this->currentUser->id()) {
      //If the requester is confirming
      case $transaction->getRequester()->id():
        //If the residual is greater than 0, the requester owes residual
        if($residual > 0) {
          if(abs($residual) > $requester_balance) {
            //If the requester owes HC but has too low of a balance, redirect to purchase page
            $purchase_response->send();
          }else{
            //Invalidate user transaction caches
            $this->cacheTagsInvalidator->invalidateTags(['hs_trade.user_transactions_view']);
            //Notify the responder that the requester has confirmed the transaction
            $this->transactionManager->notifyResponder($hs_trade_transaction);
            $this->transactionManager->sendTransactionPM(
              $hs_trade_transaction,
              'I have confirmed the transaction, and it awaits your final confirmation.',
              $requester->id()
            );
            //If the requester owed HC and has a sufficient balance, allow confirmation
            $transaction->setStatus('requester confirmed')->save();
            $user_response->send();
          }
        }else{
          //Invalidate user transaction caches
          $this->cacheTagsInvalidator->invalidateTags(['hs_trade.user_transactions_view']);
          //Notify the responder that the requester has confirmed the transaction
          $this->transactionManager->notifyResponder($hs_trade_transaction);
          $this->transactionManager->sendTransactionPM(
            $hs_trade_transaction,
            'I have confirmed the transaction, and it awaits your final confirmation.',
            $requester->id()
          );
          //If no residual is owed and no further action is required by the requester, allow confirmation
          $transaction->setStatus('requester confirmed')->save();
          $user_response->send();
        }

        break;
      //If the responder is confirming (final confirmation, so this is where all the action is)
      case $transaction->getResponder()->id():
        //If the residual is less than 0, the responder owes residual
        if($residual < 0) {
          if($responder_balance < abs($residual)) {
            //If the responder owes HC but has too low of a balance, redirect to purchase page
            $purchase_response->send();
          }else{
            //If the requester owed HC and has a sufficient balance, set the status, adjust user balances, and disable involved items
            $transaction->setStatus('confirmed')->save();
            $responder->set('hc_balance', ($responder_balance += $residual))->save();
            $requester->set('hc_balance', ($requester_balance -= $residual))->save();
            //Invalidate user transaction caches
            $this->cacheTagsInvalidator->invalidateTags(['hs_trade.user_transactions_view']);
            //Notify the requester that the responder has performed final confirmation in the message thread and via email
            $this->transactionManager->notifyRequester($hs_trade_transaction);
            $this->transactionManager->sendTransactionPM($hs_trade_transaction, 'I completed final confirmation marking the completion of our trade!', $responder->id());
            $this->transactionManager->mailTransactionSummary($hs_trade_transaction);
            //Set status of all items involved to 'Traded' and unpublish them
            //UNCOMMENT BEFORE PUSHING
            //$this->transactionManager->tradeItems($hs_trade_transaction);

            $user_response->send();
          }
        }else{
          //If no residual is owed and no further action is required by the responder, allow confirmation
          $transaction->set('status', 'confirmed')->save();
          $responder->set('hc_balance', ($responder_balance += $residual))->save();
          $requester->set('hc_balance', ($requester_balance -= $residual))->save();
          //Invalidate user transaction caches
          $this->cacheTagsInvalidator->invalidateTags(['hs_trade.user_transactions_view']);
          //Notify the requester that the responder has performed final confirmation in the message thread and via email
          $this->transactionManager->notifyRequester($hs_trade_transaction);
          $this->transactionManager->sendTransactionPM($hs_trade_transaction, 'I completed final confirmation marking the completion of our trade!', $responder->id());
          $this->transactionManager->mailTransactionSummary($hs_trade_transaction);
          //Set status of all items involved to 'Traded' and unpublish them
          //UNCOMMENT BEFORE PUSHING
          //$this->transactionManager->tradeItems($hs_trade_transaction);

          $user_response->send();
        }

        break;
      default:
        //Catches any potential unaccounted for scenarios and redirects the user to their transaction view
        $user_response->send();
    }
    //The controller is required to return something even though no markup is needed
    return [];
  }

  /**
   * Acts as a helper redirect route by sending a user to the relevant transaction's message thread
   */
  public function message($hs_trade_transaction){
    //Get the transaction's thread id
    $thread = $this->transactionManager->getTransaction($hs_trade_transaction)->get('message_thread')->value;
    //Generate a URL from the thread and send the user there
    $url = Url::fromRoute('entity.private_message_thread.canonical', ['private_message_thread' => $thread]);
    $response = new RedirectResponse($url->toString());
    $response->send();
    return[];
  }

}
