<?php

// This router only serves the disposable recovery regression database.
$socket = getenv('DEWAWI_REVIEW_TEST_SOCKET') ?: '';
$work = realpath(dirname($socket));
if (PHP_SAPI !== 'cli-server' || !$work || !preg_match('~^/tmp/dewawi-document-recovery-db-[a-zA-Z0-9_]+$~D', $work) || $socket !== $work . '/mysql.sock') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
define('BASE_PATH', $work . '/source');
if (!is_dir($work . '/sessions')) mkdir($work . '/sessions', 0700);
session_save_path($work . '/sessions');
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
	if ($file = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { require_once $file; return; }
	$parts = explode('_', $class);
	if (count($parts) < 3) return;
	$module = array_shift($parts);
	$directory = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
	$file = $root . $directory . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
	if (is_file($file)) require_once $file;
});
$reviewAction = ($_GET['action'] ?? '') === 'originalreview';
$db = Zend_Db::factory('Pdo_Mysql', ['unix_socket' => $socket, 'host' => 'localhost', 'dbname' => 'recovery_fixture', 'username' => $reviewAction ? 'recovery_archiver' : 'recovery_reader', 'password' => 'fixture-only', 'charset' => 'utf8mb4']);
Zend_Db_Table::setDefaultAdapter($db);
$user = $db->fetchRow('SELECT * FROM user WHERE id = ? AND activated = 1 AND deleted = 0', [(int)($_SERVER['HTTP_X_REVIEW_TEST_USER'] ?? 0)]);
if (!$user) { http_response_code(401); exit; }
$client = $db->fetchRow('SELECT * FROM client WHERE id = ?', [$user['clientid']]);
$client['modules'] = ['contacts' => (int)($client['parentid'] ?: $client['id']), 'sales' => (int)$client['id'], 'purchases' => (int)$client['id']];
Zend_Registry::set('User', $user); Zend_Registry::set('Client', $client);
class RecoveryDownloadFixtureController extends DEEC_Controller_DocumentAction {
	public function init() { $this->view = new RecoveryDocumentViewFixture(); }
	protected function disableView(): void {}
}
class RecoveryDocumentViewFixture extends Zend_View {
	public function translate($key) { static $texts; $locale = ($_GET['locale'] ?? '') === 'en_US' ? 'en_US' : 'de_DE'; $texts ??= require __DIR__ . '/../../languages/' . $locale . '/default/document.php'; return $texts[$key] ?? $key; }
	public function url($options = [], $name = null, $reset = false, $encode = true) { return '/?' . http_build_query($options); }
}
$request = new Zend_Controller_Request_Http();
$request->setModuleName((string)($_GET['module'] ?? 'sales'))->setControllerName((string)($_GET['controller'] ?? 'quote'))->setActionName($reviewAction ? 'originalreview' : 'reviewdownload')->setParam('id', $_GET['id'] ?? 1)->setParam('sha256', $_GET['sha256'] ?? '')->setParam('mode', $_GET['mode'] ?? 'download');
Zend_Controller_Front::getInstance()->setControllerDirectory(['default' => $root . '/application/controllers']);
Zend_Controller_Front::getInstance()->getRouter()->addDefaultRoutes();
if (isset($_GET['view'])) {
	$context = (new DEEC_Permission($user))->getObjectContext($request->getParam('id'), $request->getModuleName(), $request->getControllerName());
	if (!$context) { http_response_code(404); exit; }
	$view = new RecoveryDocumentViewFixture();
	$view->module = $request->getModuleName(); $view->controller = $request->getControllerName(); $view->{$view->controller} = $context['parent'];
	require_once $root . '/application/views/helpers/Document.php';
	$helper = new Zend_View_Helper_Document(); $helper->setView($view);
	echo $helper->Document(); exit;
}
$controller = new RecoveryDownloadFixtureController($request, new Zend_Controller_Response_Http());
if ($reviewAction) $controller->originalreviewAction(); else $controller->reviewdownloadAction();
$controller->getResponse()->sendResponse();
