<?php

namespace Drupal\hs_square;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\user\Entity\User;
use Square\Environment;
use Square\Models\Address;
use Square\Models\Card;
use Square\Models\Customer;
use Square\Models\CreateCardRequest;
use Square\Models\CreateCustomerRequest;
use Square\Models\CreatePaymentRequest;
use Square\Models\CustomerFilter;
use Square\Models\CustomerQuery;
use Square\Models\CustomerTextFilter;
use Square\Models\Money;
use Square\Models\SearchCustomersRequest;
use Square\Models\UpdateCustomerRequest;
use Square\SquareClient;

class SquareManager implements SquareManagerInterface {

  protected $configFactory;

  public function __construct(ConfigFactoryInterface $configFactory){
    $this->configFactory = $configFactory;
  }

  /**
   * Returns a Square client with the access token and environment type saved in configuration
   */
  public function openClient(){
    //Retrieve the configured environment settings
    $square_settings = $this->configFactory->get('hs_square.settings');
    $access_token = $square_settings->get('access_token');
    $environment = $square_settings->get('environment');
    //Set the environment type with the appropriate constant
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
    //Return the client
    return new SquareClient([
      'accessToken' => $access_token,
      'environment' => $environment,
    ]);
  }


  /**
   * A helper method for returning this location's ID
   */
  public function retrieveThisLocationId(){
    return $this->configFactory->get('hs_square.settings')->get('location_id');
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
   * MAKES 1 API CALL
   */
  public function retrieveCustomerByUid($uid){
    //Create an exact reference ID filter and attach it to a customer query
    $reference_id = new CustomerTextFilter();
    $reference_id->setExact($uid);
    $filter = new CustomerFilter();
    $filter->setReferenceId($reference_id);
    $query = new CustomerQuery();
    $query->setFilter($filter);
    //Apply the query to a customer request
    $body = new SearchCustomersRequest();
    $body->setQuery($query);
    //Make the API call
    $api_response = $this->openClient()->getCustomersApi()->searchCustomers($body);
    if($api_response->isSuccess()){
      if(!empty($api_response->getResult()->getCustomers())){
        return $api_response->getResult()->getCustomers()[0];
      }else{
        return null;
      }
    }else{
      return $api_response;
    }
  }

  /**
   * A helper method that acts as a wrapper for getting all Square customers
   */
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
   * MAKES 1-2 API CALLS
   */
  public function addCardToCustomer(Customer $customer, $card_data, $source_id){
    //Retrieve the appropriate customer with the given UID
    //If the customer has no other cards attached to their account, make this new card the default
    if($customer->getCards() === null){
      $this->setDefaultCard($customer, 0);
    }
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
   * MAKES 2 API CALLS
   */
  public function createCustomer($uid){
    //Check if customer profile already exists before creating
    if(!empty($this->retrieveCustomerByUid($uid))){
      \Drupal::messenger()->addError('A Square customer profile already exists for this user');
      return null;
    }

    //Retrieve user data from Drupal db and build customer request
    $user = User::load($uid);
    $body = new CreateCustomerRequest();
    $body->setGivenName($user->get('field_first_name')->value);
    $body->setFamilyName($user->get('field_last_name')->value);
    $body->setEmailAddress($user->getEmail());
    $body->setPhoneNumber('1'.$user->get('field_phone')->value);
    $body->setReferenceId($uid);

    $api_response = $this->openClient()->getCustomersApi()->createCustomer($body);
    return $this->handleResponse($api_response);
  }

  /**
   * Deletes a Square customer from their system based customer ID
   * MAKES 1 API CALL
   */
  public function deleteCustomer($squareid){
    return $this->openClient()->getCustomersApi()->deleteCustomer($squareid);
  }

  /**
   * Creates a new payment request with a saved card's source ID, the amount in USD, and the UID/RID to charge to the right customer
   * MAKES 1 API CALL
   */
  public function createPayment($source_id, $amount, Customer $customer){
    //Ensure $amount is an integer before assigning it to a new 'Money' model
    $amount = gettype($amount) === 'string' ? intval($amount) : $amount;
    $amount_money = new Money();
    $amount_money->setAmount($amount);
    $amount_money->setCurrency('USD');

    //Create a new payment request, and assign the right customer and location IDs
    $body = new CreatePaymentRequest($source_id, uniqid(), $amount_money);
    $body->setCustomerId($customer->getId());
    $body->setLocationId($this->retrieveThisLocationId());

    return $this->openClient()->getPaymentsApi()->createPayment($body);
  }

  /**
   * Returns a given customer's default card id
   */
  public function getDefaultCardId(Customer $customer){
    $index = $customer->getNote();
    $reg = '/".*?"/';
    if(preg_match($reg, $index, $m)){
      return trim($m[0], '"');
    }
    return null;
  }

  /**
   * Updates a customer's note field to demonstrate their default card
   * The note field is used as Square provides no way to directly mark cards as defaults
   * MAKES 1 API CALL
   */
  public function setDefaultCard(Customer $customer, $index){
    $body = new UpdateCustomerRequest();
    $card_id = $customer->getCards()[$index]->getId();
    $body->setNote('default_card_id:"'.$card_id.'"');
    return $this->openClient()->getCustomersApi()->updateCustomer($customer->getId(), $body);
  }

}