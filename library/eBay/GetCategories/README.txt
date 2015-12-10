This example demonstrates techniques for downloading, saving and keeping up-to-date the local copy of the Category Tree. It then displays all of the Top-Level categories. When creating a real-world application you will not need to check the online version number every time the code is executed.

This example will create an 11 MB CatTree.xml file in this directory upon execution.


To get started with this example you must open the keys.php file and edit the variables as follows:
$devID - Your Developer ID
$appID - Your Application ID
$certID - Your Certificate ID
$serverUrl - The eBay server you wish to submit your request to (Sandbox or Production)
$userToken - the Authentication Token representing the eBay user who is making the call.

This example uses the the CURL and DOM Packages, therefore these must be installed on the server on which the code is executed. The DOM package is a standard package in PHP5.