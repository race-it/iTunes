<?php 

require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload

use iTunes\ReceiptValidator;

$rcpt = '<insert receipt here>';
$password = '<insert password / shared secret here>';

$temp = new ReceiptValidator('https://buy.itunes.apple.com/verifyReceipt', $password);

print_r($temp->validateReceipt($rcpt));
$temp->setEndpoint($temp->getSandboxVerifyURL());

print_r($temp->validateReceipt($rcpt));
