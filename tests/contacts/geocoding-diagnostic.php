<?php

require __DIR__.'/map-bootstrap.php';
$schema = file_get_contents($root.'/install/config/structure.sql');
foreach (['client', 'user'] as $table) {
	preg_match('/CREATE TABLE IF NOT EXISTS `'.$table.'` .*?;/s', $schema, $match);
	$db->query($match[0]);
}
$checks = 0;
function diagnosticCheck(bool $condition, string $message): void { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; }
function diagnosticReject(callable $callback): void {
	try { $callback(); } catch (InvalidArgumentException | RuntimeException $error) { diagnosticCheck(true, 'Rejected'); return; }
	throw new RuntimeException('Invalid diagnostic request accepted');
}
foreach ([100 => 0, 200 => 100, 300 => 0] as $id => $parent) $db->insert('client', ['id' => $id, 'parentid' => $parent, 'activated' => 1, 'country' => 'DE']);
foreach ([100 => 100, 101 => 100, 102 => 100, 103 => 100, 200 => 200, 300 => 300] as $id => $tenant) {
	$db->insert('user', ['id' => $id, 'clientid' => $tenant, 'password' => '', 'activated' => (int)($id !== 102), 'deleted' => (int)($id === 103)]);
	$db->insert('permission', ['userid' => $id, 'clientid' => $tenant, 'contacts' => json_encode(['contact' => $id === 101 ? [] : ['view']])]);
}
foreach (['DE', 'AT', 'US'] as $code) $db->insert('country', ['clientid' => 100, 'code' => $code, 'name' => $code, 'language' => 'de']);
$db->insert('category', ['id' => 100, 'parentid' => 0, 'shopid' => 0, 'clientid' => 100, 'type' => 'contact', 'title' => 'Diagnostic category', 'activated' => 1]);
$db->insert('tag', ['id' => 100, 'clientid' => 100, 'module' => 'contacts', 'controller' => 'contact', 'title' => 'Diagnostic tag']);

$addresses = [];
for ($n = 1; $n <= 25; $n++) {
	$id = 10000 + $n;
	$db->insert('contact', ['id' => $id, 'clientid' => 100, 'contactid' => $id, 'name1' => 'PRIVATE_CONTACT_'.$n, 'catid' => $n <= 3 ? 100 : 0]);
	if ($n <= 3) $db->insert('tagentity', ['entityid' => $id, 'tagid' => 100, 'clientid' => 100, 'module' => 'contacts', 'controller' => 'contact']);
	if ($n === 5) continue;
	$row = ['parentid' => $id, 'clientid' => 100, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'street' => 'PRIVATE_STREET '.$n, 'postcode' => '12345', 'city' => 'PRIVATE_CITY', 'country' => 'DE', 'created' => '2026-01-01 00:00:00', 'modified' => '2026-01-01 00:00:00'];
	if (in_array($n, [1, 8, 9, 10, 14, 15, 20, 25])) $row += ['latitude' => 51, 'longitude' => 7, 'geoupdated' => '2026-01-02 00:00:00'];
	if ($n <= 3) $row['street'] = $n === 3 ? '  private_street   1  ' : 'PRIVATE_STREET 1';
	if ($n === 2) $row['geoupdated'] = '2026-01-02 00:00:00';
	if ($n === 4) $row['street'] = '';
	if ($n === 6) $row += ['latitude' => 91, 'longitude' => 7];
	if ($n === 7) $row += ['latitude' => 51];
	if ($n === 8) $row['modified'] = '2026-01-03 00:00:00';
	if ($n === 9) $row['geoupdated'] = null;
	if ($n === 10) $row['latitude'] = $row['longitude'] = 0;
	if (in_array($n, [13, 14, 15])) $row['street'] = 'PRIVATE_CONFLICT';
	if ($n === 15) $row['latitude'] = 52;
	if ($n === 16) $row['country'] = 'XX';
	if ($n === 17) { $row['country'] = 'AT'; $row['street'] = 'PRIVATE_STREET 1'; }
	if ($n === 18) $row['street'] = 'PRIVATE_STREET 1a';
	if ($n === 19) $row['street'] = "PRIVATE_Ru\u{0308}ckweg 1";
	if ($n === 20) $row['street'] = 'private_rückweg 1';
	if ($n === 21) $row['country'] = '0';
	if ($n === 22) $row['city'] = '';
	if ($n === 23) $row['street'] = "PRIVATE_QUOTE ' ; DROP TABLE address; --";
	if ($n === 24) $row['postcode'] = '';
	if ($n === 25) { $row['latitude'] = 90; $row['longitude'] = 180; }
	$db->insert('address', $row);
	$addresses[$n] = (int)$db->lastInsertId();
}
foreach ([11 => 'billing', 12 => 'shipping'] as $n => $type) $db->insert('address', ['parentid' => 10000 + $n, 'clientid' => 100, 'module' => 'contacts', 'controller' => 'contact', 'type' => $type, 'ordering' => 1, 'street' => 'PRIVATE_STREET '.$n, 'postcode' => '12345', 'city' => 'PRIVATE_CITY', 'country' => 'DE', 'latitude' => 51, 'longitude' => 7, 'created' => '2026-01-01 00:00:00', 'geoupdated' => '2026-01-02 00:00:00']);
foreach ([['clientid' => 300], ['deleted' => 1], ['controller' => 'contactperson'], ['module' => 'sales']] as $override) $db->insert('address', $override + ['parentid' => 10003, 'clientid' => 100, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'ordering' => -10, 'latitude' => 52, 'longitude' => 8, 'country' => 'US']);
foreach ([['clientid' => 300], ['deleted' => 1]] as $offset => $override) $db->insert('contact', $override + ['id' => 11000 + $offset, 'clientid' => 100, 'contactid' => 11000 + $offset, 'catid' => 0]);
foreach (['customer', 'supplier'] as $role) $db->insert('contactrole', ['parentid' => 10001, 'clientid' => 100, 'role' => $role]);
$db->insert('contactrole', ['parentid' => 10002, 'clientid' => 300, 'role' => 'partner']);
$db->insert('contactrole', ['parentid' => 10002, 'clientid' => 100, 'role' => 'partner', 'deleted' => 1]);

