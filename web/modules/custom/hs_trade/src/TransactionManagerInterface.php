<?php

namespace Drupal\hs_trade;

interface TransactionManagerInterface{

  public function getTransaction($tid);

  public function tradeItems($tid);

  public function notifyResponder($tid);

  public function notifyRequester($tid);

  public function calculateResidual(array $responder_item_ids, array $requester_item_ids);

  public function makeNewOffer(array $data);

  public function makeCounterOffer(array $data);

  public function isItemInTransaction($nid);

  public function getPMTransactionId($pmid);

  public function sendTransactionPM($tid, $message, $owner);

  public function renderTransactions(array $tids, $mode);

  public function mailTransactionSummary($tid);
}
