<?php

/**
 * PurchaseHobbyCoinForm is retrieved and displayed in Drupal\hs_trade\Controller\PurchaseHobbyCoinController
 * - This is a stand-in for an actual payment page that will be implemented later on
 * - Currently loads a transaction's residual and adds that residual to a user's balance upon submission
 * - Input fields are required but arbitrary
 * - The relevant transaction's ID is brought in through the controller
 */

namespace Drupal\hs_trade\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Container\ContainerInterface;

class PurchaseHobbyCoinForm extends FormBase {

  //Boilerplate dependency injection for the 'entity_type.manager' service
  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;
  function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Sets the form's machine name
   */
  public function getFormId() {
    return 'hs_trade.purchase_hobbycoin_form';
  }

  /**
   * buildForm() generates form fields and their options
   * - $controller_data arguments are passed in from the formBuilder service when the form is retrieved in a controller
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $controller_data = []) {

    //Using the controller data, retrieve the relevant transaction and its residual
    $transaction = $this->entityTypeManager->getStorage('hs_trade_transaction')->load($controller_data['transaction_id']);
    $user = $this->entityTypeManager->getStorage('user')->load(\Drupal::currentUser()->id());
    $controller_data['total'] = abs($transaction->get('residual')->value) - $user->get('hc_balance')->value;

    //Pass controller data into a hidden field to make it accessible in submitForm()
    $form['controller_data'] = [
      '#type' => 'hidden',
      //Convert controller data into deconstructable string to be passed through form_state
      '#value' => http_build_query($controller_data, '', '&'),
    ];
    //Both 'full_name' and 'card_number' are arbitrary and perform no function
    $form['full_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cardholder\'s Full Name:'),
      '#required' => TRUE,
    ];
    $form['card_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Card Number:'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Purchase '.$controller_data['total'].' HobbyCoin'),
    ];

    return $form;

  }

  /**
   * submitForm() adjusts the user's balance and reroutes the user to the confirmation controller
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    //Convert controller data back into an array
    parse_str($form_state->getValue('controller_data'), $controller_data);

    //Retrieve relevant transaction
    $transaction = $this->entityTypeManager->getStorage('hs_trade_transaction')->load($controller_data['transaction_id']);

    //Retrieve the current user's entity, their HC balance, and update that balance with the transaction's residual
    $user = $this->entityTypeManager->getStorage('user')->load(\Drupal::currentUser()->id());
    $balance = $user->get('hc_balance')->value;
    $user->set('hc_balance', $balance += $controller_data['total'])->save();

    //Redirect the user to the transaction confirmation as their balance is now sufficient
    $form_state->setRedirect('hs_trade.transaction_confirm', ['hs_trade_transaction' => $controller_data['transaction_id']]);
  }

}
