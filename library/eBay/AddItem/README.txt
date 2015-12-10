==============================================\

PLEASE EXERCISE CAUTION WHEN USING THIS SAMPLE

If you make listings against the eBay production site you will be charged listing fees.

Please test in the Sandbox before using this against eBay production.

==============================================/


This sample allows the user to create listings with the AddItem call.
You can list several different auction (listing) types into different categories.  

Note that to keep the code simple, some defaults are applied to some of 
the input variables.  For example, BuyItNow prices can not be applied
to StoreInventory listings, and Chinese auctions must have a quantity of one.
Some client-side validation would be useful for this.

Also note that the primary cateory numbers and descriptions apply to SiteID 0 (ebay.com US site).
Category numbers and descriptions vary by site (country).


To get started with this example you must open the ../get-common/keys.php file and edit the variables as follows:
$devID - Your Developer ID
$appID - Your Application ID
$certID - Your Certificate ID
$serverUrl - The eBay server you wish to submit your request to (Sandbox or Production)
$userToken - the Authentication Token representing the eBay user who is making the call.

PLEASE USE YOUR SANDBOX KEYS AND TOKEN BEFORE TRYING THIS IN PRODUCTION AS PRODUCTION LISTINGS WILL INCUR A CHARGE

This example uses the the CURL and DOM Packages, therefore these must be installed on the 
server on which the code is executed. The DOM package is a standard package in PHP5.