mapActor(100);
$model = new Contacts_Model_GeocodingDiagnostic();
$options = mapOptions();
$filters = Contacts_Model_Map::FILTERS;
$db->query("SET SESSION sql_mode = CONCAT(@@sql_mode, ',ONLY_FULL_GROUP_BY')");
$before = $db->fetchAll('SELECT * FROM address ORDER BY id');
$db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
$db->beginTransaction();
$report = $model->diagnose(100, $filters, $options);
$map = (new Contacts_Model_Map())->summary($filters, $options);
diagnosticCheck($report['counts']['total'] === 25, 'Contact selection differs');
foreach (['valid_coordinates' => 'positioned', 'missing_coordinates' => 'missing', 'invalid_coordinates' => 'invalid'] as $diagnostic => $field) diagnosticCheck($report['counts'][$diagnostic] === $map[$field], 'Coordinate selection differs from map');
foreach (['valid_coordinates' => 8, 'missing_coordinates' => 16, 'invalid_coordinates' => 1, 'partial_coordinates' => 1, 'no_coordinates' => 15, 'postal_complete' => 19, 'postal_incomplete' => 6, 'no_billing_address' => 1, 'coordinates_only_on_other_address' => 2, 'other_billing_valid_coordinates' => 1, 'stale_coordinates_hint' => 1, 'zero_zero_hint' => 1, 'valid_coordinates_without_timestamp' => 1, 'coordinate_gaps_with_timestamp' => 1, 'eligible_addresses' => 11, 'eligible_not_due_in_legacy_cron' => 1] as $field => $expected) diagnosticCheck($report['counts'][$field] === $expected, 'Incorrect count: '.$field.' '.json_encode($report['counts']));
diagnosticCheck($report['plan'] === ['eligible_addresses' => 11, 'unique_candidate_addresses' => 10, 'duplicate_requests_avoided' => 1, 'potential_reuse_addresses' => 5, 'potential_reuse_groups' => 4, 'estimated_requests_after_reuse' => 6], 'Duplicate/reuse estimate incorrect: '.json_encode($report['plan']));
diagnosticCheck($report['duplicates'] === ['complete_address_groups' => 14, 'duplicate_groups' => 3, 'duplicate_selected_addresses' => 5, 'conflicting_coordinate_groups' => 1], 'Conflicting/deduplicated address groups incorrect');
diagnosticCheck($report['by_role']['customer']['total'] === 1 && $report['by_role']['supplier']['total'] === 1 && $report['by_role']['unclassified']['total'] === 24 && !isset($report['by_role']['partner']), 'Role scope, overlap or unclassified count incorrect');
diagnosticCheck($report['by_country']['DE']['total'] === 21 && $report['by_country']['AT']['total'] === 1 && $report['by_country']['unknown']['total'] === 3 && !isset($report['by_country']['US']), 'Country distribution exposed foreign/alternate address');
diagnosticCheck(array_keys($report['by_contact_client']) === [100], 'Foreign tenant in distribution');
diagnosticCheck($report['external_requests'] === 0 && $report['database_writes'] === 0, 'Diagnosis performs work');
diagnosticCheck(!str_contains(json_encode($report), 'PRIVATE_') && !str_contains(json_encode($report), '10001'), 'Report exposed private address/contact data');
foreach ([['catid' => '100'], ['tagid' => '100'], ['role' => 'customer'], ['country' => 'AT'], ['keyword' => "DROP TABLE"], ['role' => 'supplier', 'catid' => '100', 'tagid' => '100', 'country' => 'DE', 'keyword' => 'PRIVATE_CITY']] as $change) {
	$params = array_replace($filters, $change);
	diagnosticCheck($model->diagnose(100, $params, $options)['counts']['total'] === (new Contacts_Model_Map())->summary($params, $options)['total'], 'Diagnostic/map filters diverged');
}
diagnosticReject(fn() => $model->diagnose(300, $filters, $options));
diagnosticReject(fn() => $model->diagnose(100, ['country' => 'XX'], $options));
mapActor(300);
diagnosticReject(fn() => $model->diagnose(100, $filters, $options));
mapActor(200);
Zend_Registry::set('Client', ['id' => 100, 'parentid' => 100, 'modules' => ['contacts' => 100]]);
diagnosticReject(fn() => $model->diagnose(100, $filters, $options));
mapActor(100);
Zend_Registry::set('Client', ['id' => 100, 'parentid' => 300, 'modules' => ['contacts' => 300]]);
diagnosticReject(fn() => $model->diagnose(100, $filters, $options));
mapActor(100);
Zend_Auth::getInstance()->getStorage()->write((object)['id' => 300, 'clientid' => 100]);
diagnosticReject(fn() => $model->diagnose(100, $filters, $options));
mapActor(100);
$db->rollBack();
diagnosticCheck($db->fetchAll('SELECT * FROM address ORDER BY id') === $before, 'Diagnosis changed addresses or coordinates');

