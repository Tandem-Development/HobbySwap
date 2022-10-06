<?php

namespace Drupal\hs_square\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\hs_square\SquareManagerInterface;
use Psr\Container\ContainerInterface;

/**
 * Displays a customer's information for admins
 */
class CustomerViewController extends ControllerBase{

  protected $squareManager;

  public function __construct(SquareManagerInterface $squareManager){
    $this->squareManager = $squareManager;
  }

  public static function create(ContainerInterface $container){
    return new static(
      $container->get('hs_square.square_manager')
    );
  }

  public function viewCustomerTitle($squareid){
    return $squareid;
  }

  public function viewCustomer($squareid){
    //Retrieves the user's customer object and passes it to the 'customer' template
    $api_response = $this->squareManager->openClient()->getCustomersApi()->retrieveCustomer($squareid);
    if($api_response->isSuccess()){
      $customer = $api_response->getResult()->getCustomer();
      $cards = $customer->getCards();
      return[
        '#theme' => 'customer',
        '#customer' => $customer,
        '#cards' => $cards,
        '#attached' => [
          'library' => ['hs_square/customer-view']
        ]
      ];
    }else{
      die($api_response->getErrors());
    }
  }

}