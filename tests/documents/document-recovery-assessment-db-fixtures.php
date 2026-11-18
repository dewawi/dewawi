<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Runs only inside the disposable full-schema K2 database regression.
require_once $root . '/library/Tcpdf/tcpdf.php';
function assessmentDbPdf(string $label): string
{
	$pdf = new TCPDF(); $pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->AddPage(); $pdf->SetFont('helvetica', '', 10); $pdf->Write(5, "Quote\nQuote ID: 9210\nQuote date: 2026-01-02\nRecipient: Synthetic recipient\nTotal: " . $label);
	return $pdf->Output('', 'S');
}
function assessmentDbWrite(string $path, string $bytes): void
{
	if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
	file_put_contents($path, $bytes);
}
function assessmentDbCli(array $changes = [], int $expected = 0): array
{
	global $root, $socket, $work, $base;
	static $sequence = 0;
	$options = array_replace(['inventory' => $work . '/reports/assessment-inventory.jsonl', 'base' => $base, 'client' => 7, 'config' => $work . '/reader.ini', 'operator-user' => 42, 'output' => $work . '/reports/assessment-' . (++$sequence), 'recent-since' => '2025-01-01'], $changes);
	$command = [PHP_BINARY, '-d', 'error_reporting=8191', '-d', 'pdo_mysql.default_socket=' . $socket, '-d', 'mysqli.default_socket=' . $socket, $root . '/tools/document-recovery-assessment.php'];
	foreach ($options as $key => $value) $command[] = '--' . $key . '=' . $value;
	$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
	integrityCheck(proc_close($process) === $expected, 'Unexpected assessment CLI exit: ' . $out . $error);
	if ($expected === 2) { integrityCheck(!file_exists($options['output']), 'Unauthorized assessment created output'); return []; }
	integrityCheck($error === '', 'Assessment CLI emitted warnings: ' . $error);
	$records = [];
	foreach (file($options['output'] . '/documents.jsonl', FILE_IGNORE_NEW_LINES) as $line) { $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR); $records[$row['document']['controller'] . '/' . $row['document']['id']] = $row; }
	return ['summary' => json_decode($out, true, 512, JSON_THROW_ON_ERROR), 'records' => $records];
}
function assessmentDbSnapshot(): array
{
	global $pdo, $base;
	$result = ['tables' => [], 'files' => []];
	foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) $result['tables'][$table] = hash('sha256', json_encode($pdo->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC)));
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base . '/files', FilesystemIterator::SKIP_DOTS)) as $file) if ($file->isFile()) $result['files'][$file->getPathname()] = [hash_file('sha256', $file->getPathname()), $file->getMTime()];
	return $result;
}

