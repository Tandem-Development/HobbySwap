<?php

namespace Drupal\hs_square\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hs_square\SquareManagerInterface;
use Psr\Container\ContainerInterface;
use Square\Environment;
use Square\Exceptions\ApiException;
use Square\SquareClient;
use Drupal\user\Entity\User;

class ConfigureSquareSettingsForm extends ConfigFormBase{

  protected $squareManager;
  protected $entityTypeManager;

  public function __construct(ConfigFactoryInterface $config_factory, SquareManagerInterface $squareManager, EntityTypeManagerInterface $entityTypeManager){
    parent::__construct($config_factory);
    $this->squareManager = $squareManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container){
    return new static(
      $container->get('config.factory'),
      $container->get('hs_square.square_manager'),
      $container->get('entity_type.manager')
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

    $square_config = $this->configFactory->get('hs_square.settings');

    $form['location'] = [
      '#title' => $this->t('Location'),
      '#markup' => $this->t('
        <h5>Location ID: @id</h5>
        <h5>Location Name: @name</h5>',
        ['@id' => $square_config->get('location_id'), '@name' => $square_config->get('location_name')])
    ];
    $form['application_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application ID'),
      '#default_value' => $square_config->get('application_id'),
    ];
    $form['access_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Token'),
      '#default_value' => $square_config->get('access_token'),
    ];
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
      parent::submitForm($form, $form_state);
      $this->configFactory->getEditable('hs_square.settings')
        ->set('access_token', $form_state->getValue('access_token'))
        ->set('application_id', $form_state->getValue('application_id'))
        ->set('environment', $form_state->getValue('environment'))
        ->save();

      try {
        $apiResponse = $this->squareManager->openClient()->getLocationsApi()->listLocations();
        if ($apiResponse->isSuccess()) {
          $result = $apiResponse->getResult();
          foreach ($result->getLocations() as $location) {
            $this->configFactory->getEditable('hs_square.settings')
              ->set('location_id', $location->getId())
              ->set('location_name', $location->getName())
              ->save();
            \Drupal::messenger()
              ->addStatus('location ID: ' . $location->getId())
              ->addStatus('location Name: ' . $location->getName())
              ->addStatus('location Address: ' . $location->getAddress()->getAddressLine1() . ', ' . $location->getAddress()->getLocality());
          }
        } else {
          $errors = $apiResponse->getErrors();
          foreach ($errors as $error) {
            \Drupal::messenger()
              ->addError('category: ' . $error->getCategory())
              ->addError('code: ' . $error->getCode())
              ->addError('details: ' . $error->getDetail());
          }
        }
      } catch (ApiException $e) {
        \Drupal::messenger()->addError("ApiException occurred: " . $e->getMessage());
      }
    } elseif($trigger === 'edit-sync-customers') {

      $customers = $this->squareManager->retrieveCustomers()->getCustomers();
      $customer_rids = array_map(function ($customer) {
        return $customer->getReferenceId();
      }, $customers);

      $query = $this->entityTypeManager->getStorage('user')->getQuery();
      $uids = $query->condition('uid', '0', '>')->execute();

      $new_customers = [];
      foreach ($uids as $uid) {
        if (!in_array($uid, $customer_rids)) {
          $this->squareManager->createCustomer($uid);
          $new_customers[] = $uid;
        }
      }
      if(empty($new_customers)){
        \Drupal::messenger()->addStatus('No new customers added');
      }else{
        \Drupal::service('cache_tags.invalidator')->invalidateTags(['manage_square_customers']);
        \Drupal::messenger()->addStatus('New Square customers created with the following user ids/reference ids: ' . implode(', ', $new_customers));
      }

    }
  }
}