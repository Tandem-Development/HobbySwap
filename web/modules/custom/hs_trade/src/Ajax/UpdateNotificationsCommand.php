<?php

namespace Drupal\hs_trade\Ajax;

use Drupal\Core\Ajax\CommandInterface;

class UpdateNotificationsCommand implements CommandInterface{

  private $unread_transactions;
  private $unread_threads;

  public function __construct($unread_transactions, $unread_threads){
    $this->unread_transactions = $unread_transactions;
    $this->unread_threads = $unread_threads;
  }

  public function render(){
    return[
      'command' => 'updateNotifications',
      'unread_transactions' => $this->unread_transactions,
      'unread_threads' => $this->unread_threads,
    ];
  }
}
