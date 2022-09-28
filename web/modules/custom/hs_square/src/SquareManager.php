<?php

namespace Drupal\hs_square;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\user\Entity\User;
use Square\Environment;
use Square\Models\Address;
use Square\Models\Card;
use Square\Models\CreateCardRequest;
use Square\Models\CreateCustomerRequest;
use Square\Models\CreatePaymentRequest;
use Square\Models\CustomerFilter;
use Square\Models\CustomerQuery;
use Square\Models\CustomerTextFilter;
use Square\Models\Money;
use Square\Models\SearchCustomersRequest;
use Square\SquareClient;

class SquareManager implements SquareManagerInterface {

  protected $configFactory;

  public function __construct(ConfigFactoryInterface $configFactory){
    $this->configFactory = $configFactory;
  }

  /**
   * Returns a Square client with the right access token for retrieving various APIs
   */
  public function openClient(){
    $square_settings = $this->configFactory->get('hs_square.settings');
    $access_token = $square_settings->get('access_token');
    $environment = $square_settings->get('environment');
    switch ($environment){
      case 'sandbox':
        $environment = Environment::SANDBOX;
        break;
      case 'production':
        $environment = Environment::PRODUCTION;
        break;
      case 'custom':
        $environment = Environment::CUSTOM;
    }
    return new SquareClient([
      'accessToken' => $access_token,
      'environment' => $environment,
    ]);
  }

  /**
   * Provides some default behavior to handle Square API responses
   */
  public function handleResponse($api_response){
    if($api_response->isSuccess()){
      return $api_response->getResult();
    }else{
      return $api_response->getErrors();
    }
  }

  /**
   * Square Customers are stored with reference IDs that correlate to Drupal UIDs. This method queries Square customers
   * by this reference ID and returns that customer as an object if successful.
   */
  public function retrieveCustomerByUid($uid){
    $reference_id = new CustomerTextFilter();
    $reference_id->setExact($uid);
    $filter = new CustomerFilter();
    $filter->setReferenceId($reference_id);
    $query = new CustomerQuery();
    $query->setFilter($filter);
    $body = new SearchCustomersRequest();
    $body->setQuery($query);

    $api_response = $this->openClient()->getCustomersApi()->searchCustomers($body);
    if($api_response->isSuccess()){
      return $api_response->getResult()->getCustomers()[0];
    }else{
      return $api_response->getErrors();
    }
  }

  public function retrieveCustomers(){
    $api_response = $this->openClient()->getCustomersApi()->listCustomers();
    return $this->handleResponse($api_response);
  }

  /**
   * Builds an API request to create a card for a specific customer (identified by Drupal UID).
   * $card_data can/must contain the following properties:
   * - given_name (REQUIRED)
   * - family_name (REQUIRED)
   * - address_line1
   * - address_line1
   * - locality
   * - administrative_area
   * - postal_code
   */
  public function addCardToCustomer($uid, $card_data, $source_id){
    //Retrieve the appropriate customer with the given UID
    $customer = $this->retrieveCustomerByUid($uid);
    //Create a new Address model and populate it with the provided card data
    $billing_address = new Address();
    $address_fields = [
      'setAddressLine1' => 'address_line1',
      'setAddressLine2' => 'address_line2',
      'setLocality' => 'locality',
      'setAdministrativeDistrictLevel1' => 'administrative_area',
      'setPostalCode' => 'postal_code',
    ];
    foreach($address_fields as $method => $field){
      if(!empty($card_data->{$field})){
        $billing_address->{$method}($card_data->{$field});
      }
    }
    $billing_address->setCountry('US');

    //Create the card model and set all the required properties
    $card = new Card();
    $card->setCardholderName($card_data->given_name.' '.$card_data->family_name);
    $card->setBillingAddress($billing_address);
    $card->setCustomerId($customer->getId());
    $card->setReferenceId($uid);
    //Create the actual card creation request and make the API call
    $body = new CreateCardRequest(uniqid(), $source_id, $card);
    $api_response = $this->openClient()->getCardsApi()->createCard($body);

    if($api_response->isSuccess()){
      $result = $api_response->getResult();
      \Drupal::messenger()->addStatus('The card ending in "'.$result->getCard()->getLast4().'" has been saved to your account');
      return $result;
    }else{
      return $api_response->getErrors();
    }
  }

  /**
   * Create a Square customer with a reference ID linking to the appropriate Drupal ID
   */
  public function createCustomer($uid){
    //Check if customer profile already exists before creating
    $customers = $this->retrieveCustomers()->getCustomers();
    foreach($customers as $customer){
      $rid = $customer->getReferenceId();
      if($uid === $rid){
        \Drupal::messenger()->addError('A customer profile already exists for this user');
        return null;
      }
    }

    //Retrieve user data from Drupal db and build customer request
    $user = User::load($uid);
    $body = new CreateCustomerRequest();
    $body->setGivenName($user->get('field_first_name')->value);
    $body->setFamilyName($user->get('field_last_name')->value);
    $body->setEmailAddress($user->getEmail());
    $body->setPhoneNumber('1'.$user->get('field_phone')->value);
    $body->setReferenceId($user->id());

    $api_response = $this->openClient()->getCustomersApi()->createCustomer($body);
    return $this->handleResponse($api_response);
  }

  /**
   * Deletes a Square customer from their system based customer ID
   */
  public function deleteCustomer($squareid){
    return $this->openClient()->getCustomersApi()->deleteCustomer($squareid);
  }

  /**
   * Creates a new payment request with a saved card's source ID, the amount in USD, and the UID/RID to charge to the right customer
   */
  public function createPayment($source_id, $amount, $uid){
    //Ensure $amount is an integer before assigning it to a new 'Money' model
    $amount = gettype($amount) === 'string' ? intval($amount) : $amount;
    $amount_money = new Money();
    $amount_money->setAmount($amount);
    $amount_money->setCurrency('USD');

    //Create a new payment request, and assign the right customer and location IDs
    $body = new CreatePaymentRequest($source_id, uniqid(), $amount_money);
    $body->setCustomerId($this->retrieveCustomerByUid($uid)->getId());
    $body->setLocationId($this->retrieveThisLocationId());

    return $this->openClient()->getPaymentsApi()->createPayment($body);
  }

  /**
   * A helper method for returning this location's ID
   */
  public function retrieveThisLocationId(){
    return $this->configFactory->get('hs_square.settings')->get('location_id');
  }

  /**
   * Retrieves a customer by UID, gets their default card, and returns that card's source ID
   */
  //TODO: Method under development
  public function getDefaultCardIdByUid($uid){
    $customer_cards = $this->retrieveCustomerByUid($uid)->getCards();
    if(!empty($customer_cards)){
      return $customer_cards[0]->getId();
    }else{
      return null;
    }
  }

}