<?php

// Run only with the disposable database runner; HTTP responses are always mocked.
if (PHP_SAPI !== 'cli' || !preg_match('~^/tmp/dewawi-geocoding-[^/]+/mysql\.sock$~D', $argv[1] ?? '')) exit(1);
require dirname(__DIR__, 2).'/library/DEEC/Address.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$work = dirname($argv[1]);
$db = new mysqli('localhost', 'root', '', '', 0, $argv[1]);
$db->set_charset('utf8mb4');
$db->query('CREATE DATABASE geocoding_test');
$db->select_db('geocoding_test');
$schema = file_get_contents(dirname(__DIR__, 2).'/install/config/structure.sql');
foreach (['address', 'contact', 'country'] as $table) {
	preg_match('/CREATE TABLE IF NOT EXISTS `'.$table.'` .*?;/s', $schema, $match);
	$db->query($match[0]);
}
$db->query('INSERT INTO contact (id, clientid, catid) VALUES (1, 100, 0), (2, 100, 0), (3, 101, 0)');
$db->query("INSERT INTO country (clientid, code, name, language) VALUES (100, 'DE', 'DE', 'de'), (100, 'AT', 'AT', 'de')");
$other = new mysqli('localhost', 'root', '', 'geocoding_test', 0, $argv[1]);
$other->set_charset('utf8mb4');
$checks = $fixture = 0;
function check(bool $condition, string $message): void {
	global $checks;
	if (!$condition) throw new RuntimeException($message);
	$checks++;
}
function rejects(callable $callback): void {
	try { $callback(); } catch (InvalidArgumentException | RuntimeException | JsonException $error) { check(true, 'Rejected'); return; }
	throw new RuntimeException('Invalid geocoding options/state accepted');
}
function result(array $changes = []): array {
	return array_replace(['lat' => '51.2', 'lon' => '6.7', 'place_rank' => 30, 'boundingbox' => ['51.19', '51.21', '6.69', '6.71'], 'address' => ['country_code' => 'de', 'road' => 'Synthetic Street', 'house_number' => '1', 'postcode' => '12345', 'city' => 'Synthetic City']], $changes);
}
function options(array $changes = []): array {
	global $work, $fixture;
	return array_replace(['endpoint' => 'https://nominatim.openstreetmap.org/search', 'user_agent' => 'DEWAWI/1.1.7 (+https://dewawi.com/contact)', 'state_dir' => $work.'/state-'.$fixture], $changes);
}
class GeocodingTestAddress extends DEEC_Address {
	public int $requests = 0;
	public int $now = 1800000000;
	public array $response;
	public string $expectedPostcode = '12345';
	public string $expectedCountry = 'de';
	public string $expectedStreet = 'synthetic street 1';
	public string $expectedCity = 'synthetic city';
	public array $lastQuery = [];
	public $duringRequest = null;
	public function __construct(array $changes = []) {
		global $argv, $work;
		parent::__construct(dirname(__DIR__, 2), 'localhost', 'root', '', 'geocoding_test', options($changes), 0, $argv[1]);
		$this->response = [json_encode([result()]), ['HTTP/1.1 200 OK']];
	}
	protected function geocodingTime(): int { return $this->now; }
	public function explain(int $id): array { return $this->review($id, $this->getAddress($id), [], true); }
	protected function requestGeocode(string $query): array {
		$this->requests++;
		parse_str($query, $fields);
		$this->lastQuery = $fields;
		check($fields['countrycodes'] === $this->expectedCountry && $fields['format'] === 'jsonv2' && $fields['addressdetails'] === '1', 'Country restriction or response format missing');
		check($fields['street'] === $this->expectedStreet && $fields['postalcode'] === $this->expectedPostcode && $fields['city'] === $this->expectedCity, 'Unexpected postal query');
		check(!isset($fields['q']) && !str_contains($query, 'PRIVATE_CONTACT'), 'Query exposed contact fields or mixed query formats');
		if ($this->duringRequest) ($this->duringRequest)();
		return $this->response;
	}
}
function seed(array $changes = []): int {
	global $db, $fixture;
	$fixture++;
	$db->query('UPDATE address SET deleted = 1');
	$row = array_replace(['parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'clientid' => 100, 'street' => 'Synthetic Street 1', 'postcode' => '12345', 'city' => 'Synthetic City', 'country' => 'DE', 'latitude' => null, 'longitude' => null, 'geoupdated' => null, 'created' => '2020-01-01 10:00:00', 'modified' => '2020-01-02 12:00:00', 'deleted' => 0], $changes);
	$query = 'INSERT INTO address (`'.implode('`, `', array_keys($row)).'`) VALUES ('.implode(', ', array_fill(0, count($row), '?')).')';
	$statement = $db->prepare($query);
	$values = array_values($row);
	$statement->bind_param(str_repeat('s', count($values)), ...$values);
	$statement->execute(); $statement->close();
	return (int)$db->insert_id;
}
function stored(int $id): array { global $db; return $db->query('SELECT * FROM address WHERE id = '.$id)->fetch_assoc(); }
function attempt(GeocodingTestAddress $service, int $id, bool $change = false): array {
	$review = $service->inspectGeocoding($id);
	return $service->geocodeAddress($id, $review['fingerprint'] ?? '', $change);
}

$id = seed();
$service = new GeocodingTestAddress();
check(!$service->geocodeLatestAddress() && $service->requests === 0, 'Unattended public geocoding is still enabled');
$before = stored($id);
$review = $service->inspectGeocoding($id);
check($review['status'] === 'needs_geocoding' && !is_dir(options()['state_dir']), 'Review made changes');
check($service->geocodeAddress($id, '')['status'] === 'public_review_required' && $service->requests === 0, 'Unreviewed address was transmitted');
$db->query("UPDATE address SET city = 'Other City' WHERE id = $id");
check($service->geocodeAddress($id, $review['fingerprint'])['status'] === 'public_review_required' && $service->requests === 0, 'Old privacy confirmation approved a changed address');
$db->query("UPDATE address SET city = 'Synthetic City' WHERE id = $id");
$review = $service->inspectGeocoding($id);
$db->query("UPDATE address SET latitude = 49, longitude = 8 WHERE id = $id");
check($service->geocodeAddress($id, $review['fingerprint'], true)['status'] === 'public_review_required' && $service->requests === 0, 'Old review overwrote newly supplied manual coordinates');
$db->query("UPDATE address SET latitude = NULL, longitude = NULL WHERE id = $id");
check(attempt($service, $id)['status'] === 'updated', 'New billing address was not geocoded');
$after = stored($id);
check((float)$after['latitude'] === 51.2 && (float)$after['longitude'] === 6.7 && $after['modified'] === $before['modified'], 'Successful update changed non-geocoding fields');
check(attempt($service, $id)['status'] === 'unchanged' && $service->requests === 1, 'Unchanged address was queried again');
$db->query("UPDATE address SET department = 'Other department', modified = '2028-01-01' WHERE id = $id");
check(attempt($service, $id)['status'] === 'unchanged' && $service->requests === 1, 'Non-geographic change triggered a request');
$db->query("UPDATE address SET street = 'Synthetic Street 2' WHERE id = $id");
check($service->inspectGeocoding($id)['status'] === 'postal_address_changed', 'Same-second street change was not detected');
check($service->geocodeAddress($id, $review['fingerprint'])['status'] === 'public_review_required', 'Changed street used an old confirmation');
$db->query("UPDATE address SET street = 'Synthetic Street 1' WHERE id = $id");
check($service->inspectGeocoding($id)['status'] === 'unchanged', 'Restored/unchanged postal fields were treated as new');
foreach (['postcode' => '54321', 'city' => 'Other City', 'country' => 'AT'] as $field => $value) {
	$original = stored($id)[$field];
	$statement = $db->prepare('UPDATE address SET '.$field.' = ? WHERE id = ?');
	$statement->bind_param('si', $value, $id); $statement->execute();
	check($service->inspectGeocoding($id)['status'] === 'postal_address_changed', 'Tracked '.$field.' change was not detected');
	$statement->bind_param('si', $original, $id); $statement->execute(); $statement->close();
}
$db->query("UPDATE address SET street = '  SYNTHETIC   STREET 1  ' WHERE id = $id");
check(attempt($service, $id)['status'] === 'unchanged' && $service->requests === 1, 'Whitespace/case edit triggered another request');

foreach ([['street' => ''], ['street' => '   '], ['street' => null], ['postcode' => ''], ['city' => ''], ['country' => '0'], ['country' => 'XX'], ['country' => "DE\r\nX: y"], ['street' => 'Postbox'], ['clientid' => 101], ['parentid' => 3], ['deleted' => 1], ['type' => 'shipping'], ['module' => 'sales'], ['controller' => 'contactperson']] as $changes) {
	$id = seed($changes); $service = new GeocodingTestAddress(); $before = stored($id);
	check(!isset($service->inspectGeocoding($id)['fingerprint']), 'Ineligible address can be approved');
	check(attempt($service, $id)['status'] === 'public_review_required' && $service->requests === 0, 'Ineligible address made a request');
	check(stored($id) === $before, 'Ineligible address was modified');
}
foreach ([['modified' => '2019-12-31'], ['modified' => '2020-01-01'], ['modified' => null], ['modified' => '2025-01-01']] as $change) {
	$id = seed($change + ['latitude' => 48.1, 'longitude' => 8.2, 'geoupdated' => '2020-01-01 12:00:00']); $service = new GeocodingTestAddress(); $before = stored($id);
	check(attempt($service, $id)['status'] === 'existing_coordinates' && $service->requests === 0 && stored($id) === $before, 'Untracked valid coordinates were unnecessarily refreshed');
	check(attempt($service, $id, true)['status'] === 'updated', 'Explicitly verified postal change could not be processed');
}
$id = seed(['country' => 'AT']); $service = new GeocodingTestAddress(); $service->expectedCountry = 'at';
$point = result(['lat' => '48.2', 'lon' => '16.3', 'boundingbox' => ['48.19', '48.21', '16.29', '16.31']]);
$point['address']['country_code'] = 'at'; $service->response[0] = json_encode([$point]);
check(attempt($service, $id)['status'] === 'updated' && (float)stored($id)['longitude'] === 16.3, 'Country restriction was hardcoded to Germany');

$failures = [false, '', 'invalid json', 'null', '{}', '[null]', '[{}]', '{"error":"busy"}'];
foreach ([['lat' => null], ['lon' => null], ['lat' => true], ['lat' => []], ['lat' => '1); UPDATE address SET deleted=1; --'], ['lat' => 'NaN'], ['lat' => '1e309'], ['lat' => 90.0001], ['lat' => -90.0001], ['lon' => 180.0001], ['lon' => -180.0001]] as $change) $failures[] = json_encode([result($change)]);
foreach ($failures as $body) {
	$id = seed(['latitude' => 48.1, 'longitude' => 8.2, 'geoupdated' => '2020-01-01 12:00:00']); $before = stored($id); $service = new GeocodingTestAddress(); $service->response[0] = $body;
	check(attempt($service, $id, true)['status'] === 'retry', 'Malformed/invalid response was accepted');
	check($service->requests === 1 && stored($id) === $before, 'API failure changed existing coordinates/timestamps');
	check(attempt($service, $id, true)['status'] === 'backoff' && $service->requests === 1, 'Failed address was queried immediately again');
}
foreach ([[], ['HTTP/1.1 429 Too Many Requests'], ['HTTP/1.1 503 Service Unavailable'], ['HTTP/1.1 302 Found'], ['HTTP/1.1 404 Not Found']] as $headers) {
	$id = seed(['latitude' => 48.1, 'longitude' => 8.2, 'geoupdated' => '2020-01-01 12:00:00']); $before = stored($id); $service = new GeocodingTestAddress(); $service->response[1] = $headers;
	check(in_array(attempt($service, $id, true)['status'], ['retry', 'blocked'], true), 'HTTP error accepted');
	check(stored($id) === $before, 'HTTP failure changed existing coordinates');
}

foreach ([['country_code' => 'at'], ['postcode' => '99999'], ['road' => 'Wrong Street'], ['house_number' => '2'], ['city' => 'Wrong City']] as $change) {
	$id = seed(); $service = new GeocodingTestAddress(); $response = result(); $response['address'] = array_replace($response['address'], $change); $service->response[0] = json_encode([$response]);
	check(attempt($service, $id)['status'] === 'review' && stored($id)['latitude'] === null, 'Geographically inconsistent result was accepted');
	check(attempt($service, $id)['status'] === 'manual_review_required' && $service->requests === 1, 'Implausible result was retried automatically');
}
foreach ([['place_rank' => 16], ['boundingbox' => [50, 52, 6, 8]], ['boundingbox' => [51, 51.1, 6.6, 6.8]], ['boundingbox' => ['NaN', 52, 6, 8]], ['lat' => 0, 'lon' => 0]] as $change) {
	$id = seed(); $service = new GeocodingTestAddress(); $service->response[0] = json_encode([result($change)]);
	check(attempt($service, $id)['status'] === 'review', 'Coarse/out-of-box/zero result accepted');
}
$id = seed(); $service = new GeocodingTestAddress();
$service->response[0] = json_encode([result(), result(['lat' => '51.201'])]);
check(attempt($service, $id)['status'] === 'review', 'Ambiguous results were silently chosen');
$id = seed(); $service = new GeocodingTestAddress(); $service->response[0] = " [ ]\n";
check(attempt($service, $id)['status'] === 'no_match', 'Empty result was not negative-cached');
$service->now += 86400;
check(attempt($service, $id)['status'] === 'backoff' && $service->requests === 1, 'Negative cache ignored');

// A failing postal key must not block other keys; identical keys share backoff/cache.
$id = seed(); $service = new GeocodingTestAddress(); $service->response[0] = false;
check(attempt($service, $id)['status'] === 'retry', 'Transient error not recorded');
$db->query("INSERT INTO address (parentid, clientid, module, controller, type, street, postcode, city, country) VALUES (2, 100, 'contacts', 'contact', 'billing', '  SYNTHETIC  STREET 1 ', '12345', 'Synthetic City', 'DE')");
$duplicate = (int)$db->insert_id;
$service->now += 15;
check(attempt($service, $duplicate)['status'] === 'backoff' && $service->requests === 1, 'Duplicate failure caused another request');
$db->query("UPDATE address SET postcode = '54321' WHERE id = $id");
$service->expectedPostcode = '54321';
$response = result(); $response['address']['postcode'] = '54321';
$service->response[0] = json_encode([$response]);
check(attempt($service, $id)['status'] === 'updated' && $service->requests === 2, 'Failed key blocked a different candidate');
$service->response[0] = json_encode([result()]);
// Use a fresh key for the second contact and restore the mocked expected postal fields.
$db->query("UPDATE address SET street = 'Synthetic Street 1', postcode = '12345', city = 'Synthetic City', country = 'de' WHERE id = $duplicate");
$endpoint = new GeocodingTestAddress(['endpoint' => 'https://another-nominatim.invalid/search']);
$endpoint->now = $service->now;
check(attempt($endpoint, $duplicate)['status'] === 'backoff', 'Provider switch bypassed shared request spacing');
$endpoint->now += 15;
check(attempt($endpoint, $duplicate)['status'] === 'updated', 'Endpoint switch requires code changes');
$service->response[0] = json_encode([result()]);
$service->expectedPostcode = '12345';
$db->query("UPDATE address SET postcode = '12345' WHERE id = $id");
$service->now += 3600;
check(attempt($service, $id)['status'] === 'updated', 'Deferred failure did not recover');
$db->query("UPDATE address SET latitude = 52 WHERE id = $duplicate");
$reused = attempt($service, $duplicate);
check($reused['status'] === 'existing_coordinates', 'Different independently stored coordinates were overwritten');
$db->query("UPDATE address SET latitude = NULL, longitude = NULL WHERE id = $duplicate");
$reused = attempt($service, $duplicate);
check($reused['status'] === 'updated' && $reused['cached'] === true && $service->requests === 3, 'Identical normalized address was queried again');
$state = file_get_contents(options()['state_dir'].'/manual.json');
check(!str_contains($state, 'Synthetic') && !str_contains($state, '12345') && !str_contains($state, 'country_code'), 'State persisted postal data or raw responses');

$id = seed(); $service = new GeocodingTestAddress(); $service->response[0] = false;
$delays = [];
for ($n = 1; $n <= 5; $n++) {
	$outcome = attempt($service, $id); $delays[] = $outcome['retry_after'];
	check($outcome['status'] === ($n === 5 ? 'review' : 'retry'), 'Retry bound/status incorrect');
	$service->now += $outcome['retry_after'];
}
check($delays === [3600, 7200, 14400, 28800, 57600], 'Exponential retry backoff incorrect');
check(attempt($service, $id)['status'] === 'manual_review_required' && $service->requests === 5, 'Retry bound was bypassed');
$id = seed(); $service = new GeocodingTestAddress();
check(attempt($service, $id)['status'] === 'updated', 'Quota fixture failed');
$file = options()['state_dir'].'/manual.json'; $state = json_decode(file_get_contents($file), true);
$state['requests_today'] = 50; file_put_contents($file, json_encode($state));
$db->query("UPDATE address SET postcode = '54321' WHERE id = $id"); $service->now += 15;
check(attempt($service, $id)['status'] === 'daily_limit' && $service->requests === 1, 'Daily API budget was ignored');
$service->now += 86400; $service->expectedPostcode = '54321'; $response = result(); $response['address']['postcode'] = '54321'; $service->response[0] = json_encode([$response]);
check(attempt($service, $id)['status'] === 'updated' && $service->requests === 2, 'UTC daily budget did not reset');
$id = seed(); $service = new GeocodingTestAddress();
$service->duringRequest = static function(): void { throw new RuntimeException('PRIVATE_TRANSPORT_ERROR'); };
check(attempt($service, $id)['status'] === 'retry' && !str_contains(file_get_contents(options()['state_dir'].'/manual.json'), 'PRIVATE_TRANSPORT_ERROR'), 'Transport exception leaked or escaped backoff');
foreach (['7200', gmdate('D, d M Y H:i:s', 1800000000 + 7200).' GMT'] as $retryAfter) {
	$id = seed(); $service = new GeocodingTestAddress(); $service->response[1] = ['HTTP/1.1 429 Too Many Requests', 'Retry-After: '.$retryAfter];
	check(attempt($service, $id)['retry_after'] === 7200, 'Retry-After ignored');
	$db->query("UPDATE address SET postcode = '54321' WHERE id = $id");
	check(attempt($service, $id)['status'] === 'backoff' && $service->requests === 1, 'Global provider backoff bypassed by another postal key');
}

// Database edits during the mocked request use a distinct connection.
foreach (['street' => 'synthetic Street 1', 'postcode' => '54321', 'city' => 'synthetic City', 'country' => 'AT', 'module' => 'other', 'controller' => 'other', 'parentid' => 2, 'type' => 'shipping', 'modified' => '2020-01-03', 'geoupdated' => '2020-01-03', 'latitude' => '49.2', 'longitude' => '9.3', 'clientid' => 101, 'deleted' => 1] as $field => $value) {
	$id = seed(); $service = new GeocodingTestAddress(); $changed = null;
	$service->duringRequest = static function() use ($other, $id, $field, $value, &$changed): void {
		$statement = $other->prepare('UPDATE address SET '.$field.' = ? WHERE id = ?');
		$statement->bind_param('si', $value, $id); $statement->execute(); $statement->close(); $changed = stored($id);
	};
	check(attempt($service, $id)['status'] === 'address_changed', 'Stale result overwrote concurrent '.$field.' edit');
	check(stored($id) === $changed, 'Concurrent '.$field.' edit was not preserved');
}
$id = seed(); $service = new GeocodingTestAddress(); $parallel = new GeocodingTestAddress();
$service->duringRequest = static function() use ($parallel, $id): void { check(attempt($parallel, $id)['status'] === 'busy' && $parallel->requests === 0, 'Parallel process made a duplicate request'); };
check(attempt($service, $id)['status'] === 'updated', 'Owning worker failed');
$id = seed(); $service = new GeocodingTestAddress(); $service->duringRequest = static function() use ($other): void { $other->query('UPDATE contact SET deleted = 1 WHERE id = 1'); };
check(attempt($service, $id)['status'] === 'address_changed' && stored($id)['latitude'] === null, 'Deleted contact received stale coordinates');
$db->query('UPDATE contact SET deleted = 0 WHERE id = 1');

// Preserve the original numeric bounds, parameter binding and snapshot-write regression cases.
foreach ([[-90, -180], [90, 180], [0, 0], ['0.000000000', '0.000000000']] as [$lat, $lon]) {
	$id = seed(); $service = new GeocodingTestAddress();
	check($service->updateAddress($id, ['latitude' => $lat, 'longitude' => $lon, 'geoupdated' => '2020-01-04'], stored($id)), 'Numeric boundary rejected');
	check((float)stored($id)['latitude'] === (float)$lat && (float)stored($id)['longitude'] === (float)$lon, 'Boundary coordinate changed');
}
$id = seed(); $snapshot = stored($id); $service = new GeocodingTestAddress(); $data = ['latitude' => 52, 'longitude' => 7, 'geoupdated' => '2020-01-04'];
check($service->updateAddress($id, $data, $snapshot), 'First snapshot write failed');
check(!$service->updateAddress($id, array_replace($data, ['latitude' => 53]), $snapshot), 'Second worker overwrote first result');
$before = stored($id);
foreach ([['latitude' => INF], ['longitude' => NAN], ['latitude' => 'NULL'], ['latitude' => "0; DELETE FROM address"]] as $invalid) check(!$service->updateAddress($id, array_replace($data, $invalid)), 'Invalid coordinate accepted');
check(!$service->updateAddress($id.' OR 1=1', $data), 'Injected ID accepted');
check(!$service->updateAddress($id, $data, ['street' => 'partial snapshot']), 'Incomplete snapshot accepted');
check(stored($id) === $before, 'Rejected update changed coordinates');
$foreign = seed(['clientid' => 101]); $before = stored($foreign);
check(!$service->updateAddress($foreign, $data) && stored($foreign) === $before && $service->getAddress($foreign) === false, 'Tenant boundary crossed');

$id = seed(); $service = new GeocodingTestAddress();
foreach ([['user_agent' => 'DEWAWI'], ['user_agent' => "DEWAWI/1.1.7 (+https://dewawi.com)\r\nInjected: x"], ['endpoint' => 'http://nominatim.openstreetmap.org/search'], ['endpoint' => 'https://nominatim.openstreetmap.org/search?q=secret'], ['state_dir' => 'relative']] as $invalid) rejects(fn() => (new GeocodingTestAddress($invalid))->inspectGeocoding($id));
rejects(fn() => (new GeocodingTestAddress(['state_dir' => dirname(__DIR__, 2).'/cache/manual-geocoding']))->inspectGeocoding($id));
mkdir(options()['state_dir'], 0700);
file_put_contents(options()['state_dir'].'/manual.json', '{broken');
rejects(fn() => $service->inspectGeocoding($id));
$state = ['version' => 1, 'entries' => [str_repeat('a', 64) => ['status' => 'success', 'point' => ['latitude' => 'invalid', 'longitude' => 7]]], 'addresses' => [], 'next_request_at' => 0, 'requests_today' => 0];
file_put_contents(options()['state_dir'].'/manual.json', json_encode($state));
rejects(fn() => $service->inspectGeocoding($id));
check($service->requests === 0 && stored($id)['latitude'] === null, 'Corrupt state permitted API traffic or writes');

// The cron must retain campaign logic while its public geocoding call is absent.
$cron = file_get_contents(dirname(__DIR__, 2).'/cron.php');
check(!str_contains($cron, '->geocodeLatestAddress(') && str_contains($cron, '$Email->send(') && str_contains($cron, '$Campaign->touchLastSent('), 'Cron campaign/geocoding boundary regressed');
echo 'PASS: '.$checks." manual geocoding, caching, backoff, plausibility, privacy, SQL and concurrency cases\n";
require __DIR__.'/geocoding-formats.php';
