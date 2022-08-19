<?php

namespace Drupal\hs_trade\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hs_trade\Ajax\UpdateNotificationsCommand;
use Psr\Container\ContainerInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\hs_trade\TransactionManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\hs_trade\Ajax\GetRenderedTransactionsCommand;

class AjaxController extends ControllerBase{

  //Boilerplate dependency injection for 'entity_type.manager' service
  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * @var \Drupal\private_message\Service\PrivateMessageServiceInterface
   */
  protected $privateMessageService;
  /**
   * @var \Drupal\hs_trade\TransactionManagerInterface
   */
  protected $transactionManager;
  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    PrivateMessageServiceInterface $privateMessageService,
    TransactionManagerInterface $transactionManager,
    RendererInterface $renderer
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->privateMessageService = $privateMessageService;
    $this->transactionManager = $transactionManager;
    $this->renderer = $renderer;
  }
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('private_message.service'),
      $container->get('hs_trade.transaction_manager'),
      $container->get('renderer')
    );
  }

  public function ajaxCallback($op){
    $response = new AjaxResponse();
    switch ($op){
      case 'get_notifications':
        $this->updateNotifications($response);
        break;
      case 'get_rendered_transactions':
        $this->getRenderedTransactions($response);
        break;
    }
    return $response;
  }

  public function updateNotifications(AjaxResponse $response){
    $user = $this->entityTypeManager->getStorage('user')->load(\Drupal::currentUser()->id());
    $unread_transactions = array_map(function($value){
      return $value['value'];
    }, $user->get('unread_transactions')->getValue());

    $unread_threads = [];
    foreach($this->privateMessageService->getThreadsForUser(0)['threads'] as $thread) {
      $messages = $thread->getMessages();
      $newest_message = end($messages);
      if($thread->getUpdatedTime() > $thread->getLastAccessTimestamp(\Drupal::currentUser())){
        if($newest_message->getOwner()->id() !== \Drupal::currentUser()->id()){
          $unread_threads[] = $thread->id();
        }
      }
    }

    $response->addCommand(new UpdateNotificationsCommand($unread_transactions, $unread_threads));
  }

  public function getRenderedTransactions(AjaxResponse $response){
    $user = \Drupal::request()->query->get('user');
    //Query the IDs of incoming and outgoing transactions before passing
    $transactionStorage = $this->entityTypeManager()->getStorage('hs_trade_transaction');
    $incoming = $transactionStorage->getQuery()
      ->condition('responder_uid', $user)
      ->sort('changed', 'DESC')
      ->execute();
    $outgoing = $transactionStorage->getQuery()
      ->condition('requester_uid', $user)
      ->sort('changed', 'DESC')
      ->execute();

    $incoming_transactions = $this->transactionManager->renderTransactions($incoming, 'incoming');
    $incoming_rendered = $this->renderer->render($incoming_transactions);
    $outgoing_transactions = $this->transactionManager->renderTransactions($outgoing, 'outgoing');
    $outgoing_rendered = $this->renderer->render($outgoing_transactions);

    $response->addCommand(new GetRenderedTransactionsCommand($incoming_rendered, $outgoing_rendered));
  }
}
