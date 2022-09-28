<?php

namespace Drupal\hs_user;

interface SubscriptionManagerInterface{

  public function setUserSubscription($uid, $plan);

  public function isUserSubscriptionExpired($uid);

  public function isPlanDefined($plan);

  public function getPlanData($plan);

  public function removeUserSubscription($uid);

}