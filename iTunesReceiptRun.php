<?php
function __autoload($class_name)  
    {  
        include_once 'inc/' . $class_name . '.class.php';  
    }  


$rcpt = '<insert receipt here>';
$ss = '<insert shared secret here>';

$temp = new iTunesReceiptValidator('https://buy.itunes.apple.com/verifyReceipt');
print_r($temp->validateReceipt($rcpt, $ss));
$temp->setEndpoint($temp->getSandboxVerifyURL());

print_r($temp->validateReceipt($rcpt, $ss));

?>
