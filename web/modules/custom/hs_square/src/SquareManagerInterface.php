<?php

namespace Drupal\hs_square;

interface SquareManagerInterface{

  public function openClient();

  public function handleResponse($api_response);

  public function addCardToCustomer($uid, $card_data, $source_id);

  public function retrieveCustomerByUid($uid);

  public function retrieveCustomers();

  public function createCustomer($uid);

  public function deleteCustomer($squareid);

  public function retrieveThisLocationId();

  public function createPayment($source_id, $amount, $uid);

  public function getDefaultCardIdByUid($uid);

}