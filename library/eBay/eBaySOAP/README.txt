eBay Web Services example code using PHP 5 ext/soap extension.

* eBaySOAP.php
Greatly simplifies process of connecting to eBay's SOAP API

- Handles creation of user credentials through SOAP header elements
- Intercepts request to seamlessly pass along headers
- Intercepts request to rewrite endpoint to include call and other values
- Includes eBaySession class to configure session data
- Limited XML Schema <-> PHP class mappings
- Limited iterator and ArrayAccess methods to simplify interactions
- Requires PHP 5 and ext/soap extension

* ebay.ini
PHP formatted ini file for storing developer keys and tokens for both Sandbox and 
Production servers.

- Makes it easy to switch from Sandbox to Production and back again
- Eliminates need to hardcode credentials within your scripts
- You will need to modify to insert your own credentials to get this to work.

* AddItem.php
* GetSearchResults.php
* GetUser.php
* ReviseItem.php
Example scripts showing how to use eBaySOAP and eBaySession classes.

- Shows how to set up basic call framework
- Provides working examples of reading and writing data
- Shows how ext/soap maps PHP variables to eBay SOAP input and vice versa


=========
June 2007

Here are a few pointers if you're having trouble getting these SOAP samples to run.

As noted above, you'll need PHP 5.x and the ext/soap extension.  

If you receive an error of form "Fatal error: Class 'SoapClient' not found" 
then you'll need to enable the php SOAP extension.  See this Knowledge Base
article for details.

https://ebay.custhelp.com/cgi-bin/ebay.cfg/php/enduser/std_adp.php?p_faqid=1032

Then, for example, you may just receive a blank page when trying to run an sample like
GetSearchResults.

You can insert the following for debugging :

    $results = $client->GetSearchResults($params);
    
    print "<pre>";
    print_r($results);
    print "</pre>";

You may see output similar to this :

...
    [faultstring] => SSL support is not available in this build
...

In this case, you will need to enable the OpenSSL module.  

In the php.ini file, uncomment the following line (remove leading semi-colon) :

extension=php_openssl.dll


Then reboot apache and from a phpinfo() call you should see the following :

openssl
OpenSSL support 	enabled
OpenSSL Version 	OpenSSL 0.9.8a 11 Oct 2005






