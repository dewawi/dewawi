<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$socket = $argv[1] ?? '';
$work = realpath(dirname($socket));
if (!$work || !preg_match('~^/tmp/dewawi-document-recovery-db-[a-zA-Z0-9_]+$~D', $work) || $socket !== $work . '/mysql.sock') throw new RuntimeException('Only an isolated recovery test socket is accepted');
require __DIR__ . '/document-recovery.php';
umask(0077);
$root = dirname(__DIR__, 2);
$pdo = new PDO('mysql:unix_socket=' . $socket . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE recovery_fixture');
$pdo->exec('USE recovery_fixture');
$pdo->exec(file_get_contents($root . '/install/config/structure.sql'));
$checks = 0;
function recoveryTestCheck(bool $condition, string $message): void
{
	global $checks;
	if (!$condition) throw new RuntimeException($message);
	$checks++;
}
function recoveryTestInsert(string $table, array $values): void
{
	global $pdo;
	foreach ($pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) as $column) {
		if ($column['Field'] === 'id' || $column['Null'] === 'YES' || $column['Default'] !== null || array_key_exists($column['Field'], $values)) continue;
		$values[$column['Field']] = preg_match('/int|decimal|float|double/', $column['Type']) ? 0 : '';
	}
	$stmt = $pdo->prepare('INSERT INTO `' . $table . '` (`' . implode('`,`', array_keys($values)) . '`) VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')');
	$stmt->execute(array_values($values));
}
function recoveryTestCli(array $arguments, bool $success = true): array
{
	global $root, $socket, $work;
	$command = [PHP_BINARY, '-d', 'error_reporting=8191', '-d', 'display_errors=stderr', '-d', 'pdo_mysql.default_socket=' . $socket, $root . '/tests/documents/document-recovery.php'];
	$arguments = array_replace(['config' => $work . '/' . (in_array($arguments['action'] ?? '', ['import', 'release'], true) ? 'archive.ini' : 'database.ini'), 'review' => $work . '/review', 'run' => $work . '/review/runs/initial/run.json', 'client' => 7, 'operator-user' => 42], $arguments);
	foreach ($arguments as $key => $value) $command[] = '--' . $key . ($value === true ? '' : '=' . $value);
	$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
	fclose($pipes[1]); fclose($pipes[2]);
	$exit = proc_close($process);
	if ($success && $exit !== 0) throw new RuntimeException('Recovery CLI failed: ' . $error . $out);
	if (!$success && $exit === 0) throw new RuntimeException('Unauthorized or invalid recovery unexpectedly succeeded');
	return ['exit' => $exit, 'json' => $out === '' ? null : json_decode($out, true, 512, JSON_THROW_ON_ERROR), 'error' => $error];
}
recoveryTestInsert('client', ['id' => 7, 'company' => 'Fixture seller', 'activated' => 1]);
recoveryTestInsert('client', ['id' => 8, 'company' => 'Other seller', 'activated' => 1]);
recoveryTestInsert('user', ['id' => 42, 'clientid' => 7, 'name' => 'Fixture reviewer', 'activated' => 1]);
recoveryTestInsert('user', ['id' => 43, 'clientid' => 8, 'name' => 'Foreign reviewer', 'activated' => 1]);
recoveryTestInsert('contact', ['id' => 500, 'contactid' => 12, 'clientid' => 7, 'name1' => 'Stored buyer']);
recoveryTestInsert('template', ['id' => 1, 'clientid' => 7, 'company' => 'Fixture seller', 'activated' => 1, 'default' => 1]);
$permissions = ['userid' => 42, 'clientid' => 7];
foreach (DEEC_DocumentArchive::documents() as $module => $controllers) $permissions[$module] = json_encode(array_fill_keys($controllers, ['view', 'edit']));
$permissions['contacts'] = '{"contact":["view"]}';
recoveryTestInsert('permission', $permissions);
$base = $work . '/source';
mkdir($base, 0700);
symlink($root . '/library', $base . '/library');
symlink($root . '/languages', $base . '/languages');
$pdo->exec("CREATE USER 'recovery_reader'@'localhost' IDENTIFIED BY 'fixture-only'");
$pdo->exec("CREATE USER 'recovery_archiver'@'localhost' IDENTIFIED BY 'fixture-only'");
$pdo->exec("GRANT SELECT ON recovery_fixture.* TO 'recovery_reader'@'localhost'");
$pdo->exec("GRANT SELECT ON recovery_fixture.* TO 'recovery_archiver'@'localhost'");
$pdo->exec("GRANT INSERT ON recovery_fixture.documentarchive TO 'recovery_archiver'@'localhost'");
$pdo->exec("GRANT UPDATE (parameters) ON recovery_fixture.documentarchive TO 'recovery_archiver'@'localhost'");
foreach (['database.ini' => 'recovery_reader', 'archive.ini' => 'recovery_archiver'] as $filename => $username) file_put_contents($work . '/' . $filename, "[production]\nresources.db.params.host = 'localhost'\nresources.db.params.username = '$username'\nresources.db.params.password = 'fixture-only'\nresources.db.params.dbname = 'recovery_fixture'\n");
$review = $work . '/review';
reviewDirectory($review);
reviewDirectory($review . '/runs/initial');
reviewWrite($review . '/review.json', reviewJson(['schema_version' => 1, 'clientid' => 7, 'contacts_clientid' => 7, 'base' => $base]));
reviewWrite($review . '/.collector.lock', '');
$run = ['clientid' => 7, 'base' => $base, 'documents' => []];
$identities = [];
$tables = [];
$id = 0;
foreach (DEEC_DocumentArchive::documents() as $module => $controllers) foreach ($controllers as $controller) {
	$id++;
	$identity = ['module' => $module, 'controller' => $controller, 'id' => $id, 'clientid' => 7, 'document_number' => (string)(7000 + $id)];
	$identities[reviewKey($identity)] = $identity;
	$values = ['id' => $id, 'clientid' => 7, 'contactid' => 12, $controller . 'id' => 7000 + $id, $controller . 'date' => '2010-01-02', 'filename' => $controller === 'invoice' ? '' : $controller . '.pdf', 'state' => 105, 'completed' => 1, 'currency' => 'EUR', 'language' => 'de_DE', 'templateid' => 1,
		'subtotal' => '100.0000', 'taxes' => '19.0000', 'total' => '119.0000', 'billingname1' => 'Stored buyer', 'billingstreet' => 'Stored street', 'billingcity' => 'Berlin', 'billingcountry' => 'DE', 'header' => '', 'footer' => '', 'notes' => 'internal', 'prepayment' => 0];
	$columns = $pdo->query('SHOW COLUMNS FROM `' . $controller . '`')->fetchAll(PDO::FETCH_COLUMN);
	$values = array_intersect_key($values, array_flip($columns));
	foreach ($columns as $field) if (str_starts_with($field, 'pdfshow')) $values[$field] = in_array($field, ['pdfshowprices', 'pdfshowdiscounts'], true) ? 1 : 0;
	recoveryTestInsert($controller, $values);
	recoveryTestInsert($controller . 'pos', ['id' => $id, 'parentid' => $id, 'clientid' => 7, 'title' => 'Stored position', 'description' => '', 'sku' => 'SAVED', 'price' => 100, 'quantity' => 1, 'taxrate' => 19, 'total' => $id % 2 ? 363 : 100, 'currency' => 'EUR', 'masterid' => 0, 'ordering' => 1, 'uom' => 'Piece']);
	$tables[] = $controller; $tables[] = $controller . 'pos';
	$directory = $module . '-' . $controller . '-' . $id . '-' . (7000 + $id);
	reviewDirectory($review . '/' . $directory . '/candidates');
	reviewDirectory($review . '/' . $directory . '/external');
	$selected = $identity + ['reported_reasons' => ['original_missing']];
	reviewWrite($review . '/' . $directory . '/manifest.json', reviewJson(['selected' => $selected, 'candidates' => [], 'sources' => [], 'suggestion' => null]));
	reviewWrite($review . '/' . $directory . '/selection.json', reviewJson(['schema_version' => 1, 'document' => $identity, 'decision' => 'pending']));
	$run['documents'][] = ['key' => reviewKey($identity), 'manifest' => $directory . '/manifest.json'];
}
reviewWrite($review . '/runs/initial/run.json', reviewJson($run));
$pdo->exec('UPDATE invoice SET contactid = NULL WHERE id = 3');
foreach (['salesorder', 'deliveryorder', 'creditnote', 'reminder', 'quoterequest', 'purchaseorder'] as $table) $pdo->exec('UPDATE `' . $table . '` SET contactid = NULL');
$pdo->exec('UPDATE purchaseorderpos SET total = NULL');
recoveryTestInsert('pricerulepos', ['id' => 800, 'parentid' => 8, 'clientid' => 7, 'module' => 'purchases', 'controller' => 'purchaseorderpos', 'action' => null, 'amount' => 0]);
$pdo->exec('UPDATE invoice SET subtotal = 3631.4100, taxes = 689.9679, total = 4321.3779 WHERE id = 3');
$pdo->exec('UPDATE invoicepos SET price = 2744.5500, total = 2744.5500 WHERE id = 3');
foreach ([[30, 960.97, 363], [31, -74.11, 19]] as [$lineId, $price, $stored]) recoveryTestInsert('invoicepos', ['id' => $lineId, 'parentid' => 3, 'clientid' => 7, 'title' => 'Historical invoice line ' . $lineId, 'price' => $price, 'quantity' => 1, 'taxrate' => 19, 'total' => $stored, 'currency' => 'EUR', 'masterid' => 0, 'ordering' => $lineId]);

