<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$socket = $argv[1] ?? '';
$work = realpath(dirname($socket));
if (!$work || !preg_match('~^/tmp/dewawi-document-completeness-[a-zA-Z0-9_]+$~D', $work) || $socket !== $work . '/mysql.sock') throw new RuntimeException('Only the disposable completeness database is accepted');
require dirname(__DIR__, 2) . '/tools/document-archive-completeness.php';
$root = dirname(__DIR__, 2); $base = $work . '/source';
umask(0077); mkdir($base, 0700); mkdir($work . '/reports', 0700);
$pdo = new PDO('mysql:unix_socket=' . $socket . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE completeness_fixture'); $pdo->exec('USE completeness_fixture');
$pdo->exec(file_get_contents($root . '/install/config/structure.sql'));
$pdo->exec("CREATE USER 'audit_reader'@'localhost' IDENTIFIED BY 'fixture-only'");
$pdo->exec("GRANT SELECT ON completeness_fixture.* TO 'audit_reader'@'localhost'");
$checks = 0;
function completeCheck(bool $condition, string $message): void { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; }
function completeSeed(string $table, array $values): void
{
	global $pdo;
	foreach ($pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) as $column) {
		if ($column['Field'] === 'id' || $column['Null'] === 'YES' || $column['Default'] !== null || array_key_exists($column['Field'], $values)) continue;
		$values[$column['Field']] = preg_match('/int|decimal|float|double/', $column['Type']) ? 0 : '';
	}
	$pdo->prepare('INSERT INTO `' . $table . '` (`' . implode('`,`', array_keys($values)) . '`) VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')')->execute(array_values($values));
}
function completeDocument(string $type, int $id, array $changes = []): void
{
	completeSeed($type, array_replace(['id' => $id, 'clientid' => 100, $type . 'id' => 112143 + $id, 'completed' => 1, 'cancelled' => 0, 'state' => 105, 'filename' => $type . '-' . $id . '.pdf'], $changes));
}
function completePdf(string $path, string $label = 'fixture'): void
{
	if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
	file_put_contents($path, "%PDF-1.4\n" . $label . "\n%%EOF\n"); chmod($path, 0600);
}
function completeRegistration(string $module, string $type, int $id, array $changes = []): void
{
	global $archive;
	$path = $archive->path(100, $module, $type, $id);
	completeSeed('documentarchive', array_replace(['clientid' => 100, 'module' => $module, 'controller' => $type, 'documentid' => $id, 'filename' => $type . '-' . $id . '.pdf', 'format' => 'pdf', 'version' => 1, 'path' => $archive->relativePath(100, $module, $type, $id), 'filesize' => filesize($path), 'sha256' => hash_file('sha256', $path), 'archived' => '2026-01-01 00:00:00'], $changes));
}
function completeCopy(string $module, string $type, int $id, string $locale = 'de_DE', array $changes = []): string
{
	global $base;
	$snapshot = DEEC_DocumentReview::viewingSnapshot($id, $module, $type);
	$bytes = "%PDF-1.4\nCopy $type $id\n%%EOF\n"; $sha = hash('sha256', $bytes);
	$directory = $base . '/files/documents/.viewing-copies/100/' . $module . '/' . $type . '/' . $id . '/' . $snapshot['sha256'] . '/' . $locale;
	mkdir($directory, 0700, true); $path = $directory . '/' . $sha . '.pdf'; file_put_contents($path, $bytes);
	$receipt = array_replace(['schema_version' => 1, 'identity' => ['clientid' => 100, 'module' => $module, 'controller' => $type, 'id' => $id], 'snapshot_sha256' => $snapshot['sha256'], 'locale' => $locale, 'status' => 'historical_reconstructed_viewing_copy', 'sha256' => $sha, 'size' => strlen($bytes), 'original' => false, 'archive_registration_allowed' => false], $changes);
	file_put_contents($directory . '/copy.json', DEEC_DocumentReview::json($receipt)); return $path;
}
function completeCli(array $changes = [], int $exit = 1): array
{
	global $work, $root, $socket;
	static $sequence = 0;
	$options = array_replace(['config' => $work . '/reader.ini', 'base' => $work . '/source', 'client' => 100, 'operator-user' => 42, 'web-user' => posix_getpwuid(posix_geteuid())['name'], 'output' => $work . '/reports/audit-' . (++$sequence), 'batch-size' => 2], $changes);
	$cmd = [PHP_BINARY, '-d', 'error_reporting=8191', '-d', 'memory_limit=48M', '-d', 'pdo_mysql.default_socket=' . $socket, $root . '/tools/document-archive-completeness.php'];
	foreach ($options as $key => $value) $cmd[] = '--' . $key . '=' . $value;
	$process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
	$actualExit = proc_close($process);
	if ($actualExit !== $exit) {
		$diagnostic = proc_open([PHP_BINARY, '-d', 'error_reporting=8191', '-d', 'pdo_mysql.default_socket=' . $socket, '-r', 'require $argv[1]; try { completenessMain(json_decode($argv[2], true)); } catch (Throwable $error) { echo $error; }', $root . '/tools/document-archive-completeness.php', json_encode(array_replace($options, ['output' => $options['output'] . '-diagnostic']))], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $debug);
		$error .= stream_get_contents($debug[1]) . stream_get_contents($debug[2]); fclose($debug[1]); fclose($debug[2]); proc_close($diagnostic);
	}
	completeCheck($actualExit === $exit, 'CLI exit differs: ' . $out . $error);
	completeCheck($out === '' && !preg_match('/fixture-only|112143|59505|source|SELECT |password|audit_reader/', $error), 'Console exposed private details');
	if ($exit === 2 && !is_file($options['output'] . '/summary.json')) return [];
	$result = ['summary' => json_decode(file_get_contents($options['output'] . '/summary.json'), true, 512, JSON_THROW_ON_ERROR), 'output' => $options['output']];
	completeCheck((fileperms($options['output']) & 0777) === 0700, 'Report directory is not private');
	foreach (['summary.json', 'documents.csv', 'problems.csv'] as $name) completeCheck((fileperms($options['output'] . '/' . $name) & 0777) === 0600, 'Report file is not private');
	foreach (['documents', 'problems'] as $name) {
		$stream = fopen($options['output'] . '/' . $name . '.csv', 'rb'); $columns = fgetcsv($stream, 0, ',', '"', ''); $result[$name] = [];
		while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) $result[$name][] = array_combine($columns, $row);
		fclose($stream);
	}
	return $result;
}
function completeFiles(string $root): array
{
	$result = [];
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $entry) {
		$path = $entry->getPathname();
		$result[$path] = [$entry->isLink() ? readlink($path) : ($entry->isFile() && is_readable($path) ? hash_file('sha256', $path) : ''), $entry->getMTime(), $entry->getPerms()];
	}
	ksort($result); return $result;
}

