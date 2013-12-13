<?php

class SendMessage {

  private $_iTunesProductionGateway = 'ssl://gateway.push.apple.com:2195';
  private $_iTunesProductionFeedback = 'ssl://feedback.push.apple.com:2196';
  private $_iTunesSandboxGateway = 'ssl://gateway.sandbox.push.apple.com:2195';
  private $_iTunesSandboxFeedback = 'ssl://feedback.sandbox.push.apple.com:2196';
  private $_endpoint;
  private $_certificate;
  private $_ctx;
  private $_verbose = FALSE;
  private $_messages;

  const PRODUCTION_RECIEPT_SENT_TO_SANDBOX_ERROR = 21008;
  const SOCKET_TIMEOUT = 30;

  function __construct($endpoint, $certificate, $verbose = FALSE) {
    $this->setEndPoint($endpoint);
    $this->setCertificate($certificate);
    $this->setVerbose($verbose);
  }

  public function setMessages($value) {
    $this->_messages = $value;
  }

  public function getMessages() {
    return $this->_messages;
  }

  public function setEndpoint($value) {
    $this->_endpoint = $value;
  }

  public function getEndpoint() {
    return $this->_endpoint;
  }

  public function setCertificate($value) {
    $this->_certificate = $value;
  }

  public function getCertificate() {
    return $this->_certificate;
  }

  public function setConnection($value) {
    $this->_ctx = $value;
  }

  public function getConnection() {
    return $this->_ctx;
  }

  function createPayload($messages) {
    $payload = '';
    foreach ($messages as $message) {
      $payload .= chr(0) . pack("n", 32) . pack('H*', $message['token']) . pack("n", strlen($message['message'])) . $message['message'];
    }
    return $payload;
  }

  function connect() {
    $opts = array('ssl' => array('local_cert' => $this->getCertificate()));
    $ctx = stream_context_create($opts);
    // Open the socket.
    if (!$fp = stream_socket_client($this->getEndpoint(), $error, $error_string, self::SOCKET_TIMEOUT, STREAM_CLIENT_CONNECT, $ctx)) {
      // error
    }
    $this->setConnection($fp);
  }

  function disconnect() {
    $fp = $this->getConnection();
    // Close the connection for this app.
    fclose($fp);
    $this->setConnection(NULL);
  }

  function push() {
    if ($this->getConnection()) {
      // check if there are messages.
      if (!empty($this->getMessages())) {
        $fwrite = fwrite($fp, $this->createPayload($this->getMessages()));
        if (!$fwrite) {
          watchdog(APPLE_TOOLS_WATCHDOG, 'Failed to write to stream');
          apple_tools_push_message_failed($messages);
        }
        else {
          watchdog(APPLE_TOOLS_WATCHDOG, 'Wrote to apple with status: @status', array('@status' => $fwrite));
          apple_tools_push_message_success($messages);
        }
      }
    }
  }

  function feedback() {

  }

  

functionpush_messages() {
  // Need to do this per app, then we can push multiple messages.
  if ($total_messages = db_result(db_query("SELECT COUNT(mid) FROM {apple_tools_message}"))) {
    // There are some messages to send so update queued count.
    apple_tools_set_stats(APPLE_TOOLS_MESSAGE_QUEUED, $total_messages);
  }
  else {
    // No messages to send may as well return.
    return;
  }
  $apps = apple_tools_retrieve_app();
  $message_limit = variable_get('apple_tools_send_message_limit', APPLE_TOOLS_SEND_MESSAGE_LIMIT);
  foreach ($apps as $app) {
    // Get the messages for this application.
    $messages = apple_tools_retrieve_message($app['aid'], APPLE_TOOLS_DEVICE_APP_VALID, $message_limit);
    $status = FALSE;
    // If there are messages to send.
    if (!empty($messages) && apple_tools_check_certificate(apple_tools_get_certificate($app['status'], $app['certificate']))) {
      $opts = array('ssl' => array('local_cert' => apple_tools_get_certificate($app['status'], $app['certificate'])));
      $ctx = stream_context_create($opts);
      // Open the socket.
      if (!$fp = stream_socket_client(apple_tools_get_gateway($app['status']), $error, $error_string, APPLE_TOOLS_SOCKET_TIMEOUT, STREAM_CLIENT_CONNECT, $ctx)) {
        // Delete any messages associated with this app, as the certificate is bad.
        db_query("DELETE FROM {apple_tools_message} WHERE aid = %d", APPLE_TOOLS_MESSAGE_ERROR, $app['aid']);
        watchdog(APPLE_TOOLS_WATCHDOG, 'Failed to connect to APNS server @server using @cert: @error @error_string.', array('@error' => $error, '@error_string' =>  $error_string, '@server' => apple_tools_get_gateway($app['status']), '@cert' => apple_tools_get_certificate($app['status'], $app['certificate'])));
        return NULL;
      }
      else {
        // Whilst we have messages for this app send them across in packets.
        // Limit the number of times this can run, to ensure it does not runaway.
        $loops_left = variable_get('apple_tools_send_message_loop_limit', APPLE_TOOLS_SEND_MESSAGE_LOOP_LIMIT);
        do {
          $payload = '';
          foreach ($messages as $message) {
            $payload .= chr(0) . pack("n", 32) . pack('H*', $message['token']) . pack("n", drupal_strlen($message['message'])) . $message['message'];
          }
          // Send the messages we have.
          if (!empty($payload)) {
            $fwrite = fwrite($fp, $payload);
            if (!$fwrite) {
              watchdog(APPLE_TOOLS_WATCHDOG, 'Failed to write to stream');
              apple_tools_push_message_failed($messages);
            }
            else {
              watchdog(APPLE_TOOLS_WATCHDOG, 'Wrote to apple with status: @status', array('@status' => $fwrite));
              apple_tools_push_message_success($messages);
            }
          }
        }
        // Perform this loop whilst we are under the loop limit and
        // the flag that lets us do this is true (apple_tools_flush_messages) and
        // we retrieve new messages for this app_id that have not yet been sent.
        while (
          --$loops_left > 0 &&
          variable_get('apple_tools_flush_messages', FALSE) == FALSE &&
          $messages = apple_tools_retrieve_message($app['aid'], APPLE_TOOLS_DEVICE_APP_VALID, $message_limit)
        );
      }
      // Close the connection for this app.
      fclose($fp);
    }
    else {
      if (empty($messages)) {
        watchdog(APPLE_TOOLS_WATCHDOG, 'No messages ready in the queue to be sent to apple for app [@aid]', array('@aid' => $app['aid']));
      }
      else {
        watchdog(APPLE_TOOLS_WATCHDOG, 'Sending messages for app [@aid] failed due to certificate check [@certificate] failure', array('@aid' => $app['aid'], '@certificate' => $app['certificate']));
      }
    }
  }
}
