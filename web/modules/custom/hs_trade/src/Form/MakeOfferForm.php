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
use Drupal\Core\Session\AccountProxyInterface;

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
  /**
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  private $currentUser;

  function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    TransactionManagerInterface $transactionManager,
    AccountProxyInterface $currentUser
  )
  {
    $this->entityTypeManager = $entityTypeManager;
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
    $this->transactionManager = $transactionManager;
    $this->currentUser = $currentUser;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('cache_tags.invalidator'),
      $container->get('hs_trade.transaction_manager'),
      $container->get('current_user')
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

    //Query each user's items and convert them into usable item lists
    $responder_results = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('field_item_status', 'Available')
      ->condition('type', 'item')
      ->condition('uid', $responder_uid)->execute();
    $requester_results = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('field_item_status', 'Available')
      ->condition('type', 'item')
      ->condition('uid', $requester_uid)->execute();
    $responder_item_selections = array_combine(array_values($responder_results), array_values($responder_results));
    $requester_item_selections = array_combine(array_values($requester_results), array_values($requester_results));

    //Get the responder's display name for use as markup
    $responder = $this->entityTypeManager->getStorage('user')->load($responder_uid);

    //Generate a list of item options from pre-generated array of responder items
    $form['responder_item_selection'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select @Responder\'s Items', ['@Responder' => $responder->getDisplayName()]),
      '#options' => $responder_item_selections,
      '#required' => TRUE,
      'residual' => [
        '#markup' => '<div class="responder-residual"><h3 class="hs--hobbycredit-after"></h3></div>'
      ],
      '#attributes' => [
        'class' => ['col-lg-6 col-12']
      ],
    ];

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

    if(empty($requester_item_selections)){
      //If the requester has no items to trade, display message instead of form
      $form['requester_item_selection']['no_options'] = [
        '#markup' => '
        <div class="item-teaser--container">
            <h2 class="hs-trade-no-items">Sorry, it looks you don\'t have any items to trade!</h2>
        </div>',
      ];
      return $form;
    }

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
      '#attributes' => [
        'class' => ['btn-gray4']
      ]
    ];

    //Adjust the form based on offer type
    if($controller_data['offer_type'] === 'new'){
      $form['submit']['#value'] = $this->t('MAKE OFFER');
      $form['responder_item_selection']['#default_value'] = [$controller_data['selected_item']];
    }else if($controller_data['offer_type'] === 'counter'){
      $form['submit']['#value'] = $this->t('COUNTER OFFER');
      $form['responder_item_selection']['#default_value'] = $this->transactionManager->getTransaction($controller_data['transaction_id'])->getRequesterItems();
      $form['requester_item_selection']['#default_value'] = $this->transactionManager->getTransaction($controller_data['transaction_id'])->getResponderItems();
    }

    return $form;
  }

  /**
   * submitForm() either creates or modifies a transaction entity dependent on offer type and selected items
   */
  public function submitForm(array &$form, FormStateInterface $form_state, array $controller_data = [])
  {

    //Convert controller data back into an array
    parse_str($form_state->getValue('controller_data'), $controller_data);

    //Filter out all responder items that weren't selected
    $controller_data['responder_items'] = array_filter(
      $form_state->getValue('responder_item_selection'),
      function ($item){return $item != 0;}
    );
    //Filter out all requester items that weren't selected
    $controller_data['requester_items'] = array_filter(
      $form_state->getValue('requester_item_selection'),
      function ($item){return $item != 0;}
    );

    //Get enforce_residual value and hand it off to the controller_data array for processing
    $controller_data['enforce_residual'] = $form_state->getValue('enforce_residual');

    if ($controller_data['offer_type'] === 'new') {
      //Make a new offer, add it to the responder's unread transactions, and invalidate user transaction caches
      $this->transactionManager->makeNewOffer($controller_data);
    } else if ($controller_data['offer_type'] === 'counter') {
      //Make a counter offer and invalidate user transaction caches
      $this->transactionManager->makeCounterOffer($controller_data);
    }
    $this->cacheTagsInvalidator->invalidateTags(['hs_trade.user_transactions_view']);

    //Redirect the user to their transactions page
    $form_state->setRedirect('hs_trade.user_view_transactions', ['user' => $this->currentUser->id()]);

  }
}
