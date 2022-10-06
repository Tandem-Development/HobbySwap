<?php

namespace Drupal\hs_square\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\hs_square\SquareManagerInterface;
use Psr\Container\ContainerInterface;
use Square\Environment;
use Square\Exceptions\ApiException;
use Square\SquareClient;
use Drupal\user\Entity\User;

/**
 * Enables the administrator to dynamically manipulate environment data such as appId, locationId, and environment type
 */
class ConfigureSquareSettingsForm extends ConfigFormBase{

  protected $squareManager;
  protected $entityTypeManager;
  protected $messenger;
  protected $cacheTagsInvalidator;

  public function __construct(
    ConfigFactoryInterface $config_factory,
    SquareManagerInterface $squareManager,
    EntityTypeManagerInterface $entityTypeManager,
    MessengerInterface $messenger,
    CacheTagsInvalidatorInterface $cacheTagsInvalidator
  ){
    parent::__construct($config_factory);
    $this->squareManager = $squareManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
  }

  public static function create(ContainerInterface $container){
    return new static(
      $container->get('config.factory'),
      $container->get('hs_square.square_manager'),
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('cache_tags.invalidator')
    );
  }

  public function getFormId(){
    return 'hs_square.configure_square_settings_form';
  }

  public function getEditableConfigNames(){
    return ['hs_square.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form = parent::buildForm($form, $form_state);
    //Get the current configuration values for this environment for default values
    $square_config = $this->configFactory->get('hs_square.settings');

    //Displays some basic information about this site's location
    $form['location_name'] = [
      '#title' => $this->t('Location'),
      '#markup' => $this->t('<h5>Location Name: @name</h5>', [ '@name' => $square_config->get('location_name')])
    ];
    $form['location_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location ID'),
      '#default_value' => $square_config->get('location_id')
    ];
    //This site's application ID assigned by Square
    $form['application_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application ID'),
      '#default_value' => $square_config->get('application_id'),
    ];
    //This site's access token assigned by Square
    $form['access_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Token'),
      '#default_value' => $square_config->get('access_token'),
    ];
    //A dropdown that allows admins to specify the environment type. This will also affect which assets are loaded from Square's CDN
    $form['environment'] = [
      '#type' => 'select',
      '#title' => $this->t('Environment'),
      '#default_value' => $square_config->get('environment'),
      '#options' => [
        'sandbox' => 'Sandbox',
        'production' => 'Production',
        'custom' => 'Custom'
      ]
    ];
    //An alternate submit button for syncing HobbySwap's user-base with Square's customer profiles
    //It will only add users where they are found to be missing and will not delete existing customer profiles
    $form['sync_customers'] = [
      '#type' => 'submit',
      '#value' => 'Sync Customers',
      '#suffix' => '<p>"Sync Customers" finds Drupal users with no attached Square customer profile and generates a new customer profile for each orphaned user.</p>'
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $trigger = $form_state->getTriggeringElement()['#id'];
    if ($trigger === 'edit-submit') {
      //If the default submit button is pressed, save the configuration values from the form
      parent::submitForm($form, $form_state);
      $this->configFactory->getEditable('hs_square.settings')
        ->set('access_token', $form_state->getValue('access_token'))
        ->set('application_id', $form_state->getValue('application_id'))
        ->set('environment', $form_state->getValue('environment'))
        ->save();
      try {
        $apiResponse = $this->squareManager->openClient()->getLocationsApi()->retrieveLocation($form_state->getValue('location_id'));
        if ($apiResponse->isSuccess()) {
          $result = $apiResponse->getResult();
          //If the location exists, set the configuration values
          $this->configFactory->getEditable('hs_square.settings')
            ->set('location_id', $form_state->getValue('location_id'))
            ->set('location_name', $result->getLocation()->getName())
            ->save();
        } else {
          $errors = $apiResponse->getErrors();
          foreach ($errors as $error) {
            $this->messenger
              ->addError('category: ' . $error->getCategory())
              ->addError('code: ' . $error->getCode())
              ->addError('details: ' . $error->getDetail());
          }
        }
      } catch (ApiException $e) {
        $this->messenger->addError("ApiException occurred: " . $e->getMessage());
      }
    } elseif($trigger === 'edit-sync-customers') {
      //If the 'Sync Customers' submit button was used, look for users with no attached customer profiles and create profiles for those users
      //Get all the users for this location and temporarily store their reference IDs
      $customers = $this->squareManager->retrieveCustomers()->getCustomers();
      $customer_rids = [];
      if(!empty($customers)){
        foreach($customers as $customer){
          $customer_rids[] = $customer->getReferenceId();
        }
      }
      //Get all user entity IDs that aren't anonymous
      $query = $this->entityTypeManager->getStorage('user')->getQuery();
      $uids = $query->condition('uid', '0', '>')->execute();

      $new_customers = [];
      //Loop through each UID and check if there is a corresponding Square reference ID
      foreach ($uids as $uid) {
        if (!in_array($uid, $customer_rids)) {
          //If no corresponding customer profile is found, create one for that user
          $this->squareManager->createCustomer($uid);
          $new_customers[] = $uid;
        }
      }
      if(empty($new_customers)){
        //Do nothing if no new customers were created
        $this->messenger->addStatus('No new customers added');
      }else{
        //If new users were added, invalidate the caches of the customer management table, and notify the admin of all create profiles
        $this->cacheTagsInvalidator->invalidateTags(['manage_square_customers']);
        $this->messenger->addStatus('New Square customers created with the following user ids/reference ids: ' . implode(', ', $new_customers));
        drupal_flush_all_caches();
      }

    }
  }
}