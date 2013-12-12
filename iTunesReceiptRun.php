<?php
function __autoload($class_name)  
    {  
        include_once 'inc/' . $class_name . '.class.php';  
    }  


$rcpt = '<insert receipt here>';
$password = '<insert password / shared secret here>';

$temp = new iTunesReceiptValidator('https://buy.itunes.apple.com/verifyReceipt', $password);

print_r($temp->validateReceipt($rcpt));
$temp->setEndpoint($temp->getSandboxVerifyURL());

print_r($temp->validateReceipt($rcpt));

?>
