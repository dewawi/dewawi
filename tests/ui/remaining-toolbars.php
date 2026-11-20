<?php

// Reuse the B5 baseline and real form/helper/layout fixture without application bootstrap.
ob_start();
$remainingArguments = $argv;
$argv = [$argv[0]];
require __DIR__ . '/toolbar-buttons.php';
$argv = $remainingArguments;
$remainingBaseline = ob_get_clean();
$checks = 0;

class AdminToolbarSubclassFixture extends Admin_Form_Toolbar {}
class StatisticsToolbarSubclassFixture extends Statistics_Form_Toolbar {}

function remainingContexts(): array
{
	global $root;
	$contexts = [['contacts', 'contact'], ['items', 'item'], ['tasks', 'task'], ['shops', 'category'], ['sales', 'quote']];
	foreach (['admin', 'statistics'] as $module) foreach (glob($root . '/application/modules/' . $module . '/controllers/*Controller.php') as $path) $contexts[] = [$module, strtolower(basename($path, 'Controller.php'))];
	return $contexts;
}

if (($argv[1] ?? '') === 'snapshot') {
	$snapshots = [];
	foreach (remainingContexts() as [$module, $controller]) foreach (['index', 'search', 'select', 'edit', 'view', 'add', 'copy', 'get', 'export', 'toolbar'] as $action) {
		toolbarContext($module, $controller, $action);
		foreach (['Admin_Form_Toolbar', 'AdminToolbarSubclassFixture', 'Admin_Form_ToolbarInline', 'Statistics_Form_Toolbar', 'StatisticsToolbarSubclassFixture'] as $class) $snapshots[$module . '/' . $controller . '/' . $action . '/' . $class] = (new $class())->getElements();
	}
	echo json_encode($snapshots, JSON_THROW_ON_ERROR);
	exit;
}

$adminControllers = ['country', 'uom', 'deliverytime', 'manufacturer', 'warehouse', 'shippingmethod', 'shop'];
$reportControllers = ['customer', 'quote', 'turnover'];
$standardActions = ['index', 'search', 'select', 'edit', 'view'];
$standardIcons = ['add' => 'add', 'edit' => 'edit', 'copy' => 'copy', 'delete' => 'delete', 'filter' => 'filter', 'reset' => 'reset', 'clear' => 'close'];
$changedContexts = 0;
foreach (remainingContexts() as [$module, $controller]) foreach (['index', 'search', 'select', 'edit', 'view', 'add', 'copy', 'get', 'export', 'toolbar'] as $action) {
	toolbarContext($module, $controller, $action);
	foreach (['Admin_Form_Toolbar' => 'AdminToolbarSubclassFixture', 'Statistics_Form_Toolbar' => 'StatisticsToolbarSubclassFixture'] as $class => $legacyClass) {
		$modern = new $class();
		$legacy = new $legacyClass();
		$enabled = $class === 'Admin_Form_Toolbar'
			? $module === 'admin' && in_array($controller, $adminControllers, true) && in_array($action, $standardActions, true)
			: $module === 'statistics' && in_array($controller, $reportControllers, true) && in_array($action, ['index', 'search'], true);
		$changedContexts += (int)$enabled;
		$icons = $class === 'Admin_Form_Toolbar' ? $standardIcons : ['filter' => 'filter', 'reset' => 'reset'];
		foreach ($legacy->getElements() as $name => $old) {
			check(!isset($old['iconFormat']), 'Subclass inherited presentation opt-in');
			$new = $modern->getElement($name);
			if (!$enabled || !isset($icons[$name])) {
				check($new === $old, 'Excluded context/field changed: ' . $module . '/' . $controller . '/' . $action . '/' . $name);
				continue;
			}
			$expected = array_replace($old, ['icon' => $name, 'iconFormat' => 'svg']);
			if ($name === 'clear') $expected = array_replace($expected, ['label' => 'CLEAR', 'iconOnly' => true]);
			check($new === $expected, 'Opt-in changed behavioral configuration');
		}
	}
	foreach ((new Admin_Form_ToolbarInline())->getElements() as $name => $element) check(isset($element['iconFormat']) === ($module === 'admin' && in_array($controller, $adminControllers, true) && in_array($action, ['index', 'search'], true) && $name === 'copy'), 'Admin table actions escaped their explicit scope');
}
check($changedContexts === 41, 'Unexpected number of enabled route-action contexts');

