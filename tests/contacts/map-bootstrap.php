<?php

$work = getenv('DEWAWI_CONTACT_MAP_TEST');
if (!in_array(PHP_SAPI, ['cli', 'cli-server'], true) || !$work || !preg_match('~^/tmp/dewawi-contact-map-[a-zA-Z0-9_-]+$~D', $work) || !is_dir($work)) { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
ini_set('session.save_path', $work);
$root = dirname(__DIR__, 2);
define('BASE_PATH', $work);
define('APPLICATION_PATH', $root.'/application');
set_include_path($root.'/library'.PATH_SEPARATOR.get_include_path());
spl_autoload_register(static function($class) use ($root): void {
	$path = stream_resolve_include_path(str_replace('_', '/', $class).'.php');
	if (!$path && preg_match('/^Application_(Model|Form|Plugin|Controller)_(.+)$/', $class, $parts)) $path = $root.'/application/'.strtolower($parts[1]).'s/'.str_replace('_', '/', $parts[2]).'.php';
	if (!$path && preg_match('/^(Contacts|Users|Admin)_(Model|Form|Service)_(.+)$/', $class, $parts)) $path = $root.'/application/modules/'.strtolower($parts[1]).'/'.strtolower($parts[2]).'s/'.str_replace('_', '/', $parts[3]).'.php';
	if (!$path && preg_match('/^Contacts_(.+Controller)$/', $class, $parts)) $path = $root.'/application/modules/contacts/controllers/'.$parts[1].'.php';
	if ($path && is_file($path)) require_once $path;
});
$db = Zend_Db::factory('Pdo_Mysql', ['host' => 'localhost', 'unix_socket' => $work.'/mysql.sock', 'username' => 'root', 'password' => '', 'dbname' => 'contact_map_test', 'charset' => 'utf8']);
Zend_Db_Table::setDefaultAdapter($db);
Zend_Session::start();
Zend_Controller_Action_HelperBroker::addPath($root.'/application/controllers/helpers', 'Application_Controller_Action_Helper_');
Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;
$translate = new DEEC_Translate('de_DE');
$translate->loadDir('default', $root.'/languages/de_DE/default');
$translate->loadDir('contacts', $root.'/languages/de_DE/contacts');
Zend_Registry::set('DEEC_Translate', $translate);
Zend_Registry::set('Zend_Locale', new Zend_Locale('de_DE'));
function mapActor(int $tenant = 1, bool $read = true, bool $guest = false): void {
	$owner = $tenant === 2 ? 1 : $tenant;
	$userId = $read ? $tenant : 4;
	Zend_Registry::set('User', ['id' => $userId, 'name' => 'Map fixture', 'email' => 'fixture@example.invalid', 'clientid' => $tenant, 'admin' => 0]);
	Zend_Registry::set('Client', ['id' => $owner, 'parentid' => $tenant === 2 ? 1 : 0, 'modules' => ['contacts' => $owner], 'country' => 'DE']);
	Zend_Registry::set('Config', ['timezone' => 'Europe/Berlin']);
	Zend_Auth::getInstance()->getStorage()->write((object)['id' => $userId, 'clientid' => $tenant]);
	if ($guest) Zend_Auth::getInstance()->clearIdentity();
}
mapActor();
$request = new Zend_Controller_Request_Http();
$request->setModuleName('contacts')->setControllerName('contact')->setActionName('map');
Zend_Controller_Front::getInstance()->setRequest($request);
function mapOptions(): array {
	$class = get_class(Zend_Controller_Action_HelperBroker::getStaticHelper('Options'));
	return (new $class())->getOptions(new Contacts_Form_Toolbar());
}
