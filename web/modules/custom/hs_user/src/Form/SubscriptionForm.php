<?php

namespace Drupal\hs_user\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\hs_square\SquareManagerInterface;
use Drupal\hs_user\SubscriptionManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SubscriptionForm extends ConfigFormBase{

  protected $subscriptionManager;
  protected $currentUser;
  protected $squareManager;

  public function __construct(ConfigFactoryInterface $config_factory, SubscriptionManagerInterface $subscriptionManager, AccountProxyInterface $currentUser, SquareManagerInterface $squareManager){
    parent::__construct($config_factory);
    $this->subscriptionManager = $subscriptionManager;
    $this->currentUser = User::load($currentUser->id());
    $this->squareManager = $squareManager;
  }

  public static function create(ContainerInterface $container){
    return new static(
      $container->get('config.factory'),
      $container->get('hs_user.subscription_manager'),
      $container->get('current_user'),
      $container->get('hs_square.square_manager')
    );
  }

  public function getEditableConfigNames(){
    return ['hs_user.subscriptions'];
  }

  public function getFormId(){
    return 'hs_user.subscription_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state){

    $plans = $this->configFactory->getEditable('hs_user.subscriptions')->get('subscriptions');
    $current_plan = !empty($this->currentUser->get('field_subscription')->value) ? $this->currentUser->get('field_subscription')->value : null;

    $plan_options = [];
    foreach($plans as $plan => $details){
      $classes = 'subscription-plan-details';
      $classes .= ($plan === $current_plan) ? ' current-plan' : '';
      $new_plan = '<div class="@classes"><h2>@label</h2><h5>@cost</h5><ul>';
      foreach($details['features'] as $feature){
        $new_plan .= '<li>'.$feature.'</li>';
      }
      $new_plan .= '</ul></div>';
      $plan_options[$plan] = $this->t($new_plan, ['@cost'=>$details['cost_label'], '@label'=>$details['label'], '@classes'=>$classes]);
    }

    $form['header'] = ['#markup' => $this->t('<h1 class="text-center hs-color-black h1-responsive">Choose Your Subscription Plan</h1>')];

    $form['description'] = [
      '#markup' => $this->t('<h6 class="text-center h6-responsive">While HobbySwap moves through its earliest stages, the last
        thing we want is to intimidate new users with overwhelming feature lists and complicated subscription tiers. That is
        why we kept things simple and provide one simple tier at an affordable price. Whether paying monthly or annually, a
        simple subscription fully unlocks HobbySwap and all the features it has to offer! If you would like to provide a little extra
        financial support, we have provided an easy way to integrate that support with your subscription plan.</h6>'),
    ];
    $form['subscription_plans'] = [
      '#type' => 'radios',
      '#title' => 'Basic Plans',
      '#required' => TRUE,
      '#options' => $plan_options,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Select Plan',
      '#attributes' => [
        'class' => ['mt-4 mb-4 btn-yellow']
      ]
    ];

    $form['#attached']['library'] = ['hs_user/subscription-form'];
    $form['#attributes']['class'] = ['text-center'];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state){
    $current_plan = $this->currentUser->get('field_subscription')->value;
    if(!empty($current_plan) && $current_plan === $form_state->getValue('subscription_plans')){
      $form_state->setErrorByName('subscription_plans', 'Your account is already using this subscription plan. Choose a different plan if you would like to change it.');
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state){
    $selected_plan = $form_state->getValue('subscription_plans');

    //Redirect to payment page and load it with requested subscription data
    $query_args = ['type' => 'subscription', 'plan' => $selected_plan];
    $url = Url::fromRoute('hs_square.payment_form')->setOptions(['query' => $query_args]);
    $response = new RedirectResponse($url->toString());
    $response->send();
  }

}