<?php

namespace Drupal\hs_dm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\hs_dm\Ajax\GetRenderedThreads;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\user\Entity\User;
use Psr\Container\ContainerInterface;
use Drupal\hs_trade\TransactionManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Render\RendererInterface;

class AjaxController extends ControllerBase{

  protected $privateMessageService;
  protected $dateFormatter;
  protected $transactionManager;
  protected $renderer;
  protected $currentUser;
  function __construct(
    PrivateMessageServiceInterface $privateMessageService,
    TransactionManagerInterface $transactionManager,
    DateFormatterInterface $dateFormatter,
    RendererInterface $renderer,
    AccountProxyInterface $currentUser
  )
  {
    $this->privateMessageService = $privateMessageService;
    $this->transactionManager = $transactionManager;
    $this->dateFormatter = $dateFormatter;
    $this->renderer = $renderer;
    $this->currentUser = $currentUser;
  }
  public static function create(ContainerInterface $container){
    return new static(
      $container->get('private_message.service'),
      $container->get('hs_trade.transaction_manager'),
      $container->get('date.formatter'),
      $container->get('renderer'),
      $container->get('current_user')
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
      $thread_members = $thread->getMembers();
      $current_user = $this->currentUser->id();

      $messages = $thread->getMessages();
      $newest_message = end($messages);
      $other_member_image = '/themes/custom/hobbyswap/logo.png';

      //If a user left the thread or had their account deleted, create a placeholder User entity to prevent null errors
      if(count($thread_members) === 1){
        $other_member = User::create(['uid' => 0, 'name' => 'MISSING']);
        $newest_message->setOwner($other_member);
      }else{
        //If the user is still present in the thread, store that user to retrieve data
        $member_ids = array_map(function ($member){
          return $member->id();
        }, $thread_members);
        unset($thread_members[array_search($current_user, $member_ids)]);
        $other_member = reset($thread_members);
        if(!empty($other_member->user_picture)){
          $other_member_image = $other_member->user_picture->entity->createFileUrl();
        }
      }

      $newest_message_text = ($newest_message->getOwner()->id() === $current_user) ? 'You: ' : '';
      $newest_message_text .= strip_tags($newest_message->getMessage());

      $is_unread = FALSE;
      if($thread->getUpdatedTime() > $thread->getLastAccessTimestamp($this->currentUser)){
        if($newest_message->getOwner()->id() !== $current_user){
          $unread_threads[$thread->id()] = $newest_message_text;
          $is_unread = TRUE;
        }
      }

      $threads[] = [
        'id' => $thread->id(),
        'unread' => $is_unread,
        'other_member' => $other_member,
        'other_member_image' =>  $other_member_image,
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
