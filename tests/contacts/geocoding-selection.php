<?php

require __DIR__.'/map-bootstrap.php';
mapActor(100);
$db->query("SET SESSION sql_mode = CONCAT(@@sql_mode, ',ONLY_FULL_GROUP_BY')");
$checks = 0;
function selectionCheck(bool $condition, string $message): void { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; }
function selectionReject(callable $callback): void {
	try { $callback(); } catch (InvalidArgumentException | RuntimeException | JsonException $error) { selectionCheck(true, 'Rejected'); return; }
	throw new RuntimeException('Unsafe selection/continuation accepted');
}
$db->update('contact', ['deleted' => 1], ['clientid = ?' => 100, 'id < ?' => 100000]);
$db->query('CREATE TEMPORARY TABLE selection_digits (n INT NOT NULL)');
$db->query('INSERT INTO selection_digits VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9)');
$db->query("INSERT INTO contact (id, clientid, catid, created, name1) SELECT 200000 + seq.n, 100, 0, '2026-12-02', 'PRIVATE_SELECTION_FIXTURE' FROM (SELECT a.n + b.n*10 + c.n*100 + d.n*1000 + e.n*10000 AS n FROM selection_digits a CROSS JOIN selection_digits b CROSS JOIN selection_digits c CROSS JOIN selection_digits d CROSS JOIN selection_digits e) seq WHERE seq.n BETWEEN 1 AND 30005");
$db->query("INSERT INTO address (parentid, clientid, module, controller, type, street, postcode, city, country) SELECT id, 100, 'contacts', 'contact', 'billing', 'Synthetic Street 1', '11111', 'Querytown', 'DE' FROM contact WHERE id BETWEEN 200001 AND 230005");
$addresses = [];
for ($n = 1; $n <= 14; $n++) {
	$db->insert('contact', ['id' => 90000 + $n, 'contactid' => 90000 + $n, 'clientid' => 100, 'catid' => 0, 'created' => $n >= 12 ? null : '2028-01-01', 'name1' => 'PRIVATE_SELECTION_CONTACT']);
	if ($n === 14) continue;
	$row = ['parentid' => 90000 + $n, 'clientid' => 100, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'street' => 'Synthetic Street 1', 'postcode' => '11111', 'city' => 'Querytown', 'country' => 'DE'];
	if ($n === 11) { $row['street'] = null; $row['city'] = ''; }
	if ($n === 10) $row['street'] = 'Synthetic Street without a number';
	if ($n === 9) $row['postcode'] = null;
	if ($n === 8) $row['city'] = '';
	if ($n === 7) $row['country'] = '';
	if ($n === 6) $row['country'] = 'DEU';
	if ($n === 5) $row['country'] = 'XX';
	if ($n === 4) $row['street'] = "Synthetic\nStreet 1";
	if ($n === 3) $row['street'] = '0';
	if ($n === 2) $row['postcode'] = '0';
	$db->insert('address', $row); $addresses[$n] = (int)$db->lastInsertId();
}
class SelectionFixtureService extends DEEC_AddressBatch {
	public int $now = 1800000000;
	public int $requests = 0;
	protected function geocodingTime(): int { return $this->now; }
	public function explain(array $row): array { return $this->review(90001, $row, [], true); }
	protected function requestGeocode(string $query): array {
		$this->requests++; parse_str($query, $fields);
		return [json_encode([['lat' => '51.2', 'lon' => '6.7', 'place_rank' => 30, 'boundingbox' => ['51.19', '51.21', '6.69', '6.71'], 'address' => ['country_code' => 'de', 'road' => 'synthetic street', 'house_number' => '1', 'postcode' => $fields['postalcode'], 'city' => $fields['city']]]]), ['HTTP/1.1 200 OK']];
	}
}
function selectionService(string $directory, array $policy = []): SelectionFixtureService {
	global $root, $work;
	$service = new SelectionFixtureService($root, 'localhost', 'root', '', 'contact_map_test', ['endpoint' => 'https://nominatim.openstreetmap.org/search', 'user_agent' => 'DEWAWI/1.1.7 (+https://dewawi.com/contact)', 'state_dir' => $directory], 0, $work.'/mysql.sock');
	$service->configureBatch(new Contacts_Model_GeocodingBatch(), $policy + ['selection_page_size' => 11, 'enabled' => true, 'usage_approval_reference' => 'TEST-ONLY', 'privacy_approval_reference' => 'TEST-ONLY', 'approval_valid_until' => '2099-01-01T00:00:00Z']);
	return $service;
}
function selectionApprove(string $path): array {
	$data = json_decode(file_get_contents($path), true);
	foreach ($data['addresses'] as &$entry) { $entry['public'] = $entry['non_personal'] = $entry['non_confidential'] = true; $entry['review_reference'] = 'TEST-REVIEW'; }
	unset($entry); file_put_contents($path, json_encode($data));
	return $data;
}
$model = new Contacts_Model_GeocodingBatch(); $directory = $work.'/selection-state'; $service = selectionService($directory);
$before = $db->fetchAll('SELECT * FROM address WHERE parentid BETWEEN 90001 AND 90014 ORDER BY id');
$dry = $service->dryRun();
selectionCheck($dry['counts']['selected'] === 11 && $dry['counts']['unreviewable'] === 9 && $dry['counts']['not_approved'] === 2 && $dry['counts']['approved'] === 0, 'Incorrect exclusion classification');
$expectedReasons = ['missing_street' => 1, 'unrecognized_house_number' => 2, 'missing_postcode' => 1, 'missing_city' => 1, 'missing_country' => 1, 'invalid_country_code' => 1, 'country_not_configured' => 1, 'control_characters_street' => 1]; ksort($expectedReasons);
selectionCheck($dry['exclusion_reasons'] === $expectedReasons && array_sum($dry['exclusion_reasons']) === 9, 'Primary exclusions do not partition unreviewable contacts');
selectionCheck($dry['validation_issues']['missing_city'] === 2 && array_sum($dry['validation_issues']) === 10 && $dry['validation_issues_overlap'], 'Overlapping field issues were lost/double-counted as primary reasons');
selectionCheck($dry['population']['candidates'] === 58528 && $dry['population']['without_billing_address'] === 1 && $dry['population']['missing_coordinates'] === 58529, 'K2A/map population and billing omissions cannot be reconciled');
selectionCheck($dry['selection_page']['remaining_older'] === 58517 && $dry['selection_page']['next_after'] !== null && !is_dir($directory) && $service->requests === 0, 'Dry-run truncates inventory or changes state');
selectionCheck($db->fetchAll('SELECT * FROM address WHERE parentid BETWEEN 90001 AND 90014 ORDER BY id') === $before && !str_contains(strtolower(json_encode($dry)), 'synthetic') && !str_contains(json_encode($dry), 'PRIVATE_'), 'Diagnosis mutated or exposed postal data');
$base = $service->getAddress($addresses[1]);
foreach (['street', 'postcode', 'city', 'country'] as $field) {
	$invalid = $base; $invalid[$field] = "\xFF";
	selectionCheck(in_array('invalid_utf8_'.$field, $service->explain($invalid)['reasons'], true), 'UTF-8 failure not classified by field');
}
$invalid = $base; $invalid['country'] = '0';
selectionCheck($service->explain($invalid)['reasons'] === ['invalid_country_code'], 'Zero country was accepted or misreported as empty');
$invalid = $base; $invalid['street'] = 'Synthetic Street 12 a';
selectionCheck($service->explain($invalid)['reasons'] === ['unrecognized_house_number'], 'House-number parser was broadly relaxed');
$invalid = $base; $invalid['street'] = '12 Synthetic Street';
selectionCheck($service->explain($invalid)['status'] === 'needs_geocoding', 'Existing supported prefix house format regressed');
selectionCheck($service->explain($service->getAddress($addresses[2]))['status'] === 'needs_geocoding', 'Existing postcode validation changed');

