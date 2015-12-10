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

// Make an AddItem API call and print Listing Fee and ItemID
try {
	$client = new eBaySOAP($session);

	$PrimaryCategory = array('CategoryID' => 357);

	$Item = array('ListingType' => 'Chinese',
				  'Currency' => 'USD',
				  'Country' => 'US',
				  'PaymentMethods' => 'PaymentSeeDescription',
				  'RegionID' => 0,
				  'ListingDuration' => 'Days_3',
				  'Title' => 'The new item',
				  'Description' => "It's a great new item",
				  'Location' => "San Jose, CA",
				  'Quantity' => 1,
				  'StartPrice' => 24.99,
				  'PrimaryCategory' => $PrimaryCategory,
				 );

	$params = array('Version' => $compatibilityLevel, 'Item' => $Item);
	$results = $client->AddItem($params);

	// The $results->Fees['ListingFee'] syntax is a result of SOAP classmapping
	print "Listing fee is: " . $results->Fees['ListingFee'] . " <br> \n";

	print "Listed Item ID: " . $results->ItemID . " <br> \n";
     
    print "Item was listed for the user associated with the auth token <br>\n";

} catch (SOAPFault $f) {
	print $f; // error handling
}

// Uncomment below to view SOAP envelopes
// print "Request: \n".$client->__getLastRequest() ."\n";
// print "Response: \n".$client->__getLastResponse()."\n";
?>