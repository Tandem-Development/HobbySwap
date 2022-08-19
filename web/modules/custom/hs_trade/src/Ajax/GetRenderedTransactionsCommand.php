<?php

namespace Drupal\hs_trade\Ajax;

use Drupal\Core\Ajax\CommandInterface;

class GetRenderedTransactionsCommand implements CommandInterface{

  private $incoming_transactions;
  private $outgoing_transactions;

  public function __construct($incoming_transactions, $outgoing_transactions){
    $this->incoming_transactions = $incoming_transactions;
    $this->outgoing_transactions = $outgoing_transactions;
  }

  public function render(){
    return[
      'command' => 'getRenderedTransactions',
      'incoming_transactions' => $this->incoming_transactions,
      'outgoing_transactions' => $this->outgoing_transactions,
    ];
  }

}
