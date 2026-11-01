<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/items/shop-assignment.php (no database required).

error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) {
		require_once $path;
		return;
	}

	$parts = explode('_', $class);
	if (count($parts) < 3) return;
	$module = strtolower(array_shift($parts));
	$path = $root . '/application/modules/' . $module . '/' . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
	if (is_file($path)) require_once $path;
});

require_once $root . '/application/modules/items/services/ShopAssignment.php';
require_once $root . '/application/modules/items/forms/Item.php';

$checks = 0;
function checkShopAssignment(bool $condition, string $message): void
{
	global $checks;
	$checks++;
	if (!$condition) throw new RuntimeException($message);
}

class ShopAssignmentFixture extends Items_Service_ShopAssignment
{
	protected function loadShopOptions(): array
	{
		return ['1' => 'Website One'];
	}

	protected function loadCategoryOptions(int $shopId): array
	{
		return $shopId === 1 ? ['10' => 'Products', '11' => '— Components'] : [];
	}

	protected function findShop(int $shopId): ?array
	{
		return $shopId === 1 ? ['id' => 1, 'clientid' => 7, 'deleted' => 0] : null;
	}

	protected function findCategory(int $categoryId): ?array
	{
		return [
			10 => ['id' => 10, 'clientid' => 7, 'shopid' => 1, 'type' => 'shop', 'deleted' => 0],
			11 => ['id' => 11, 'clientid' => 7, 'shopid' => 1, 'type' => 'shop', 'deleted' => 0],
			20 => ['id' => 20, 'clientid' => 7, 'shopid' => 2, 'type' => 'shop', 'deleted' => 0],
			30 => ['id' => 30, 'clientid' => 7, 'shopid' => 1, 'type' => 'item', 'deleted' => 0],
		][$categoryId] ?? null;
	}
}

$service = new ShopAssignmentFixture();
checkShopAssignment($service->getShopOptions() === ['0' => 'ITEMS_SHOP_NONE', '1' => 'Website One'], 'Shop options are incomplete');
checkShopAssignment(array_keys($service->getCategoryOptions(1)) === [0, 10, 11], 'Shop category options are incomplete');
checkShopAssignment($service->getCategoryOptions(2) === ['0' => 'ITEMS_SHOP_CATEGORY_NONE'], 'Foreign shop categories are exposed');

$current = ['shopid' => 1, 'shopcatid' => 10];
checkShopAssignment($service->normalize(['title' => 'Unchanged'], $current) === ['title' => 'Unchanged'], 'Unrelated save changed the shop assignment');
checkShopAssignment($service->normalize(['shopid' => 1], $current) === ['shopid' => 1], 'Unchanged shop cleared its category');
checkShopAssignment($service->normalize(['shopid' => 0], $current) === ['shopid' => 0, 'shopcatid' => null], 'Removing a shop did not clear its category');
checkShopAssignment($service->normalize(['shopcatid' => 11], $current) === ['shopcatid' => 11, 'shopid' => 1], 'Valid shop category was not normalized');
checkShopAssignment($service->normalize(['shopid' => 1, 'shopcatid' => 0], ['shopid' => 0, 'shopcatid' => null]) === ['shopid' => 1, 'shopcatid' => null], 'Incomplete assignment was not retained as a draft');

foreach ([['shopid' => 2], ['shopcatid' => 20], ['shopcatid' => 30]] as $invalid) {
	try {
		$service->normalize($invalid, $current);
		checkShopAssignment(false, 'Invalid or cross-shop assignment was accepted');
	} catch (InvalidArgumentException $exception) {
		checkShopAssignment(true, '');
	}
}

checkShopAssignment($service->getStatus(['shopid' => 0, 'shopcatid' => null])['state'] === 'unassigned', 'Missing assignment status is wrong');
checkShopAssignment($service->getStatus(['shopid' => 1, 'shopcatid' => null])['state'] === 'incomplete', 'Incomplete assignment status is wrong');
checkShopAssignment($service->getStatus($current)['state'] === 'assigned', 'Valid assignment status is wrong');
checkShopAssignment($service->getStatus(['shopid' => 1, 'shopcatid' => 20])['state'] === 'invalid', 'Cross-shop assignment status is wrong');

$form = new Items_Form_Item();
$form->configureShopAssignment($service->getShopOptions(), $service->getCategoryOptions(1), 1, 10);
$form->setValues($current);
$shopHtml = $form->renderElement('shopid');
$categoryHtml = $form->renderElement('shopcatid');
checkShopAssignment(str_contains($shopHtml, 'data-shop-assignment="shop"') && str_contains($shopHtml, '<option value="1" selected>Website One</option>'), 'Existing shop assignment is not rendered in the editor');
checkShopAssignment(str_contains($categoryHtml, 'data-shop-assignment="category"') && str_contains($categoryHtml, '<option value="10" selected>Products</option>'), 'Existing shop category assignment is not rendered in the editor');
checkShopAssignment($form->isValidPartial(['shopcatid' => 11]), 'Form rejected an allowed shop category');
checkShopAssignment(!$form->isValidPartial(['shopcatid' => 20]) && isset($form->getErrors()['shopcatid']), 'Form accepted a category from another shop');
checkShopAssignment(!$form->isValidPartial(['shopid' => 2]) && isset($form->getErrors()['shopid']), 'Form accepted a foreign shop');
checkShopAssignment(!$form->isValidPartial(['shopcatid' => [11]]) && isset($form->getErrors()['shopcatid']), 'Form accepted a non-scalar shop category');
checkShopAssignment($form->isValidPartial(['shopid' => 1]), 'Form rejected its unchanged shop');

$controllerSource = file_get_contents($root . '/application/modules/items/controllers/ItemController.php');
$viewSource = file_get_contents($root . '/application/modules/items/views/scripts/item/edit.phtml');
$jsSource = file_get_contents($root . '/js/global.js');
checkShopAssignment(str_contains($controllerSource, 'Items_Service_ShopAssignment') && str_contains($controllerSource, "['ok' => true, 'options' => \$options]"), 'Controller does not validate and serve shop categories');
checkShopAssignment(str_contains($controllerSource, 'foreach ((new Items_Model_DbTable_Item())->getVariants($id) as $variant)') && str_contains($controllerSource, 'saveShopSlug'), 'Variant slugs are not synchronized with the manual shop assignment');
checkShopAssignment(str_contains($viewSource, "'shop' => ['title' => 'TABS_SHOP']"), 'Shop tab is still hidden for unassigned products');
checkShopAssignment(str_contains($jsSource, '[data-shop-assignment="shop"]') && str_contains($jsSource, 'shopcatid: 0'), 'Shop selection does not clear and reload its category');

echo 'PASS: ' . $checks . " manual shop assignment, validation and tenant-bound option cases\n";
