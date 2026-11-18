<?php

// Included by the isolated K2 database regression only.
function integrityFinalized(int $id, string $completed = '2026-01-02 00:00:00'): void
{
	global $pdo;
	integrityDraft('quote', $id);
	$pdo->prepare('UPDATE quote SET quoteid = ?, completed = 1, state = 105, completeddate = ?, created = ?, filename = ? WHERE id = ?')->execute([9000 + $id, $completed, $completed, 'quote-' . $id . '.pdf', $id]);
}
function integrityRegistration(int $id, array $changes = []): array
{
	global $pdo, $archive;
	$path = $archive->path(7, 'sales', 'quote', $id);
	if (!is_file($path)) $archive->store("%PDF-1.4\nIntegrity fixture $id\n%%EOF\n", 7, 'sales', 'quote', $id, 'pdf');
	$row = array_replace(['clientid' => 7, 'module' => 'sales', 'controller' => 'quote', 'documentid' => $id, 'format' => 'pdf', 'version' => 1, 'filename' => 'quote-' . $id . '.pdf', 'path' => $archive->relativePath(7, 'sales', 'quote', $id), 'filesize' => filesize($path), 'sha256' => hash_file('sha256', $path), 'archived' => '2026-01-02 00:00:00'], $changes);
	integritySeed('documentarchive', $row); $row['id'] = (int)$pdo->lastInsertId();
	return $row;
}
function integrityCli(array $changes = [], int $expected = 1): array
{
	global $root, $socket, $work;
	static $sequence = 0;
	$options = array_replace(['config' => $work . '/reader.ini', 'base' => $work . '/source', 'client' => 7, 'operator-user' => 42, 'report' => $work . '/reports/check-' . (++$sequence) . '.jsonl', 'required-since' => '2020-01-01 00:00:00'], $changes);
	$command = [PHP_BINARY, '-d', 'error_reporting=8191', '-d', 'pdo_mysql.default_socket=' . $socket, $root . '/tools/document-archive-integrity.php'];
	foreach ($options as $key => $value) $command[] = '--' . $key . '=' . $value;
	$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
	integrityCheck(proc_close($process) === $expected, 'Unexpected integrity CLI exit: ' . $out . $error);
	if ($expected === 2) return ['output' => $out, 'error' => $error];
	$summary = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
	integrityCheck((fileperms($options['report']) & 0777) === 0600, 'Integrity report is not private');
	$records = array_map(static fn(string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR), array_filter(explode("\n", file_get_contents($options['report']))));
	return ['summary' => $summary, 'records' => $records, 'report' => $options['report']];
}

$pdo->exec("CREATE USER 'integrity_reader'@'localhost' IDENTIFIED BY 'fixture-only'");
$pdo->exec("GRANT SELECT ON integrity_fixture.* TO 'integrity_reader'@'localhost'");
$clean = integrityCli(['type' => 'purchases/purchaseorder'], 0);
integrityCheck($clean['summary']['complete'] && $clean['summary']['failed'] === 0 && $clean['summary']['documents'] === 1, 'Clean original was reported as damaged');
integrityFinalized(101); integrityFinalized(102, '2010-01-02 00:00:00');
for ($id = 103; $id <= 110; $id++) integrityFinalized($id);
$missing = integrityRegistration(103); unlink($archive->path(7, 'sales', 'quote', 103));
$invalid = integrityRegistration(104); file_put_contents($archive->path(7, 'sales', 'quote', 104), '%PDF-truncated');
integrityRegistration(105, ['path' => 'files/documents/wrong-canonical.pdf']);
integrityRegistration(106, ['sha256' => str_repeat('0', 64)]);
integrityRegistration(107, ['filesize' => 1]);
integrityRegistration(108, ['version' => 2]);
$ambiguous = integrityRegistration(109); integrityRegistration(109, ['version' => 2, 'path' => 'files/documents/version-two.pdf']);
integrityRegistration(110, ['clientid' => 8, 'documentid' => 9999]);
integritySeed('documentarchive', ['clientid' => 7, 'module' => 'sales', 'controller' => 'quote', 'documentid' => 9999, 'format' => 'pdf', 'version' => 1, 'filename' => 'orphan.pdf', 'path' => 'files/documents/orphan.pdf', 'filesize' => 1, 'sha256' => str_repeat('1', 64), 'archived' => '2026-01-02 00:00:00']);
integritySeed('documentarchive', ['clientid' => 7, 'module' => 'bad', 'controller' => 'user', 'documentid' => 1, 'format' => 'pdf', 'version' => 1, 'filename' => 'invalid.pdf', 'path' => 'files/documents/context.pdf', 'filesize' => 1, 'sha256' => str_repeat('1', 64), 'archived' => '2026-01-02 00:00:00']);
integrityDraft('quote', 115); integrityRegistration(115);
integrityDraft('quote', 116); $pdo->exec('UPDATE quote SET clientid = 8 WHERE id = 116'); integrityRegistration(116);

