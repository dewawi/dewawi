<?php

// Exercise the shared helper and real forms/layouts with memory-only data.
ob_start();
$filterArguments = $argv;
$argv = [$argv[0]];
require __DIR__ . '/toolbar-buttons.php';
$argv = $filterArguments;
$filterBaseline = ob_get_clean();
$checks = 0;

class FilterLegacyHelperFixture extends Zend_View_Helper_Toolbar
{
	protected function usesSvgFilters($toolbar): bool { return false; }
}
class FilterContactsSubclassFixture extends Contacts_Form_Toolbar {}
class FilterItemsSubclassFixture extends Items_Form_Toolbar {}
class FilterTasksSubclassFixture extends Tasks_Form_Toolbar {}
class FilterAdminSubclassFixture extends Admin_Form_Toolbar {}
class FilterStatisticsSubclassFixture extends Statistics_Form_Toolbar {}
class FilterInquirySubclassFixture extends Contacts_Form_InquiryToolbar {}

function filterGroups(): array
{
	$groups = ['contacts/inquiry', 'contacts/contact', 'items/item', 'tasks/task'];
	foreach (['country', 'uom', 'deliverytime', 'manufacturer', 'warehouse', 'shippingmethod'] as $c) $groups[] = 'admin/' . $c;
	foreach (['customer', 'quote', 'turnover'] as $c) $groups[] = 'statistics/' . $c;
	return $groups;
}

function filterFixture(string $route, string $state): array
{
	if ($state === 'clear') return [];
	if ($state === 'multi') return ['values' => ['states' => ['100', '105']], 'options' => ['states' => ['100' => 'STATES_CREATED', '105' => 'STATES_COMPLETED']]];
	if ($state === 'date') return ['values' => ['daterange' => 'custom', 'from' => '2026-02-01', 'to' => '2026-02-28']];
	if ($state === 'long') {
		$text = str_repeat('LongUnbrokenFixtureLabel', 6) . ' & <unsafe> "quoted"';
		return str_starts_with($route, 'admin/') || $route === 'contacts/inquiry'
			? ['values' => ['sort' => 'ASC'], 'options' => ['sort' => ['ASC' => $text, 'DESC' => 'TOOLBAR_DESC']]]
			: ['values' => ['country' => 'DE'], 'options' => ['country' => ['0' => 'TOOLBAR_ALL_COUNTRIES', 'DE' => $text]]];
	}
	if (str_starts_with($route, 'statistics/')) return ['values' => ['country' => 'DE'], 'options' => ['country' => ['0' => 'TOOLBAR_ALL_COUNTRIES', 'DE' => 'Fixture & Country']]];
	return ['values' => ['sort' => 'ASC'], 'options' => ['sort' => ['ASC' => 'TOOLBAR_ASC', 'DESC' => 'TOOLBAR_DESC']]];
}

function filterPage(string $locale, string $route, string $action = 'index', string $layout = 'layout', string $context = 'index', string $state = 'active'): array
{
	return toolbarPage($locale, $route, $action, $layout, $state === 'view-only' ? ['view'] : ['view', 'add', 'edit', 'delete'], $state === 'readonly', $context, false, false, false, null, filterFixture($route, $state));
}

if (($argv[1] ?? '') === 'render') {
	echo json_encode(filterPage($argv[2], $argv[3], $argv[4], $argv[5], $argv[6], $argv[7]), JSON_THROW_ON_ERROR);
	exit;
}