$assessmentStart = $checks;
$pdfA = assessmentDbPdf('119.00'); $pdfB = assessmentDbPdf('120.00');
foreach ([210, 211, 212, 213, 214, 215, 216] as $id) integrityFinalized($id);
$paths = $archive->paths(['module' => 'sales', 'controller' => 'quote', 'id' => 210, 'clientid' => 7, 'contactid' => 12, 'contact_record_id' => 1, 'filename' => 'quote-210.pdf']);
foreach (['attachment', 'current', 'legacy'] as $name) assessmentDbWrite($paths[$name], $pdfA);
assessmentDbWrite($paths['current'], $pdfB);
$attachmentDirectory = dirname($paths['attachment']); assessmentDbWrite($attachmentDirectory . '/old-210.pdf', $pdfB);
integritySeed('emailattachment', ['id' => 501, 'clientid' => 7, 'documentid' => 210, 'module' => 'sales', 'controller' => 'quote', 'filename' => 'quote-210.pdf', 'location' => $attachmentDirectory, 'filesize' => strlen($pdfA)]);
integritySeed('emailattachment', ['id' => 502, 'clientid' => 7, 'documentid' => 210, 'module' => 'sales', 'controller' => 'quote', 'filename' => 'old-210.pdf', 'location' => $attachmentDirectory, 'filesize' => strlen($pdfB), 'deleted' => 1]);
$foreignPath = DEEC_Emailattachment::getDirectory('sales', 'quote', 210, 8) . '/foreign.pdf'; assessmentDbWrite($foreignPath, $pdfB);
integritySeed('emailattachment', ['id' => 503, 'clientid' => 7, 'documentid' => 210, 'module' => 'sales', 'controller' => 'quote', 'filename' => 'foreign.pdf', 'location' => dirname($foreignPath), 'filesize' => strlen($pdfB)]);
integritySeed('contact', ['id' => 501, 'clientid' => 7, 'contactid' => 99]); integritySeed('contact', ['id' => 502, 'clientid' => 7, 'contactid' => 99]);
$pdo->exec('UPDATE quote SET contactid = 99 WHERE id = 211');
$pdo->exec("UPDATE quote SET filename = '' WHERE id = 212");
$pdo->exec('UPDATE quote SET clientid = 8 WHERE id = 215');
$pdo->exec("UPDATE quote SET filename = 'quote-210.pdf' WHERE id = 216");
foreach ([901 => [210, 501, $pdfA, null], 902 => [210, 502, $pdfB, null], 903 => [214, null, $pdfA, null], 904 => [210, 501, $pdfA, str_repeat('0', 64)], 905 => [210, 501, $pdfB, null]] as $messageId => [$documentId, $attachmentId, $bytes, $hash]) {
	integritySeed('emailmessage', ['id' => $messageId, 'clientid' => $messageId === 905 ? 8 : 7, 'documentid' => $documentId, 'module' => 'sales', 'controller' => 'quote', 'deliverystatus' => 'accepted', 'messagesent' => '2026-01-02 00:00:00']);
	$relative = 'files/attachments/emailmessage/7/0/7/0/' . $messageId . '/1.pdf'; assessmentDbWrite($base . '/' . $relative, $bytes);
	integritySeed('emailmessageattachment', ['id' => $messageId, 'emailmessageid' => $messageId, 'emailattachmentid' => $attachmentId, 'clientid' => 7, 'filename' => $attachmentId === 502 ? 'old-210.pdf' : 'quote-210.pdf', 'path' => $relative, 'filesize' => strlen($bytes), 'sha256' => $hash ?? hash('sha256', $bytes), 'ordering' => 1]);
}
// The real migration dry run defines the input schema. Its output stays private.
$inventory = $work . '/reports/assessment-inventory.jsonl';
$process = proc_open([PHP_BINARY, '-d', 'mysqli.default_socket=' . $socket, $root . '/tests/documents/document-archive-migration.php', '--config=' . $work . '/reader.ini', '--base=' . $base, '--client=7'], [1 => ['file', $inventory, 'x'], 2 => ['pipe', 'w']], $pipes);
$error = stream_get_contents($pipes[2]); fclose($pipes[2]);
integrityCheck(proc_close($process) === 1 && $error === '', 'Migration dry-run fixture failed: ' . $error);
$beforeAssessment = assessmentDbSnapshot();
$assessment = assessmentDbCli(); $records = $assessment['records'];
$inventoryBlocked = 0;
foreach (file($inventory, FILE_IGNORE_NEW_LINES) as $line) { $row = json_decode($line, true); if (($row['kind'] ?? '') === 'document' && ($row['plan']['status'] ?? '') === 'blocked') $inventoryBlocked++; }
integrityCheck($assessment['summary']['documents'] === $inventoryBlocked && $inventoryBlocked > 10, 'Did not assess every blocked migration record');
integrityCheck($records['quote/210']['classification'] === 'conflicting_versions_needing_review' && count($records['quote/210']['versions']) === 2, 'Database attachment versions not compared');
integrityCheck($records['quote/211']['classification'] === 'contact_mapping_ambiguous', 'Database contact mapping ambiguity omitted');
integrityCheck($records['quote/212']['classification'] === 'invalid_or_unreadable_candidate', 'Invalid document context not preserved');
integrityCheck($records['quote/213']['classification'] === 'no_candidate_found', 'Missing file outcome incorrect');
integrityCheck($records['quote/214']['classification'] === 'possible_additional_candidate', 'Unreferenced mail copy was treated as known origin');
$mailReferences = [];
foreach ($records['quote/210']['candidates'] as $candidate) {
	if ($candidate['original_path'] === $foreignPath) integrityCheck($candidate['observed'] === null, 'Metadata redirected reads to another tenant');
	if (in_array($candidate['original_path'], [$paths['current'], $paths['legacy']], true)) integrityCheck($candidate['observed'] === null, 'Duplicate contact filename mapping bypassed DB evidence');
	foreach ($candidate['references'] as $reference) if ($reference['source'] === 'emailmessageattachment') { $mailReferences[] = (int)$reference['evidence']['emailmessageid']; if ((int)$reference['evidence']['emailmessageid'] === 904) integrityCheck(in_array('recorded_sha256_differs', $candidate['issues'], true), 'Stored email hash mismatch omitted'); }
}
sort($mailReferences); integrityCheck($mailReferences === [901, 902, 904], 'Cross-tenant message joined or valid message references lost');
integrityCheck(!$records['quote/210']['original_confirmed'] && !$records['quote/210']['approval_allowed'], 'Transport status or matching hashes approved an original');
assessmentDbCli(['operator-user' => 43], 2);
integrityCheck(assessmentDbSnapshot() === $beforeAssessment, 'Assessment changed database rows, PDFs, archives or delivery state');

