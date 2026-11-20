<?php

// Exercise original controllers, routing, ViewRenderer and MVC layout with read-only synthetic data.
if (!in_array(PHP_SAPI, ['cli', 'cli-server'], true)) { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);
define('BASE_PATH', dirname(__DIR__, 2));
define('APPLICATION_PATH', BASE_PATH . '/application');
define('APPLICATION_ENV', 'testing');
set_include_path(BASE_PATH . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { require_once $path; return; }
	$parts = explode('_', $class);
	if (count($parts) < 3) return;
	$module = array_shift($parts);
	$base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
	$path = BASE_PATH . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
	$fixturePath = (string)getenv('DEWAWI_ICON_TEST_AREA_FORMS') . substr($path, strlen(BASE_PATH));
	if (getenv('DEWAWI_ICON_TEST_AREA_FORMS') && (!getenv('DEWAWI_ICON_TEST_COMPARE_AREAS') || isset($_GET['fixture_legacy'])) && str_contains($path, '/forms/') && is_file($fixturePath)) $path = $fixturePath;
	if (is_file($path)) require_once $path;
});

class ButtonPageReadAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
	public array $queries = [];
	public array $rows = [];
	public bool $standardArea = false;
	public bool $processView = false;
	protected function _connect() {}
	protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
	public function describeTable($table, $schema = null) {
		$columns = ['id', 'clientid', 'userid', 'parentid', 'deleted', 'created', 'modified', 'ordering', 'title', 'name', 'language'];
		foreach ($this->rows[$table] ?? [] as $row) $columns = array_merge($columns, array_keys($row));
		$out = [];
		foreach (array_unique($columns) as $column) $out[$column] = ['COLUMN_NAME' => $column, 'DATA_TYPE' => 'varchar', 'PRIMARY' => $column === 'id', 'PRIMARY_POSITION' => 1, 'IDENTITY' => false];
		return $out;
	}
	public function query($sql, $bind = []) {
		$text = (string)$sql;
		if (!preg_match('/^\s*SELECT\b/i', $text)) throw new RuntimeException('Only synthetic SELECTs are permitted');
		$this->queries[] = $text;
		$table = '';
		if ($sql instanceof Zend_Db_Select) { $from = $sql->getPart(Zend_Db_Select::FROM); $table = reset($from)['tableName']; }
		$rows = is_string($table) ? ($this->rows[$table] ?? []) : [];
		if ($this->standardArea && $sql instanceof Zend_Db_Select) {
			$where = str_replace('`', '', implode(' ', $sql->getPart(Zend_Db_Select::WHERE)));
			$columns = $this->processView ? ['id', 'clientid', 'parentid', 'contactid', 'deleted'] : ['id', 'clientid'];
			foreach ($columns as $column) if (preg_match('/\b' . $column . '\s*=\s*\x27?(-?[0-9]+)/', $where, $match)) $rows = array_values(array_filter($rows, static fn($row) => (int)($row[$column] ?? 0) === (int)$match[1]));
			if ($this->processView && $table === 'processpos') usort($rows, static fn($a, $b) => [(int)$a['ordering'], (int)$a['id']] <=> [(int)$b['ordering'], (int)$b['id']]);
		}
		if (preg_match('/^\s*SELECT\s+COUNT\s*\(/i', $text)) $rows = [['records' => 25]];
		if (preg_match('/\bSUM\s*\(/i', $text)) $rows = [['total' => 0]];
		return new class($rows) {
			public function __construct(private array $rows) {}
			public function fetchAll($mode = null) { return $this->rows; }
			public function fetch($mode = null) { return $this->rows[0] ?? false; }
			public function fetchColumn($column = 0) { return isset($this->rows[0]) ? (array_values($this->rows[0])[$column] ?? false) : false; }
		};
	}
	public function beginTransaction() { throw new RuntimeException('Database writes are forbidden'); }
	public function insert($table, array $bind) { throw new RuntimeException('Database writes are forbidden'); }
	public function update($table, array $bind, $where = '') { throw new RuntimeException('Database writes are forbidden'); }
	public function delete($table, $where = '') { throw new RuntimeException('Database writes are forbidden'); }
}

class ButtonPageContextProbe extends Zend_Controller_Plugin_Abstract
{
	public function preDispatch(Zend_Controller_Request_Abstract $request) {
		if ($request->getModuleName() === 'default' && $request->getControllerName() === 'index' && getenv('DEWAWI_ICON_TEST_DASHBOARD_VIEWS')) {
			$renderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
			$renderer->view->addScriptPath(APPLICATION_PATH . '/views/scripts');
			$renderer->setViewBasePathSpec(getenv('DEWAWI_ICON_TEST_DASHBOARD_VIEWS'));
		}
	}
	public function postDispatch(Zend_Controller_Request_Abstract $request) {
		if ($request->getParam('fixture_contextless') !== '1' || $request->isXmlHttpRequest()) return;
		// Fault injection: presentation state must not decide availability of shared symbols.
		Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->view->action = '';
	}
}