foreach (['root' => ['root', ''], 'reader' => ['audit_reader', 'fixture-only']] as $name => [$user, $password]) file_put_contents($work . '/' . $name . '.ini', "[production]\nresources.db.params.host='localhost'\nresources.db.params.username='$user'\nresources.db.params.password='$password'\nresources.db.params.dbname='completeness_fixture'\n");
completeSeed('client', ['id' => 100, 'activated' => 1]); completeSeed('client', ['id' => 200, 'activated' => 1]);
completeSeed('user', ['id' => 42, 'clientid' => 100, 'activated' => 1, 'admin' => 0]);
completeSeed('user', ['id' => 43, 'clientid' => 200, 'activated' => 1]);
completeSeed('user', ['id' => 44, 'clientid' => 100, 'activated' => 1]);
$permissions = ['userid' => 42, 'clientid' => 100];
foreach (DEEC_DocumentArchive::documents() as $module => $controllers) $permissions[$module] = json_encode(array_fill_keys($controllers, ['view']));
completeSeed('permission', $permissions);
$db = recoveryBoot(migrationConfig($work . '/reader.ini', 'production') + ['unix_socket' => $socket], $base, 100, 42);
$archive = new DEEC_DocumentArchive($base);
completeCheck($archive->relativePath(100, 'sales', 'invoice', 59505) === 'files/documents/1/0/100/sales/invoice/59/59505.pdf', 'Internal ID shard differs');
foreach (completenessTypes(null) as [$module, $type]) {
	completeDocument($type, 59505, [$type . 'id' => 112143]);
	completePdf($archive->path(100, $module, $type, 59505), $type); completeRegistration($module, $type, 59505);
	completeDocument($type, 1, [$type . 'id' => null, 'completed' => 0, 'state' => 100]);
	completeDocument($type, 2, ['deleted' => 1]);
	completeDocument($type, 3, ['clientid' => 200]);
}
$clean = completeCli([], 0);
completeCheck(count($clean['documents']) === 16 && count($clean['summary']['types']) === 8, 'Eight-type selection or drafts differ');
foreach ($clean['summary']['types'] as $counts) completeCheck($counts['non_deleted'] === 2 && $counts['issued'] === 1 && $counts['drafts'] === 1 && $counts['groups']['original_complete'] === 1 && $counts['status_sum_matches_issued'], 'Clean totals differ');

