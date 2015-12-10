<?php
// be sure include path contains current directory
// to make sure samples work
ini_set('include_path', ini_get('include_path') . ':.');

// Load general helper classes for eBay SOAP API
require_once 'eBaySOAP.php';

// Load developer-specific configuration data from ini file
$config = parse_ini_file('ebay.ini', true);
$site = $config['settings']['site'];
$compatibilityLevel = $config['settings']['compatibilityLevel'];

$dev = $config[$site]['devId'];
$app = $config[$site]['appId'];
$cert = $config[$site]['cert'];
$token = $config[$site]['authToken'];
$location = $config[$site]['gatewaySOAP'];

// Create and configure session
$session = new eBaySession($dev, $app, $cert);
$session->token = $token;
$session->site = 0; // 0 = US;
$session->location = $location;

// Make a GetUser API call and print results
try {
    $client = new eBaySOAP($session);

    // Default value is to use the user in the Auth & Auth token
    $params = array('Version' => $compatibilityLevel);
    $results = $client->GetUser($params);
    print "<pre> \n";
    print_r($results->User);
    print "</pre> \n";

    print "<p>---\n";

    // Or you can specify a UserID
    $UserID = 'ebay_user_name';
    $params = array('Version' => $compatibilityLevel, 'UserID' => $UserID);
    $results = $client->GetUser($params);
    print "<pre> \n";
    print_r($results->User);
    print "</pre> \n";

} catch (SOAPFault $f) {
    print $f; // error handling
}

// Uncomment below to view SOAP envelopes
print "<pre> \n";
print "Request: \n".$client->__getLastRequest() ."\n";
print "----- \n";
print "Response: \n".$client->__getLastResponse()."\n";
print "</pre> \n";
?>