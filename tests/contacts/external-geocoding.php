<?php

require __DIR__.'/map-bootstrap.php';
require $root.'/tools/one-time-geocoding/Geocoding.php';
date_default_timezone_set('Europe/Berlin'); mapActor(100);
$db->update('permission', ['contacts' => json_encode(['contact' => ['view', 'edit']])], ['userid = ?' => 100]);
$db->insert('user', ['id' => 104, 'clientid' => 100, 'password' => '', 'activated' => 1]);
$db->insert('permission', ['userid' => 104, 'clientid' => 100, 'contacts' => json_encode(['contact' => ['view']])]);
$checks = 0; $scenario = 0;
chmod($work.'/readonly.ini', 0600);
function externalCheck(bool $condition, string $message): void { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; }
function externalReject(callable $callback): void { try { $callback(); } catch (Throwable $error) { externalCheck(true, 'Rejected'); return; } throw new RuntimeException('Unsafe external geocoding action accepted'); }
function externalFixture(): array {
	global $db;
	$db->update('contact', ['deleted' => 1], ['clientid = ?' => 100]);
	$db->delete('address', 'parentid BETWEEN 70001 AND 70009'); $db->delete('contact', 'id BETWEEN 70001 AND 70009');
	$ids = [];
	foreach (range(1, 9) as $n) {
		$db->insert('contact', ['id' => 70000 + $n, 'clientid' => 100, 'catid' => 0, 'created' => '2026-11-18', 'name1' => 'PRIVATE_EXTERNAL_CONTACT']);
		if ($n === 7) continue;
		$street = 'Synthetic Street '.($n <= 2 ? 1 : $n - 3);
		if ($n === 2) $street = "Synthetic\tStreet 1";
		$db->insert('address', ['parentid' => 70000 + $n, 'clientid' => 100, 'module' => 'contacts', 'controller' => 'contact', 'type' => $n === 9 ? 'shipping' : 'billing', 'street' => $n === 4 ? null : $street, 'postcode' => '12345', 'city' => 'Example City', 'country' => 'DE', 'geoupdated' => '2026-11-01', 'latitude' => $n === 3 ? 51 : null, 'longitude' => $n === 3 ? 7 : null]);
		$ids[$n] = (int)$db->lastInsertId();
	}
	$db->insert('address', ['parentid' => 70001, 'clientid' => 100, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'ordering' => 1, 'latitude' => 52, 'longitude' => 8]);
	$db->insert('address', ['parentid' => 70001, 'clientid' => 300, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'ordering' => -1, 'latitude' => 53, 'longitude' => 9]);
	return $ids;
}
function externalFeature(string $street = 'synthetic street', string $number = '1'): array {
	return ['type' => 'Feature', 'properties' => ['lat' => 51.2, 'lon' => 6.7, 'country_code' => 'de', 'postcode' => '12345', 'city' => 'Example City', 'street' => $street, 'housenumber' => $number, 'result_type' => 'building', 'rank' => ['match_type' => 'full_match', 'confidence' => 1, 'confidence_city_level' => 1, 'confidence_street_level' => 1, 'confidence_building_level' => 1], 'datasource' => ['attribution' => 'Synthetic attribution']], 'geometry' => ['type' => 'Point', 'coordinates' => [6.7, 51.2]]];
}
class ExternalFixtureTool extends OneTimeGeocoding {
	public int $clock = 1800000000;
	public array $calls = [];
	public string $behavior = 'pending';
	public bool $withAmenity = false;
	public bool $cityOnly = false;
	public static array $remote = [];
	public $beforeApply = null;
	protected function now(): int { return $this->clock; }
	protected function http(string $method, ?string $id, ?array $body, string $key): array {
		$this->calls[] = [$method, $id, $body];
		externalCheck(!str_contains(json_encode($body), 'PRIVATE_') && !str_contains(json_encode($body), 'contact_id') && !str_contains(json_encode($body), 'address_id'), 'Provider received contact identifiers or names');
		if ($method === 'POST') {
			externalCheck($body['api'] === '/v1/geocode/search' && $body['priority'] === .5 && $body['params']['limit'] === 3 && count($body['inputs']) <= 1000, 'Batch API contract changed');
			$id = md5(json_encode($body)); $items = [];
			foreach ($body['inputs'] as $input) {
				externalCheck(array_keys($input) === ['params'] && array_keys($input['params']) === ['text', 'filter'], 'Unexpected provider input fields');
				[$street] = explode(', ', $input['params']['text']); preg_match('/^(.+) (\d+)$/D', $street, $parts);
				$feature = externalFeature($parts[1], $parts[2]);
				if ($parts[2] === '3') $feature['properties']['rank']['confidence'] = .5;
				if ($this->cityOnly) { $feature['properties']['result_type'] = 'street'; $feature['properties']['rank']['match_type'] = 'match_by_street'; }
				$features = [$feature];
				if ($this->withAmenity) {
					$amenity = externalFeature($parts[1], $parts[2]); $amenity['properties']['result_type'] = 'amenity'; $amenity['properties']['rank']['match_type'] = 'inner_part'; $features[] = $amenity;
				}
				$items[] = ['params' => $input['params'], 'result' => ['type' => 'FeatureCollection', 'features' => $features]];
			}
			self::$remote[$id] = ['api' => '/v1/geocode/search', 'id' => $id, 'results' => array_reverse($items)];
			if ($this->behavior === 'timeout') return [0, [], 0];
			if ($this->behavior !== 'immediate') return [202, ['id' => $id, 'status' => 'pending', 'url' => 'https://invalid.test/?apiKey=DO_NOT_STORE'], 0];
		}
		if ($this->behavior === 'rate_limit') return [429, [], $this->clock + 7200];
		if ($this->behavior === 'expired') return [404, [], 0];
		$response = self::$remote[$id];
		if ($this->behavior === 'no_echo') { unset($response['api']); $response['results'] = array_reverse($response['results']); foreach ($response['results'] as &$item) unset($item['params']); unset($item); }
		if ($this->behavior === 'bad_mapping') $response['results'][0]['params']['text'] = 'Wrong address';
		return [200, $response, 0];
	}
	protected function apply(array $record, array $point, string $updated): bool {
		if ($this->beforeApply) ($this->beforeApply)($record);
		return parent::apply($record, $point, $updated);
	}
}
function externalTool(?string $directory = null): array {
	global $root, $work, $db, $scenario;
	$directory = $directory ?? $work.'/external-'.(++$scenario); if (!is_dir($directory)) mkdir($directory, 0700);
	$address = new OneTimeGeocodingAddress($root, 'localhost', 'root', '', 'contact_map_test', ['endpoint' => OneTimeGeocoding::ENDPOINT], 0, $work.'/mysql.sock');
	return [new ExternalFixtureTool($directory, $db, new Contacts_Model_GeocodingBatch(), $address), $directory];
}
function externalProvider(string $directory): array {
	$quota = $directory.'-quota'; if (!is_dir($quota)) mkdir($quota, 0700);
	return ['enabled' => true, 'api_key' => 'TEST_ONLY_API_KEY_0123456789', 'quota_dir' => $quota, 'batch_size' => 2, 'processing_approval' => 'TEST-PRIVACY', 'terms_approval' => 'TEST-TERMS'];
}
function externalPermit(string $digest): array { return ['enabled' => true, 'plan_sha256' => $digest, 'approval_reference' => 'TEST-RELEASE', 'backup_reference' => 'TEST-SNAPSHOT', 'attribution_reference' => 'TEST-LICENSE']; }
function externalCli(array $args): array {
	global $root;
	$process = proc_open(array_merge(['php', '-d', 'allow_url_fopen=0', $root.'/tools/one-time-geocoding/cli.php'], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); return [proc_close($process), $output, $error];
}
$target = OneTimeGeocoding::digest(['localhost', 0, $work.'/mysql.sock', 'contact_map_test']);
$ids = externalFixture(); [$tool, $directory] = externalTool(); $provider = externalProvider($directory);
$before = $db->fetchAll('SELECT * FROM address ORDER BY id');
$db->query('SET TRANSACTION READ ONLY'); $db->beginTransaction(); $export = $tool->export($target); $db->rollBack(); $hash = $export['export_sha256'];
externalCheck($export['selected'] === 6 && $export['exported'] === 5 && $export['unique_requests'] === 4 && $export['duplicate_requests_saved'] === 1 && $export['excluded'] === ['missing_street' => 1] && $export['remaining_older'] === 0, 'Export differs from map-selected missing coordinates');
externalCheck($db->fetchAll('SELECT * FROM address ORDER BY id') === $before && !str_contains(json_encode($export), 'Synthetic'), 'Export changed SQL or leaked postal text');
externalReject(fn() => $tool->export($target)); externalReject(fn() => new OneTimeGeocoding($directory));
externalReject(fn() => $tool->geocode(array_replace($provider, ['enabled' => false]), $hash));
externalReject(fn() => $tool->geocode($provider, str_repeat('0', 64)));
externalCheck(!$tool->calls, 'Disabled/unreleased mode made HTTP calls');
externalCheck($tool->geocode($provider, $hash)['status'] === 'pending', 'POST did not persist a provider job');
externalCheck($tool->geocode($provider, $hash)['status'] === 'waiting' && count($tool->calls) === 1, 'Repeated invocation bypassed pacing');
unset($tool); [$tool] = externalTool($directory); $tool->clock += 300;
externalCheck($tool->geocode($provider, $hash)['status'] === 'done' && $tool->calls[0][0] === 'GET', 'Interrupted job was resubmitted');
$tool->clock += 60; externalCheck($tool->geocode($provider, $hash)['status'] === 'pending', 'Next batch was blocked');
$tool->clock += 300; externalCheck($tool->geocode($provider, $hash)['status'] === 'done', 'Second job not collected');
$tool->clock += 60; externalCheck($tool->geocode($provider, $hash)['status'] === 'complete', 'Completed inputs were submitted again');
$ledger = json_decode(file_get_contents($provider['quota_dir'].'/quota.json'), true);
externalCheck(array_sum(array_column($ledger['entries'], 'credits')) === 6 && array_sum(array_column($ledger['entries'], 'inputs')) === 4, 'Credits, first polling reservation or deduplication incorrect');
externalCheck(!str_contains(file_get_contents($directory.'/jobs.json'), 'DO_NOT_STORE') && !str_contains(file_get_contents($directory.'/jobs.json'), $provider['api_key']), 'Provider URL/API key persisted');
$db->update('address', ['city' => 'Changed City'], ['id = ?' => $ids[8]]);
$plan = $tool->plan('plan-main.json', $target); $planHash = $plan['plan_sha256'];
externalCheck($plan['counts'] === ['planned' => 3, 'conflicts' => 1, 'rejected' => 1, 'pending' => 0, 'deferred' => 0] && $plan['rejected_reasons'] === ['low_or_missing_accuracy' => 1], 'Dry-run did not distinguish conflicts and rejected matches');
externalReject(fn() => $tool->plan('plan-main.json', $target));
externalReject(fn() => $tool->import('plan-main.json', $target, array_replace(externalPermit($planHash), ['enabled' => false]), $planHash));
externalReject(fn() => $tool->import('plan-main.json', 'wrong-target', externalPermit($planHash), $planHash));
$user = Zend_Registry::get('User'); $user['id'] = 104; Zend_Registry::set('User', $user); Zend_Auth::getInstance()->getStorage()->write((object)['id' => 104, 'clientid' => 100]);
externalReject(fn() => $tool->import('plan-main.json', $target, externalPermit($planHash), $planHash)); mapActor(100);
$result = $tool->import('plan-main.json', $target, externalPermit($planHash), $planHash, 1);
externalCheck($result['database_writes'] === 1 && is_file($directory.'/'.$result['backup']) && (fileperms($directory.'/'.$result['backup']) & 0077) === 0, 'Import did not persist a private before-image first');
externalCheck($result['status'] === 'partial' && $result['remaining'] === 2, 'Bounded import lost its remaining-record count');
$journal = $directory.'/'.$result['journal']; $lines = file($journal); array_pop($lines); file_put_contents($journal, implode('', $lines));
$recovered = $tool->import('plan-main.json', $target, externalPermit($planHash), $planHash, 1);
externalCheck($recovered['counts']['recovered'] === 1 && $recovered['database_writes'] === 0, 'Crash after commit was not recovered without a duplicate write');
externalCheck($tool->import('plan-main.json', $target, externalPermit($planHash), $planHash)['database_writes'] === 2, 'Remaining updates did not resume');
$complete = $tool->import('plan-main.json', $target, externalPermit($planHash), $planHash);
externalCheck($complete['database_writes'] === 0 && $complete['status'] === 'complete' && $complete['remaining'] === 0, 'Repeated import was not idempotent or complete');
$journalBytes = file_get_contents($journal); $invalidEntry = json_decode(explode("\n", $journalBytes)[0], true); $invalidEntry['status'] = 'unsupported';
file_put_contents($journal, json_encode($invalidEntry)."\n");
externalReject(fn() => $tool->import('plan-main.json', $target, externalPermit($planHash), $planHash)); file_put_contents($journal, $journalBytes);
foreach ([1, 2, 5] as $n) {
	$row = $db->fetchRow('SELECT * FROM address WHERE id = ?', [$ids[$n]]); $original = array_column($before, null, 'id')[$ids[$n]];
	externalCheck((float)$row['latitude'] === 51.2 && (float)$row['longitude'] === 6.7, 'Validated point not imported');
	foreach (['latitude', 'longitude', 'geoupdated'] as $field) unset($row[$field], $original[$field]);
	externalCheck($row === $original, 'Import changed columns outside the three authorized fields');
}

// Validate malformed, coarse, mismatching and ambiguous results without any HTTP.
$fields = ['street' => 'synthetic street 1', 'postcode' => '12345', 'city' => 'example city', 'country' => 'de'];
$feature = externalFeature();
externalCheck(isset(OneTimeGeocoding::validate(['type' => 'FeatureCollection', 'features' => [$feature]], $fields)['point']), 'Valid building rejected');
$bad = [null, [], ['properties' => 1], ['geometry' => ['coordinates' => 1]]];
foreach ([['country_code', 'at'], ['postcode', '99999'], ['city', 'Wrong'], ['street', 'Wrong'], ['housenumber', '2'], ['result_type', 'street'], ['lat', true], ['lon', '0; DELETE FROM address'], ['rank', []], ['lat', 91], ['lon', '1e309']] as [$field, $value]) { $copy = $feature; $copy['properties'][$field] = $value; $bad[] = $copy; }
$copy = $feature; $copy['geometry']['coordinates'] = [7, 52]; $bad[] = $copy;
$copy = $feature; $copy['bbox'] = [0, 0, 100, 80]; $bad[] = $copy;
foreach ($bad as $invalid) externalCheck(isset(OneTimeGeocoding::validate(['type' => 'FeatureCollection', 'features' => [$invalid]], $fields)['rejected']), 'Invalid feature accepted');
externalCheck(isset(OneTimeGeocoding::validate(['type' => 'FeatureCollection', 'features' => [$feature, $feature]], $fields)['rejected']), 'Ambiguous matches accepted');

// Only fully qualified buildings compete, independently of provider result order.
$point = OneTimeGeocoding::validate(['type' => 'FeatureCollection', 'features' => [$feature]], $fields);
$amenity = $feature; $amenity['properties']['result_type'] = 'amenity'; $amenity['properties']['rank']['match_type'] = 'inner_part';
foreach ([[$feature, $amenity], [$amenity, $feature]] as $features) externalCheck(OneTimeGeocoding::validate(['type' => 'FeatureCollection', 'features' => $features], $fields) === $point, 'Exact building was disqualified by a subordinate amenity or result order');
$otherBuilding = $feature; $otherBuilding['properties']['lat'] = 51.25; $otherBuilding['properties']['lon'] = 6.75; $otherBuilding['geometry']['coordinates'] = [6.75, 51.25];
externalCheck(isset(OneTimeGeocoding::validate(['type' => 'FeatureCollection', 'features' => [$otherBuilding]], $fields)['point']), 'Conflicting fixture is not an equally qualified building');
foreach ([[$feature, $otherBuilding], [$otherBuilding, $feature], [$amenity, $feature, $otherBuilding]] as $features) externalCheck(OneTimeGeocoding::validate(['type' => 'FeatureCollection', 'features' => $features], $fields) === ['rejected' => 'missing_or_ambiguous'], 'Competing building coordinates were resolved by result order');
foreach ([['country_code', 'at'], ['postcode', '99999'], ['city', 'Wrong City'], ['street', 'Wrong Street'], ['housenumber', '2']] as [$field, $value]) {
	$wrong = $feature; $wrong['properties'][$field] = $value;
	externalCheck(OneTimeGeocoding::validate(['type' => 'FeatureCollection', 'features' => [$wrong, $amenity]], $fields) === ['rejected' => 'no_suitable_building'], 'Postal mismatch became eligible beside an amenity');
	externalCheck(OneTimeGeocoding::validate(['type' => 'FeatureCollection', 'features' => [$wrong, $feature]], $fields) === $point, 'Nonmatching building competed with the exact postal match');
}
foreach (['confidence', 'confidence_city_level', 'confidence_street_level', 'confidence_building_level'] as $confidence) {
	foreach ([null, .94, 1.01, true, 'NaN'] as $value) {
		$weak = $feature;
		if ($value === null) unset($weak['properties']['rank'][$confidence]); else $weak['properties']['rank'][$confidence] = $value;
		externalCheck(isset(OneTimeGeocoding::validate(['type' => 'FeatureCollection', 'features' => [$weak, $amenity]], $fields)['rejected']), 'Missing/invalid accuracy was rescued by an amenity');
	}
}
$wrongGeometry = $feature; $wrongGeometry['geometry']['coordinates'] = [8, 53];
externalCheck(isset(OneTimeGeocoding::validate(['type' => 'FeatureCollection', 'features' => [$wrongGeometry, $amenity]], $fields)['rejected']), 'Contradictory point geometry was accepted');
$wrongBox = $feature; $wrongBox['bbox'] = [8, 53, 8.01, 53.01];
externalCheck(isset(OneTimeGeocoding::validate(['type' => 'FeatureCollection', 'features' => [$wrongBox, $amenity]], $fields)['rejected']), 'Contradictory bounding box was accepted');
$innerBuilding = $feature; $innerBuilding['properties']['rank']['match_type'] = 'inner_part';
externalCheck(isset(OneTimeGeocoding::validate(['type' => 'FeatureCollection', 'features' => [$innerBuilding, $amenity]], $fields)['rejected']), 'Inner-part building match became an exact match');
$buildingMatch = $feature; $buildingMatch['properties']['rank']['match_type'] = 'match_by_building';
externalCheck(OneTimeGeocoding::validate(['type' => 'FeatureCollection', 'features' => [$amenity, $buildingMatch]], $fields) === $point, 'Existing exact building match type regressed');
foreach ([[], [$amenity], [$amenity, $amenity], [null, $amenity]] as $features) externalCheck(isset(OneTimeGeocoding::validate(['type' => 'FeatureCollection', 'features' => $features], $fields)['rejected']), 'Response without a suitable building was accepted');

// Re-evaluate saved results into a new plan; never mutate an old rejection/release.
externalFixture(); [$candidateTool, $candidateDir] = externalTool(); $candidateTool->withAmenity = true; $candidateTool->behavior = 'immediate';
$candidateExport = $candidateTool->export($target, 1); $candidateTool->geocode(externalProvider($candidateDir), $candidateExport['export_sha256']);
$candidatePlan = $candidateTool->plan('plan-candidates.json', $target);
externalCheck($candidatePlan['counts'] === ['planned' => 1, 'conflicts' => 0, 'rejected' => 0, 'pending' => 0, 'deferred' => 0], 'Saved batch response with building and amenity remained rejected');
$legacy = json_decode(file_get_contents($candidateDir.'/plan-candidates.json'), true); $legacy['data']['updates'] = []; $legacy['data']['counts']['planned'] = 0; $legacy['data']['counts']['rejected'] = 1; $legacy['data']['rejected_reasons'] = ['missing_or_ambiguous' => 1]; $legacy['sha256'] = OneTimeGeocoding::digest($legacy['data']);
$legacyBackup = 'backup-'.$legacy['sha256'].'.json';
foreach (['plan-legacy.json', $legacyBackup] as $name) { file_put_contents($candidateDir.'/'.$name, json_encode($legacy)); chmod($candidateDir.'/'.$name, 0600); }
file_put_contents($candidateDir.'/permit.ini', "[import]\nenabled = false\nplan_sha256 = ".$legacy['sha256']."\n"); chmod($candidateDir.'/permit.ini', 0600);
$files = ['export.json', 'jobs.json', 'results-0.json', 'plan-legacy.json', $legacyBackup, 'permit.ini']; $hashes = [];
foreach ($files as $name) $hashes[$name] = hash_file('sha256', $candidateDir.'/'.$name);
$candidateBefore = $db->fetchAll('SELECT * FROM address ORDER BY id');
$reviewed = $candidateTool->plan('plan-reviewed.json', $target);
externalCheck($reviewed['counts']['planned'] === 1 && $reviewed['plan_sha256'] !== $legacy['sha256'] && count($candidateTool->calls) === 1 && $db->fetchAll('SELECT * FROM address ORDER BY id') === $candidateBefore, 'Reassessment reused a stale release, requested API data or imported automatically');
foreach ($files as $name) externalCheck(hash_file('sha256', $candidateDir.'/'.$name) === $hashes[$name], 'Reassessment changed a snapshot, backup or release');

// All database mutations below affect this disposable fixture only.
$other = Zend_Db::factory('Pdo_Mysql', ['host' => 'localhost', 'unix_socket' => $work.'/mysql.sock', 'username' => 'root', 'password' => '', 'dbname' => 'contact_map_test', 'charset' => 'utf8']);
foreach (['street' => 'Changed Street 9', 'city' => 'changed city', 'country' => 'AT', 'postcode' => '54321', 'parentid' => 70002, 'type' => 'shipping', 'clientid' => 300, 'module' => 'other', 'controller' => 'other', 'ordering' => -1, 'modified' => '2030-01-01', 'geoupdated' => '2030-01-01', 'latitude' => 52, 'longitude' => 8, 'deleted' => 1, 'earlier_address' => true, 'contact_created' => '2030-01-01', 'contact_deleted' => true] as $field => $value) {
	$fixtureIds = externalFixture(); [$race, $raceDir] = externalTool(); $raceProvider = externalProvider($raceDir); $race->behavior = 'immediate';
	$raceExport = $race->export($target, 1); $race->geocode($raceProvider, $raceExport['export_sha256']); $racePlan = $race->plan('plan.json', $target);
	$race->beforeApply = static function(array $record) use ($other, $field, $value): void {
		$id = $record['snapshot']['id'];
		if ($field === 'earlier_address') $other->insert('address', ['parentid' => $record['contact_id'], 'clientid' => 100, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'ordering' => -1]);
		elseif ($field === 'contact_created') $other->update('contact', ['created' => $value], ['id = ?' => $record['contact_id']]);
		elseif ($field === 'contact_deleted') $other->update('contact', ['deleted' => 1], ['id = ?' => $record['contact_id']]);
		else $other->update('address', [$field => $value], ['id = ?' => $id]);
	};
	$outcome = $race->import('plan.json', $target, externalPermit($racePlan['plan_sha256']), $racePlan['plan_sha256']);
	externalCheck($outcome['counts']['conflicts'] === 1 && $outcome['database_writes'] === 0, 'Concurrent '.$field.' change was overwritten'); unset($race);
}

$ids = externalFixture(); [$uncertain, $uncertainDir] = externalTool(); $uncertainProvider = externalProvider($uncertainDir); $uncertain->behavior = 'timeout';
$uncertainHash = $uncertain->export($target, 1)['export_sha256'];
externalCheck($uncertain->geocode($uncertainProvider, $uncertainHash)['status'] === 'unknown', 'Uncertain POST was not recorded');
$callCount = count($uncertain->calls); $uncertain->clock += 7200;
externalCheck($uncertain->geocode($uncertainProvider, $uncertainHash)['status'] === 'submission_unknown' && count($uncertain->calls) === $callCount, 'Uncertain POST was charged/submitted twice');
$providerId = array_key_last(ExternalFixtureTool::$remote); $uncertain->recoverJob(0, $providerId, $uncertainHash); $uncertain->behavior = 'rate_limit';
externalCheck($uncertain->geocode($uncertainProvider, $uncertainHash)['status'] === 'pending', '429 discarded the resumable provider ID');
$uncertain->clock += 60;
externalCheck($uncertain->geocode($uncertainProvider, $uncertainHash)['status'] === 'waiting', 'Retry-After was ignored');
$uncertain->clock += 7200; $uncertain->behavior = 'pending';
externalCheck($uncertain->geocode($uncertainProvider, $uncertainHash)['status'] === 'done', 'Rate-limited job did not resume');

[$quotaTool, $quotaDir] = externalTool(); $quotaProvider = externalProvider($quotaDir); $quotaProvider['daily_credits'] = 2;
$quotaHash = $quotaTool->export($target, 2)['export_sha256'];
externalCheck($quotaTool->geocode($quotaProvider, $quotaHash)['status'] === 'quota_wait' && !$quotaTool->calls, 'Credit cap allowed an unbudgeted batch');
$quotaProvider['daily_credits'] = 2800; $quotaProvider['daily_inputs'] = 1;
externalCheck($quotaTool->geocode($quotaProvider, $quotaHash)['status'] === 'quota_wait' && !$quotaTool->calls, 'Address day cap was bypassed');
mapActor(300); externalReject(fn() => $quotaTool->export($target)); mapActor(100);

// Shared-key quotas are global across workspaces and roll over conservatively.
$quotaProvider['daily_inputs'] = 5000; $quotaProvider['daily_credits'] = 4;
externalCheck($quotaTool->geocode($quotaProvider, $quotaHash)['status'] === 'pending', 'Budgeted job was blocked');
[$shared, $sharedDir] = externalTool(); $sharedHash = $shared->export($target, 2)['export_sha256']; $shared->clock += 60;
externalCheck($shared->geocode($quotaProvider, $sharedHash)['status'] === 'quota_wait' && !$shared->calls, 'Another workspace bypassed shared credits');
externalReject(fn() => $shared->geocode(array_replace($quotaProvider, ['api_key' => 'CHANGED_TEST_KEY_0123456789']), $sharedHash));
externalReject(fn() => $shared->geocode(array_replace($quotaProvider, ['batch_size' => 1001]), $sharedHash));
$shared->clock += 86401;
externalCheck($shared->geocode($quotaProvider, $sharedHash)['status'] === 'pending', 'Rolling budget never recovered');
externalReject(fn() => $shared->plan('export.json', $target));

foreach (['no_echo', 'bad_mapping', 'expired'] as $behavior) {
	[$mapping, $mappingDir] = externalTool(); $mappingProvider = externalProvider($mappingDir); $mappingHash = $mapping->export($target, 2)['export_sha256'];
	$mapping->geocode($mappingProvider, $mappingHash); $mapping->clock += 300; $mapping->behavior = $behavior;
	$outcome = $mapping->geocode($mappingProvider, $mappingHash); $mappedPlan = $mapping->plan('plan.json', $target);
	if ($behavior === 'no_echo') externalCheck($outcome['status'] === 'done' && $mappedPlan['counts']['planned'] === 1 && $mappedPlan['counts']['rejected'] === 1, 'Documented ordered response without optional params failed');
	else externalCheck($mappedPlan['counts']['rejected'] === 2 && $mappedPlan['pending_queries'] === 0, 'Invalid mapping/expired job blocks later inputs or produces updates');
	unset($mapping);
}
[$abandon, $abandonDir] = externalTool(); $abandonProvider = externalProvider($abandonDir); $abandonHash = $abandon->export($target, 1)['export_sha256']; $abandon->behavior = 'timeout';
$abandon->geocode($abandonProvider, $abandonHash); $budgetBefore = file_get_contents($abandonProvider['quota_dir'].'/quota.json');
$abandon->abandonJob(0, $abandonHash);
externalCheck($abandon->plan('plan.json', $target)['counts']['rejected'] === 1 && file_get_contents($abandonProvider['quota_dir'].'/quota.json') === $budgetBefore, 'Abandon reset quotas or left input unaccounted for');

// Exercise read-only CLI and negative release/tenant arguments; never enable network.
$cliDir = $work.'/external-cli'; mkdir($cliDir, 0700);
$baseArgs = ['--client=100', '--work='.$cliDir, '--config='.$work.'/readonly.ini', '--environment=synthetic', '--user=100'];
[$exit, $output, $errors] = externalCli(array_merge($baseArgs, ['--mode=export', '--limit=2']));
externalCheck($exit === 0 && $errors === '' && json_decode($output, true)['exported'] === 2 && !str_contains($output, 'Synthetic'), 'SELECT-only CLI export failed or exposed addresses');
[$exit, $output, $errors] = externalCli(array_merge($baseArgs, ['--mode=plan', '--plan=plan.json']));
externalCheck($exit === 0 && $errors === '' && json_decode($output, true)['counts']['pending'] === 2, 'SELECT-only dry-run attempted SQL writes');
foreach ([['--client=300', '--work='.$cliDir, '--mode=status'], ['--client=100', '--work='.$cliDir, '--mode=import'], ['--client=100', '--work='.$cliDir, '--mode=status', '--execute='.str_repeat('0', 64)]] as $invalid) {
	[$exit, $output, $errors] = externalCli($invalid); externalCheck($exit === 1 && $output === '' && !str_contains($errors, $work), 'CLI accepted invalid scope or leaked exception details');
}
externalCheck((fileperms($cliDir.'/export.json') & 0077) === 0 && (fileperms($cliDir.'/plan.json') & 0077) === 0, 'Private snapshots have public permissions');

// Real CLI import: SELECT-only fails, column-scoped UPDATE succeeds, no network is used.
[$cliTool] = externalTool($cliDir); $cliProvider = externalProvider($cliDir); $cliTool->behavior = 'immediate'; $cliHash = $cliTool->status()['export_sha256'];
$cliTool->geocode($cliProvider, $cliHash); unset($cliTool);
[$exit, $output, $errors] = externalCli(array_merge($baseArgs, ['--mode=plan', '--plan=plan-ready.json']));
$cliPlan = json_decode($output, true); externalCheck($exit === 0 && $cliPlan['counts']['planned'] === 1 && $cliPlan['counts']['rejected'] === 1, 'CLI result validation changed');
$permitPath = $work.'/external-import.ini';
file_put_contents($permitPath, "[import]\nenabled = true\nplan_sha256 = ".$cliPlan['plan_sha256']."\napproval_reference = TEST-CLI\nbackup_reference = TEST-BACKUP\nattribution_reference = TEST-ATTRIBUTION\n"); chmod($permitPath, 0600);
$importArgs = array_merge($baseArgs, ['--mode=import', '--plan=plan-ready.json', '--permit='.$permitPath, '--execute='.$cliPlan['plan_sha256']]);
$sqlBefore = $db->fetchAll('SELECT * FROM address ORDER BY id');
[$exit, $output, $errors] = externalCli($importArgs);
externalCheck($exit === 1 && $output === '' && $db->fetchAll('SELECT * FROM address ORDER BY id') === $sqlBefore, 'Read-only SQL account imported coordinates');
$db->query("CREATE USER 'external_writer'@'localhost' IDENTIFIED BY 'synthetic-only'");
$db->query('GRANT SELECT ON contact_map_test.* TO external_writer@localhost');
$db->query('GRANT UPDATE (latitude, longitude, geoupdated) ON contact_map_test.address TO external_writer@localhost');
$writer = $work.'/external-writer.ini'; file_put_contents($writer, str_replace('username = diagnostic', 'username = external_writer', file_get_contents($work.'/readonly.ini'))); chmod($writer, 0600);
[$exit, $output, $errors] = externalCli(array_replace($importArgs, [2 => '--config='.$writer]));
externalCheck($exit === 0 && $errors === '' && json_decode($output, true)['database_writes'] === 1, 'Column-scoped SQL writer could not import');
[$exit, $output, $errors] = externalCli(array_replace($importArgs, [2 => '--config='.$writer]));
externalCheck($exit === 0 && $errors === '' && json_decode($output, true)['database_writes'] === 0, 'Repeated CLI import modified coordinates again');

// Reject even rehashed plans whose points or snapshots no longer match the export/results.
$originalPlan = json_decode(file_get_contents($directory.'/plan-main.json'), true); $forged = $originalPlan; $forged['data']['updates'][0]['point']['latitude'] = '52.000000000'; $forged['sha256'] = OneTimeGeocoding::digest($forged['data']);
file_put_contents($directory.'/plan-forged.json', json_encode($forged)); chmod($directory.'/plan-forged.json', 0600);
externalReject(fn() => $tool->import('plan-forged.json', $target, externalPermit($forged['sha256']), $forged['sha256']));
$exportBytes = file_get_contents($cliDir.'/export.json'); $damaged = json_decode($exportBytes, true); $damaged['data']['clientid'] = 300; file_put_contents($cliDir.'/export.json', json_encode($damaged));
[$exit, $output, $errors] = externalCli(['--client=100', '--work='.$cliDir, '--mode=status']);
externalCheck($exit === 1 && $output === '', 'Changed export was accepted'); file_put_contents($cliDir.'/export.json', $exportBytes);

require __DIR__.'/external-geocoding-deferrals.php';
require __DIR__.'/external-geocoding-city.php';

// Exercise the intended inventory size, provider chunking and multiple budget windows.
$db->update('contact', ['deleted' => 1], ['clientid = ?' => 100]);
$db->query('CREATE TEMPORARY TABLE external_digits (n INT NOT NULL)'); $db->query('INSERT INTO external_digits VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9)');
$db->query("INSERT INTO contact (id, clientid, catid, created) SELECT 900000 + seq.n, 100, 0, '2026-11-18' FROM (SELECT a.n + b.n*10 + c.n*100 + d.n*1000 + e.n*10000 AS n FROM external_digits a CROSS JOIN external_digits b CROSS JOIN external_digits c CROSS JOIN external_digits d CROSS JOIN external_digits e) seq WHERE seq.n BETWEEN 1 AND 27863");
$db->query("INSERT INTO address (parentid, clientid, module, controller, type, street, postcode, city, country) SELECT id, 100, 'contacts', 'contact', 'billing', CONCAT('Synthetic Street ', id), '12345', 'Example City', 'DE' FROM contact WHERE id BETWEEN 900001 AND 927863");
[$large, $largeDir] = externalTool(); $large->cityOnly = true; $largeExport = $large->export($target); $largeFile = json_decode(file_get_contents($largeDir.'/export.json'), true);
externalCheck($largeExport['exported'] === 27863 && $largeExport['unique_requests'] === 27863 && $largeExport['remaining_older'] === 0 && array_column($largeFile['data']['records'], 'contact_id') === range(927863, 900001), 'Paged export omitted, duplicated or reordered contacts'); unset($largeFile);
$largeProvider = externalProvider($largeDir); $largeProvider['batch_size'] = 1000; $waits = 0;
for ($step = 0; $step < 200; $step++) {
	$progress = $large->run($largeProvider, $largeExport['export_sha256'], 'city');
	if ($progress['status'] === 'complete') break;
	if ($progress['status'] === 'quota_wait') { $waits++; $large->clock += 86401; } else $large->clock += 300;
}
externalCheck($progress['status'] === 'complete' && $waits === 5 && count($large->calls) === 56, 'Large run lost jobs, ignored limits or repeated submissions');
ExternalFixtureTool::$remote = []; $large->calls = [];
$largePlan = $large->plan('plan.json', $target, 'city');
externalCheck($largePlan['counts'] === ['planned' => 27863, 'conflicts' => 0, 'rejected' => 0, 'pending' => 0, 'deferred' => 0], 'Full-sized import dry-run incomplete');
externalCheck($largePlan['accuracy_counts'] === ['building' => 0, 'city' => 27863], 'Full-sized fallback city plan lost accuracy evidence');
$largeImport = $large->import('plan.json', $target, externalPermit($largePlan['plan_sha256']), $largePlan['plan_sha256'], 1);
externalCheck($largeImport['database_writes'] === 1 && $largeImport['remaining'] === 27862, 'Full-sized bounded import failed');
$largeResume = $large->import('plan.json', $target, externalPermit($largePlan['plan_sha256']), $largePlan['plan_sha256'], 1);
externalCheck($largeResume['database_writes'] === 1 && $largeResume['remaining'] === 27861, 'Full-sized backup verification/resume failed');
echo 'PASS: 27,863 synthetic unique addresses, 28 mocked batches, six budget windows; peak '.round(memory_get_peak_usage(true) / 1024 / 1024)." MiB\n";
echo 'PASS: '.$checks." removable Geoapify export, mocked batches, validation, quotas, resume, CAS import, backup and CLI security checks\n";
