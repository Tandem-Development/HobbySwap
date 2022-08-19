<?php

/**
 * This controller is responsible for the following:
 * - 'hs_trade.transaction_accept' route default controller for handling offer acceptance
 * - 'hs_trade.transaction_decline' route default controller for handling offer declination
 * - 'hs_trade.transaction_counter' route default controller for handling counter offers
 */

namespace Drupal\hs_trade\Controller;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Psr\Container\ContainerInterface;
use Drupal\hs_trade\TransactionManagerInterface;

class RequestInteractController extends ControllerBase {

  /**
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   * Boilerplate for cache invalidation service
   */
  private $cacheTagsInvalidator;
  /**
   * @var \Drupal\hs_trade\TransactionManagerInterface
   */
  private $transactionManager;

  function __construct(CacheTagsInvalidatorInterface $cacheTagsInvalidator, TransactionManagerInterface $transactionManager) {
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
    $this->transactionManager = $transactionManager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache_tags.invalidator'),
      $container->get('hs_trade.transaction_manager')
    );
  }


  /**
   * RequestInteractController::interactionAccess is the access controller for all stage 1 request interaction routes
   * - $account is the account object for the current user
   * - $hs_trade_transaction is the ID of the relevant transaction
   * - Is applied to all three stage 1 interaction routes ('decline', 'accept', and 'counter')
   * - Determines access based on current user and transaction status
   */
  public function interactionAccess(AccountInterface $account, $hs_trade_transaction) {
    //Retrieve relevant transaction and get its status and responder_uid
    $transaction = $this->transactionManager->getTransaction($hs_trade_transaction);
    $status = $transaction->getStatus();

    //Don't even consider transaction status if the current user doesn't have the right permission and isn't the responder
    if($account->hasPermission('interact transaction requests') && $account->id() === $transaction->getResponder()->id()){
      //Simple way of managing all possible transaction statuses
      switch ($status){
        //Allow interaction if pending or if pending counter offer
        case 'pending':
        case 'countered':
          return AccessResult::allowed();
          break;
        //Forbid interaction if closed with declination
        case 'declined':
          return AccessResult::forbidden();
          break;
        default:
          return AccessResult::forbidden();
      }
    }else{
      //Forbid if user doesn't have permission or isn't the responder
      return AccessResult::forbidden();
    }
  }


  /**
   * RequestInteractController::declinationAccess is the sole access controller for a transaction's declination
   * - $account is the account object for the current user
   * - $hs_trade_transaction is the ID of the relevant transaction
   * - This is necessary as the responder should be able to decline at any point during the transaction until it's been finally confirmed
   * - No other interaction controller has unique needs like this
   */
  public function declinationAccess(AccountInterface $account, $hs_trade_transaction) {
    //Retrieve relevant transaction
    $transaction = $this->transactionManager->getTransaction($hs_trade_transaction);

    //Allow the responder to decline at any point unless the transaction has been finally confirmed
    if($account->id() === $transaction->getResponder()->id() && $transaction->getStatus() !== 'confirmed') {
      return AccessResult::allowed();
    }else{
      return AccessResult::forbidden();
    }

  }


  /**
   * RequestInteractController::confirmationAccess is the access controller for the request confirmation route (stage 2)
   * - $account is the account object for the current user
   * - $hs_trade_transaction is the ID of the relevant transaction
   * - Is applied to the 'confirm' interaction route
   * - Determines access based on current user and transaction status
   */
  public function confirmationAccess(AccountInterface $account, $hs_trade_transaction) {
    //Retrieve relevant transaction
    $transaction = $this->transactionManager->getTransaction($hs_trade_transaction);

    //Don't even consider transaction status if the current user doesn't have the right permission
    if($account->hasPermission('interact transaction requests')){
      switch ($transaction->getStatus()){
        //After acceptance, the requester must be the first to confirm
        //Once the requester has confirmed the transaction, the responder must complete final confirmation
        case 'declined':
        case 'requester confirmed':
          if($account->id() === $transaction->getResponder()->id()) {
            return AccessResult::allowed();
          }else{
            return AccessResult::forbidden();
          }
          break;
        //After acceptance, the requester must be the first to confirm
        case 'accepted':
          if($account->id() === $transaction->getRequester()->id()) {
            return AccessResult::allowed();
          }else{
            return AccessResult::forbidden();
          }
          break;
        //Restrict all transaction interaction once the transaction has been finally confirmed
        case 'confirmed':
          return AccessResult::forbidden();
          break;
        default:
          return AccessResult::forbidden();
      }
    }else{
      //Forbid if user doesn't have permission ('interact transaction requests')
      return AccessResult::forbidden();
    }
  }



  /**
   * RequestInteractController::acceptRequest is the default controller for the 'hs_trade.transaction_accept' route
   * - Handles functionality of a user accepting an offer request
   * - $hs_trade_transaction is the ID of the relevant transaction
   * - Purely functional, displays nothing
   */
  public function acceptRequest($hs_trade_transaction) {
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
    $user_url = Url::fromRoute('hs_trade.user_view_transactions', ['user' => \Drupal::currentUser()->id()]);
    $user_path = $user_url->getInternalPath();
    $response = new RedirectResponse('/'.$user_path);
    $response->send();

    return[];
  }



  /**
   * RequestInteractController::declineRequest is the default controller for the 'hs_trade.transaction_decline' route
   * - Handles functionality for a user declining an offer request
   * - $hs_trade_transaction is the ID of the relevant transaction
   * - Purely functional, displays nothing
   */
  public function declineRequest($hs_trade_transaction) {
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
    $user_url = Url::fromRoute('hs_trade.user_view_transactions', ['user' => \Drupal::currentUser()->id()]);
    $user_path = $user_url->getInternalPath();
    $response = new RedirectResponse('/'.$user_path);
    $response->send();
    return[];
  }



  /**
   * RequestInteractController::counterRequest is the default controller for the 'hs_trade.transaction_counter' route
   * - Facilitates the process of a user making a counter offer
   * - $hs_trade_transaction is the ID of the relevant transaction
   * - Displays the form '\Drupal\hs_trade\Form\MakeOfferForm'
   * - Form submission is handled in '\Drupal\hs_trade\Form\MakeOfferForm'
   */
  public function counterRequest($hs_trade_transaction) {
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
    $form = \Drupal::formBuilder()->getForm('\Drupal\hs_trade\Form\MakeOfferForm', $controller_data);
    $form['#attached']['library'][] = 'hs_trade/trade-page';
    $form['#attributes']['class'][] = 'row';
    return[
      'form' => $form,
    ];
  }



  /**
   * RequestInteractController::confirmRequest is the default controller for the 'hs_trade.transaction_confirm' route
   * - All final item/transaction processing happens in this method
   * - Behaves differently depending on current user and transaction status
   * - Upon final confirmation:
   *  - All involved items will be unpublished and marked as 'Traded'
   *  - The transaction status will be set to 'confirmed' and closed to all user interaction
   *  - User HC balances will be adjusted to account for value imbalances
   */
  public function confirmationRequest($hs_trade_transaction) {

    //Load relevant transaction entity
    $transaction = $this->transactionManager->getTransaction($hs_trade_transaction);

    //Retrieve the responder, requester, their HobbyCoin balances, and the residual
    $responder = $transaction->getResponder();
    $requester = $transaction->getRequester();
    $responder_balance = $responder->get('hc_balance')->value;
    $requester_balance = $requester->get('hc_balance')->value;
    $residual = $transaction->getResidual();

    //Create RedirectResponse object to redirect users under various circumstances
    $user_url = Url::fromRoute('hs_trade.user_view_transactions', ['user' => \Drupal::currentUser()->id()]);
    $user_path = $user_url->getInternalPath();
    $user_response = new RedirectResponse('/'.$user_path);

    //Create RedirectResponse for HobbyCoin Purchase route
    $purchase_response = new RedirectResponse('/transaction/'.$hs_trade_transaction.'/purchase');

    //The access controller handles restricting user interaction, but checking the current user allows for custom behavior when the user is allowed
    //All following scenarios assume that the access controller appropriately forbade/allowed the user
    switch(\Drupal::currentUser()->id()) {
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
            //!!!COMMENTED OUT FOR DEVELOPMENT PURPOSES!!!
            //Set status of all items involved to 'Traded' and unpublish them
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
          //!!!COMMENTED OUT FOR DEVELOPMENT PURPOSES!!!
          //Set status of all items involved to 'Traded' and unpublish them
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

}
