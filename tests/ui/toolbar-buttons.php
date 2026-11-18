<?php

// Shared toolbar integration with real helpers/layouts and the existing memory-only adapter.
ob_start();
$toolbarArguments = $argv;
$argv = [$argv[0]];
require __DIR__ . '/buttons.php';
$argv = $toolbarArguments;
$toolbarBaseline = ob_get_clean();
$checks = 0;

class ToolbarButtonRequestFixture extends Zend_Controller_Request_Http
{
	public function isXmlHttpRequest() { return $this->getActionName() === 'search'; }
}

class ToolbarStatisticsLegacyFixture extends Statistics_Form_Toolbar {}

function toolbarContext(string $module, string $controller, string $action, array $rights = ['view', 'add', 'edit', 'delete']): void
{
	global $root, $adapter;
	$adapter->permissions = [['id' => 1, 'userid' => 42, 'clientid' => 70, 'deleted' => 0, 'contacts' => json_encode(['contact' => $rights]), 'items' => json_encode(['item' => $rights])]];
	if ($module === 'statistics') $adapter->permissions[0]['statistics'] = json_encode(['customer' => $rights, 'quote' => $rights, 'turnover' => $rights]);
	$request = new ToolbarButtonRequestFixture('http://localhost/fixture/' . $module . '/' . $controller . '/' . $action);
	$request->setBaseUrl('/fixture')->setModuleName($module)->setControllerName($controller)->setActionName($action);
	$front = Zend_Controller_Front::getInstance();
	$front->setRequest($request)->setBaseUrl('/fixture');
	$directories = ['default' => $root . '/application/controllers'];
	foreach (['contacts', 'items', 'tasks', 'admin', 'statistics'] as $name) $directories[$name] = $root . '/application/modules/' . $name . '/controllers';
	$front->setControllerDirectory($directories);
	$front->setRouter(new Zend_Controller_Router_Rewrite());
	$front->getRouter()->addDefaultRoutes();
}

