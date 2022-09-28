<?php

namespace Drupal\hs_square\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hs_user\SubscriptionManagerInterface;
use Psr\Container\ContainerInterface;
use Drupal\hs_square\SquareManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PaymentForm extends FormBase{

  protected $configFactory;
  protected $squareManager;
  protected $subscriptionManager;

  public function __construct(ConfigFactoryInterface $configFactory, SquareManagerInterface $squareManager, SubscriptionManagerInterface $subscriptionManager){
    $this->configFactory = $configFactory;
    $this->squareManager = $squareManager;
    $this->subscriptionManager = $subscriptionManager;
  }

  public static function create(ContainerInterface $container){
    return new static(
      $container->get('config.factory'),
      $container->get('hs_square.square_manager'),
      $container->get('hs_user.subscription_manager')
    );
  }

  public function getFormId(){
    return 'hs_user.subscription_payment_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state){

    $form['#prefix'] = '<h1>Pay</h1>';
    $form['cards'] = [
      '#type' => 'radios',
      '#title' => $this->t('Payment Method'),
      '#required' => TRUE,
      '#description' => $this->t('Choose one of your cards to charge this payment to'),
    ];

    $form['checkout_summary'] = [
      '#theme' => 'checkout_summary',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#attributes' => [
        'class' => ['w-100']
      ],
    ];

    $cards = $this->squareManager->retrieveCustomerByUid($this->currentUser()->id())->getCards();
    if(!empty($cards)) {
      //If the user has a card(s) saved to their account, populate the card selector with that card(s)
      $form['cards']['#options'] = array_map(function ($card) {
        $card_render_array = [
          '#theme' => 'card',
          '#card' => $card
        ];
        return \Drupal::service('renderer')->render($card_render_array);
      }, $cards);
    }else{
      //If no cards are present, remove the cards radio selector and disable the submission button
      $form['cards']['#markup'] = $this->t('<strong>Before purchasing something on HobbySwap, you must attach a card to your account</strong>');
      $form['cards']['#options'] = [];
      unset($form['cards']['#description']);
      $form['submit']['#disabled'] = TRUE;
    }


    if(!array_key_exists('type', $_GET)){
      \Drupal::messenger()->addError('Missing valid payment type');
      return null;
    }
    switch ($_GET['type']){
      case 'subscription':
        if(array_key_exists('plan', $_GET)){
          $plan = $this->subscriptionManager->isPlanDefined($_GET['plan']);
          if($plan === false){
            \Drupal::messenger()->addError('Invalid subscription plan referenced');
            return null;
          }else{
            //Code to run if type is 'subscription' and a valid plan is passed in
            $plan_data = $this->subscriptionManager->getPlanData($plan);
            $form['checkout_summary']['#mode'] = 'Subscription';
            $form['checkout_summary']['#requested'] = $plan_data['label'];
            $form['checkout_summary']['#cost'] = intval($plan_data['cost'])/100;
            $form['submit']['#value'] = 'Purchase Subscription';
          }
        }else{
          \Drupal::messenger()->addError('Missing valid subscription plan');
          return null;
        }
        break;
      case 'hobbycredit':
        //TODO: HobbyCredit purchasing and security logic
        $form['submit']['#value'] = 'Purchase HobbyCredit';
        break;
      default:
        \Drupal::messenger()->addError('Invalid payment type');
        return null;
    }

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state){
    //Submit payment with the selected card and execute necessary processing
    $trigger = $form_state->getTriggeringElement()['#value'];
    switch ($trigger){
      case 'Purchase Subscription':
        //Charge the user for the subscription, and apply it if successful
        $cards = $this->squareManager->retrieveCustomerByUid($this->currentUser()->id())->getCards();
        $source_id = $cards[$form_state->getValue('cards')]->getId();

        $plan_data = $this->subscriptionManager->getPlanData($_GET['plan']);
        $api_response = $this->squareManager->createPayment($source_id, $plan_data['cost'], $this->currentUser()->id());
        if($api_response->isSuccess()){
          \Drupal::messenger()->addStatus('Your subscription has been updated to '.$plan_data['label']);
          $this->subscriptionManager->setUserSubscription(\Drupal::currentUser()->id(), $_GET['plan']);
          $response = new RedirectResponse('/user');
          $response->send();
        }else{
          $form_state->setErrorByName('cards', 'Payment failed. Your card is likely invalid');
        }
        break;
      case 'Purchase HobbyCredit':

    }
  }


}