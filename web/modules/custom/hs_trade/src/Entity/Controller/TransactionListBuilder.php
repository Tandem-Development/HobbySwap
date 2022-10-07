<?php

/**
 * This is a standard list builder for displaying all transaction entities
 * Only the headers and rows have been modified from the boilerplate list builder
 */

namespace Drupal\hs_trade\Entity\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Render\FormattableMarkup;

/**
 * Provides a list controller for hs_trade_transaction entity
 * @ingroup hs_trade
 */
class TransactionListBuilder extends EntityListBuilder{

  /**
   * The url generator
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('url_generator')
    );
  }

  /**
   * Constructs a new TransactionListBuilder object
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, UrlGeneratorInterface $url_generator){
    parent::__construct($entity_type, $storage);
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['description'] = [
      '#markup' => $this->t('These transactions are fieldable entities. You can manage the fields on the <a href="@adminlink">Transactions admin page</a>.', [
        '@adminlink' => $this->urlGenerator->generateFromRoute('hs_trade.transaction_settings'),
      ]),
    ];
    $build['table'] = parent::render();
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Transaction ID');
    $header['status'] = $this->t('Status');
    $header['responder'] = $this->t('Responder');
    $header['requester'] = $this->t('Requester');
    $header['enforced_residual'] = $this->t('Enforced Residual');
    $header['residual'] = $this->t('HC Residual');
    $header['created'] = $this->t('Created');
    $header['changed'] = $this->t('Changed');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\hs_trade\Entity\Transaction */

    $responder = $entity->getResponder();
    $requester = $entity->getRequester();

    if($entity->residual->value < 0){
      $residual = 'Req: '.abs($entity->residual->value);
    }else if($entity->residual->value > 0){
      $residual = 'Resp: '.$entity->residual->value;
    }else{
      $residual = 'NA';
    }

    $row['id'] = $entity->id();
    $row['status'] = $entity->status->value;
    $row['responder'] = new FormattableMarkup('<a href="/user/@uid">@username</a>', ['@uid' => $responder->id(), '@username' => $responder->getDisplayName()]);
    $row['requester'] = new FormattableMarkup('<a href="/user/@uid">@username</a>', ['@uid' => $requester->id(), '@username' => $requester->getDisplayName()]);;
    $row['enforced_residual'] = $entity->isResidualEnforced() == TRUE ? 'Yes': 'No';
    $row['residual'] = $residual;
    $row['created'] = \Drupal::service('date.formatter')->format($entity->created->value);
    $row['changed'] = \Drupal::service('date.formatter')->format($entity->changed->value);
    return $row + parent::buildRow($entity);
  }

}