function toolbarPage(string $locale, string $module, string $action = 'index', string $layoutName = 'layout', array $rights = ['view', 'add', 'edit', 'delete'], bool $readonly = false, string $contextAction = 'index', bool $legacyStatistics = false): array
{
	global $root;
	if (str_contains($module, '/')) [$module, $controller] = explode('/', $module, 2);
	else $controller = ['contacts' => 'contact', 'items' => 'item', 'tasks' => 'task'][$module];
	toolbarContext($module, $controller, $action, $rights);
	$user = Zend_Registry::get('User');
	$user['admin'] = $module === 'admin' ? 1 : 0;
	Zend_Registry::set('User', $user);
	$translator = new DEEC_Translate($locale);
	$translator->loadDir('default', $root . '/languages/' . $locale . '/default');
	foreach (glob($root . '/languages/' . $locale . '/*/common.php') as $common) $translator->load(basename(dirname($common)) . '_common', $common);
	$translator->loadDir($module, $root . '/languages/' . $locale . '/' . $module);
	Zend_Registry::set('DEEC_Translate', $translator);
	Zend_Registry::set('Zend_Locale', new Zend_Locale($locale));
	Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
	$class = ucfirst($module) . '_Form_Toolbar';
	if ($legacyStatistics && $module === 'statistics') $class = 'ToolbarStatisticsLegacyFixture';
	$toolbar = new $class();
	if ($module === 'tasks') $toolbar->setElementData('state', ['options' => $toolbar->getElement('states')['options']]);
	if ($readonly) $toolbar->setMode('readonly');
	$view = new Zend_View();
	$view->addHelperPath($root . '/application/views/helpers', 'Zend_View_Helper');
	if ($module === 'admin') $view->addHelperPath($root . '/application/modules/admin/views/helpers', 'Zend_View_Helper');
	$view->addScriptPath($root . '/application/views/scripts');
	foreach (['headTitle', 'headScript', 'headLink', 'headMeta'] as $helperName) $view->$helperName()->getContainer()->exchangeArray([]);
	$view->doctype('HTML5');
	$view->headScript()->appendFile('/fixture/toolbar-setup.js');
	$view->assign([
		'language' => $locale, 'module' => $module, 'controller' => $controller, 'action' => $action,
		'id' => 17, 'title' => $translator->t(['contacts' => 'CONTACTS', 'items' => 'ITEMS', 'tasks' => 'TASKS', 'admin' => 'ADMIN', 'statistics' => 'STATISTICS'][$module]),
		'toolbar' => $toolbar, 'options' => [], 'messages' => [],
		'user' => Zend_Registry::get('User') + ['name' => 'Fixture User'],
		'categories' => [1 => ['id' => 1, 'parentid' => 0, 'title' => 'Fixture category']], 'tags' => [1 => 'Fixture tag'],
	]);
	$list = new DEEC_List([
		'view' => $view, 'module' => $module, 'controller' => $controller, 'toolbar' => $toolbar,
		'items' => [['id' => 17, 'title' => 'Fixture A'], ['id' => 18, 'title' => 'Fixture B']],
		'columns' => [['name' => 'title', 'label' => 'TOOLBAR_NAME']], 'selectable' => true,
		'listFooter' => in_array($action, ['index', 'select', 'search'], true), 'context' => ['action' => $action === 'search' ? $contextAction : $action],
	]);
	$content = $list->render();
	if ($module === 'admin' && in_array($action, ['edit', 'view'], true)) {
		$formClass = 'Admin_Form_' . ucfirst($controller);
		$form = new $formClass();
		$field = in_array($controller, ['country', 'manufacturer'], true) ? 'name' : 'title';
		$form->setValue($field, 'Fixture record');
		if ($readonly || $action === 'view') $form->setMode('readonly');
		$content .= '<form id="admin-fields">' . $form->renderElement($field) . '</form>';
	}
	if ($module === 'statistics') {
		$view->addScriptPath($root . '/application/modules/statistics/views/scripts');
		$pixel = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aK1cAAAAASUVORK5CYII=';
		$view->assign([
			'customerList' => [], 'yearlySummaryTable' => $list, 'yearlyTables' => [],
			'yearlyTurnover' => [], 'monthlyTurnover' => [], 'categoryRows' => [],
			'yearlyCategoryRows' => [], 'yearlyComparisonRows' => [],
			'chartImages' => ['turnover' => $pixel, 'turnover-category' => $pixel],
		]);
		$list->setSelectable(false)->setListFooter(false);
		$content = $view->render($controller . '/index.phtml');
	}
	if ($module === 'tasks' && in_array($action, ['edit', 'view'], true)) {
		$taskForm = new Tasks_Form_Task();
		$taskForm->setElementData('responsible', ['options' => [42 => 'Fixture User', 43 => 'Fixture Assignee']]);
		$taskForm->setValues(['state' => '100', 'responsible' => '42']);
		if ($readonly || $action === 'view') $taskForm->setMode('readonly');
		$content .= '<form id="task-fields">' . $taskForm->renderElement('state') . $taskForm->renderElement('responsible') . '</form>';
	}
	if ($action === 'search') {
		Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->setView($view);
		Zend_Controller_Front::getInstance()->getRequest()->setParam('context_action', $contextAction);
		$response = new Zend_Controller_Response_Http();
		$response->setBody($content);
		$plugin = new Application_Plugin_ListResponse();
		$plugin->setResponse($response);
		$plugin->postDispatch(Zend_Controller_Front::getInstance()->getRequest());
		return json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
	}
	$top = $view->Toolbar();
	$bottom = $module === 'statistics' ? '' : $view->ToolbarBottom($toolbar, $action);
	$layout = new Zend_Layout(['layoutPath' => $root . '/application/layouts/scripts', 'layout' => $layoutName]);
	$layout->setView($view);
	$view->getHelper('layout')->setLayout($layout);
	$layout->content = $content;
	return ['html' => $layout->render(), 'content' => $content, 'toolbar' => $top, 'bottom' => $bottom, 'elements' => $toolbar->getElements()];
}

if (($argv[1] ?? '') === 'render') {
	echo json_encode(toolbarPage($argv[2] ?? 'de_DE', $argv[3] ?? 'contacts', $argv[4] ?? 'index', $argv[5] ?? 'layout', ['view', 'add', 'edit', 'delete'], ($argv[6] ?? '') === 'readonly', $argv[7] ?? 'index', ($argv[6] ?? '') === 'legacy-statistics'), JSON_THROW_ON_ERROR);
	exit;
}

if (($argv[1] ?? '') === 'snapshot') {
	buttonTranslations('de_DE');
	$snapshots = [];
	foreach (['contacts' => ['contact', 'email', 'contactperson'], 'items' => ['item', 'inventory', 'itemlist', 'stock', 'ledger'], 'shops' => ['category'], 'admin' => ['export']] as $module => $controllers) foreach ($controllers as $controller) foreach (['index', 'search', 'select', 'edit', 'view', 'import', 'export', 'map'] as $action) {
		toolbarContext($module, $controller, $action);
		foreach (['Contacts_Form_Toolbar', 'Items_Form_Toolbar', 'Items_Form_ToolbarStock', 'Items_Form_ToolbarMovements', 'Items_Form_ToolbarItemlist', 'Items_Form_ToolbarStockplanning'] as $class) $snapshots[$module . '/' . $controller . '/' . $action . '/' . $class] = (new $class())->getElements();
	}
	echo json_encode($snapshots, JSON_THROW_ON_ERROR);
	exit;
}

