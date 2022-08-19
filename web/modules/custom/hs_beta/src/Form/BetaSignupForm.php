<?php

namespace Drupal\hs_beta\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManager;

class BetaSignupForm extends FormBase{

  public function getFormId() {
    return 'hs_beta.signup';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      //'#default_value' => 'sam.dufrin@gmail.com', /*Remove after development work*/
      '#required' => TRUE,
    ];
    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      //'#default_value' => 'Samuel', /*Remove after development work*/
      '#required' => TRUE,
    ];
    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      //'#default_value' => 'Dufrin', /*Remove after development work*/
      '#required' => TRUE,
    ];
    $form['phone'] = [
      '#type' => 'number',
      '#title' => $this->t('Phone Number'),
      //'#default_value' => '5172819641', /*Remove after development work*/
      '#required' => TRUE,
    ];
    $form['interview'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Why would you like early access to HobbySwap?'),
      '#default_value' => 'This part of the application could greatly determine whether or not it is accepted. Take your time with this one ;)',
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Submit Application',
      '#attributes' => [
        'class' => ['h3-responsive']
      ],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $user_manager = \Drupal::entityTypeManager()->getStorage('user');
    $query = $user_manager->getQuery();
    $results = $query->execute();
    $users = $user_manager->loadMultiple($results);
    $username = $form_state->getValue('first_name').'.'.$form_state->getValue('last_name');

    foreach($users as $user){
      if($form_state->getValue('email') == $user->get('mail')->value){
        $form_state->setErrorByName('email', $this->t('This email has already been used'));
      }
    }

  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

    //Create new pending user and retrieve ID for email action links
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $new_user = $user_storage->create([
      'name' => str_replace(' ', '', strtolower($form_state->getValue('first_name').'.'.$form_state->getValue('last_name').rand(0, 9).rand(0, 9).rand(0, 9))),
      'mail' => $form_state->getValue('email'),
      'status' => FALSE,
    ])->save();
    $query = $user_storage->getQuery();
    $result = $query->condition('mail', $form_state->getValue('email'))->execute();
    $user = $user_storage->load(reset($result));
    $beta_base_path = 'http://'.\Drupal::request()->getHost().'/beta/'.$user->id().'/';

    $mail_manager = \Drupal::service('plugin.manager.mail');
    $mail_result = $mail_manager->mail('hs_beta', 'register', $form_state->getValue('email'), 'en', [], NULL, TRUE);
    if($mail_result == TRUE){
      \Drupal::messenger()->addMessage($this->t('Your application has been submitted, and we sent a confirmation email to your inbox. If you don\'t see it, check your spam folder.'));
    }else{
      \Drupal::messenger()->addError($this->t('Your application failed to submit. Contact a site administrator if the problem persists'));
    }

    $params = [
      'email' => $form_state->getValue('email'),
      'name' => $form_state->getValue('first_name').' '.$form_state->getValue('last_name'),
      'phone' => $form_state->getValue('phone'),
      'interview' => $form_state->getValue('interview'),
      'accept_path' => $beta_base_path.'accept',
      'reject_path' => $beta_base_path.'reject',
    ];
    $site_address = \Drupal::config('system.site')->get('mail');
    $mail_manager->mail('hs_beta', 'app_action', $site_address, 'en', $params, NULL, TRUE);

  }

}
