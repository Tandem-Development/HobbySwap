<?php

namespace Drupal\hs_square\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

class AddCardController{

  protected $squareManager;
  protected $currentUser;
  protected $configFactory;
  protected $subscriptionManager;

  public function __construct(){
    $this->squareManager = \Drupal::service('hs_square.square_manager');
    $this->currentUser = \Drupal::service('current_user');
    $this->configFactory = \Drupal::service('config.factory');
    $this->subscriptionManager = \Drupal::service('hs_user.subscription_manager');
  }

  public function addCard(){

    $json = file_get_contents('php://input');
    $data = json_decode($json);

    $api_response = $this->squareManager->addCardToCustomer($this->currentUser->id(), $data, $data->sourceId);
    return new JsonResponse([
      'data' => $api_response,
      'request' => $data,
      'method' => 'POST',
    ]);

  }

}