<?php

// Included only by the disposable recovery database regression.
if (!isset($pdo, $work, $base) || !preg_match('~^/tmp/dewawi-document-recovery-db-[a-zA-Z0-9_]+$~D', $work)) throw new RuntimeException('Isolated invoice batch fixtures are required');
require_once __DIR__ . '/invoice-original-batch.php';
$batchCheckStart = $checks;
recoveryTestInsert('client', ['id' => 100, 'company' => 'Invoice batch tenant fixture', 'activated' => 1]);
recoveryTestInsert('client', ['id' => 101, 'company' => 'Foreign batch tenant fixture', 'activated' => 1]);
recoveryTestInsert('user', ['id' => 142, 'clientid' => 100, 'name' => 'Batch operator fixture', 'activated' => 1]);
recoveryTestInsert('user', ['id' => 143, 'clientid' => 101, 'name' => 'Foreign batch operator', 'activated' => 1]);
recoveryTestInsert('permission', ['userid' => 142, 'clientid' => 100, 'sales' => '{"invoice":["view","edit"]}']);
$invoiceBatchRoot = $work . '/invoice-batch-review';
reviewDirectory($invoiceBatchRoot . '/runs/initial');
reviewWrite($invoiceBatchRoot . '/review.json', reviewJson(['schema_version' => 1, 'clientid' => 100, 'contacts_clientid' => 100, 'base' => $base]));
reviewWrite($invoiceBatchRoot . '/.collector.lock', '');
$invoiceBatchRun = ['clientid' => 100, 'base' => $base, 'documents' => []];
$invoiceBatchPdfs = []; $invoiceBatchDirectories = []; $invoiceBatchIdentities = [];
require_once $root . '/library/Tcpdf/tcpdf.php';
function invoiceBatchFixturePdf(int $number, string $extra = ''): string
{
	$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false); $pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->AddPage();
	$pdf->Write(0, 'Invoice ' . $number . ' | 2010-01-02 | Stored buyer | Position: 100 EUR x 1 | Tax: 19 EUR | Total: 119 EUR | Customer archive fixture ' . $extra);
	return $pdf->Output('', 'S');
}
for ($i = 0; $i < 27; $i++) {
	$id = 6000 + $i; $number = 109860 + $i;
	$document = $pdo->query('SELECT * FROM invoice WHERE id = 3')->fetch(PDO::FETCH_ASSOC);
	recoveryTestInsert('invoice', array_replace($document, ['id' => $id, 'clientid' => 100, 'invoiceid' => $number, 'invoicedate' => '2010-01-02', 'filename' => 'invoice-' . $number . '.pdf', 'subtotal' => 100, 'taxes' => 19, 'total' => 119, 'cancelled' => 0, 'deleted' => 0]));
	$position = $pdo->query('SELECT * FROM invoicepos WHERE id = 3')->fetch(PDO::FETCH_ASSOC);
	recoveryTestInsert('invoicepos', array_replace($position, ['id' => 9000 + $i, 'clientid' => 100, 'parentid' => $id, 'price' => 100, 'quantity' => 1, 'taxrate' => 19, 'total' => 119]));
	$identity = ['module' => 'sales', 'controller' => 'invoice', 'id' => $id, 'clientid' => 100, 'document_number' => (string)$number];
	$directory = $invoiceBatchRoot . '/sales-invoice-' . $id . '-' . $number;
	reviewDirectory($directory . '/candidates'); reviewDirectory($directory . '/external');
	reviewWrite($directory . '/manifest.json', reviewJson(['selected' => $identity + ['reported_reasons' => ['original_missing']], 'candidates' => [], 'sources' => []]));
	reviewWrite($directory . '/selection.json', reviewJson(['document' => $identity, 'decision' => 'pending']));
	$invoiceBatchRun['documents'][] = ['key' => reviewKey($identity), 'manifest' => basename($directory) . '/manifest.json'];
	$invoiceBatchDirectories[$id] = $directory; $invoiceBatchIdentities[$id] = $identity;
	$invoiceBatchPdfs['correct/' . $number . '.pdf'] = invoiceBatchFixturePdf($number);
}
reviewWrite($invoiceBatchRoot . '/runs/initial/run.json', reviewJson($invoiceBatchRun));
$firstDocument = $pdo->query('SELECT * FROM invoice WHERE id = 6000')->fetch(PDO::FETCH_ASSOC);
recoveryTestInsert('invoice', array_replace($firstDocument, ['id' => 6100, 'clientid' => 101, 'invoiceid' => 150001]));
$archive = new DEEC_DocumentArchive($base);
$firstFile = $archive->store($invoiceBatchPdfs['correct/109860.pdf'], 100, 'sales', 'invoice', 6000, 'pdf');
recoveryTestInsert('documentarchive', ['clientid' => 100, 'module' => 'sales', 'controller' => 'invoice', 'documentid' => 6000, 'format' => 'pdf', 'version' => 1, 'filename' => $firstDocument['filename'], 'path' => $archive->relativePath(100, 'sales', 'invoice', 6000), 'filesize' => $firstFile['size'], 'sha256' => $firstFile['sha256'], 'archived' => '2026-11-01 10:00:00', 'archivedby' => 142, 'parameters' => '{}']);
function invoiceBatchFixtureZip(string $path, array $entries): void
{
	$zip = new ZipArchive();
	if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Fixture ZIP creation failed');
	$zip->addEmptyDir('correct');
	foreach ($entries as $name => $bytes) $zip->addFromString($name, $bytes);
	$zip->close(); chmod($path, 0600);
}
$invoiceBatchZip = $work . '/correct.zip'; invoiceBatchFixtureZip($invoiceBatchZip, $invoiceBatchPdfs);
function invoiceBatchCli(array $arguments = [], bool $success = true): array
{
	global $work, $socket, $root, $invoiceBatchRoot, $invoiceBatchZip;
	$command = [PHP_BINARY, '-d', 'error_reporting=8191', '-d', 'display_errors=stderr', '-d', 'pdo_mysql.default_socket=' . $socket, $root . '/tests/documents/invoice-original-batch.php'];
	$options = array_replace(['config' => $work . '/' . (($arguments['action'] ?? 'plan') === 'apply' ? 'archive.ini' : 'database.ini'), 'client' => 100, 'operator-user' => 142, 'zip' => $invoiceBatchZip, 'review' => $invoiceBatchRoot, 'run' => $invoiceBatchRoot . '/runs/initial/run.json', 'action' => 'plan'], $arguments);
	foreach ($options as $key => $value) if ($value !== null) $command[] = '--' . $key . ($value === true ? '' : '=' . $value);
	$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
	if (($code === 0) !== $success) throw new RuntimeException('Unexpected invoice batch exit: ' . $code . ' ' . $error . $out);
	return ['code' => $code, 'json' => $out === '' ? null : json_decode($out, true, 512, JSON_THROW_ON_ERROR), 'error' => $error];
}
$invoiceBatchOldArchiveRows = $pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$invoiceBatchOldFiles = [];
foreach ([$base . '/files/documents', $invoiceBatchRoot] as $directory) foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) if ($file->isFile()) $invoiceBatchOldFiles[$file->getPathname()] = [hash_file('sha256', $file->getPathname()), filemtime($file->getPathname())];
$invoiceBatchBusiness = [];
foreach (['invoice', 'invoicepos', 'invoiceposset', 'pricerulepos', 'contact', 'emailattachment', 'emailmessage', 'emailmessageattachment'] as $table) $invoiceBatchBusiness[$table] = $pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$undeclared = invoiceBatchCli()['json'];
recoveryTestCheck($undeclared['summary'] === ['already_archived' => 1, 'blocked' => 26], 'Initial plan did not distinguish 109860 and the 26 required declarations');
$template = recoveryJsonFile($undeclared['declaration_template']);
recoveryTestCheck(count($template['documents']) === 27 && $template['documents'][0]['comparison_performed_at'] === null && !$template['documents'][1]['business_values_compared'], 'Template invented a historical comparison or silently confirmed business data');
foreach ($invoiceBatchOldFiles as $path => $state) recoveryTestCheck([hash_file('sha256', $path), filemtime($path)] === $state, 'Plan changed a prior archive/recovery file');
recoveryTestCheck($pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $invoiceBatchOldArchiveRows, 'Plan changed archive rows');
foreach (['client', 'operator-user', 'zip', 'review', 'run', 'config', 'action'] as $required) recoveryTestCheck(str_contains(invoiceBatchCli([$required => null], false)['error'], '--' . $required), 'Missing required option was not rejected: ' . $required);
foreach ($template['documents'] as &$statement) {
	foreach (['original_provenance_verified', 'identity_verified', 'customer_archive_compared', 'business_values_compared', 'positions_taxes_totals_match'] as $field) $statement[$field] = true;
	$statement['unresolved_differences'] = false; $statement['provenance'] = 'Synthetic customer archive source ' . $statement['entry']; $statement['comparison_reference'] = 'Fixture comparison against customer archive and the supplied saved business basis';
} unset($statement);
$declarationPath = $invoiceBatchRoot . '/runs/operator-declaration.json'; reviewWrite($declarationPath, reviewJson($template));
$declared = ['declaration' => $declarationPath];
$ready = invoiceBatchCli($declared)['json']; $plan = recoveryJsonFile($ready['report']);
recoveryTestCheck($ready['summary'] === ['already_archived' => 1, 'register' => 26] && hash_file('sha256', $ready['report']) === $ready['report_sha256'], 'Declared plan counts or SHA differ');
$csv = fopen(substr($ready['report'], 0, -5) . '.csv', 'rb'); $columns = fgetcsv($csv, 0, ';', '"', ''); $csvRows = []; while (($values = fgetcsv($csv, 0, ';', '"', '')) !== false) $csvRows[] = array_combine($columns, $values); fclose($csv);
recoveryTestCheck(count($csvRows) === 27 && $csvRows[0]['documentid'] === '6000' && $csvRows[0]['document_number'] === '109860' && $csvRows[1]['archive_status'] === 'missing' && (fileperms($ready['report']) & 0777) === 0600, 'JSON/CSV plan lost IDs, dates, archive state or privacy');
$apply = $declared + ['action' => 'apply', 'plan' => $ready['report'], 'confirm-plan' => $ready['report_sha256'], 'backup-verified' => true, 'backup-reference' => 'Verified isolated database and document-file fixture backup'];
foreach ([['confirm-plan' => str_repeat('0', 64)], ['backup-verified' => null], ['backup-reference' => null], ['declaration' => null], ['operator-user' => 143], ['client' => 101]] as $bad) invoiceBatchCli(array_replace($apply, $bad), false);
$pdo->exec("UPDATE permission SET sales = '{\"invoice\":[\"view\"]}' WHERE userid = 142");
invoiceBatchCli($declared, false); invoiceBatchCli($apply, false);
$pdo->exec("UPDATE permission SET sales = '{\"invoice\":[\"view\",\"edit\"]}' WHERE userid = 142");
$badDeclaration = $template; $badDeclaration['documents'][1]['unresolved_differences'] = true; $badDeclaration['documents'][1]['differences'] = ['PDF and stored total require clarification'];
$badDeclarationPath = $invoiceBatchRoot . '/runs/unresolved-declaration.json'; reviewWrite($badDeclarationPath, reviewJson($badDeclaration));
$blocked = invoiceBatchCli(['declaration' => $badDeclarationPath])['json'];
recoveryTestCheck($blocked['summary']['blocked'] === 1 && str_contains(recoveryJsonFile($blocked['report'])['rows'][1]['reason'], 'manual clarification'), 'Business-value disagreement was silently confirmed');
$badDeclaration['documents'][1]['unresolved_differences'] = false; $badDeclaration['documents'][1]['differences'] = []; $badDeclaration['documents'][1]['business_values_compared'] = false;
$badDeclarationPath2 = $invoiceBatchRoot . '/runs/uncompared-declaration.json'; reviewWrite($badDeclarationPath2, reviewJson($badDeclaration));
recoveryTestCheck(invoiceBatchCli(['declaration' => $badDeclarationPath2])['json']['summary']['blocked'] === 1, 'Customer archive comparison alone implied current business-data equivalence');
foreach (['2026-02-30T12:00:00Z', '2100-01-01T12:00:00Z', 'not a timestamp'] as $badTime) {
	$statement = array_replace($template['documents'][1], ['comparison_performed_at' => $badTime]);
	try { DEEC_DocumentRecovery::customerArchiveAttestation($statement, $statement['document'], $statement['snapshot_sha256'], $statement['sha256']); throw new LogicException('Invalid comparison time accepted'); }
	catch (RuntimeException $error) { recoveryTestCheck(true, 'Invalid or invented comparison timestamp was rejected'); }
}
$knownTime = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
$statement = array_replace($template['documents'][1], ['comparison_performed_at' => $knownTime]);
DEEC_DocumentRecovery::customerArchiveAttestation($statement, $statement['document'], $statement['snapshot_sha256'], $statement['sha256']);
recoveryTestCheck($statement['comparison_performed_at'] === $knownTime, 'Known comparison time was rewritten');
function invoiceBatchFixtureSubset(array $plan, int $index, string $path): array
{
	foreach ($plan['rows'] as $i => &$row) if ($i !== $index) $row['proposed_action'] = 'blocked'; unset($row);
	reviewWrite($path, reviewJson($plan));
	return ['plan' => $path, 'confirm-plan' => hash_file('sha256', $path)];
}
$forged = $plan; $forged['rows'][1]['identity'] = $forged['rows'][2]['identity'];
$forgedOptions = invoiceBatchFixtureSubset($forged, 1, $invoiceBatchRoot . '/runs/forged-identity-plan.json');
$forgedResult = invoiceBatchCli(array_replace($apply, $forgedOptions), false)['json'];
recoveryTestCheck(str_contains(recoveryJsonFile($forgedResult['report'])['rows'][1]['reason'], 'ZIP filename'), 'Apply trusted a forged plan identity instead of the exact ZIP number');
$existingOnly = invoiceBatchFixtureSubset($plan, 0, $invoiceBatchRoot . '/runs/archived-only-plan.json');
recoveryTestInsert('invoice', array_replace($firstDocument, ['id' => 6102]));
$ambiguousApply = invoiceBatchCli(array_replace($apply, $existingOnly), false)['json'];
recoveryTestCheck(str_contains(recoveryJsonFile($ambiguousApply['report'])['rows'][0]['reason'], 'ambiguous'), 'Already archived row bypassed a newly ambiguous number mapping');
$pdo->exec('DELETE FROM invoice WHERE id = 6102');
$selectionPath = $invoiceBatchDirectories[6001] . '/selection.json'; $selectionBytes = file_get_contents($selectionPath); $selectionTime = filemtime($selectionPath);
file_put_contents($selectionPath, reviewJson(['document' => $invoiceBatchIdentities[6001], 'decision' => 'pending', 'new_manual_context' => true]));
$selectedOnly = invoiceBatchFixtureSubset($plan, 1, $invoiceBatchRoot . '/runs/selected-only-plan.json');
$selectionConflict = invoiceBatchCli(array_replace($apply, $selectedOnly), false)['json'];
recoveryTestCheck(str_contains(recoveryJsonFile($selectionConflict['report'])['rows'][1]['reason'], 'manual selection'), 'Apply overwrote another selection changed after the plan');
file_put_contents($selectionPath, $selectionBytes); touch($selectionPath, $selectionTime);
$badScope = $template; $badScope['documents'][] = array_replace($badScope['documents'][1], ['entry' => 'correct/999999.pdf']);
$badScopePath = $invoiceBatchRoot . '/runs/unknown-declaration-file.json'; reviewWrite($badScopePath, reviewJson($badScope));
invoiceBatchCli(['declaration' => $badScopePath], false);
$missingScope = $template; array_splice($missingScope['documents'], 1, 1);
$missingScopePath = $invoiceBatchRoot . '/runs/missing-declaration-file.json'; reviewWrite($missingScopePath, reviewJson($missingScope));
recoveryTestCheck(invoiceBatchCli(['declaration' => $missingScopePath])['json']['summary']['blocked'] === 1, 'An unlisted PDF inherited a batch declaration');
$declarationBytes = file_get_contents($declarationPath); file_put_contents($declarationPath, $declarationBytes . "\n"); invoiceBatchCli($apply, false); file_put_contents($declarationPath, $declarationBytes);
$zipBytes = file_get_contents($invoiceBatchZip); invoiceBatchFixtureZip($invoiceBatchZip, array_replace($invoiceBatchPdfs, ['correct/109861.pdf' => invoiceBatchFixturePdf(109861, 'changed after plan')])); invoiceBatchCli($apply, false); file_put_contents($invoiceBatchZip, $zipBytes);
$adversarialZip = $work . '/adversarial.zip';
foreach (['correct/../109861.pdf', '/correct/109861.pdf', 'correct/0109861.pdf', 'correct/109861.PDF', 'correct/109861.php', 'other/109861.pdf'] as $entry) { invoiceBatchFixtureZip($adversarialZip, [$entry => $invoiceBatchPdfs['correct/109861.pdf']]); invoiceBatchCli(['zip' => $adversarialZip], false); }
invoiceBatchFixtureZip($adversarialZip, ['correct/109861.pdf' => str_repeat('A', 1048576)]); invoiceBatchCli(['zip' => $adversarialZip], false);
invoiceBatchFixtureZip($adversarialZip, ['correct/109861.pdf' => $invoiceBatchPdfs['correct/109861.pdf']]);
$zip = new ZipArchive(); $zip->open($adversarialZip); $zip->setExternalAttributesName('correct/109861.pdf', ZipArchive::OPSYS_UNIX, 0120777 << 16); $zip->close(); invoiceBatchCli(['zip' => $adversarialZip], false);
invoiceBatchFixtureZip($adversarialZip, ['correct/109861.pdf' => $invoiceBatchPdfs['correct/109861.pdf']]);
$zip = new ZipArchive(); $zip->open($adversarialZip); $zip->setPassword('synthetic-fixture-only');
recoveryTestCheck($zip->setEncryptionName('correct/109861.pdf', ZipArchive::EM_AES_256), 'ZIP encryption fixture unavailable'); $zip->close(); invoiceBatchCli(['zip' => $adversarialZip], false);
$manyEntries = []; for ($i = 1; $i <= 101; $i++) $manyEntries['correct/' . $i . '.pdf'] = $invoiceBatchPdfs['correct/109861.pdf'];
invoiceBatchFixtureZip($adversarialZip, $manyEntries); invoiceBatchCli(['zip' => $adversarialZip], false);
invoiceBatchFixtureZip($adversarialZip, ['correct/109861.pdf' => $invoiceBatchPdfs['correct/109861.pdf'], 'correct/109862.pdf' => $invoiceBatchPdfs['correct/109862.pdf']]);
file_put_contents($adversarialZip, str_replace('correct/109862.pdf', 'correct/109861.pdf', file_get_contents($adversarialZip))); invoiceBatchCli(['zip' => $adversarialZip], false);
invoiceBatchFixtureZip($adversarialZip, ['correct/109861.pdf' => str_repeat('A', 20971521)]);
$zip = new ZipArchive(); $zip->open($adversarialZip); $zip->setCompressionName('correct/109861.pdf', ZipArchive::CM_STORE); $zip->close(); invoiceBatchCli(['zip' => $adversarialZip], false);
invoiceBatchFixtureZip($adversarialZip, ['correct/109861.pdf' => $invoiceBatchPdfs['correct/109861.pdf']]);
$bytes = file_get_contents($adversarialZip); $end = strrpos($bytes, "PK\x05\x06");
file_put_contents($adversarialZip, substr($bytes, 0, $end) . "PK\x06\x07" . pack('V', 0) . pack('P', 0) . pack('V', 1) . substr($bytes, $end));
recoveryTestCheck(str_contains(invoiceBatchCli(['zip' => $adversarialZip], false)['error'], 'ZIP64'), 'ZIP64 locator bypassed preallocation limits');
symlink($invoiceBatchZip, $work . '/linked-correct.zip'); invoiceBatchCli(['zip' => $work . '/linked-correct.zip'], false);
invoiceBatchFixtureZip($adversarialZip, ['correct/109861.pdf' => "%PDF-1.4\nBroken fixture without EOF marker\n"]);
$invalid = invoiceBatchCli(['zip' => $adversarialZip])['json']; recoveryTestCheck(recoveryJsonFile($invalid['report'])['rows'][0]['candidate_status'] === 'invalid_pdf', 'Malformed PDF remained importable');
foreach ([999999, 150001] as $number) { invoiceBatchFixtureZip($adversarialZip, ['correct/' . $number . '.pdf' => invoiceBatchFixturePdf($number)]); $missing = invoiceBatchCli(['zip' => $adversarialZip])['json']; recoveryTestCheck($missing['summary'] === ['blocked' => 1] && recoveryJsonFile($missing['report'])['rows'][0]['identity'] === null, 'Unknown/foreign invoice number was mapped'); }
invoiceBatchFixtureZip($adversarialZip, ['correct/109860.pdf' => invoiceBatchFixturePdf(109860, 'conflicting original')]);
$conflict = invoiceBatchCli(['zip' => $adversarialZip])['json']; recoveryTestCheck(recoveryJsonFile($conflict['report'])['rows'][0]['candidate_status'] === 'CONFLICT', '109860 SHA conflict was treated as already archived');
$duplicateInvoice = array_replace($firstDocument, ['id' => 6101, 'invoiceid' => 109861]); recoveryTestInsert('invoice', $duplicateInvoice);
$ambiguous = invoiceBatchCli($declared)['json']; recoveryTestCheck(recoveryJsonFile($ambiguous['report'])['rows'][1]['identity'] === null, 'Duplicate invoice number was assigned arbitrarily'); $pdo->exec('DELETE FROM invoice WHERE id = 6101');
$diagnosticDirectory = $invoiceBatchDirectories[6003] . '/diagnostics'; reviewDirectory($diagnosticDirectory);
$diagnosticPath = $diagnosticDirectory . '/' . str_repeat('a', 64) . '.json'; reviewWrite($diagnosticPath, reviewJson(['identity' => $invoiceBatchIdentities[6003], 'diagnosis' => ['issues' => ['open fixture issue']]]));
$diagnosticPlan = invoiceBatchCli($declared)['json']; recoveryTestCheck(recoveryJsonFile($diagnosticPlan['report'])['rows'][3]['proposed_action'] === 'blocked', 'Diagnostic conflict was ignored in plan');
recoveryTestCheck($pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $invoiceBatchOldArchiveRows, 'Preflight checks registered an archive original');
$pdo->exec('UPDATE invoice SET total = 120 WHERE id = 6001');
$pdo->exec("CREATE TRIGGER invoice_batch_fail BEFORE INSERT ON documentarchive FOR EACH ROW BEGIN IF NEW.documentid = 6002 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'isolated invoice batch insert failure'; END IF; END");
$partial = invoiceBatchCli($apply, false)['json'];
recoveryTestCheck($partial['summary'] === ['already_archived' => 1, 'failed' => 3, 'registered' => 23], 'Partial failure did not isolate stale data, failed insert and diagnosis: ' . json_encode($partial));
$partialRows = recoveryJsonFile($partial['report'])['rows'];
recoveryTestCheck(str_contains($partialRows[1]['reason'], 'fingerprint') && str_contains($partialRows[2]['reason'], 'isolated invoice batch insert failure') && str_contains($partialRows[3]['reason'], 'diagnostic'), 'Partial errors were not reported separately');
$retryPath = $archive->path(100, 'sales', 'invoice', 6002);
recoveryTestCheck(is_file($retryPath) && !(int)$pdo->query('SELECT COUNT(*) FROM documentarchive WHERE clientid = 100 AND documentid = 6002')->fetchColumn(), 'Failed registration lost the verified copy or committed a failed insert');
$registeredAfterPartial = $pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$pdo->exec('DROP TRIGGER invoice_batch_fail'); $pdo->exec('UPDATE invoice SET total = 119 WHERE id = 6001'); unlink($diagnosticPath);
$retry = invoiceBatchCli($apply)['json'];
recoveryTestCheck($retry['summary'] === ['already_archived' => 24, 'registered' => 3], 'Same confirmed plan did not safely resume the failed subset');
foreach ($registeredAfterPartial as $old) recoveryTestCheck($pdo->query('SELECT * FROM documentarchive WHERE id = ' . (int)$old['id'])->fetch(PDO::FETCH_ASSOC) === $old, 'Retry rewrote an existing original or provenance');
$allRegistered = $pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
recoveryTestCheck(invoiceBatchCli($apply)['json']['summary'] === ['already_archived' => 27] && $pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $allRegistered, 'Repeated apply mutated registrations');
$imported = $pdo->query('SELECT * FROM documentarchive WHERE clientid = 100 ORDER BY documentid')->fetchAll(PDO::FETCH_ASSOC);
recoveryTestCheck(count($imported) === 27, 'Expected one existing plus 26 new originals');
foreach (array_slice($imported, 1) as $row) {
	$file = $archive->inspect($base . '/' . $row['path'], 'pdf'); $evidence = json_decode($row['parameters'], true)['recovery'];
	recoveryTestCheck($file['status'] === 'ok' && $file['sha256'] === $row['sha256'] && $file['size'] === (int)$row['filesize'] && DEEC_DocumentArchive::hasRecoveryApproval($row), 'Registered original failed path/size/SHA/approval verification');
	recoveryTestCheck($evidence['selection']['original_comparison']['statement']['comparison_performed_at'] === null && $evidence['selection']['original_comparison']['confirmed_by'] === 142 && strtotime($evidence['selection']['original_comparison']['confirmed_at']) > 0 && $evidence['approval']['retrospective_customer_archive_comparison_attested'] && !$evidence['approval']['individual_original_comparison_claimed'] && $evidence['approval']['original_provenance_verified'], 'Provenance invented a comparison timestamp or lost its explicit attestation');
	recoveryTestCheck($evidence['release_authorization']['plan_sha256'] === $ready['report_sha256'] && $evidence['release_authorization']['database_and_file_backup_confirmed'] && $evidence['release_authorization']['declaration_sha256'] === hash_file('sha256', $declarationPath) && $evidence['selection']['sha256'] === hash('sha256', $invoiceBatchPdfs['correct/' . $evidence['selection']['document']['document_number'] . '.pdf']), 'Attestation is not bound to the exact plan, backup, declaration and file SHA');
}
foreach ($invoiceBatchOldArchiveRows as $old) recoveryTestCheck($pdo->query('SELECT * FROM documentarchive WHERE id = ' . (int)$old['id'])->fetch(PDO::FETCH_ASSOC) === $old, 'Batch changed an existing archived original, including 109860');
foreach ($invoiceBatchOldFiles as $path => $state) {
	if (basename($path) === 'selection.json' && $path !== $invoiceBatchDirectories[6000] . '/selection.json') {
		$history = dirname($path) . '/selections/' . $state[0] . '.json';
		recoveryTestCheck(is_file($history) && hash_file('sha256', $history) === $state[0], 'Batch discarded the previous manual selection');
	} else recoveryTestCheck([hash_file('sha256', $path), filemtime($path)] === $state, 'Batch changed an existing original or proof: ' . $path);
}
foreach ($invoiceBatchBusiness as $table => $rows) recoveryTestCheck($pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $rows, 'Invoice batch changed stored business/email values');
recoveryTestCheck(glob($invoiceBatchRoot . '/runs/.invoice-batch-*') === [], 'Private temporary PDF files were not cleaned');
echo 'PASS: ' . ($checks - $batchCheckStart) . " invoice-original batch checks; 27 files, 26 new registrations, 109860 protected, declaration/plan/backup binding, tenant/permission/ZIP guards and transactional retry\n";
