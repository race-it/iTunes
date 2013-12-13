<?php

namespace iTunes;

class ReceiptValidator {
  private $_iTunesProductionVerifyURL = 'https://buy.itunes.apple.com/verifyReceipt';
  private $_iTunesSandboxVerifyURL = 'https://sandbox.itunes.apple.com/verifyReceipt';
  private $_retrySandbox = TRUE;
  private $_retryProduction = FALSE;
  private $_endpoint;
  private $_password;
  private $_verbose = FALSE;

  const COULD_NOT_READ_JSON_OBJECT = 21000; // The App Store could not read the JSON object you provided.
  const RECEIPT_DATA_PROPERTY_MALFORMED = 21002; // The data in the receipt-data property was malformed.
  const RECEIPT_COULD_NOT_BE_AUTHENTICATED = 21003; // The receipt could not be authenticated.
  const SHARED_SECRET_MISMATCH = 21004; // The shared secret you provided does not match the shared secret on file for your account.
  const RECEIPT_SERVER_UNAVAILABLE = 21005; // The receipt server is not currently available.
  const VALID_RECEIPT_BUT_SUBSCRIPTION_EXPIRED = 21006; // This receipt is valid but the subscription has expired. When this status code is returned to your server, the receipt data is also decoded and returned as part of the response.
  const SANDBOX_RECEIPT_SENT_TO_PRODUCTION_ERROR = 21007; // This receipt is a sandbox receipt, but it was sent to the production service for verification.
  const PRODUCTION_RECIEPT_SENT_TO_SANDBOX_ERROR = 21008; // This receipt is a production receipt, but it was sent to the sandbox service for verification.
  const RECEIPT_VALID = 0; // This receipt valid.
  const CURL_ERROR = 60001;

  function __construct($endpoint, $password = NULL, $verbose = FALSE) {
    $this->setEndPoint($endpoint);
    $this->setPassword($password);
    $this->setVerbose($verbose);
  }

  public function setProductionVerifyURL($value) {
    $this->_iTunesProductionVerifyURL = $value;
  }

  public function getProductionVerifyURL() {
    return $this->_iTunesProductionVerifyURL;
  }

  public function setSandboxVerifyURL($value) {
    $this->_iTunesSandboxVerifyURL = $value;
  }

  public function getSandboxVerifyURL() {
    return $this->_iTunesSandboxVerifyURL;
  }

  public function setEndpoint($value) {
    $this->_endpoint = $value;
  }

  public function getEndpoint() {
    return $this->_endpoint;
  }

  public function setVerbose($value) {
    $this->_verbose = $value;
  }

  public function getVerbose() {
    return $this->_verbose;
  }

  public function setRetrySandbox($value) {
    $this->_retrySandbox = $value;
  }

  public function getRetrySandbox() {
    return $this->$_retrySandbox;
  }

  public function setPassword($value) {
    $this->_password = $value;
  }

  public function getPassword() {
    return $this->_password;
  }
  
  public function setRetryProduction($value) {
    $this->_retryProduction = $value;
  }

  public function getRetryProduction() {
    return $this->_retryProduction;
  }

  public function validateReceipt($receipt) {
    $ch = curl_init($this->getEndpoint());

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    $receiptData = (object) array(
      'receipt-data' => $receipt,
    );

    if ($this->getPassword() == '') {
      $receiptData->password = $this->getPassword();
    }

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($receiptData));

    if ($this->getVerbose()) {
      curl_setopt($ch, CURLOPT_VERBOSE, true);
    }
 
    // Execute the cURL request and fetch response data.
    $response = curl_exec($ch);

    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    // Ensure the http status code was 200.
    if ($http_status != 200) {
      return (object) array(
        'status' => $http_status
      );
    }

    // Parse the response data.
    $data = json_decode($response);

    // Ensure response data was a valid JSON string.
    if (!is_object($data)) {
      return (object) array(
        'status' => CURL_ERROR,
      );
    } 

    if ($data->status === self::SANDBOX_RECEIPT_SENT_TO_PRODUCTION_ERROR && $this->getRetrySandbox()) {
      $this->setEndpoint($this->getSandboxVerifyURL());
      return $this->validateReceipt($receipt);
    }
    
    if ($data->status === self::PRODUCTION_RECIEPT_SENT_TO_SANDBOX_ERROR && $this->getRetryProduction()) {
      $this->setEndpoint($this->getProductionVerifyURL());
      return $this->validateReceipt($receipt);
    }    

    return $data;

  }
}
