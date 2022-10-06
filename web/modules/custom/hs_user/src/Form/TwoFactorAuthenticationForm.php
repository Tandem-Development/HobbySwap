<?php

namespace Drupal\hs_user\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Session\AccountInterface;
use Psr\Container\ContainerInterface;

/**
 * A form that handles 2FA for new users and integrates with the Twilio API
 */
class TwoFactorAuthenticationForm extends ConfigFormBase{

  private $code_config;
  protected $currentUser;
  protected $messenger;
  protected $time;

  public function __construct(ConfigFactoryInterface $configFactory, AccountProxyInterface $currentUser, MessengerInterface $messenger, TimeInterface $time){
    parent::__construct($configFactory);
    $this->code_config = $this->configFactory->getEditable('hs_user.authentication_codes');
    $this->currentUser = $currentUser;
    $this->messenger = $messenger;
    $this->time = $time;
  }

  public static function create(ContainerInterface $container){
    return new static(
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('datetime.time')
    );
  }

  //Only allow authentication by users who haven't already done so
  public function customAccess(AccountInterface $account){
    if(in_array('2fa', $account->getRoles()) || $account->isAnonymous()){
      return AccessResult::forbidden();
    }else{
      return AccessResult::allowed();
    }
  }

  public function getFormId(){
    return 'hs_user.two_factor_authentication_form';
  }

  public function getEditableConfigNames(){
    return 'hs_user.authentication_codes';
  }

  public function buildForm(array $form, FormStateInterface $form_state){
    //Manually add form title
    $form['title'] = [
      '#markup' => '<h3 class="hs-color-black">Two-Factor Authentication</h3>',
    ];
    $form['#attributes']['class'] = ['form--widget', 'col-md-6', 'offset-md-3',];
    //The resend container contains the necessary fields to send a new code to the input phone number
    $form['resend_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Resend Code'),
    ];
    $form['resend_container']['phone_number'] = [
      '#type' => 'textfield',
      '#default_value' => User::load($this->currentUser->id())->get('field_phone')->value,
      '#maxlength' => '12',
      '#size' => 13,
      '#description' => $this->t('If you accidentally registered your account with the wrong phone number, change it here to receive your authentication code.'),
      '#attached' => [
        'library' => ['hs_user/phone-widget-formatter']
      ],
      '#attributes' => [
        'class' => ['input--phone'],
        'placeholder' => '000-000-0000'
      ],
    ];
    $form['resend_container']['resend'] = [
      '#type' => 'submit',
      '#value' => 'Resend',
      '#attributes' => [
        'class' => ['btn-secondary', 'button--danger']
      ]
    ];
    $form['code_input'] = [
      '#type' => 'textfield',
      '#description' => $this->t('Enter the authentication code you received on your device'),
      '#attributes' => [
        'placeholder' => $this->t('Code')
      ]
    ];

    $form['authenticate'] = [
      '#type' => 'submit',
      '#value' => 'Authenticate'
    ];

    //Alter the form based on the existence of a code for the current user
    if(empty($this->code_config->getRawData()['codes'][$this->currentUser->id()])){
      $form['2fa_description'] = [
        '#markup' => '<p>Before your account can be fully activated, you must complete two-factor authentication.
                      Clicking the button below will send an authentication code to the phone number linked with your account.</p>'
      ];
      $form['phone_number'] = $form['resend_container']['phone_number'];
      unset($form['code_input'], $form['resend_container'], $form['authenticate']);
      $form['send'] = [
        '#type' => 'submit',
        '#value' => 'Send Code',
      ];
    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state){
    $trigger = $form_state->getTriggeringElement();
    if ($trigger['#value'] === 'Authenticate') {
      $code_data = $this->code_config->getRawData();
      $user_code = $code_data['codes'][$this->currentUser->id()];
      $current_time = $this->time->getRequestTime();
      //If the code has expired, require a code resend to move forward
      if ($current_time - $user_code['registered'] >= $code_data['code_timeout']) {
        $form_state->setErrorByName('code_input', 'Your code has expired. Resend to get a new code.');
        return;
      }
      //If the code is incorrect, throw an error
      if ($form_state->getValue('code_input') !== $user_code['code']) {
        $form_state->setErrorByName('code_input', 'Incorrect code entered');
      }
    } elseif($trigger['#value'] === 'Resend'){
      //Check if the entered phone number is under 10 digits
      if(strlen($form_state->getValue('phone_number')) !== 10){
        $form_state->setErrorByName('phone_number', 'Must be a valid phone number');
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state){
    $trigger = $form_state->getTriggeringElement();
    $user = User::load($this->currentUser->id());
    //Update the user's phone number if changed
    if($user->get('field_phone')->value !== $form_state->getValue('phone_number')){
      $user->set('field_phone', $form_state->getValue('phone_number'))->save();
    }
    //If validation is passed, the entered code is correct
    if ($trigger['#value'] === 'Authenticate') {
      //Delete the code entry from config
      $this->code_config->clear('codes.' . $this->currentUser->id())->save();
      //Give user the 2FA role and redirect them to their unlocked profile page
      $user->addRole('2fa');
      $user->save();
      $form_state->setRedirect('user.page');

      $this->messenger->addStatus('Your account has been authenticated!');
    } elseif ($trigger['#value'] === 'Resend' || $trigger['#value'] === 'Send Code') {
      //Store the authentication code in configuration
      $new_code = [
        'code' => rand(0, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9),
        'registered' => $this->time->getRequestTime(),
      ];
      $this->code_config->set('codes.' . $this->currentUser->id(), $new_code)->save();

      //Prepare and send the verification code through Twilio SMS API
      $message = (new \Drupal\sms\Message\SmsMessage)
        ->setMessage($new_code['code'])
        ->addRecipient('+1'.$user->get('field_phone')->value)
        ->setDirection(\Drupal\sms\Direction::OUTGOING);
      try {
        \Drupal::service('sms.provider')->send($message);
      } catch(\Drupal\sms\Exception\NoPhoneNumberException $e){
        $this->messenger->addError($e);
      }

      $status_message = $trigger['#value'] === 'Resend' ? 'A new code has been sent to your device' : 'Your authentication code has been sent.';
      $this->messenger->addStatus($status_message);
    }
  }


}