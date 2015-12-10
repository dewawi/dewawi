<?php set_time_limit(1500) //increase time-out to 25 mins as downloading and parsing the tree may take a while ?>

<?php require_once('../get-common/keys.php') ?>
<?php require_once('../get-common/eBaySession.php') ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<HTML>
<HEAD>
<META http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>GetCategories</TITLE>
</HEAD>
<BODY>

<?php
	//SiteID must also be set in the Request's XML
	//SiteID = 0  (US) - UK = 3, Canada = 2, Australia = 15, ....
	//SiteID Indicates the eBay site to associate the call with
	$siteID = 77;
	
	$catTreeDoc = NULL;  //declared here for wider variable scope (avoid having to parse more than once)
	
	if(!isset($_REQUEST['CatID']))
	{
		//see if CatTree.xml file exists
		if(!file_exists('CatTree.xml'))
		{   //file does not exists -> request and save
			echo '<P><B>Downloading new category tree...</B>';
			$catTreeDoc = getEntireCategoryTree($devID, $appID, $certID, $compatabilityLevel,
												$siteID, $userToken, $serverUrl);
			echo '<P><B>Latest Category Tree Downloaded.</B>';
		}
		else
		{
			//Get the online version number
			$onlineVersion = getOnlineTreeVersion($devID, $appID, $certID, $compatabilityLevel,
												$siteID, $userToken, $serverUrl);
			//get local version number
			$catTreeDoc = new DOMDocument();
			$catTreeDoc->load('CatTree.xml');
			$localVersionNode = $catTreeDoc->getElementsByTagName('CategoryVersion');
			$localVersion = $localVersionNode->item(0)->nodeValue;
			
			//if version numbers are different
			if( $onlineVersion != $localVersion)
			{	
				echo '<P><B>Downloading new category tree...</B>';
				//download and save new category tree
				$catTreeDoc = getEntireCategoryTree($devID, $appID, $certID, $compatabilityLevel,
												$siteID, $userToken, $serverUrl);
				echo '<P><B>Latest Category Tree Downloaded.</B>';
			}
		}
		
		//show the top-level domains
		displayTopLevelCategories($catTreeDoc);
	}
	else //category has been selected
	{
		$catID = $_REQUEST['CatID'];
		$catTreeDoc = new DOMDocument();
		$catTreeDoc->load('CatTree.xml');
		
		if(isLeafCategory($catTreeDoc, $catID))
		{
			echo "<P><B>YOU MAY SUBMIT TO THIS CATEGORY : $catID</B>";
		}
		else
		{
			displaySubCategories($catTreeDoc, $catID);
		}
	}

/**	isLeafCategory
	Returns true if te category given is a leaf category in the category tree specified
	Input:	$tree - a DOM Document represnting the category tree
			$catID - the ID of the category to see if it is a Leaf
*/
function isLeafCategory($tree, $catID)
{
	//get all the categories and go through each one
	$categories = $tree->getElementsByTagName('Category');
	foreach($categories as $cat)
	{	
		//get the categories ID
		$catIDNode = $cat->getElementsByTagName('CategoryID');
		//if the category id is the one we want
		if($catIDNode->item(0)->nodeValue == $catID)
		{
			$leafNode = $cat->getElementsByTagName('LeafCategory');
			//if LeafCategory = return true, otherwise return false
			if($leafNode->item(0)->nodeValue == "1" || $leafNode->item(0)->nodeValue == "true")
				return true;
			else
				return false;
		}
	}
}


/**	displaySubCategories
	Displays the Subcategories of the given category from the given category tree
*/
function displaySubCategories($tree, $parentCategoryID)
{
	//get all the categories and go through each one
	$categories = $tree->getElementsByTagName('Category');
	echo '<P><B>Subcategories Categories</B><BR>Please Select:<BR>';
	foreach($categories as $cat)
	{	
		//get ParentID
		$parentIDNode = $cat->getElementsByTagName('CategoryParentID');
		//If parentID is the one we want then dusplay this category
		if($parentIDNode->item(0)->nodeValue == $parentCategoryID)
		{
			//get ID and name and display as link
			$catIDNode = $cat->getElementsByTagName('CategoryID');
			$catNameNode = $cat->getElementsByTagName('CategoryName');
			echo '<BR><A href="GetCategories.php?CatID=', $catIDNode->item(0)->nodeValue, '">', $catNameNode->item(0)->nodeValue,'</A>';
		}
	}
}

