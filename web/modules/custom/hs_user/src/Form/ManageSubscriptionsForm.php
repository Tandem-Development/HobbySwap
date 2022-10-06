<?php

namespace Drupal\hs_user\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\hs_user\SubscriptionManagerInterface;
use Psr\Container\ContainerInterface;

/**
 * An admin form for defining subscription plans
 */
class ManageSubscriptionsForm extends ConfigFormBase{

  protected $subscriptionManager;
  protected $messenger;

  public function __construct(ConfigFactoryInterface $config_factory, SubscriptionManagerInterface $subscriptionManager, MessengerInterface $messenger){
    parent::__construct($config_factory);
    $this->subscriptionManager = $subscriptionManager;
    $this->messenger = $messenger;
  }

  public static function create(ContainerInterface $container){
    return new static(
      $container->get('config.factory'),
      $container->get('hs_user.subscription_manager'),
      $container->get('messenger')
    );
  }

  public function getEditableConfigNames(){
    //In addition to the subscription configuration, the field storage for 'field_subscription' needs to be updated with plan options
    return ['hs_user.subscriptions', 'field.storage.user.field_subscription'];
  }

  public function getFormId(){
    return 'hs_user.manage_subscriptions_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state){
    $form = parent::buildForm($form, $form_state);
    //Retrieve and output the current subscription configuration
    $subscription_bin = $this->config('hs_user.subscriptions');
    $data = $subscription_bin->getOriginal();
    try{
      $output = Yaml::encode($data);
    }catch(\Exception $e){
      $this->messenger->addError($e);
    }

    $form['schema'] = [
      '#markup' => '
<pre>
SUBSCRIPTION PLAN STRUCTURE{
subscriptions:
    MACHINE_NAME:
        label: STRING
        cost: INTEGER (IN USD)
        cost_label: STRING
        duration: INTEGER (IN MONTHS)
        features:
            - FEATURE STRING #1
            - FEATURE STRING #2
}
</pre>      
      ',
    ];
    //Create the editor field and set its default value to the current output
    $form['editor'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Subscription Plan Configuration'),
      '#default_value' => $output,
      '#description' => $this->t('Direct output from hs_user.subscriptions configuration object'),
      '#rows' => 30,
      '#required' => TRUE,
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state){
    parent::submitForm($form, $form_state);
    try{
      //Decode the new value and save the configuration
      $values = Yaml::decode($form_state->getValue('editor'));
      $this->configFactory->getEditable('hs_user.subscriptions')->setData($values)->save();

      //Update the field options configuration for the user field 'field_subscription'
      $subscription_data = $this->configFactory->get('hs_user.subscriptions')->getRawData()['subscriptions'];
      $field_storage = $this->configFactory->getEditable('field.storage.user.field_subscription');
      $allowed_values = [];
      foreach($subscription_data as $name => $data){
        $allowed_values[] = [
          'value' => $name,
          'label' => $data['label']
        ];
      }
      $field_storage->set('settings.allowed_values', $allowed_values)->save();
      drupal_flush_all_caches();
    }catch(\Exception $e){
      $this->messenger->addError($e);
    }

  }

}