<?php

// Reuse the existing memory fixtures; no ERP configuration or database connection.
ob_start();
$areaArguments = $argv;
$argv = [$argv[0]];
require __DIR__ . '/toolbar-buttons.php';
$argv = $areaArguments;
ob_end_clean();
$checks = 0;

class AreaProcessesToolbarLegacy extends Processes_Form_Toolbar {}
class AreaProcessesInlineLegacy extends Processes_Form_ToolbarInline {}
class AreaItemsToolbarLegacy extends Items_Form_Toolbar {}
class AreaItemsInlineLegacy extends Items_Form_ToolbarInline {}
class AreaAdminToolbarLegacy extends Admin_Form_Toolbar {}
class AreaAdminInlineLegacy extends Admin_Form_ToolbarInline {}

$areas = ['processes/process' => 'Processes', 'items/pricerule' => 'Items', 'admin/shop' => 'Admin'];
function areaAttributes(DOMElement $node): array {
	$attributes = [];
	foreach ($node->attributes as $attribute) if ($attribute->name !== 'class') $attributes[$attribute->name] = $attribute->value;
	ksort($attributes);
	return $attributes;
}
function areaContext(string $module, string $controller, string $action, array $rights): void {
	global $adapter;
	toolbarContext($module, $controller, $action, $rights);
	$adapter->permissions[0]['items'] = json_encode(['item' => $rights, 'pricerule' => $rights]);
	$adapter->permissions[0]['processes'] = json_encode(['process' => $rights]);
	$user = Zend_Registry::get('User'); $user['admin'] = $module === 'admin' ? 1 : 0; Zend_Registry::set('User', $user);
}

if (($argv[1] ?? '') === 'snapshot') {
	$snapshot = [];
	foreach ($areas as $route => $prefix) foreach (['index', 'search', 'select', 'edit', 'view', 'export', 'get', 'add', 'copy'] as $action) {
		[$module, $controller] = explode('/', $route);
		areaContext($module, $controller, $action, ['view', 'add', 'edit', 'delete']);
		foreach (['Toolbar', 'ToolbarInline'] as $kind) { $class = $prefix . '_Form_' . $kind; $snapshot[$route . '/' . $action . '/' . $class] = (new $class())->getElements(); }
	}
	echo json_encode($snapshot, JSON_THROW_ON_ERROR); exit;
}

foreach (['de_DE', 'en_US'] as $locale) foreach ($areas as $route => $prefix) {
	[$module, $controller] = explode('/', $route);
	buttonTranslations($locale);
	foreach (['index', 'search', 'select', 'edit', 'view', 'export', 'get', 'add', 'copy'] as $action) foreach (['index', 'select'] as $context) {
		areaContext($module, $controller, $action, ['view', 'add', 'edit', 'delete']);
		Zend_Controller_Front::getInstance()->getRequest()->setParam('context_action', $context);
		foreach (['Toolbar', 'ToolbarInline'] as $kind) {
			$class = $prefix . '_Form_' . $kind; $legacyClass = 'Area' . $prefix . ($kind === 'Toolbar' ? 'ToolbarLegacy' : 'InlineLegacy');
			$form = new $class(); $legacy = new $legacyClass();
			check(!array_filter($legacy->getElements(), static fn($element) => isset($element['iconFormat'])), 'Subclass inherited an unintended SVG opt-in');
			$enabled = $kind === 'Toolbar' ? in_array($action, ['index', 'search', 'select', 'edit', 'view'], true) : in_array($action, ['index', 'search'], true) && $context === 'index';
			$names = $kind === 'Toolbar' ? ['add', 'edit', 'copy', 'delete', 'filter', 'reset', 'clear'] : ($module === 'admin' ? ['copy'] : ($module === 'items' ? ['edit', 'copy', 'delete'] : ['view', 'edit', 'copy', 'delete']));
			foreach ($legacy->getElements() as $name => $old) {
				$expected = $old;
				if ($enabled && in_array($name, $names, true)) {
					$expected = array_replace($old, ['icon' => $name, 'iconFormat' => 'svg']);
					if ($kind === 'ToolbarInline') $expected = array_replace($expected, ['iconOnly' => true, 'size' => 'sm']);
					if ($name === 'clear') $expected = array_replace($expected, ['label' => 'CLEAR', 'iconOnly' => true]);
				}
				check($form->getElement($name) === $expected, 'Area opt-in changed behavior/context: ' . $route . '/' . $action . '/' . $kind . '/' . $name);
				if (!in_array($old['type'], ['button', 'submit'], true)) continue;
				$attrs = ['data-id' => '18', 'data-module' => $module, 'data-controller' => $controller, 'data-action' => $name, 'id' => 'fixture', 'value' => '0', 'rel' => 'fixture', 'onclick' => 'fixture()', 'data-autosave' => 'false', 'aria-describedby' => 'description'];
				$a = $form->renderElementWithAttribs($name, $attrs); $b = $legacy->renderElementWithAttribs($name, $attrs);
				if ($expected === $old) { check($a === $b, 'Excluded area action changed markup'); continue; }
				$a = buttonDom($a)->firstChild; $b = buttonDom($b)->firstChild;
				check($a instanceof DOMElement && $b instanceof DOMElement && areaAttributes($a) === areaAttributes($b) && $a->textContent === $b->textContent, 'Native/event/ARIA contract changed');
				check($a->getElementsByTagName('use')->item(0)->getAttribute('href') === '#dw-icon-' . ($name === 'clear' ? 'close' : $name), 'Missing area symbol');
				foreach (explode(' ', $b->getAttribute('class')) as $css) check(in_array($css, explode(' ', $a->getAttribute('class')), true), 'Old class removed');
			}
		}
	}
	if ($module !== 'admin') foreach ([[], ['view']] as $rights) {
		areaContext($module, $controller, 'index', $rights);
		foreach (['Toolbar', 'ToolbarInline'] as $kind) {
			$class = $prefix . '_Form_' . $kind;
			foreach (['add', 'edit', 'copy', 'delete'] as $name) check((new $class())->renderElement($name) === '', 'Area button bypassed existing rights');
		}
	}
}

foreach ([['processes', 'position'], ['processes', 'positionset'], ['items', 'inventory'], ['items', 'stock'], ['items', 'ledger'], ['items', 'itemlist'], ['admin', 'page'], ['admin', 'media'], ['admin', 'config'], ['admin', 'user'], ['shops', 'order'], ['sales', 'quote']] as [$module, $controller]) foreach (['index', 'search', 'select', 'edit'] as $action) {
	areaContext($module, $controller, $action, ['view', 'add', 'edit', 'delete']);
	foreach (['Processes', 'Items', 'Admin'] as $prefix) foreach (['Toolbar', 'ToolbarInline'] as $kind) {
		$class = $prefix . '_Form_' . $kind; $legacyClass = 'Area' . $prefix . ($kind === 'Toolbar' ? 'ToolbarLegacy' : 'InlineLegacy');
		check((new $class())->getElements() === (new $legacyClass())->getElements(), 'SVG leaked into an excluded module/controller');
	}
}
echo 'PASS: ' . $checks . " B18 area metadata, attributes, names, translations, scope and permission assertions\n";