/**	displayTopLevelCategories
	Displays the Top-Level categories from the given category tree
*/
function displayTopLevelCategories($tree)
{	
	//get all the categories and go through each one
	$categories = $tree->getElementsByTagName('Category');
	echo '<P><B>Main Categories</B><BR>Please Select:<BR>';
	foreach($categories as $cat)
	{	
		//get the ID and ParentID
		$catIDNode = $cat->getElementsByTagName('CategoryID');
		$parentIDNode = $cat->getElementsByTagName('CategoryParentID');
		//if ID equals ParentID then it is a Top-Level category
		if($catIDNode->item(0)->nodeValue == $parentIDNode->item(0)->nodeValue)
		{	
			//get name and display as link
			$catNameNode = $cat->getElementsByTagName('CategoryName');
			echo '<BR><A href="GetCategories.php?CatID=', $catIDNode->item(0)->nodeValue, '">', $catNameNode->item(0)->nodeValue,'</A>';
		}
	}
}
	

/**	getEntireCategoryTree
	Retrieves the entire category tree from eBay API and saves it locally in an XML file
*/
function getEntireCategoryTree($devID, $appID, $certID, $compatabilityLevel, $siteID, $userToken, $serverUrl)
{
	//Build the request Xml string
	$requestXmlBody = '<?xml version="1.0" encoding="utf-8" ?>';
	$requestXmlBody .= '<GetCategoriesRequest xmlns="urn:ebay:apis:eBLBaseComponents">';
	$requestXmlBody .= "<RequesterCredentials><eBayAuthToken>$userToken</eBayAuthToken></RequesterCredentials>";
	$requestXmlBody .= "<DetailLevel>ReturnAll</DetailLevel>"; //get the entire tree
	$requestXmlBody .= "<Item><Site>$siteID</Site></Item>";
	$requestXmlBody .= "<ViewAllNodes>1</ViewAllNodes>"; //Gets all nodes not just leaf nodes
	$requestXmlBody .= '</GetCategoriesRequest>';
	
	//Create a new eBay session with all details pulled in from included keys.php
	$session = new eBaySession($userToken, $devID, $appID, $certID, $serverUrl, $compatabilityLevel, $siteID, 'GetCategories');
	$responseXml = $session->sendHttpRequest($requestXmlBody);
	if(stristr($responseXml, 'HTTP 404') || $responseXml == '')
		die('<P>Error sending request');
	
	//Xml string is parsed and creates a DOM Document object
	$responseDoc = new DomDocument();
	$responseDoc->loadXML($responseXml);
	
	//save the tree to local file
	$responseDoc->save('CatTree.xml');
	
	//return the DOM Document
	return $responseDoc;
}


/**	getOnlineTreeVersion
	Returns the Version number of the Category tree that is currently available online
*/
function getOnlineTreeVersion($devID, $appID, $certID, $compatabilityLevel, $siteID, $userToken, $serverUrl)
{
	//Build the request Xml string
	$requestXmlBody = '<?xml version="1.0" encoding="utf-8" ?>';
	$requestXmlBody .= '<GetCategoriesRequest xmlns="urn:ebay:apis:eBLBaseComponents">';
	$requestXmlBody .= "<RequesterCredentials><eBayAuthToken>$userToken</eBayAuthToken></RequesterCredentials>";
	$requestXmlBody .= "<Item><Site>$siteID</Site></Item>";
	$requestXmlBody .= "<ViewAllNodes>0</ViewAllNodes>";
	$requestXmlBody .= '</GetCategoriesRequest>';
	
	//Create a new eBay session with all details pulled in from included keys.php
	$session = new eBaySession($userToken, $devID, $appID, $certID, $serverUrl, $compatabilityLevel, $siteID, 'GetCategories');
	//send the request
	$responseXml = $session->sendHttpRequest($requestXmlBody);
	if(stristr($responseXml, 'HTTP 404') || $responseXml == '')
		die('<P>Error sending request');
	
	//Xml string is parsed and creates a DOM Document object
	$responseDoc = new DomDocument();
	$responseDoc->loadXML($responseXml);
	
	//get the version name
	$version = $responseDoc->getElementsByTagName('CategoryVersion');
	
	//return the version
	return $version->item(0)->nodeValue;
}
?>

</BODY>
</HTML>
