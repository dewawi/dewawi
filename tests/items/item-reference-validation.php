<?php

if(PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/items/item-reference-validation.php (in-memory database).
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/security/fixtures/tenant-access.php';
require_once $root . '/application/modules/items/controllers/ItemController.php';

$checks = 0;
function checkItemReference(bool $condition, string $message): void
{
	global $checks;
	$checks++;
	if(!$condition) throw new RuntimeException($message);
}

class ItemReferenceTestController extends Items_ItemController
{
	public function init() {}

	public function validateReferences(array $values, array $row): array
	{
		$this->validateReferenceValues($values, $row);
		return $values;
	}
}

$adapter = tenantAccessFixture();
Zend_Registry::set('Client', ['id' => 1, 'parentid' => 0]);
$adapter->rows += [
	'category' => [
		['id' => 10, 'type' => 'item', 'clientid' => 1, 'deleted' => 0],
		['id' => 11, 'type' => 'contact', 'clientid' => 1, 'deleted' => 0],
		['id' => 12, 'type' => 'item', 'clientid' => 9, 'deleted' => 0],
	],
	'taxrate' => [
		['id' => 20, 'clientid' => 1, 'deleted' => 0],
		['id' => 21, 'clientid' => 9, 'deleted' => 0],
	],
	'warehouse' => [
		['id' => 30, 'clientid' => 1, 'deleted' => 0, 'activated' => 1],
		['id' => 31, 'clientid' => 9, 'deleted' => 0, 'activated' => 1],
	],
	'uom' => [
		['id' => 40, 'clientid' => 1, 'deleted' => 0],
		['id' => 41, 'clientid' => 9, 'deleted' => 0],
	],
	'manufacturer' => [
		['id' => 50, 'clientid' => 1, 'deleted' => 0],
		['id' => 51, 'clientid' => 9, 'deleted' => 0],
	],
	'deliverytime' => [
		['id' => 60, 'clientid' => 1, 'deleted' => 0],
		['id' => 61, 'clientid' => 9, 'deleted' => 0],
	],
	'currency' => [
		['id' => 70, 'code' => 'EUR', 'symbol' => 'EUR', 'clientid' => 1, 'deleted' => 0],
		['id' => 71, 'code' => 'USD', 'symbol' => 'USD', 'clientid' => 9, 'deleted' => 0],
	],
];

$request = new Zend_Controller_Request_Http();
$request->setModuleName('items')->setControllerName('item')->setActionName('edit');
$controller = new ItemReferenceTestController($request, new Zend_Controller_Response_Http());
$row = [
	'catid' => 0,
	'taxid' => 0,
	'warehouseid' => 30,
	'uomid' => 0,
	'manufacturerid' => 0,
	'deliverytime' => 0,
	'deliverytimeoos' => 0,
	'currency' => 'EUR',
	'type' => 'stockItem',
];
$valid = [
	'catid' => 10,
	'taxid' => 20,
	'warehouseid' => 30,
	'uomid' => 40,
	'manufacturerid' => 50,
	'deliverytime' => 60,
	'deliverytimeoos' => 60,
	'currency' => 'EUR',
	'type' => 'service',
];
checkItemReference($controller->validateReferences($valid, $row) === $valid, 'Valid item references were rejected');

foreach([
	['catid' => 11],
	['catid' => 12],
	['taxid' => 21],
	['warehouseid' => 0],
	['warehouseid' => 31],
	['uomid' => 41],
	['manufacturerid' => 51],
	['deliverytime' => 61],
	['deliverytimeoos' => 61],
	['currency' => 'USD'],
	['type' => 'foreign'],
	['taxid' => null],
] as $invalid) {
	try {
		$controller->validateReferences($invalid, $row);
		checkItemReference(false, 'Invalid or cross-tenant item reference was accepted');
	} catch(InvalidArgumentException $exception) {
		checkItemReference(true, '');
	}
}

$legacy = array_replace($row, ['catid' => 999, 'currency' => 'LEGACY']);
checkItemReference($controller->validateReferences(['catid' => 999, 'currency' => 'LEGACY', 'title' => 'Saved'], $legacy)['title'] === 'Saved', 'Unchanged legacy references blocked an unrelated edit');

$form = new Items_Form_Item();
checkItemReference($form->getElement('warehouseid')['source'] === 'warehouse', 'Warehouse options are not loaded into the item form');

echo 'PASS: ' . $checks . " item reference, tenant and warehouse option cases\n";
