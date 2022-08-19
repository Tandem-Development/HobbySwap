<?php

namespace Drupal\hs_credit\Controller;

use Drupal\Core\Controller\ControllerBase;

class BalanceResetController extends ControllerBase {

  public function balanceReset() {
    $query = $this->entityTypeManager()->getStorage('user')->getQuery();
    $results = $query->execute();
    $users = $this->entityTypeManager()->getStorage('user')->loadMultiple($results);
    foreach($users as $u) {
      $u->set('hc_balance', 0);
      $u->save();
      ksm($u->getDisplayName().': '.$u->get('hc_balance')->value);
    }
    return[
      '#markup' => 'User balances have been reset',
    ];
  }

}
