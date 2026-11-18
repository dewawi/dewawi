<?php

require __DIR__.'/map-bootstrap.php';
mapActor(100);
$db->query("SET SESSION sql_mode = CONCAT(@@sql_mode, ',ONLY_FULL_GROUP_BY')");
$checks = $scenario = 0;
function batchCheck(bool $condition, string $message): void { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; }
function batchReject(callable $callback): void {
	try { $callback(); } catch (InvalidArgumentException | RuntimeException | JsonException $error) { batchCheck(true, 'Rejected'); return; }
	throw new RuntimeException('Unsafe batch operation accepted');
}
function batchPolicy(array $changes = []): array { return $changes + ['enabled' => true, 'max_contacts' => 28506, 'daily_limit' => 500, 'usage_approval_reference' => 'TEST-USAGE-ONLY', 'privacy_approval_reference' => 'TEST-PRIVACY-ONLY', 'approval_valid_until' => '2099-01-01T00:00:00Z']; }
function batchFixture(): array {
	global $db;
	$db->update('contact', ['deleted' => 1], ['clientid = ?' => 100]);
	$db->delete('address', 'parentid BETWEEN 50001 AND 50012');
	$db->delete('contact', 'id BETWEEN 50001 AND 50012');
	$db->delete('contactrole', 'parentid BETWEEN 50001 AND 50012');
	$db->insert('contactrole', ['parentid' => 50003, 'clientid' => 100, 'role' => 'customer']);
	$ids = [];
	foreach ([1 => '2026-11-17', 2 => '2026-11-18', 3 => '2026-11-18', 4 => '2026-11-16', 5 => '2026-11-15', 6 => '2026-11-14', 7 => null, 8 => '2026-11-19', 9 => '2026-11-20', 10 => '2026-11-13', 11 => '2026-11-12', 12 => '2026-11-11'] as $n => $created) {
		$tenant = $n === 9 ? 300 : 100;
		$db->insert('contact', ['id' => 50000 + $n, 'contactid' => 50000 + $n, 'clientid' => $tenant, 'catid' => 0, 'created' => $created, 'name1' => 'PRIVATE_BATCH_CONTACT', 'deleted' => (int)($n === 8)]);
		$db->insert('address', ['parentid' => 50000 + $n, 'clientid' => $tenant, 'module' => 'contacts', 'controller' => 'contact', 'type' => $n === 10 ? 'shipping' : 'billing', 'street' => 'Synthetic Street '.($n === 5 ? 1 : $n), 'postcode' => '12345', 'city' => $n === 6 ? '' : 'Batch City', 'country' => 'DE', 'created' => '2026-11-01', 'modified' => '2026-11-01', 'geoupdated' => '2026-11-25', 'latitude' => in_array($n, [4, 11, 12]) ? ($n === 11 ? 0 : 51) : null, 'longitude' => in_array($n, [4, 11]) ? ($n === 11 ? 0 : 7) : null]);
		$ids[$n] = (int)$db->lastInsertId();
	}
	$db->insert('address', ['parentid' => 50005, 'clientid' => 100, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'ordering' => 1, 'latitude' => 52, 'longitude' => 8]);
	foreach ([['clientid' => 300], ['deleted' => 1], ['type' => 'shipping']] as $override) $db->insert('address', $override + ['parentid' => 50003, 'clientid' => 100, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'ordering' => -1, 'latitude' => 51, 'longitude' => 7]);
	return $ids;
}
class BatchFixtureService extends DEEC_AddressBatch {
	public int $now = 1800000000;
	public array $requests = [];
	public bool $fail = false;
	public string $httpStatus = 'HTTP/1.1 200 OK';
	public $duringRequest = null;
	public $beforeAuthorization = null;
	protected function geocodingTime(): int { return $this->now; }
	protected function authorizeGeocodingRequest(): void { if ($this->beforeAuthorization) ($this->beforeAuthorization)(); parent::authorizeGeocodingRequest(); }
	protected function requestGeocode(string $query): array {
		parse_str($query, $fields); $this->requests[] = $fields;
		batchCheck($fields['countrycodes'] === 'de' && !isset($fields['q']), 'Batch lost country restriction');
		if ($this->duringRequest) ($this->duringRequest)();
		if ($this->fail) return [false, []];
		preg_match('/^(.*) (\d+)$/D', $fields['street'], $parts);
		return [json_encode([['lat' => '51.2', 'lon' => '6.7', 'place_rank' => 30, 'boundingbox' => ['51.19', '51.21', '6.69', '6.71'], 'address' => ['country_code' => 'de', 'road' => $parts[1], 'house_number' => $parts[2], 'postcode' => $fields['postalcode'], 'city' => $fields['city']]]]), [$this->httpStatus]];
	}
}
function batchService(string $directory, array $policy = []): BatchFixtureService {
	global $root, $work;
	$service = new BatchFixtureService($root, 'localhost', 'root', '', 'contact_map_test', ['endpoint' => 'https://nominatim.openstreetmap.org/search', 'user_agent' => 'DEWAWI/1.1.7 (+https://dewawi.com/contact)', 'state_dir' => $directory], 0, $work.'/mysql.sock');
	$service->configureBatch(new Contacts_Model_GeocodingBatch(), batchPolicy($policy));
	return $service;
}
function approveReview(string $path): array {
	$data = json_decode(file_get_contents($path), true);
	foreach ($data['addresses'] as &$entry) { $entry['public'] = $entry['non_personal'] = $entry['non_confidential'] = true; $entry['review_reference'] = 'TEST-REVIEW-'.$entry['id']; }
	unset($entry); file_put_contents($path, json_encode($data));
	return $data;
}
function preparedBatch(array $policy = []): array {
	global $work, $scenario;
	$ids = batchFixture(); $directory = $work.'/batch-'.(++$scenario); $manifest = $work.'/approvals-'.$scenario.'.json';
	$service = batchService($directory, $policy); $service->exportReview($manifest); approveReview($manifest);
	$prepared = $service->prepare($manifest);
	return [$service, $directory, $manifest, $prepared['run_id'], $ids];
}
$model = new Contacts_Model_GeocodingBatch();
$ids = batchFixture();
$rows = $model->candidates();
batchCheck(array_map('intval', array_column($rows, 'id')) === [50003, 50002, 50001, 50005, 50006, 50012, 50007], 'Batch sort/NULL dates/valid coordinates/contact scope differs');
batchCheck(array_map('intval', array_column($model->candidates(3), 'id')) === [50003, 50002, 50001], 'Selection limit not applied after sorting');
batchCheck((int)$model->current(50005)['address_id'] === $ids[5] && $model->current(50005)['latitude'] === null, 'Secondary billing coordinates replaced map-selected address');
batchCheck($model->current(50009) === null && $model->current(50008) === null, 'Foreign/deleted contacts admitted');
$directory = $work.'/batch-dry'; $service = batchService($directory, ['enabled' => false]);
$before = $db->fetchAll('SELECT * FROM address ORDER BY id');
$dry = $service->dryRun();
batchCheck($dry['counts'] === ['selected' => 7, 'unreviewable' => 1, 'already_valid' => 0, 'not_approved' => 6, 'approval_changed' => 0, 'approved' => 0], 'Dry-run classification incorrect');
batchCheck(!is_dir($directory) && !$service->requests && $db->fetchAll('SELECT * FROM address ORDER BY id') === $before, 'Dry-run had side effects');
$manifest = $work.'/batch-review.json'; $service->exportReview($manifest);
$data = json_decode(file_get_contents($manifest), true);
batchCheck(count($data['addresses']) === 6 && $data['addresses'][0]['id'] === 50003 && $data['addresses'][5]['id'] === 50007, 'Review export lost ordering');
batchCheck(!str_contains(strtolower(file_get_contents($manifest)), 'synthetic') && !str_contains(file_get_contents($manifest), 'PRIVATE_'), 'Review export exposed postal text');
batchCheck($service->prepare($manifest)['status'] === 'no_approved_candidates', 'Unreviewed template implicitly approved contacts');
$data = approveReview($manifest);
$data['addresses'][0]['non_personal'] = false; file_put_contents($manifest, json_encode($data));
batchCheck($service->dryRun($manifest)['counts']['approved'] === 5, 'A public flag alone authorized personal data');
$data['addresses'][0]['non_personal'] = 'true'; file_put_contents($manifest, json_encode($data));
batchReject(fn() => $service->dryRun($manifest));
$data = approveReview($manifest); $data['addresses'][0]['fingerprint'] = str_repeat('a', 64); file_put_contents($manifest, json_encode($data));
batchCheck($service->dryRun($manifest)['counts']['approval_changed'] === 1, 'Stale postal approval accepted');
// Regenerate the immutable approval file from the current snapshot.
unlink($manifest); $service->exportReview($manifest); approveReview($manifest);
$prepared = $service->prepare($manifest); $runId = $prepared['run_id'];
batchCheck($prepared['total'] === 6 && $prepared['cursor'] === 0 && !$service->requests && $db->fetchAll('SELECT * FROM address ORDER BY id') === $before, 'Prepare made API/DB changes');
batchCheck($service->run($runId)['status'] === 'blocked' && !$service->requests, 'Default disabled gate ignored');
batchReject(fn() => $service->prepare($manifest));
foreach ([['usage_approval_reference' => ''], ['privacy_approval_reference' => ''], ['approval_valid_until' => '2020-01-01T00:00:00Z']] as $missing) {
	$guarded = batchService($directory, $missing); batchCheck($guarded->run($runId)['status'] === 'blocked' && !$guarded->requests, 'Missing global approval/expiry gate ignored');
}
$service = batchService($directory);
batchReject(fn() => $service->run(str_repeat('0', 32)));
$immutable = file_get_contents($manifest); file_put_contents($manifest, $immutable."\n");
batchReject(fn() => $service->run($runId)); file_put_contents($manifest, $immutable);
$first = $service->run($runId);
batchCheck($first['external_requests'] === 1 && $first['cursor'] === 1 && $service->requests[0]['street'] === 'synthetic street 3', 'Newest candidate not processed first');
batchCheck((float)$db->fetchOne('SELECT latitude FROM address WHERE id = ?', $ids[3]) === 51.2, 'Existing geoupdated blocked missing coordinates');
$paused = $service->run($runId);
batchCheck($paused['external_requests'] === 0 && $paused['cursor'] === 1 && $paused['stopped']['scope'] === 'provider', 'Rate limit advanced cursor past pending contacts');
$service->now += 59; batchCheck($service->run($runId)['external_requests'] === 0, 'Request made before 60 seconds');
$service->now++; $service->run($runId);
batchCheck($service->requests[1]['street'] === 'synthetic street 2', 'ID tie-breaker was lost');
$restart = batchService($directory); $restart->now = $service->now + 60; $restart->run($runId);
batchCheck($restart->requests[0]['street'] === 'synthetic street 1' && $restart->status()['cursor'] === 3, 'Restart failed to resume frozen progress');
$cacheFile = $directory.'/manual.json'; $cache = json_decode(file_get_contents($cacheFile), true); $cache['requests_today'] = 500; file_put_contents($cacheFile, json_encode($cache));
$cached = $restart->run($runId);
batchCheck($cached['database_writes'] === 1 && $cached['external_requests'] === 0 && $cached['cached'] === 1 && $cached['cursor'] === 4 && $cached['stopped']['status'] === 'backoff', 'Cache reuse or ordered cursor failed: '.json_encode($cached));
$restart->now += 60;
batchCheck($restart->run($runId)['stopped']['status'] === 'daily_limit' && $restart->status()['cursor'] === 4, 'Daily quota did not preserve pending cursor');
$restart->now += 86400; $restart->run($runId);
batchCheck(json_decode(file_get_contents($cacheFile), true)['requests_today'] === 1, 'UTC daily counter did not reset');
$restart->now += 60; $done = $restart->run($runId);
batchCheck($done['status'] === 'complete' && $done['total'] === 6 && $done['counts']['updated'] === 6 && $done['cached'] === 1, 'Batch did not complete after resume');
batchCheck(!str_contains(strtolower(file_get_contents($directory.'/batch.json')), 'synthetic') && !str_contains(strtolower(file_get_contents($cacheFile)), 'synthetic'), 'Progress/cache persisted postal text');
batchCheck($restart->run($runId)['external_requests'] === 0, 'Completed run repeated API calls');

