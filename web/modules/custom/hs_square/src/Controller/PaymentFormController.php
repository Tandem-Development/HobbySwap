<?php

namespace Drupal\hs_square\Controller;

use Drupal\Core\Controller\ControllerBase;

class PaymentFormController extends ControllerBase{

  public function render(){
    $payment_form = \Drupal::formBuilder()->getForm('\Drupal\hs_square\Form\PaymentForm');
    $add_card_form = \Drupal::formBuilder()->getForm('\Drupal\hs_square\Form\AddCardForm');

    return[
      'payment_form' => $payment_form,
      'add_card_form' => $add_card_form,
      '#attached' => [
        'library' => ['hs_square/payment-form']
      ]
    ];
  }

}