$amountDb = recoveryBoot(migrationConfig($work . '/database.ini', 'production') + ['unix_socket' => $socket], $base, 7, 42);
class RecoveryAmountTestRenderer extends DEEC_Pdf
{
	public function amounts(array $document, array $rows): array { return $this->validateReconstructionAmounts($document, new Zend_Db_Table_Rowset(['data' => $rows]), 'sales', 'invoice', 7); }
	public function displayed(array $payload, array $document, array $rows, array $calculation): array { return $this->assertReconstructionPayload($payload, $document, new Zend_Db_Table_Rowset(['data' => $rows]), $calculation); }
}
function recoveryAmountReject(callable $operation, string $message): void
{
	try { $operation(); } catch (RuntimeException $expected) { recoveryTestCheck(true, $message); return; }
	throw new RuntimeException($message);
}
$amountDb->query('SET TRANSACTION READ ONLY');
$amountDb->beginTransaction();
$contactDb = new Contacts_Model_DbTable_Contact();
try {
	$contactDb->fetchAll($contactDb->select()->where('contactid = ?', null));
	throw new RuntimeException('Legacy NULL contact predicate did not reproduce the SQL error');
} catch (Zend_Db_Statement_Exception $expected) { recoveryTestCheck(str_contains($expected->getMessage(), 'contactid = ?'), 'Wrong NULL-contact regression error'); }
$storedRecipient = ['clientid' => 7, 'contactid' => null, 'billingname1' => 'Historical buyer', 'billingstreet' => 'Stored street', 'billingcity' => 'Berlin', 'billingcountry' => 'DE'];
$recipient = $contactDb->getHistoricalDocumentRecipient($storedRecipient);
recoveryTestCheck($recipient['contact'] === [] && $recipient['evidence']['contact_mapping'] === 'missing', 'Missing contact snapshot selected a replacement contact');
recoveryAmountReject(static fn() => $contactDb->getHistoricalDocumentRecipient(array_replace($storedRecipient, ['billingstreet' => ''])), 'Incomplete historical recipient was silently filled');
$amountRenderer = new RecoveryAmountTestRenderer();
$amountDocument = ['subtotal' => 100, 'taxes' => 19, 'total' => 119, 'currency' => 'EUR', 'taxfree' => 0];
$amountRow = ['id' => 1000, 'price' => 100, 'quantity' => 1, 'taxrate' => 19, 'total' => 119, 'masterid' => 0, 'pricerulemaster' => 0, 'currency' => 'EUR'];
foreach ([119 => 'matches_gross', 100 => 'matches_net', 363 => 'historical_position_total_conflict'] as $stored => $classification) {
	[$calculation, $validation] = $amountRenderer->amounts($amountDocument, [array_replace($amountRow, ['total' => $stored])]);
	recoveryTestCheck($validation['positions'][1000]['classification'] === $classification && $validation['original_comparison_required'] === ($stored !== 119), 'Historical net/gross/intermediate total was misclassified');
}
[, $missingTotal] = $amountRenderer->amounts($amountDocument, [array_replace($amountRow, ['total' => null])]);
recoveryTestCheck($missingTotal['positions'][1000]['stored_total'] === null && $missingTotal['positions'][1000]['difference_to_net'] === null && $missingTotal['positions'][1000]['classification'] === 'stored_total_missing' && $missingTotal['original_comparison_required'], 'Missing derived position total was invented or lost');
recoveryAmountReject(static fn() => $amountRenderer->amounts($amountDocument, [array_replace($amountRow, ['price' => null])]), 'Missing unit price was replaced with zero');
foreach ([0.01, 0.10, 0.11] as $difference) {
	[, $validation] = $amountRenderer->amounts($amountDocument, [array_replace($amountRow, ['total' => 119 + $difference])]);
	recoveryTestCheck($validation['positions'][1000]['classification'] === 'historical_position_total_conflict', 'An unexplained small difference was silently classified as rounding');
	$quantity = $difference / 0.004;
	$roundedRow = array_replace($amountRow, ['price' => 0.004, 'quantity' => $quantity, 'total' => 0]);
	$header = ['subtotal' => $difference, 'taxes' => round($difference * 0.19, 2), 'total' => round($difference * 1.19, 2), 'currency' => 'EUR'];
	[, $validation] = $amountRenderer->amounts($header, [$roundedRow]);
	recoveryTestCheck($validation['positions'][1000]['classification'] === ($difference <= 0.10 ? 'historical_rounding' : 'historical_position_total_conflict'), 'Historical unit-price rounding crossed the inclusive ten-cent limit');
	if ($difference <= 0.10) recoveryTestCheck($validation['positions'][1000]['rounding_basis']['reference'] === 'net', 'Net rounding was compared against gross');
}
foreach ([2 => 0.01, 23 => 0.10, 25 => 0.11] as $count => $difference) {
	$rows = [];
	for ($i = 0; $i < $count; $i++) $rows[] = array_replace($amountRow, ['id' => 2000 + $i, 'price' => 0.03, 'total' => 0.04]);
	$header = ['subtotal' => round($count * 0.03, 2), 'taxes' => $count * 0.01, 'total' => round($count * 0.04, 2), 'currency' => 'EUR'];
	if ($difference > 0.10) recoveryAmountReject(static fn() => $amountRenderer->amounts($header, $rows), 'Eleven-cent header drift was accepted');
	else {
		[, $validation] = $amountRenderer->amounts($header, $rows);
		recoveryTestCheck($validation['header_basis'] === 'line_rounded_tax' && abs($validation['header']['taxes']['difference'] - $difference) < 0.000001, 'Reproducible historical header rounding was rejected');
	}
}
recoveryAmountReject(static fn() => $amountRenderer->amounts(array_replace($amountDocument, ['total' => 120]), [$amountRow]), 'Inconsistent header was accepted');
recoveryAmountReject(static fn() => $amountRenderer->amounts(array_replace($amountDocument, ['taxes' => 19.01, 'total' => 119.01]), [$amountRow]), 'Unexplained one-cent header drift was accepted');
recoveryAmountReject(static fn() => $amountRenderer->amounts(array_replace($amountDocument, ['subtotal' => 90, 'taxes' => 17.1, 'total' => 107.1]), [$amountRow]), 'Missing discount rules were invented');
recoveryAmountReject(static fn() => $amountRenderer->amounts($amountDocument, [array_replace($amountRow, ['masterid' => 999])]), 'Missing price-rule master was accepted');
$amountDb->rollBack();
recoveryTestInsert('pricerulepos', ['id' => 1000, 'parentid' => 1000, 'clientid' => 7, 'module' => 'sales', 'controller' => 'invoicepos', 'action' => 'bypercent', 'amount' => 10]);
recoveryTestInsert('pricerulepos', ['id' => 1001, 'parentid' => 1001, 'clientid' => 7, 'module' => 'sales', 'controller' => 'invoicepos', 'action' => 'unknown', 'amount' => 10]);
recoveryTestInsert('pricerulepos', ['id' => 1002, 'parentid' => 1003, 'clientid' => 7, 'module' => 'sales', 'controller' => 'invoicepos', 'action' => null, 'amount' => 0]);
$amountDb->query('SET TRANSACTION READ ONLY'); $amountDb->beginTransaction();
[$calculation, $validation] = $amountRenderer->amounts(array_replace($amountDocument, ['subtotal' => 90, 'taxes' => 17.1, 'total' => 107.1]), [array_replace($amountRow, ['total' => 107.1])]);
recoveryTestCheck($calculation['row'][1000]['price'] === 90.0 && count($validation['positions'][1000]['applicable_price_rules']) === 1, 'Stored price rules were ignored');
[, $inheritedValidation] = $amountRenderer->amounts(['subtotal' => 108, 'taxes' => 20.52, 'total' => 128.52, 'currency' => 'EUR'], [array_replace($amountRow, ['pricerulemaster' => 1, 'total' => 107.1]), array_replace($amountRow, ['id' => 1002, 'price' => 20, 'masterid' => 1000, 'total' => 21.42])]);
recoveryTestCheck($inheritedValidation['positions'][1002]['price_rule_source'] === 1000 && $inheritedValidation['positions'][1002]['calculated_price'] === 18.0, 'Applicable inherited rules were lost');
recoveryAmountReject(static fn() => $amountRenderer->amounts($amountDocument, [array_replace($amountRow, ['id' => 1001])]), 'Unknown price-rule action was silently ignored');
recoveryAmountReject(static fn() => $amountRenderer->amounts($amountDocument, [$amountRow]), 'Contradictory price rules were ignored');
[, $emptyRule] = $amountRenderer->amounts($amountDocument, [array_replace($amountRow, ['id' => 1003])]);
recoveryTestCheck($emptyRule['warnings'][0]['code'] === 'zero_amount_price_rule_without_action', 'A provably ineffective empty rule was silently ignored');
$amountDb->rollBack();
$pdo->exec('UPDATE pricerulepos SET deleted = 1 WHERE id IN (1000, 1001, 1002)');
$amountDb->query('SET TRANSACTION READ ONLY'); $amountDb->beginTransaction();
[$calculation, $validation] = $amountRenderer->amounts($amountDocument, [$amountRow]);
$currency = Zend_Registry::get('Zend_Currency');
$payload = ['document' => ['subtotal' => $currency->toCurrency(100), 'taxes' => $currency->toCurrency(19), 'total' => $currency->toCurrency(119)], 'positions' => new Zend_Db_Table_Rowset(['data' => [['id' => 1000, 'price' => $calculation['locale'][1000]['price'], 'total' => $calculation['locale'][1000]['total'], 'quantity' => '1', 'masterid' => 0]]])];
recoveryTestCheck(count($amountRenderer->displayed($payload, $amountDocument, [$amountRow], $calculation)['positions']) === 1, 'Consistent displayed amounts were rejected');
$wrongHeader = $payload; $wrongHeader['document']['total'] = $currency->toCurrency(118);
recoveryAmountReject(static fn() => $amountRenderer->displayed($wrongHeader, $amountDocument, [$amountRow], $calculation), 'Wrong PDF header was accepted');
$wrongPosition = $payload; $wrongPosition['positions'] = new Zend_Db_Table_Rowset(['data' => [['id' => 1000, 'price' => 'wrong', 'total' => 'wrong', 'quantity' => '1']]]);
recoveryAmountReject(static fn() => $amountRenderer->displayed($wrongPosition, $amountDocument, [$amountRow], $calculation), 'Wrong PDF line amounts were accepted');
$amountDb->rollBack(); $amountDb->closeConnection();
recoveryTestInsert('client', ['id' => 9, 'parentid' => 7, 'company' => 'Child fixture', 'activated' => 1]);
recoveryTestInsert('user', ['id' => 44, 'clientid' => 9, 'name' => 'Child reviewer', 'activated' => 1]);
recoveryTestInsert('permission', ['userid' => 44, 'clientid' => 9, 'sales' => '{"quote":["view","edit"]}', 'contacts' => '{"contact":["view"]}']);
recoveryTestInsert('template', ['id' => 2, 'clientid' => 9, 'company' => 'Child seller', 'activated' => 1]);
$childDocument = array_replace($pdo->query('SELECT * FROM quote WHERE id = 1')->fetch(PDO::FETCH_ASSOC), ['id' => 9, 'clientid' => 9, 'quoteid' => 7099, 'templateid' => 2, 'filename' => 'child.pdf', 'contactid' => null]);
recoveryTestInsert('quote', $childDocument);
$childPosition = array_replace($pdo->query('SELECT * FROM quotepos WHERE parentid = 1')->fetch(PDO::FETCH_ASSOC), ['id' => 9, 'parentid' => 9, 'clientid' => 9]);
recoveryTestInsert('quotepos', $childPosition);
$childRoot = $work . '/child-review';
$childDirectory = $childRoot . '/sales-quote-9-7099';
$childIdentity = ['module' => 'sales', 'controller' => 'quote', 'id' => 9, 'clientid' => 9, 'document_number' => '7099'];
reviewDirectory($childRoot . '/runs/initial');
reviewDirectory($childDirectory . '/candidates'); reviewDirectory($childDirectory . '/external');
reviewWrite($childRoot . '/.collector.lock', '');
reviewWrite($childRoot . '/review.json', reviewJson(['schema_version' => 1, 'clientid' => 9, 'contacts_clientid' => 9, 'base' => $base]));
reviewWrite($childRoot . '/runs/initial/run.json', reviewJson(['clientid' => 9, 'base' => $base, 'documents' => [['key' => reviewKey($childIdentity), 'manifest' => 'sales-quote-9-7099/manifest.json']]]));
reviewWrite($childDirectory . '/manifest.json', reviewJson(['selected' => $childIdentity + ['reported_reasons' => ['original_missing']], 'sources' => [], 'candidates' => [], 'suggestion' => null]));
reviewWrite($childDirectory . '/selection.json', reviewJson(['document' => $childIdentity, 'decision' => 'pending']));
$before = [];
$tables[] = 'pricerulepos';
foreach (DEEC_DocumentArchive::documents() as $controllers) foreach ($controllers as $controller) $tables[] = $controller . 'posset';
foreach ($tables as $table) $before[$table] = $pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$childOptions = ['action' => 'prepare', 'only' => reviewKey($childIdentity), 'review' => $childRoot, 'run' => $childRoot . '/runs/initial/run.json', 'client' => 9, 'operator-user' => 44];
recoveryTestCli($childOptions, false);
file_put_contents($childRoot . '/review.json', reviewJson(['schema_version' => 1, 'clientid' => 9, 'contacts_clientid' => 7, 'base' => $base]));
recoveryTestCli($childOptions);
$childRecords = glob($childDirectory . '/reconstructions/*.json');
recoveryTestCheck(count($childRecords) === 1 && (int)recoveryJsonFile($childRecords[0])['dependencies']['template']['clientid'] === 9, 'Authorized parent contact sharing lost child document ownership');
$childPlan = recoveryTestCli(array_replace($childOptions, ['action' => 'release-plan', 'attest-previous-paper-match' => recoveryJsonFile($childRecords[0])['sha256']]));
recoveryTestCheck($childPlan['json']['results'][reviewKey($childIdentity)]['status'] === 'eligible', 'Release plan rejected authorized parent-child contacts');
recoveryTestCli(['action' => 'prepare', 'only' => 'sales/quote/1', 'operator-user' => 43], false);
$pdo->exec("UPDATE permission SET sales = '{\"quote\":[\"view\"]}' WHERE userid = 42");
recoveryTestCli(['action' => 'prepare', 'only' => 'sales/quote/1'], false);
$stmt = $pdo->prepare('UPDATE permission SET sales = ? WHERE userid = 42'); $stmt->execute([$permissions['sales']]);
$pdo->exec('UPDATE contact SET deleted = 1 WHERE id = 500');
$pdo->exec("UPDATE quote SET billingstreet = '' WHERE id = 1");
recoveryTestCli(['action' => 'prepare', 'only' => 'sales/quote/1'], false);
$pdo->exec("UPDATE quote SET billingstreet = 'Stored street' WHERE id = 1");
$pdo->exec('UPDATE contact SET deleted = 0 WHERE id = 500');
recoveryTestInsert('contact', ['id' => 501, 'clientid' => 7, 'contactid' => 12, 'name1' => 'Ambiguous buyer']);
$pdo->exec("UPDATE quote SET billingstreet = '' WHERE id = 1");
recoveryTestCli(['action' => 'prepare', 'only' => 'sales/quote/1'], false);
$pdo->exec("UPDATE quote SET billingstreet = 'Stored street' WHERE id = 1");
$pdo->exec('UPDATE contact SET deleted = 1 WHERE id = 501');
$pdo->exec('UPDATE quote SET total = 120 WHERE id = 1');
recoveryTestCli(['action' => 'prepare', 'only' => 'sales/quote/1'], false);
$pdo->exec('UPDATE quote SET total = 119 WHERE id = 1');
recoveryTestCheck(glob($review . '/sales-quote-1-7001/candidates/*.pdf') === [], 'Rejected reconstruction left a PDF');
$prepared = recoveryTestCli(['action' => 'prepare']);
recoveryTestCheck(count($prepared['json']['results']) === 8, 'Preparation did not cover all eight types');
recoveryTestCli(['action' => 'prepare', 'only' => 'sales/quote/1']);
$reusedRecordPath = glob($review . '/sales-quote-1-7001/reconstructions/*.json')[0];
$reusedRecord = recoveryJsonFile($reusedRecordPath);
$reusedRecord['dependencies']['renderer_sha256'] = str_repeat('0', 64);
file_put_contents($reusedRecordPath, reviewJson($reusedRecord));
$reused = recoveryTestCli(['action' => 'prepare', 'only' => 'sales/quote/1']);
recoveryTestCheck($reused['json']['results']['sales/quote/1']['status'] === 'already_prepared' && count(glob($review . '/sales-quote-1-7001/reconstructions/*.json')) === 1, 'A renderer source change invalidated an otherwise valid prepared PDF');
$candidateHashes = [];
foreach (glob($review . '/*/candidates/*.pdf') as $path) $candidateHashes[$path] = [hash_file('sha256', $path), filemtime($path)];
$plan = recoveryTestCli(['action' => 'release-plan']);
recoveryTestCheck(count(array_filter($plan['json']['results'], static fn(array $row): bool => $row['category'] === 'original_comparison_required')) === 8, 'Batch policy waived required original comparisons');
recoveryTestCheck((int)$pdo->query('SELECT COUNT(*) FROM documentarchive')->fetchColumn() === 0 && recoveryJsonFile($review . '/sales-quote-1-7001/selection.json')['decision'] === 'pending', 'Release plan changed an archive or selection');
recoveryTestCli(['action' => 'release', 'confirm-client' => 7, 'backup-verified' => true], false);
recoveryTestCli(['action' => 'release', 'confirm-client' => 7, 'backup-verified' => true, 'attest-previous-paper-match' => str_repeat('0', 64)], false);
recoveryTestCli(['action' => 'release', 'only' => 'sales/invoice/3', 'confirm-client' => 7, 'backup-verified' => true, 'attest-previous-paper-match' => str_repeat('0', 64)], false);
$decisions = [];
foreach ($run['documents'] as $row) {
	$directory = dirname($review . '/' . $row['manifest']);
	$records = glob($directory . '/reconstructions/*.json');
	recoveryTestCheck(count($records) === 1, 'Missing reconstruction provenance: ' . $row['key']);
	$record = recoveryJsonFile($records[0]);
	$file = (new DEEC_DocumentArchive($review))->inspect($directory . '/candidates/' . $record['sha256'] . '.pdf', 'pdf');
	recoveryTestCheck($file['status'] === 'ok' && $file['sha256'] === $record['sha256'], 'Real renderer did not create a complete PDF');
	recoveryTestCheck($record['validation']['original_comparison_required'] && count($record['validation']['warnings']) > 0 && isset($record['validation']['displayed']), 'Historical line discrepancies lost their protected reconstruction warnings');
	if ($row['key'] === 'sales/invoice/3') {
		$line = $record['validation']['positions'][31];
		recoveryTestCheck($line['stored_total'] === '19.0000' && $line['calculated_net'] === -74.11 && $line['classification'] === 'historical_position_total_conflict', 'Negative historical line was corrected or misclassified');
		$text = []; $textCode = 0;
		exec('pdftotext -layout ' . escapeshellarg($file['path']) . ' -', $text, $textCode);
		$pdfText = implode("\n", $text);
		foreach (['2.744,55', '960,97', '-74,11', '3.631,41', '689,97', '4.321,38'] as $amount) recoveryTestCheck($textCode === 0 && str_contains($pdfText, $amount), 'Real historical PDF omitted the approved amount basis: ' . $amount);
	}
	$decisions[] = ['document' => $identities[$row['key']], 'decision' => 'selected', 'candidate_path' => 'candidates/' . $record['sha256'] . '.pdf', 'sha256' => $record['sha256'], 'reason' => 'Fixture manual comparison with saved records and paper', 'origin' => ['kind' => 'reconstructed', 'source_path' => 'stored database snapshot', 'evidence' => 'fixture']];
}
recoveryTestCli(['action' => 'approve'], false);
$linked = $review . '/sales-quote-1-7001/external/linked.pdf';
symlink($review . '/sales-quote-1-7001/' . $decisions[0]['candidate_path'], $linked);
$unsafeDecision = array_replace($decisions[0], ['candidate_path' => 'external/linked.pdf', 'origin' => ['kind' => 'datev', 'source_path' => 'fixture']]);
reviewWrite($work . '/unsafe-decisions.jsonl', json_encode($unsafeDecision) . "\n");
recoveryTestCli(['action' => 'select', 'decisions' => $work . '/unsafe-decisions.jsonl'], false);
recoveryTestCheck(recoveryJsonFile($review . '/sales-quote-1-7001/selection.json')['decision'] === 'pending', 'Rejected source overwrote manual selection');
unlink($linked);
reviewWrite($work . '/decisions.jsonl', implode("\n", array_map('json_encode', $decisions)) . "\n");
recoveryTestCli(['action' => 'select', 'decisions' => $work . '/decisions.jsonl']);
recoveryTestCli(['action' => 'approve'], false);
$comparisonDenied = recoveryTestCli(['action' => 'approve', 'only' => 'sales/quote/1'], false);
recoveryTestCheck(str_contains($comparisonDenied['json']['results']['sales/quote/1']['error'], 'explicit documented comparison'), 'A generic selection reason bypassed the original-comparison gate');
$digital = $decisions[0];
$digital['original_comparison'] = ['kind' => 'verified_dispatch_copy', 'reference' => 'Fixture verified SMTP attachment', 'compared_by' => 42, 'compared_at' => gmdate('c'), 'amounts_match' => true, 'evidence_path' => 'external/sent.pdf', 'evidence_sha256' => str_repeat('0', 64)];
$evidence = $review . '/sales-quote-1-7001/external/sent.pdf';
reviewWrite($evidence, "%PDF-1.4\nfixture verified dispatch copy\n%%EOF\n");
file_put_contents($work . '/decisions.jsonl', json_encode($digital) . "\n");
recoveryTestCli(['action' => 'select', 'decisions' => $work . '/decisions.jsonl']);
recoveryTestCli(['action' => 'approve', 'only' => 'sales/quote/1'], false);
$digital['original_comparison']['evidence_sha256'] = hash_file('sha256', $evidence);
file_put_contents($work . '/decisions.jsonl', json_encode($digital) . "\n");
recoveryTestCli(['action' => 'select', 'decisions' => $work . '/decisions.jsonl']);
recoveryTestCli(['action' => 'approve', 'only' => 'sales/quote/1']);
file_put_contents($evidence, "%PDF-1.4\nchanged evidence\n%%EOF\n");
$evidenceDenied = recoveryTestCli(['action' => 'import', 'only' => 'sales/quote/1', 'confirm-client' => 7, 'backup-verified' => true], false);
recoveryTestCheck(str_contains($evidenceDenied['json']['results']['sales/quote/1']['error'], 'comparison evidence'), 'Archive import did not recheck the original-comparison evidence');
recoveryTestCheck((int)$pdo->query('SELECT COUNT(*) FROM documentarchive')->fetchColumn() === 0, 'Rejected original comparison wrote archive metadata');
foreach ($decisions as &$decision) $decision['original_comparison'] = ['kind' => 'paper_document', 'reference' => 'Isolated fixture paper archive, document ' . $decision['document']['document_number'], 'compared_by' => 42, 'compared_at' => gmdate('c'), 'amounts_match' => true];
unset($decision);
file_put_contents($work . '/decisions.jsonl', implode("\n", array_map('json_encode', $decisions)) . "\n");
recoveryTestCli(['action' => 'select', 'decisions' => $work . '/decisions.jsonl']);
recoveryTestCli(['action' => 'approve']);
recoveryTestCli(['action' => 'import'], false);
$import = ['action' => 'import', 'confirm-client' => '7', 'backup-verified' => true];
$validationPath = glob($review . '/sales-quote-1-7001/reconstructions/*.json')[0];
$validationBytes = file_get_contents($validationPath);
$legacyRecord = json_decode($validationBytes, true, 512, JSON_THROW_ON_ERROR);
unset($legacyRecord['validation']);
file_put_contents($validationPath, reviewJson($legacyRecord));
recoveryTestCli(['action' => 'approve', 'only' => 'sales/quote/1'], false);
recoveryTestCli($import + ['only' => 'sales/quote/1'], false);
file_put_contents($validationPath, $validationBytes);
$changedValidation = json_decode($validationBytes, true, 512, JSON_THROW_ON_ERROR);
$changedValidation['validation']['warnings'] = [];
file_put_contents($validationPath, reviewJson($changedValidation));
$validationDenied = recoveryTestCli($import + ['only' => 'sales/quote/1'], false);
recoveryTestCheck(str_contains($validationDenied['json']['results']['sales/quote/1']['error'], 'validation has changed'), 'Approved reconstruction warnings could be changed before import');
file_put_contents($validationPath, $validationBytes);
$pdo->exec('UPDATE quotepos SET total = 120 WHERE parentid = 1');
recoveryTestCli($import + ['only' => 'sales/quote/1'], false);
$pdo->exec('UPDATE quotepos SET total = 363 WHERE parentid = 1');
$source = $review . '/sales-quote-1-7001/' . $decisions[0]['candidate_path'];
$sourceBytes = file_get_contents($source);
file_put_contents($source, "%PDF-1.4\ntampered source\n%%EOF\n");
recoveryTestCli($import + ['only' => 'sales/quote/1'], false);
file_put_contents($source, $sourceBytes);
touch($source, $candidateHashes[$source][1]);
$canonicalPath = (new DEEC_DocumentArchive($base))->path(7, 'sales', 'quote', 1);
reviewDirectory(dirname($canonicalPath));
reviewWrite($canonicalPath, "%PDF-1.4\nprotected existing original\n%%EOF\n");
recoveryTestCli($import + ['only' => 'sales/quote/1'], false);
recoveryTestCli(['action' => 'release', 'only' => 'sales/quote/1', 'confirm-client' => 7, 'backup-verified' => true, 'authorize-batch' => 'verified-evidence-v1'], false);
recoveryTestCheck(file_get_contents($canonicalPath) === "%PDF-1.4\nprotected existing original\n%%EOF\n", 'Existing canonical PDF was overwritten');
unlink($canonicalPath);
$pdo->exec("CREATE TRIGGER recovery_fail BEFORE INSERT ON documentarchive FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'isolated insert failure'");
recoveryTestCli($import + ['only' => 'sales/quote/1'], false);
recoveryTestCheck(is_file($canonicalPath) && (int)$pdo->query('SELECT COUNT(*) FROM documentarchive')->fetchColumn() === 0, 'Failed registration lost the verified PDF or committed metadata');
$pdo->exec('DROP TRIGGER recovery_fail');
$invoiceDecision = array_values(array_filter($decisions, static fn(array $row): bool => $row['document']['controller'] === 'invoice'))[0];
$paperRelease = ['action' => 'release', 'only' => 'sales/invoice/3', 'confirm-client' => 7, 'backup-verified' => true, 'attest-previous-paper-match' => $invoiceDecision['sha256']];
recoveryTestCli($paperRelease);
$invoiceArchive = $pdo->query("SELECT * FROM documentarchive WHERE controller = 'invoice'")->fetch(PDO::FETCH_ASSOC);
$invoiceProof = json_decode($invoiceArchive['parameters'], true)['recovery'];
$late = $invoiceProof['selection']['original_comparison'];
recoveryTestCheck($late['kind'] === 'previous_paper_match' && $late['comparison_performed_at'] === null && $late['paper_location'] === null && $late['reference'] === 'Paper document 7003' && $late['sha256'] === $invoiceDecision['sha256'] && $late['confirmed_by'] === 42 && strtotime($late['confirmed_at']) > 0, 'Retrospective paper release invented an original comparison date or location');
recoveryTestCheck($invoiceProof['approval']['method'] === 'operator_attestation' && $invoiceProof['approval']['individual_original_comparison_claimed'] === false && $invoiceProof['release_authorization']['database_and_file_backup_confirmed'], 'Late attestation was misrepresented as a newly performed original comparison');
recoveryTestCli($paperRelease);
recoveryTestCheck($pdo->query("SELECT * FROM documentarchive WHERE controller = 'invoice'")->fetch(PDO::FETCH_ASSOC) === $invoiceArchive, 'Repeated paper release changed existing archive metadata');
$batchRelease = ['action' => 'release', 'confirm-client' => 7, 'backup-verified' => true, 'authorize-batch' => 'verified-evidence-v1'];
recoveryTestCli($batchRelease);
recoveryTestCli($batchRelease);
foreach ($candidateHashes as $path => $state) recoveryTestCheck([hash_file('sha256', $path), filemtime($path)] === $state, 'Release rerendered or replaced a prepared PDF');
recoveryTestCheck((int)$pdo->query('SELECT COUNT(*) FROM documentarchive')->fetchColumn() === 8, 'Archive registration was duplicated or missed a type');
$existingOriginal = $pdo->query("SELECT * FROM documentarchive WHERE controller = 'quote'")->fetch(PDO::FETCH_ASSOC);
$existingBytes = file_get_contents($base . '/' . $existingOriginal['path']);
$pdo->exec("UPDATE documentarchive SET parameters = '{\"legacy_note\":\"must survive\"}' WHERE controller = 'quote'");
recoveryTestCli($import + ['only' => 'sales/quote/1']);
$reviewedOriginal = $pdo->query("SELECT * FROM documentarchive WHERE controller = 'quote'")->fetch(PDO::FETCH_ASSOC);
recoveryTestCheck(array_diff_key($existingOriginal, ['parameters' => true]) === array_diff_key($reviewedOriginal, ['parameters' => true]) && file_get_contents($base . '/' . $existingOriginal['path']) === $existingBytes, 'Approving an existing canonical original changed its file or registration fields');
recoveryTestCheck(json_decode($reviewedOriginal['parameters'], true)['legacy_note'] === 'must survive' && DEEC_DocumentArchive::hasRecoveryApproval($reviewedOriginal), 'Existing archive provenance was discarded or approval was lost');
$historical = (new DEEC_DocumentArchive($base))->paths(['clientid' => 7, 'module' => 'sales', 'controller' => 'quote', 'id' => 1, 'contactid' => 12, 'contact_record_id' => 500, 'filename' => 'quote.pdf']);
foreach (['current', 'legacy'] as $name) {
	reviewDirectory(dirname($historical[$name]));
	reviewWrite($historical[$name], "%PDF-1.4\nHistorical $name version\n%%EOF\n");
}
$migrationCommand = [PHP_BINARY, '-d', 'mysqli.default_socket=' . $socket, $root . '/tests/documents/document-archive-migration.php', '--config=' . $work . '/database.ini', '--base=' . $base, '--client=7', '--contacts-client=7'];
$process = proc_open($migrationCommand, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$auditOut = stream_get_contents($pipes[1]); $auditError = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
recoveryTestCheck(proc_close($process) === 0 && $auditError === '', 'Post-recovery full dry-run failed: ' . $auditError . $auditOut);
$auditRows = array_map(static fn(string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR), explode("\n", trim($auditOut)));
$auditSummary = end($auditRows)['summary'];
recoveryTestCheck($auditSummary['complete'] === 8 && $auditSummary['blocked'] === 0 && $auditSummary['missing_filenames'] === 1, 'Approved originals or missing filenames were misclassified');
$quotePlan = array_values(array_filter($auditRows, static fn(array $row): bool => $row['kind'] === 'document' && $row['plan']['controller'] === 'quote'))[0]['plan'];
recoveryTestCheck(in_array('historical_versions_differ', $quotePlan['historical_conflicts'], true), 'Post-recovery audit discarded historical conflict evidence');
$inspection = recoveryTestCli(['action' => 'inspect']);
$html = substr($inspection['json']['report'], 0, -5) . '.html';
recoveryTestCheck(str_contains(file_get_contents($html), '<iframe') && str_contains(file_get_contents($html), 'reconstructed'), 'Offline candidate comparison report is missing');
recoveryTestCheck(str_contains(file_get_contents($html), 'Originalvergleich vor Freigabe erforderlich') && str_contains(file_get_contents($html), 'historical_position_total_conflict'), 'Offline comparison omitted reconstruction warnings');
recoveryTestCli(['action' => 'retention-plan']);
recoveryTestCli(['action' => 'prepare'], false);
foreach ($before as $table => $rows) recoveryTestCheck($pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $rows, 'Recovery changed historical business values in ' . $table);
foreach ($pdo->query('SELECT * FROM documentarchive')->fetchAll(PDO::FETCH_ASSOC) as $row) {
	$file = (new DEEC_DocumentArchive($base))->inspect($base . '/' . $row['path'], 'pdf');
	recoveryTestCheck($file['sha256'] === $row['sha256'] && $file['size'] === (int)$row['filesize'] && DEEC_DocumentArchive::hasRecoveryApproval($row), 'Archive bytes or approval provenance differ');
}
$batchRoot = $work . '/batch-review';
reviewDirectory($batchRoot . '/runs/initial');
reviewWrite($batchRoot . '/.collector.lock', '');
reviewWrite($batchRoot . '/review.json', reviewJson(['schema_version' => 1, 'clientid' => 7, 'contacts_clientid' => 7, 'base' => $base]));
$batchRun = ['clientid' => 7, 'base' => $base, 'documents' => []];
$batchBytes = "%PDF-1.4\nverified historical dispatched original\n%%EOF\n";
$batchSha = hash('sha256', $batchBytes);
foreach ([51, 52, 53, 50] as $documentId) {
	$document = array_replace($pdo->query('SELECT * FROM quote WHERE id = 1')->fetch(PDO::FETCH_ASSOC), ['id' => $documentId, 'quoteid' => 8000 + $documentId, 'filename' => 'original-' . $documentId . '.pdf']);
	recoveryTestInsert('quote', $document);
	if ($documentId !== 53) recoveryTestInsert('quotepos', array_replace($pdo->query('SELECT * FROM quotepos WHERE parentid = 1')->fetch(PDO::FETCH_ASSOC), ['id' => $documentId, 'parentid' => $documentId]));
	$identity = ['module' => 'sales', 'controller' => 'quote', 'id' => $documentId, 'clientid' => 7, 'document_number' => (string)(8000 + $documentId)];
	$directoryName = 'sales-quote-' . $documentId;
	$directory = $batchRoot . '/' . $directoryName;
	reviewDirectory($directory . '/candidates'); reviewDirectory($directory . '/external');
	$candidates = [];
	if ($documentId !== 53) {
		reviewWrite($directory . '/candidates/' . $batchSha . '.pdf', $batchBytes);
		$candidates[] = ['path' => 'candidates/' . $batchSha . '.pdf', 'sha256' => $batchSha, 'size' => strlen($batchBytes)];
	}
	if ($documentId === 52) {
		$other = "%PDF-1.4\ndifferent historical bytes\n%%EOF\n"; $otherSha = hash('sha256', $other);
		reviewWrite($directory . '/candidates/' . $otherSha . '.pdf', $other);
		$candidates[] = ['path' => 'candidates/' . $otherSha . '.pdf', 'sha256' => $otherSha, 'size' => strlen($other)];
	}
	$sources = [];
	if ($documentId === 50) {
		$sentPath = 'files/attachments/emailmessage/7/0/7/0/500/1.pdf';
		reviewDirectory(dirname($base . '/' . $sentPath)); reviewWrite($base . '/' . $sentPath, $batchBytes);
		recoveryTestInsert('emailattachment', ['id' => 500, 'clientid' => 7, 'documentid' => 50, 'module' => 'sales', 'controller' => 'quote', 'filename' => $document['filename'], 'filesize' => strlen($batchBytes), 'location' => dirname($base . '/' . $sentPath)]);
		recoveryTestInsert('emailmessage', ['id' => 500, 'clientid' => 7, 'documentid' => 50, 'module' => 'sales', 'controller' => 'quote', 'messagesent' => '2010-01-02 10:00:00', 'deliverystatus' => 'accepted']);
		recoveryTestInsert('emailmessageattachment', ['id' => 500, 'clientid' => 7, 'emailmessageid' => 500, 'emailattachmentid' => 500, 'filename' => $document['filename'], 'path' => $sentPath, 'filesize' => strlen($batchBytes), 'sha256' => $batchSha, 'ordering' => 1]);
		$sources[] = ['source' => 'emailmessageattachment', 'status' => 'ok', 'sha256' => $batchSha, 'path' => $base . '/' . $sentPath, 'metadata_integrity' => 'matches', 'evidence' => ['id' => 500]];
	}
	reviewWrite($directory . '/manifest.json', reviewJson(['selected' => $identity + ['reported_reasons' => ['original_missing']], 'document' => ['document_date' => '2010-01-02'], 'candidates' => $candidates, 'sources' => $sources]));
	reviewWrite($directory . '/selection.json', reviewJson(['document' => $identity, 'decision' => 'pending']));
	$batchRun['documents'][] = ['key' => reviewKey($identity), 'manifest' => $directoryName . '/manifest.json'];
}
$baselinePath = $batchRoot . '/baseline.jsonl';
$baselineRows = [['kind' => 'excluded_document', 'record' => ['module' => 'sales', 'controller' => 'quote', 'id' => 99, 'clientid' => 7, 'document_number' => '8099', 'document_date' => '2009-02-03', 'exclusion_reason' => 'soft_deleted_issued']], ['kind' => 'unassigned_file', 'classification' => 'unassigned_archive_candidate', 'file' => ['path' => $base . '/files/unassigned.pdf', 'sha256' => $batchSha]], ['kind' => 'summary', 'summary' => ['mode' => 'dry-run', 'clientid' => 7]]];
reviewWrite($baselinePath, implode("\n", array_map('json_encode', $baselineRows)) . "\n");
$batchRun['reports'] = [['path' => $baselinePath, 'sha256' => hash_file('sha256', $baselinePath)]];
reviewWrite($batchRoot . '/runs/initial/run.json', reviewJson($batchRun));
reviewWrite($batchRoot . '/runs/recovery-0000-fixture.json', reviewJson(['action' => 'prepare', 'clientid' => 7, 'results' => ['sales/quote/53' => ['status' => 'failed', 'error' => 'Stored document positions are missing']]]));
$batchOptions = ['review' => $batchRoot, 'run' => $batchRoot . '/runs/initial/run.json'];
$businessTables = array_merge($tables, ['contact', 'emailattachment', 'emailmessage', 'emailmessageattachment']);
$batchBefore = [];
foreach ($businessTables as $table) $batchBefore[$table] = $pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$archivedBefore = $pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$batchPlan = recoveryTestCli($batchOptions + ['action' => 'release-plan']);
$planned = $batchPlan['json']['results'];
recoveryTestCheck($planned['sales/quote/50']['category'] === 'eligible' && $planned['sales/quote/51']['category'] === 'candidate_evidence_missing' && $planned['sales/quote/52']['category'] === 'historical_conflict' && $planned['sales/quote/53']['category'] === 'business_data_incomplete', 'Batch plan conflated a found PDF with a proven original or lost failure categories: ' . json_encode($planned));
$planFile = $batchPlan['json']['report']; $planRecord = recoveryJsonFile($planFile);
recoveryTestCheck(count($planRecord['rows']) === 6 && isset($planRecord['summary']['year']['2009'], $planRecord['summary']['type']['sales/quote']) && is_file(substr($planFile, 0, -5) . '.csv') && (fileperms(substr($planFile, 0, -5) . '.csv') & 0777) === 0600, 'Private JSON/CSV inventory or summaries are incomplete');
recoveryTestCheck($pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $archivedBefore && recoveryJsonFile($batchRoot . '/sales-quote-50/selection.json')['decision'] === 'pending', 'Read-only batch plan changed originals or decisions');
$retried = recoveryTestCli($batchOptions + ['action' => 'prepare', 'retry-failed-from' => $batchRoot . '/runs/recovery-0000-fixture.json'], false);
recoveryTestCheck(array_keys($retried['json']['results']) === ['sales/quote/53'], 'Retry included already prepared or unrelated documents');
recoveryTestCli($batchOptions + ['action' => 'release', 'only' => 'sales/quote/50', 'operator-user' => 43, 'confirm-client' => 7, 'backup-verified' => true, 'authorize-batch' => 'verified-evidence-v1'], false);
$pdo->exec("CREATE TRIGGER recovery_fail BEFORE INSERT ON documentarchive FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'isolated batch insert failure'");
recoveryTestCli($batchOptions + $batchRelease + ['only' => 'sales/quote/50'], false);
$batchCanonical = (new DEEC_DocumentArchive($base))->path(7, 'sales', 'quote', 50);
recoveryTestCheck(is_file($batchCanonical) && (int)$pdo->query('SELECT COUNT(*) FROM documentarchive')->fetchColumn() === 8, 'Interrupted batch release lost the published PDF or committed a failed record');
$pdo->exec('DROP TRIGGER recovery_fail');
$batchResult = recoveryTestCli($batchOptions + $batchRelease);
recoveryTestCheck($batchResult['json']['results']['sales/quote/50']['category'] === 'archived' && $batchResult['json']['results']['sales/quote/51']['status'] === 'skipped', 'Unclear batch documents interrupted the eligible subset');
$batchOriginal = $pdo->query('SELECT * FROM documentarchive WHERE documentid = 50')->fetch(PDO::FETCH_ASSOC);
$batchProvenance = json_decode($batchOriginal['parameters'], true)['recovery'];
recoveryTestCheck($batchProvenance['approval']['method'] === 'batch_policy' && $batchProvenance['approval']['individual_original_comparison_claimed'] === false && $batchProvenance['release_authorization']['database_and_file_backup_confirmed'] && !isset($batchProvenance['selection']['original_comparison']), 'Batch policy falsely recorded an individual comparison or omitted backup authorization');
recoveryTestCli($batchOptions + $batchRelease);
recoveryTestCheck($pdo->query('SELECT * FROM documentarchive WHERE documentid = 50')->fetch(PDO::FETCH_ASSOC) === $batchOriginal && file_get_contents($batchCanonical) === $batchBytes, 'Repeated batch release changed original bytes or metadata');
foreach ($archivedBefore as $old) recoveryTestCheck($pdo->query('SELECT * FROM documentarchive WHERE id = ' . (int)$old['id'])->fetch(PDO::FETCH_ASSOC) === $old, 'Batch release changed an existing archived original');
foreach ($batchBefore as $table => $rows) recoveryTestCheck($pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $rows, 'Batch release changed business or email rows');
echo 'PASS: ' . $checks . " isolated recovery checks; eight types, retrospective attestations, policy releases, private reports and unchanged business rows\n";
