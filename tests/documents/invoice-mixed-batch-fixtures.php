<?php

// Included after the original-only fixtures in the disposable recovery regression.
if (!isset($pdo, $work, $invoiceBatchRoot, $invoiceBatchPdfs) || !preg_match('~^/tmp/dewawi-document-recovery-db-[a-zA-Z0-9_]+$~D', $work)) throw new RuntimeException('Isolated mixed batch fixtures are required');
$mixedCheckStart = $checks; $mixedRun = ['clientid' => 100, 'base' => $base, 'documents' => []];
$mixedDirectories = []; $mixedIdentities = []; $mixedEntries = []; $reconstructionRun = $mixedRun;
recoveryTestInsert('template', array_replace($pdo->query('SELECT * FROM template WHERE id = 1')->fetch(PDO::FETCH_ASSOC), ['id' => 100, 'clientid' => 100]));
for ($i = 0; $i < 26; $i++) {
	$id = 7000 + $i; $number = 200000 + $i;
	$document = array_replace($firstDocument, ['id' => $id, 'invoiceid' => $number, 'filename' => 'invoice-' . $number . '.pdf', 'templateid' => 100]);
	recoveryTestInsert('invoice', $document);
	$position = $pdo->query('SELECT * FROM invoicepos WHERE parentid = 6000 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
	recoveryTestInsert('invoicepos', array_replace($position, ['id' => 10000 + $i, 'parentid' => $id]));
	$identity = ['module' => 'sales', 'controller' => 'invoice', 'id' => $id, 'clientid' => 100, 'document_number' => (string)$number];
	$directory = $invoiceBatchRoot . '/sales-invoice-' . $id . '-' . $number;
	reviewDirectory($directory . '/candidates'); reviewDirectory($directory . '/external');
	reviewWrite($directory . '/manifest.json', reviewJson(['selected' => $identity + ['reported_reasons' => ['original_missing']], 'candidates' => [], 'sources' => []]));
	reviewWrite($directory . '/selection.json', reviewJson(['document' => $identity, 'decision' => 'pending']));
	$entry = ['key' => reviewKey($identity), 'manifest' => basename($directory) . '/manifest.json'];
	$mixedRun['documents'][] = $entry; if ($i < 17) $reconstructionRun['documents'][] = $entry;
	$mixedDirectories[$id] = $directory; $mixedIdentities[$id] = $identity;
	if ($i >= 17) $mixedEntries['correct/' . $number . '.pdf'] = invoiceBatchFixturePdf($number, 'Recovered original mixed fixture');
}
$mixedRunPath = $invoiceBatchRoot . '/runs/mixed-run.json'; reviewWrite($mixedRunPath, reviewJson($mixedRun));
$reconstructionRunPath = $invoiceBatchRoot . '/runs/mixed-reconstruction-fixtures.json'; reviewWrite($reconstructionRunPath, reviewJson($reconstructionRun));
$prepareOptions = ['client' => 100, 'operator-user' => 142, 'review' => $invoiceBatchRoot, 'run' => $reconstructionRunPath];
// Generate real, validated fixture candidates before invoking the batch importer.
$preparedMixed = recoveryTestCli($prepareOptions + ['action' => 'prepare'])['json'];
$publishedMixed = recoveryTestCli($prepareOptions + ['action' => 'publish-reviewable', 'confirm-client' => 100, 'database-values-verified' => true, 'backup-verified' => true])['json'];
$mixedOriginalFiles = [];
foreach ($reconstructionRun['documents'] as $entry) {
	$id = (int)basename($entry['key']); $recordPath = glob($mixedDirectories[$id] . '/reconstructions/*.json')[0]; $proof = recoveryJsonFile($recordPath);
	$mixedEntries['correct/' . $mixedIdentities[$id]['document_number'] . '.pdf'] = file_get_contents($mixedDirectories[$id] . '/candidates/' . $proof['sha256'] . '.pdf');
	foreach ([$recordPath, $mixedDirectories[$id] . '/candidates/' . $proof['sha256'] . '.pdf', DEEC_DocumentReview::publicationDirectory($invoiceBatchRoot, $mixedIdentities[$id]) . '/' . $proof['sha256'] . '.json'] as $path) $mixedOriginalFiles[$path] = [hash_file('sha256', $path), filemtime($path)];
}
foreach ([109856, 109954] as $number) $mixedEntries['correct/' . $number . '.pdf'] = invoiceBatchFixturePdf($number, 'Unassigned mixed fixture');
recoveryTestInsert('invoice', array_replace($firstDocument, ['id' => 7100, 'clientid' => 101, 'invoiceid' => 109954]));
$invoiceBatchZip = $work . '/mixed-correct.zip'; invoiceBatchFixtureZip($invoiceBatchZip, $mixedEntries);
$mixedOptions = ['run' => $mixedRunPath];
$mixedBefore = $pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$mixedBusiness = [];
foreach (['invoice', 'invoicepos', 'invoiceposset', 'pricerulepos', 'contact', 'emailattachment', 'emailmessage', 'emailmessageattachment'] as $table) $mixedBusiness[$table] = $pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$initial = invoiceBatchCli($mixedOptions)['json']; $mixedPlan = recoveryJsonFile($initial['report']);
$mixedTemplate = recoveryJsonFile($initial['declaration_template']);
recoveryTestCheck(count($mixedPlan['rows']) === 28 && $initial['summary'] === ['blocked' => 28] && !in_array('109860', array_column($mixedPlan['rows'], 'document_number'), true), 'Mixed plan changed its exact 28-file scope');
recoveryTestCheck(count(array_filter($mixedPlan['rows'], static fn(array $row) => $row['release_type'] === 'reviewed_reconstruction_duplicate')) === 17 && count(array_filter($mixedPlan['rows'], static fn(array $row) => $row['release_type'] === 'verified_recovered_original')) === 9, 'Plan did not distinguish the 17 reconstructions and nine original files');
foreach ($mixedTemplate['documents'] as &$statement) {
	if ($statement['document'] === null) continue;
	$statement['identity_verified'] = $statement['customer_archive_compared'] = $statement['business_values_compared'] = $statement['positions_taxes_totals_match'] = true;
	$statement['unresolved_differences'] = false; $statement['comparison_reference'] = 'Actual synthetic customer archive comparison of this exact PDF and saved business basis';
	if ($statement['release_type'] === 'reviewed_reconstruction_duplicate') $statement['historical_content_match'] = true;
	else { $statement['original_provenance_verified'] = true; $statement['provenance'] = 'Recovered customer archive original fixture ' . $statement['entry']; }
} unset($statement);
$mixedDeclaration = $invoiceBatchRoot . '/runs/mixed-declaration.json'; reviewWrite($mixedDeclaration, reviewJson($mixedTemplate));
$mixedDeclared = $mixedOptions + ['declaration' => $mixedDeclaration];
$readyMixed = invoiceBatchCli($mixedDeclared)['json']; $mixedPlan = recoveryJsonFile($readyMixed['report']);
recoveryTestCheck($readyMixed['summary'] === ['blocked' => 2, 'register' => 26], 'Mixed declaration did not release exactly 26 matching documents');
foreach ($mixedPlan['rows'] as $row) if (in_array($row['document_number'], ['109856', '109954'], true)) recoveryTestCheck($row['identity'] === null && $row['proposed_action'] === 'blocked', 'Unknown or foreign named invoice was mapped automatically');
$mixedApply = $mixedDeclared + ['action' => 'apply', 'plan' => $readyMixed['report'], 'confirm-plan' => $readyMixed['report_sha256'], 'backup-verified' => true, 'backup-reference' => 'Current verified mixed-fixture database and archive backup'];
// Execute individual failure probes without importing unrelated entries.
$firstRowIndex = array_search('200000', array_column($mixedPlan['rows'], 'document_number'), true);
$only = invoiceBatchFixtureSubset($mixedPlan, $firstRowIndex, $invoiceBatchRoot . '/runs/mixed-first-only.json');
$onlyApply = array_replace($mixedApply, $only); $sha = $mixedPlan['rows'][$firstRowIndex]['sha256'];
$proofPath = $mixedDirectories[7000] . '/reconstructions/' . $sha . '.json';
$publicationPath = DEEC_DocumentReview::publicationDirectory($invoiceBatchRoot, $mixedIdentities[7000]) . '/' . $sha . '.json';
foreach ([$proofPath, $publicationPath] as $path) {
	$bytes = file_get_contents($path); $mtime = filemtime($path); file_put_contents($path, $bytes . "\n");
	$failed = invoiceBatchCli($onlyApply, false)['json'];
	recoveryTestCheck(recoveryJsonFile($failed['report'])['rows'][$firstRowIndex]['status'] === 'failed', 'Changed proof/publication bypassed its plan binding');
	file_put_contents($path, $bytes); touch($path, $mtime);
}
$publicationBytes = file_get_contents($publicationPath); $publicationTime = filemtime($publicationPath); unlink($publicationPath);
$unpublished = invoiceBatchCli($mixedDeclared)['json'];
recoveryTestCheck(recoveryJsonFile($unpublished['report'])['rows'][$firstRowIndex]['proposed_action'] === 'blocked', 'Unpublished reconstruction was accepted');
$unpublishedApply = invoiceBatchCli($onlyApply, false)['json']; recoveryTestCheck(recoveryJsonFile($unpublishedApply['report'])['rows'][$firstRowIndex]['status'] === 'failed', 'Apply accepted a removed publication');
reviewWrite($publicationPath, $publicationBytes); touch($publicationPath, $publicationTime);
$proofBytes = file_get_contents($proofPath); $proofTime = filemtime($proofPath); $invalidProof = json_decode($proofBytes, true); $invalidProof['validation']['displayed']['header'] = [];
file_put_contents($proofPath, reviewJson($invalidProof));
recoveryTestCheck(recoveryJsonFile(invoiceBatchCli($mixedDeclared)['json']['report'])['rows'][$firstRowIndex]['proposed_action'] === 'blocked', 'Invalid displayed-amount proof was accepted');
file_put_contents($proofPath, $proofBytes); touch($proofPath, $proofTime);
$proofBytes = file_get_contents($proofPath); $proofTime = filemtime($proofPath); unlink($proofPath);
$missingProof = recoveryJsonFile(invoiceBatchCli($mixedDeclared)['json']['report'])['rows'][$firstRowIndex];
recoveryTestCheck($missingProof['proposed_action'] === 'blocked' && $missingProof['release_type'] === 'reviewed_reconstruction_duplicate', 'Missing proof relabeled a published reconstruction as an original');
reviewWrite($proofPath, $proofBytes); touch($proofPath, $proofTime);
$pdfPath = $mixedDirectories[7000] . '/candidates/' . $sha . '.pdf'; $savedPdfPath = $mixedDirectories[7000] . '/external/test-preserved-existing.pdf'; rename($pdfPath, $savedPdfPath);
recoveryTestCheck(recoveryJsonFile(invoiceBatchCli($onlyApply, false)['json']['report'])['rows'][$firstRowIndex]['status'] === 'failed', 'Missing existing recovery PDF was silently replaced from the ZIP'); rename($savedPdfPath, $pdfPath);
$diagnosticPath = $mixedDirectories[7000] . '/diagnostics/' . str_repeat('d', 64) . '.json'; reviewDirectory(dirname($diagnosticPath)); reviewWrite($diagnosticPath, reviewJson(['identity' => $mixedIdentities[7000], 'diagnosis' => ['issues' => ['Unresolved mixed fixture issue']]]));
recoveryTestCheck(recoveryJsonFile(invoiceBatchCli($onlyApply, false)['json']['report'])['rows'][$firstRowIndex]['status'] === 'failed', 'Mixed reconstruction approval bypassed a diagnostic conflict'); unlink($diagnosticPath);
$foreignZip = $work . '/mixed-foreign-reconstruction.zip'; invoiceBatchFixtureZip($foreignZip, ['correct/200017.pdf' => $mixedEntries['correct/200000.pdf']]);
$foreignRow = recoveryJsonFile(invoiceBatchCli($mixedOptions + ['zip' => $foreignZip])['json']['report'])['rows'][0];
recoveryTestCheck($foreignRow['proposed_action'] === 'blocked' && str_contains($foreignRow['reason'], 'different or ambiguous'), 'Reconstruction of another invoice was accepted');
$pdo->exec('UPDATE invoice SET total = 120 WHERE id = 7000');
recoveryTestCheck(recoveryJsonFile(invoiceBatchCli($onlyApply, false)['json']['report'])['rows'][$firstRowIndex]['status'] === 'failed', 'Stale reconstruction data fingerprint was accepted'); $pdo->exec('UPDATE invoice SET total = 119 WHERE id = 7000');
$bad = $mixedTemplate;
foreach ($bad['documents'] as &$statement) if (($statement['document']['id'] ?? 0) === 7000) { $statement['release_type'] = 'verified_recovered_original'; $statement['original_provenance_verified'] = true; $statement['provenance'] = 'False original-file claim'; } unset($statement);
$badPath = $invoiceBatchRoot . '/runs/mixed-false-origin.json'; reviewWrite($badPath, reviewJson($bad));
recoveryTestCheck(invoiceBatchCli($mixedOptions + ['declaration' => $badPath])['json']['summary']['blocked'] === 3, 'Known reconstruction was relabeled as a recovered original');
$bad = $mixedTemplate;
foreach ($bad['documents'] as &$statement) if (($statement['document']['id'] ?? 0) === 7017) { $statement['release_type'] = 'reviewed_reconstruction_duplicate'; $statement['original_provenance_verified'] = false; $statement['provenance'] = ''; $statement['historical_content_match'] = true; } unset($statement);
$badPath = $invoiceBatchRoot . '/runs/mixed-phantom-reconstruction.json'; reviewWrite($badPath, reviewJson($bad));
recoveryTestCheck(invoiceBatchCli($mixedOptions + ['declaration' => $badPath])['json']['summary']['blocked'] === 3, 'An independent PDF claimed reconstruction provenance without a proof');
foreach (['historical_content_match', 'customer_archive_compared', 'business_values_compared'] as $flag) {
	$bad = $mixedTemplate; foreach ($bad['documents'] as &$statement) if (($statement['document']['id'] ?? 0) === 7000) $statement[$flag] = false; unset($statement);
	$path = $invoiceBatchRoot . '/runs/mixed-missing-' . $flag . '.json'; reviewWrite($path, reviewJson($bad));
	recoveryTestCheck(invoiceBatchCli($mixedOptions + ['declaration' => $path])['json']['summary']['blocked'] === 3, 'Missing explicit reconstruction comparison was accepted: ' . $flag);
}
recoveryTestCheck($pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $mixedBefore, 'Mixed planning/failure checks changed existing originals');
$result = invoiceBatchCli($mixedApply, false)['json'];
recoveryTestCheck($result['summary'] === ['blocked' => 2, 'registered' => 26], 'Mixed apply did not report the unresolved two numbers separately');
$registeredMixed = $pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
recoveryTestCheck(invoiceBatchCli($mixedApply, false)['json']['summary'] === ['blocked' => 2, 'already_archived' => 26] && $pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $registeredMixed, 'Mixed retry rewrote existing archive metadata');
foreach ($mixedIdentities as $id => $identity) {
	$row = $pdo->query('SELECT * FROM documentarchive WHERE clientid = 100 AND controller = \'invoice\' AND documentid = ' . $id)->fetch(PDO::FETCH_ASSOC);
	$recovery = json_decode($row['parameters'], true)['recovery']; $reconstructed = $id < 7017;
	recoveryTestCheck($recovery['selection']['origin']['category'] === ($reconstructed ? 'reviewed_reconstruction_duplicate' : 'verified_recovered_original') && $recovery['selection']['origin']['kind'] === ($reconstructed ? 'reconstructed' : 'historical_pdf') && $recovery['approval']['original_provenance_verified'] === !$reconstructed && ($recovery['reconstruction'] !== null) === $reconstructed, 'Archived mixed provenance was conflated');
	recoveryTestCheck($recovery['selection']['original_comparison']['statement']['comparison_performed_at'] === null && $recovery['approval']['individual_original_comparison_claimed'] === false && hash_file('sha256', $base . '/' . $row['path']) === $row['sha256'], 'Mixed comparison invented time or changed PDF bytes');
	if ($reconstructed) recoveryTestCheck($recovery['selection']['candidate_path'] === 'candidates/' . $row['sha256'] . '.pdf' && glob($mixedDirectories[$id] . '/external/batch-original-*.pdf') === [], 'Reconstruction did not reuse its existing recovery PDF');
}
foreach ($mixedOriginalFiles as $path => $state) recoveryTestCheck([hash_file('sha256', $path), filemtime($path)] === $state, 'Batch changed an existing reconstruction or proof');
foreach ($mixedBefore as $row) recoveryTestCheck($pdo->query('SELECT * FROM documentarchive WHERE id = ' . (int)$row['id'])->fetch(PDO::FETCH_ASSOC) === $row, 'Mixed import changed an existing archive original');
foreach ($mixedBusiness as $table => $rows) recoveryTestCheck($pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $rows, 'Mixed batch changed stored business/email data');
// Export the newly archived mixed batch through the real GoBD exporter.
$pdo->exec('UPDATE user SET admin = 1 WHERE id = 142');
$rights = [];
foreach (DEEC_DocumentArchive::documents() as $module => $controllers) $rights[$module] = json_encode(array_fill_keys($controllers, ['view', 'edit']));
$pdo->prepare('UPDATE permission SET sales = ?, purchases = ? WHERE userid = 142')->execute([$rights['sales'], $rights['purchases']]);
$exportDb = recoveryBoot(migrationConfig($work . '/database.ini', 'production') + ['unix_socket' => $socket], $base, 100, 142);
$destination = $base . '/files/export/1/0/100/'; reviewDirectory(rtrim($destination, '/'));
$package = (new DEEC_Export_Gobd($exportDb, 100, 142))->export($destination, '2010-01-01', '2010-12-31', true);
$zip = new ZipArchive(); $zip->open($destination . $package);
$stream = $zip->getStream('document_files.csv'); $columns = fgetcsv($stream, 0, ';', '"', ''); $origins = [];
while (($values = fgetcsv($stream, 0, ';', '"', '')) !== false) {
	$row = array_combine($columns, $values);
	if ($row['controller'] !== 'invoice' || !isset($mixedIdentities[(int)$row['documentid']])) continue;
	$origin = (int)$row['documentid'] < 7017 ? 'reviewed_reconstruction_duplicate' : 'verified_recovered_original';
	recoveryTestCheck($row['pdf_supplied'] === '1' && $row['pdf_origin'] === $origin && hash('sha256', $zip->getFromName($row['pdf_path'])) === hash('sha256', $mixedEntries['correct/' . $row['document_number'] . '.pdf']), 'External GoBD delivery misrepresented mixed provenance or bytes');
	$origins[$origin] = ($origins[$origin] ?? 0) + 1;
}
fclose($stream);
recoveryTestCheck($origins === ['reviewed_reconstruction_duplicate' => 17, 'verified_recovered_original' => 9] && str_contains($zip->getFromName('readme.txt'), 'not unchanged historical original files'), 'GoBD omitted mixed archive PDFs or concealed duplicate provenance');
$zip->close(); $exportDb->closeConnection();
echo 'PASS: ' . ($checks - $mixedCheckStart) . " mixed invoice batch checks; 17 existing reconstructions, nine recovered originals, two blocked numbers and explicit GoBD provenance\n";
