<?php

// Exercise the existing list/form/layout pipeline with memory-only data.
ob_start();
$listArguments = $argv;
$argv = [$argv[0]];
require __DIR__ . '/toolbar-buttons.php';
$argv = $listArguments;
$listBaseline = ob_get_clean();
$checks = 0;

class ListContactsLegacyFixture extends Contacts_Form_ToolbarInline {}
class ListItemsLegacyFixture extends Items_Form_ToolbarInline {}
class ListTasksLegacyFixture extends Tasks_Form_ToolbarInline {}
class ListAdminLegacyFixture extends Admin_Form_ToolbarInline {}

$groups = [
	'contacts/contact' => ['Contacts', ['view', 'edit', 'copy']],
	'items/item' => ['Items', ['edit', 'copy']],
	'tasks/task' => ['Tasks', ['view', 'edit', 'copy']],
];
foreach (['country', 'uom', 'deliverytime', 'manufacturer', 'warehouse', 'shippingmethod'] as $controller) $groups['admin/' . $controller] = ['Admin', ['copy']];

function listNativeAttributes(DOMElement $button): array
{
	$attributes = [];
	foreach ($button->attributes as $attribute) if (!in_array($attribute->name, ['class', 'aria-label', 'title'], true)) $attributes[$attribute->name] = $attribute->value;
	ksort($attributes);
	return $attributes;
}

foreach (['de_DE', 'en_US'] as $locale) foreach ($groups as $route => [$prefix, $actions]) {
	[$module, $controller] = explode('/', $route);
	buttonTranslations($locale);
	$class = $prefix . '_Form_ToolbarInline';
	$legacyClass = 'List' . $prefix . 'LegacyFixture';
	foreach ([['index', null], ['index', 'index'], ['search', null], ['search', 'index'], ['search', 'select'], ['search', 'invalid'], ['index', 'select'], ['select', 'index'], ['edit', 'index'], ['view', 'index'], ['history', 'index'], ['get', 'index'], ['export', 'index']] as [$action, $context]) {
		toolbarContext($module, $controller, $action);
		$user = Zend_Registry::get('User');
		$user['admin'] = $module === 'admin' ? 1 : 0;
		Zend_Registry::set('User', $user);
		if ($context !== null) Zend_Controller_Front::getInstance()->getRequest()->setParam('context_action', $context);
		$modern = new $class();
		$legacy = new $legacyClass();
		$enabled = in_array($action, ['index', 'search'], true) && in_array($context, [null, 'index'], true);
		foreach ($legacy->getElements() as $name => $old) {
			$expected = $old;
			if ($enabled && in_array($name, $actions, true)) {
				$label = 'TOOLBAR_' . strtoupper($name);
				$expected = array_replace($old, ['label' => $label, 'icon' => $name, 'iconFormat' => 'svg', 'iconOnly' => true, 'size' => 'sm', 'attribs' => $old['attribs'] + ['title' => $modern->translate($label)]]);
			} elseif ($name === 'apply' && in_array($module, ['contacts', 'items'], true) && ($action === 'select' || ($action === 'search' && $context === 'select'))) {
				$expected = array_replace($old, ['label' => 'TOOLBAR_APPLY', 'icon' => 'apply', 'iconFormat' => 'svg', 'iconOnly' => true, 'size' => 'sm']);
			}
			check($modern->getElement($name) === $expected, 'List metadata changed an excluded field/context: ' . $route . '/' . $action . '/' . $name);
			$attrs = ['id' => 'fixture-action', 'data-action' => $name, 'data-id' => '18', 'data-module' => $module, 'data-controller' => $controller, 'data-autosave' => 'false', 'data-setid' => '7', 'value' => '0', 'onclick' => 'fixture()'];
			$a = $modern->renderElementWithAttribs($name, $attrs);
			$b = $legacy->renderElementWithAttribs($name, $attrs);
			if ($expected === $old) check($a === $b, 'Legacy list action HTML changed');
			elseif ($enabled && in_array($name, $actions, true)) {
				$a = buttonDom($a)->firstChild;
				$b = buttonDom($b)->firstChild;
				check(listNativeAttributes($a) === listNativeAttributes($b), 'List action changed native/target/event attributes');
				check($a->getAttribute('type') === 'button' && $a->getAttribute('name') === $name && $a->getAttribute('data-action') === $name, 'List action became submit/link/reset');
				check($a->getAttribute('aria-label') === $modern->translate($label) && $a->getAttribute('title') === $a->getAttribute('aria-label') && !str_contains($a->getAttribute('aria-label'), 'TOOLBAR_'), 'List accessible name/tooltip untranslated');
				check(trim($a->textContent) === '' && $a->getElementsByTagName('use')->item(0)->getAttribute('href') === '#dw-icon-' . $name && $a->getElementsByTagName('svg')->item(0)->getAttribute('aria-hidden') === 'true', 'Wrong icon or duplicate accessible text');
				foreach (explode(' ', $b->getAttribute('class')) as $css) check(in_array($css, explode(' ', $a->getAttribute('class')), true), 'List action removed legacy class');
			}
		}
	}
	foreach (['layout', 'plain'] as $layout) foreach ([false, true] as $readonly) {
		$page = toolbarPage($locale, $route, 'index', $layout, ['view', 'add', 'edit', 'delete'], $readonly, 'index', false, false, true);
		check(substr_count($page['html'], 'class="dw-icon-symbols"') === 1, 'Full list document missing/duplicating symbols');
		$ids = [];
		foreach (buttonDom($page['content'])->getElementsByTagName('button') as $button) if ($button->hasAttribute('data-id') && in_array($button->getAttribute('name'), $actions, true)) {
			$ids[] = $button->getAttribute('data-id');
			check($button->getElementsByTagName('svg')->length === 1 && $button->hasAttribute('disabled') === $readonly && $button->getAttribute('data-module') === $module && $button->getAttribute('data-controller') === $controller, 'Real list lost SVG, readonly or row target');
		}
		check($ids === array_merge(array_fill(0, count($actions), '17'), array_fill(0, count($actions), '18')), 'Real list gained/lost/reordered row actions');
		$links = buttonDom($page['content'])->getElementsByTagName('a');
		check($links->length >= 2 && str_contains($links->item(0)->getAttribute('href'), '/' . $route . '/edit/id/17'), 'Native list link changed');
	}
	$ajax = toolbarPage($locale, $route, 'search', 'layout', ['view', 'add', 'edit', 'delete'], false, 'index', false, false, true);
	check(array_keys($ajax) === ['content', 'toolbar'] && !str_contains($ajax['content'] . $ajax['toolbar'], '<symbol'), 'Real AJAX list contract/symbol scope changed');
	check(substr_count($ajax['content'], 'dw-btn--sm') === count($actions) * 2, 'Search lost row opt-in');
	if (in_array($module, ['contacts', 'items'], true)) {
		foreach ([[], ['view'], ['edit'], ['add']] as $rights) {
			$page = toolbarPage($locale, $route, 'index', 'layout', $rights, false, 'index', false, false, true);
			$visible = [];
			foreach (buttonDom($page['content'])->getElementsByTagName('button') as $button) if ($button->hasAttribute('data-id') && in_array($button->getAttribute('name'), $actions, true)) $visible[] = $button->getAttribute('name');
			$expected = array_values(array_filter($actions, static fn($name) => in_array($name === 'copy' ? 'add' : $name, $rights, true)));
			check($visible === array_merge($expected, $expected), 'List action visibility differs from existing ACL: ' . $route . '/' . json_encode($rights) . ' expected ' . json_encode($expected) . ' got ' . json_encode($visible));
		}
		toolbarContext($module, $controller, 'index');
		$adapter->permissions[0]['userid'] = 99;
		foreach ($actions as $name) check((new $class())->renderElement($name) === '', 'Foreign-user permission enabled row action');
	} elseif ($module === 'admin') {
		$user = Zend_Registry::get('User');
		$user['admin'] = 0;
		Zend_Registry::set('User', $user);
		check(((new $class())->renderElement('copy') === '') === ((new $legacyClass())->renderElement('copy') === ''), 'Admin legacy render visibility changed; controller access remains separate');
	} else {
		check(!(new DEEC_Permission(['id' => 42, 'clientid' => 70]))->hasModule('tasks'), 'Tasks permission schema changed; review separately');
	}
}

