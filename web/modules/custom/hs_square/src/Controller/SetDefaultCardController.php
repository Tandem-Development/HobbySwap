<?php

namespace Drupal\hs_square\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\hs_square\SquareManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Http\RequestStack;

/**
 * Acts a wrapper for the SquareManager::setDefaultCard method and makes it easy to insert anywhere via links
 * This is a functional route and isn't meant to display any content
 */
class SetDefaultCardController extends ControllerBase{

  protected $squareManager;
  protected $currentUser;
  protected $messenger;
  protected $request;

  public function __construct(SquareManagerInterface $squareManager, AccountProxyInterface $currentUser, MessengerInterface $messenger, RequestStack $request){
    $this->squareManager = $squareManager;
    $this->currentUser = $currentUser;
    $this->messenger = $messenger;
    $this->request = $request;
  }

  public static function create(ContainerInterface $container){
    return new static(
      $container->get('hs_square.square_manager'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('request_stack')
    );
  }

  public function setDefaultCard($index){
    //Retrieve the current user's customer profile and set their default card index
    $customer = $this->squareManager->retrieveCustomerByUid($this->currentUser->id());
    $api_response = $this->squareManager->setDefaultCard($customer, $index);
    if(!$api_response->isSuccess()){
      $this->messenger->addError('Failed to set default card');
    }
    //Redirect to the previous page
    $url = $this->request->getCurrentRequest()->headers->get('referer');
    $response = new RedirectResponse($url);
    $response->send();
    return [];
  }

}