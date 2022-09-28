<?php

namespace Drupal\hs_square\Form;

use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\FieldOverride;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class AddCardForm extends FormBase{

  public function getFormId(){
    return 'hs_square.add_card_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state){
    $form['card_details'] = [
      '#type' => 'fieldset',
      '#title'  => $this->t('Save a card to your account'),
    ];
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
    $form['card_details']['square_card_input'] = [
      '#theme' => 'square__web_card_form',
    ];

    $config_factory = \Drupal::configFactory()->get('hs_square.settings');

    $form['#attached'] = [
      'drupalSettings'  => [
        'locationId' => $config_factory->get('location_id'),
        'appId' => $config_factory->get('application_id'),
      ]
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state){
    //Submission logic is handled Drupal\hs_square\Controller\AddCardController
  }

}