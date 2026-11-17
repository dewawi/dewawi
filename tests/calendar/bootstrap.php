<?php

// This fixture accepts only the disposable socket created by run.py.
$work = getenv('DEWAWI_CALENDAR_TEST');
if (!in_array(PHP_SAPI, ['cli', 'cli-server'], true) || !$work || !preg_match('#^/tmp/dewawi-calendar-[a-zA-Z0-9_]+$#D', $work) || !is_dir($work)) { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');
ini_set('session.save_path', $work);
$root = dirname(__DIR__, 2);
define('BASE_PATH', $work);
define('APPLICATION_PATH', $root . '/application');
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function ($class) use ($root) {
	$path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php');
	if (!$path && preg_match('/^Application_(Model|Form|Plugin|Controller)_(.+)$/', $class, $parts)) $path = $root . '/application/' . strtolower($parts[1]) . 's/' . str_replace('_', '/', $parts[2]) . '.php';
	if (!$path && preg_match('/^(Calendar|Admin|Contacts)_(Model|Form)_(.+)$/', $class, $parts)) $path = $root . '/application/modules/' . strtolower($parts[1]) . '/' . strtolower($parts[2]) . 's/' . str_replace('_', '/', $parts[3]) . '.php';
	if (!$path && preg_match('/^Calendar_(.+Controller)$/', $class, $parts)) $path = $root . '/application/modules/calendar/controllers/' . $parts[1] . '.php';
	if ($path && is_file($path)) require_once $path;
});
$db = Zend_Db::factory('Pdo_Mysql', ['host' => 'localhost', 'unix_socket' => $work . '/mysql.sock', 'username' => 'root', 'password' => '', 'dbname' => 'calendar_test', 'charset' => 'utf8']);
Zend_Db_Table::setDefaultAdapter($db);
Zend_Session::start();
Zend_Controller_Action_HelperBroker::addPath($root . '/application/controllers/helpers', 'Application_Controller_Action_Helper_');
if (is_file($work . '/smtp.json')) {
	require_once $root . '/application/controllers/helpers/Email.php';
	class CalendarFixtureEmail extends Application_Controller_Action_Helper_Email {
		public function getName() { return 'Email'; }
		public function getEmailConfig($module): array {
			global $work;
			$config = json_decode(file_get_contents($work . '/smtp.json'), true);
			if (!empty($config['disabled'])) throw new RuntimeException('Fixture SMTP configuration unavailable');
			$port = $config['port'];
			return [['host' => '127.0.0.1', 'port' => $port, 'auth' => false, 'secure' => '', 'timeout' => 3], 'organizer@example.invalid', 'Calendar Organizer'];
		}
	}
	Zend_Controller_Action_HelperBroker::addHelper(new CalendarFixtureEmail());
}
function calendarActor(int $id = 1, int $client = 1): void {
	Zend_Auth::getInstance()->getStorage()->write((object)['id' => $id, 'clientid' => $client]);
	Zend_Registry::set('User', ['id' => $id, 'name' => 'Member ' . $id, 'clientid' => $client, 'admin' => 0]);
	Zend_Registry::set('Client', ['id' => $client, 'parentid' => $client === 2 ? 1 : 0, 'modules' => ['contacts' => 1]]);
	Zend_Registry::set('Config', ['timezone' => 'Europe/Berlin']);
}
calendarActor();