foreach (['de_DE', 'en_US'] as $locale) foreach (['admin' => $adminControllers, 'statistics' => $reportControllers] as $module => $controllers) foreach ($controllers as $controller) {
	$profiles = $module === 'admin' ? ['index', 'select', 'edit', 'view'] : ['index'];
	foreach ($profiles as $action) foreach (['layout', 'plain'] as $layoutName) {
		$page = toolbarPage($locale, $module . '/' . $controller, $action, $layoutName);
		$expectedCount = $module === 'statistics' ? 2 : ($action === 'index' ? 11 : ($action === 'edit' ? 2 : 1));
		check(substr_count($page['html'], 'class="dw-icon-symbols"') === 1 && substr_count($page['html'], 'dw-btn--control') === $expectedCount, 'Layout symbol count or presentation scope changed');
		$icons = $module === 'admin' ? $standardIcons : ['filter' => 'filter', 'reset' => 'reset'];
		$class = ucfirst($module) . '_Form_Toolbar';
		$legacyClass = $module === 'admin' ? 'AdminToolbarSubclassFixture' : 'StatisticsToolbarSubclassFixture';
		$modern = new $class();
		$legacy = new $legacyClass();
		foreach ($icons as $name => $symbol) {
			$a = buttonDom($modern->renderElementWithAttribs($name, ['data-id' => '17', 'data-parent' => 'fixture', 'data-autosave' => 'false', 'value' => 'unchanged', 'onclick' => 'fixture()']))->firstChild;
			$b = buttonDom($legacy->renderElementWithAttribs($name, ['data-id' => '17', 'data-parent' => 'fixture', 'data-autosave' => 'false', 'value' => 'unchanged', 'onclick' => 'fixture()']))->firstChild;
			$attrs = static function (DOMElement $node): array { $values = []; foreach ($node->attributes as $attribute) if ($attribute->name !== 'class') $values[$attribute->name] = $attribute->value; ksort($values); return $values; };
			check($attrs($a) === $attrs($b) && $a->getAttribute('type') === 'button', 'Native type, name/value, data attributes, event or accessible name changed');
			check($a->textContent === $b->textContent && !str_contains($a->getAttribute('aria-label'), 'TOOLBAR_'), 'Visible or accessible label changed');
			foreach (explode(' ', $b->getAttribute('class')) as $cssClass) check(in_array($cssClass, explode(' ', $a->getAttribute('class')), true), 'Legacy class lost');
			$svg = $a->getElementsByTagName('svg')->item(0);
			check($svg && $svg->getAttribute('aria-hidden') === 'true' && $svg->getAttribute('focusable') === 'false' && $svg->getElementsByTagName('use')->item(0)->getAttribute('href') === '#dw-icon-' . $symbol, 'SVG mapping or decoration changed');
			if ($name === 'clear') check($a->getAttribute('aria-label') === Zend_Registry::get('DEEC_Translate')->t('CLEAR') && $a->getAttribute('rel') === 'keyword', 'Icon-only field target/name incorrect');
		}
		$readonly = toolbarPage($locale, $module . '/' . $controller, $action, $layoutName, [], true);
		foreach (buttonDom($readonly['toolbar'] . $readonly['bottom'])->getElementsByTagName('button') as $button) check($button->hasAttribute('disabled'), 'Readonly control enabled');
	}
	foreach ($module === 'admin' ? ['index', 'select'] : ['index'] as $context) {
		$ajax = toolbarPage($locale, $module . '/' . $controller, 'search', 'layout', [], false, $context);
		check(array_keys($ajax) === ['content', 'toolbar'] && !str_contains($ajax['content'] . $ajax['toolbar'], '<symbol'), 'AJAX contract changed or symbols duplicated');
		check(substr_count($ajax['toolbar'], 'dw-btn--control') === ($module === 'statistics' ? 2 : ($context === 'index' ? 7 : 1)), 'AJAX toolbar scope incorrect');
		check(substr_count($ajax['content'], 'dw-btn--control') === ($module === 'admin' && $context === 'index' ? 4 : 0), 'AJAX footer context incorrect');
	}
}

