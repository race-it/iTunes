<?php

class iTunesReceiptValidator {
  private $_iTunesProductionVerifyURL = 'https://buy.itunes.apple.com/verifyReceipt';
  private $_iTunesSandboxVerifyURL = 'https://sandbox.itunes.apple.com/verifyReceipt';
  private $_retrySandbox = TRUE;
  private $_retryProduction = FALSE;
  private $_endpoint;
  private $_verbose = FALSE;

  const SANDBOX_RECEIPT_SENT_TO_PRODUCTION_ERROR = 21007;
  const PRODUCTION_RECIEPT_SENT_TO_SANDBOX_ERROR = 21008;
  const CURL_ERROR = 60001;

  function __construct($endpoint, $verbose = FALSE) {
    $this->setEndPoint($endpoint);
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
    return $this->$_password;
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
      'password' => getPassword(),
    );

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
?>
