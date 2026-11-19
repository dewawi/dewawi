<?php

// Render real toolbar/list/layout components with the existing memory-only fixture.
ob_start();
$standardActionArguments = $argv;
$argv = [$argv[0]];
require __DIR__ . '/toolbar-buttons.php';
$argv = $standardActionArguments;
$standardActionBaseline = ob_get_clean();
$checks = 0;

function standardActionRoutes(): array {
	return ['contacts/contact', 'items/item', 'tasks/task', 'admin/country', 'admin/uom', 'admin/deliverytime', 'admin/manufacturer', 'admin/warehouse', 'admin/shippingmethod'];
}

function standardActionPages(string $locale): array {
	$pages = [];
	foreach (standardActionRoutes() as $route) {
		$pages[$route] = [];
		foreach (['index', 'edit', 'view'] as $action) $pages[$route][$action] = toolbarPage($locale, $route, $action, 'layout', ['view', 'add', 'edit', 'delete'], false, 'index', false, false, true);
		$pages[$route]['readonly'] = toolbarPage($locale, $route, 'index', 'layout', ['view', 'add', 'edit', 'delete'], true, 'index', false, false, true);
		$pages[$route]['denied'] = toolbarPage($locale, $route, 'index', 'layout', ['view', 'add', 'edit'], false, 'index', false, false, true);
		foreach (['search' => null, 'remaining' => [['id' => 18, 'title' => 'Fixture B']], 'empty' => []] as $name => $items) $pages[$route][$name] = toolbarPage($locale, $route, 'search', 'layout', ['view', 'add', 'edit', 'delete'], false, 'index', false, false, true, $items);
		if (str_starts_with($route, 'contacts/') || str_starts_with($route, 'items/')) $pages[$route]['select'] = toolbarPage($locale, $route, 'select', 'plain', ['view', 'add', 'edit', 'delete'], false, 'select', false, false, true);
	}
	return $pages;
}

if (($argv[1] ?? '') === 'fixtures') {
	echo json_encode(standardActionPages($argv[2] ?? 'de_DE'), JSON_THROW_ON_ERROR);
	exit;
}

class StandardContactsToolbarLegacy extends Contacts_Form_Toolbar {}
class StandardItemsToolbarLegacy extends Items_Form_Toolbar {}
class StandardTasksToolbarLegacy extends Tasks_Form_Toolbar {}
class StandardAdminToolbarLegacy extends Admin_Form_Toolbar {}
class StandardContactsInlineLegacy extends Contacts_Form_ToolbarInline {}
class StandardItemsInlineLegacy extends Items_Form_ToolbarInline {}
class StandardTasksInlineLegacy extends Tasks_Form_ToolbarInline {}

function standardActionAttributes(DOMElement $button): array {
	$values = [];
	foreach ($button->attributes as $attribute) if ($attribute->name !== 'class') $values[$attribute->name] = $attribute->value;
	ksort($values);
	return $values;
}

foreach (['de_DE', 'en_US'] as $locale) foreach (standardActionRoutes() as $route) {
	[$module, $controller] = explode('/', $route);
	$prefix = ucfirst($module);
	buttonTranslations($locale);
	foreach (['index', 'search', 'select', 'edit', 'view'] as $action) {
		toolbarContext($module, $controller, $action);
		$user = Zend_Registry::get('User'); $user['admin'] = $module === 'admin' ? 1 : 0; Zend_Registry::set('User', $user);
		$class = $prefix . '_Form_Toolbar'; $legacyClass = 'Standard' . $prefix . 'ToolbarLegacy';
		$form = new $class(); $legacy = new $legacyClass();
		check($form->getElement('delete') === array_replace($legacy->getElement('delete'), ['icon' => 'delete', 'iconFormat' => 'svg']), 'Delete changed more than presentation metadata');
		foreach (['delete'] as $name) {
			$extra = ['data-id' => '18', 'data-module' => $module, 'data-controller' => $controller, 'data-action' => 'delete', 'value' => '0', 'rel' => 'fixture', 'onclick' => 'fixture()', 'data-autosave' => 'false', 'aria-describedby' => 'fixture-description'];
			$a = buttonDom($form->renderElementWithAttribs($name, $extra))->firstChild; $b = buttonDom($legacy->renderElementWithAttribs($name, $extra))->firstChild;
			check($a instanceof DOMElement && $b instanceof DOMElement && standardActionAttributes($a) === standardActionAttributes($b), 'Delete lost a native, aria or event attribute');
			check(trim($a->textContent) === $form->translate('TOOLBAR_DELETE') && $a->textContent === $b->textContent, 'Visible Delete label changed');
			check($a->getElementsByTagName('use')->item(0)->getAttribute('href') === '#dw-icon-delete', 'Delete symbol missing');
			foreach (explode(' ', $b->getAttribute('class')) as $className) check(in_array($className, explode(' ', $a->getAttribute('class')), true), 'Delete removed an old class hook');
		}
		if (in_array($module, ['contacts', 'items'], true) && $action === 'index') check($form->renderElement('apply') === $legacy->renderElement('apply'), 'Index Apply changed its existing contract');
	}
	if ($module !== 'admin') foreach (['index', 'search'] as $action) {
		toolbarContext($module, $controller, $action);
		$class = $prefix . '_Form_ToolbarInline'; $legacyClass = 'Standard' . $prefix . 'InlineLegacy';
		$form = new $class(); $legacy = new $legacyClass();
		check($form->getElement('delete') === array_replace($legacy->getElement('delete'), ['label' => 'TOOLBAR_DELETE', 'icon' => 'delete', 'iconFormat' => 'svg', 'iconOnly' => true, 'size' => 'sm']), 'Inline Delete changed behavior metadata');
		$a = buttonDom($form->renderElement('delete'))->firstChild; $b = buttonDom($legacy->renderElement('delete'))->firstChild;
		$expected = standardActionAttributes($b); $expected['aria-label'] = $form->translate('TOOLBAR_DELETE');
		check(standardActionAttributes($a) === $expected && trim($a->textContent) === '', 'Inline Delete changed native attributes or lost its translated name');
		check(buttonDom($form->renderElementWithAttribs('delete', ['aria-label' => 'Explicit name']))->firstChild->getAttribute('aria-label') === 'Explicit name', 'Explicit accessible name changed');
	}
	$page = toolbarPage($locale, $route, 'index', 'layout', ['view', 'add', 'edit', 'delete'], false, 'index', false, false, true);
	foreach (['toolbar', 'bottom'] as $part) {
		$dom = buttonDom($page[$part]);
		foreach ($dom->getElementsByTagName('button') as $button) if ($button->getAttribute('name') === 'delete') check(trim($button->textContent) === Zend_Registry::get('DEEC_Translate')->t('TOOLBAR_DELETE') && $button->getElementsByTagName('svg')->length === 1, 'Head/footer Delete lost visible label or SVG');
	}
}

echo $standardActionBaseline;
echo 'PASS: ' . $checks . " standard Delete/Apply regression assertions\n";