$expected = array_merge(range(90011, 90001), range(230005, 200001), range(128510, 100001), [90013, 90012]);
$seen = []; $after = null; $pages = 0;
do {
	$page = $model->page(7777, $after); $pages++;
	$seen = array_merge($seen, array_map('intval', array_column($page['rows'], 'id')));
	selectionCheck($page['selection_page']['remaining_older'] === count($expected) - count($seen), 'Older-candidate count differs from actual remaining inventory');
	$after = $page['selection_page']['next_after'];
} while ($after !== null);
selectionCheck($seen === $expected && count(array_unique($seen)) === 58528 && $pages > 1, 'Large inventory incomplete, duplicated or out of order');
$maximumPage = $model->page(Contacts_Model_GeocodingBatch::MAX_PAGE_SIZE);
selectionCheck(count($maximumPage['rows']) === 50000 && $maximumPage['selection_page']['remaining_older'] === 8528 && $maximumPage['selection_page']['next_after'] !== null, 'Resource page cap became an unreachable total cap');
$nullCursor = Contacts_Model_GeocodingBatch::encodeCursor(['id' => 100001, 'created' => '2026-12-01 00:00:00']);
selectionCheck(array_map('intval', array_column($model->candidates(3, $nullCursor), 'id')) === [90013, 90012], 'NULL creation date boundary skipped candidates');
$lastCursor = Contacts_Model_GeocodingBatch::encodeCursor(['id' => 90013, 'created' => null]);
selectionCheck(array_map('intval', array_column($model->candidates(3, $lastCursor), 'id')) === [90012], 'NULL tie-breaker skipped the next lower ID');
foreach (['invalid!', str_repeat('a', 257), Contacts_Model_GeocodingBatch::encodeCursor(['id' => 1, 'created' => '2026-02-30 00:00:00']), rtrim(strtr(base64_encode(json_encode(['version' => 1, 'clientid' => 300, 'id' => 1, 'created' => null])), '+/', '-_'), '=')] as $invalidCursor) selectionReject(fn() => $model->candidates(2, $invalidCursor));

