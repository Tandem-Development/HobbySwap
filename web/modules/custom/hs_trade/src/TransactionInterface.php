<?php

/**
 * Creates a simple interface for the transaction entity
 */

namespace Drupal\hs_trade;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * @ingroup hs_trade
 */
interface TransactionInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
