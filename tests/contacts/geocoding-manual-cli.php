<?php

require __DIR__.'/map-bootstrap.php';
$checks = 0;
function manualCheck(bool $condition, string $message): void { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; }
function manualCli(array $arguments): array {
	global $root;
	$process = proc_open(array_merge(['php', '-d', 'allow_url_fopen=0', $root.'/application/cli/contact-geocode.php'], $arguments), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]); fclose($pipes[2]);
	return [proc_close($process), $output, $errors];
}
foreach ([100, 200] as $user) $db->update('permission', ['contacts' => json_encode(['contact' => ['view', 'edit']])], ['userid = ?' => $user]);
foreach ([104 => ['view'], 105 => ['edit'], 106 => ['view']] as $id => $rights) {
	$db->insert('user', ['id' => $id, 'clientid' => 100, 'password' => '', 'activated' => 1, 'admin' => (int)($id === 106)]);
	$db->insert('permission', ['userid' => $id, 'clientid' => 100, 'contacts' => json_encode(['contact' => $rights])]);
}
$db->query("CREATE USER 'manual_geocoding'@'localhost' IDENTIFIED BY 'synthetic-only'");
$db->query("GRANT SELECT ON contact_map_test.* TO 'manual_geocoding'@'localhost'");
$db->query("GRANT UPDATE (latitude, longitude, geoupdated) ON contact_map_test.address TO 'manual_geocoding'@'localhost'");
$config = $work.'/manual-database.ini';
file_put_contents($config, "[synthetic]\nresources.db.adapter = pdo_mysql\nresources.db.params.host = localhost\nresources.db.params.unix_socket = ".$work."/mysql.sock\nresources.db.params.username = manual_geocoding\nresources.db.params.password = synthetic-only\nresources.db.params.dbname = contact_map_test\nresources.db.params.charset = utf8\n");
$geocodingConfig = $work.'/geocoding.ini';
$stateDirectory = $work.'/manual-geocoding';
file_put_contents($geocodingConfig, "[manual]\nendpoint = https://nominatim.openstreetmap.org/search\nuser_agent = \"DEWAWI/1.1.7 (+https://dewawi.com/contact)\"\nstate_dir = ".$stateDirectory."\n");
$id = (int)$db->fetchOne("SELECT id FROM address WHERE parentid = 10003 AND clientid = 100 AND module = 'contacts' AND controller = 'contact' AND type = 'billing' AND deleted = 0 ORDER BY ordering, id LIMIT 1");
$donor = (int)$db->fetchOne("SELECT id FROM address WHERE parentid = 10001 AND clientid = 100 AND deleted = 0 ORDER BY ordering, id LIMIT 1");
$args = ['--client=100', '--user=100', '--address='.$id, '--config='.$config, '--environment=synthetic', '--geocoding-config='.$geocodingConfig];
$before = $db->fetchAll('SELECT * FROM address ORDER BY id');
[$exit, $output, $errors] = manualCli($args);
$review = json_decode($output, true);
manualCheck($exit === 0 && $errors === '' && $review['status'] === 'needs_geocoding' && isset($review['fingerprint']), 'Manual CLI review failed');
manualCheck(!is_dir($stateDirectory) && !str_contains($output, 'PRIVATE_'), 'Read-only review created state or leaked address fields');
manualCheck($db->fetchAll('SELECT * FROM address ORDER BY id') === $before, 'Review modified addresses');
[$exit, $output] = manualCli(array_merge($args, ['--confirm-public-address='.str_repeat('0', 64)]));
manualCheck($exit === 0 && json_decode($output, true)['status'] === 'public_review_required' && !is_dir($stateDirectory), 'Missing public-address review permitted a request');
foreach ([101, 102, 103, 104, 105, 106, 300, 9999] as $user) {
	[$exit, $output, $errors] = manualCli(array_replace($args, [1 => '--user='.$user]));
	manualCheck($exit === 1 && $output === '' && !str_contains($errors, 'SELECT') && !str_contains($errors, 'synthetic-only'), 'Unauthorized manual CLI actor accepted or details leaked');
}
[$exit, $output, $errors] = manualCli(array_replace($args, [1 => '--user=200']));
manualCheck($exit === 0 && $errors === '' && json_decode($output, true) === $review, 'Authorized child cannot select parent 100');
foreach ([[], ['--client=200'], array_merge($args, ['--all=1']), array_merge($args, ['--client=100']), array_merge($args, ['--confirmed-postal-change=1']), array_replace($args, [2 => '--address=1 OR 1=1']), array_replace($args, [4 => '--environment=missing'])] as $invalid) {
	[$exit, $output, $errors] = manualCli($invalid);
	manualCheck($exit === 1 && $output === '' && !str_contains($errors, $work), 'Invalid or bulk CLI options accepted');
}
foreach ([1, 3000] as $foreign) {
	$foreignAddress = (int)$db->fetchOne('SELECT id FROM address WHERE parentid = ? ORDER BY id LIMIT 1', $foreign);
	[$exit, $output, $errors] = manualCli(array_replace($args, [2 => '--address='.$foreignAddress]));
	manualCheck($exit === 0 && $errors === '' && json_decode($output, true)['status'] === 'ineligible', 'Foreign tenant address was exposed');
}