$db->update('address', ['geoupdated' => null], ['id = ?' => $addresses[1]]);
diagnosticCheck($model->diagnose(100, $filters, $options)['plan']['potential_reuse_addresses'] === 3, 'Undated coordinates were offered for reuse');
$db->update('address', ['geoupdated' => '2026-01-02 00:00:00', 'latitude' => 0, 'longitude' => 0], ['id = ?' => $addresses[1]]);
diagnosticCheck($model->diagnose(100, $filters, $options)['plan']['potential_reuse_addresses'] === 3, 'Zero/zero coordinates were offered for reuse');
$db->update('address', ['latitude' => 51, 'longitude' => 7], ['id = ?' => $addresses[1]]);
$db->update('address', ['modified' => '2026-01-03 00:00:00'], ['id = ?' => $addresses[1]]);
diagnosticCheck($model->diagnose(100, $filters, $options)['plan']['potential_reuse_addresses'] === 3, 'Stale coordinates were offered for reuse');
$db->update('address', ['modified' => '2026-01-01 00:00:00'], ['id = ?' => $addresses[1]]);

$writer = Zend_Db::factory('Pdo_Mysql', $db->getConfig());
$db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
$db->beginTransaction();
diagnosticCheck($model->diagnose(100, $filters, $options) === $report, 'Baseline snapshot changed');
$writer->update('address', ['latitude' => null, 'longitude' => null], ['id = ?' => $addresses[1]]);
diagnosticCheck($model->diagnose(100, $filters, $options) === $report, 'Concurrent change produced inconsistent diagnosis');
$db->rollBack();
diagnosticCheck($db->fetchOne('SELECT latitude FROM address WHERE id = ?', $addresses[1]) === null, 'Diagnosis overwrote concurrent update');
$writer->update('address', ['latitude' => 51, 'longitude' => 7], ['id = ?' => $addresses[1]]);
$writer->closeConnection();

