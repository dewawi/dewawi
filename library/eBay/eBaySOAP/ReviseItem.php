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

// Make AddItem, ReviseItem, and GetItem API calls to demonstate how to modify
// and item listing
try {
	$client = new eBaySOAP($session);

	$PrimaryCategory = array('CategoryID' => 357);

	$Item = array('ListingType' => 'Chinese',
				  'Currency' => 'USD',
				  'Country' => 'US',
				  'PaymentMethods' => 'PaymentSeeDescription',
				  'RegionID' => 0,
				  'ListingDuration' => 'Days_3',
				  'Title' => 'The new item title',
				  'SubTitle' => 'The new item subtitle',
				  'Description' => "It's a great new item",
				  'Location' => "San Jose, CA",
				  'Quantity' => 1,
				  'StartPrice' => 24.99,
				  'BuyItNowPrice' => 54.99,
				  'PrimaryCategory' => $PrimaryCategory,
				 );

	$params = array('Version' => $compatibilityLevel, 'Item' => $Item);
	$results = $client->AddItem($params);

	$ItemID = (string) $results->ItemID;
	print  "Listed Item ID: $ItemID <br>\n";

	// Get it to confirm
	$params = array('Version' => $compatibilityLevel, 'ItemID' =>  $ItemID);
	$results = $client->GetItem($params);

	print "Got Item ID: $ItemID <br>\n";
	print "It has a title of: " . $results->Item->Title . " <br>\n";
	print "It has a BIN Price of: " . $results->Item->BuyItNowPrice->_ . ' ' . $results->Item->BuyItNowPrice->currencyID . " <br> \n";

	// Revise it and change the Title and raise the BuyItNowPrice
	$Item = array('ItemID' => $ItemID,
				  'Title' => 'The revised item title',
				  'BuyItNowPrice' => 99.99,
				 );

                           
	$params = array('Version' => $compatibilityLevel, 
	                'Item' => $Item
	               );

	$results = $client->ReviseItem($params);

	print "<hr>Revised Item ID: $ItemID <br>\n";

	// Get it to confirm
	$params = array('Version' => $compatibilityLevel, 'ItemID' =>  $ItemID);
	$results = $client->GetItem($params);

	print "Got Item ID: $ItemID <br>\n";
	print "It has a title of: " . $results->Item->Title . " <br>\n";
      print "It has a BIN Price of: " . $results->Item->BuyItNowPrice->_ . ' ' . $results->Item->BuyItNowPrice->currencyID . " <br> \n";

} catch (SOAPFault $f) {
	print $f; // error handling
}


// Uncomment below to view SOAP envelopes
// print "Request: \n".$client->__getLastRequest() ."\n";
// print "Response: \n".$client->__getLastResponse()."\n";
?>