// Prime only the synthetic cache through mocked HTTP; the real CLI has URL access disabled.
class ManualCliFixtureAddress extends DEEC_Address {
	protected function requestGeocode(string $query): array {
		return [json_encode([['lat' => '51.2', 'lon' => '6.7', 'place_rank' => 30, 'boundingbox' => ['51.19', '51.21', '6.69', '6.71'], 'address' => ['country_code' => 'de', 'road' => 'private_street', 'house_number' => '1', 'postcode' => '12345', 'city' => 'PRIVATE_CITY']]]), ['HTTP/1.1 200 OK']];
	}
}
$service = new ManualCliFixtureAddress($root, 'localhost', 'root', '', 'contact_map_test', ['endpoint' => 'https://nominatim.openstreetmap.org/search', 'user_agent' => 'DEWAWI/1.1.7 (+https://dewawi.com/contact)', 'state_dir' => $stateDirectory], 0, $work.'/mysql.sock');
$donorReview = $service->inspectGeocoding($donor);
manualCheck($service->geocodeAddress($donor, $donorReview['fingerprint'], true)['status'] === 'updated', 'Mock cache fixture failed');
$snapshot = [];
foreach ($db->fetchCol('SHOW TABLES') as $table) $snapshot[$table] = $db->fetchAll('SELECT * FROM '.$db->quoteIdentifier($table).' ORDER BY id');
$approvedArgs = array_merge($args, ['--confirm-public-address='.$review['fingerprint']]);
$lock = fopen($stateDirectory.'/manual.lock', 'c');
flock($lock, LOCK_EX);
[$exit, $output, $errors] = manualCli($approvedArgs);
manualCheck($exit === 0 && $errors === '' && json_decode($output, true)['status'] === 'busy', 'Real parallel CLI ignored lock');
fclose($lock);
[$exit, $output, $errors] = manualCli($approvedArgs);
$applied = json_decode($output, true);
manualCheck($exit === 0 && $errors === '' && $applied['status'] === 'updated' && $applied['cached'] === true && isset($applied['license_url']), 'Real CLI failed cached write with column-restricted credentials');
manualCheck((float)$db->fetchOne('SELECT latitude FROM address WHERE id = ?', $id) === 51.2, 'Cached coordinates were not applied');
[$exit, $output, $errors] = manualCli($approvedArgs);
manualCheck($exit === 0 && $errors === '' && json_decode($output, true)['status'] === 'unchanged', 'Repeated CLI queried unchanged address');
foreach ($snapshot as $table => $rows) {
	$after = $db->fetchAll('SELECT * FROM '.$db->quoteIdentifier($table).' ORDER BY id');
	if ($table === 'address') foreach ($after as &$row) if ((int)$row['id'] === $id) foreach (['latitude', 'longitude', 'geoupdated'] as $field) $row[$field] = array_values(array_filter($rows, fn($before) => (int)$before['id'] === $id))[0][$field];
	unset($row);
	manualCheck($after === $rows, 'Manual CLI modified unrelated fields/table '.$table);
}
[$exit, $output, $errors] = manualCli(['--help']);
manualCheck($exit === 0 && $errors === '' && str_contains($output, 'Nominatim policy:') && str_contains($output, '--confirm-public-address='), 'Manual CLI policy/help missing');
echo 'PASS: '.$checks." manual CLI permissions, child sharing, privacy, cached write and concurrent process checks\n";
