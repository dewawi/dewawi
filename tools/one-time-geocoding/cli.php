<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0'); ini_set('log_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED);
set_error_handler(static function(int $severity): bool { if (!(error_reporting() & $severity)) return false; throw new RuntimeException('runtime_error'); });
$db = null; $readTransaction = false;
try {
	$args = [];
	foreach (array_slice($argv, 1) as $argument) {
		if ($argument === '--help' && $argc === 2) {
			echo <<<'HELP'
Removable one-time Geoapify EU batch tool, client 100 only. No cron/application bootstrap.
See tools/one-time-geocoding/README.md before use. Private files contain postal data.

All modes: --client=100 --work=/private/run
  --mode=export --config=/private/readonly.ini --environment=SECTION --user=ID [--limit=50000]
  --mode=status [--accuracy=strict|city]
  --mode=entries [--accuracy=strict|city]  List export entry numbers/IDs/status, no postal text.
  --mode=defer --entry=NUMBER --execute=EXPORT_SHA256
    --operator=LABEL --reason=postal_review --reference=TICKET
    Defer an unsent export entry. Shared queries require --include-duplicates=true.
    Reasons: postal_review, incomplete_address, privacy_review, other_review.
    Appends a private audit event; no database/API access, no job modifications.
  --mode=geocode --provider-config=/private/provider.ini --execute=EXPORT_SHA256
    One external HTTP operation per invocation; disabled in the sample configuration.
  --mode=run --provider-config=/private/provider.ini --execute=EXPORT_SHA256 [--accuracy=strict|city]
    One scheduler tick: geocode, save private aggregate progress, exit. No SQL import.
    Repeat each minute using ONE scheduler; workspace/shared-key locks prevent overlap.
  --mode=recover-job --job=INDEX --provider-job=PROVIDER_ID --execute=EXPORT_SHA256
    Link an uncertain submission to its known provider ID. Never submit it again blindly.
  --mode=abandon-job --job=INDEX --execute=EXPORT_SHA256
    Mark an uncertain submission rejected; preserve its credit reservation.
  --mode=plan --config=/private/readonly.ini --environment=SECTION --user=ID --plan=plan-01.json [--accuracy=strict|city]
    Read-only import dry-run; creates a new immutable local plan with counts/hash.
    City mode requires matching country/city/postcode and confident, consistent candidates.
    Import reads the sealed policy from the plan; no command-line accuracy override.
  --mode=import --config=/private/writer.ini --environment=SECTION --user=ID
    --plan=plan-01.json --permit=/private/import.ini --execute=PLAN_SHA256 [--limit=100]
    Separate release required. SELECT + column-scoped address UPDATE only.

Export and plan explicitly use read-only transactions. No database is opened by
status/entries/defer/geocode/run/recover-job/abandon-job. An import never calls an external API.
Export/plan use contact view permissions; import additionally requires contact edit.
The workspace, configurations, quota directory, plans and backups must stay private,
outside the web root. Keep one quota directory per dedicated Geoapify API key.
No postal text, API key, contact name or raw error is printed. Entry IDs are private
metadata; do not publish them. Operator labels are self-declared audit references,
not ERP authentication; offline modes require OS access to the private workspace.
No K2B/K2C state is touched. Deferrals cannot undo or edit existing provider jobs.

HELP;
			exit(0);
		}
		if (!preg_match('/^--([a-z-]+)=(.*)$/sD', $argument, $match) || isset($args[$match[1]]) || !in_array($match[1], ['client', 'work', 'mode', 'config', 'environment', 'user', 'limit', 'provider-config', 'execute', 'job', 'provider-job', 'plan', 'permit', 'entry', 'operator', 'reason', 'reference', 'include-duplicates', 'accuracy'], true)) throw new RuntimeException('invalid_arguments');
		$args[$match[1]] = $match[2];
	}
	$mode = $args['mode'] ?? 'status';
	if (($args['client'] ?? '') !== '100' || !isset($args['work']) || !in_array($mode, ['export', 'status', 'entries', 'defer', 'geocode', 'run', 'recover-job', 'abandon-job', 'plan', 'import'], true)) throw new RuntimeException('invalid_scope');
	$required = ['export' => ['config', 'environment', 'user'], 'status' => [], 'entries' => [], 'defer' => ['entry', 'execute', 'operator', 'reason', 'reference'], 'geocode' => ['provider-config', 'execute'], 'run' => ['provider-config', 'execute'], 'recover-job' => ['job', 'provider-job', 'execute'], 'abandon-job' => ['job', 'execute'], 'plan' => ['config', 'environment', 'user', 'plan'], 'import' => ['config', 'environment', 'user', 'plan', 'permit', 'execute']][$mode];
	$allowed = array_merge(['client', 'work', 'mode'], $required, in_array($mode, ['export', 'import'], true) ? ['limit'] : [], $mode === 'defer' ? ['include-duplicates'] : [], in_array($mode, ['status', 'entries', 'plan', 'run'], true) ? ['accuracy'] : []);
	if (array_diff(array_keys($args), $allowed)) throw new RuntimeException('invalid_mode_arguments');
	foreach ($required as $name) if (!isset($args[$name]) || $args[$name] === '') throw new RuntimeException('missing_argument');
	if (isset($args['execute']) && !preg_match('/^[a-f0-9]{64}$/D', $args['execute'])) throw new RuntimeException('invalid_release_hash');
	if (isset($args['include-duplicates']) && $args['include-duplicates'] !== 'true') throw new RuntimeException('invalid_duplicate_confirmation');
	$entry = isset($args['entry']) ? filter_var($args['entry'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50000]]) : null;
	if ($entry === false) throw new RuntimeException('invalid_entry_number');
	$accuracy = $args['accuracy'] ?? 'strict';
	if (!in_array($accuracy, ['strict', 'city'], true)) throw new RuntimeException('invalid_accuracy_mode');
	define('BASE_PATH', dirname(__DIR__, 2)); define('APPLICATION_PATH', BASE_PATH.'/application');
	date_default_timezone_set('Europe/Berlin'); set_include_path(BASE_PATH.'/library'.PATH_SEPARATOR.get_include_path());
	spl_autoload_register(static function(string $class): void {
		$path = stream_resolve_include_path(str_replace('_', '/', $class).'.php');
		if (!$path && preg_match('/^Application_Model_(.+)$/', $class, $parts)) $path = APPLICATION_PATH.'/models/'.str_replace('_', '/', $parts[1]).'.php';
		if (!$path && preg_match('/^(Contacts|Users)_Model_(.+)$/', $class, $parts)) $path = APPLICATION_PATH.'/modules/'.strtolower($parts[1]).'/models/'.str_replace('_', '/', $parts[2]).'.php';
		if ($path && is_file($path)) require_once $path;
	});
	require_once __DIR__.'/Geocoding.php';
	$model = $address = null; $target = '';
	if (in_array($mode, ['export', 'plan', 'import'], true)) {
		OneTimeGeocoding::privateConfig($args['config']);
		$config = (new Zend_Config_Ini($args['config'], $args['environment']))->resources->db->toArray();
		if (strtolower($config['adapter'] ?? '') !== 'pdo_mysql' || !ctype_digit($args['user']) || (int)$args['user'] < 1) throw new RuntimeException('invalid_database_context');
		$params = array_intersect_key($config['params'] ?? [], array_flip(['host', 'port', 'unix_socket', 'username', 'password', 'dbname', 'charset']));
		$db = Zend_Db::factory('Pdo_Mysql', $params); Zend_Db_Table::setDefaultAdapter($db);
		if ($mode !== 'import') { $db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY'); $db->beginTransaction(); $readTransaction = true; }
		$user = (new Users_Model_DbTable_User())->getActiveUserById((int)$args['user']);
		if (!$user) throw new RuntimeException('inactive_user');
		$user['clientid'] = 100; Zend_Registry::set('User', $user);
		$clients = new Application_Model_DbTable_Client();
		if (!array_key_exists(100, $clients->getSwitchableClients())) throw new RuntimeException('client_access_denied');
		$client = $clients->getClient(); if (!empty($client['parentid'])) throw new RuntimeException('foreign_contact_owner');
		Zend_Registry::set('Client', $client);
		$auth = Zend_Auth::getInstance(); $auth->setStorage(new Zend_Auth_Storage_NonPersistent()); $auth->getStorage()->write((object)['id' => $user['id'], 'clientid' => 100]);
		$model = new Contacts_Model_GeocodingBatch(); $model->authorize();
		$address = new OneTimeGeocodingAddress(BASE_PATH, $params['host'] ?? 'localhost', $params['username'], $params['password'] ?? '', $params['dbname'], ['endpoint' => OneTimeGeocoding::ENDPOINT], (int)($params['port'] ?? 0), $params['unix_socket'] ?? null);
		$target = OneTimeGeocoding::digest([$params['host'] ?? 'localhost', (int)($params['port'] ?? 0), $params['unix_socket'] ?? null, $params['dbname']]);
	}
	$tool = new OneTimeGeocoding($args['work'], $db, $model, $address);
	$limit = filter_var($args['limit'] ?? ($mode === 'import' ? 100 : 50000), FILTER_VALIDATE_INT);
	if ($limit === false) throw new RuntimeException('invalid_limit');
	if (isset($args['job']) && (!ctype_digit($args['job']) || strlen($args['job']) > 6)) throw new RuntimeException('invalid_job');
	$privateIni = static function(string $path, string $section): array { OneTimeGeocoding::privateConfig($path); return (new Zend_Config_Ini($path, $section))->toArray(); };
	switch ($mode) {
		case 'export': $result = $tool->export($target, $limit); break;
		case 'entries': $result = $tool->entries($accuracy); break;
		case 'defer': $result = $tool->defer($entry, $args['execute'], $args['operator'], $args['reason'], $args['reference'], isset($args['include-duplicates'])); break;
		case 'geocode': $result = $tool->geocode($privateIni($args['provider-config'], 'provider'), $args['execute']); break;
		case 'run': $result = $tool->run($privateIni($args['provider-config'], 'provider'), $args['execute'], $accuracy); break;
		case 'recover-job': $result = $tool->recoverJob((int)$args['job'], $args['provider-job'], $args['execute']); break;
		case 'abandon-job': $result = $tool->abandonJob((int)$args['job'], $args['execute']); break;
		case 'plan': $result = $tool->plan($args['plan'], $target, $accuracy); break;
		case 'import': $result = $tool->import($args['plan'], $target, $privateIni($args['permit'], 'import'), $args['execute'], $limit); break;
		default: $result = $tool->status($accuracy);
	}
	if ($readTransaction) { $db->rollBack(); $readTransaction = false; }
	echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
	if ($readTransaction) { try { $db->rollBack(); } catch (Throwable $ignored) {} }
	fwrite(STDERR, "One-time geocoding stopped. Check explicit arguments, private files, permissions, approvals and local status. No exception details emitted.\n"); exit(1);
}
