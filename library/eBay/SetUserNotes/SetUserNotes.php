<?php
set_time_limit(1500); //increase time-out to 25 mins as downloading and parsing the tree may take a while

//SiteID must also be set in the Request's XML
//SiteID = 0  (US) - UK = 3, Canada = 2, Australia = 15, ....
//SiteID Indicates the eBay site to associate the call with
$siteID = 77;

//Get the online version number
$responseXml = setUserNotes($devID, $appID, $certID, $compatabilityLevel, $siteID, $userToken, $serverUrl);

//Xml string is parsed and creates a DOM Document object
$responseDoc = new DomDocument();
$responseDoc->loadXML($responseXml);

$Orders = $responseDoc->getElementsByTagName('Order');

//print_r($responseXml);

function setUserNotes($devID, $appID, $certID, $compatabilityLevel, $siteID, $userToken, $serverUrl)
{
	//Build the request Xml string
	$requestXmlBody = '<?xml version="1.0" encoding="utf-8" ?>';
	$requestXmlBody .= '<SetUserNotesRequest xmlns="urn:ebay:apis:eBLBaseComponents">';
	$requestXmlBody .= "<RequesterCredentials><eBayAuthToken>$userToken</eBayAuthToken></RequesterCredentials>";
	$requestXmlBody .= '<OrderRole>Seller</OrderRole>';
	$requestXmlBody .= '<Action>AddOrUpdate</Action>';
	$requestXmlBody .= '</SetUserNotesRequest>';

<?xml version="1.0" encoding="utf-8"?> 
<SetUserNotesRequest xmlns="urn:ebay:apis:eBLBaseComponents"> 
  <RequesterCredentials> 
    <eBayAuthToken>ABC...123</eBayAuthToken> 
  </RequesterCredentials> 
  <ItemID>8084591050</ItemID> 
  <Action>AddOrUpdate</Action> 
  <NoteText>This could be the best buy.</NoteText> 
</SetUserNotesRequest>

	//Create a new eBay session with all details pulled in from included keys.php
	$session = new eBaySession($userToken, $devID, $appID, $certID, $serverUrl, $compatabilityLevel, $siteID, 'SetUserNotes');
	//send the request
	$responseXml = $session->sendHttpRequest($requestXmlBody);
	if(stristr($responseXml, 'HTTP 404') || $responseXml == '')
		die('<P>Error sending request');
	
	//return the version
	return $responseXml;
}