$before = [];
foreach (['quote', 'quotepos', 'invoice', 'invoicepos', 'documentarchive', 'increment', 'permission'] as $table) $before[$table] = $pdo->query('SELECT * FROM `' . $table . '` ORDER BY ' . ($table === 'increment' ? 'clientid' : 'id'))->fetchAll(PDO::FETCH_ASSOC);
$fileBefore = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base . '/files', FilesystemIterator::SKIP_DOTS)) as $file) if ($file->isFile()) $fileBefore[$file->getPathname()] = [hash_file('sha256', $file->getPathname()), $file->getMTime()];
$report = integrityCli();
foreach (['registration_missing', 'file_missing', 'file_invalid_pdf', 'path_mismatch', 'sha256_mismatch', 'filesize_mismatch', 'version_mismatch', 'registrations_ambiguous', 'clientid_mismatch', 'documentid_mismatch', 'registered_document_missing', 'registration_context_invalid', 'registration_for_draft', 'registered_document_tenant_mismatch'] as $issue) integrityCheck(isset($report['summary']['issues'][$issue]), 'Integrity CLI omitted ' . $issue);
foreach ([101 => 'current_expectation', 102 => 'historical'] as $id => $expectation) {
	$matching = array_values(array_filter($report['records'], static fn(array $record): bool => ($record['kind'] ?? '') === 'document' && ($record['document']['controller'] ?? '') === 'quote' && ($record['document']['id'] ?? 0) === $id));
	integrityCheck(count($matching) === 1 && $matching[0]['expectation'] === $expectation && in_array('registration_missing', $matching[0]['issues'], true), 'Current and historical missing originals were conflated');
}
integrityCheck($report['summary']['complete'] && $report['summary']['clientid'] === 7, 'Full tenant scan was incomplete');
$bounded = integrityCli(['type' => 'purchases/purchaseorder', 'limit' => 1], 0);
integrityCheck($bounded['summary']['documents'] === 1, 'Clean bounded scan changed scope');
$bounded = integrityCli(['type' => 'sales/quote', 'limit' => 1], 3);
integrityCheck(!$bounded['summary']['complete'] && $bounded['summary']['documents'] === 1 && $bounded['summary']['next_document_ids']['sales/quote'] === 1, 'Bounded scan did not report its continuation cursor');
$continued = integrityCli(['type' => 'sales/quote', 'after-id' => 101, 'after-archive-id' => 0]);
integrityCheck(!array_filter($continued['records'], static fn(array $record): bool => ($record['kind'] ?? '') === 'document' && $record['document']['id'] <= 101), 'Document cursor was ignored');
integrityCli(['report' => $report['report']], 2);
integrityCli(['operator-user' => 43], 2);
integrityCli(['required-since' => 'invalid'], 2);
mkdir($base . '/private-report', 0700); integrityCli(['report' => $base . '/private-report/report.jsonl'], 2);
symlink($report['report'], $work . '/reports/linked.jsonl'); integrityCli(['report' => $work . '/reports/linked.jsonl'], 2);
foreach ($before as $table => $rows) integrityCheck($pdo->query('SELECT * FROM `' . $table . '` ORDER BY ' . ($table === 'increment' ? 'clientid' : 'id'))->fetchAll(PDO::FETCH_ASSOC) === $rows, 'Read-only CLI changed ' . $table);
foreach ($fileBefore as $path => $state) integrityCheck([hash_file('sha256', $path), filemtime($path)] === $state, 'Read-only CLI changed a stored file');

$db = recoveryBoot(migrationConfig($work . '/root.ini', 'production') + ['unix_socket' => $socket], $base, 7, 42);
try { (new Application_Model_DbTable_Documentarchive())->getForDocument(109, 'sales', 'quote'); throw new LogicException('Ambiguous versions were served'); }
catch (RuntimeException $error) { integrityCheck(str_contains($error->getMessage(), 'ambiguous'), 'Unexpected ambiguous original error'); }
try { (new Application_Model_DbTable_Documentarchive())->getArchive($ambiguous['id']); throw new LogicException('Ambiguous original could enter email attachments'); }
catch (RuntimeException $error) { integrityCheck(str_contains($error->getMessage(), 'ambiguous'), 'Email archive selection lost the ambiguity guard'); }
$user = Zend_Registry::get('User'); Zend_Registry::set('User', array_replace($user, ['clientid' => 8])); Zend_Registry::set('Client', ['id' => 8]);
integrityCheck((new Application_Model_DbTable_Documentarchive())->getArchive($ambiguous['id']) === null, 'Foreign tenant could select an archive attachment');
$db->closeConnection();