if (($argv[1] ?? '') === 'fixtures') {
	$out = [];
	foreach (array_merge(filterGroups(), ['contacts/email', 'items/inventory', 'admin/paymentmethod']) as $route) {
		$out[$route] = ['page' => filterPage($argv[2], $route), 'long' => filterPage($argv[2], $route, 'index', 'layout', 'index', 'long')];
		if (($argv[3] ?? '') === 'audit' && in_array($route, filterGroups(), true) && $route !== 'contacts/inquiry' && !str_starts_with($route, 'statistics/')) {
			foreach (['index', 'select', 'edit', 'view'] as $action) {
				$layout = $action === 'select' ? 'plain' : 'layout';
				$out[$route]['audit'][$action] = toolbarPage($argv[2], $route, $action, $layout, ['view', 'add', 'edit', 'delete'], false, 'index', false, false, in_array($action, ['index', 'select'], true), null, filterFixture($route, 'active'));
				if (in_array($action, ['index', 'select'], true)) $out[$route]['auditSearch'][$action] = toolbarPage($argv[2], $route, 'search', $layout, ['view', 'add', 'edit', 'delete'], false, $action, false, false, true, null, filterFixture($route, 'clear'));
			}
		}
		if ($route === 'tasks/task') foreach (['multi', 'date'] as $state) $out[$route][$state] = filterPage($argv[2], $route, 'index', 'layout', 'index', $state);
		if (in_array($route, ['contacts/email', 'admin/paymentmethod'], true)) $out[$route]['legacy-select'] = filterPage($argv[2], $route, 'select', 'plain');
		foreach (['active', 'clear'] as $state) $out[$route]['search'][$state] = filterPage($argv[2], $route, 'search', 'layout', 'index', $state);
		if (in_array($route, ['contacts/contact', 'items/item', 'tasks/task', 'admin/country'], true)) {
			$out[$route]['select'] = filterPage($argv[2], $route, 'select', 'plain');
			foreach (['active', 'clear'] as $state) $out[$route]['selectSearch'][$state] = filterPage($argv[2], $route, 'search', 'plain', 'select', $state);
		}
	}
	echo json_encode($out, JSON_THROW_ON_ERROR);
	exit;
}

foreach (['de_DE', 'en_US'] as $locale) foreach (filterGroups() as $route) {
	$select = !str_starts_with($route, 'statistics/') && $route !== 'contacts/inquiry';
	foreach ($select ? ['index', 'select'] : ['index'] as $action) foreach (['layout', 'plain'] as $layout) {
		$page = filterPage($locale, $route, $action, $layout);
		$dom = buttonDom($page['html']);
		$xp = new DOMXPath($dom->ownerDocument);
		$chips = $xp->query('//button[@data-action="clear-filter"]');
		check($chips->length === 1, 'Active scalar filter missing/duplicated: ' . $route);
		$chip = $chips->item(0);
		check($chip->getAttribute('type') === 'button' && !$chip->hasAttribute('name') && !$chip->hasAttribute('value') && !$chip->hasAttribute('href'), 'Chip native contract changed');
		check($chip->getAttribute('data-filter') === (str_starts_with($route, 'statistics/') ? 'country' : 'sort'), 'Chip targets another field');
		check(str_contains($chip->getAttribute('class'), 'dw-btn--control') && str_contains($chip->getAttribute('class'), 'dw-filter-chip') && str_contains($chip->getAttribute('class'), 'dw-btn--sm'), 'Chip bypassed shared presentation or lost old class');
		check($chip->getElementsByTagName('use')->item(0)->getAttribute('href') === '#dw-icon-close' && $chip->getElementsByTagName('svg')->item(0)->getAttribute('aria-hidden') === 'true', 'Wrong/decoratively exposed icon');
		check($chip->getAttribute('aria-label') === Zend_Registry::get('DEEC_Translate')->t('TOOLBAR_CLEAR_FILTER', [trim($chip->textContent)]) && !str_contains($chip->getAttribute('aria-label'), 'TOOLBAR_'), 'Untranslated/ambiguous chip name');
		$panel = $xp->query('//*[@data-role="filter-panel"]')->item(0);
		check($panel && $panel->getAttribute('id') === 'dw-toolbar-filters-' . str_replace('/', '-', $route), 'Missing scoped panel ID');
		foreach ($xp->query('//button[@data-action="filter"]') as $button) check($button->getAttribute('aria-controls') === $panel->getAttribute('id') && $button->getAttribute('aria-expanded') === 'false', 'Filter disclosure contract missing');
		check(substr_count($page['html'], 'class="dw-icon-symbols"') === 1, 'Symbols missing/duplicated');
		$ajax = filterPage($locale, $route, 'search', $layout, $action);
		check(array_keys($ajax) === ['content', 'toolbar'] && substr_count($ajax['toolbar'], 'dw-filter-chip') === 1 && !str_contains($ajax['content'] . $ajax['toolbar'], '<symbol'), 'AJAX chip/context/symbol contract changed');
		$clear = filterPage($locale, $route, 'search', $layout, $action, 'clear');
		check(!str_contains($clear['toolbar'], 'dw-filter-chip'), 'Default/cleared filter still produces chip');
	}
	foreach (['readonly', 'view-only'] as $state) {
		$page = filterPage($locale, $route, 'index', 'layout', 'index', $state);
		check(substr_count($page['toolbar'], 'dw-filter-chip') === 1, 'Read-only/view-only filtering behavior changed');
	}
}

