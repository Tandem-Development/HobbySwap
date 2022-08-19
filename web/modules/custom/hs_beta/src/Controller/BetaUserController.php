<?php

namespace Drupal\hs_beta\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class BetaUserController extends ControllerBase{

  /**
   * BetaUserController::accept is the controlling method for the hs_beta.accept_account route
   * - Adds the beta role to the user
   * - Sends an email to the user notifying them of their application's acceptance and beta program details
   */
  public function accept($uid){

    $mail_manager = \Drupal::service('plugin.manager.mail');
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    if(!empty($user)){
      $user->addRole('beta');
      $user->save();
      $result = $mail_manager->mail('hs_beta', 'app_accept', $user->get('mail')->value, 'en', [], NULL, TRUE);
      $response = new RedirectResponse('/admin/people');
      $response->send();
    }else{
      \Drupal::messenger()->addError($this->t('The uid '.$uid. ' does not exists'));
    }

    return[];
  }

  /**
   * BetaUserController::reject is the controlling method for the hs_beta.reject_account route
   * - Deletes the rejected user
   * - Sends an email to the user notifying them of their application's rejection
   */
  public function reject($uid){

    $mail_manager = \Drupal::service('plugin.manager.mail');
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($uid);

    if(!empty($user)){
      $user->delete();
      $result = $mail_manager->mail('hs_beta', 'app_reject', $user->get('mail')->value, 'en', [], NULL, TRUE);
      $response = new RedirectResponse('/admin/people');
      $response->send();
    }else{
      \Drupal::messenger()->addError($this->t('The uid '.$uid. ' does not exists'));
    }

    return[];
  }

}
