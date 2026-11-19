<?php

// Reuse real list/layout rendering without bootstrapping a database or session.
ob_start();
$mobileArguments = $argv;
$argv = [$argv[0]];
require __DIR__ . '/list-buttons.php';
$argv = $mobileArguments;
$mobileBaseline = ob_get_clean();
$checks = 0;

if (($argv[1] ?? '') === 'render') {
	$variant = $argv[7] ?? '';
	$rights = $variant === 'view-only' ? ['view'] : ['view', 'add', 'edit', 'delete'];
	$items = $variant === 'long' ? [['id' => 17, 'title' => str_repeat('LongUnbrokenFixtureName', 4), 'name1' => str_repeat('LangerUngetrennterKontaktname', 4), 'street' => 'Fixture Street 123', 'city' => 'Fixture City']] : null;
	echo json_encode(toolbarPage($argv[2], $argv[3], $argv[4], $argv[5], $rights, $variant === 'readonly', $argv[6], false, false, true, $items), JSON_THROW_ON_ERROR);
	exit;
}

foreach (['de_DE', 'en_US'] as $locale) foreach (['contacts' => 'contact', 'tasks' => 'task'] as $module => $controller) {
	$class = ucfirst($module) . '_Model_List_' . ucfirst($module);
	$list = new $class(['module' => $module, 'controller' => $controller]);
	$columns = $list->getColumns();
	check($list->getId() === $module . '-' . $controller && $list->getTableClass() === 'dw-table dw-table--' . $module . '-' . $controller, 'Mobile CSS lost its existing table scope');
	check(end($columns)['name'] === 'actions' && end($columns)['class'] === 'dw-col-actions', 'Actions lost their semantic column class');
	foreach (['layout', 'plain'] as $layout) foreach (['index', 'select'] as $action) {
		$page = toolbarPage($locale, $module, $action, $layout, ['view', 'add', 'edit', 'delete'], false, 'index', false, false, true);
		$dom = buttonDom($page['html']);
		$xpath = new DOMXPath($dom->ownerDocument);
		$scope = $layout === 'layout' ? 'dw-app-shell__inner ' . $module . ' ' . $action : 'dw-popup-shell dw-popup-shell--' . $module . ' dw-popup-shell--' . $controller . ' dw-popup-shell--' . $action;
		check(str_contains($page['html'], $scope), 'Existing full/plain context classes changed');
		check($xpath->query('//th[contains(@class,"dw-col-actions")]')->length === 1 && $xpath->query('//td[contains(@class,"dw-col-actions")]')->length === 2, 'Header/body actions no longer share their column');
		foreach ($xpath->query('//div[@class="dw-row-actions"]/button') as $button) check($button->getAttribute('type') === 'button' && in_array($button->getAttribute('data-id'), ['17', '18'], true), 'Mobile presentation changed native actions/row IDs');
	}
}

echo $mobileBaseline;
echo 'PASS: ' . $checks . " mobile table scope, semantic column and native action checks\n";
