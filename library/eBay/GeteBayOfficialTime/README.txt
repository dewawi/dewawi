This example displays the current official eBay time in GMT.

To get started with this example you must open the ../get-common/keys.php file and edit the variables as follows:
$devID - Your Developer ID
$appID - Your Application ID
$certID - Your Certificate ID
$serverUrl - The eBay server you wish to submit your request to (Sandbox or Production)
$userToken - the Authentication Token representing the eBay user who is making the call.

This example uses the the CURL and DOM Packages, therefore these must be installed on the server on which the code is executed. The DOM package is a standard package in PHP5.