[$service, $directory, $manifest, $runId, $ids] = preparedBatch(['max_contacts' => 3]);
$service->fail = true; $service->run($runId);
batchCheck($service->status()['counts']['retry'] === 1 && $service->status()['cursor'] === 1, 'Failure did not defer the address');
$service->fail = false; $service->now += 60; $service->run($runId); $service->now += 60; $service->run($runId);
batchCheck($service->status()['cursor'] === 3 && $service->status()['counts']['updated'] === 2, 'Failed address blocked following candidates');
$service->now = 1800003600; $retried = $service->run($runId);
batchCheck($retried['status'] === 'complete' && count($service->requests) === 4, 'Deferred address was not retried after initial pass');

[$service, $directory, $manifest, $runId, $ids] = preparedBatch(['max_contacts' => 3]);
$service->httpStatus = 'HTTP/1.1 403 Forbidden'; $service->run($runId);
$blocked = $service->run($runId);
batchCheck($blocked['cursor'] === 1 && $blocked['counts']['pending'] === 2 && $blocked['stopped']['scope'] === 'provider' && count($service->requests) === 1, 'Provider block discarded unrelated pending jobs');
foreach (['revoke', 'expire'] as $guard) {
	[$service, $directory, $manifest, $runId, $ids] = preparedBatch(['max_contacts' => 3, 'approval_valid_until' => gmdate('Y-m-d\TH:i:s\Z', 1800000001)]);
	$calls = 0;
	$service->beforeAuthorization = static function() use ($service, $manifest, $guard, &$calls): void {
		if (++$calls !== 2) return;
		if ($guard === 'revoke') file_put_contents($manifest, file_get_contents($manifest)."\n");
		else $service->now += 2;
	};
	batchReject(fn() => $service->run($runId));
	batchCheck(!$service->requests && $db->fetchOne('SELECT latitude FROM address WHERE id = ?', $ids[3]) === null, 'Approval '.$guard.' before transport still transmitted/wrote data');
}
[$service, $directory, $manifest, $runId, $ids] = preparedBatch(['max_contacts' => 3]);
$service->run($runId);
$manual = batchService($directory); $manual->now = $service->now + 59; $review = $manual->inspectGeocoding($ids[2]);
batchCheck($manual->geocodeAddress($ids[2], $review['fingerprint'])['status'] === 'backoff' && !$manual->requests, 'Manual traffic bypassed shared 60s pacing');
$manual->now++; batchCheck($manual->geocodeAddress($ids[2], $review['fingerprint'])['status'] === 'updated', 'Manual interface no longer processes approved individual addresses');
$service->now = $manual->now; $mixed = $service->run($runId);
batchCheck($mixed['counts']['skipped_valid'] === 1 && $mixed['external_requests'] === 0 && $mixed['cursor'] === 2, 'Manual update/batch resume repeated a request');
$service->now += 60; $service->run($runId); $quota = json_decode(file_get_contents($directory.'/manual.json'), true);
batchCheck($quota['requests_today'] === 3 && $quota['manual_requests_today'] === 1, 'Shared and manual quota accounting diverged');