foreach ([['contacts', 'email'], ['contacts', 'contactperson'], ['items', 'inventory'], ['items', 'itemlist'], ['items', 'stock'], ['items', 'ledger'], ['tasks', 'position'], ['admin', 'user'], ['admin', 'client'], ['admin', 'currency'], ['admin', 'taxrate'], ['admin', 'paymentmethod'], ['admin', 'media'], ['admin', 'page'], ['admin', 'export'], ['shops', 'category'], ['sales', 'quote'], ['purchases', 'purchaseorder'], ['processes', 'process']] as [$module, $controller]) foreach (['index', 'search'] as $action) {
	toolbarContext($module, $controller, $action);
	foreach (['Contacts', 'Items', 'Tasks', 'Admin'] as $prefix) {
		$class = $prefix . '_Form_ToolbarInline';
		$legacy = 'List' . $prefix . 'LegacyFixture';
		check((new $class())->getElements() === (new $legacy())->getElements(), 'Shared inline class changed special controller/module');
	}
}

// Row targets are added by the existing list after permission-aware rendering.
toolbarContext('contacts', 'contact', 'index');
$inline = new Contacts_Form_ToolbarInline();
$inline->setElementData('copy', ['attribs' => ['id' => 'fixture-copy', 'data-parent' => 'tasks|task|501', 'onclick' => 'fixture()']]);
$list = new Contacts_Model_List_Contacts(['module' => 'contacts', 'controller' => 'contact', 'toolbarInline' => $inline]);
$html = $list->renderCell(['id' => 18], ['type' => 'actions', 'elements' => [['name' => 'copy']]]);
$button = buttonDom($html)->getElementsByTagName('button')->item(0);
check($button->getAttribute('data-parent') === 'tasks|task|501' && $button->getAttribute('onclick') === 'fixture()' && $button->getAttribute('data-id') === '18', 'List injected row attributes removed caller attributes');
check((new Items_Form_ToolbarInline())->getElement('view') === null, 'Nonexistent item View action was invented');

echo $listBaseline;
echo 'PASS: ' . $checks . " list action scope, attributes, native links, actual models/layouts/AJAX, names, readonly and permissions checks\n";
