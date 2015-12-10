<?php
// Configuration class to handle settings
class eBaySession {
	private $properties;

 	public function __construct($dev, $app, $cert) {
		$this->properties = array(
			'dev'      => null,
			'app'      => null,
			'cert'     => null,
			'wsdl'     => null,
			'options'  => null,
			'token'    => null,
			'site'     => null,
			'location' => null,
		);

		$this->dev = $dev;
		$this->app = $app;
		$this->cert = $cert;
	
		$this->wsdl = 'http://developer.ebay.com/webservices/latest/eBaySvc.wsdl';
		$this->options = array('trace' => 1, 
		                       'exceptions' => 0,
		                       'classmap' => array(/* 'UserType' => 'eBayUserType', */
		                                           'GetSearchResultsResponseType' => 'eBayGetSearchResultsResponseType',
		                                           'SearchResultItemArrayType' => 'eBaySearchResultItemArrayType',
		                                           'SearchResultItemType' => 'eBaySearchResultItemType',
		                                           'AmountType' => 'eBayAmountType',
		                                           'FeeType' => 'eBayFeeType',
		                                           'FeesType' => 'eBayFeesType',
		                                          ),
		                       /* 'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP, */
		                      );
	}

	public function __set($property, $value) {
		 if (array_key_exists($property, $this->properties)) {
		    $this->properties[$property] = $value;
		 } else {
		    return null;
		 }
	}

	public function __get($property) {
		 if (array_key_exists($property, $this->properties)) {
		    return $this->properties[$property];
		 } else {
		    return null;
		 }
	}
}

// Necessary to construct SOAP headers for authentication
class eBayCredentials {
	private $AppId;
	private $DevId;
	private $AuthCert;

	public function __construct(eBaySession $session) {
		$this->AppId = new SoapVar($session->app, XSD_STRING, null, null, null, 'urn:ebay:apis:eBLBaseComponents');
		$this->DevId = new SoapVar($session->dev, XSD_STRING, null, null, null, 'urn:ebay:apis:eBLBaseComponents');
		$this->AuthCert = new SOAPVar($session->cert, XSD_STRING, null, null, null, 'urn:ebay:apis:eBLBaseComponents');
	}
}

// Necessary to construct SOAP headers for authentication
class eBayAuth {
	private $eBayAuthToken;
	private $Credentials;

	public function __construct(eBaySession $session) {
		$credentials = new eBayCredentials($session);
		$this->eBayAuthToken = new SoapVar($session->token, XSD_STRING,	null, null, null, 'urn:ebay:apis:eBLBaseComponents');
		$this->Credentials = new SoapVar($credentials, SOAP_ENC_OBJECT, null, null, null, 'urn:ebay:apis:eBLBaseComponents');
	}
}

// Main class for communication with eBay Web services via SOAP
class eBaySOAP extends SoapClient {
	private $headers = null;
	private $session = null;

	public function __construct(eBaySession $session) {
		$this->session = $session;
		$this->__setHeaders();
		parent::__construct($session->wsdl, $session->options);
	}

	private function __setHeaders() {
		$eBayAuth = new eBayAuth($this->session);
		$header_body = new SoapVar($eBayAuth, SOAP_ENC_OBJECT);
		$headers = array(new SOAPHeader('urn:ebay:apis:eBLBaseComponents', 'RequesterCredentials', $header_body));
	
		$this->headers = $headers;
	}

 	public function __call($function, $args) {
		$callname = $function;
		$siteid = $this->session->site;
		$version = $args[0]['Version'];
		$appid = $this->session->app;
		$Routing = 'default'; // XXX: hardcoded

		$query_string = http_build_query(array('callname' => $callname, 'siteid' => $siteid, 'version' => $version, 'appid' => $appid, 'Routing' => $Routing));
	 	$location = "{$this->session->location}?{$query_string}";

 		return $this->__soapCall($function, $args, array('location' => $location), $this->headers);
 	}
}

// General utility class. Currently not used.
class eBayUtils {
	static public function findByName($values, $name) {
		foreach($values as $value) {
			if ($value->Name == $name) {
				return $value;
			}
		}
	}
}

// The following classes are used in the classmap array
// Right now, they are largely experiments to see how I can make it easier to use the API
class eBayFeesType implements ArrayAccess {
	public function offsetExists($offset) {
		return true;
	}

	public function offsetGet($offset) {
		foreach ($this->Fee as $value) {
			if ($value->Name == $offset) {
				return $value;
			}
		}
	}

	public function offsetSet($offset, $value) {
		return true;
	}

	public function offsetUnset($offset) {
		return true;
	}

}

class eBayFeeType {
	public function __toString() {
		return (string) $this->Fee->_;
	}
}

class eBayGetSearchResultsResponseType implements IteratorAggregate {
	public function getIterator( ) {
        return $this->SearchResultItemArray;
    }
	
}

class eBaySearchResultItemArrayType implements IteratorAggregate {
	public function getIterator( ) {
        return new ArrayObject($this->SearchResultItem);
    }
}

class eBaySearchResultItemType {
	public function __toString() {
		return $this->Item->Title;
	}
}

class eBayAmountType {
	public function __toString() {
		return (string) $this->_;
	}
}


?>
