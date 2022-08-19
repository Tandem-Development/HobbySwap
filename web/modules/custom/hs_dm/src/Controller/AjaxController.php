<?php

namespace Drupal\hs_dm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\hs_dm\Ajax\GetRenderedThreads;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\hs_trade\TransactionManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Render\RendererInterface;

class AjaxController extends ControllerBase{

  private $privateMessageService;
  private $dateFormatter;
  private $transactionManager;
  private $renderer;
  function __construct(PrivateMessageServiceInterface $privateMessageService, TransactionManagerInterface $transactionManager, DateFormatterInterface $dateFormatter, RendererInterface $renderer){
    $this->privateMessageService = $privateMessageService;
    $this->transactionManager = $transactionManager;
    $this->dateFormatter = $dateFormatter;
    $this->renderer = $renderer;
  }
  public static function create(ContainerInterface $container){
    return new static(
      $container->get('private_message.service'),
      $container->get('hs_trade.transaction_manager'),
      $container->get('date.formatter'),
      $container->get('renderer')
    );
  }

  public function ajaxCallback($op){

    $response = new AjaxResponse();

    switch($op){
      case 'get_rendered_threads':
        $this->getRenderedThreads($response);
        break;
    }
    return $response;
  }

  public function getRenderedThreads(AjaxResponse $response){

    $unread_threads = [];
    $threads = [];
    foreach($this->privateMessageService->getThreadsForUser(0)['threads'] as $thread) {
      $other_member = NULL;
      foreach($thread->getMembers() as $member){
        if($member->id() !== \Drupal::currentUser()->id()){
          $other_member = $member;
        }
      }
      $other_member_image = NULL;
      if(!empty($other_member->user_picture->entity)){
        $other_member_image = $other_member->user_picture->entity->createFileUrl();
      }
      $messages = $thread->getMessages();
      $newest_message = end($messages);
      $newest_message_text = $newest_message->getOwner()->id() === \Drupal::currentUser()->id() ? 'You: ' : '';
      $newest_message_text .= strip_tags($newest_message->getMessage());

      $is_unread = FALSE;
      if($thread->getUpdatedTime() > $thread->getLastAccessTimestamp(\Drupal::currentUser())){
        if($newest_message->getOwner()->id() !== \Drupal::currentUser()->id()){
          $unread_threads[$thread->id()] = $newest_message_text;
          $is_unread = TRUE;
        }
      }


      $threads[] = [
        'id' => $thread->id(),
        'unread' => $is_unread,
        'other_member' => [
          'id' => $other_member->id(),
          'name' => $other_member->getDisplayName(),
          'image' =>  $other_member_image,
        ],
        'newest_message' => $newest_message_text,
        'newest_message_time' => $this->dateFormatter->format($newest_message->getCreatedTime(), 'short'),
        'transaction' => $this->transactionManager->getPMTransactionId($thread->id()),
      ];


    }

    $render_array = [
      '#theme' => 'hs_dm__inbox',
      '#threads' => $threads,
    ];

    $rendered_threads = $this->renderer->render($render_array);

    $response->addCommand(new GetRenderedThreads($rendered_threads, $unread_threads));

  }

}