[$service, $directory, $manifest, $runId, $ids] = preparedBatch(['max_contacts' => 3]);
$parallel = batchService($directory, ['max_contacts' => 3]);
$service->duringRequest = static function() use ($parallel, $runId): void { batchCheck($parallel->run($runId)['status'] === 'busy' && !$parallel->requests, 'Concurrent batch ignored shared progress lock'); };
$service->run($runId);
// Rewind only synthetic progress to reproduce a crash after the DB apply, before checkpoint.
$state = json_decode(file_get_contents($directory.'/batch.json'), true); $state['cursor'] = 0; $state['jobs'][0]['status'] = 'pending'; file_put_contents($directory.'/batch.json', json_encode($state));
$parallel->now = $service->now;
$recovered = $parallel->run($runId);
batchCheck($recovered['counts']['skipped_valid'] === 1 && !$parallel->requests && $recovered['cursor'] === 1, 'Crash recovery repeated an already applied request');

foreach (['selection', 'coordinates', 'contact_date'] as $race) {
	[$service, $directory, $manifest, $runId, $ids] = preparedBatch(['max_contacts' => 3]);
	$service->duringRequest = static function() use ($db, $ids, $race): void {
		if ($race === 'selection') $db->insert('address', ['parentid' => 50003, 'clientid' => 100, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'ordering' => -2, 'street' => 'Different Street 1', 'postcode' => '12345', 'city' => 'Batch City', 'country' => 'DE']);
		elseif ($race === 'coordinates') $db->update('address', ['latitude' => 53, 'longitude' => 9], ['id = ?' => $ids[3]]);
		else $db->update('contact', ['created' => '2026-11-30'], ['id = ?' => 50003]);
	};
	$outcome = $service->run($runId);
	batchCheck($outcome['counts']['changed'] === 1, 'Concurrent '.$race.' change accepted');
	batchCheck($race === 'coordinates' ? (float)$db->fetchOne('SELECT latitude FROM address WHERE id = ?', $ids[3]) === 53.0 : $db->fetchOne('SELECT latitude FROM address WHERE id = ?', $ids[3]) === null, 'Concurrent '.$race.' change overwritten');
}
[$service, $directory, $manifest, $runId, $ids] = preparedBatch(['max_contacts' => 3]);
$db->update('address', ['city' => 'Changed City'], ['id = ?' => $ids[3]]);
$outcome = $service->run($runId);
batchCheck($outcome['counts']['changed'] === 1 && count($service->requests) === 1 && $service->requests[0]['street'] === 'synthetic street 2', 'Stale privacy approval transmitted changed address');
mapActor(300); batchReject(fn() => $model->candidates()); batchReject(fn() => $service->status()); mapActor(100);
batchReject(fn() => batchService($work.'/invalid-limit', ['daily_limit' => 501]));
batchReject(fn() => $model->candidates(Contacts_Model_GeocodingBatch::MAX_PAGE_SIZE + 1));

