<?php

namespace Drupal\hs_trade\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\hs_trade\TransactionInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Entity\EntityChangedTrait;

/**
 * Defines the Transaction content entity
 *
 * @ingroup hs_trade
 * @ContentEntityType(
 *   id = "hs_trade_transaction",
 *   label = @Translation("Transaction Entity"),
 *   handlers = {
 *      "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *      "list_builder" = "Drupal\hs_trade\Entity\Controller\TransactionListBuilder",
 *      "form" = {
 *          "default" = "Drupal\hs_trade\Form\TransactionForm",
 *          "delete" = "Drupal\hs_trade\Form\TransactionDeleteForm",
 *      },
 *      "access" = "Drupal\hs_trade\TransactionAccessControlHandler",
 *   },
 *   list_cache_contexts = { "user" },
 *   base_table = "transaction",
 *   admin_permission = "administer transaction entity",
 *   entity_keys = {
 *    "id" = "id"
 *   },
 *   links = {
 *      "canonical" = "/transaction/{transaction_id}",
 *      "edit-form" = "/transaction/{transaction_id}/edit",
 *      "delete-form" = "/transaction/{transaction_id}/delete",
 *      "collection" = "/transaction/list",
 *   },
 *   field_ui_base_route = "hs_trade.transaction_settings",
 * )
 */
class Transaction extends ContentEntityBase implements TransactionInterface{

  use EntityChangedTrait;

  public static function preCreate(EntityStorageInterface $storage_controller, array &$values){
    parent::preCreate($storage_controller, $values);
  }

  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  public function setResponderUID($uid) {
    $this->set('responder_uid', $uid);
    return $this;
  }

  public function getResponder(){
    return \Drupal::entityTypeManager()->getStorage('user')->load($this->get('responder_uid')->value);
  }

  public function setRequesterUID($uid) {
    $this->set('requester_uid', $uid);
    return $this;
  }

  public function getRequester(){
    return \Drupal::entityTypeManager()->getStorage('user')->load($this->get('requester_uid')->value);
  }

  public function setResponderItems(array $items) {
    $this->set('responder_items', $items);
    return $this;
  }

  public function getResponderItems(){
    $items = $this->get('responder_items')->getValue();
    return array_map(function($id){
      return $id['value'];
    }, $items);
  }

  public function setRequesterItems(array $items) {
    $this->set('requester_items', $items);
    return $this;
  }

  public function getRequesterItems(){
    $items = $this->get('requester_items')->getValue();
    return array_map(function($id){
      return $id['value'];
    }, $items);
  }

  public function setStatus($status) {
    $this->set('status', $status);
    return $this;
  }

  public function getStatus(){
    return $this->get('status')->value;
  }

  public function setResidual($residual) {
    $this->set('residual', $residual);
    return $this;
  }

  public function getResidual(){
    return $this->get('residual')->value;
  }

  public function isResidualEnforced(){
    $transaction_manager = \Drupal::service('hs_trade.transaction_manager');
    $residual = $transaction_manager->calculateResidual($this->getResponderItems(), $this->getRequesterItems());
    if($residual == $this->getResidual()){
      return TRUE;
    }else{
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type){

    //Standard ID field for the transaction
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Transaction entity'))
      ->setReadOnly(TRUE);

    //The UID of the current responder
    $fields['responder_uid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Responder UID'))
      ->setDescription(t('The UID of the user whom the transaction awaits a response from.'))
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -8,
      ]);

    //The UID of the current requester
    $fields['requester_uid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Requester UID'))
      ->setDescription(t('The UID of the user who made the latest transaction request.'))
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -8,
      ]);

    //The IDs of the responder's requested items
    $fields['responder_items'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Responder Items'))
      ->setDescription(t('Stores the IDs of the requested responder items'))
      ->setCardinality(10)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'integer',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('view', TRUE);

    //The IDs of the items offered by the requester
    $fields['requester_items'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Requester Items'))
      ->setDescription(t('Stores the IDs of the offered requester items'))
      ->setCardinality(10)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'integer',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('view', TRUE);

    //The private message thread ID assigned to the transaction
    $fields['message_thread'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Message Thread'))
      ->setDescription(t('The ID for the private message thread that belongs to the transaction'))
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -8,
      ]);

    //The transaction's current status used to determine available actions
    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Status'))
      ->setDescription(t('The status of the Transaction entity'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue(NULL)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    //The residual tracks any difference in collective value between offered/requested items
    $fields['residual'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('HC Residual'))
      ->setDescription(t('The amount of additional HC to be paid by the requester upon request acceptance.'))
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -8,
      ]);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The language code of Transaction entity.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the transaction was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the transaction was last edited.'));

    return $fields;

  }



}
