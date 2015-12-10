<?php set_time_limit(900) //increase time-out to 15 mins as downloading and parsing the map may take a while ?>

<?php require_once('../get-common/keys.php') ?>
<?php require_once('../get-common/eBaySession.php') ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<HTML>
<HEAD>
<META http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>GetCategory2CS</TITLE>
</HEAD>
<BODY>

<?php
	//SiteID must also be set in the Request's XML
	//SiteID = 0  (US) - UK = 3, Canada = 2, Australia = 15, ....
	//SiteID Indicates the eBay site to associate the call with
	$siteID = 0;
	//Regulates versioning of the XML interface for the API
	$compatabilityLevel = 433;
	//whether to use Sandbox or Production server
	$useSandboxServer = true;
	
	
	$catMapDoc = NULL;  //declared here for wider variable scope to avoid parsing twice
		
		
		
		
	/*********************************
	 * KEEP LOCAL MAPPING UP TO DATE *
	 *********************************/
	
	//see if CatMap.xml file exists
	if(!file_exists('CatMap.xml'))
	{   //file does not exists -> request and save
		echo '<P><B>Downloading new category tree...</B>';
		$catMapDoc = getEntireCategoryMap($devID, $appID, $certID, $compatabilityLevel,
											$siteID, $userToken, $serverUrl);
		echo '<P><B>Latest Category Map Downloaded.</B>';
	}
	else
	{
		//get online version number
		$onlineVersion = getOnlineMapVersion($devID, $appID, $certID, $compatabilityLevel,
											$siteID, $userToken, $serverUrl);
		//get local version number
		$catMapDoc = new DOMDocument();
		$catMapDoc->load('CatMap.xml');
		
		$attSysVersionNode = $catMapDoc->getElementsByTagName('AttributeSystemVersion');
		$localVersion = $attSysVersionNode->item(0)->nodeValue;
		
		//if version numbers differ
		if( $onlineVersion != $localVersion)
		{
			//download and save the category map
			echo '<P><B>Downloading new category tree...</B>';
			$catMapDoc = getEntireCategoryMap($devID, $appID, $certID, $compatabilityLevel,
											$siteID, $userToken, $serverUrl);
			echo '<P><B>Latest Category Map Downloaded.</B>';
		}
	}
	
	
	
	
	/***************************
	 * OUTPUT CATEGORY MAPPING *
	 ***************************/
	
	$mappedNode = $catMapDoc->getElementsByTagName('MappedCategoryArray');
	$unmappedNode = $catMapDoc->getElementsByTagName('UnmappedCategoryArray');
	$siteWideNode = $catMapDoc->getElementsByTagName('SiteWideCharacteristicSets');


	//DISPLAY MAPPED CATEGORIES - if any
	if($mappedNode->length > 0 && $mappedNode->item(0)->childNodes->length > 0)
	{	
		//Get all the categories
		$categoriesNode = $mappedNode->item(0)->getElementsByTagName('Category');
		if($categoriesNode->length > 0)
		{	//if there are some categories then display them
			echo '<P><B>MAPPED</B>';
			displayCategories($categoriesNode);
		}
	}


	//DISPLAY UNMAPPED CATEGORIES - if any
	if($unmappedNode->length > 0 && $unmappedNode->item(0)->childNodes->length > 0)
	{
		//Get all the categories
		$categoriesNode = $unmappedNode->item(0)->getElementsByTagName('Category');
		if(count($categoriesNode) > 0)
		{	//if there are some categories then display them
			echo '<P><B>UNMAPPED</B>';
			displayCategories($categoriesNode);
		}
		
	}
	

	//DISPLAY SITE-WIDE CSs
	if($siteWideNode->length > 0 && $siteWideNode->item(0)->childNodes->length > 0)
	{
		$csNodes = $siteWideNode->item(0)->getElementsByTagName('CharacteristicsSet');
		if($csNodes->length > 0)
		{ //if there are some Site-Wide CSs
			echo '<P><B>SITE-WIDE CHARACTERISTICS SETS</B>';
			//go throught each one
			foreach($csNodes as $cs)
			{
				//get its details
				$csIDNode = $cs->getElementsByTagName('AttributeSetID');
				$versionNode = $cs->getElementsByTagName('AttributeSetVersion');
				$nameNode = $cs->getElementsByTagName('Name');
				
				//output details
				echo '<BR>Attribute Set ID: ', $csIDNode->item(0)->nodeValue, ', Name: ', $nameNode->item(0)->nodeValue;
				echo ', Version: ', $versionNode->item(0)->nodeValue;
				
			}
			
		}
		
	}


