<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

ini_set('display_errors', '0');
ini_set('log_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED);
set_error_handler(static function(int $severity): bool {
	if (!(error_reporting() & $severity)) return false;
	throw new RuntimeException('Diagnostic runtime error');
});

$db = null;
try {
	$args = [];
	foreach (array_slice($argv, 1) as $argument) {
		if ($argument === '--help' && $argc === 2) {
			echo <<<'HELP'
Read-only contact geocoding diagnosis for client 100. No external API calls.

Usage:
  php application/cli/contact-geocoding-dry-run.php --client=100 --user=ID --config=/absolute/read-only.ini --environment=SECTION

Required: explicit client, active authorized user, INI path and section.
INI format: resources.db.adapter = pdo_mysql, resources.db.params.* (as in configs/database.sample.ini).
Use an explicitly approved snapshot/database and a SELECT-only database account.
The command uses a read-only repeatable-read transaction and rolls it back.
It never discovers or opens configs/database.ini automatically.

Optional map filters (same values as the contact toolbar):
  --keyword=TEXT --catid=ID --tagid=ID --country=CODE --role=CODE

JSON contains aggregate counts only. Roles overlap for contacts with multiple roles.
Postal completeness means street, postcode, city and configured two-letter country;
it does not prove deliverability. Countries without postcodes require manual review.
Existing valid coordinates are skipped even when timestamps suggest a review.
Reuse estimates require matching normalized addresses and consistent, dated coordinates;
provider/license and accuracy checks remain necessary before any future reuse.
Duplicate detection preserves punctuation and house numbers; no fuzzy matching.
Only alternative addresses of the selected contacts are inspected for reuse.
Filters must match the map to compare totals. No processing of other tenants.

HELP;
			exit(0);
		}
		if (!preg_match('/^--([a-z]+)=(.*)$/sD', $argument, $match) || isset($args[$match[1]]) || !in_array($match[1], ['client', 'user', 'config', 'environment', 'keyword', 'catid', 'tagid', 'country', 'role'], true)) throw new InvalidArgumentException('Invalid command arguments');
		$args[$match[1]] = $match[2];
	}
	foreach (['client', 'user', 'config', 'environment'] as $required) if (empty($args[$required])) throw new InvalidArgumentException('Missing command arguments');
	if ($args['client'] !== '100' || !ctype_digit($args['user']) || !filter_var($args['user'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) || $args['config'][0] !== '/' || !is_file($args['config'])) throw new InvalidArgumentException('Invalid diagnostic scope or configuration');

	define('BASE_PATH', dirname(__DIR__, 2));
	define('APPLICATION_PATH', BASE_PATH.'/application');
	set_include_path(BASE_PATH.'/library'.PATH_SEPARATOR.get_include_path());

	// Bootstrap only the required read paths; do not invoke the application or cron.
	spl_autoload_register(static function(string $class): void {
		$path = stream_resolve_include_path(str_replace('_', '/', $class).'.php');
		if (!$path && preg_match('/^Application_(Model|Form|Controller)_(.+)$/', $class, $parts)) $path = APPLICATION_PATH.'/'.strtolower($parts[1]).'s/'.str_replace('_', '/', $parts[2]).'.php';
		if (!$path && preg_match('/^(Contacts|Users)_(Model|Form)_(.+)$/', $class, $parts)) $path = APPLICATION_PATH.'/modules/'.strtolower($parts[1]).'/'.strtolower($parts[2]).'s/'.str_replace('_', '/', $parts[3]).'.php';
		if ($path && is_file($path)) require_once $path;
	});
	$config = (new Zend_Config_Ini($args['config'], $args['environment']))->resources->db->toArray();
	if (strtolower($config['adapter'] ?? '') !== 'pdo_mysql') throw new InvalidArgumentException('Unsupported diagnostic adapter');
	$params = array_intersect_key($config['params'] ?? [], array_flip(['host', 'port', 'unix_socket', 'username', 'password', 'dbname', 'charset']));
	$db = Zend_Db::factory('Pdo_Mysql', $params);
	Zend_Db_Table::setDefaultAdapter($db);
	$db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
	$db->beginTransaction();

	$user = (new Users_Model_DbTable_User())->getActiveUserById((int)$args['user']);
	if (!$user) throw new RuntimeException('Inactive diagnostic user', 403);
	$user['clientid'] = 100;
	Zend_Registry::set('User', $user);
	$clients = new Application_Model_DbTable_Client();
	if (!array_key_exists(100, $clients->getSwitchableClients())) throw new RuntimeException('Diagnostic client access denied', 403);
	$client = $clients->getClient();
	if ($client['parentid']) $client['modules'] = ['contacts' => (int)$client['parentid']];
	Zend_Registry::set('Client', $client);
	$auth = Zend_Auth::getInstance();
	$auth->setStorage(new Zend_Auth_Storage_NonPersistent());
	$auth->getStorage()->write((object)['id' => $user['id'], 'clientid' => 100]);
	$model = new Contacts_Model_GeocodingDiagnostic();
	$model->authorize();

	$translate = new DEEC_Translate('de_DE');
	$translate->loadDir('default', BASE_PATH.'/languages/de_DE/default');
	$translate->loadDir('contacts', BASE_PATH.'/languages/de_DE/contacts');
	Zend_Registry::set('DEEC_Translate', $translate);
	Zend_Registry::set('Zend_Locale', new Zend_Locale('de_DE'));
	$request = new Zend_Controller_Request_Simple('map', 'contact', 'contacts');
	Zend_Controller_Front::getInstance()->setRequest($request);
	require_once APPLICATION_PATH.'/controllers/helpers/Options.php';
	$options = (new Application_Controller_Action_Helper_Options())->getOptions(new Contacts_Form_Toolbar());
	$filters = array_intersect_key($args, Contacts_Model_Map::FILTERS);
	$result = $model->diagnose(100, $filters, $options);
	$db->rollBack();
	echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
	if ($db && $db->isConnected()) {
		try { $db->rollBack(); } catch (Throwable $rollbackError) {}
	}
	// Exception messages can contain SQL, credentials or postal data.
	fwrite(STDERR, "Diagnosis failed. Check explicit arguments, configuration, database access and user permissions. No results emitted.\n");
	exit(1);
}
