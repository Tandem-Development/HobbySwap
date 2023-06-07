<?php

namespace Drupal\hs_beta\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Psr\Container\ContainerInterface;

/**
 * Provides a form for administrators to open and close the beta program
 */
class BetaManageForm extends FormBase{

  protected $entityTypeManager;
  protected $messenger;
  protected $mailManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, MessengerInterface $messenger, MailManagerInterface $mailManager){
    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
    $this->mailManager = $mailManager;
  }

  public static function create(ContainerInterface $container){
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('plugin.manager.mail')
    );
  }

  public function getFormId() {
    return 'hs_beta.open';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    //Build the form with two primary submit buttons: one to open the beta and one to close it
    $form['#attached']['library'] = ['hs_beta/manage-form'];

    $form['open_set'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('OPEN BETA'),
    ];
    $form['open_set']['open_warning'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => 'WARNING!!! Clicking this button will unblock all beta accounts and email each user with their account information!',
    ];
    $form['open_set']['open_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirmation Text'),
      '#description' => $this->t('To perform this action, type "openbeta" into this text field'),
    ];
    $form['open_set']['open'] = [
      '#type' => 'submit',
      '#value' => 'Open Beta',
    ];
    $form['close_set'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('CLOSE BETA'),
    ];
    $form['close_set']['close_warning'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => 'WARNING!!! Clicking this button will block all beta accounts and effectively end early access testing!',
    ];
    $form['close_set']['close_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirmation Text'),
      '#description' => $this->t('To perform this action, type "closebeta" into this text field'),
    ];
    $form['close_set']['close'] = [
      '#type' => 'submit',
      '#value' => 'Close Beta',
    ];

    return $form;

  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    //Require the admin to enter a string to prevent accidental opening or closing of the beta
    if($form_state->getTriggeringElement()['#value'] === 'Open Beta'){
      if($form_state->getValue('open_text') != 'openbeta'){
        $form_state->setErrorByName('open_text', $this->t('Please enter the correct confirmation text to perform this action'));
      }
    }else if($form_state->getTriggeringElement()['#value'] === 'Close Beta'){
      if($form_state->getValue('close_text') != 'closebeta'){
        $form_state->setErrorByName('close_text', $this->t('Please enter the correct confirmation text to perform this action'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

    //Regardless of selected action, all beta user accounts are needed
    $user_storage = $this->entityTypeManager->getStorage('user');
    $query = $user_storage->getQuery()
      ->condition('uid', '0', '>')
      ->condition('roles', 'beta', 'CONTAINS');
    $users = $user_storage->loadMultiple($query->execute());

    if($form_state->getTriggeringElement()['#value'] === 'Open Beta'){
      //Run this code if 'Open Beta' was clicked
      foreach($users as $user){
        //Generate and the user's new password
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $password = '';
        for ($i = 0; $i < 15; $i++) {
          $password .= $characters[rand(0, $charactersLength - 1)];
        }
        //Set the new password
        $user->set('status', TRUE)->setPassword($password)->save();
        //Prepare email parameters to send each user their account info
        $params['username'] = $user->getDisplayName();
        $params['password'] = $password;
        //Send the user their info
        $this->mailManager->mail('hs_beta', 'beta_open', $user->get('mail')->value, 'en', $params, NULL, TRUE);
      }
      $this->messenger->addMessage('All beta accounts have been activated');

    }else if($form_state->getTriggeringElement()['#value'] === 'Close Beta'){
      //Run this code if 'Close Beta' was clicked
      foreach($users as $user){
        //Block all accounts to prevent them from logging in
        $user->set('status', FALSE)->save();
        $this->mailManager->mail('hs_beta', 'beta_close', $user->get('mail')->value, 'en', [], NULL, TRUE);
      }
      $this->messenger->addMessage('All beta accounts have been deactivated');
    }
  }

}
