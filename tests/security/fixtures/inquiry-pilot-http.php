<?php

// Isolated test router; never boot the application or read its database configuration.
$socket = getenv('DEWAWI_INQUIRY_TEST_SOCKET');
if(!in_array(PHP_SAPI, ['cli', 'cli-server'], true) || !is_string($socket) || !str_starts_with($socket, '/tmp/dewawi-shop-inquiry-quote-') || !is_file(dirname($socket).'/server.pid')) {
	http_response_code(404);
	exit;
}
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
$root = dirname(__DIR__, 3);
define('BASE_PATH', dirname($socket).'/base');
set_include_path($root.'/library'.PATH_SEPARATOR.get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
	if($path = stream_resolve_include_path(str_replace('_', '/', $class).'.php')) { require_once $path; return; }
	$parts = explode('_', $class);
	if(count($parts) < 3) return;
	$module = array_shift($parts);
	$base = $module === 'Application' ? '/application/' : '/application/modules/'.strtolower($module).'/';
	$path = $root.$base.strtolower(array_shift($parts)).'s/'.implode('/', $parts).'.php';
	if(is_file($path)) require_once $path;
});
$adapter = new Zend_Db_Adapter_Pdo_Mysql(['host' => 'localhost', 'unix_socket' => $socket, 'username' => 'root', 'password' => '', 'dbname' => 'dewawi_shop_inquiry_quote_test', 'charset' => 'utf8mb4']);
Zend_Db_Table::setDefaultAdapter($adapter);
if(PHP_SAPI === 'cli') {
	$rows = $adapter->fetchAll("SELECT clientid, shopid, data FROM shopinquirydata WHERE data LIKE '%http-pilot@example.test%'");
	$catalogRows = $adapter->fetchAll("SELECT data FROM shopinquirydata WHERE data LIKE '%catalog-http@example.test%'");
	echo json_encode(['quotes' => (int)$adapter->fetchOne('SELECT COUNT(*) FROM quote'), 'inquiries' => array_map(static fn(array $row): array => json_decode($row['data'], true), $rows), 'scopes' => array_map(static fn(array $row): array => ['clientid' => (int)$row['clientid'], 'shopid' => (int)$row['shopid']], $rows), 'catalog_inquiries' => array_map(static fn(array $row): array => json_decode($row['data'], true), $catalogRows)]);
	exit;
}
Zend_Session::setOptions(['save_path' => dirname($socket).'/sessions', 'use_cookies' => true]);
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 120, 'clientid' => 100], [], ['inquiry', 'catalog']));
Zend_Registry::set('Zend_Locale', new Zend_Locale('de_DE'));
$translator = new DEEC_Translate('de_DE');
$translator->loadDir('default', $root.'/languages/de_DE/default');
$translator->loadDir('country', $root.'/languages/de_DE/country');
Zend_Registry::set('DEEC_Translate', $translator);
require_once $root.'/application/modules/shops/controllers/InquiryController.php';

class PilotHttpController extends Shops_InquiryController
{
	protected function initSiteLayout(): void {}
	protected function renderSiteError(int $status): void { $this->getResponse()->setHttpResponseCode($status)->setBody('Unavailable form'); }
	protected function consumeSubmissionLimit(string $operation, int $limit, int $window): array { return ['allowed' => true]; }
	protected function sendInquiryNotification(array $data): bool { return false; }
	protected function sendOfferNotification(array $data, string $pdfPath): bool { throw new RuntimeException('Real emails forbidden in HTTP test'); }
}

$request = new Zend_Controller_Request_Http();
$request->setModuleName('shops')->setControllerName('inquiry')->setActionName(str_starts_with($request->getPathInfo(), '/success') ? 'success' : 'index')->setParam('id', (int)($_GET['id'] ?? 865));
$response = new Zend_Controller_Response_Http();
$front = Zend_Controller_Front::getInstance();
$front->setRequest($request)->setResponse($response);
$front->addControllerDirectory($root.'/application/modules/shops/controllers', 'shops');
$front->getRouter()->addRoute('inquiry_success', new Zend_Controller_Router_Route_Static('success', ['module' => 'shops', 'controller' => 'inquiry', 'action' => 'success']));
$front->getRouter()->addRoute('shop_home', new Zend_Controller_Router_Route_Static('', ['module' => 'shops', 'controller' => 'index', 'action' => 'index']));
Zend_Controller_Action_HelperBroker::addPath($root.'/library/Zend/Layout/Controller/Action/Helper', 'Zend_Layout_Controller_Action_Helper');
Zend_Layout::startMvc(['layoutPath' => $root.'/application/layouts/scripts']);
Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;
Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->setExit(false);
$view = new Zend_View();
$view->addScriptPath($root.'/application/modules/shops/views/scripts');
$view->addHelperPath($root.'/application/views/helpers', 'Zend_View_Helper');
$view->addHelperPath($root.'/application/modules/shops/views/helpers', 'Zend_View_Helper');
Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->setView($view);
$controller = new PilotHttpController($request, $response);
$action = $request->getActionName();
$controller->{$action.'Action'}();
if(!$response->getBody() && !$response->isRedirect()) $response->setBody($view->render('inquiry/'.$action.'.phtml'));
$response->sendResponse();
