<?php

namespace Drupal\hs_square\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\hs_square\SquareManagerInterface;
use Psr\Container\ContainerInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

class ManageCustomersController extends ControllerBase{

  protected $squareManager;

  public function __construct(SquareManagerInterface $squareManager){
    $this->squareManager = $squareManager;
  }

  public static function create(ContainerInterface $container){
    return new static(
      $container->get('hs_square.square_manager')
    );
  }

  public function displayCustomers(){

    $customers = $this->squareManager->retrieveCustomers()->getCustomers();
    $header = [
      $this->t('Name'),
      $this->t('HobbySwap UID'),
      $this->t('Square ID'),
      $this->t('Phone #'),
      $this->t('Email'),
      $this->t('Delete')
    ];
    $row = [];
    foreach($customers as $customer){
      $row[] = [
        $customer->getGivenName().' '.$customer->getFamilyName(),
        Link::fromTextAndUrl(
          $customer->getReferenceId(),
          Url::fromRoute('hs_square.view_customer', ['squareid' => $customer->getId()])
        ),
        $customer->getId(),
        $customer->getPhoneNumber(),
        $customer->getEmailAddress(),
        Link::fromTextAndUrl(
          'Delete',
          Url::fromRoute('hs_square.delete_customer', ['squareid' => $customer->getId()])
        )
      ];
    }
    return[
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $row,
      '#cache' => [
        'tags' => ['manage_square_customers'],
        'max-age' => 3600
      ]
    ];
  }

}