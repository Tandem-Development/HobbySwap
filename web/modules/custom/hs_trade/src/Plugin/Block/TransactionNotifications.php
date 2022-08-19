<?php

namespace Drupal\hs_trade\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Url;
use Psr\Container\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Provides an ajaxified notifications block to notify users of trade activity
 *
 * @Block(
 *   id = "hs_trade_transaction_notifications",
 *   admin_label = @Translation("HS Transaction Notifications"),
 *   category = @Translation("HobbySwap")
 * )
 */


class TransactionNotifications extends BlockBase implements ContainerFactoryPluginInterface{

  protected $csrfToken;
  function __construct(array $configuration, $plugin_id, $plugin_definition, CsrfTokenGenerator $csrfToken){
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->csrfToken = $csrfToken;
  }
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition){
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('csrf_token')
    );
  }

  public function build(){

    $url = Url::fromRoute('hs_trade.ajax_callback', ['op' => 'get_notifications']);
    $token = $this->csrfToken->get($url->getInternalPath());
    $url->setOptions(['query' => ['token' => $token]]);

    return[
      '#theme' => 'notifications_block',
      '#attached' => [
        'library' => ['hs_trade/ajax-update-notifications'],
        'drupalSettings' => ['getNotifications' => $url->toString()],
      ],
    ];
  }

}
