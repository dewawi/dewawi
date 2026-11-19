<?php

// Inspect real presentation metadata using the existing memory-only fixture.
ob_start();
$argv = [$argv[0]];
require __DIR__ . '/toolbar-buttons.php';
ob_end_clean();
buttonTranslations('de_DE');

$groups = [
	'contacts/inquiry' => ['Contacts_Form_InquiryToolbar', 'Contacts_Form_InquiryToolbarInline', 'Contacts_Form_Inquiry'],
	'contacts/contact' => ['Contacts_Form_Toolbar', 'Contacts_Form_ToolbarInline'],
	'items/item' => ['Items_Form_Toolbar', 'Items_Form_ToolbarInline'],
	'tasks/task' => ['Tasks_Form_Toolbar', 'Tasks_Form_ToolbarInline'],
];
foreach (['country', 'uom', 'deliverytime', 'manufacturer', 'warehouse', 'shippingmethod'] as $controller) $groups['admin/' . $controller] = ['Admin_Form_Toolbar', 'Admin_Form_ToolbarInline'];
foreach (['customer', 'quote', 'turnover'] as $controller) $groups['statistics/' . $controller] = ['Statistics_Form_Toolbar'];
$fields = [];
$byController = [];
foreach ($groups as $route => $classes) {
	[$module, $controller] = explode('/', $route);
	foreach ([['index', 'index'], ['search', 'index'], ['search', 'select'], ['select', 'select'], ['edit', 'edit'], ['view', 'view']] as [$action, $context]) {
		toolbarContext($module, $controller, $action);
		Zend_Controller_Front::getInstance()->getRequest()->setParam('context_action', $context);
		foreach ($classes as $class) foreach ((new $class())->getElements() as $name => $element) {
			if (($element['iconFormat'] ?? '') !== 'svg') continue;
			$key = $class . '::' . $name;
			if (!isset($fields[$key])) $fields[$key] = ['file' => substr((new ReflectionClass($class))->getFileName(), strlen($root) + 1), 'type' => $element['type'], 'icon' => $element['icon'], 'label' => $element['label'], 'contexts' => []];
			$fields[$key]['contexts'][] = $route . '/' . $action . '/' . $context;
			$byController[$route][$key] = true;
		}
	}
}
ksort($fields);
$visible = [];
foreach (['contacts', 'items', 'tasks', 'admin/country', 'admin/uom', 'admin/deliverytime', 'admin/manufacturer', 'admin/warehouse', 'admin/shippingmethod'] as $route) {
	$page = toolbarPage('de_DE', $route, 'index', 'layout', ['view', 'add', 'edit', 'delete'], false, 'index', false, false, true);
	$visible[$route] = substr_count($page['html'], 'dw-btn--control');
}
echo json_encode([
	'method' => 'Unique class::field metadata with explicit SVG in the documented real controller contexts. Contexts mean constructor activation, not guaranteed visibility. Fixture DOM counts use two synthetic rows and include repeated footer controls; never a production total.',
	'svg_fields' => count($fields),
	'controller_field_pairs' => array_sum(array_map('count', $byController)),
	'controllers' => count($groups),
	'svg_catalog' => (new ReflectionClass(DEEC_Form::class))->getConstant('BUTTON_SVG_ICONS'),
	'controller_fields' => array_map('array_keys', $byController),
	'fields' => $fields,
	'index_fixture_visible_controls' => $visible,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
