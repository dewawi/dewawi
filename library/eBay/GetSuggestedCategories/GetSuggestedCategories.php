<?php require_once('../get-common/keys.php') ?>
<?php require_once('../get-common/eBaySession.php') ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<HTML>
<HEAD>
<META http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<TITLE>GetSuggestedCategories</TITLE>
</HEAD>
<BODY>
<FORM action="GetSuggestedCategories.php" method="post">
<TABLE cellpadding="2" border="0">
	<TR>
		<TD>Query:</TD>
		<TD><INPUT type="text" name="Query"></TD>
	</TR>
	<TR>
		<TD colspan="2" align="right"><INPUT type="submit" name="submit"></TD>
	</TR>
</TABLE>
</FORM>

<?php
	if(isset($_POST['Query']))
	{
		//Get the query inputted
		$query = $_POST['Query'];
	
	
		//SiteID must also be set in the Request's XML
		//SiteID = 0  (US) - UK = 3, Canada = 2, Australia = 15, ....
		//SiteID Indicates the eBay site to associate the call with
		$siteID = 0;
		//the call being made:
		$verb = 'GetSuggestedCategories';
		
		///Build the request Xml string
		$requestXmlBody = '<?xml version="1.0" encoding="utf-8" ?>';
		$requestXmlBody .= '<GetSuggestedCategoriesRequest xmlns="urn:ebay:apis:eBLBaseComponents">';
		$requestXmlBody .= "<RequesterCredentials><eBayAuthToken>$userToken</eBayAuthToken></RequesterCredentials>";
		$requestXmlBody .= "<Query>$query</Query>";
		$requestXmlBody .= '</GetSuggestedCategoriesRequest>';
        
        //Create a new eBay session with all details pulled in from included keys.php
        $session = new eBaySession($userToken, $devID, $appID, $certID, $serverUrl, $compatabilityLevel, $siteID, $verb);
		
		//send the request and get response
		$responseXml = $session->sendHttpRequest($requestXmlBody);
		if(stristr($responseXml, 'HTTP 404') || $responseXml == '')
			die('<P>Error sending request');
		
		//Xml string is parsed and creates a DOM Document object
		$responseDoc = new DomDocument();
		$responseDoc->loadXML($responseXml);
		
		
		//get any error nodes
		$errors = $responseDoc->getElementsByTagName('Errors');
		
		//if there are error nodes
		if($errors->length > 0)
		{
			echo '<P><B>eBay returned the following error(s):</B>';
			//display each error
			//Get error code, ShortMesaage and LongMessage
			$code = $errors->item(0)->getElementsByTagName('ErrorCode');
			$shortMsg = $errors->item(0)->getElementsByTagName('ShortMessage');
			$longMsg = $errors->item(0)->getElementsByTagName('LongMessage');
			//Display code and shortmessage
			echo '<P>', $code->item(0)->nodeValue, ' : ', str_replace(">", "&gt;", str_replace("<", "&lt;", $shortMsg->item(0)->nodeValue));
			//if there is a long message (ie ErrorLevel=1), display it
			if(count($longMsg) > 0)
				echo '<BR>', str_replace(">", "&gt;", str_replace("<", "&lt;", $longMsg->item(0)->nodeValue));
	
		}
		else //no errors
		{
			//get the nodes needed
			$catCount = $responseDoc->getElementsByTagName('CategoryCount');
			$suggestedCategories = $responseDoc->getElementsByTagName('SuggestedCategory');
		
			//display title and number of categories returned
			echo '<P><B>Suggested Categories - ', $catCount->item(0)->nodeValue, '</B>';
			
			//go through each suggested category
			foreach($suggestedCategories as $cat)
			{
				//get SuggestedCategory details
				$catName = $cat->getElementsByTagName('CategoryName');
				$catId = $cat->getElementsByTagName('CategoryID');
				$percentItems = $cat->getElementsByTagName('PercentItemFound');
				//display suggested category
				echo '<BR>', $catName->item(0)->nodeValue, ' (', $catId->item(0)->nodeValue, ') - ', $percentItems->item(0)->nodeValue, '% of items';
			}
		}
	}
?>

</BODY>
</HTML>
