<?php

namespace Drupal\hs_trade\Entity\Controller;

use Drupal\Core\Controller\ControllerBase;

class TransactionViewBuilder extends ControllerBase{

  public function build($transaction_id){
    return[
      '#markup' => $transaction_id
    ];
  }

}