// Compare fixture translations with the actual route plugin, without its optional user/DB branch.
require_once $root . '/application/plugins/Translate.php';
if (!defined('BASE_PATH')) define('BASE_PATH', $root);
$front = Zend_Controller_Front::getInstance();
$previousBootstrap = $front->getParam('bootstrap');
$front->setParam('bootstrap', new class {
	public function getResource($name) { return new Zend_View(); }
});
$previousUser = Zend_Registry::get('User');
foreach (['de_DE', 'en_US'] as $locale) foreach (['contacts/contact', 'items/item', 'tasks/task', 'admin/country', 'statistics/turnover'] as $context) {
	toolbarPage($locale, $context);
	$fixtureTranslator = Zend_Registry::get('DEEC_Translate');
	$translator = new DEEC_Translate($locale);
	$translator->loadDir('default', $root . '/languages/' . $locale . '/default');
	Zend_Registry::set('DEEC_Translate', $translator);
	Zend_Registry::getInstance()->offsetUnset('User');
	(new Application_Plugin_Translate())->routeShutdown($front->getRequest());
	foreach (['TOOLBAR_NEW', 'TOOLBAR_EDIT', 'TOOLBAR_COPY', 'TOOLBAR_FILTER', 'TOOLBAR_RESET', 'CLEAR', 'MENU_CONTACTS', 'MENU_ITEMS', 'MENU_STATISTICS', 'STATISTICS_TURNOVER_MONTHS'] as $key) check($fixtureTranslator->t($key) === $translator->t($key), 'Fixture translation differs from route plugin: ' . $locale . '/' . $context . '/' . $key);
	Zend_Registry::set('User', $previousUser);
}
$front->setParam('bootstrap', $previousBootstrap);

// Exercise the unchanged server-side ACL with an authenticated in-memory identity.
require_once $root . '/application/plugins/Acl.php';
class RemainingToolbarFlashFixture extends Zend_Controller_Action_Helper_Abstract
{
	public function getName() { return 'FlashMessenger'; }
	public function addMessage($message) { return $this; }
}
Zend_Controller_Action_HelperBroker::addHelper(new RemainingToolbarFlashFixture());
Zend_Auth::getInstance()->getStorage()->write((object)['id' => 42]);
foreach (['admin' => $adminControllers, 'statistics' => $reportControllers] as $module => $controllers) foreach ($controllers as $controller) foreach ($module === 'admin' ? $standardActions : ['index', 'search'] as $action) foreach ([false, true] as $allowed) {
	toolbarContext($module, $controller, $action);
	Zend_Registry::set('User', ['id' => 42, 'admin' => $module === 'admin' && $allowed ? 1 : 0, 'clientid' => 70]);
	$adapter->permissions[0]['statistics'] = json_encode([$controller => $allowed ? ['view'] : []]);
	$request = Zend_Controller_Front::getInstance()->getRequest();
	$response = new Zend_Controller_Response_Http();
	$acl = new Application_Plugin_Acl();
	$acl->setResponse($response);
	$acl->routeShutdown($request);
	check($response->getHttpResponseCode() === ($allowed ? 200 : 403), 'Admin/report ACL changed for ' . $controller . '/' . $action);
}
toolbarContext('admin', 'country', 'index', []);
Zend_Registry::set('User', ['id' => 42, 'admin' => 0, 'clientid' => 70]);
$form = new Admin_Form_Toolbar();
check($form->renderElementWithAttribs('edit', ['data-module' => 'contacts', 'data-controller' => 'contact']) === '', 'Presentation bypassed protected target permissions');
$adapter->permissions[0]['userid'] = 99;
$adapter->permissions[0]['contacts'] = json_encode(['contact' => ['edit']]);
check((new Admin_Form_Toolbar())->renderElementWithAttribs('edit', ['data-module' => 'contacts', 'data-controller' => 'contact']) === '', 'Foreign user permissions leaked into rendering');

echo $remainingBaseline;
echo 'PASS: ' . $checks . " remaining toolbar metadata, contexts, HTML, translations, layouts, AJAX, readonly and ACL checks\n";