foreach (completenessTypes(null) as [$module, $type]) { completeDocument($type, 4); completeCopy($module, $type, 4); }
for ($id = 10; $id <= 33; $id++) completeDocument('quote', $id);
// Missing, unregistered, incorrect integrity, cancelled, state-only and completed-only documents.
completePdf($archive->path(100, 'sales', 'quote', 11));
foreach ([12 => ['sha256' => str_repeat('0', 64)], 13 => ['filesize' => 1], 14 => ['clientid' => 200], 15 => ['version' => 2], 16 => ['format' => 'wrong'], 17 => ['filename' => 'wrong.pdf'], 18 => ['path' => 'files/documents/wrong.pdf'], 19 => [], 31 => ['module' => 'purchases', 'controller' => 'purchaseorder'], 32 => ['documentid' => 123456], 33 => []] as $id => $changes) {
	completePdf($archive->path(100, 'sales', 'quote', $id)); completeRegistration('sales', 'quote', $id, $changes);
}
unlink($archive->path(100, 'sales', 'quote', 33));
completeRegistration('sales', 'quote', 19, ['version' => 2, 'path' => 'files/documents/second.pdf']);
completePdf($archive->path(100, 'sales', 'quote', 20)); completeRegistration('sales', 'quote', 20); file_put_contents($archive->path(100, 'sales', 'quote', 20), 'broken');
completePdf($archive->path(100, 'sales', 'quote', 21)); chmod($archive->path(100, 'sales', 'quote', 21), 0000);
$pdo->exec('UPDATE quote SET cancelled = 1, completed = 0, quoteid = NULL, state = 100 WHERE id = 22');
$pdo->exec('UPDATE quote SET completed = 0, quoteid = NULL, state = 105 WHERE id = 23');
$pdo->exec('UPDATE quote SET quoteid = NULL, state = 100 WHERE id = 24');
completePdf($archive->path(100, 'sales', 'quote', 25)); file_put_contents($archive->path(100, 'sales', 'quote', 25), '');
symlink($archive->path(100, 'sales', 'quote', 11), $archive->path(100, 'sales', 'quote', 26));
foreach ([1, 2, 3, 99999] as $id) completePdf($archive->path(100, 'sales', 'quote', $id));
completePdf(dirname($archive->path(100, 'sales', 'quote', 59505)) . '/99998.pdf');

