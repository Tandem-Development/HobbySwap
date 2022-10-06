<?php

namespace Drupal\hs_square\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Psr\Container\ContainerInterface;

/**
 * This controller is implemented to include the payment and add card forms on the same page.
 * Other than attaching an asset library, no additional processing is happening
 */
class PaymentFormController extends ControllerBase{

  protected $formBuilder;

  public function __construct(FormBuilderInterface $formBuilder){
    $this->formBuilder = $formBuilder;
  }

  public static function create(ContainerInterface $container){
    return new static(
      $container->get('form_builder')
    );
  }

  public function render(){
    $payment_form = \Drupal::formBuilder()->getForm('\Drupal\hs_square\Form\PaymentForm');
    $add_card_form = \Drupal::formBuilder()->getForm('\Drupal\hs_square\Form\AddCardForm');

    return[
      'payment_form' => $payment_form,
      'add_card_form' => $add_card_form,
      '#attached' => [
        'library' => ['hs_square/payment-form']
      ]
    ];
  }

}