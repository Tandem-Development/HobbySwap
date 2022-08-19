<?php

/**
 * This controller is responsible for returning 'PurchaseHobbyCoinForm' in its own route
 * - Displaying the form through a controller gives more control over data passed to the form
 * - This should also make it easier to implement real payment processing later on
 */

namespace Drupal\hs_trade\Controller;

use Drupal\Core\Controller\ControllerBase;

class PurchaseHobbyCoinController extends ControllerBase {

  /**
   * PurchaseHobbyCoinController::purchase is the controlling method for the 'hs_trade.purchase_hobbycoin' route
   * - The transaction ID slug is simply passed to the form upon retrieval before displaying the form
   */
  public function purchase($hs_trade_transaction) {

    //An array of transaction data passed from the controller to the form
    $controller_data = [
      'transaction_id' => $hs_trade_transaction
    ];

    //Retrieve the 'PurchaseHobbyCoinForm' form and pass it $controller_data
    $form = \Drupal::formBuilder()->getForm('\Drupal\hs_trade\Form\PurchaseHobbyCoinForm', $controller_data);

    //Display the form
    return[
      'form' => $form,
    ];
  }

}
