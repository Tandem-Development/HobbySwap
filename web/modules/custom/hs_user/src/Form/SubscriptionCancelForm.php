<?php

namespace Drupal\hs_user\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\hs_user\SubscriptionManagerInterface;
use Psr\Container\ContainerInterface;

/**
 * This form acts a confirmation phase to prevent users from accidentally cancelling their subscription
 */
class SubscriptionCancelForm extends ConfirmFormBase{

  protected $subscriptionManager;
  protected $currentUser;
  protected $messenger;

  public function __construct(SubscriptionManagerInterface $subscriptionManager, AccountProxyInterface $currentUser, MessengerInterface $messenger){
    $this->subscriptionManager = $subscriptionManager;
    $this->currentUser = $currentUser;
    $this->messenger = $messenger;
  }

  public static function create(ContainerInterface $container){
    return new static(
      $container->get('hs_user.subscription_manager'),
      $container->get('current_user'),
      $container->get('messenger')
    );
  }

  public function getCancelUrl(){
    //Send the user to their profile page if they decide not to cancel their subscription
    return Url::fromRoute('user.page');
  }

  public function getQuestion(){
    return $this->t('By cancelling your subscription, you will be fully locked out of HobbySwap\'s trade system. Do you wish to proceed?');
  }

  public function getFormId(){
    return 'hs_user.subscription_cancel_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state){
    $buttons = parent::buildForm($form, $form_state);

    //All additional markup is styled inline or via utility classes
    //Raw markup is used to avoid the need for an additional theme
    $form['#attributes']['class'] = ['d-flex flex-column align-items-center'];
    $form['warning'] = [
      '#markup' => $this->t('
<h1 style="color: white; background-color: red; font-weight: bold; padding: 0 0.5em">WARNING</h1>
<h3 class="text-center">YOU ARE ABOUT TO CANCEL YOUR SUBSCRIPTION!</h3>
<h4 class="text-center">Cancelling your HobbySwap subscription will refute your access to:</h4>
<ul>
    <li>Edit your items</li>
    <li>Browse available items and make offers on them</li>
    <li>Send direct messages and view incoming messages</li>
    <li>Complete any pending trades</li>
    <li>Spend any HobbyCredit that remains in your wallet</li>
</ul>
')
    ];

    //Add actions after the markup
    $form[] = $buttons;

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state){
    //Remove the user's subscription role, set 'field_subscription' to null, and notify the user
    $this->subscriptionManager->removeUserSubscription($this->currentUser->id());
    $this->messenger->addStatus('Your subscription has been cancelled');
    //Redirect the user to their profile page
    $form_state->setRedirect('user.page');
  }

}