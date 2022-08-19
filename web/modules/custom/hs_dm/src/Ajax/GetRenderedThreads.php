<?php

namespace Drupal\hs_dm\Ajax;

use Drupal\Core\Ajax\CommandInterface;

class GetRenderedThreads implements CommandInterface{

  private $rendered_threads;
  private $unread_threads;

  public function __construct($rendered_threads, $unread_threads){
    $this->rendered_threads = $rendered_threads;
    $this->unread_threads = $unread_threads;
  }

  public function render(){
    return[
      'command' => 'getRenderedThreads',
      'rendered_threads' => $this->rendered_threads,
      'unread_threads' => $this->unread_threads,
    ];
  }
}
