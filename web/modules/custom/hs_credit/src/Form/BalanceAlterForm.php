<?php

namespace Drupal\hs_credit\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Psr\Container\ContainerInterface;
use Drupal\Core\Url;

class BalanceAlterForm extends FormBase {

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


  public function getFormId() {
    return 'hs_credit.balance_alter_form';
  }


  public function buildForm(array $form, FormStateInterface $form_state, array $controller_data = []) {
    $user_query = $this->entityTypeManager->getStorage('user')->getQuery();
    $results = $user_query->execute();
    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($results);
    $user_selections = [];
    foreach($users as $user) {
      $user_selections[$user->id()] = $user->getDisplayName(). ' - '.$user->get('hc_balance')->value;
    }

    $form['user_selection'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select Target Users'),
      '#options' => $user_selections,
      '#required' => TRUE,
    ];
    $form['new_balance'] = [
      '#type' => 'number',
      '#title' => $this->t('New Balance'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Alter',
    ];

    return $form;
  }


  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach($form_state->getValue('user_selection') as $uid){
      if($uid != 0) {
        $user = $this->entityTypeManager->getStorage('user')->load($uid);
        $user->set('hc_balance', $form_state->getValue('new_balance'));
        $user->save();
      }
    }
  }

}
