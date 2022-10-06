<?php

namespace Drupal\hs_square;

use Square\Models\Customer;

interface SquareManagerInterface{

  public function openClient();

  public function retrieveThisLocationId();

  public function handleResponse($api_response);

  public function addCardToCustomer(Customer $customer, $card_data, $source_id);

  public function retrieveCustomerByUid($uid);

  public function retrieveCustomers();

  public function createCustomer($uid);

  public function deleteCustomer($squareid);

  public function createPayment($source_id, $amount, Customer $customer);

  public function getDefaultCardIndex(Customer $customer);

  public function setDefaultCard(Customer $customer, $index);

}