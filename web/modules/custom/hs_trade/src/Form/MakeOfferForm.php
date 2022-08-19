<?php

/**
 * MakeOfferForm handles building and submitting new and counter offers
 * - This form doesn't belong to a specific route and should be only accessed with the formBuilder service
 * - Form behavior greatly depends on the controller data passed in when the form is retrieved with formBuilder
 * - The 'new' offer type creates a new transaction entity
 * - The 'counter' offer type modifies an existing transaction
 * - Regardless of offer type, the following transaction entity fields are written:
 *  - responder_uid
 *  - requester_uid
 *  - responder_items
 *  - requester_items
 *  - status
 */

namespace Drupal\hs_trade\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hs_trade\TransactionManagerInterface;
use Psr\Container\ContainerInterface;

class MakeOfferForm extends FormBase
{

  //Boilerplate dependency injection for 'entity_type.manager' and 'cache_tags.invalidator' services
  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;
  /**
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  private $cacheTagsInvalidator;
  /**
   * @var \Drupal\hs_trade\TransactionManagerInterface
   */
  private $transactionManager;

  function __construct(EntityTypeManagerInterface $entityTypeManager, CacheTagsInvalidatorInterface $cacheTagsInvalidator, TransactionManagerInterface $transactionManager)
  {
    $this->entityTypeManager = $entityTypeManager;
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
    $this->transactionManager = $transactionManager;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('cache_tags.invalidator'),
      $container->get('hs_trade.transaction_manager')
    );
  }

  /**
   * Sets the form's machine name
   */
  public function getFormId()
  {
    return 'hs_trade.make_offer_form';
  }

  /**
   * buildForm() generates form fields and their options
   * - $controller_data arguments are passed in from the formBuilder service when the form is retrieved in a controller
   * - Offer type (passed in through controller data) determines which fields are built out
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $controller_data = [])
  {

    /* Counter offer preparation creates a unique situation in which user transaction roles are about to swap, and
       because of this, the form must be generated as if the responder is already the requester. By simply swapping
       UIDs for counter offer forms, redundant transaction writing can be avoided.*/
    $requester_uid = $controller_data['requester_uid'];
    $responder_uid = $controller_data['responder_uid'];
    if ($controller_data['offer_type'] === 'counter') {
      $requester_uid = $controller_data['responder_uid'];
      $responder_uid = $controller_data['requester_uid'];
    }

    $entity_manager = $this->entityTypeManager->getStorage('node');

    //Query all the items for the requesting user and provide an option for each item
    $requester_items_query = $entity_manager->getQuery();
    $requester_results = $requester_items_query->condition('uid', $requester_uid)->execute();
    $requester_items = $entity_manager->loadMultiple($requester_results);
    $requester_item_selections = [];
    foreach ($requester_items as $item) {
      //Only include item IDs from 'item' nodes
      if ($item->bundle() === 'item') {
        $requester_item_selections[$item->id()] = $item->id();
      }
    }

    //Query all the items for the responding user and provide an option for each item
    $responder_items_query = $entity_manager->getQuery();
    $responder_results = $responder_items_query->condition('uid', $responder_uid)->execute();
    $responder_items = $entity_manager->loadMultiple($responder_results);
    $responder_item_selections = [];
    foreach ($responder_items as $item) {
      //If a new offer, don't include the active item as it's implied and a teaser is already displayed
      if ($controller_data['offer_type'] === 'new') {
        if (($item->bundle() === 'item' && $item->id() != $controller_data['first_item_id'])) {
          $responder_item_selections[$item->id()] = $item->id();
        }//Include all requester item IDs
      } else if ($controller_data['offer_type'] === 'counter') {
        if (($item->bundle() === 'item')) {
          $responder_item_selections[$item->id()] = $item->id();
        }
      }
    }

    //Get the responder's display name for use as markup
    $responder = $this->entityTypeManager->getStorage('user')->load($responder_uid);

    //Generate a list of item options from pre-generated array of responder items
    $form['responder_item_selection'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select @Responder\'s Items', ['@Responder' => $responder->getDisplayName()]),
      '#options' => [],
      '#required' => TRUE,
      'residual' => [
        '#markup' => '<div class="responder-residual"><h3 class="hs--hobbycredit-after"></h3></div>'
      ],
      '#attributes' => [
        'class' => ['col-lg-6 col-12']
      ],
    ];
    //If the responder has no items to trade, display message instead of form
    if (!empty($responder_item_selections)) {
      //Only pass in the options if any are present
      $form['responder_item_selection']['#options'] = $responder_item_selections;
    } else {
      $form['responder_item_selection']['no_options'] = [
        '#markup' => '
        <div class="item-teaser--container">
            <h2 class="hs-trade-no-items">This user doesn\'t have any other items to trade!</h2>
        </div>',
      ];
    }

    //Don't make additional responder items required if it's a new offer
    if ($controller_data['offer_type'] === 'new') {
      $form['responder_item_selection']['#required'] = FALSE;
    }

    //Generate a list of item options from pre-generated array of requester items
    $form['requester_item_selection'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select Your Items'),
      '#options' => [],
      '#required' => TRUE,
      'residual' => [
        '#markup' => '<div class="requester-residual"><h3 class="hs--hobbycredit-after"></h3></div>'
      ],
      '#attributes' => [
        'class' => ['col-lg-6 col-12']
      ],
    ];
    $form['enforce_residual'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enforce Residual'),
      '#description' => $this->t('Unchecking this box bypasses the residual system therefore requiring no use of HobbyCredit'),
      '#default_value' => TRUE,
    ];
    //If the requester has no items to trade, display message instead of form
    if (!empty($requester_item_selections)) {
      //Only pass in the options if any are present
      $form['requester_item_selection']['#options'] = $requester_item_selections;
      //Hidden field to hold controller data for use in submitForm() method
      $form['controller_data'] = [
        '#type' => 'hidden',
        //Convert controller data into deconstructable string to be passed through form_state
        '#value' => http_build_query($controller_data, '', '&'),
      ];
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => 'MAKE OFFER',
        '#attributes' => [
          'class' => ['btn-gray4']
        ]
      ];
    } else {
      $form['requester_item_selection']['no_options'] = [
        '#markup' => '
        <div class="item-teaser--container">
            <h2 class="hs-trade-no-items">Sorry, it looks you don\'t have any items to trade!</h2>
        </div>',
      ];
    }

    return $form;
  }

  /**
   * submitForm() either creates or modifies a transaction entity dependent on offer type and selected items
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {

    //Convert controller data back into an array
    parse_str($form_state->getValue('controller_data'), $controller_data);

    //Filter out all responder items that weren't selected
    if (!empty($form_state->getValue('responder_item_selection'))) {
      foreach ($form_state->getValue('responder_item_selection') as $item) {
        if ($item != 0) {
          $controller_data['responder_items'][] = $item;
        }
      }
    }
    //Filter out all requester items that weren't selected
    foreach ($form_state->getValue('requester_item_selection') as $item) {
      if ($item != 0) {
        $controller_data['requester_items'][] = $item;
      }
    }

    //Get enforce_residual value and hand it off to the controller_data array for processing
    $controller_data['enforce_residual'] = $form_state->getValue('enforce_residual');

    if ($controller_data['offer_type'] === 'new') {
      //Make a new offer, add it to the responder's unread transactions, and invalidate user transaction caches
      $controller_data['responder_items'][] = $controller_data['first_item_id'];
      $this->transactionManager->makeNewOffer($controller_data);
      $this->cacheTagsInvalidator->invalidateTags(['hs_trade.user_transactions_view']);
    } else if ($controller_data['offer_type'] === 'counter') {
      //Make a counter offer and invalidate user transaction caches
      $this->transactionManager->makeCounterOffer($controller_data);
      $this->cacheTagsInvalidator->invalidateTags(['hs_trade.user_transactions_view']);
    }

    //Redirect the user to their transactions page
    $form_state->setRedirect('hs_trade.user_view_transactions', ['user' => \Drupal::currentUser()->id()]);

  }
}
