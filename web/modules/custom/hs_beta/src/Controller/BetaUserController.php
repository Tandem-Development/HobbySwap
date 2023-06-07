<?php

namespace Drupal\hs_beta\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Mail\MailManagerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;

class BetaUserController extends ControllerBase{

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

    /**
   * BetaUserController::accept is the controlling method for the hs_beta.accept_account route
   * - Adds the beta role to the user
   * - Sends an email to the user notifying them of their application's acceptance and beta program details
   */
  public function accept($uid){

    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if(!empty($user)){
      $user->addRole('beta');
      $user->save();
      $this->mailManager->mail('hs_beta', 'app_accept', $user->get('mail')->value, 'en', [], NULL, TRUE);
      $response = new RedirectResponse('/admin/people');
      $response->send();
    }else{
      $this->messenger->addError($this->t('The uid '.$uid. ' does not exists'));
    }

    return[];
  }

  /**
   * BetaUserController::reject is the controlling method for the hs_beta.reject_account route
   * - Deletes the rejected user
   * - Sends an email to the user notifying them of their application's rejection
   */
  public function reject($uid){
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if(!empty($user)){
      $user->delete();
      $this->mailManager->mail('hs_beta', 'app_reject', $user->get('mail')->value, 'en', [], NULL, TRUE);
      $response = new RedirectResponse('/admin/people');
      $response->send();
    }else{
      $this->messenger->addError($this->t('The uid '.$uid. ' does not exists'));
    }

    return[];
  }

}