/**	displayCategories
	Takes an array of Cateogires with CS details and displays them
*/
function displayCategories($categoriesNode)
{
	//loop through each category
	foreach($categoriesNode as $cat)
	{
		//get category id
		$catID = $cat->getElementsByTagName('CategoryID');
		//get all CS details
		$csIDNode = $cat->getElementsByTagName('AttributeSetID');
		$csVersionNode = $cat->getElementsByTagName('AttributeSetVersion');
		$csNameNode = $cat->getElementsByTagName('Name');
		$catalogEnabledNode = $cat->getElementsByTagName('CatalogEnabled');
		$prodSearchPageNode = $cat->getElementsByTagName('ProductSearchPageAvailable');
		
		//output CatID, CSId, CS Name and CS Verison
		echo '<BR>CategoryID: ', $catID->item(0)->nodeValue ,' <BR>&nbsp;&nbsp;&nbsp;&nbsp;';
		if($csIDNode->length > 0)
			echo 'Attribute Set ID: ', $csIDNode->item(0)->nodeValue, ', Name: ', $csNameNode->item(0)->nodeValue, ', Version: ', $csVersionNode->item(0)->nodeValue;
		
		//if CatalogEnabled node is there then it is true
		if($catalogEnabledNode->length > 0)
			echo '<BR>&nbsp;&nbsp;&nbsp;&nbsp;Catalog Enabled';
		
		//if ProductSearchPageAvailable is there then it is true
		if($prodSearchPageNode->length > 0)
			echo '<BR>&nbsp;&nbsp;&nbsp;&nbsp;Single-Attribute Search';
		
	}
}

	

/**	getEntireCategoryMap
	Retrieves the entire category Map from eBay API and saves it locally in an XML file
*/
function getEntireCategoryMap($devID, $appID, $certID, $compatabilityLevel, $siteID, $userToken, $serverUrl)
{
	//Build the request Xml string
	$requestXmlBody = '<?xml version="1.0" encoding="utf-8" ?>';
	$requestXmlBody .= '<GetCategory2CSRequest xmlns="urn:ebay:apis:eBLBaseComponents">';
	$requestXmlBody .= "<RequesterCredentials><eBayAuthToken>$userToken</eBayAuthToken></RequesterCredentials>";
	$requestXmlBody .= "<DetailLevel>ReturnAll</DetailLevel>"; //get the entire Map
	$requestXmlBody .= "<Item><Site>$siteID</Site></Item>";
	$requestXmlBody .= '</GetCategory2CSRequest>';
	
	
    //Create a new eBay session with all details pulled in from included keys.php
	$session = new eBaySession($userToken, $devID, $appID, $certID, $serverUrl, $compatabilityLevel, $siteID, 'GetCategory2CS');
    
	//send the request
	$responseXml = $session->sendHttpRequest($requestXmlBody);
	if(stristr($responseXml, 'HTTP 404') || $responseXml == '')
		die('<P>Error sending request');
	
	//Xml string is parsed and creates a DOM Document object
	$responseDoc = new DomDocument();
	$responseDoc->loadXML($responseXml);
	
	//save the tree to local file
	$responseDoc->save('CatMap.xml');
	
	//return the DOM Document
	return $responseDoc;
}


/**	getOnlineMapVersion
	Returns the Version number of the Category Map that is currently available online
*/
function getOnlineMapVersion($devID, $appID, $certID, $compatabilityLevel, $siteID, $userToken, $serverUrl)
{
	//Build the request Xml string
	$requestXmlBody = '<?xml version="1.0" encoding="utf-8" ?>';
	$requestXmlBody .= '<GetCategory2CSRequest xmlns="urn:ebay:apis:eBLBaseComponents">';
	$requestXmlBody .= "<RequesterCredentials><eBayAuthToken>$userToken</eBayAuthToken></RequesterCredentials>";
	$requestXmlBody .= "<Item><Site>$siteID</Site></Item>";
	$requestXmlBody .= '</GetCategory2CSRequest>';
	    
    //Create a new eBay session with all details pulled in from included keys.php
	$session = new eBaySession($userToken, $devID, $appID, $certID, $serverUrl, $compatabilityLevel, $siteID, 'GetCategory2CS');
    
	//send the request
	$responseXml = $session->sendHttpRequest($requestXmlBody);
	if(stristr($responseXml, 'HTTP 404') || $responseXml == '')
		die('<P>Error sending request');
	
	//Xml string is parsed and creates a DOM Document object
	$responseDoc = new DOMDocument();
	$responseDoc->loadXML($responseXml);
	
	//get the version name
	$version = $responseDoc->getElementsByTagName('AttributeSystemVersion');
	
	//return the version
	return $version->item(0)->nodeValue;
}
?>

</BODY>
</HTML>
