<?php

namespace Drupal\hs_square\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\hs_user\SubscriptionManagerInterface;
use Psr\Container\ContainerInterface;
use Drupal\hs_square\SquareManagerInterface;
use Drupal\Core\Form\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Dynamically handles all user payment requests
 */
class PaymentForm extends FormBase{

  protected $configFactory;
  protected $squareManager;
  protected $subscriptionManager;
  protected $messenger;
  protected $renderer;

  public function __construct(
    ConfigFactoryInterface $configFactory,
    SquareManagerInterface $squareManager,
    SubscriptionManagerInterface $subscriptionManager,
    MessengerInterface $messenger,
    RendererInterface $renderer
  )
  {
    $this->configFactory = $configFactory;
    $this->squareManager = $squareManager;
    $this->subscriptionManager = $subscriptionManager;
    $this->messenger = $messenger;
    $this->renderer = $renderer;
  }

  public static function create(ContainerInterface $container){
    return new static(
      $container->get('config.factory'),
      $container->get('hs_square.square_manager'),
      $container->get('hs_user.subscription_manager'),
      $container->get('messenger'),
      $container->get('renderer')
    );
  }

  public function getFormId(){
    return 'hs_user.subscription_payment_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state){

    //The title for the form as the title block is disabled in the theme
    $form['#prefix'] = '<h1>Pay</h1>';
    //A list of cards attached to the user's customer profiles. It will be populated with values later
    $form['cards'] = [
      '#type' => 'radios',
      '#title' => $this->t('Payment Method'),
      '#required' => TRUE,
      '#description' => $this->t('Choose one of your cards to charge this payment to'),
    ];
    //Summarizes what the user is about to purchase. Markup will be dynamically passed in later
    $form['checkout_summary'] = [
      '#theme' => 'checkout_summary',
    ];
    //The main submission button to submit the payment
    $form['submit'] = [
      '#type' => 'submit',
      '#attributes' => [
        'class' => ['w-100']
      ],
    ];

    //Get the current user's customer profile, their cards, and their default card's index
    $customer = $this->squareManager->retrieveCustomerByUid($this->currentUser()->id());
    $cards = $customer->getCards();
    $default_card_index = $this->squareManager->getDefaultCardIndex($customer);
    if(!empty($cards)) {
      //If the user has a card(s) saved to their account, populate the card selector with that card(s)
      foreach($cards as $index => $card){
        $card_render_array = [
          '#theme' => 'card',
          '#card' => $card,
          '#index' => $index,
          '#is_default' => ($default_card_index == $index),
        ];
        $form['cards']['#options'][$index] = $this->renderer->render($card_render_array);
      }
    }else{
      //If no cards are present, indicate this with markup and disable the submit button
      $form['cards']['#markup'] = $this->t('<strong>Before purchasing something on HobbySwap, you must attach a card to your account</strong>');
      $form['cards']['#options'] = [];
      unset($form['cards']['#description']);
      $form['submit']['#disabled'] = TRUE;
    }

    //If a valid payment type is not present in the URL, throw an error and display nothing
    if(!array_key_exists('type', $_GET)){
      $this->messenger->addError('Missing valid payment type');
      return null;
    }

    //Payment types are passed via the URL allowing for dynamic form generation determined by this value
    switch ($_GET['type']){
      case 'subscription':
        //Ensure that a subscription plan was included and that it's defined. If that is not the case, display an error and display nothing
        if(array_key_exists('plan', $_GET)){
          $plan = $this->subscriptionManager->isPlanDefined($_GET['plan']);
          if($plan === false){
            $this->messenger->addError('Invalid subscription plan referenced');
            return null;
          }
          //Code to run if type is 'subscription' and a valid plan is passed in
          //Get the plan's data and use it to populate the checkout summary
          $plan_data = $this->subscriptionManager->getPlanData($plan);
          $form['checkout_summary']['#mode'] = 'Subscription';
          $form['checkout_summary']['#requested'] = $plan_data['label'];
          $form['checkout_summary']['#cost'] = intval($plan_data['cost'])/100;
          //The submit button's value will be used to determine submission behavior and should be set under ideal conditions
          $form['submit']['#value'] = 'Purchase Subscription';
        }else{
          $this->messenger->addError('Missing valid subscription plan');
          return null;
        }
        break;
      case 'hobbycredit':

        //TODO: Implement HobbyCredit form mode

        $form['submit']['#value'] = 'Purchase HobbyCredit';
        break;
      default:
        $this->messenger->addError('Invalid payment type');
        return null;
    }

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state){
    //Alter submission behavior based on the submit button's value which has already been derived from the payment type
    $trigger = $form_state->getTriggeringElement()['#value'];
    switch ($trigger){
      case 'Purchase Subscription':
        //Charge the user for the subscription, and apply it if the payment is successful
        //Retrieve the current user's customer profile
        $customer = $this->squareManager->retrieveCustomerByUid($this->currentUser()->id());
        //Get the id of the selected card
        $source_id = $customer->getCards()[$form_state->getValue('cards')]->getId();
        //Get the requested plan's definition
        $plan_data = $this->subscriptionManager->getPlanData($_GET['plan']);
        //Make the payment call by passing in the appropriate card id, plan cost, and customer object
        $api_response = $this->squareManager->createPayment($source_id, $plan_data['cost'], $customer);
        if($api_response->isSuccess()){
          //If the payment was processed successfully, update the user's subscription plan and notify them
          $this->messenger->addStatus('Your subscription has been updated to '.$plan_data['label']);
          $this->subscriptionManager->setUserSubscription($this->currentUser()->id(), $_GET['plan']);
          $response = new RedirectResponse('/user');
          $response->send();
        }else{
          //If the payment fails, notify the user
          $this->messenger->addError('Payment failed. Your card is likely invalid');
        }
        break;
      case 'Purchase HobbyCredit':
        //TODO: Include the ability to purchase HobbyCredit
    }
  }


}