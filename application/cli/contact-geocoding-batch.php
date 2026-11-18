<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
ini_set('log_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED);
set_error_handler(static function(int $severity): bool {
	if (!(error_reporting() & $severity)) return false;
	throw new RuntimeException('Batch runtime error');
});

try {
	$args = [];
	foreach (array_slice($argv, 1) as $argument) {
		if ($argument === '--help' && $argc === 2) {
			echo <<<'HELP'
Controlled one-time Nominatim batch, contact owner 100 only. Disabled by default.
No provider is integrated and no cron is installed by this command.

All modes require explicit context:
  --client=100 --user=ID --config=/approved/database.ini --environment=SECTION --geocoding-config=/private/geocoding.ini

Modes:
  --mode=status                     Read progress and activation blockers (default).
  --mode=dry-run                    Read counts; no files, API or DB writes.
  --mode=dry-run --after=CURSOR      Inspect the next older selection page.
  --mode=dry-run --approvals=/private/approvals.json
  --mode=review --output=/private/review.json
                                    Export IDs/fingerprints, ALL privacy flags false.
                                    Optional --after=CURSOR continues older contacts.
  --mode=prepare --approvals=/private/approvals.json
                                    Freeze approved candidates; persist a run ID.
  --mode=run --execute=RUN_ID        Resume <=100 local items / one API request.
  --mode=archive --execute=RUN_ID    Archive only a completed run; keep cache/quotas.

Review each selected billing address in the ERP. Only public, NON-PERSONAL and
NON-CONFIDENTIAL addresses may have all three flags true, with a review_reference
(ticket). Never infer approval from roles/tags. No postal text is exported.
The reviewed_by user must prepare the batch; an authorized service user may run it.
After prepare, the approval file is immutable: any edit/revocation stops execution.
Pause with batch.enabled=false; resume with the SAME approval file and run ID.

Selection: created DESC, id DESC; the map's first active billing address by ordering
and id. Valid coordinates are skipped. geoupdated never excludes missing coordinates.
Selection is bounded by selection_page_size (default 1000, maximum 50000) per page,
NOT by estimated API requests or total contacts. Legacy max_contacts is a page-size
alias. The dry-run reports exclusion_reasons (one primary reason per unreviewable row),
validation_issues (overlapping field errors), population and selection_page.
Use selection_page.next_after as --after for the next older page, including pages
without reviewable/approved entries. remaining_older counts candidates beyond this
page; population.without_billing_address explains contacts missing from the queue.
NULL creation dates are last, with descending IDs. No OFFSET pagination is used.
Review files bind their page/cursor; prepare uses that page even after config changes.
If page membership changes between review and prepare, review_page_changed is true
and prepare requires a fresh review; no older unreviewed rows fill vacated slots.
Snapshots never grow automatically. Unapproved/unreviewable records stay excluded.
After a run completes, archive it explicitly and prepare a separately reviewed older
page using a new approval file. Active/retrying runs cannot be archived. Legacy run
files remain resumable; their missing selection boundary is never guessed.

Use configs/geocoding-batch.sample.ini. Before ANY run, obtain written service-use
approval covering the volume/rate, complete privacy clearance, set both approval
references and a future approval_valid_until UTC timestamp, then explicitly enable.
Rate limits alone do NOT authorize public Nominatim bulk processing.

Manual and batch MUST share one private state directory (0700), outside the web root,
on ONE machine. Approval files and their parents require 0600 and 0700 respectively.
Application rights: contact view; prepare/run/archive also require edit. DB: SELECT plus
UPDATE(latitude, longitude, geoupdated) on address for run. No SQL migration needed.
No web sessions or campaigns are bootstrapped. Output contains aggregate status only.

>=60s between external calls; default 500/day UTC (configurable downward). Shared
counters include manual requests. On batch-use UTC days shared traffic stays at 60s;
manual CLI/options remain available with its own 50/day cap. Cache, backoff,
Retry-After and locks are shared with K2B. Retry jobs cannot block the initial pass.
Interrupted applies recover by skipping coordinates already present. Keep state:
deleting/resetting it discards quotas, cache and progress. No automatic state reset.

EXAMPLE ONLY after all approvals and separate operational release; do not install now:
# * * * * * /usr/bin/php /var/www/dewawi/application/cli/contact-geocoding-batch.php --client=100 --user=ID --config=/private/database.ini --environment=production --geocoding-config=/private/geocoding.ini --mode=run --execute=RUN_ID >> /private/geocoding-status.log 2>&1

Attribution: © OpenStreetMap contributors, ODbL 1.0
License: https://www.openstreetmap.org/copyright
Policy: https://operations.osmfoundation.org/policies/nominatim/

HELP;
			exit(0);
		}
		if (!preg_match('/^--([a-z-]+)=(.*)$/sD', $argument, $match) || isset($args[$match[1]]) || !in_array($match[1], ['client', 'user', 'config', 'environment', 'geocoding-config', 'mode', 'execute', 'approvals', 'output', 'after'], true)) throw new InvalidArgumentException('Invalid arguments');
		$args[$match[1]] = $match[2];
	}
	foreach (['client', 'user', 'config', 'environment', 'geocoding-config'] as $required) if (empty($args[$required])) throw new InvalidArgumentException('Explicit context required');
	$mode = $args['mode'] ?? 'status';
	if ($args['client'] !== '100' || !ctype_digit($args['user']) || !filter_var($args['user'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) || !in_array($mode, ['status', 'dry-run', 'review', 'prepare', 'run', 'archive'], true)) throw new InvalidArgumentException('Invalid batch scope');
	foreach (['config', 'geocoding-config'] as $field) if ($args[$field][0] !== '/' || !is_file($args[$field])) throw new InvalidArgumentException('Explicit configuration path required');
	if (in_array($mode, ['run', 'archive'], true) !== isset($args['execute']) || (isset($args['execute']) && !preg_match('/^[a-f0-9]{32}$/D', $args['execute']))) throw new InvalidArgumentException('Explicit run ID required for run/archive only');
	if (($mode === 'review') !== isset($args['output']) || ($mode === 'prepare' && empty($args['approvals'])) || (isset($args['approvals']) && !in_array($mode, ['prepare', 'dry-run'], true))) throw new InvalidArgumentException('Invalid mode arguments');
	if (isset($args['after']) && (!in_array($mode, ['dry-run', 'review'], true) || !preg_match('/^[A-Za-z0-9_-]{1,256}$/D', $args['after']))) throw new InvalidArgumentException('Invalid selection cursor argument');

	define('BASE_PATH', dirname(__DIR__, 2));
	define('APPLICATION_PATH', BASE_PATH.'/application');
	date_default_timezone_set('Europe/Berlin');
	set_include_path(BASE_PATH.'/library'.PATH_SEPARATOR.get_include_path());
	spl_autoload_register(static function(string $class): void {
		$path = stream_resolve_include_path(str_replace('_', '/', $class).'.php');
		if (!$path && preg_match('/^Application_Model_(.+)$/', $class, $parts)) $path = APPLICATION_PATH.'/models/'.str_replace('_', '/', $parts[1]).'.php';
		if (!$path && preg_match('/^(Contacts|Users)_Model_(.+)$/', $class, $parts)) $path = APPLICATION_PATH.'/modules/'.strtolower($parts[1]).'/models/'.str_replace('_', '/', $parts[2]).'.php';
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
	$auth = Zend_Auth::getInstance();
	$auth->setStorage(new Zend_Auth_Storage_NonPersistent());
	$auth->getStorage()->write((object)['id' => $user['id'], 'clientid' => 100]);
	$geocoding = new Zend_Config_Ini($args['geocoding-config']);
	$service = new DEEC_AddressBatch(BASE_PATH, $params['host'] ?? 'localhost', $params['username'], $params['password'] ?? '', $params['dbname'], $geocoding->manual->toArray(), (int)($params['port'] ?? 0), $params['unix_socket'] ?? null);
	$service->configureBatch(new Contacts_Model_GeocodingBatch(), $geocoding->batch->toArray());
	switch ($mode) {
		case 'dry-run': $result = $service->dryRun($args['approvals'] ?? null, $args['after'] ?? null); break;
		case 'review': $result = $service->exportReview($args['output'], $args['after'] ?? null); break;
		case 'prepare': $result = $service->prepare($args['approvals']); break;
		case 'run': $result = $service->run($args['execute']); break;
		case 'archive': $result = $service->archive($args['execute']); break;
		default: $result = $service->status();
	}
	echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
	if ($result['status'] === 'blocked') exit(2);
} catch (Throwable $error) {
	fwrite(STDERR, "Batch failed. Check explicit context, permissions, approvals and private state. No exception details emitted.\n");
	exit(1);
}
