<?php

namespace Drupal\hs_dm\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\user\Entity\User;
use Psr\Container\ContainerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\hs_trade\TransactionManagerInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Url;

class DMInboxBuilder extends ControllerBase{

  protected $privateMessageService;
  protected $dateFormatter;
  protected $transactionManager;
  protected $csrfToken;
  protected $currentUser;
  function __construct(
    PrivateMessageServiceInterface $privateMessageService,
    DateFormatterInterface $dateFormatter,
    TransactionManagerInterface $transactionManager,
    CsrfTokenGenerator $csrfToken,
    AccountProxyInterface $currentUser
  ){
    $this->privateMessageService = $privateMessageService;
    $this->dateFormatter = $dateFormatter;
    $this->transactionManager = $transactionManager;
    $this->csrfToken = $csrfToken;
    $this->currentUser = $currentUser;
  }
  public static function create(ContainerInterface $container){
    return new static(
      $container->get('private_message.service'),
      $container->get('date.formatter'),
      $container->get('hs_trade.transaction_manager'),
      $container->get('csrf_token'),
      $container->get('current_user')
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
      //Get the users involved in the thread
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
        //If the user is still present in the thread, save that user to retrieve its values
        $member_ids = array_map(function ($member){
          return $member->id();
        }, $thread_members);
        unset($thread_members[array_search($current_user, $member_ids)]);
        $other_member = reset($thread_members);
        if(!empty($other_member->user_picture->entity)){
          $other_member_image = $other_member->user_picture->entity->createFileUrl();
        }
      }

      $newest_message_text = ($newest_message->getOwner()->id() === $current_user) ? 'You: ' : '';
      $newest_message_text .= strip_tags($newest_message->getMessage());

      $is_unread = FALSE;
      if($thread->getUpdatedTime() > $thread->getLastAccessTimestamp($this->currentUser)){
        if($newest_message->getOwner()->id() !== $current_user){
          $is_unread = TRUE;
        }
      }

      return[
        'id' => $thread->id(),
        'unread' => $is_unread,
        'other_member' => $other_member,
        'other_member_image' =>  $other_member_image,
        'newest_message' => $newest_message_text,
        'newest_message_time' => $this->dateFormatter->format($newest_message->getCreatedTime(), 'short'),
        'transaction' => $this->transactionManager->getPMTransactionId($thread->id()),
      ];
    }, $threads['threads']);

    return $threads;
  }

}
