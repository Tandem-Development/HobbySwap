<?php

namespace Drupal\hs_user;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\Entity\User;

/**
 * Provides a variety of helper methods for managing users' subscription statuses across the site
 */
class SubscriptionManager implements SubscriptionManagerInterface{

  protected $entityTypeManager;
  protected $currentUser;
  protected $configFactory;
  protected $time;

  function __construct(EntityTypeManagerInterface $entityTypeManager, AccountProxyInterface $currentUser, ConfigFactoryInterface $configFactory, TimeInterface $time){
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->configFactory = $configFactory;
    $this->time = $time;
  }


  /**
   * Updates a user's subscription to a valid plan defined in the hs_user.subscriptions config
   */
  public function setUserSubscription($uid, $plan){
    $current_time = $this->time->getRequestTime();
    $user = User::load($uid);
    //Verify the plan definition exists before attempting an update
    if($this->isPlanDefined($plan) !== false){
      //Update the value of 'field_subscription' and when it's update timestamp
      $user
        ->set('field_subscription', $plan)
        ->set('field_subscription_updated', $current_time);
      //Give the user the 'subscribed' role if they don't already have it
      if(!$user->hasRole('subscribed')){
        $user->addRole('subscribed');
      }
      $user->save();
      return $plan;
    }else{
      return null;
    }
  }


  /**
   * Removes the 'subscribed' role from a user and updates the appropriate user fields
   */
  public function removeUserSubscription($uid){
    //Load the requested user
    $user = User::load($uid);
    if(!empty($user)){
      $current_time = $this->time->getRequestTime();
      //Set the user's subscription to null, update the timestamp, and remove the 'subscribed' role
      $user
        ->set('field_subscription', null)
        ->set('field_subscription_updated', $current_time)
        ->removeRole('subscribed');
      $user->save();
    }
  }


  /**
   * Compares the last time a user's subscription was updated to the current time and returns true or false accordingly
   */
  public function isUserSubscriptionExpired($uid){
    $user = User::load($uid);
    //Return null if the user isn't subscribed at all
    if(!$user->hasRole('subscribed')){
      return null;
    }
    $current_plan_data = $this->getPlanData($user->get('field_subscription')->value);
    $subscription_updated = $user->get('field_subscription_updated')->value;
    $current_time = $this->time->getRequestTime();
    //If the user's subscription is not expired based on their plan's duration, return true
    if(($current_time - $subscription_updated) > intval($current_plan_data['duration']) * 2628288){
      return true;
    }
    //Otherwise, return false
    return false;
  }


  /**
   * Compares the provided plan against the configuration to ensure that it's defined and with all the correct attributes
   */
  public function isPlanDefined($plan){
    //Get subscription plan data from configuration and store all valid plans
    $subscription_data = $this->configFactory->get('hs_user.subscriptions')->getRawData()['subscriptions'];
    $valid_plans = array_keys($subscription_data);
    //Each value in this array marks a required plan attribute/key
    $required_attributes = ['label', 'cost', 'cost_label', 'duration', 'features'];
    if(!in_array($plan, $valid_plans)){
      //Return false if the plan's name doesn't exist
      return false;
    }else{
      foreach($required_attributes as $attribute){
        if(!array_key_exists($attribute, $subscription_data[$plan])){
          //If a plan is missing a required attribute, it's invalid
          return false;
        }
      }
      return $plan;
    }
  }


  /**
   * Returns a structured array of a plan and it's attributes if the plan is defined
   */
  public function getPlanData($plan){
    if($this->isPlanDefined($plan) !== false){
      $plan_data = $this->configFactory->get('hs_user.subscriptions')->getRawData()['subscriptions'][$plan];
      $plan_data['name'] = $plan;
      return $plan_data;
    }else{
      return null;
    }
  }
}