// Synthetic K4 receipts use exactly the snapshot and protected layout used by the viewing service.
$copyPaths = [];
foreach ([10, 27, 28, 29, 30] as $id) $copyPaths[$id] = completeCopy('sales', 'quote', $id, 'de_DE', $id === 29 ? ['identity' => ['clientid' => 200, 'module' => 'sales', 'controller' => 'quote', 'id' => $id]] : []);
unlink($copyPaths[27]); file_put_contents($copyPaths[28], 'broken'); chmod($copyPaths[30], 0000);
$beforeFiles = completeFiles($base);
$beforeDb = [];
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) $beforeDb[$table] = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
$result = completeCli();
$byId = [];
foreach ($result['documents'] as $row) if ($row['controller'] === 'quote') $byId[(int)$row['id']] = $row;
foreach ([10 => 'missing_original_valid_copy', 11 => 'original_without_registration', 12 => 'invalid_archive_metadata', 13 => 'invalid_archive_metadata', 14 => 'invalid_archive_metadata', 15 => 'invalid_archive_metadata', 16 => 'invalid_archive_metadata', 17 => 'invalid_archive_metadata', 18 => 'invalid_archive_metadata', 19 => 'invalid_archive_metadata', 20 => 'damaged_or_unreadable_pdf', 21 => 'damaged_or_unreadable_pdf', 22 => 'missing_original_copy_not_generated', 23 => 'missing_original_copy_not_generated', 24 => 'missing_original_copy_not_generated', 25 => 'damaged_or_unreadable_pdf', 26 => 'damaged_or_unreadable_pdf', 27 => 'damaged_or_unreadable_pdf', 28 => 'damaged_or_unreadable_pdf', 29 => 'damaged_or_unreadable_pdf', 30 => 'damaged_or_unreadable_pdf', 31 => 'invalid_archive_metadata', 32 => 'invalid_archive_metadata', 33 => 'missing_original_copy_not_generated'] as $id => $group) completeCheck($byId[$id]['group'] === $group, 'Wrong classification for ' . $id . ': ' . json_encode($byId[$id]));
completeCheck($byId[21]['original_status'] === 'unreadable' && $byId[27]['k4_copy_status'] === 'missing' && $byId[28]['k4_copy_status'] === 'invalid_pdf' && $byId[30]['k4_copy_status'] === 'unreadable', 'Missing, damaged and unreadable copies conflated');
completeCheck($byId[22]['issuance_status'] === 'issued_cancelled', 'Cancelled issued document omitted');
completeCheck(!isset($byId[2]) && !isset($byId[3]), 'Deleted or foreign documents included');
$reasons = [];
foreach ($result['problems'] as $row) $reasons = array_merge($reasons, json_decode($row['reasons'], true));
foreach (['sha256_mismatch', 'filesize_mismatch', 'clientid_mismatch', 'module_mismatch', 'controller_mismatch', 'documentid_mismatch', 'format_mismatch', 'filename_mismatch', 'path_mismatch', 'registrations_ambiguous', 'file_for_draft', 'no_active_tenant_document', 'noncanonical_pdf_path'] as $reason) completeCheck(in_array($reason, $reasons, true), 'Missing issue ' . $reason);
completeCheck($result['summary']['filesystem']['orphan_pdfs'] === 5 && $result['summary']['complete'], 'Orphan scan differs');
foreach ($result['summary']['types'] as $counts) completeCheck($counts['issued'] === array_sum($counts['groups']) && $counts['non_deleted'] === $counts['issued'] + $counts['drafts'], 'Counts do not reconcile');
completeCheck($result['summary']['totals']['groups']['missing_original_valid_copy'] === 9, 'K4 copies for all eight types were not counted separately');
completeCheck(completeFiles($base) === $beforeFiles, 'Audit mutated archive files, directories or copies');
foreach ($beforeDb as $table => $rows) completeCheck($pdo->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) === $rows, 'Audit mutated ' . $table);
$filtered = completeCli(['type' => 'sales/invoice']);
completeCheck(count($filtered['summary']['types']) === 1 && count($filtered['documents']) === 3 && $filtered['summary']['filesystem']['pdfs'] === 1, 'Type filter escaped its scope');
completeCli(['operator-user' => 43], 2); completeCli(['operator-user' => 44], 2);
completeCli(['client' => 200], 2); completeCli(['web-user' => 'root'], 2);
completeCli(['type' => 'sales/user'], 2); completeCli(['batch-size' => 0], 2);
completeCli(['config' => $work . '/root.ini'], 2);
completeCli(['output' => $result['output']], 2);
mkdir($base . '/reports', 0700); completeCli(['output' => $base . '/reports/private'], 2);
symlink($work . '/reports', $work . '/linked'); completeCli(['output' => $work . '/linked/private'], 2);
// A filesystem blocker keeps the report explicitly incomplete.
$shard = dirname($archive->path(100, 'sales', 'quote', 59505)); chmod($shard, 0000);
$incomplete = completeCli([], 2); chmod($shard, 0700);
completeCheck(!$incomplete['summary']['complete'] && $incomplete['summary']['filesystem']['blockers'] > 0, 'Inaccessible directory reported complete');
// Receipt/snapshot changes and orphaned copies never become originals.
$savedNotes = $pdo->query('SELECT notes FROM quote WHERE id = 10')->fetchColumn();
$pdo->exec("UPDATE quote SET notes = 'changed snapshot' WHERE id = 10");
$stale = (new DEEC_DocumentReview())->inspectViewingCopies(10, 'sales', 'quote');
completeCheck($stale['status'] === 'stale_snapshot', 'Stale copy accepted for current snapshot');
$pdo->prepare('UPDATE quote SET notes = ? WHERE id = 10')->execute([$savedNotes]);
$receiptPath = dirname($copyPaths[10]) . '/copy.json'; $savedReceipt = file_get_contents($receiptPath); unlink($receiptPath);
completeCheck((new DEEC_DocumentReview())->inspectViewingCopies(10, 'sales', 'quote')['status'] === 'invalid', 'PDF without receipt reported as not generated');
file_put_contents($receiptPath, $savedReceipt);
// Receipt integrity, privacy, locale and snapshot checks use the common viewing validator.
$copyRecord = json_decode($savedReceipt, true);
foreach ([['snapshot_sha256' => str_repeat('f', 64)], ['size' => 1], ['original' => true], ['archive_registration_allowed' => true], ['locale' => 'en_US'], ['sha256' => []]] as $changes) {
	file_put_contents($receiptPath, DEEC_DocumentReview::json(array_replace($copyRecord, $changes)));
	completeCheck((new DEEC_DocumentReview())->inspectViewingCopies(10, 'sales', 'quote')['status'] === 'invalid', 'Invalid K4 receipt accepted');
}
file_put_contents($receiptPath, $savedReceipt); chmod($receiptPath, 0000);
completeCheck((new DEEC_DocumentReview())->inspectViewingCopies(10, 'sales', 'quote')['status'] === 'unreadable', 'Unreadable K4 receipt accepted'); chmod($receiptPath, 0600);
unlink($receiptPath); symlink(dirname($copyPaths[10]) . '/missing.json', $receiptPath);
completeCheck((new DEEC_DocumentReview())->inspectViewingCopies(10, 'sales', 'quote')['status'] === 'unsafe', 'Symlink K4 receipt accepted'); unlink($receiptPath); file_put_contents($receiptPath, $savedReceipt);
$stream = fopen('php://temp', 'w+b'); completenessCsv($stream, ['document_number' => '=FORMULA()', 'reasons' => ['quoted,"text']]); rewind($stream); $cells = fgetcsv($stream, 0, ',', '"', ''); fclose($stream);
completeCheck($cells[5] === "'=FORMULA()" && json_decode($cells[14], true) === ['quoted,"text'], 'CSV quoting or formula escaping failed');
// Larger than the CLI memory limit: the copy and original must be inspected without reading all bytes.
$large = $work . '/large.pdf'; $stream = fopen($large, 'xb'); fwrite($stream, "%PDF-1.4\n"); fseek($stream, 64 * 1024 * 1024); fwrite($stream, "\n%%EOF\n"); fclose($stream);
$largeSha = hash_file('sha256', $large); $largeSize = filesize($large);
$largeCopy = dirname($copyPaths[10]) . '/' . $largeSha . '.pdf'; copy($large, $largeCopy);
file_put_contents($receiptPath, DEEC_DocumentReview::json(array_replace($copyRecord, ['sha256' => $largeSha, 'size' => $largeSize])));
copy($large, $archive->path(100, 'sales', 'invoice', 59505));
$pdo->prepare("UPDATE documentarchive SET sha256 = ?, filesize = ? WHERE module = 'sales' AND controller = 'invoice' AND documentid = 59505")->execute([$largeSha, $largeSize]);
// Large tenant list: batches and reports must not accumulate document rows in memory.
$pdo->beginTransaction();
for ($id = 100000; $id < 112000; $id++) completeDocument('quote', $id, ['quoteid' => null, 'completed' => 0, 'state' => 100]);
$pdo->commit();
$largeResult = completeCli(['batch-size' => 1000]);
completeCheck($largeResult['summary']['totals']['drafts'] === 12008 && $largeResult['summary']['peak_memory_bytes'] < 48 * 1024 * 1024, 'Large audit exceeded bounded CLI memory or omitted documents');
$largeRows = array_values(array_filter($largeResult['documents'], static fn(array $row): bool => $row['controller'] === 'quote' && $row['id'] === '10'));
completeCheck(count($largeRows) === 1 && $largeRows[0]['k4_copy_status'] === 'available', 'Large K4 PDF was not inspected with bounded memory');
// Read-only transactions and SELECT-only grants both reject writes in this disposable fixture.
completenessRequireReader($db); $db->query('SET TRANSACTION READ ONLY'); $db->beginTransaction();
try { $db->query('UPDATE quote SET notes = notes WHERE id = 10'); throw new LogicException('Read-only write accepted'); }
catch (Zend_Db_Exception $expected) { completeCheck(true, 'Read-only write blocked'); }
$db->rollBack(); $db->closeConnection();
echo 'PASS: ' . $checks . " completeness checks; eight types, read-only CLI, K4 receipts/snapshots, integrity, tenant isolation, permissions, orphans and unchanged database/files\n";
