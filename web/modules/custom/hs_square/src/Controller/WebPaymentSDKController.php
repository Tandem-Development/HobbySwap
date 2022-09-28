<?php

namespace Drupal\hs_square\Controller;

use Drupal\Core\Controller\ControllerBase;

class WebPaymentSDKController extends ControllerBase{

  public function displayForm(){
    return[
      '#theme' => 'square__web_payment_form',
      '#attached' => [
        'library' => ['hs_square/square']
      ]
    ];
  }
}