if (PHP_SAPI === 'cli') {
	$_SERVER['REQUEST_URI'] = $argv[1] ?? '/contacts/contact';
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['HTTP_HOST'] = 'localhost';
	parse_str((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $_GET);
}
$path = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$prefix = str_starts_with($path, '/erp/') ? '/erp' : '';
$route = substr($path, strlen($prefix));
$standardArea = preg_match('#^/(processes/process|items/pricerule|admin/shop)(?:/(index|search|select|edit/id/[0-9]+|view/id/[0-9]+))?$#D', $route) === 1;
if (preg_match('#^/processes/process/view(?:/id/[^/]+)?$#D', $route)) $standardArea = true;
if (preg_match('#^/(?:css|js|images|library)/#', $route) || $route === '/favicon.ico') {
	$asset = realpath(BASE_PATH . $route);
	if (!$asset || !str_starts_with($asset, BASE_PATH . '/') || !in_array(pathinfo($asset, PATHINFO_EXTENSION), ['css', 'js', 'png', 'gif', 'svg', 'ico'], true)) { http_response_code(404); exit; }
	header('Content-Type: ' . (['css' => 'text/css', 'js' => 'text/javascript', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'gif' => 'image/gif', 'ico' => 'image/x-icon'][pathinfo($asset, PATHINFO_EXTENSION)]));
	readfile($asset);
	exit;
}
// Calendar polling is unrelated to this rendering test; keep it synthetic as well.
if ($route === '/calendar/index/reminders') { header('Content-Type: application/json'); echo '{"ok":true,"count":0,"reminders":[],"csrf_token":"fixture"}'; exit; }
if ((!$standardArea && !in_array($route, ['/', '/contacts/contact', '/contacts/contact/index', '/contacts/contact/select', '/contacts/contact/search', '/items/item', '/items/item/select', '/items/item/search', '/tasks/task', '/sales/quote'], true))
	|| ($_SERVER['REQUEST_METHOD'] === 'POST' && !str_ends_with($route, '/search'))) { http_response_code(405); exit; }

if ($standardArea && ($_GET['fixture_ajax'] ?? '') === '1') $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

require_once 'Zend/Application.php';
$application = new Zend_Application('testing', [
	'bootstrap' => ['path' => APPLICATION_PATH . '/Bootstrap.php', 'class' => 'Bootstrap'],
	'resources' => [
		'view' => ['helperPath' => ['Zend_View_Helper' => APPLICATION_PATH . '/views/helpers'], 'doctype' => 'HTML5'],
		'frontController' => ['controllerDirectory' => APPLICATION_PATH . '/controllers', 'moduleDirectory' => APPLICATION_PATH . '/modules'],
		'layout' => ['layoutPath' => getenv('DEWAWI_ICON_TEST_LAYOUTS') ?: APPLICATION_PATH . '/layouts/scripts'],
	],
]);
$bootstrap = $application->getBootstrap();
$bootstrap->bootstrap('Doctype')->bootstrap('ControllerHelpers')->bootstrap('layout');
Zend_Session::setOptions(['save_path' => getenv('DEWAWI_ICON_TEST_SESSIONS') ?: sys_get_temp_dir(), 'use_cookies' => false, 'cache_limiter' => '']);
$locale = ($_GET['fixture_locale'] ?? $_SERVER['HTTP_X_ICON_FIXTURE_LOCALE'] ?? '') === 'en_US' ? 'en_US' : 'de_DE';
Zend_Registry::set('Zend_Locale', new Zend_Locale($locale));
Zend_Registry::set('Zend_Currency', new Zend_Currency('EUR', $locale));
$tr = new DEEC_Translate($locale);
$tr->loadDir('default', BASE_PATH . '/languages/' . $locale . '/default');
Zend_Registry::set('DEEC_Translate', $tr);
$user = ['id' => 42, 'clientid' => 70, 'admin' => 0, 'name' => 'Synthetic Test User', 'email' => 'fixture@example.test'];
Zend_Registry::set('User', $user);
Zend_Registry::set('Client', ['id' => 70, 'parentid' => 0, 'modules' => ['contacts' => 70, 'items' => 70]]);
Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
Zend_Auth::getInstance()->getStorage()->write((object)$user);
$adapter = new ButtonPageReadAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
$adapter->rows['permission'] = [['id' => 1, 'userid' => 42, 'clientid' => 70, 'deleted' => 0, 'default' => json_encode(['index' => ['view']]), 'contacts' => json_encode(['contact' => ['view', 'add', 'edit', 'delete']]), 'items' => json_encode(['item' => ['view', 'add', 'edit', 'delete']]), 'calendar' => json_encode(['index' => ['view']]), 'sales' => json_encode(['quote' => ['view', 'add', 'edit']])]];
if ($route === '/tasks/task') {
	$client = Zend_Registry::get('Client');
	$client['modules']['tasks'] = 70;
	Zend_Registry::set('Client', $client);
	$adapter->rows['permission'][0]['tasks'] = json_encode(['task' => ['view', 'add', 'edit', 'delete']]);
	$adapter->rows['task'] = [['id' => 61, 'clientid' => 70, 'deleted' => 0, 'title' => 'Synthetic Task', 'state' => 100, 'priority' => 0, 'completed' => 0, 'cancelled' => 0, 'responsible' => 42]];
}
foreach (range(1, 25) as $id) $adapter->rows['contact'][] = ['id' => $id, 'clientid' => 70, 'deleted' => 0, 'contactid' => 10000 + $id, 'name1' => 'Synthetic Contact ' . $id, 'name2' => '', 'country' => 'DE', 'phones' => '', 'emails' => '', 'internets' => ''];
$adapter->rows['item'] = [['id' => 31, 'clientid' => 70, 'deleted' => 0, 'parentid' => 0, 'title' => 'Synthetic Item', 'description' => '', 'sku' => 'TEST-31', 'price' => '12.50', 'currency' => 'EUR']];
$adapter->rows['quote'] = [['id' => 51, 'clientid' => 70, 'deleted' => 0, 'contactid' => 1, 'title' => 'Synthetic Quote', 'state' => 100, 'billingname1' => 'Synthetic Contact', 'billingname2' => '', 'modified' => '2026-11-19 10:00:00']];
$adapter->rows['state'] = [['id' => 100, 'module' => 'sales', 'controller' => 'quote', 'state' => 100, 'title' => 'STATES_CREATED', 'clientid' => 70, 'deleted' => 0, 'ordering' => 0]];
if ($standardArea) {
	$adapter->standardArea = true;
	if (isset($_GET['fixture_ajax'])) $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
	// Keep attachment preparation out of these read-only rendering probes.
	require_once APPLICATION_PATH . '/controllers/helpers/Directory.php';
	Zend_Controller_Action_HelperBroker::addHelper(new class extends Application_Controller_Action_Helper_Directory {
		public function getName() { return 'Directory'; }
		public function isWritable($id, $type, $flashMessenger) {
			if ($this->getRequest()->getModuleName() === 'processes' && $this->getRequest()->getActionName() === 'view') throw new RuntimeException('Process views must not prepare attachment directories');
			return false;
		}
	});
	require_once APPLICATION_PATH . '/controllers/helpers/Access.php';
	Zend_Controller_Action_HelperBroker::addHelper(new class extends Application_Controller_Action_Helper_Access {
		public function getName() { return 'Access'; }
		public function lock($id, $userid, $locked = null, $lockedtime = null, $db = null) {
			if ($this->getRequest()->getModuleName() === 'processes' && $this->getRequest()->getActionName() === 'view') throw new RuntimeException('Process views must not acquire write locks');
			return null;
		}
	});
	$role = $_GET['fixture_role'] ?? $_SERVER['HTTP_X_BUTTON_FIXTURE_ROLE'] ?? 'full';
	$user['admin'] = str_starts_with($route, '/admin/') && $role !== 'denied' ? 1 : 0;
	Zend_Registry::set('User', $user);
	Zend_Auth::getInstance()->getStorage()->write((object)$user);
	$client = Zend_Registry::get('Client'); $client['modules']['processes'] = 70; Zend_Registry::set('Client', $client);
	if (in_array($_GET['fixture_tenant'] ?? '', ['foreign', 'shared'], true)) {
		$client['id'] = $_GET['fixture_tenant'] === 'foreign' ? 99 : 71;
		$client['modules']['processes'] = $client['modules']['items'] = $_GET['fixture_tenant'] === 'foreign' ? 99 : 70;
		Zend_Registry::set('Client', $client);
	}
	$rights = $role === 'denied' ? [] : ($role === 'readonly' ? ['view'] : ['view', 'add', 'edit', 'delete']);
	$adapter->rows['permission'][0]['processes'] = json_encode(['process' => $rights]);
	$adapter->rows['permission'][0]['items'] = json_encode(['item' => $rights, 'pricerule' => $rights]);
	$row = ['id' => 17, 'clientid' => 70, 'deleted' => 0, 'title' => 'Synthetic B18', 'state' => 100, 'contactid' => 0, 'processid' => null, 'total' => 0, 'currency' => 'EUR', 'deliverydate' => null, 'paymentdate' => null, 'paymentstatus' => '', 'deliverystatus' => '', 'completed' => 0, 'cancelled' => 0, 'locked' => 0, 'modified' => '2026-11-20 10:00:00', 'modifiedby' => 0];
	$adapter->rows['process'] = [$row, array_replace($row, ['id' => 18, 'state' => 105])];
	$adapter->rows['pricerule'] = [array_replace($row, ['amount' => 5, 'action' => 'bypercent', 'datefrom' => null, 'dateto' => null, 'priority' => 1, 'itemcatid' => 0, 'contactcatid' => 0, 'activated' => 1]), array_replace($row, ['id' => 18, 'amount' => 10, 'action' => 'byfixed'])];
	$adapter->rows['shop'] = [array_replace($row, ['url' => 'https://fixture.example.test', 'language' => $locale, 'timezone' => 'Europe/Berlin', 'activated' => 1]), array_replace($row, ['id' => 18])];
	foreach (['process', 'pricerule', 'shop'] as $table) {
		if (str_contains($route, '/edit/') || str_contains($route, '/view/')) $adapter->rows[$table] = [$adapter->rows[$table][0]];
		if (($_POST['fixture_rows'] ?? '') === 'remaining') $adapter->rows[$table] = array_values(array_filter($adapter->rows[$table], static fn($row) => $row['id'] === 18));
		if (($_POST['fixture_rows'] ?? '') === 'empty') $adapter->rows[$table] = [];
	}
}
if ($route === '/' && in_array($_GET['fixture_dashboard'] ?? '', ['all', 'no-contacts', 'no-items', 'view-only'], true)) {
	// Cover every dashboard group without enabling production modules or write routes.
	$role = $_GET['fixture_dashboard'];
	$adapter->rows['permission'][0]['default'] = json_encode(['index' => ['view', 'edit', 'add']]);
	$adapter->rows['permission'][0]['tasks'] = json_encode(['task' => ['view']]);
	$adapter->rows['permission'][0]['sales'] = json_encode(array_fill_keys(['quote', 'salesorder', 'invoice'], ['view']));
	$adapter->rows['permission'][0]['purchases'] = json_encode(array_fill_keys(['quoterequest', 'purchaseorder'], ['view']));
	if ($role === 'no-contacts') $adapter->rows['permission'][0]['contacts'] = json_encode(['contact' => []]);
	if ($role === 'no-items') $adapter->rows['permission'][0]['items'] = json_encode(['item' => []]);
	if ($role === 'view-only') foreach (['contacts' => 'contact', 'items' => 'item'] as $module => $controller) $adapter->rows['permission'][0][$module] = json_encode([$controller => ['view']]);
	$adapter->rows['ledger'] = [['id' => 41, 'clientid' => 70, 'deleted' => 0, 'itemid' => 31, 'ledgerdate' => '2026-11-19', 'comment' => 'Synthetic Movement']];
	$adapter->rows['task'] = [['id' => 61, 'clientid' => 70, 'deleted' => 0, 'title' => 'Synthetic Task', 'startdate' => null, 'duedate' => null, 'priority' => 0, 'responsible' => 0]];
	foreach (['salesorder', 'invoice', 'quoterequest', 'purchaseorder'] as $index => $table) $adapter->rows[$table] = [array_replace($adapter->rows['quote'][0], ['id' => 71 + $index, 'title' => 'Synthetic Document'])];
}
if (str_starts_with($route, '/processes/process')) {
	// Real database rows include nullable schema columns, unlike the old sparse fixture.
	preg_match('/CREATE TABLE IF NOT EXISTS `process` \((.*?)\) ENGINE/s', file_get_contents(BASE_PATH . '/install/config/structure.sql'), $schema);
	preg_match_all('/^  `([^`]+)`/m', $schema[1], $columns);
	$processDefaults = array_fill_keys($columns[1], null);
	foreach ($adapter->rows['process'] as &$processRow) $processRow += $processDefaults;
	unset($processRow);
	if (isset($_GET['fixture_process_view'])) require __DIR__ . '/../processes/fixtures/process-view.php';
}
$front = Zend_Controller_Front::getInstance();
$front->setParam('bootstrap', $bootstrap)->setParam('noErrorHandler', true)->throwExceptions(true)->returnResponse(true);
$front->setBaseUrl($prefix);
$front->registerPlugin(new Application_Plugin_Translate());
$front->registerPlugin(new Application_Plugin_Acl());
$front->registerPlugin(new Application_Plugin_ListResponse());
$front->registerPlugin(new ButtonPageContextProbe());
$front->registerPlugin(new Application_Plugin_SecurityHeaders());
$request = new Zend_Controller_Request_Http();
if (PHP_SAPI === 'cli') $request->setRequestUri($_SERVER['REQUEST_URI']);
$request->setBaseUrl($prefix);
$response = $front->dispatch($request, new Zend_Controller_Response_Http());
$response->setHeader('X-Button-Fixture-Queries', (string)count($adapter->queries), true)->sendResponse();
