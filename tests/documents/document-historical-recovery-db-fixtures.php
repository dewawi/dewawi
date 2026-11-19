<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$historicalStart = $checks;
require_once $root . '/tools/document-historical-recovery.php';
$db = recoveryBoot(migrationConfig($work . '/root.ini', 'production') + ['unix_socket' => $socket], $base, 7, 42);
function historicalTestCli(array $changes = [], bool $success = true, bool $defer = false): array
{
	global $root, $socket, $work, $base;
	static $sequence = 0;
	$options = array_replace(['config' => $work . '/reader.ini', 'base' => $base, 'client' => '7', 'operator-user' => '42', 'inventory' => $work . '/reports/historical-inventory.jsonl', 'output' => $work . '/reports/historical-' . (++$sequence), 'action' => 'plan'], $changes);
	$command = [PHP_BINARY, '-d', 'error_reporting=8191', '-d', 'pdo_mysql.default_socket=' . $socket, $root . '/tools/document-historical-recovery.php'];
	foreach ($options as $key => $value) if ($value !== null) $command[] = '--' . $key . ($value === false ? '' : '=' . $value);
	$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	if ($defer) return [$process, $pipes];
	return historicalTestFinish([$process, $pipes], $success);
}
function historicalTestFinish(array $worker, bool $success = true): array
{
	[$process, $pipes] = $worker;
	$out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
	$code = proc_close($process);
	integrityCheck($success ? ($code === 0 && $error === '') : $code !== 0, 'Historical CLI outcome differs: ' . $out . $error);
	return $success ? json_decode($out, true, 512, JSON_THROW_ON_ERROR) : ['error' => $error];
}
function historicalTestPdf(array $identity, string $version): string
{
	$types = ['invoice' => 'Invoice', 'quote' => 'Quote', 'salesorder' => 'Sales order', 'deliveryorder' => 'Delivery note', 'creditnote' => 'Credit note', 'reminder' => 'Reminder', 'quoterequest' => 'Request for quotation', 'purchaseorder' => 'Purchase order'];
	$pdf = new TCPDF(); $pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->AddPage(); $pdf->SetFont('helvetica', '', 10);
	$pdf->Write(5, $types[$identity['controller']] . "\nDocument ID: " . $identity['document_number'] . "\nDocument date: 2026-01-02\nSynthetic version " . $version . ($version === 'copy' ? "\nKOPIE\n" : ''));
	return $pdf->Output('', 'S');
}
function historicalTestInventory(array $identities, string $path): void
{
	$lines = [];
	foreach ($identities as $id) $lines[] = json_encode(['kind' => 'document', 'plan' => $id + ['format' => 'pdf', 'status' => 'blocked', 'reason' => 'historical_versions_differ', 'files' => []]], JSON_THROW_ON_ERROR);
	$lines[] = json_encode(['kind' => 'summary', 'summary' => ['mode' => 'dry-run', 'clientid' => 7, 'contacts_clientid' => 7, 'unassigned_scan' => 'complete', 'documents' => count($identities), 'blocked' => count($identities)]], JSON_THROW_ON_ERROR);
	file_put_contents($path, implode("\n", $lines) . "\n"); chmod($path, 0600);
}
$cases = []; $identities = []; $sourceFiles = []; $businessBefore = [];
$patterns = ['preferred_conflict' => ['A', 'B', 'A'], 'all_equal' => ['A', 'A', 'A'], 'no_current' => ['A', null, 'A'], 'attachment_current' => ['A', 'A', 'B'], 'current_legacy' => ['A', 'B', 'B'], 'all_different' => ['A', 'B', 'C'], 'no_attachment' => [null, 'A', 'A'], 'no_legacy' => ['A', 'A', null], 'none' => [null, null, null], 'invalid' => ['invalid', 'B', 'invalid']];
foreach (DEEC_DocumentArchive::documents() as $module => $types) foreach ($types as $type) $cases[$module . '-' . $type] = [$module, $type, ['A', 'B', 'A']];
foreach ($patterns as $name => $pattern) $cases[$name] = ['sales', 'invoice', $pattern];
$cases['missing_association'] = ['sales', 'invoice', ['A', 'B', 'A']];
$cases['foreign_attachment'] = ['sales', 'invoice', ['A', 'B', 'A']];
$cases['deleted_association'] = ['sales', 'invoice', ['A', 'B', 'A']];
$cases['symlink_source'] = ['sales', 'invoice', ['A', 'B', 'A']];
$cases['wrong_size'] = ['sales', 'invoice', ['A', 'B', 'A']];
$cases['ambiguous_contact'] = ['sales', 'invoice', ['A', 'B', 'A']];
$cases['foreign_document'] = ['sales', 'invoice', ['A', 'B', 'A']];
$cases['wrong_identity'] = ['sales', 'invoice', ['wrong', 'B', 'wrong']];
$cases['marked_copy'] = ['sales', 'invoice', ['copy', 'B', 'copy']];
$cases['occupied_canonical'] = ['sales', 'invoice', ['A', 'B', 'A']];
$cases['conflicting_registration'] = ['sales', 'invoice', ['A', 'B', 'A']];
$cases['matching_registration'] = ['sales', 'invoice', ['A', 'B', 'A']];
$index = 9100;
foreach ($cases as $name => [$module, $type, $pattern]) {
	$id = ++$index; integrityDraft($type, $id); $number = 800000 + $id; $filename = 'historical-' . $name . '.pdf';
	$pdo->prepare('UPDATE `' . $type . '` SET `' . $type . 'id` = ?, completed = 1, state = 105, filename = ?, subtotal = 100, taxes = 19, total = 119 WHERE id = ?')->execute([$number, $filename, $id]);
	$identity = ['module' => $module, 'controller' => $type, 'id' => $id, 'clientid' => 7, 'document_number' => (string)$number];
	$paths = $archive->paths($identity + ['contactid' => 12, 'contact_record_id' => 1, 'filename' => $filename]);
	$pdf = [];
	foreach (['A', 'B', 'C', 'copy'] as $version) $pdf[$version] = historicalTestPdf($identity, $version);
	$pdf['wrong'] = historicalTestPdf(array_replace($identity, ['document_number' => '9999999']), 'wrong identity');
	$pdf['invalid'] = "%PDF-1.4\ninvalid structure\n%%EOF\n";
	foreach (array_combine(['attachment', 'current', 'legacy'], $pattern) as $source => $version) if ($version !== null) { assessmentDbWrite($paths[$source], $pdf[$version]); $sourceFiles[$paths[$source]] = [hash_file('sha256', $paths[$source]), filemtime($paths[$source])]; }
	if ($name !== 'missing_association') integritySeed('emailattachment', ['clientid' => $name === 'foreign_attachment' ? 8 : 7, 'deleted' => $name === 'deleted_association' ? 1 : 0, 'documentid' => $id, 'module' => $module, 'controller' => $type, 'filename' => $filename, 'location' => dirname($paths['attachment']), 'filesize' => strlen($pdf[$pattern[0] ?? 'A']) + ($name === 'wrong_size' ? 1 : 0), 'filetype' => 'application/pdf']);
	if ($name === 'symlink_source') { unlink($paths['attachment']); symlink($paths['legacy'], $paths['attachment']); $sourceFiles[$paths['attachment']] = [hash_file('sha256', $paths['attachment']), filemtime($paths['attachment'])]; }
	if ($name === 'ambiguous_contact') { integritySeed('contact', ['id' => 9951, 'clientid' => 7, 'contactid' => 999]); integritySeed('contact', ['id' => 9952, 'clientid' => 7, 'contactid' => 999]); $pdo->exec('UPDATE invoice SET contactid = 999 WHERE id = ' . $id); }
	if ($name === 'foreign_document') $pdo->exec('UPDATE invoice SET clientid = 8 WHERE id = ' . $id);
	if ($name === 'occupied_canonical') $archive->store($pdf['B'], 7, $module, $type, $id, 'pdf');
	if (in_array($name, ['conflicting_registration', 'matching_registration'], true)) {
		$archive->store($pdf[$name === 'conflicting_registration' ? 'B' : 'A'], 7, $module, $type, $id, 'pdf');
		$document = $pdo->query('SELECT * FROM `' . $type . '` WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
		(new Application_Model_DbTable_Documentarchive())->registerOriginal($document, $module, $type);
		unlink($paths['canonical']);
	}
	$cases[$name] = ['identity' => $identity, 'paths' => $paths]; $identities[] = $identity;
	$businessBefore[$type][$id] = $pdo->query('SELECT * FROM `' . $type . '` WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
}
$inventoryPath = $work . '/reports/historical-inventory.jsonl'; historicalTestInventory($identities, $inventoryPath);
$registrationsBefore = $pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$preview = historicalTestCli(); $plan = recoveryJsonFile($preview['report']);
$byId = []; foreach ($plan['rows'] as $row) $byId[$row['identity']['id']] = $row;
$qualifying = ['sales-quote', 'sales-salesorder', 'sales-invoice', 'sales-deliveryorder', 'sales-creditnote', 'sales-reminder', 'purchases-quoterequest', 'purchases-purchaseorder', 'preferred_conflict', 'all_equal', 'no_current', 'matching_registration'];
foreach ($cases as $name => $case) integrityCheck($byId[$case['identity']['id']]['action'] === (in_array($name, $qualifying, true) ? 'recover' : 'excluded'), 'Unexpected historical eligibility: ' . $name . ' ' . json_encode($byId[$case['identity']['id']]));
integrityCheck($preview['summary']['recover'] === count($qualifying) && $pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $registrationsBefore, 'Preview mutated archive registrations or omitted qualifying documents');
integrityCheck((fileperms(dirname($preview['report'])) & 0777) === 0700 && (fileperms($preview['report']) & 0777) === 0600, 'Historical preview output is not private');
$applyOptions = ['config' => $work . '/root.ini', 'action' => 'apply', 'plan' => $preview['report'], 'confirm-plan' => $preview['report_sha256'], 'backup-verified' => false, 'backup-reference' => 'Synthetic verified database dump and complete source filesystem backup'];
historicalTestCli(array_replace($applyOptions, ['confirm-plan' => str_repeat('0', 64)]), false);
historicalTestCli(array_replace($applyOptions, ['backup-verified' => null]), false);
historicalTestCli(array_replace($applyOptions, ['backup-reference' => '']), false);
$pdo->exec('UPDATE user SET admin = 0 WHERE id = 42'); historicalTestCli($applyOptions, false); $pdo->exec('UPDATE user SET admin = 1 WHERE id = 42');
$source = $cases['sales-quote']['paths']['legacy']; $bytes = file_get_contents($source); file_put_contents($source, assessmentDbPdf('Synthetic changed source'));
historicalTestCli($applyOptions, false); file_put_contents($source, $bytes); touch($source, $sourceFiles[$source][1]);
integrityCheck($pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $registrationsBefore, 'Failed batch preflight changed registrations');
$testRow = $byId[$cases['sales-quote']['identity']['id']];
$occupiedPath = $testRow['files']['canonical']['path']; assessmentDbWrite($occupiedPath, historicalTestPdf($testRow['identity'], 'protected collision'));
historicalTestCli($applyOptions, false);
integrityCheck(hash_file('sha256', $occupiedPath) !== $testRow['files']['attachment']['sha256'], 'Batch overwrote an occupied original'); unlink($occupiedPath);
$pdo->exec("CREATE TRIGGER fail_historical BEFORE INSERT ON documentarchive FOR EACH ROW BEGIN IF NEW.documentid = " . $testRow['identity']['id'] . " AND NEW.controller = 'quote' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Synthetic historical registration failure'; END IF; END");
$testApproval = historicalRecoveryValidation('parser') + ['manual' => true, 'rule' => HISTORICAL_FILE_RULE, 'approved_by' => 42, 'approved_at' => gmdate('c'), 'batch_plan_sha256' => $preview['report_sha256'], 'database_and_file_backup_confirmed' => true, 'backup_reference' => 'Synthetic verified backup'];
$testUser = Zend_Registry::get('User'); Zend_Registry::set('User', array_replace($testUser, ['admin' => 0]));
correctionReject(fn() => historicalRecoveryApply($db, $testRow, '/usr/bin/pdftotext', $testApproval), 'Administrator batch approval and verified backup evidence required'); Zend_Registry::set('User', $testUser);
correctionReject(fn() => historicalRecoveryApply($db, $testRow, '/usr/bin/pdftotext', array_replace($testApproval, ['database_and_file_backup_confirmed' => false])), 'Administrator batch approval and verified backup evidence required');
try { historicalRecoveryApply($db, $testRow, '/usr/bin/pdftotext', $testApproval); throw new LogicException('Historical database failure was ignored'); }
catch (Zend_Db_Exception $error) { integrityCheck(str_contains($error->getMessage(), 'Synthetic historical registration failure'), 'Unexpected historical registration failure'); }
$pdo->exec('DROP TRIGGER fail_historical');
integrityCheck(!file_exists($occupiedPath) && $pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $registrationsBefore, 'Historical registration rollback retained uncommitted bytes or rows');
$applied = historicalTestCli($applyOptions); $result = recoveryJsonFile($applied['report']);
integrityCheck(($applied['summary']['recovered'] ?? 0) === count($qualifying) - 1 && ($applied['summary']['restored_registered_bytes'] ?? 0) === 1, 'Batch did not recover every approved qualifying document');
foreach ($result['rows'] as $row) if ($row['action'] === 'recover') {
	$id = $row['identity']; $original = (new Application_Model_DbTable_Documentarchive())->getForDocument($id['id'], $id['module'], $id['controller']);
	integrityCheck($original && hash_file('sha256', $base . '/' . $original['path']) === $row['files']['attachment']['sha256'] && file_get_contents($base . '/' . $original['path']) === file_get_contents($row['files']['legacy']['path']), 'Recovery changed historical PDF bytes');
	if (!$row['registrations']) {
		$metadata = json_decode($original['parameters'], true)['recovery'];
		integrityCheck($metadata['rule'] === HISTORICAL_FILE_RULE && $metadata['original_provenance_verified'] === false && DEEC_DocumentArchive::pdfProvenance($original) === 'registered_archive_origin_unconfirmed' && DEEC_DocumentArchive::hasRecoveryApproval($original), 'Recovery falsely upgraded original provenance or lost batch evidence');
		$document = $pdo->query('SELECT * FROM `' . $id['controller'] . '` WHERE id = ' . $id['id'])->fetch(PDO::FETCH_ASSOC);
		$archivePlan = $archive->plan($document + ['module' => $id['module'], 'controller' => $id['controller'], 'document_number' => $id['document_number'], 'contact_record_id' => 1], [$original]);
		integrityCheck($archivePlan['status'] === 'complete' && $original['sha256'] === (new Application_Model_DbTable_Documentarchive())->getForExport($id['id'], $id['module'], $id['controller'])['sha256'], 'Recovered original broke archive/export resolution');
		if ($id['controller'] === 'invoice') correctionReject(fn() => (new Sales_Service_InvoiceCorrectionService())->original($id['id']), 'CORRECTION_ORIGINAL_REQUIRED');
	}
}
foreach (['de_DE', 'en_US'] as $locale) {
	$translator = new DEEC_Translate($locale); $translator->loadDir('default', $root . '/languages/' . $locale . '/default'); Zend_Registry::set('DEEC_Translate', $translator);
	$view = new Zend_View(); $view->addHelperPath($root . '/application/views/helpers', 'Zend_View_Helper');
	$view->module = 'sales'; $view->controller = 'quote'; $view->quote = $businessBefore['quote'][$cases['sales-quote']['identity']['id']];
	$html = $view->Document();
	integrityCheck(str_contains($html, $translator->t('DOCUMENTS_HISTORICAL_FILE_RECOVERY_ORIGIN_UNCONFIRMED')) && !str_contains($html, 'DOCUMENTS_HISTORICAL_FILE_RECOVERY_ORIGIN_UNCONFIRMED'), 'Recovered file UI falsely omitted unproven provenance or translation');
}
foreach ($registrationsBefore as $original) integrityCheck($pdo->query('SELECT * FROM documentarchive WHERE id = ' . $original['id'])->fetch(PDO::FETCH_ASSOC) === $original, 'Recovery changed an existing archive registration or approval');
foreach ($sourceFiles as $path => $state) integrityCheck([hash_file('sha256', $path), filemtime($path)] === $state, 'Recovery changed a historical source or differing current version');
foreach ($businessBefore as $type => $documents) foreach ($documents as $id => $document) integrityCheck($pdo->query('SELECT * FROM `' . $type . '` WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC) === $document, 'Recovery changed a historical business row');
$retry = historicalTestCli($applyOptions); integrityCheck(($retry['summary']['already_recovered'] ?? 0) === count($qualifying), 'Reviewed batch retry changed or duplicated recovered originals');
integritySeed('client', ['id' => 29, 'parentid' => 7, 'activated' => 1]);
integrityDraft('invoice', 9300);
$pdo->exec("UPDATE invoice SET clientid = 29, invoiceid = 809300, completed = 1, state = 105, filename = 'historical-child.pdf' WHERE id = 9300");
$pdo->exec('UPDATE invoicepos SET clientid = 29 WHERE parentid = 9300');
$child = ['module' => 'sales', 'controller' => 'invoice', 'id' => 9300, 'clientid' => 29, 'document_number' => '809300'];
$childPaths = $archive->paths($child + ['contactid' => 12, 'contact_record_id' => 1, 'filename' => 'historical-child.pdf']);
$childPdfBytes = historicalTestPdf($child, 'child original');
foreach (['attachment', 'legacy'] as $name) assessmentDbWrite($childPaths[$name], $childPdfBytes);
integritySeed('emailattachment', ['clientid' => 29, 'documentid' => 9300, 'module' => 'sales', 'controller' => 'invoice', 'filename' => 'historical-child.pdf', 'location' => dirname($childPaths['attachment']), 'filesize' => filesize($childPaths['attachment']), 'filetype' => 'application/pdf']);
$childInventory = $work . '/reports/historical-child.jsonl'; historicalTestInventory([$child], $childInventory);
$childLines = file($childInventory, FILE_IGNORE_NEW_LINES); $childSummary = json_decode(array_pop($childLines), true); $childSummary['summary']['clientid'] = 29;
file_put_contents($childInventory, implode("\n", $childLines) . "\n" . json_encode($childSummary) . "\n");
$childPreview = historicalTestCli(['client' => '29', 'inventory' => $childInventory]);
integrityCheck(($childPreview['summary']['recover'] ?? 0) === 1, 'Authorized child recovery lost parent contact ownership');
$childOptions = array_replace($applyOptions, ['client' => '29', 'inventory' => $childInventory, 'plan' => $childPreview['report'], 'confirm-plan' => $childPreview['report_sha256']]);
$childWorkers = [historicalTestCli($childOptions, true, true), historicalTestCli($childOptions, true, true)];
$childResults = array_map('historicalTestFinish', $childWorkers);
integrityCheck(array_sum(array_map(static fn(array $result): int => $result['summary']['recovered'] ?? 0, $childResults)) === 1 && array_sum(array_map(static fn(array $result): int => $result['summary']['already_recovered'] ?? 0, $childResults)) === 1 && hash_file('sha256', $childPaths['canonical']) === hash_file('sha256', $childPaths['attachment']), 'Concurrent child batch recovery failed, duplicated or used foreign bytes');
require __DIR__ . '/document-historical-validation-fixtures.php';
echo 'PASS: ' . ($checks - $historicalStart) . " historical file recovery checks; all eight types, matching attachment/legacy, differing current versions, private batch approval, provenance, exclusions, immutable sources and retries\n";