$icons = ['add' => 'add', 'edit' => 'edit', 'copy' => 'copy', 'filter' => 'filter', 'reset' => 'reset', 'clear' => 'close'];
foreach (['de_DE', 'en_US'] as $locale) foreach (['contacts', 'items'] as $module) {
	foreach (['index', 'select', 'edit', 'view'] as $action) foreach (['layout', 'plain'] as $layoutName) {
		$page = toolbarPage($locale, $module, $action, $layoutName);
		check(substr_count($page['html'], 'class="dw-icon-symbols"') === 1, 'Missing/duplicate full-document symbols');
		check(substr_count($page['html'], 'dw-btn--control') === ($action === 'index' ? 9 : 1), 'Toolbar migration escaped its bounded scope');
		foreach ($icons as $name => $symbol) {
			$config = $page['elements'][$name];
			check(($config['iconFormat'] ?? '') === 'svg', 'Standard control not explicitly migrated');
			$form = new DEEC_Form();
			$form->addElement($config);
			$button = buttonDom($form->renderElement($name))->getElementsByTagName('button')->item(0);
			check($button->getAttribute('type') === 'button' && $button->getAttribute('data-action') === $name, 'Native type or action changed');
			check($button->getElementsByTagName('use')->item(0)->getAttribute('href') === '#dw-icon-' . $symbol, 'Unexpected SVG mapping');
			if ($name === 'clear') check($button->getAttribute('aria-label') === Zend_Registry::get('DEEC_Translate')->t('CLEAR') && $button->getAttribute('rel') === 'keyword' && trim($button->textContent) === '', 'Clear lost translated accessible name or field target');
		}
		foreach (['delete', 'apply', 'tags'] as $name) if (isset($page['elements'][$name])) check(!isset($page['elements'][$name]['iconFormat']), 'A special action was implicitly migrated');
		$readonly = toolbarPage($locale, $module, $action, $layoutName, ['view', 'add', 'edit', 'delete'], true);
		foreach (buttonDom($readonly['toolbar'] . $readonly['bottom'])->getElementsByTagName('button') as $button) check($button->hasAttribute('disabled'), 'Readonly toolbar action enabled');
	}
	foreach ([['view'], []] as $rights) {
		$page = toolbarPage($locale, $module, 'index', 'layout', $rights);
		$top = buttonDom($page['toolbar']);
		foreach ($top->getElementsByTagName('button') as $button) check(!in_array($button->getAttribute('name'), ['add', 'edit', 'copy'], true), 'Toolbar bypassed action permissions');
	}
	$ajax = toolbarPage($locale, $module, 'search');
	check(array_keys($ajax) === ['content', 'toolbar'] && !str_contains($ajax['content'] . $ajax['toolbar'], '<symbol'), 'Search response changed or duplicated symbols');
	check(substr_count($ajax['toolbar'], 'dw-btn--control') === 6 && substr_count($ajax['content'], 'dw-btn--control') === 3, 'Dynamic top/bottom toolbar missing SVGs');
	$selectAjax = toolbarPage($locale, $module, 'search', 'plain', ['view', 'add', 'edit', 'delete'], false, 'select');
	check(substr_count($selectAjax['toolbar'], 'dw-btn--control') === 1 && !str_contains($selectAjax['content'], 'dw-btn--control'), 'Select AJAX changed its apply/footer context');
}

foreach (['Items_Form_ToolbarStock', 'Items_Form_ToolbarMovements', 'Items_Form_ToolbarItemlist', 'Items_Form_ToolbarStockplanning'] as $class) {
	toolbarContext('items', 'item', 'index');
	foreach ((new $class())->getElements() as $element) check(!isset($element['iconFormat']), 'Subclass inherited unintended SVG opt-in');
}
foreach ([['contacts', 'email'], ['contacts', 'contactperson'], ['shops', 'category'], ['admin', 'export'], ['items', 'inventory'], ['items', 'itemlist'], ['items', 'stock'], ['items', 'ledger']] as [$module, $controller]) {
	toolbarContext($module, $controller, 'index');
	foreach (['Contacts_Form_Toolbar', 'Items_Form_Toolbar'] as $class) foreach ((new $class())->getElements() as $element) check(!isset($element['iconFormat']), 'Shared class changed an excluded controller');
}
foreach (['import', 'export', 'map'] as $action) foreach (['contacts' => 'contact', 'items' => 'item'] as $module => $controller) {
	toolbarContext($module, $controller, $action);
	$class = ucfirst($module) . '_Form_Toolbar';
	foreach ((new $class())->getElements() as $element) check(!isset($element['iconFormat']), 'Excluded action migrated');
}
toolbarContext('contacts', 'contact', 'index');
foreach (['Application_Form_Toolbar', 'Contacts_Form_ToolbarInline', 'Items_Form_ToolbarInline'] as $class) foreach ((new $class())->getElements() as $element) check(!isset($element['iconFormat']), 'Dashboard or row toolbar was migrated');
echo $toolbarBaseline;
echo 'PASS: ' . $checks . " shared toolbar, layout, SVG, translation, permissions, readonly and exclusion checks\n";