// More than one batch; duplicates and donors must survive page boundaries.
for ($n = 1; $n <= 501; $n++) $db->insert('contact', ['id' => 20000 + $n, 'contactid' => 20000 + $n, 'clientid' => 100, 'catid' => 0]);
$db->insert('address', ['parentid' => 20501, 'clientid' => 100, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'street' => 'PRIVATE_STREET 1', 'postcode' => '12345', 'city' => 'PRIVATE_CITY', 'country' => 'DE']);
$paged = $model->diagnose(100, $filters, $options);
diagnosticCheck($paged['counts']['total'] === 526 && $paged['plan']['eligible_addresses'] === 12 && $paged['plan']['unique_candidate_addresses'] === 10 && $paged['plan']['potential_reuse_addresses'] === 6, 'Batch pagination/deduplication failed');

// The real CLI must work with SELECT-only credentials, not the writable fixture account.
$db->query("CREATE USER 'diagnostic'@'localhost' IDENTIFIED BY 'synthetic-only'");
$db->query("GRANT SELECT ON contact_map_test.* TO 'diagnostic'@'localhost'");
$config = $work.'/readonly.ini';
file_put_contents($config, '[synthetic]'."\nresources.db.adapter = pdo_mysql\nresources.db.params.unix_socket = ". $work ."/mysql.sock\nresources.db.params.host = localhost\nresources.db.params.username = diagnostic\nresources.db.params.password = synthetic-only\nresources.db.params.dbname = contact_map_test\nresources.db.params.charset = utf8\n");
$tables = $db->fetchCol('SHOW TABLES');
$snapshot = [];
foreach ($tables as $table) $snapshot[$table] = $db->fetchAll('SELECT * FROM '.$db->quoteIdentifier($table).' ORDER BY id');
function diagnosticCli(array $arguments): array {
	global $root;
	$process = proc_open(array_merge(['php', '-d', 'allow_url_fopen=0', $root.'/application/cli/contact-geocoding-dry-run.php'], $arguments), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]); fclose($pipes[2]);
	return [proc_close($process), $output, $errors];
}
$args = ['--client=100', '--user=100', '--config='.$config, '--environment=synthetic'];
[$exit, $output, $errors] = diagnosticCli($args);
diagnosticCheck($exit === 0 && $errors === '', 'CLI failed with read-only credentials: '.$errors);
diagnosticCheck(json_decode($output, true, 512, JSON_THROW_ON_ERROR) === $paged, 'CLI/model results differ');
[$exit, $output, $errors] = diagnosticCli(array_merge($args, ['--catid=100', '--tagid=100', '--role=customer', '--country=DE', '--keyword=PRIVATE_CITY']));
diagnosticCheck($exit === 0 && $errors === '' && json_decode($output, true)['counts']['total'] === 1 && !str_contains($output, 'PRIVATE_'), 'CLI filters differ from map or expose search data');
foreach ([101, 102, 103, 300, 9999] as $user) {
	[$exit, $output, $errors] = diagnosticCli(array_replace($args, [1 => '--user='.$user]));
	diagnosticCheck($exit === 1 && $output === '' && !str_contains($errors, 'synthetic-only') && !str_contains($errors, 'SELECT'), 'Unauthorized CLI user admitted or exception leaked');
}
[$exit, $output] = diagnosticCli(array_replace($args, [1 => '--user=200']));
diagnosticCheck($exit === 0 && json_decode($output, true)['counts']['total'] === 526, 'Authorized child account cannot select parent 100');
foreach ([[], ['--client=300'], array_merge($args, ['--client=100']), array_merge($args, ['--run=1']), array_replace($args, [3 => '--environment=missing']), array_merge($args, ['--role=unknown'])] as $invalid) {
	[$exit, $output, $errors] = diagnosticCli($invalid);
	diagnosticCheck($exit === 1 && $output === '' && !str_contains($errors, $work), 'Invalid CLI arguments accepted or data leaked');
}
[$exit, $output, $errors] = diagnosticCli(['--help']);
diagnosticCheck($exit === 0 && str_contains($output, '--client=100') && $errors === '', 'CLI help unavailable');
foreach ($snapshot as $table => $rows) diagnosticCheck($db->fetchAll('SELECT * FROM '.$db->quoteIdentifier($table).' ORDER BY id') === $rows, 'CLI changed table '.$table);
echo 'PASS: '.$checks." geocoding diagnostic counts, map parity, deduplication, privacy, read-only CLI and authorization checks\n";