// View-only permissions suffice, while denied document types fail before output.
$savedPermissions = $pdo->query('SELECT sales FROM permission WHERE userid = 42')->fetchColumn();
$pdo->exec("UPDATE permission SET sales = '{}' WHERE userid = 42");
assessmentDbCli([], 2);
$pdo->exec("UPDATE permission SET sales = '{\"quote\":[\"view\"]}' WHERE userid = 42");
$quoteRows = [];
foreach (file($inventory, FILE_IGNORE_NEW_LINES) as $line) { $row = json_decode($line, true); if (($row['kind'] ?? '') === 'document' && $row['plan']['module'] === 'sales' && $row['plan']['controller'] === 'quote') $quoteRows[] = $row; }
$counts = ['blocked' => 0, 'complete' => 0, 'ready' => 0]; foreach ($quoteRows as $row) $counts[$row['plan']['status']]++;
$quoteRows[] = ['kind' => 'document', 'plan' => ['module' => 'sales', 'controller' => 'quote', 'id' => 215, 'clientid' => 7, 'document_number' => '9215', 'filename' => 'quote-215.pdf', 'format' => 'pdf', 'status' => 'blocked', 'reason' => 'original_missing', 'files' => ['attachment' => ['path' => $foreignPath]]]]; $counts['blocked']++;
$quoteRows[] = ['kind' => 'summary', 'summary' => ['mode' => 'dry-run', 'clientid' => 7, 'contacts_clientid' => 7, 'unassigned_scan' => 'complete', 'documents' => count($quoteRows)] + $counts];
file_put_contents($work . '/reports/quotes.jsonl', implode("\n", array_map('json_encode', $quoteRows)) . "\n");
$quoteAssessment = assessmentDbCli(['inventory' => $work . '/reports/quotes.jsonl']);
integrityCheck($quoteAssessment['records']['quote/215']['versions'] === [], 'Foreign document fell back to filesystem inventory association');
$pdo->prepare('UPDATE permission SET sales = ? WHERE userid = 42')->execute([$savedPermissions]);

integritySeed('client', ['id' => 9, 'parentid' => 7, 'activated' => 1]); integritySeed('user', ['id' => 44, 'clientid' => 9, 'activated' => 1]);
integritySeed('permission', ['userid' => 44, 'clientid' => 9, 'sales' => '{"quote":["view"]}']);
integrityFinalized(217); $pdo->exec('UPDATE quote SET clientid = 9 WHERE id = 217');
$child = ['module' => 'sales', 'controller' => 'quote', 'id' => 217, 'clientid' => 9, 'document_number' => '9217', 'filename' => 'quote-217.pdf', 'format' => 'pdf', 'status' => 'blocked', 'reason' => 'original_missing', 'files' => []];
assessmentDbWrite(DEEC_Emailattachment::getDirectory('sales', 'quote', 217, 9) . '/quote-217.pdf', $pdfA);
file_put_contents($work . '/reports/child.jsonl', json_encode(['kind' => 'document', 'plan' => $child]) . "\n" . json_encode(['kind' => 'summary', 'summary' => ['mode' => 'dry-run', 'clientid' => 9, 'contacts_clientid' => 7, 'documents' => 1, 'blocked' => 1, 'unassigned_scan' => 'complete']]) . "\n");
$beforeChild = assessmentDbSnapshot();
$childAssessment = assessmentDbCli(['inventory' => $work . '/reports/child.jsonl', 'client' => 9, 'operator-user' => 44]);
integrityCheck($childAssessment['records']['quote/217']['database_correlated'] && count($childAssessment['records']['quote/217']['versions']) === 1, 'Authorized child with parent contact mapping failed');
integrityCheck(assessmentDbSnapshot() === $beforeChild, 'Child assessment modified stored data');
echo 'PASS: ' . ($checks - $assessmentStart) . " isolated recovery assessment database checks; SELECT-only account, real migration inventory, mail versions, permissions and parent contacts\n";