foreach (['de_DE', 'en_US'] as $locale) {
	foreach (['multi', 'date', 'long'] as $state) {
		$page = filterPage($locale, 'tasks/task', 'index', 'layout', 'index', $state);
		$xp = new DOMXPath(buttonDom($page['html'])->ownerDocument);
		$chip = $xp->query('//button[@data-action="clear-filter"]')->item(0);
		check($chip && !$chip->getElementsByTagName('unsafe')->length && !str_contains($chip->getAttribute('aria-label'), 'STATES_'), 'Array/custom-date/escaped label lost safe translated name');
		if ($state === 'date') check($chip->getAttribute('data-filter') === 'daterange' && str_contains($chip->textContent, '2026'), 'Custom date lost parent filter semantics');
		if ($state === 'long') check(str_contains($chip->textContent, '& <unsafe> "quoted"') && !str_contains($chip->textContent, '&amp;'), 'Chip text escaped zero/two times');
	}
}

foreach ([['contacts', 'email'], ['contacts', 'contactperson'], ['items', 'inventory'], ['items', 'itemlist'], ['items', 'ledger'], ['tasks', 'position'], ['admin', 'user'], ['admin', 'paymentmethod'], ['admin', 'page'], ['admin', 'export'], ['sales', 'quote'], ['purchases', 'purchaseorder'], ['processes', 'process'], ['shops', 'category']] as [$module, $controller]) {
	toolbarContext($module, $controller, 'index');
	foreach (['Contacts', 'Items', 'Tasks', 'Admin', 'Statistics'] as $prefix) {
		$class = $prefix . '_Form_Toolbar';
		$form = new $class();
		$form->setElementData('filter', ['icon' => 'filter', 'iconFormat' => 'svg']);
		$form->setValue('country', 'DE');
		$form->setValue('sort', 'ASC');
		$view = new Zend_View();
		$view->addHelperPath($root . '/application/views/helpers', 'Zend_View_Helper');
		$view->assign(['module' => $module, 'controller' => $controller, 'action' => 'index', 'toolbar' => $form]);
		check((new Zend_View_Helper_Toolbar())->setView($view)->toolbar() === (new FilterLegacyHelperFixture())->setView($view)->toolbar(), 'Excluded context changed despite SVG metadata: ' . $module . '/' . $controller);
	}
}

foreach (['contacts/contact' => 'Contacts', 'items/item' => 'Items', 'tasks/task' => 'Tasks', 'admin/country' => 'Admin', 'statistics/customer' => 'Statistics', 'contacts/inquiry' => 'Inquiry'] as $route => $prefix) {
	[$module, $controller] = explode('/', $route);
	toolbarContext($module, $controller, 'index');
	$class = 'Filter' . $prefix . 'SubclassFixture';
	$form = new $class();
	$form->setElementData('filter', ['icon' => 'filter', 'iconFormat' => 'svg']);
	$form->setValue('country', 'DE');
	$form->setValue('sort', 'ASC');
	$view = new Zend_View();
	$view->addHelperPath($root . '/application/views/helpers', 'Zend_View_Helper');
	$view->assign(['module' => $module, 'controller' => $controller, 'action' => 'index', 'toolbar' => $form]);
	check((new Zend_View_Helper_Toolbar())->setView($view)->toolbar() === (new FilterLegacyHelperFixture())->setView($view)->toolbar(), 'Subclass inherited filter presentation');
}

echo $filterBaseline;
echo 'PASS: ' . $checks . " standard filter chips, disclosure, translations, native attributes, layouts, AJAX, escaping and exclusions checks\n";