$firstPage = $model->page(2); $after = $firstPage['selection_page']['next_after'];
$db->update('contact', ['deleted' => 1], ['id = ?' => 90011]);
selectionCheck(array_map('intval', array_column($model->candidates(2, $after), 'id')) === [90009, 90008], 'Deletion before cursor shifted the next page');
$db->insert('contact', ['id' => 90015, 'contactid' => 90015, 'clientid' => 100, 'catid' => 0, 'created' => '2030-01-01']);
$db->insert('address', ['parentid' => 90015, 'clientid' => 100, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'street' => 'Synthetic Street 1', 'postcode' => '11111', 'city' => 'Querytown', 'country' => 'DE']);
selectionCheck(array_map('intval', array_column($model->candidates(2, $after), 'id')) === [90009, 90008], 'Newer insert shifted continuation');

$staleService = selectionService($work.'/selection-stale', ['selection_page_size' => 2]);
$staleFile = $work.'/selection-stale.json'; $staleService->exportReview($staleFile); selectionApprove($staleFile);
$db->insert('contact', ['id' => 90016, 'clientid' => 100, 'catid' => 0, 'created' => '2031-01-01']);
$db->insert('address', ['parentid' => 90016, 'clientid' => 100, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'street' => 'Synthetic Street 1', 'postcode' => '11111', 'city' => 'Querytown', 'country' => 'DE']);
selectionCheck($staleService->dryRun($staleFile)['review_page_changed'], 'Review page drift was not reported');
selectionReject(fn() => $staleService->prepare($staleFile));
selectionCheck($staleService->requests === 0 && !file_exists($work.'/selection-stale/batch.json'), 'Changed page advanced past unreviewed candidates');
$db->update('contact', ['deleted' => 1], ['id = ?' => 90016]);

// A page with no reviewable entries still supplies a cursor to older candidates.
$emptyService = selectionService($work.'/selection-empty', ['selection_page_size' => 1]);
$emptyFile = $work.'/selection-empty.json'; $emptyService->exportReview($emptyFile, $after);
$empty = json_decode(file_get_contents($emptyFile), true);
selectionCheck($empty['addresses'] === [] && $empty['selection_page']['next_after'] !== null && $empty['selection_page']['selected'] === 1, 'Unreviewable page lost its continuation boundary');
$emptyPrepared = $emptyService->prepare($emptyFile);
selectionCheck($emptyPrepared['status'] === 'no_approved_candidates' && $emptyPrepared['selection_page']['next_after'] === $empty['selection_page']['next_after'], 'Empty approved run blocks older pages');

$firstFile = $work.'/selection-first.json'; $firstReview = $service->exportReview($firstFile); selectionApprove($firstFile);
// The immutable review page, not a later configuration size, determines prepare.
$service = selectionService($directory, ['selection_page_size' => 1]); $prepared = $service->prepare($firstFile); $run = $prepared['run_id'];
selectionCheck($prepared['selection_page']['page_size'] === 11 && $prepared['selection']['selected'] === 11 && $prepared['total'] === 3, 'Config change silently truncated a reviewed page');
selectionReject(fn() => $service->archive($run));
for ($attempt = 0; $attempt < 10 && $service->status()['status'] !== 'complete'; $attempt++) { $service->run($run); $service->now += 60; }
selectionCheck($service->status()['status'] === 'complete' && $service->requests === 2, 'First prepared page did not finish with deduplicated requests');
$quotaBefore = file_get_contents($directory.'/manual.json');
selectionReject(fn() => $service->archive(str_repeat('0', 32)));
$archived = $service->archive($run);
selectionCheck($archived['status'] === 'archived' && is_file($directory.'/batch-'.$run.'.json') && !file_exists($directory.'/batch.json') && file_get_contents($directory.'/manual.json') === $quotaBefore, 'Archiving discarded quota/cache or failed to preserve the completed run');
$second = selectionService($directory, ['selection_page_size' => 2]); $second->now = $service->now;
$secondFile = $work.'/selection-second.json'; $second->exportReview($secondFile, $archived['next_after']); $review = selectionApprove($secondFile);
selectionCheck(array_column($review['addresses'], 'id') === [230005, 230004], 'Next prepared page restarted at the newest contacts');
$secondPrepared = $second->prepare($secondFile); $result = $second->run($secondPrepared['run_id']);
selectionCheck($result['status'] === 'complete' && $result['cached'] === 2 && $result['external_requests'] === 0 && $second->requests === 0 && file_get_contents($directory.'/batch-'.$run.'.json') !== '', 'Consecutive page did not preserve cache/progress');

