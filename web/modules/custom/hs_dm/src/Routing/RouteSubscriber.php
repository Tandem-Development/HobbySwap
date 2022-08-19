<?php

namespace Drupal\hs_dm\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase{

  public function alterRoutes(RouteCollection $collection){
    //Alter private_message module inbox route
    if($route = $collection->get('private_message.private_message_page')){
      $route->setPath('/inbox');
      $route->setDefault('_title', 'Direct Messages');
      $route->setDefault('_controller', '\Drupal\hs_dm\Controller\DMInboxBuilder::buildInbox');
    }
    //Restrict access to non-admins on non-transaction related private message threads
    if($route = $collection->get('private_message.private_message_create')){
      $route->setOption('_admin_route', 'TRUE');
      $route->setRequirement('_permission', 'administer private message module');
    }
  }

}
