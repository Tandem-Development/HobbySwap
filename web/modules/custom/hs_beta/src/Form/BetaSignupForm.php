<?php

namespace Drupal\hs_beta\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Psr\Container\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * This form is built in the block 'hobbyswapbetasignupform' allowing it to be inserted in various places on production
 * It collects a user's information and creates a blocked account for them awaiting further action from an admin
 */
class BetaSignupForm extends FormBase{

  protected $entityTypeManager;
  protected $messenger;
  protected $mailManager;
  protected $configFactory;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    MessengerInterface $messenger,
    MailManagerInterface $mailManager,
    ConfigFactoryInterface $configFactory
  ){
    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
    $this->mailManager = $mailManager;
    $this->configFactory = $configFactory;
  }

  public static function create(ContainerInterface $container){
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('plugin.manager.mail'),
      $container->get('config.factory')
    );
  }

  public function getFormId() {
    return 'hs_beta.signup';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    //Build the form with the necessary fields
    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];
    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
    ];
    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
    ];
    $form['phone'] = [
      '#type' => 'textfield',
      '#maxlength' => '12',
      '#size' => 13,
      '#attached' => [
        'library' => ['hs_user/phone-widget-formatter']
      ],
      '#attributes' => [
        'class' => ['input--phone'],
        'placeholder' => '000-000-0000'
      ],
    ];
    $form['interview'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Why would you like early access to HobbySwap?'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => $this->t('This part of the application could greatly determine whether or not it is accepted. Take your time with this one ;)')
      ]
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit Application'),
      '#attributes' => [
        'class' => ['h5-responsive']
      ],
    ];

    $form['#attributes']['class'] = ['form--widget col-md-6 offset-md-3'];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    //Check existing users to make sure this email hasn't been used before
    $query = $this->entityTypeManager
      ->getStorage('user')
      ->getQuery()
      ->condition('mail', $form_state->getValue('email'));
    if(!empty($query->execute())){
      $form_state->setErrorByName('email', $this->t('This email has already been used'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

    //Create a new blocked user with the data passed from the form
    $user_storage = $this->entityTypeManager->getStorage('user');
    $phone_last_3 = substr($form_state->getValue('phone'), 7, 3);
    $username = $form_state->getValue('first_name').'.'.$form_state->getValue('last_name').$phone_last_3;
    $new_user = $user_storage->create([
      'name' => str_replace(' ', '', strtolower($username)),
      'mail' => $form_state->getValue('email'),
      'field_first_name' => str_replace(' ', '', $form_state->getValue('first_name')),
      'field_last_name' => str_replace(' ', '', $form_state->getValue('last_name')),
      'field_phone' => $form_state->getValue('phone'),
      'status' => FALSE,
    ]);
    $new_user->save();
    $beta_base_path = 'https://'.\Drupal::request()->getHost().'/beta/'.$new_user->id().'/';

    //Notify the user that their application has been received
    $mail_result = $this->mailManager->mail('hs_beta', 'register', $form_state->getValue('email'), 'en', [], NULL, TRUE);
    if($mail_result == TRUE){
      $this->messenger->addMessage($this->t('Your application has been submitted, and we sent a confirmation email to your inbox. If you don\'t see it, check your spam folder.'));
    }else{
      $this->messenger->addError($this->t('Your application failed to submit. Contact a site administrator if the problem persists'));
    }

    //Prepare the parameters to build an admin action email
    $params = [
      'email' => $form_state->getValue('email'),
      'name' => $form_state->getValue('first_name').' '.$form_state->getValue('last_name'),
      'phone' => $form_state->getValue('phone'),
      'interview' => $form_state->getValue('interview'),
      'accept_path' => $beta_base_path.'accept',
      'reject_path' => $beta_base_path.'reject',
    ];
    //Send the applications to the site email address
    $site_address = $this->configFactory->get('system.site')->get('mail');
    $this->mailManager->mail('hs_beta', 'app_action', $site_address, 'en', $params, NULL, TRUE);

  }

}
