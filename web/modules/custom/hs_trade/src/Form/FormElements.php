<?php

namespace Drupal\hs_trade\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class FormElements extends FormBase{
  public function getFormId(){
    return 'hs_trade.form_elements';
  }

  public function buildForm(array $form, FormStateInterface $form_state){
    $form['textfield'] = [
      '#type' => 'textfield',
      '#title' => 'textfield',
      '#placeholder' => 'Text Field',
      '#attributes' => [
        'class' => ['inline']
      ]
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state){
    \Drupal::messenger()->addMessage('Form Submitted');
  }
}
