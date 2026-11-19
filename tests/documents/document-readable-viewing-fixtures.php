<?php

// Included only by the disposable recovery database regression.
$readableStart = $checks;
$readableDb = recoveryBoot(migrationConfig($work . '/database.ini', 'production') + ['unix_socket' => $socket], $base, 7, 42);
$readableCases = [];
foreach ([29 => 28569, 620 => 28920, 621 => 28621, 622 => 28622, 623 => 28623, 624 => null, 625 => 28625, 626 => 28626, 628 => 28628] as $id => $number) {
	$document = array_replace($pdo->query('SELECT * FROM quote WHERE id = 601')->fetch(PDO::FETCH_ASSOC), ['id' => $id, 'quoteid' => $number, 'quotedate' => '2010-02-03', 'clientid' => 7, 'state' => 105, 'completed' => 1, 'cancelled' => 0, 'filename' => 'saved-quote-' . $number . '.pdf', 'billingname1' => 'Saved recipient ' . $number, 'billingstreet' => 'Historical street 18', 'billingcity' => 'Berlin', 'billingcountry' => 'DE', 'subtotal' => 100, 'taxes' => 19, 'total' => 119, 'notes' => 'PRIVATE_NOTE_SHOULD_NOT_APPEAR', 'templateid' => 999999]);
	if ($id === 29) { $document['subtotal'] = 250; $document['taxes'] = 47.5; $document['total'] = 297.5; }
	if ($id === 620) { $document['subtotal'] = 195; $document['taxes'] = 37.05; $document['total'] = 232.05; }
	if ($id === 621) $document['total'] = 200;
	if ($id === 622) foreach (['billingname1', 'billingstreet', 'billingcity', 'billingcountry'] as $field) $document[$field] = '';
	if ($id === 623) { $document['cancelled'] = 1; $document['state'] = 106; }
	if ($id === 624) { $document['quotedate'] = null; $document['currency'] = ''; $document['language'] = ''; $document['subtotal'] = null; $document['taxes'] = null; $document['total'] = null; }
	if ($id === 625) { $document['subtotal'] = -74.11; $document['taxes'] = -14.08; $document['total'] = -88.19; }
	if ($id === 626) { $document['subtotal'] = 0; $document['taxes'] = 0; $document['total'] = 0; }
	if ($id === 628) { $document['subtotal'] = 3000; $document['taxes'] = 570; $document['total'] = 3570; }
	recoveryTestInsert('quote', $document);
	foreach (range(1, $id === 29 ? 2 : ($id === 628 ? 30 : 1)) as $line) {
		$position = ['id' => 700000 + $id * 100 + $line, 'clientid' => 7, 'parentid' => $id, 'ordering' => $line, 'price' => 100, 'quantity' => 1, 'taxrate' => 19, 'total' => 0, 'masterid' => 0, 'currency' => $document['currency'], 'uom' => 'pcs', 'title' => 'Saved article ' . $line, 'description' => '<p>Saved description</p><p>Readable line details</p>'];
		if ($id === 29) { $position['price'] = $line === 1 ? 120 : 10; $position['quantity'] = $line === 1 ? 2 : 1; }
		if ($id === 620) { $position['price'] = 65; $position['quantity'] = 3; }
		if ($id === 624) { $position['price'] = null; $position['quantity'] = null; $position['taxrate'] = null; $position['total'] = null; }
		if ($id === 625) $position['price'] = -74.11;
		if ($id === 626) $position['price'] = 0;
		if ($id === 628) $position['description'] = str_repeat('Saved long product description. ', 20);
		recoveryTestInsert('quotepos', $position);
	}
	$readableCases[$id] = ['module' => 'sales', 'controller' => 'quote', 'id' => $id];
}
$legacyStates = []; $legacyResults = [];
foreach ([29, 620] as $id) {
	$diagnostic = (new DEEC_Pdf())->reconstructDiagnostic($id, 'sales', 'quote');
	$identity = ['clientid' => 7, 'module' => 'sales', 'controller' => 'quote', 'id' => $id];
	$directory = $base . '/files/documents/.viewing-copies/7/sales/quote/' . $id . '/' . $diagnostic['snapshot_sha256'] . '/de_DE'; reviewDirectory($directory);
	$sha = hash('sha256', $diagnostic['bytes']);
	reviewWrite($directory . '/' . $sha . '.pdf', $diagnostic['bytes']);
	reviewWrite($directory . '/copy.json', reviewJson(['schema_version' => 1, 'identity' => $identity, 'snapshot_sha256' => $diagnostic['snapshot_sha256'], 'locale' => 'de_DE', 'status' => 'historical_diagnostic_viewing_copy', 'sha256' => $sha, 'size' => strlen($diagnostic['bytes']), 'original' => false, 'archive_registration_allowed' => false]));
	foreach ([$directory . '/' . $sha . '.pdf', $directory . '/copy.json'] as $path) $legacyStates[$path] = [hash_file('sha256', $path), filemtime($path)];
	$legacyResults[$id] = ['snapshot_sha256' => $diagnostic['snapshot_sha256'], 'sha256' => $sha, 'size' => strlen($diagnostic['bytes'])];
}
$readableBefore = [];
foreach ($viewingTables as $table) $readableBefore[$table] = $pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$readableFilesBefore = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base . '/files', FilesystemIterator::SKIP_DOTS)) as $file) if ($file->isFile()) $readableFilesBefore[$file->getPathname()] = [hash_file('sha256', $file->getPathname()), filemtime($file->getPathname())];
$readablePrivate = $work . '/readable'; reviewDirectory($readablePrivate);
reviewWrite($readablePrivate . '/scope.json', reviewJson(['documents' => [$readableCases[29], $readableCases[620]]]));
$readableOptions = ['verification' => 'local', 'http-base' => null, 'cookie-file' => null, 'scope' => $readablePrivate . '/scope.json'];
$readablePlan = availabilityTestCli($readableOptions);
recoveryTestCheck($readablePlan['coverage']['classifications'] === ['copy_can_be_generated' => 2], 'Legacy diagnosis was served as the preferred user copy');
$legacyResume = '';
foreach (availabilityReportRows($readablePlan['report']) as $row) {
	if ($row['kind'] === 'header') { $row['action'] = 'apply'; $row['plan_sha256'] = $readablePlan['report_sha256']; }
	if ($row['kind'] === 'document') $row = array_replace($row, $legacyResults[$row['identity']['id']], ['availability' => 'copy_available', 'classification' => 'copy_available', 'local_verified' => true, 'local_checked_at' => '2026-01-01T00:00:00Z']);
	if ($row['kind'] !== 'summary') $legacyResume .= json_encode($row, JSON_UNESCAPED_SLASHES) . "\n";
}
reviewWrite($readablePrivate . '/old-apply.jsonl', $legacyResume);
$readableApply = $readableOptions + ['action' => 'apply', 'plan' => $readablePlan['report'], 'confirm-plan' => $readablePlan['report_sha256']];
$readableLimited = availabilityTestCli($readableApply + ['resume' => $readablePrivate . '/old-apply.jsonl', 'limit' => 1], 2);
recoveryTestCheck($readableLimited['coverage']['local_verifications_reused'] === 0 && $readableLimited['coverage']['local_verified'] === 1 && $readableLimited['coverage']['pending'] === 1, 'Old-renderer K5 evidence skipped the new user view');
$readableComplete = availabilityTestCli($readableApply + ['resume' => $readableLimited['report']]);
recoveryTestCheck($readableComplete['coverage']['every_scoped_document_local_verified'] && $readableComplete['coverage']['local_verifications_reused'] === 1, 'Versioned K5 backfill did not resume');
$httpLog = fopen($work . '/readable-http.log', 'wb');
$httpServer = proc_open([PHP_BINARY, '-d', 'error_reporting=8191', '-S', $httpAddress, __DIR__ . '/document-recovery-download-fixture.php'], [0 => ['pipe', 'r'], 1 => $httpLog, 2 => $httpLog], $httpPipes, null, array_replace(getenv(), ['DEWAWI_REVIEW_TEST_SOCKET' => $socket])); fclose($httpPipes[0]);
try {
	for ($i = 0; $i < 100; $i++) { $ready = @stream_socket_client('tcp://' . $httpAddress); if ($ready) { fclose($ready); break; } usleep(50000); }
	foreach ($readableCases as $id => $identity) {
		$download = recoveryTestDownload($identity + ['action' => 'download']);
		$pages = viewingCheckMarks($download, 'KOPIE', $id === 628 ? 2 : 1); $text = implode('', $pages);
		recoveryTestCheck(str_contains($download['headers'], DEEC_DocumentReview::VIEWING_STATUS) && str_contains($text, 'Gespeicherte Belegsummen') && str_contains($text, 'Saved article'), 'Readable copy lacks document lines/totals');
		foreach (['Gespeicherte Daten:', 'Diagnosebeleg', '"clientid"', '"createdby"', 'pricerulepos', 'PRIVATE_NOTE_SHOULD_NOT_APPEAR'] as $secret) recoveryTestCheck(!str_contains($text, $secret), 'Normal PDF exposed internal data: ' . $secret);
		$preview = recoveryTestDownload($identity + ['action' => 'preview']);
		recoveryTestCheck($preview['code'] === 200 && $preview['bytes'] === $download['bytes'] && str_contains($preview['headers'], 'inline;') && str_contains($download['headers'], 'attachment;'), 'Normal preview/download differ or lost their disposition');
		if (in_array($id, [29, 620], true)) {
			$number = $id === 29 ? '28569' : '28920';
			recoveryTestCheck(str_contains($text, $number) && str_contains($text, 'Saved recipient ' . $number) && str_contains($text, 'Historical street 18') && str_contains($text, '2010-02-03') && str_contains($text, 'Readable line details'), 'Reference document lost its saved header/recipient/description');
			recoveryTestCheck(str_contains($text, 'rechnerisch abgeleitet') && str_contains($text, 'gespeicherte Positionsbetrag weicht') && !str_contains($text, 'Belegsummen stimmen nicht'), 'Zero stored position amounts or matching header were misrepresented');
			recoveryTestCheck(str_contains($text, $id === 29 ? '297,50' : '232,05') && str_contains($text, $id === 29 ? '240,00' : '195,00'), 'Saved header or derived line amount differs');
			$recordPath = glob($base . '/files/documents/.viewing-copies/7/sales/quote/' . $id . '/*/de_DE/v2/copy.json')[0]; $record = recoveryJsonFile($recordPath);
			recoveryTestCheck($record['renderer_version'] === 2 && $record['status'] === DEEC_DocumentReview::VIEWING_STATUS && $record['original'] === false && $record['archive_registration_allowed'] === false && DEEC_DocumentReview::isViewingCopy(7, $record['sha256']) && DEEC_DocumentReview::isViewingCopy(7, $legacyResults[$id]['sha256']), 'Versioned/legacy viewing copies lost original promotion protection');
			$receiptBytes = file_get_contents($recordPath); $record['renderer_version'] = 1; file_put_contents($recordPath, reviewJson($record));
			recoveryTestCheck(recoveryTestDownload($identity + ['action' => 'download'])['code'] === 404, 'Wrong renderer version receipt was accepted'); file_put_contents($recordPath, $receiptBytes);
		}
		if ($id === 621) recoveryTestCheck(str_contains($text, '200,00') && str_contains($text, 'Belegsummen stimmen nicht'), 'Conflicting saved header total was hidden or recalculated');
		if ($id === 622 || $id === 624) recoveryTestCheck(str_contains($text, 'Nicht gespeichert / unvollständig'), 'Missing recipient or amounts were invented');
		if ($id === 623) recoveryTestCheck(str_contains($text, 'Storniert'), 'Cancelled state was concealed');
		if ($id === 624) recoveryTestCheck(str_contains($text, 'Währung nicht gespeichert') && str_contains($text, 'rechnerischer Vergleich'), 'Missing calculation basis was hidden');
		if ($id === 625) recoveryTestCheck(str_contains($text, '-88,19') && str_contains($text, '-74,11'), 'Negative saved/derived amounts changed sign');
		if ($id === 626) recoveryTestCheck(!str_contains($text, 'gespeicherte Positionsbetrag weicht'), 'Legitimate zero line incorrectly flagged');
		$foreignDownload = recoveryTestDownload($identity + ['action' => 'download'], 43); $foreignPreview = recoveryTestDownload($identity + ['action' => 'preview'], 44);
		recoveryTestCheck(in_array($foreignDownload['code'], [302, 404], true) && in_array($foreignPreview['code'], [302, 404], true) && !str_contains($foreignDownload['headers'], 'application/pdf') && !str_contains($foreignPreview['headers'], 'application/pdf'), 'Readable view crossed tenant boundaries for ' . $id . ': ' . $foreignDownload['code'] . '/' . $foreignPreview['code']);
	}
	$permissionJson = $pdo->query('SELECT sales FROM permission WHERE userid = 42')->fetchColumn();
	$pdo->prepare('UPDATE permission SET sales = ? WHERE userid = 42')->execute([json_encode(['quote' => ['view']])]);
	$diagnosticReceipt = glob($review . '/published-diagnostics/sales-quote-200/*.json')[0];
	$diagnosticQuery = ['module' => 'sales', 'controller' => 'quote', 'id' => 200, 'sha256' => basename($diagnosticReceipt, '.json')];
	recoveryTestCheck(recoveryTestDownload($diagnosticQuery)['code'] === 404 && recoveryTestDownload($readableCases[29] + ['action' => 'download'])['code'] === 200, 'Technical diagnostic authorization was not separated from ordinary viewing');
	$pdo->prepare('UPDATE permission SET sales = ? WHERE userid = 42')->execute([$permissionJson]);
	recoveryTestCheck(recoveryTestDownload($diagnosticQuery)['code'] === 200, 'Authorized internal diagnostic is no longer available');
} finally { proc_terminate($httpServer); proc_close($httpServer); fclose($httpLog); }
try { (new DEEC_Pdf())->reconstruct(621, 'sales', 'quote'); throw new LogicException('Readable-view tolerance weakened original reconstruction validation'); }
catch (RuntimeException $error) { recoveryTestCheck(str_contains($error->getMessage(), 'Stored document amounts'), 'Original reconstruction failed unexpectedly'); }
foreach ($readableBefore as $table => $rows) recoveryTestCheck($pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $rows, 'Readable view changed stored business/archive data: ' . $table);
foreach ($readableFilesBefore as $path => $state) recoveryTestCheck([hash_file('sha256', $path), filemtime($path)] === $state, 'Readable view changed an existing original/copy/receipt');
foreach ($legacyStates as $path => $state) recoveryTestCheck([hash_file('sha256', $path), filemtime($path)] === $state, 'New renderer replaced a legacy diagnostic copy or receipt');
foreach ($readableCases as $id => $identity) recoveryTestCheck(!file_exists((new DEEC_DocumentArchive($base))->path(7, 'sales', 'quote', $id)), 'Readable copy created an original');
$readableDb->closeConnection();
echo 'PASS: ' . ($checks - $readableStart) . " readable viewing-copy checks; reference IDs, stored totals, marked calculations, private versioned copies, K5 resume, HTTP and internal diagnostics\n";
