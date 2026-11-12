<?php

// Included only by the disposable recovery database regression.
if (!isset($pdo, $work, $review, $base) || !preg_match('~^/tmp/dewawi-document-recovery-db-[a-zA-Z0-9_]+$~D', $work)) throw new RuntimeException('Isolated recovery fixtures are required');
$diagnosticRun = ['clientid' => 7, 'base' => $base, 'documents' => [$run['documents'][0]]];
$diagnosticIdentities = [];
$cases = [];
$caseId = 200;
foreach (DEEC_DocumentArchive::documents() as $module => $controllers) foreach ($controllers as $controller) $cases[] = [$module, $controller, $caseId++];
$cases[] = ['sales', 'invoice', 208]; $cases[] = ['sales', 'invoice', 209]; $cases[] = ['sales', 'quote', 211];
foreach ($cases as [$module, $controller, $id]) {
	$source = $pdo->query('SELECT * FROM `' . $controller . '` WHERE clientid = 7 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
	$document = array_replace($source, ['id' => $id, $controller . 'id' => 9000 + $id, $controller . 'date' => '2010-01-02', 'currency' => 'EUR', 'contactid' => 12, 'billingname1' => 'Saved diagnostic buyer', 'billingstreet' => 'Saved street', 'billingcity' => 'Berlin', 'billingcountry' => 'DE', 'subtotal' => 100, 'taxes' => 19, 'total' => 119, 'filename' => 'saved-' . $id . '.pdf']);
	$position = ['id' => 2000 + $id, 'parentid' => $id, 'clientid' => 7, 'title' => 'Saved diagnostic position ' . $id, 'price' => 100, 'quantity' => 1, 'taxrate' => 19, 'total' => 119, 'masterid' => 0, 'currency' => 'EUR'];
	if ($id === 200) $position['price'] = null;
	if ($id === 202) { $document['contactid'] = null; $document['billingstreet'] = ''; }
	if ($id === 203) { $document[$controller . 'id'] = null; $document[$controller . 'date'] = null; $document['currency'] = ''; }
	if (in_array($id, [204, 209], true)) $document['total'] = 200;
	if ($id === 206) $document['total'] = 119.11;
	if ($id === 207) $position['quantity'] = null;
	if ($id === 208) { $document[$controller . 'date'] = null; $position['price'] = -74.11; $position['total'] = -88.1909; $document['subtotal'] = -74.11; $document['taxes'] = -14.0809; $document['total'] = -88.1909; }
	recoveryTestInsert($controller, $document);
	if ($id !== 205) recoveryTestInsert($controller . 'pos', $position);
	if ($id === 201) recoveryTestInsert('pricerulepos', ['id' => 9001, 'parentid' => $position['id'], 'clientid' => 7, 'module' => $module, 'controller' => $controller . 'pos', 'action' => 'broken', 'amount' => 13]);
	$identity = ['module' => $module, 'controller' => $controller, 'id' => $id, 'clientid' => 7, 'document_number' => (string)$document[$controller . 'id']];
	$diagnosticIdentities[$id] = $identity;
	$directoryName = $module . '-' . $controller . '-diagnostic-' . $id;
	$directory = $review . '/' . $directoryName;
	reviewDirectory($directory . '/candidates'); reviewDirectory($directory . '/external');
	reviewWrite($directory . '/manifest.json', reviewJson(['selected' => $identity + ['reported_reasons' => ['original_missing']], 'sources' => [], 'candidates' => [], 'suggestion' => null]));
	reviewWrite($directory . '/selection.json', reviewJson(['document' => $identity, 'decision' => 'pending']));
	$diagnosticRun['documents'][] = ['key' => reviewKey($identity), 'manifest' => $directoryName . '/manifest.json'];
}
$unavailable = ['module' => 'sales', 'controller' => 'quote', 'id' => 299, 'clientid' => 7, 'document_number' => '9299'];
reviewDirectory($review . '/sales-quote-diagnostic-299/candidates'); reviewDirectory($review . '/sales-quote-diagnostic-299/external');
reviewWrite($review . '/sales-quote-diagnostic-299/manifest.json', reviewJson(['selected' => $unavailable, 'sources' => [], 'candidates' => []]));
$diagnosticRun['documents'][] = ['key' => reviewKey($unavailable), 'manifest' => 'sales-quote-diagnostic-299/manifest.json'];
reviewDirectory($review . '/runs/diagnostic-fixtures');
$diagnosticRunPath = $review . '/runs/diagnostic-fixtures/run.json';
reviewWrite($diagnosticRunPath, reviewJson($diagnosticRun));
$diagnosticOptions = ['run' => $diagnosticRunPath, 'action' => 'prepare-diagnostic', 'confirm-client' => 7];
recoveryTestCli($diagnosticOptions, false);
$preparation = recoveryTestCli(['run' => $diagnosticRunPath, 'action' => 'prepare'], false);
$failureData = recoveryJsonFile($preparation['json']['report']);
recoveryTestCheck($failureData['results']['sales/quote/211']['status'] === 'prepared', 'Control fixture did not prepare a valid reconstruction');
recoveryTestCli(['run' => $diagnosticRunPath, 'action' => 'publish-reviewable', 'only' => 'sales/quote/211', 'confirm-client' => 7, 'database-values-verified' => true]);
// A stale failure report must not rerender a now valid published PDF.
$failureData['results']['sales/quote/211'] = ['status' => 'failed', 'error' => 'Earlier failed preparation'];
$diagnosticFailuresPath = $review . '/runs/recovery-9999-diagnostic-fixtures.json';
reviewWrite($diagnosticFailuresPath, reviewJson($failureData));
$diagnosticOptions['retry-failed-from'] = 'latest';
recoveryTestCli($diagnosticOptions + ['operator-user' => 43], false);
recoveryTestCli(array_replace($diagnosticOptions, ['retry-failed-from' => $work . '/outside-report.json']), false);
// An unregistered canonical file is retained; a separate diagnostic never replaces it.
$unregisteredCanonical = (new DEEC_DocumentArchive($base))->store("%PDF-1.4\nUnregistered historical bytes retained\n%%EOF\n", 7, 'sales', 'invoice', 209, 'pdf');
$diagnosticBefore = [];
foreach (array_unique(array_merge($businessTables, ['documentarchive'])) as $table) $diagnosticBefore[$table] = $pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$oldFiles = [];
foreach ([$base . '/files/documents', $review] as $directory) foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) if ($file->isFile()) $oldFiles[$file->getPathname()] = [hash_file('sha256', $file->getPathname()), filemtime($file->getPathname())];
$diagnosticResult = recoveryTestCli($diagnosticOptions, false);
$diagnosticReport = recoveryJsonFile($diagnosticResult['json']['report']);
recoveryTestCheck($diagnosticReport['publication_summary']['available_candidates'] === 10 && $diagnosticReport['publication_summary']['diagnostic_unavailable'] === 1, 'Diagnostic batch omitted failed cases or invented an unavailable document: ' . json_encode($diagnosticReport['publication_summary']));
recoveryTestCheck(isset($diagnosticReport['publication_summary']['diagnostic_created'], $diagnosticReport['publication_summary']['diagnostic_incomplete']) && $diagnosticReport['authorization']['database_values_verified'] === false && $diagnosticReport['authorization']['original_comparison_performed'] === false, 'Diagnostic outcomes or non-attestation are incorrect');
recoveryTestCheck($diagnosticResult['json']['results']['sales/quote/1']['status'] === 'already_registered' && $diagnosticResult['json']['results']['sales/quote/211']['status'] === 'skipped', 'Diagnostic preparation changed an archived or valid prepared document');
$diagnosticStates = [];
foreach ($cases as [$module, $controller, $id]) {
	if ($id === 211) continue;
	$key = reviewKey($diagnosticIdentities[$id]); $result = $diagnosticResult['json']['results'][$key];
	$sha = $result['sha256']; $directory = $review . '/' . $module . '-' . $controller . '-diagnostic-' . $id;
	$record = recoveryJsonFile($directory . '/diagnostics/' . $sha . '.json');
	$pdfPath = $directory . '/diagnostic-candidates/' . $sha . '.pdf';
	recoveryTestCheck($record['status'] === DEEC_DocumentReview::DIAGNOSTIC_STATUS && $record['archive_approval_allowed'] === false && $record['diagnosis']['accounting_basis_verified'] === false && count($record['diagnosis']['issues']) > 0 && $record['source_failure']['report_sha256'] === hash_file('sha256', $record['source_failure']['report']), 'Diagnostic proof lost its status, issues or failure-report binding');
	$text = []; $code = 0; exec('pdftotext -layout ' . escapeshellarg($pdfPath) . ' -', $text, $code);
	$pages = array_filter(explode("\f", implode("\n", $text)), static fn(string $page): bool => trim($page) !== '');
	recoveryTestCheck($code === 0 && count($pages) > 1, 'Diagnostic PDF is unreadable or the multi-page fixture did not paginate');
	foreach ($pages as $page) recoveryTestCheck(str_contains($page, 'REKONSTRUKTION ZUR PRÜFUNG – KEIN BESTÄTIGTES ORIGINAL'), 'Diagnostic page lost its visible non-original banner');
	$diagnosticStates[$pdfPath] = [hash_file('sha256', $pdfPath), filemtime($pdfPath)];
	if ($id === 200) recoveryTestCheck($record['diagnosis']['positions_reference_without_rules'][2200]['net'] === null && $record['diagnosis']['calculated_with_valid_saved_rules'] === null, 'Missing price was replaced with zero');
	if ($id === 201) recoveryTestCheck($record['diagnosis']['saved_price_rules'][0]['valid'] === false && $record['diagnosis']['saved_price_rules'][0]['stored']['action'] === 'broken' && $record['diagnosis']['calculated_with_valid_saved_rules'] === null, 'Invalid price rule was silently accepted or removed');
	if ($id === 203) recoveryTestCheck($record['identity']['document_number'] === '' && $record['diagnosis']['header']['total']['calculated_with_valid_saved_rules'] === null, 'Missing number or currency was invented');
	if ($id === 204) recoveryTestCheck($record['diagnosis']['header']['total']['stored'] === '200.0000' && $record['diagnosis']['header']['total']['calculated_with_valid_saved_rules'] === 119 && $record['diagnosis']['header']['total']['difference'] === 81, 'Stored/calculated header values or difference were concealed');
	if ($id === 205) recoveryTestCheck($record['diagnosis']['positions_reference_without_rules'] === [] && $record['diagnosis']['calculated_with_valid_saved_rules'] === null, 'Missing positions were fabricated');
	if ($id === 208) recoveryTestCheck($record['diagnosis']['positions_reference_without_rules'][2208]['net'] === -74.11, 'Negative diagnostic position was changed');
}
recoveryTestCli($diagnosticOptions, false);
foreach ($diagnosticStates as $path => $state) recoveryTestCheck([hash_file('sha256', $path), filemtime($path)] === $state, 'Diagnostic retry rewrote an existing PDF');
$resumeIdentity = $diagnosticIdentities[203];
$resumeSha = $diagnosticResult['json']['results'][reviewKey($resumeIdentity)]['sha256'];
$resumePublication = DEEC_DocumentReview::publicationDirectory($review, $resumeIdentity, true) . '/' . $resumeSha . '.json';
unlink($resumePublication);
recoveryTestCli($diagnosticOptions + ['only' => reviewKey($resumeIdentity)]);
recoveryTestCheck(is_file($resumePublication), 'Interrupted diagnostic publication could not resume');
$blockedIdentity = $diagnosticIdentities[209];
$blockedSha = $diagnosticResult['json']['results'][reviewKey($blockedIdentity)]['sha256'];
$blockedDirectory = $review . '/sales-invoice-diagnostic-209';
reviewWrite($blockedDirectory . '/external/diagnostic-copy.pdf', file_get_contents($blockedDirectory . '/diagnostic-candidates/' . $blockedSha . '.pdf'));
$diagnosticSelection = ['document' => $blockedIdentity, 'decision' => 'selected', 'candidate_path' => 'external/diagnostic-copy.pdf', 'sha256' => $blockedSha, 'origin' => ['kind' => 'historical_pdf', 'source_path' => 'fixture copied diagnostic'], 'reason' => 'Must not promote a diagnostic'];
reviewWrite($work . '/diagnostic-decisions.jsonl', json_encode($diagnosticSelection) . "\n");
$blocked = recoveryTestCli(['run' => $diagnosticRunPath, 'action' => 'select', 'only' => reviewKey($blockedIdentity), 'decisions' => $work . '/diagnostic-decisions.jsonl'], false);
recoveryTestCheck(str_contains($blocked['json']['results'][reviewKey($blockedIdentity)]['error'], 'cannot be selected') && recoveryJsonFile($blockedDirectory . '/selection.json')['decision'] === 'pending', 'A copied diagnostic could be selected as a historical original');
recoveryTestCli(['run' => $diagnosticRunPath, 'action' => 'release', 'only' => reviewKey($blockedIdentity), 'confirm-client' => 7, 'backup-verified' => true, 'attest-previous-paper-match' => $blockedSha], false);
$diagnosticDb = recoveryBoot(migrationConfig($work . '/database.ini', 'production') + ['unix_socket' => $socket], $base, 7, 42);
$diagnosticUser = Zend_Registry::get('User'); Zend_Registry::set('User', array_replace($diagnosticUser, ['admin' => 1]));
$diagnosticExportDir = $base . '/files/export/7/0/7/'; reviewDirectory(rtrim($diagnosticExportDir, '/'));
$diagnosticExportName = (new DEEC_Export_Gobd($diagnosticDb, 7, 42))->export($diagnosticExportDir, '2010-01-01', '2010-12-31', false, true);
$diagnosticZip = new ZipArchive(); $diagnosticZip->open($diagnosticExportDir . $diagnosticExportName, ZipArchive::CHECKCONS);
$stream = $diagnosticZip->getStream('document_manifest.csv'); $fields = fgetcsv($stream, 0, ';', '"', ''); $diagnosticRows = [];
while (($values = fgetcsv($stream, 0, ';', '"', '')) !== false) { $row = array_combine($fields, $values); if ($row['status'] === DEEC_DocumentReview::DIAGNOSTIC_STATUS) $diagnosticRows[] = $row; }
fclose($stream);
recoveryTestCheck(count($diagnosticRows) === 10 && json_decode($diagnosticZip->getFromName('metadata.json'), true)['documents']['summary']['undated_diagnostic_documents'] === 2, 'Review export omitted diagnostic or explicitly undated PDFs');
foreach ($diagnosticRows as $row) recoveryTestCheck(str_starts_with($row['zip_path'], 'diagnostics/') && hash('sha256', $diagnosticZip->getFromName($row['zip_path'])) === $row['sha256'] && json_decode($row['database_confirmation'], true)['database_values_verified'] === false, 'Diagnostic export promoted a PDF or changed its bytes');
foreach ($diagnosticRows as $row) if (in_array($row['documentid'], ['203', '208'], true)) recoveryTestCheck($row['document_date'] === '' && $row['date_scope'] === 'undated_diagnostic_not_assigned_to_period', 'Unknown date was assigned to a historical period');
$diagnosticZip->close();
$originalExportName = (new DEEC_Export_Gobd($diagnosticDb, 7, 42))->export($diagnosticExportDir, '2010-01-01', '2010-12-31', true);
$originalZip = new ZipArchive(); $originalZip->open($diagnosticExportDir . $originalExportName);
for ($i = 0; $i < $originalZip->numFiles; $i++) recoveryTestCheck(!str_starts_with($originalZip->getNameIndex($i), 'diagnostics/'), 'Diagnostic entered GoBD originals'); $originalZip->close();
Zend_Registry::set('User', $diagnosticUser); $diagnosticDb->closeConnection();
// Exercise the actual protected HTTP controller and response headers.
$httpLog = fopen($work . '/diagnostic-http.log', 'wb');
$httpServer = proc_open([PHP_BINARY, '-d', 'error_reporting=8191', '-S', $httpAddress, __DIR__ . '/document-recovery-download-fixture.php'], [0 => ['pipe', 'r'], 1 => $httpLog, 2 => $httpLog], $httpPipes, null, array_replace(getenv(), ['DEWAWI_REVIEW_TEST_SOCKET' => $socket])); fclose($httpPipes[0]);
try {
	for ($i = 0; $i < 100; $i++) { $ready = @stream_socket_client('tcp://' . $httpAddress); if ($ready) { fclose($ready); break; } usleep(50000); }
	foreach ($cases as [$module, $controller, $id]) {
		if ($id === 211) continue;
		$sha = $diagnosticResult['json']['results'][reviewKey($diagnosticIdentities[$id])]['sha256'];
		$query = ['module' => $module, 'controller' => $controller, 'id' => $id, 'sha256' => $sha];
		$download = recoveryTestDownload($query);
		recoveryTestCheck($download['code'] === 200 && hash('sha256', $download['bytes']) === $sha && str_contains($download['headers'], DEEC_DocumentReview::DIAGNOSTIC_STATUS) && str_contains($download['headers'], 'private, no-store'), 'Diagnostic download failed or claimed validated reconstruction status');
		$head = recoveryTestDownload($query, 42, 'HEAD');
		recoveryTestCheck($head['code'] === 200 && $head['bytes'] === '' && recoveryTestDownload($query, 43)['code'] === 404 && recoveryTestDownload($query, 44)['code'] === 404 && recoveryTestDownload($query, null)['code'] === 401, 'Diagnostic HEAD, tenant or authentication protection regressed');
		$view = recoveryTestDownload($query + ['view' => 1]);
		recoveryTestCheck(str_contains($view['bytes'], 'Diagnosebeleg') && str_contains($view['bytes'], DEEC_DocumentReview::DIAGNOSTIC_STATUS), 'Diagnostic document view lost its label');
		if ($id === 209) foreach (['de_DE', 'en_US'] as $locale) {
			$panel = recoveryTestDownload($query + ['view' => 1, 'locale' => $locale]);
			preg_match('~<section.*?</section>~s', $panel['bytes'], $match);
			reviewWrite($work . '/diagnostic-panel-' . $locale . '.html', '<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="file://' . $root . '/css/ui.css"><body>' . $match[0] . '</body></html>');
		}
	}
	$diagnosticQuery = ['module' => 'sales', 'controller' => 'invoice', 'id' => 209, 'sha256' => $blockedSha];
	$diagnosticPdfPath = $blockedDirectory . '/diagnostic-candidates/' . $blockedSha . '.pdf';
	$pdfBytes = file_get_contents($diagnosticPdfPath);
	file_put_contents($diagnosticPdfPath, $pdfBytes . "\n");
	recoveryTestCheck(recoveryTestDownload($diagnosticQuery)['code'] === 404, 'Changed diagnostic SHA/size remained downloadable');
	file_put_contents($diagnosticPdfPath, $pdfBytes); touch($diagnosticPdfPath, $diagnosticStates[$diagnosticPdfPath][1]);
	$diagnosticProofPath = $blockedDirectory . '/diagnostics/' . $blockedSha . '.json'; $proofBytes = file_get_contents($diagnosticProofPath);
	$changedProof = json_decode($proofBytes, true); $changedProof['diagnosis']['issues'] = [];
	file_put_contents($diagnosticProofPath, reviewJson($changedProof));
	recoveryTestCheck(recoveryTestDownload($diagnosticQuery)['code'] === 404, 'Changed diagnostic warnings lost their publication binding');
	file_put_contents($diagnosticProofPath, $proofBytes);
	$pdo->exec('UPDATE invoice SET total = 201 WHERE id = 209');
	recoveryTestCheck(recoveryTestDownload($diagnosticQuery)['code'] === 404, 'Changed business fingerprint retained diagnostic access');
	$pdo->exec('UPDATE invoice SET total = 200 WHERE id = 209');
	$pdo->exec("UPDATE permission SET purchases = '{}' WHERE userid = 42");
	$purchaseQuery = ['module' => 'purchases', 'controller' => 'purchaseorder', 'id' => 207, 'sha256' => $diagnosticResult['json']['results']['purchases/purchaseorder/207']['sha256']];
	recoveryTestCheck(recoveryTestDownload($purchaseQuery)['code'] === 404, 'Diagnostic download ignored document view permission');
	$pdo->prepare('UPDATE permission SET purchases = ? WHERE userid = 42')->execute([$permissions['purchases']]);
} finally { proc_terminate($httpServer); proc_close($httpServer); fclose($httpLog); }
foreach ($diagnosticBefore as $table => $rows) recoveryTestCheck($pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $rows, 'Diagnostic reconstruction changed business/archive rows in ' . $table);
foreach ($oldFiles as $path => $state) recoveryTestCheck([hash_file('sha256', $path), filemtime($path)] === $state, 'Diagnostic preparation changed an existing original, review file or proof');
