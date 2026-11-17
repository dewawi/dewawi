<?php

// Run with: python3 tests/contacts/geocoding.py. Never use application database configuration.
if (PHP_SAPI !== 'cli' || !preg_match('~^/tmp/dewawi-geocoding-[^/]+/mysql\.sock$~D', $argv[1] ?? '')) exit(1);

require dirname(__DIR__, 2).'/library/DEEC/Address.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '', '', 0, $argv[1]);
$db->query('CREATE DATABASE geocoding_test');
$db->select_db('geocoding_test');
$schema = file_get_contents(dirname(__DIR__, 2).'/install/config/structure.sql');
preg_match('/CREATE TABLE IF NOT EXISTS `address` .*?;/s', $schema, $match);
$db->query($match[0]);
$other = new mysqli('localhost', 'root', '', 'geocoding_test', 0, $argv[1]);

$checks = 0;
function check(bool $condition, string $message): void {
	global $checks;
	if (!$condition) throw new RuntimeException($message);
	$checks++;
}

class GeocodingTestAddress extends DEEC_Address {
	public int $requests = 0;
	public array $response = ['[{"lat":"51.2","lon":"6.7"}]', ['HTTP/1.1 200 OK']];
	public $duringRequest = null;

	public function __construct(mysqli $connection) {
		$this->connection = $connection;
	}

	protected function requestGeocode(string $location): array {
		$this->requests++;
		check($location === 'Synthetic Street 1 12345 Synthetic City', 'Unexpected geocoding address');
		if ($this->duringRequest) ($this->duringRequest)();
		return $this->response;
	}
}

function seed(array $changes = []): int {
	global $db;
	$db->query('UPDATE address SET deleted = 1');
	$row = array_replace([
		'parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing',
		'clientid' => 100, 'street' => 'Synthetic Street 1', 'postcode' => '12345', 'city' => 'Synthetic City',
		'country' => 'DE', 'latitude' => '48.1', 'longitude' => '8.2', 'geoupdated' => '2020-01-01 12:00:00',
		'created' => '2020-01-01 10:00:00', 'modified' => '2020-01-02 12:00:00', 'deleted' => 0,
	], $changes);
	$query = 'INSERT INTO address (`'.implode('`, `', array_keys($row)).'`) VALUES ('.implode(', ', array_fill(0, count($row), '?')).')';
	$statement = $db->prepare($query);
	$values = array_values($row);
	$statement->bind_param(str_repeat('s', count($values)), ...$values);
	$statement->execute();
	$statement->close();
	return (int)$db->insert_id;
}

function stored(int $id): array {
	global $db;
	return $db->query('SELECT * FROM address WHERE id = '.$id)->fetch_assoc();
}

foreach ([
	['modified' => '2019-12-31 12:00:00'],
	['modified' => '2020-01-01 12:00:00'],
	['modified' => null],
	['street' => ''],
	['street' => '   '],
	['street' => null],
	['clientid' => 101],
	['deleted' => 1],
] as $changes) {
	$id = seed($changes);
	$before = stored($id);
	$address = new GeocodingTestAddress($db);
	check(!$address->geocodeLatestAddress(), 'Unchanged/ineligible address was processed');
	check($address->requests === 0, 'Unchanged/ineligible address made an HTTP request');
	check(stored($id) === $before, 'Unchanged/ineligible address lost data');
}

// An unchanged oldest row must not prevent a newer pending row from being processed.
$pending = seed();
$db->query("INSERT INTO address (parentid, clientid, street, geoupdated, modified) VALUES (2, 100, 'Already processed', '2010-01-02', '2010-01-01')");
$unchanged = (int)$db->insert_id;
$before = stored($unchanged);
$address = new GeocodingTestAddress($db);
check($address->geocodeLatestAddress(), 'Pending address behind an unchanged row was not updated');
$after = stored($pending);
check((float)$after['latitude'] === 51.2 && (float)$after['longitude'] === 6.7, 'Successful coordinates were not stored');
check($after['geoupdated'] > $after['modified'], 'Successful geocoding timestamp was not stored');
check($after['modified'] === '2020-01-02 12:00:00', 'Geocoding changed the address modification time');
check(stored($unchanged) === $before, 'Unchanged row was modified');
check(!$address->geocodeLatestAddress() && $address->requests === 1, 'Successful geocoding was repeated without an address change');

foreach ([[-90, -180], [90, 180], [0, 0], ['0.000000000', '0.000000000']] as [$lat, $lon]) {
	$id = seed(['latitude' => null, 'longitude' => null, 'geoupdated' => null, 'modified' => null, 'created' => null]);
	$address = new GeocodingTestAddress($db);
	$address->response[0] = json_encode([['lat' => $lat, 'lon' => $lon]]);
	check($address->geocodeLatestAddress(), 'Valid boundary/zero coordinates were rejected');
	check((float)stored($id)['latitude'] === (float)$lat && (float)stored($id)['longitude'] === (float)$lon, 'Boundary/zero coordinates changed');
}

