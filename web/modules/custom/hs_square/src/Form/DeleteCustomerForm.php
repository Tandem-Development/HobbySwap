<?php

namespace Drupal\hs_square\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hs_square\SquareManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Psr\Container\ContainerInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;

/**
 * An admin form that acts as a confirmation step before deleting Square customers
 * Requires the customer's ID to be passed in as a slug value
 */
class DeleteCustomerForm extends ConfirmFormBase{

  protected $squareManager;
  protected $messenger;
  protected $cacheTagsInvalidator;

  public function __construct(SquareManagerInterface $squareManager, MessengerInterface $messenger, CacheTagsInvalidatorInterface $cacheTagsInvalidator){
    $this->squareManager = $squareManager;
    $this->messenger = $messenger;
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
  }

  public static function create(ContainerInterface $container){
    return new static(
      $container->get('hs_square.square_manager'),
      $container->get('messenger'),
      $container->get('cache_tags.invalidator')
    );
  }

  public function getCancelUrl(){
    return Url::fromRoute('hs_square.manage_customers');
  }

  public function getQuestion(){
    return 'Are you sure you want to delete this customer?';
  }

  public function getFormId(){
    return 'hs_square.delete_customer_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $squareid = NULL){
    //Hide the value in the form so that it can be processed on submission
    $form['squareid'] = [
      '#type' => 'hidden',
      '#default_value' => $squareid,
    ];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state){
    $squareid = $form_state->getValue('squareid');
    //Delete the customer on submission
    $api_response = $this->squareManager->deleteCustomer($squareid);
    if($api_response->isSuccess()){
      $this->messenger->addStatus('Customer with ID "'.$squareid.'" has been deleted');
      //If the customer was deleted successfully, clear caches for the admin customer view
      $this->cacheTagsInvalidator->invalidateTags(['manage_square_customers']);
    }else{
      $this->messenger->addError('Failed to delete customer with ID: '.$squareid);
    }
    //Redirect to the customer management table as this is the only place deletion routes are openly displayed
    $form_state->setRedirect('hs_square.manage_customers');
  }

}