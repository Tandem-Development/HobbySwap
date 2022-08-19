<?php

/**
 * Essentially boilerplate entity access controller
 * Permission names are altered to match permissions created in module configuration
 */

namespace Drupal\hs_trade;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

class TransactionAccessControlHandler extends EntityAccessControlHandler {

  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $admin_permission = $this->entityType->getAdminPermission();
    if($account->hasPermission($admin_permission)) {
      return AccessResult::allowed();
    }
    switch($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view transaction entity');
      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit transaction entity');
      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete transaction entity');
    }
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $admin_permission = $this->entityType->getAdminPermission();
    if($account->hasPermission($admin_permission)) {
      return  AccessResult::allowed();
    }
    return AccessResult::allowedIfHasPermission($account, 'add transaction entity');
  }

}
