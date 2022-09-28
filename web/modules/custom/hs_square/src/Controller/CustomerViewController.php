<?php

namespace Drupal\hs_square\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\hs_square\SquareManagerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

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

  public function deleteCustomer($squareid){
    $api_response = $this->squareManager->deleteCustomer($squareid);
    if($api_response->isSuccess()){
      \Drupal::messenger()->addStatus('Customer with ID "'.$squareid.'" has been deleted');
    }else{
      \Drupal::messenger()->addError('Failed to delete customer with ID: '.$squareid);
    }

    $redirect_url = Url::fromRoute('hs_square.manage_customers');
    $redirect_path = $redirect_url->getInternalPath();
    $response = new RedirectResponse('/'.$redirect_path);
    $response->send();
    return[];
  }

}