$failures = [false, '', 'invalid json', 'null', '{}', '[]', '[null]', '[{}]', '{"error":"busy"}',
	'[{"lat":51.2}]', '[{"lon":6.7}]', '[{"lat":null,"lon":6.7}]',
	'[{"lat":true,"lon":6.7}]', '[{"lat":[],"lon":6.7}]',
	'[{"lat":"1); UPDATE address SET deleted=1; --","lon":6.7}]',
	'[{"lat":"NaN","lon":6.7}]', '[{"lat":"1e309","lon":6.7}]',
	'[{"lat":90.0001,"lon":6.7}]', '[{"lat":-90.0001,"lon":6.7}]',
	'[{"lat":51.2,"lon":180.0001}]', '[{"lat":51.2,"lon":-180.0001}]'];
foreach ($failures as $body) {
	$id = seed();
	$before = stored($id);
	$address = new GeocodingTestAddress($db);
	$address->response[0] = $body;
	check(!$address->geocodeLatestAddress(), 'Invalid/failed response was accepted');
	check($address->requests === 1, 'Pending address was not attempted exactly once');
	check(stored($id) === $before, 'Failed geocoding changed stored coordinates or timestamps');
}

foreach ([[], ['HTTP/1.1 429 Too Many Requests'], ['HTTP/1.1 503 Service Unavailable'], ['HTTP/1.1 302 Found'], ['HTTP/1.1 404 Not Found']] as $headers) {
	$id = seed();
	$before = stored($id);
	$address = new GeocodingTestAddress($db);
	$address->response[1] = $headers;
	check(!$address->geocodeLatestAddress(), 'Non-success HTTP status was accepted');
	check(stored($id) === $before, 'HTTP error changed stored geodata');
}

// Use another connection to reproduce edits after selection and before the geocoding write.
foreach ([
	'street' => 'synthetic Street 1', 'postcode' => '54321', 'city' => 'synthetic City',
	'country' => 'AT', 'module' => 'other', 'controller' => 'other', 'parentid' => 2, 'type' => 'shipping',
	'modified' => '2020-01-03 12:00:00', 'geoupdated' => '2020-01-03 12:00:00',
	'latitude' => '49.2', 'longitude' => '9.3', 'clientid' => 101, 'deleted' => 1,
] as $field => $value) {
	$id = seed();
	$address = new GeocodingTestAddress($db);
	$changed = null;
	$address->duringRequest = static function() use ($other, $id, $field, $value, &$changed): void {
		$statement = $other->prepare('UPDATE address SET '.$field.' = ? WHERE id = ?');
		$statement->bind_param('si', $value, $id);
		$statement->execute();
		$statement->close();
		$changed = stored($id);
	};
	check(!$address->geocodeLatestAddress(), 'Stale result overwrote a concurrent '.$field.' change');
	check(stored($id) === $changed, 'Geocoding changed data after a concurrent '.$field.' change');
}

$id = seed();
$snapshot = stored($id);
$address = new GeocodingTestAddress($db);
$data = ['latitude' => 52, 'longitude' => 7, 'geoupdated' => '2020-01-04 12:00:00'];
check($address->updateAddress($id, $data, $snapshot), 'First worker result was rejected');
check(!$address->updateAddress($id, array_replace($data, ['latitude' => 53]), $snapshot), 'Second worker overwrote the first result');
check((float)stored($id)['latitude'] === 52.0, 'Newer coordinates were overwritten');
$before = stored($id);
check(!$address->updateAddress($id.' OR 1=1', $data), 'Invalid ID was accepted');
check(!$address->updateAddress($id, array_replace($data, ['latitude' => INF])), 'Infinite coordinate was accepted');
check(!$address->updateAddress($id, array_replace($data, ['longitude' => NAN])), 'NaN coordinate was accepted');
check(!$address->updateAddress($id, array_replace($data, ['latitude' => 'NULL'])), 'SQL NULL string was accepted');
check(!$address->updateAddress($id, $data, ['street' => 'partial snapshot']), 'Incomplete snapshot was accepted');
check(stored($id) === $before, 'Rejected update changed the address');
$foreign = seed(['clientid' => 101]);
$before = stored($foreign);
check(!$address->updateAddress($foreign, $data), 'Direct update crossed the existing tenant boundary');
check(stored($foreign) === $before, 'Foreign tenant data was changed');

echo 'PASS: '.$checks." geocoding, failure preservation, SQL and concurrent address cases\n";
