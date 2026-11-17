<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
ini_set('log_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED);
set_error_handler(static function(int $severity): bool {
	if (!(error_reporting() & $severity)) return false;
	throw new RuntimeException('Geocoding runtime error');
});

try {
	$args = [];
	foreach (array_slice($argv, 1) as $argument) {
		if ($argument === '--help' && $argc === 2) {
			echo <<<'HELP'
Manually review and geocode exactly one contact billing address, client 100 only.
No historical scan, queue drain, cron mode or automatic retries are available.

Review (read-only; no API call or state directory creation):
  php application/cli/contact-geocode.php --client=100 --user=ID --address=ID --config=/approved/database.ini --environment=SECTION --geocoding-config=/private/geocoding.ini

After personally checking the address in the existing contact view, repeat with:
  --confirm-public-address=FINGERPRINT
This confirms that the address is public, non-personal, non-confidential, and that
this is a manually initiated individual lookup under the current Nominatim policy.
Never confirm a home address, a sole trader's personal address or confidential data.
Only street, postcode, city and country are transmitted; no contact names or IDs.
The fingerprint binds this confirmation to this address and its postal contents.

Existing valid coordinates with no stored postal baseline are preserved. Only if
you have verified a real postal change, additionally use --confirmed-postal-change=1.
Tracked postal changes are detected by fingerprint; unrelated edits do not requery.

Configuration: configs/geocoding.sample.ini, explicit [manual] section, real contact
URL in the User-Agent, HTTPS Nominatim-compatible endpoint, private state directory
(0700), outside the web root. The database user needs SELECT and address UPDATE;
the selected active application user must have contact view AND edit permission.
Use one shared state directory on one machine. Do not run from multiple hosts.

At most one API request per invocation, one at a time, at least 15 seconds apart,
and at most 50/day (UTC). Successful results remain cached. Temporary errors use
1h, 2h, 4h, 8h backoff, then manual review; empty results are cached for seven days.
Retry-After is respected. Retries always require another manual invocation.
403 blocks the endpoint pending operator review. Corrupt state fails closed.
Do not routinely delete/reset state: it contains cache, backoff and quota history.
No raw addresses, request URLs or response bodies are emitted or persisted.

Nominatim policy: https://operations.osmfoundation.org/policies/nominatim/
Attribution: © OpenStreetMap contributors, ODbL 1.0
License: https://www.openstreetmap.org/copyright
The existing contact map already displays OSM attribution and is unchanged.
Personal/confidential addresses and automatic weekly processing remain unsupported.

HELP;
			exit(0);
		}
		if (!preg_match('/^--([a-z-]+)=(.*)$/sD', $argument, $match) || isset($args[$match[1]]) || !in_array($match[1], ['client', 'user', 'address', 'config', 'environment', 'geocoding-config', 'confirm-public-address', 'confirmed-postal-change'], true)) throw new InvalidArgumentException('Invalid arguments');
		$args[$match[1]] = $match[2];
	}
	foreach (['client', 'user', 'address', 'config', 'environment', 'geocoding-config'] as $required) if (empty($args[$required])) throw new InvalidArgumentException('Missing explicit arguments');
	if ($args['client'] !== '100') throw new InvalidArgumentException('Only client 100 is supported');
	foreach (['user', 'address'] as $field) if (!ctype_digit($args[$field]) || !filter_var($args[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) throw new InvalidArgumentException('Invalid ID');
	foreach (['config', 'geocoding-config'] as $field) if ($args[$field][0] !== '/' || !is_file($args[$field])) throw new InvalidArgumentException('Explicit configuration paths required');
	if (isset($args['confirmed-postal-change']) && ($args['confirmed-postal-change'] !== '1' || !isset($args['confirm-public-address']))) throw new InvalidArgumentException('Invalid change confirmation');
	if (isset($args['confirm-public-address']) && !preg_match('/^[a-f0-9]{64}$/D', $args['confirm-public-address'])) throw new InvalidArgumentException('Invalid public-address confirmation');

	define('BASE_PATH', dirname(__DIR__, 2));
	define('APPLICATION_PATH', BASE_PATH.'/application');
	date_default_timezone_set('Europe/Berlin');
	set_include_path(BASE_PATH.'/library'.PATH_SEPARATOR.get_include_path());
	// Do not bootstrap web sessions, cron campaigns or application write hooks.
	spl_autoload_register(static function(string $class): void {
		$path = stream_resolve_include_path(str_replace('_', '/', $class).'.php');
		if (!$path && preg_match('/^Application_Model_(.+)$/', $class, $parts)) $path = APPLICATION_PATH.'/models/'.str_replace('_', '/', $parts[1]).'.php';
		if (!$path && preg_match('/^Users_Model_(.+)$/', $class, $parts)) $path = APPLICATION_PATH.'/modules/users/models/'.str_replace('_', '/', $parts[1]).'.php';
		if ($path && is_file($path)) require_once $path;
	});
	$config = (new Zend_Config_Ini($args['config'], $args['environment']))->resources->db->toArray();
	if (strtolower($config['adapter'] ?? '') !== 'pdo_mysql') throw new InvalidArgumentException('Unsupported database adapter');
	$params = array_intersect_key($config['params'] ?? [], array_flip(['host', 'port', 'unix_socket', 'username', 'password', 'dbname', 'charset']));
	$db = Zend_Db::factory('Pdo_Mysql', $params);
	Zend_Db_Table::setDefaultAdapter($db);
	$user = (new Users_Model_DbTable_User())->getActiveUserById((int)$args['user']);
	if (!$user) throw new RuntimeException('Inactive user');
	$user['clientid'] = 100;
	Zend_Registry::set('User', $user);
	$clients = new Application_Model_DbTable_Client();
	if (!array_key_exists(100, $clients->getSwitchableClients())) throw new RuntimeException('Client access denied');
	$client = $clients->getClient();
	if (!empty($client['parentid'])) throw new RuntimeException('Contact owner is outside client 100');
	Zend_Registry::set('Client', $client);
	$permission = new DEEC_Permission($user);
	if (!$permission->hasPermission('contacts', 'contact', 'view') || !$permission->hasPermission('contacts', 'contact', 'edit')) throw new RuntimeException('Contact access denied');
	$geocoding = (new Zend_Config_Ini($args['geocoding-config'], 'manual'))->toArray();
	$address = new DEEC_Address(BASE_PATH, $params['host'] ?? 'localhost', $params['username'], $params['password'] ?? '', $params['dbname'], $geocoding, (int)($params['port'] ?? 0), $params['unix_socket'] ?? null);
	$result = isset($args['confirm-public-address'])
		? $address->geocodeAddress((int)$args['address'], $args['confirm-public-address'], isset($args['confirmed-postal-change']))
		: $address->inspectGeocoding((int)$args['address']);
	echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
	// SQL and transport exceptions may contain credentials or address data.
	fwrite(STDERR, "Geocoding failed. Check explicit configuration, permissions and private state; no exception details emitted.\n");
	exit(1);
}
