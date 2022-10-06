<?php

namespace Drupal\hs_square\Form;

use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\FieldOverride;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Psr\Container\ContainerInterface;

/**
 * This form is responsible for implementing Square's Web Payment SDK form, and in particular,
 * using the SDK to add cards to customer profiles
 */
class AddCardForm extends FormBase{

  //Boilerplate dependency injection
  protected $configFactory;

  public function __construct(ConfigFactoryInterface $configFactory){
    $this->configFactory = $configFactory;
  }

  public static function create(ContainerInterface $container){
    return new static(
      $container->get('config.factory')
    );
  }

  public function getFormId(){
    return 'hs_square.add_card_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state){
    //A fieldset to nicely contain the form
    $form['card_details'] = [
      '#type' => 'fieldset',
      '#title'  => $this->t('Save a card to your account'),
    ];
    //Implements a custom form field widget from the 'address' module. Based on Square's requirements most fields are marked as optional
    //The country code is automatically overridden to 'US' as HobbySwap exists on a .us domain
    $form['card_details']['billing_address'] = [
      '#type' => 'address',
      '#available_countries' => ['US'],
      '#default_value' => [
        'country_code' => 'US'
      ],
      '#field_overrides' => [
        AddressField::ORGANIZATION => FieldOverride::HIDDEN,
        Addressfield::ADDRESS_LINE1 => FieldOverride::OPTIONAL,
        Addressfield::ADDRESS_LINE2 => FieldOverride::OPTIONAL,
        Addressfield::LOCALITY => FieldOverride::OPTIONAL,
        Addressfield::ADMINISTRATIVE_AREA=> FieldOverride::OPTIONAL,
        Addressfield::POSTAL_CODE => FieldOverride::OPTIONAL,
      ],
      '#required' => TRUE,
    ];
    //Append the Web SDK form as a part of the form
    $form['card_details']['square_card_input'] = [
      '#theme' => 'square__web_card_form',
    ];

    //Attach this environment's location and app IDs so that they can be accessed by the SDK aka square.js
    $config = $this->configFactory->get('hs_square.settings');
    $form['#attached'] = [
      'drupalSettings'  => [
        'locationId' => $config->get('location_id'),
        'appId' => $config->get('application_id'),
      ]
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state){
    /** Submission logic is handled Drupal\hs_square\Controller\AddCardController */
  }

}