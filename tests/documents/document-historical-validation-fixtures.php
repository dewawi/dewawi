<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Included only by the disposable full-schema historical recovery fixture.
$modeStart = $checks; $modeCases = []; $modeIdentities = []; $modeFiles = []; $modeRows = [];
foreach (['matching', 'pair_differs', 'no_metadata', 'forged_identity', 'known_copy', 'known_reconstruction', 'known_correction', 'invalid_envelope', 'bad_offset', 'xref_stream', 'plain_mark', 'encrypted_hint'] as $index => $name) {
	$id = 9400 + $index; integrityDraft('invoice', $id); $number = 900000 + $id; $filename = 'synthetic-php-' . $name . '.pdf';
	$pdo->prepare('UPDATE invoice SET invoiceid = ?, completed = 1, state = 105, filename = ?, subtotal = 100, taxes = 19, total = 119 WHERE id = ?')->execute([$number, $filename, $id]);
	$identity = ['module' => 'sales', 'controller' => 'invoice', 'id' => $id, 'clientid' => 7, 'document_number' => (string)$number];
	$paths = $archive->paths($identity + ['contactid' => 12, 'contact_record_id' => 1, 'filename' => $filename]);
	$bytes = historicalTestPdf($identity, $name === 'known_copy' ? 'copy' : 'PHP association mode');
	if ($name === 'known_correction') $bytes = file_get_contents($base . '/' . $pdo->query("SELECT path FROM invoicecorrection WHERE clientid = 7 AND status = 'approved' ORDER BY id LIMIT 1")->fetchColumn());
	if ($name === 'invalid_envelope') $bytes = "%PDF-1.4\nmissing xref\n%%EOF\n";
	if ($name === 'bad_offset') $bytes = preg_replace('/startxref\s+[0-9]+/', "startxref\n99999999999999999999999999999", $bytes);
	if ($name === 'xref_stream') {
		$bytes = "%PDF-1.5\n"; $offsets = [0];
		$objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] /Resources << >> /Contents 4 0 R >>', "<< /Length 0 >>\nstream\nendstream"];
		foreach ($objects as $index => $object) { $offsets[] = strlen($bytes); $bytes .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n"; }
		$offsets[] = strlen($bytes); $entries = chr(0) . pack('Nn', 0, 65535);
		foreach (array_slice($offsets, 1) as $offset) $entries .= chr(1) . pack('Nn', $offset, 0);
		$bytes .= "5 0 obj\n<< /Type /XRef /Size 6 /Root 1 0 R /W [1 4 2] /Length " . strlen($entries) . " >>\nstream\n" . $entries . "\nendstream\nendobj\nstartxref\n" . $offsets[5] . "\n%%EOF\n";
	}
	if ($name === 'plain_mark' || $name === 'encrypted_hint') {
		// Add an incremental trailer-like indication without changing the referenced xref offset.
		$bytes = preg_replace('/startxref/', ($name === 'plain_mark' ? '(KOPIE - REKONSTRUKTION)' : '/Encrypt 99 0 R') . "\nstartxref", $bytes);
	}
	assessmentDbWrite($paths['attachment'], $bytes); assessmentDbWrite($paths['legacy'], $name === 'pair_differs' ? historicalTestPdf($identity, 'different version') : $bytes);
	assessmentDbWrite($paths['current'], historicalTestPdf($identity, 'different retained current version'));
	if ($name !== 'no_metadata') integritySeed('emailattachment', ['clientid' => 7, 'documentid' => $id, 'module' => 'sales', 'controller' => 'invoice', 'filename' => $filename, 'location' => dirname($paths['attachment']), 'filesize' => strlen($bytes), 'filetype' => 'application/pdf']);
	if ($name === 'known_copy') {
		$copyPath = $base . '/files/documents/.viewing-copies/7/sales/invoice/' . $id . '/' . str_repeat('0', 64) . '/de_DE/' . hash('sha256', $bytes) . '.pdf';
		assessmentDbWrite($copyPath, $bytes);
	}
	if ($name === 'known_reconstruction') {
		$archive->store($bytes, 7, 'sales', 'invoice', $id, 'pdf');
		(new Application_Model_DbTable_Documentarchive())->registerOriginal($pdo->query('SELECT * FROM invoice WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC), 'sales', 'invoice', 'pdf', null, ['recovery' => ['selection' => ['origin' => ['kind' => 'reconstructed']]]]);
		unlink($paths['canonical']);
	}
	$modeRows[$id] = $pdo->query('SELECT * FROM invoice WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
	foreach (['attachment', 'legacy', 'current'] as $source) $modeFiles[$paths[$source]] = [hash_file('sha256', $paths[$source]), filemtime($paths[$source])];
	$modeCases[$name] = ['identity' => $identity, 'paths' => $paths];
	$modeIdentities[] = $name === 'forged_identity' ? array_replace($identity, ['document_number' => '123456789']) : $identity;
}
$modeInventory = $work . '/reports/php-validation-inventory.jsonl'; historicalTestInventory($modeIdentities, $modeInventory);
$missingExtractor = $work . '/pdftotext-not-installed';
$modeOptions = ['inventory' => $modeInventory, 'validation-mode' => 'file-association', 'pdftotext' => $missingExtractor];
$unavailable = historicalTestCli(array_replace($modeOptions, ['validation-mode' => 'parser']));
$unavailablePlan = recoveryJsonFile($unavailable['report']);
integrityCheck(($unavailable['summary']['recover'] ?? 0) === 0 && count(array_filter($unavailablePlan['rows'], static fn(array $row): bool => ($row['pdf_validation']['status'] ?? '') === 'unavailable')) > 0, 'Strict parser mode silently bypassed the missing executable');
$phpPreview = historicalTestCli($modeOptions); $phpPlan = recoveryJsonFile($phpPreview['report']); $phpById = [];
foreach ($phpPlan['rows'] as $row) $phpById[$row['identity']['id']] = $row;
integrityCheck($phpPlan['validation_mode'] === 'file-association' && $phpPlan['pdftotext'] === null && count($phpPlan['validation_limitations']) === 3, 'Parser-free preview did not bind its mode and limitations');
foreach ($modeCases as $name => $case) integrityCheck($phpById[$case['identity']['id']]['action'] === (in_array($name, ['matching', 'xref_stream'], true) ? 'recover' : 'excluded'), 'Unexpected PHP-mode eligibility: ' . $name . ' ' . json_encode($phpById[$case['identity']['id']]));
$matching = $phpById[$modeCases['matching']['identity']['id']];
integrityCheck($matching['pdf_validation']['external_parser_used'] === false && $matching['pdf_validation']['content_verified'] === false && $matching['pdf_validation']['embedded_marks_fully_verified'] === false && $matching['document_identity_text']['status'] === 'not_verified_without_independent_parser', 'Limited PHP inspection falsely claimed verified content, marks or parser identity');
// An executable supplied in association mode must not run, even if it exists.
$trap = $work . '/parser-trap'; $trapMark = $work . '/parser-was-run';
file_put_contents($trap, "#!/bin/sh\ntouch '" . $trapMark . "'\nexit 1\n"); chmod($trap, 0700);
historicalTestCli(array_replace($modeOptions, ['pdftotext' => $trap]));
integrityCheck(!file_exists($trapMark), 'Association mode invoked an external executable');
$phpApply = array_replace($modeOptions, ['config' => $work . '/root.ini', 'action' => 'apply', 'plan' => $phpPreview['report'], 'confirm-plan' => $phpPreview['report_sha256'], 'backup-verified' => false, 'backup-reference' => 'Synthetic separately verified database and file backup']);
historicalTestCli(array_replace($phpApply, ['validation-mode' => 'parser']), false);
historicalTestCli(array_replace($phpApply, ['validation-mode' => null]), false);
historicalTestCli(array_replace($phpApply, ['validation-mode' => 'unknown']), false);
historicalTestCli(array_replace($phpApply, ['confirm-plan' => str_repeat('0', 64)]), false);
historicalTestCli(array_replace($phpApply, ['backup-verified' => null]), false);
foreach (['header_mode', 'header_limits', 'row_mode', 'row_limits'] as $tamper) {
	$changedPlan = $phpPlan;
	if ($tamper === 'header_mode') $changedPlan['validation_mode'] = 'parser';
	if ($tamper === 'header_limits') $changedPlan['validation_limitations'] = [];
	if ($tamper === 'row_mode') $changedPlan['rows'][0]['validation_mode'] = 'parser';
	if ($tamper === 'row_limits') $changedPlan['rows'][0]['validation_limitations'] = [];
	$changedPath = $work . '/reports/tampered-' . $tamper . '.json'; reviewWrite($changedPath, reviewJson($changedPlan));
	// Even a newly confirmed hash cannot bypass mode/context and per-row revalidation.
	historicalTestCli(array_replace($phpApply, ['plan' => $changedPath, 'confirm-plan' => hash_file('sha256', $changedPath)]), false);
}
$pdo->exec('UPDATE user SET admin = 0 WHERE id = 42'); historicalTestCli($phpApply, false); $pdo->exec('UPDATE user SET admin = 1 WHERE id = 42');
$before = $pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$metadataId = $modeCases['matching']['identity']['id'];
$pdo->exec('UPDATE emailattachment SET clientid = 8 WHERE documentid = ' . $metadataId . " AND module = 'sales' AND controller = 'invoice'");
historicalTestCli($phpApply, false);
$pdo->exec('UPDATE emailattachment SET clientid = 7 WHERE documentid = ' . $metadataId . " AND module = 'sales' AND controller = 'invoice'");
$pdo->exec('UPDATE invoice SET total = 120 WHERE id = ' . $metadataId); historicalTestCli($phpApply, false); $pdo->exec('UPDATE invoice SET total = 119 WHERE id = ' . $metadataId);
$stalePath = $modeCases['matching']['paths']['legacy']; $staleBytes = file_get_contents($stalePath);
file_put_contents($stalePath, historicalTestPdf($modeCases['matching']['identity'], 'changed after reviewed preview'));
historicalTestCli($phpApply, false); file_put_contents($stalePath, $staleBytes); touch($stalePath, $modeFiles[$stalePath][1]);
integrityCheck($pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $before, 'Stale parser-free preflight changed the archive');
$modeApproval = historicalRecoveryValidation('file-association') + ['manual' => true, 'rule' => HISTORICAL_FILE_RULE, 'approved_by' => 42, 'approved_at' => gmdate('c'), 'batch_plan_sha256' => $phpPreview['report_sha256'], 'database_and_file_backup_confirmed' => true, 'backup_reference' => 'Synthetic verified backup'];
correctionReject(fn() => historicalRecoveryApply($db, $matching, $missingExtractor, $modeApproval), 'Reviewed row and approval validation mode/limitations differ');
correctionReject(fn() => historicalRecoveryApply($db, $matching, $missingExtractor, array_replace($modeApproval, ['validation_limitations' => []]), 'file-association'), 'Reviewed row and approval validation mode/limitations differ');
$workers = [historicalTestCli($phpApply, true, true), historicalTestCli($phpApply, true, true)]; $modeResults = array_map('historicalTestFinish', $workers);
integrityCheck(array_sum(array_map(static fn(array $result): int => $result['summary']['recovered'] ?? 0, $modeResults)) === 2 && array_sum(array_map(static fn(array $result): int => $result['summary']['already_recovered'] ?? 0, $modeResults)) === 2, 'Concurrent parser-free recovery duplicated or changed originals');
foreach (['matching', 'xref_stream'] as $name) {
	$id = $modeCases[$name]['identity']['id']; $original = (new Application_Model_DbTable_Documentarchive())->getForDocument($id, 'sales', 'invoice');
	$metadata = json_decode($original['parameters'], true)['recovery'];
	integrityCheck($metadata['validation_mode'] === 'file-association' && $metadata['validation_limitations'] === $phpPlan['validation_limitations'] && $metadata['approval']['validation_mode'] === 'file-association' && $metadata['approval']['validation_limitations'] === $phpPlan['validation_limitations'] && $metadata['original_provenance_verified'] === false && DEEC_DocumentArchive::pdfProvenance($original) === 'registered_archive_origin_unconfirmed', 'Parser-free mode/limits/provenance were lost in archive audit metadata');
	integrityCheck(file_get_contents($base . '/' . $original['path']) === file_get_contents($modeCases[$name]['paths']['attachment']), 'Parser-free recovery changed original bytes');
	correctionReject(fn() => (new Sales_Service_InvoiceCorrectionService())->original($id), 'CORRECTION_ORIGINAL_REQUIRED');
}
$phpApplication = recoveryJsonFile($modeResults[0]['report']);
integrityCheck($phpApplication['approval']['validation_mode'] === 'file-association' && $phpApplication['approval']['validation_limitations'] === $phpPlan['validation_limitations'], 'Private batch approval omitted mode/limitations');
foreach ($modeFiles as $path => $state) integrityCheck([hash_file('sha256', $path), filemtime($path)] === $state, 'PHP-mode recovery modified a historical source file');
foreach ($modeRows as $id => $row) integrityCheck($pdo->query('SELECT * FROM invoice WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC) === $row, 'PHP-mode recovery changed historical business data');
echo 'PASS: ' . ($checks - $modeStart) . " parser-free historical validation checks; no executable, conservative PHP envelopes, mode-bound approval, exclusions, stale plans, concurrent recovery and unchanged provenance/bytes\n";
