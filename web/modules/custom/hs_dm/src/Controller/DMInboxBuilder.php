<?php

namespace Drupal\hs_dm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Psr\Container\ContainerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\hs_trade\TransactionManagerInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Url;

class DMInboxBuilder extends ControllerBase{

  private $privateMessageService;
  private $dateFormatter;
  private $transactionManager;
  protected $csrfToken;
  function __construct(
    PrivateMessageServiceInterface $privateMessageService,
    DateFormatterInterface $dateFormatter,
    TransactionManagerInterface $transactionManager,
    CsrfTokenGenerator $csrfToken
  ){
    $this->privateMessageService = $privateMessageService;
    $this->dateFormatter = $dateFormatter;
    $this->transactionManager = $transactionManager;
    $this->csrfToken = $csrfToken;
  }
  public static function create(ContainerInterface $container){
    return new static(
      $container->get('private_message.service'),
      $container->get('date.formatter'),
      $container->get('hs_trade.transaction_manager'),
      $container->get('csrf_token')
    );
  }

  public function buildInbox(){

    $url = Url::fromRoute('hs_dm.ajax_callback', ['op' => 'get_rendered_threads']);
    $token = $this->csrfToken->get($url->getInternalPath());
    $url->setOptions(['query' => ['token' => $token]]);

    $threads = $this->getUserThreads();
    $return = [
      '#theme' => 'hs_dm__inbox',
      '#threads' => $threads,
      '#attached' => [
        'library' => ['hs_dm/inbox'],
        'drupalSettings' => ['getUnreadThreadsCallback' => $url->toString()],
      ],
      '#prefix' => '<div class="hs-dm--inbox">',
      '#suffix' => '</div>',
    ];
    if(empty($threads)){
      $return['#suffix'] = '
        <div class="no-threads">
            <h2>You don\'t have any messages right now, but if anyone makes an offer on your items, a thread will be opened between you and that user to discuss transaction details, counter offers, etc.</h2>
        </div>
        </div>
      ';
    }
    return $return;
  }

  public function getUserThreads(){
    $threads = $this->privateMessageService->getThreadsForUser(0);

    $threads = array_map(function($thread){
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
          $is_unread = TRUE;
        }
      }

      return[
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
    }, $threads['threads']);

    return $threads;
  }

}
