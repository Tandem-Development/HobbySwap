<?php

/**
 * This controller is responsible for displaying a user's:
 * - pending, accepted, and countered request transactions
 * - pending, accepted, declined, and countered offer transactions
 */

namespace Drupal\hs_trade\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\hs_trade\TransactionManagerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Access\CsrfTokenGenerator;

class UserTransactionViewController extends ControllerBase {

  /**
   * @var \Drupal\hs_trade\TransactionManagerInterface
   */
  private $transactionManager;
  /**
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  private $csrfToken;

  function __construct( TransactionManagerInterface $transactionManager, CsrfTokenGenerator $csrfToken) {
    $this->transactionManager = $transactionManager;
    $this->csrfToken = $csrfToken;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('hs_trade.transaction_manager'),
      $container->get('csrf_token')
    );
  }

  /**
   * UserTransactionViewController::viewUserTransactions is the default controller for the 'hs_trade.user_view_transactions' route
   * - $user is the UID of currently viewed user account
   * - Leaves entity queries up to the getUserTransactions() helper method and just passes data to themes
   */
  public function viewUserTransactions($user) {

    $url = Url::fromRoute('hs_trade.ajax_callback', ['op' => 'get_rendered_transactions']);
    $token = $this->csrfToken->get($url->getInternalPath());
    $url->setOptions(['query' => ['token' => $token, 'user' => $user]]);

    //Query the IDs of incoming and outgoing transactions before passing
    $transactionStorage = $this->entityTypeManager()->getStorage('hs_trade_transaction');
    $incoming = $transactionStorage->getQuery()
      ->condition('responder_uid', $user)
      ->sort('changed', 'DESC')
      ->execute();
    $outgoing = $transactionStorage->getQuery()
      ->condition('requester_uid', $user)
      ->sort('changed', 'DESC')
      ->execute();

    //Unread transactions have been viewed, so clear notifications
    $current_user = $this->entityTypeManager()->getStorage('user')->load($user);
    $current_user->set('unread_transactions', NULL)->save();

    return[
      '#prefix' => '<div class="hs--transactions-tabs">
                      <h2 class="hs--incoming--label active"><span>'.count($incoming).'</span> INCOMING OFFERS</h2>
                      <h2 class="hs--outgoing--label"><span>'.count($outgoing).'</span> OUTGOING OFFERS</h2>
                    </div>',
      'incoming_offers' => [
        '#prefix' => '<div class="hs--incoming-container">',
        $this->transactionManager->renderTransactions($incoming, 'incoming'),
        '#suffix' => '</div>',
      ],
      'outgoing_offers' => [
        '#prefix' => '<div class="hs--outgoing-container">',
        $this->transactionManager->renderTransactions($outgoing, 'outgoing'),
        '#suffix' => '</div>',
      ],
      '#attached' => [
        'library' => ['hs_trade/user-transactions-view'],
        'drupalSettings' => ['getRenderedTransactions' => $url->toString()]
      ],
      '#cache' => [
        'tags' => ['hs_trade.user_transactions_view'],
      ],
    ];
  }

  /**
   * UserTransactionViewController::alias is a redirect controller for the user transaction view
   * - It exists so that links can be created without the need to retrieve the current user
   */
  public function userTransactionsAlias(){
    //Redirect to the current user's transaction view
    $user_url = Url::fromRoute('hs_trade.user_view_transactions', ['user' => \Drupal::currentUser()->id()]);
    $user_path = $user_url->getInternalPath();
    $response = new RedirectResponse('/'.$user_path);
    $response->send();
    return[];
  }

  /**
   * UserTransactionViewController::viewUserTransactionsAccess is necessary to prevent users from viewing other's transactions
   * - One exception is made for admins who can view anyone's transactions
   */
  public function viewUserTransactionsAccess(AccountInterface $account, $user){
    //Only allow the current user to view their transactions
    if($account->id() === $user && $account->isAuthenticated()){
      return AccessResult::allowed();
    }else if(in_array('administrator', $account->getRoles())){
      //Allow admins to view any user's transactions view
      return AccessResult::allowed();
    }else{
      return AccessResult::forbidden();
    }
  }

}
