<?php

/**
 * This controller is responsible for the following
 * - Displaying a teaser of the selected item
 * - Generating a "MakeOfferForm" form of 'offer_type' = 'new' to create a new transaction entity
 */

namespace Drupal\hs_trade\Controller;

use \Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\HttpFoundation\RedirectResponse;

class MakeOfferController extends ControllerBase{

  public function tradeAccess(AccountInterface $account, $id) {
    $item = $this->entityTypeManager()->getStorage('node')->load($id);
    if($account->id() === $item->getOwner()->id()){
      return AccessResult::forbidden();
    }
    return AccessResult::allowed();
  }

  /**
   * Sets the title of the page
   */
  public function tradeTitle($id){
    return 'Make an Offer';
  }

  /**
   * ItemTradeController::viewTrade is the default controller for the 'hs_trade.item_trade' route
   * The $id parameter is the NID of the selected item
   */
  public function viewTrade($id) {

    //Calls the getItem() helper method to return a structured array of item values
    $item = $this->getItem($id);
    //Set the proper $controller_data values to customize how the form behaves
    $controller_data = [
      'offer_type' => 'new',
      'responder_uid' => $item['responder']['id'],
      'requester_uid' => \Drupal::currentUser()->id(),
      'selected_item' => $id
    ];

    //Loads MakeOfferForm and passes in $controller_data to apply desired form behavior
    $form = \Drupal::formBuilder()->getForm('\Drupal\hs_trade\Form\MakeOfferForm', $controller_data);
    $form['#attributes']['class'][] = 'row';

    //$item returns an error string if an item couldn't be loaded from getItem()
    if(gettype($item) === 'string') {
      return [
        '#markup' => '<h2>' . $item . '</h2>'
      ];
    }

    //Display the item_trade teaser template and generate the loaded form
    return[
      'item_trade' => [
        '#theme' => 'item_trade',
        '#item' => $item,
        '#attached' => [
          'library' => ['hs_trade/trade-page'],
        ],
      ],
      'form' => $form
    ];
  }

  /**
   * Helper method that returns a structured array of item values ready to be passed to the 'item_trade' theme
   */
  private function getItem($id) {
    //Load the node with the passed ID
    $itemQuery = $this->entityTypeManager()->getStorage('node')->load($id);
    $item = null;
    //If the item query returns nothing or a node other than an item, return an error string
    //Otherwise, load the item for value retrieval
    if($itemQuery == NULL || $itemQuery->bundle() != 'item'){
      return 'Sorry, this item doesn\'t exist!';
    }else{
      $item = $itemQuery;
    }

    //Get the item's file entities and create an array of their Urls
    $file_ids = $item->get('field_item_image')->getValue();
    $files = $this->entityTypeManager()->getStorage('file')->loadMultiple(array_column($file_ids, 'target_id'));
    $files = array_map(function($file) {
      if(!empty($file)){
        return ['image_path' => $file->createFileUrl()];
      }
    }, $files);

    //Create the array with necessary values and return it
    return [
      'title' => $item->getTitle(),
      'value' => $item->get('field_item_value')->getValue()[0]['value'],
      'description' => $item->get('field_item_description')->getValue()[0]['value'],
      'images' => $files,
      'responder' => [
        'id' => $item->getOwner()->id(),
        'name' => $item->getOwner()->getDisplayName(),
        'path' => $item->getOwner()->toUrl()->getInternalPath(),
      ],
      'bundle' => $item->bundle(),
    ];

  }

}