// Legacy version-1 manifests and checkpoints remain resumable without guessing a page boundary.
$legacy = json_decode(file_get_contents($secondFile), true); unset($legacy['selection_page']); file_put_contents($secondFile, json_encode($legacy));
$state = json_decode(file_get_contents($directory.'/batch.json'), true); unset($state['diagnostics']); $state['manifest_digest'] = hash_file('sha256', $secondFile); $state['cursor'] = 0;
foreach ($state['jobs'] as &$job) { $job['status'] = 'pending'; unset($job['cached']); $db->update('address', ['latitude' => null, 'longitude' => null, 'geoupdated' => null], ['id = ?' => $job['address_id']]); }
unset($job); file_put_contents($directory.'/batch.json', json_encode($state));
$legacyResume = selectionService($directory, ['selection_page_size' => 2]); $legacyResume->now = $second->now;
$resumed = $legacyResume->run($state['run_id']);
selectionCheck($resumed['status'] === 'complete' && $resumed['cached'] === 2 && $legacyResume->requests === 0, 'Legacy checkpoint/manifest no longer resumes');

function selectionCli(array $arguments): array {
	global $root;
	$process = proc_open(array_merge(['php', '-d', 'allow_url_fopen=0', $root.'/application/cli/contact-geocoding-batch.php'], $arguments), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
	return [proc_close($process), $output, $errors];
}
$cliArgs = ['--client=100', '--user=100', '--config='.$work.'/readonly.ini', '--environment=synthetic', '--geocoding-config='.$work.'/batch-cli.ini'];
[$exit, $output, $errors] = selectionCli(array_merge($cliArgs, ['--mode=dry-run', '--after='.$nullCursor]));
$cliDry = json_decode($output, true);
selectionCheck($exit === 0 && $errors === '' && $cliDry['counts']['selected'] === 2 && $cliDry['selection_page']['next_after'] === null && $cliDry['external_requests'] === 0 && $cliDry['database_writes'] === 0, 'Read-only CLI continuation failed');
$cliReview = $work.'/selection-cli-review.json';
[$exit, $output, $errors] = selectionCli(array_merge($cliArgs, ['--mode=review', '--after='.$nullCursor, '--output='.$cliReview]));
$cliTemplate = json_decode(file_get_contents($cliReview), true);
selectionCheck($exit === 0 && $errors === '' && array_column($cliTemplate['addresses'], 'id') === [90013, 90012] && array_column($cliTemplate['addresses'], 'public') === [false, false], 'CLI continuation inferred privacy approval or lost NULL tail');
$cliOld = json_decode(file_get_contents($work.'/batch-cli/batch.json'), true);
$cacheBeforeArchive = file_get_contents($work.'/batch-cli/manual.json');
[$exit, $output, $errors] = selectionCli(array_merge(array_replace($cliArgs, [1 => '--user=104']), ['--mode=archive', '--execute='.$cliOld['run_id']]));
selectionCheck($exit === 1 && $output === '' && is_file($work.'/batch-cli/batch.json'), 'Read-only application actor archived a batch');
[$exit, $output, $errors] = selectionCli(array_merge($cliArgs, ['--mode=archive', '--execute='.$cliOld['run_id']]));
selectionCheck($exit === 0 && $errors === '' && json_decode($output, true)['status'] === 'archived' && !file_exists($work.'/batch-cli/batch.json') && file_get_contents($work.'/batch-cli/manual.json') === $cacheBeforeArchive, 'CLI archive reset shared cache/quota');
foreach ([['--mode=archive'], ['--mode=status', '--after='.$nullCursor], ['--mode=dry-run', '--after=invalid!']] as $invalid) {
	[$exit, $output, $errors] = selectionCli(array_merge($cliArgs, $invalid));
	selectionCheck($exit === 1 && $output === '' && !str_contains($errors, $work), 'Invalid CLI cursor/archive arguments accepted or leaked');
}
mapActor(300); selectionReject(fn() => $model->page(2, $after)); selectionReject(fn() => $service->dryRun(null, $after)); mapActor(100);
echo 'PASS: '.$checks." selection diagnostics, 58,528-contact completeness, keyset ordering, legacy resume and consecutive runs\n";
