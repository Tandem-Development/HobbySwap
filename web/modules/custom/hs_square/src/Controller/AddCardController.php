<?php

namespace Drupal\hs_square\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * AddCardController receives a JSON request from square.js, processes that request, and makes the necessary API calls
 */
class AddCardController{

  public function addCard(){
    //Get the request data and decode the JSON
    $json = file_get_contents('php://input');
    $data = json_decode($json);

    //Retrieve the current user's customer profile and add the entered to their profile
    $square_manager = \Drupal::service('hs_square.square_manager');
    $customer = $square_manager->retrieveCustomerByUid(\Drupal::currentUser()->id());
    $api_response = $square_manager->addCardToCustomer($customer, $data, $data->sourceId);

    //Return the response from Square to square.js for error handling
    return new JsonResponse([
      'data' => $api_response,
      'request' => $data,
      'method' => 'POST',
    ]);
  }
}