// Exercise the actual CLI with URL access disabled and column-restricted test credentials.
function batchCli(array $arguments): array {
	global $root;
	$process = proc_open(array_merge(['php', '-d', 'allow_url_fopen=0', $root.'/application/cli/contact-geocoding-batch.php'], $arguments), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
	return [proc_close($process), $output, $errors];
}
$ids = batchFixture(); $cliDir = $work.'/batch-cli'; $cliConfig = $work.'/batch-cli.ini'; $cliManifest = $work.'/batch-cli-approvals.json';
function batchCliConfig(bool $enabled): void {
	global $cliConfig, $cliDir;
	file_put_contents($cliConfig, "[manual]\nendpoint = https://nominatim.openstreetmap.org/search\nuser_agent = \"DEWAWI/1.1.7 (+https://dewawi.com/contact)\"\nstate_dir = ".$cliDir."\n[batch]\nenabled = ".($enabled ? 'true' : 'false')."\nusage_approval_reference = TEST-CLI-USAGE\nprivacy_approval_reference = TEST-CLI-PRIVACY\napproval_valid_until = 2099-01-01T00:00:00Z\n");
}
batchCliConfig(false);
$args = ['--client=100', '--user=100', '--config='.$work.'/manual-database.ini', '--environment=synthetic', '--geocoding-config='.$cliConfig];
[$exit, $output, $errors] = batchCli($args);
batchCheck($exit === 0 && $errors === '' && json_decode($output, true)['status'] === 'not_prepared' && !is_dir($cliDir), 'CLI status created state');
$before = $db->fetchAll('SELECT * FROM address ORDER BY id');
[$exit, $output, $errors] = batchCli(array_merge($args, ['--mode=dry-run']));
batchCheck($exit === 0 && $errors === '' && !str_contains($output, 'PRIVATE_') && !is_dir($cliDir) && $db->fetchAll('SELECT * FROM address ORDER BY id') === $before, 'CLI dry-run changed data or emitted PII');
[$exit, $output, $errors] = batchCli(array_merge(array_replace($args, [2 => '--config='.$work.'/readonly.ini']), ['--mode=dry-run']));
batchCheck($exit === 0 && $errors === '' && json_decode($output, true)['database_writes'] === 0, 'Dry-run requires writable database credentials');
$primer = batchService($cliDir); $review = $primer->inspectGeocoding($ids[1]); $primer->geocodeAddress($ids[1], $review['fingerprint']);
[$exit, $output, $errors] = batchCli(array_merge($args, ['--mode=review', '--output='.$cliManifest]));
batchCheck($exit === 0 && $errors === '' && json_decode($output, true)['approved'] === 0, 'CLI review implicitly approved data');
$data = json_decode(file_get_contents($cliManifest), true);
foreach ($data['addresses'] as &$entry) if ($entry['id'] === 50005) { $entry['public'] = $entry['non_personal'] = $entry['non_confidential'] = true; $entry['review_reference'] = 'TEST-CLI-REVIEW'; }
unset($entry); file_put_contents($cliManifest, json_encode($data));
[$exit, $output, $errors] = batchCli(array_merge($args, ['--mode=prepare', '--approvals='.$cliManifest]));
$prepared = json_decode($output, true);
batchCheck($exit === 0 && $errors === '' && $prepared['total'] === 1, 'CLI prepare included unapproved records');
$runArgs = array_merge($args, ['--mode=run', '--execute='.$prepared['run_id']]);
[$exit, $output, $errors] = batchCli($runArgs);
batchCheck($exit === 2 && $errors === '' && json_decode($output, true)['status'] === 'blocked', 'CLI bypassed disabled activation gate');
foreach ([101, 102, 103, 105, 106, 300, 9999] as $user) {
	[$exit, $output, $errors] = batchCli(array_replace($args, [1 => '--user='.$user]));
	if ($user === 106) { batchCheck($exit === 0, 'View-only admin cannot inspect status'); continue; }
	batchCheck($exit === 1 && $output === '' && !str_contains($errors, 'synthetic-only'), 'Unauthorized CLI actor admitted');
}
[$exit, $output, $errors] = batchCli(array_merge(array_replace($args, [1 => '--user=104']), ['--mode=prepare', '--approvals='.$cliManifest]));
batchCheck($exit === 1 && $output === '', 'View-only actor prepared write-capable batch');
[$exit, $output, $errors] = batchCli(array_replace($args, [1 => '--user=200']));
batchCheck($exit === 0 && $errors === '' && json_decode($output, true)['run_id'] === $prepared['run_id'], 'Authorized child sharing changed');
batchCliConfig(true);
$lock = fopen($cliDir.'/batch.lock', 'c'); flock($lock, LOCK_EX);
[$exit, $output, $errors] = batchCli($runArgs);
batchCheck($exit === 0 && $errors === '' && json_decode($output, true)['status'] === 'busy', 'Concurrent real CLI ignored batch lock'); fclose($lock);
[$exit, $output, $errors] = batchCli($runArgs); $finished = json_decode($output, true);
batchCheck($exit === 0 && $errors === '' && $finished['status'] === 'complete' && $finished['external_requests'] === 0 && $finished['database_writes'] === 1 && $finished['cached'] === 1, 'Actual CLI failed cached apply with column-restricted credentials');
foreach ([[], ['--client=200'], array_merge($args, ['--all=1']), array_merge($args, ['--mode=run']), array_merge($args, ['--mode=prepare']), array_merge($args, ['--execute='.str_repeat('0', 32)]), array_merge($args, ['--client=100'])] as $invalid) {
	[$exit, $output, $errors] = batchCli($invalid); batchCheck($exit === 1 && $output === '' && !str_contains($errors, $work), 'Invalid CLI arguments accepted or details leaked');
}
[$exit, $output, $errors] = batchCli(['--help']);
batchCheck($exit === 0 && $errors === '' && str_contains($output, '# * * * * *') && str_contains($output, 'Disabled by default'), 'Safe CLI/cron documentation missing');
$db->query('CREATE TEMPORARY TABLE batch_digits (n INT NOT NULL)');
$db->query('INSERT INTO batch_digits VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9)');
$db->query("INSERT INTO contact (id, clientid, catid, created, name1) SELECT 100000 + seq.n, 100, 0, '2026-12-01', 'Synthetic scale fixture' FROM (SELECT a.n + b.n*10 + c.n*100 + d.n*1000 + e.n*10000 AS n FROM batch_digits a CROSS JOIN batch_digits b CROSS JOIN batch_digits c CROSS JOIN batch_digits d CROSS JOIN batch_digits e) seq WHERE seq.n BETWEEN 1 AND 28510");
$db->query("INSERT INTO address (parentid, clientid, module, controller, type) SELECT id, 100, 'contacts', 'contact', 'billing' FROM contact WHERE id BETWEEN 100001 AND 128510");
$started = microtime(true); $scale = $model->candidates(28506);
batchCheck(count($scale) === 28506 && (int)$scale[0]['id'] === 128510 && (int)$scale[28505]['id'] === 100005, '28,506-contact selection cap/sort failed');
echo 'PASS: 28,506 synthetic candidates selected in '.round(microtime(true) - $started, 3)." seconds; no API calls\n";
echo 'PASS: '.$checks." batch ordering, approvals, resume, quotas, cache, concurrency, map parity and CLI security cases\n";
