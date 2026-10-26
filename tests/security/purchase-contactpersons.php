<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/security/purchase-contactpersons.php (no database required).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());

spl_autoload_register(static function(string $class) use ($root): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) {
		require_once $path;
		return;
	}

	$parts = explode('_', $class);
	if (count($parts) >= 3) {
		$module = array_shift($parts);
		$base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
		$path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
		if (is_file($path)) require_once $path;
	}
});

$checks = 0;
function check(bool $condition, string $message): void
{
	global $checks;
	if (!$condition) throw new RuntimeException($message);
	$checks++;
}

foreach ([
	'purchaseorder' => new Purchases_Form_Purchaseorder(),
	'quoterequest' => new Purchases_Form_Quoterequest(),
] as $document => $form) {
	$element = $form->getElement('contactpersonid');
	check($element !== null && $element['type'] === 'select' && $element['tab'] === 'customer', $document . ' has no contact-person select');
	check($form->isValidPartial(['contactpersonid' => '42']), $document . ' contact-person auto-save validation failed');
	check($form->getFilteredValuesPartial(['contactpersonid' => '42'])['contactpersonid'] === 42, $document . ' contact-person ID was not normalized');
}

$dePurchaseorder = require $root . '/languages/de_DE/purchases/purchaseorder.php';
$enPurchaseorder = require $root . '/languages/en_US/purchases/purchaseorder.php';
$deQuoterequest = require $root . '/languages/de_DE/purchases/quoterequest.php';
$enQuoterequest = require $root . '/languages/en_US/purchases/quoterequest.php';
check($dePurchaseorder['PURCHASE_ORDERS_CONTACT'] === 'Lieferant' && $dePurchaseorder['PURCHASE_ORDERS_CONTACT_ID'] === 'Lieferantennr.', 'German purchase-order supplier labels are incorrect');
check($deQuoterequest['QUOTE_REQUESTS_CONTACT'] === 'Lieferant' && $deQuoterequest['QUOTE_REQUESTS_CONTACT_ID'] === 'Lieferantennr.', 'German quote-request supplier labels are incorrect');
check($enPurchaseorder['PURCHASE_ORDERS_CONTACT'] === 'Supplier' && $enPurchaseorder['PURCHASE_ORDERS_CONTACT_ID'] === 'Supplier ID', 'English purchase-order supplier labels are incorrect');
check($enQuoterequest['QUOTE_REQUESTS_CONTACT'] === 'Supplier' && $enQuoterequest['QUOTE_REQUESTS_CONTACT_ID'] === 'Supplier ID', 'English quote-request supplier labels are incorrect');

foreach (['purchaseorder', 'quoterequest'] as $document) {
	foreach (['edit', 'view'] as $action) {
		$view = file_get_contents($root . '/application/modules/purchases/views/scripts/' . $document . '/' . $action . '.phtml');
		check(str_contains($view, "'customer' => ['title' => 'TABS_SUPPLIER']"), $document . ' ' . $action . ' view still labels the supplier tab as customer');
	}
}

$contactpersonModel = file_get_contents($root . '/application/modules/contacts/models/DbTable/Contactperson.php');
$contactpersonController = file_get_contents($root . '/application/modules/contacts/controllers/ContactpersonController.php');
$contactsJs = file_get_contents($root . '/js/contacts.js');
$purchaseController = file_get_contents($root . '/application/modules/purchases/controllers/PurchaseorderController.php');
$quoteRequestController = file_get_contents($root . '/application/modules/purchases/controllers/QuoterequestController.php');
$salesForms = file_get_contents($root . '/application/modules/sales/forms/Quote.php')
	. file_get_contents($root . '/application/modules/sales/forms/Salesorder.php');
$responsibleMigration = file_get_contents($root . '/install/config/update-1.1.5-1.1.6.sql');

check(str_contains($contactpersonModel, 'DEEC_ContactpersonFormatter::displayName'), 'Contact-person options do not use the shared formatter');
check(str_contains($contactpersonController, "'displayname' => \$label"), 'Existing contact-person endpoint does not provide formatted option labels');
check(str_contains($contactsJs, 'refreshContactpersonOptions(contact.id, selectedContactpersonId)') && str_contains($contactsJs, 'data.contactpersonid = selectedContactpersonId'), 'Supplier autocomplete does not refresh and save the contact-person selection');
check(str_contains($purchaseController, 'validateDocumentContactperson') && str_contains($quoteRequestController, 'validateDocumentContactperson'), 'Purchase controllers do not validate contact-person ownership');
check(!str_contains($salesForms, "'name' => 'contactpersonid'"), 'Sales forms were unexpectedly changed to use purchase contact persons');
check(str_contains($responsibleMigration, 'SET `responsible` = `contactperson`') && str_contains($responsibleMigration, 'SET `contactperson` = NULL'), 'Historical contactperson field semantics are no longer documented by the migration');

echo 'PASS: ' . $checks . " purchase contact-person integration cases\n";
