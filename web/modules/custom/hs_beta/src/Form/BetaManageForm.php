<?php

namespace Drupal\hs_beta\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * BetaManageForm is called by the route hs_beta.manage
 * - Handles the opening and closing of the beta program
 */
class BetaManageForm extends FormBase{

  public function getFormId() {
    return 'hs_beta.open';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

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

    $mail_manager = \Drupal::service('plugin.manager.mail');

    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $query = $user_storage->getQuery();
    $users = $user_storage->loadMultiple($query->execute());

    if($form_state->getTriggeringElement()['#value'] === 'Open Beta'){
      foreach($users as $id => $user){
        if($user->hasRole('beta')){
          //Generate and set user's password
          $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
          $charactersLength = strlen($characters);
          $password = '';
          for ($i = 0; $i < 15; $i++) {
            $password .= $characters[rand(0, $charactersLength - 1)];
          }
          $user->set('status', TRUE)->setPassword($password)->save();
          $params['username'] = $user->getDisplayName();
          $params['password'] = $password;
          $mail_manager->mail('hs_beta', 'beta_open', $user->get('mail')->value, 'en', $params, NULL, TRUE);
        }
      }
      \Drupal::messenger()->addMessage('All beta accounts have been activated');

    }else if($form_state->getTriggeringElement()['#value'] === 'Close Beta'){
      foreach($users as $id => $user){
        if($user->hasRole('beta')){
          $user->set('status', FALSE)->save();
          $mail_manager->mail('hs_beta', 'beta_close', $user->get('mail')->value, 'en', [], NULL, TRUE);
        }
      }
      \Drupal::messenger()->addMessage('All beta accounts have been deactivated');
    }
  }

}
