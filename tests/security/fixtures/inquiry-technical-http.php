<?php

$socket = getenv('DEWAWI_TECHNICAL_TEST_SOCKET');
if(PHP_SAPI !== 'cli-server' || !is_string($socket) || !preg_match('#^/tmp/dewawi-technical-test-[^/]+/mysql\.sock$#D', $socket) || !is_file(dirname($socket).'/inquiry-technical.json')) { http_response_code(404); exit; }
$root = dirname(__DIR__, 3);
$work = dirname($socket);
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
set_include_path($root.'/library'.PATH_SEPARATOR.get_include_path());
spl_autoload_register(static function(string $class) use($root): void {
	if($file = stream_resolve_include_path(str_replace('_', '/', $class).'.php')) { require_once $file; return; }
	$parts = explode('_', $class); if(count($parts) < 3) return;
	$module = array_shift($parts); $section = strtolower(array_shift($parts)).'s';
	$file = $root.($module === 'Application' ? '/application/' : '/application/modules/'.strtolower($module).'/').$section.'/'.implode('/', $parts).'.php';
	if(is_file($file)) require_once $file;
});
Zend_Session::start(['save_path' => $work]);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if($path === '/css/ui.css') { header('Content-Type: text/css'); readfile($root.'/css/ui.css'); return; }
if($path === '/browser') { header('Content-Type: text/html; charset=utf-8'); readfile($work.'/browser.html'); return; }
$db = Zend_Db::factory('Pdo_Mysql', ['host' => 'localhost', 'unix_socket' => $socket, 'username' => 'root', 'password' => '', 'dbname' => 'technical_fresh', 'charset' => 'utf8']);
Zend_Db_Table::setDefaultAdapter($db);
Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
$user = $db->fetchRow('SELECT * FROM user WHERE id = 10');
Zend_Registry::set('User', $user);
Zend_Registry::set('Client', $db->fetchRow('SELECT * FROM client WHERE id = 100'));
if(empty($_GET['guest'])) Zend_Auth::getInstance()->getStorage()->write((object)['id' => 10]);
if($path === '/state') {
	$state = []; foreach(['item','quote','quotepos','shopinquiryform','shopinquirydata','technicaldataset','itemtechnicalprofile'] as $table) $state[$table] = $db->fetchAll('SELECT * FROM '.$table.' ORDER BY id');
	header('Content-Type: application/json'); echo json_encode($state); return;
}
if(!preg_match('#^/contacts/inquiry/(select|view)/id/([0-9]+)$#D', $path, $route)) { http_response_code(404); return; }
require_once $root.'/application/modules/contacts/controllers/InquiryController.php';
class InquiryTechnicalHttpController extends Contacts_InquiryController
{
	public function init() { $this->view = new Zend_View(); }
}
$request = new Zend_Controller_Request_Http();
$request->setBaseUrl('')->setModuleName('contacts')->setControllerName('inquiry')->setActionName($route[1])->setParam('id', $route[2]);
$front = Zend_Controller_Front::getInstance(); $front->setRequest($request); $front->getRouter()->addDefaultRoutes();
$response = new Zend_Controller_Response_Http();
$controller = new InquiryTechnicalHttpController($request, $response);
$view = $controller->view; $view->setScriptPath($root.'/application/modules/contacts/views/scripts');
Zend_Registry::set('Zend_Translate', new Zend_Translate('array', require $root.'/languages/de_DE/contacts/inquiry.php', 'de'));
if($route[1] === 'select') { $controller->selectAction(); $html = $view->render('inquiry/select.phtml'); }
else {
	$v = (new Contacts_Service_InquiryTechnical())->open((int)$route[2]);
	$html = $view->partial('inquiry/detail.phtml', ['inquiry' => $v['inquiry']]);
	$html .= $view->partial('inquiry/technical-entry.phtml', ['inquiry' => $v['inquiry'], 'user' => $user]);
}
$response->sendHeaders();
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/css/ui.css"><body>'.$html.'</body></html>';
