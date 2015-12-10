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
$session->site = 1; // 100 = eBay Motors
$session->location = $location;

// Make a series of GetSearchResults API calls and print results
try {
    $client = new eBaySOAP($session);

    // Find 10 ipods and print their Titles
    $params = array('Version' => $compatibilityLevel, 
                    'Query' => 'ipod',
                    'Pagination' => array('EntriesPerPage' => 10),
                   );

    $results = $client->GetSearchResults($params);
    
    print "<pre>";
    //print_r($results);
    print "</pre>";

    foreach ($results->SearchResultItemArray as $item) {
        echo $item, "  <br>\n";
    }

    print "<p>---</p>\n";


    // Find 10 passenger vehicles (CategoryID 6001) within 10 miles of ZIP Code 95125
    // ordered by ascending distance
    $params = array('Version' => $compatibilityLevel, 
                    'Query' => '*',
                    'CategoryID' => 6001,
                    'ProximitySearch' => array('MaxDistance' => 10, 'PostalCode' => 95125),
                    'Pagination' => array('EntriesPerPage' => 10),
                    'Order' => 'SortByDistanceAsc',
                   );

    $results = $client->GetSearchResults($params);

    foreach ($results->SearchResultItemArray->SearchResultItem as $item) {
        print $item->Item->Title . " <br> \n";
    }

    print "<p>---</p>\n";

    // Find the count of all passenger vehicles (CategoryID 6001)
    $params = array('Version' => $compatibilityLevel, 
                    'Query' => '*',
                    'CategoryID' => 6001,
                    'TotalOnly' => true,
                   );
    $results = $client->GetSearchResults($params);
    $total = number_format($results->PaginationResult->TotalNumberOfEntries);
    print "There are $total passenger vehicles for sale on eBay Motors <br>\n";

} catch (SOAPFault $f) {
    print $f; // error handling
}

// Uncomment below to view SOAP envelopes
// print "Request: \n".$client->__getLastRequest() ."\n";
// print "Response: \n".$client->__getLastResponse()."\n";
?>