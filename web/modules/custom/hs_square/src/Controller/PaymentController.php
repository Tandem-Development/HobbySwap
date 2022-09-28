<?php

namespace Drupal\hs_square\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;

class PaymentController{

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

  public function payment(){

    $json = file_get_contents('php://input');
    $data = json_decode($json);
    $paymentType = $data->paymentType;

    switch ($paymentType){
      case 'subscription':
        $plan_data = $this->subscriptionManager->getPlanData($data->subscriptionPlan);
        $is_card_saved = $data->isCardSaved;
        $api_response = $this->squareManager->createPayment($data->sourceId, $plan_data['cost'], $this->currentUser->id());

        if($api_response->isSuccess()){
          $this->subscriptionManager->setUserSubscription($plan_data['name']);
          if($is_card_saved){
            $payment_id = $api_response->getResult()->getPayment()->getId();
            $this->squareManager->addCardToCustomer(\Drupal::currentUser()->id(), $data, $payment_id);
          }
        }
        return new JsonResponse(['data' => $api_response, 'method' => 'POST']);
        break;
      case 'hobbycredit':
        //TODO: HobbyCredit purchase processing
        break;
      default:
        return new JsonResponse([
          'error' => 'Invalid payment type selected',
          'method' => 'POST',
        ]);
    }
  }

}