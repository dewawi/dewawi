<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Only included by the disposable recovery database suite.
$availabilityStart = $checks; $availabilityScope = []; $availabilityCases = [];
recoveryTestInsert('contact', ['id' => 8100, 'contactid' => 999, 'clientid' => 7]);
recoveryTestInsert('contact', ['id' => 8101, 'contactid' => 999, 'clientid' => 7]);
recoveryTestInsert('contact', ['id' => 8102, 'contactid' => 998, 'clientid' => 7, 'deleted' => 1]);
recoveryTestInsert('contact', ['id' => 8103, 'contactid' => 997, 'clientid' => 8]);
$availabilityId = 8000;
foreach (DEEC_DocumentArchive::documents() as $module => $controllers) foreach ($controllers as $controller) {
	$id = ++$availabilityId;
	$document = $pdo->query('SELECT * FROM `' . $controller . '` WHERE id = ' . (600 + $id - 8000))->fetch(PDO::FETCH_ASSOC);
	$document = array_replace($document, ['id' => $id, $controller . 'id' => 980000 + $id, 'filename' => $controller . '-saved.pdf', 'contactid' => [8001 => null, 8002 => 999, 8003 => 998, 8004 => 997][$id] ?? 12]);
	if ($id === 8001) $document['contactid'] = null;
	if ($id === 8005) { $document['billingname1'] = ''; $document['subtotal'] = null; }
	if ($id === 8006) { $document[$controller . 'id'] = null; $document[$controller . 'date'] = null; $document['filename'] = '../invalid.pdf'; }
	recoveryTestInsert($controller, $document);
	$position = $pdo->query('SELECT * FROM `' . $controller . 'pos` ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
	recoveryTestInsert($controller . 'pos', array_replace($position, ['id' => $id, 'parentid' => $id, 'clientid' => 7, 'total' => 100, 'masterid' => 0]));
	$availabilityScope[] = $availabilityCases[$controller] = ['module' => $module, 'controller' => $controller, 'id' => $id];
}
$availabilityScope[] = ['module' => 'sales', 'controller' => 'quote', 'id' => 1];
$candidate = array_replace($pdo->query('SELECT * FROM quote WHERE id = 8001')->fetch(PDO::FETCH_ASSOC), ['id' => 8010, 'quoteid' => 998010, 'contactid' => 12, 'filename' => 'candidate-saved.pdf']);
recoveryTestInsert('quote', $candidate); recoveryTestInsert('quotepos', array_replace($pdo->query('SELECT * FROM quotepos WHERE id = 8001')->fetch(PDO::FETCH_ASSOC), ['id' => 8010, 'parentid' => 8010]));
$availabilityScope[] = ['module' => 'sales', 'controller' => 'quote', 'id' => 8010];
$candidatePaths = (new DEEC_DocumentArchive($base))->paths($candidate + ['module' => 'sales', 'controller' => 'quote', 'contact_record_id' => 500]);
$candidatePdf = new TCPDF(); $candidatePdf->AddPage(); $candidatePdf->Write(5, 'Synthetic historical quote 998010'); $candidateBytes = $candidatePdf->Output('', 'S');
foreach (['attachment', 'legacy', 'current'] as $source) { if (!is_dir(dirname($candidatePaths[$source]))) mkdir(dirname($candidatePaths[$source]), 0700, true); file_put_contents($candidatePaths[$source], $source === 'current' ? str_replace('998010', '998011', $candidateBytes) . "\n" : $candidateBytes); }
recoveryTestInsert('emailattachment', ['clientid' => 7, 'documentid' => 8010, 'module' => 'sales', 'controller' => 'quote', 'filename' => $candidate['filename'], 'location' => dirname($candidatePaths['attachment']), 'filesize' => strlen($candidateBytes), 'filetype' => 'application/pdf']);
$pdo->exec('UPDATE user SET admin = 1 WHERE id = 42');
foreach (DEEC_DocumentArchive::documents() as $module => $controllers) $pdo->prepare('UPDATE permission SET `' . $module . '` = ? WHERE userid = 42')->execute([json_encode(array_fill_keys($controllers, ['view', 'edit']))]);
$availabilityPrivate = $work . '/availability'; reviewDirectory($availabilityPrivate);
reviewWrite($availabilityPrivate . '/scope.json', reviewJson(['documents' => $availabilityScope]));
reviewWrite($availabilityPrivate . '/cookie', 'recovery_test_user=42');
$port = stream_socket_server('tcp://127.0.0.1:0'); $availabilityAddress = stream_socket_get_name($port, false); fclose($port);
$availabilityLog = fopen($work . '/availability-http.log', 'wb');
$availabilityServer = proc_open([PHP_BINARY, '-d', 'error_reporting=8191', '-S', $availabilityAddress, __DIR__ . '/document-recovery-download-fixture.php'], [0 => ['pipe', 'r'], 1 => $availabilityLog, 2 => $availabilityLog], $pipes, null, array_replace(getenv(), ['DEWAWI_REVIEW_TEST_SOCKET' => $socket])); fclose($pipes[0]);
function availabilityTestCli(array $changes = [], int $expected = 0, bool $defer = false): array
{
	global $root, $socket, $work, $base, $availabilityAddress, $availabilityPrivate;
	static $run = 0;
	$options = array_replace(['config' => $work . '/database.ini', 'base' => $base, 'client' => 7, 'operator-user' => 42, 'web-user' => posix_getpwuid(posix_geteuid())['name'], 'action' => 'preview', 'scope' => $availabilityPrivate . '/scope.json', 'http-base' => 'http://' . $availabilityAddress, 'output' => $availabilityPrivate . '/run-' . (++$run)], $changes);
	$command = [PHP_BINARY, '-d', 'error_reporting=8191', '-d', 'display_errors=stderr', '-d', 'pdo_mysql.default_socket=' . $socket, $root . '/tools/document-pdf-availability.php'];
	foreach ($options as $key => $value) if ($value !== null) $command[] = '--' . $key . '=' . $value;
	$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	return $defer ? [$process, $pipes] : availabilityTestFinish([$process, $pipes], $expected);
}
function availabilityTestFinish(array $worker, int $expected = 0): array
{
	[$process, $pipes] = $worker; $out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
	recoveryTestCheck($exit === $expected, 'Availability CLI failed: ' . $exit . ' ' . $error . $out);
	return $expected === 1 ? ['error' => $error] : json_decode($out, true, 512, JSON_THROW_ON_ERROR);
}
try {
	for ($i = 0; $i < 100; $i++) { $ready = @stream_socket_client('tcp://' . $availabilityAddress); if ($ready) { fclose($ready); break; } usleep(50000); }
	$beforeFiles = []; foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base . '/files', FilesystemIterator::SKIP_DOTS)) as $file) if ($file->isFile()) $beforeFiles[$file->getPathname()] = [hash_file('sha256', $file->getPathname()), $file->getMTime()];
	$beforeDb = []; foreach (array_merge($viewingTables, ['contact']) as $table) $beforeDb[$table] = $pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
	$preview = availabilityTestCli();
	recoveryTestCheck((fileperms($preview['report']) & 0777) === 0600 && (fileperms(dirname($preview['report'])) & 0777) === 0700, 'Availability reports were not private');
	recoveryTestCheck($preview['coverage']['classifications'] === ['copy_can_be_generated' => 8, 'original_available' => 1, 'original_recovery_candidate' => 1], 'Availability excluded issued documents because of original/contact problems: ' . json_encode($preview));
	$afterFiles = []; foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base . '/files', FilesystemIterator::SKIP_DOTS)) as $file) if ($file->isFile()) $afterFiles[$file->getPathname()] = [hash_file('sha256', $file->getPathname()), $file->getMTime()];
	recoveryTestCheck($beforeFiles === $afterFiles, 'Read-only availability preview created or changed PDFs');
	$apply = ['action' => 'apply', 'plan' => $preview['report'], 'confirm-plan' => $preview['report_sha256'], 'cookie-file' => $availabilityPrivate . '/cookie'];
	availabilityTestCli(array_replace($apply, ['confirm-plan' => str_repeat('0', 64)]), 1);
	availabilityTestCli(array_replace($apply, ['web-user' => 'root']), 1);
	availabilityTestCli(array_replace($apply, ['web-user' => 'nobody']), 1);
	availabilityTestCli(array_replace($apply, ['output' => $base . '/unsafe-report']), 1);
	availabilityTestCli(array_replace($apply, ['http-base' => 'https://user@example.invalid']), 1);
	chmod($availabilityPrivate . '/cookie', 0644); availabilityTestCli($apply, 1); chmod($availabilityPrivate . '/cookie', 0600);
	$pdo->exec('UPDATE user SET admin = 0 WHERE id = 42'); availabilityTestCli($apply, 1); $pdo->exec('UPDATE user SET admin = 1 WHERE id = 42');
	$limited = availabilityTestCli($apply + ['limit' => 3], 2);
	recoveryTestCheck($limited['coverage']['http_verified'] === 3 && $limited['coverage']['pending'] === 7, 'Limited backfill did not record resumable progress');
	$workers = [availabilityTestCli($apply + ['resume' => $limited['report']], 0, true), availabilityTestCli($apply + ['resume' => $limited['report']], 0, true)];
	$applied = array_map('availabilityTestFinish', $workers);
	foreach ($applied as $result) recoveryTestCheck($result['coverage']['every_scoped_document_http_verified'] === true && $result['coverage']['http_verified'] === 10 && $result['coverage']['blocked'] === 0, 'Concurrent backfill did not verify the entire scope over normal HTTP: ' . json_encode($result));
	require_once $root . '/tools/document-pdf-availability.php';
	$rows = []; foreach (availabilityReportRows($applied[0]['report']) as $row) if ($row['kind'] === 'document') $rows[reviewKey($row['identity'])] = $row;
	foreach ($availabilityScope as $identity) {
		$row = $rows[reviewKey($identity)];
		recoveryTestCheck($row['http_verified'] && (!empty($row['verification_reused']) || $row['http']['download']['sha256'] === $row['http']['preview']['sha256']), 'Download/preview returned different protected PDFs');
		if ($identity['id'] !== 1) {
			$records = glob($base . '/files/documents/.viewing-copies/7/' . reviewKey($identity) . '/*/de_DE/copy.json'); recoveryTestCheck(count($records) === 1, 'Concurrent backfill duplicated a copy');
			$record = recoveryJsonFile($records[0]); $copyPath = dirname($records[0]) . '/' . $record['sha256'] . '.pdf';
			recoveryTestCheck(fileowner($copyPath) === posix_geteuid() && (fileperms($copyPath) & 0777) === 0600 && (fileperms(dirname($copyPath)) & 0777) === 0700, 'Private copy ownership or permissions changed');
			foreach (viewingPdfPages(file_get_contents($copyPath)) as $page) recoveryTestCheck(str_contains($page, 'KOPIE'), 'Bulk copy lacks its mark on every page');
		}
	}
	$repeat = availabilityTestCli($apply + ['resume' => $applied[0]['report'], 'limit' => 1]); recoveryTestCheck($repeat['coverage']['http_verified'] === 10, 'Completed backfill could not be resumed without regeneration');
	$partialLines = file($limited['report']); array_pop($partialLines); reviewWrite($availabilityPrivate . '/interrupted.jsonl', implode('', $partialLines) . '{"kind":"document"');
	$partial = availabilityTestCli($apply + ['resume' => $availabilityPrivate . '/interrupted.jsonl']); recoveryTestCheck($partial['coverage']['http_verified'] === 10, 'Interrupted apply report could not resume safely');
	$wrongResume = str_replace('"publication_uid":' . posix_geteuid(), '"publication_uid":0', file_get_contents($applied[0]['report'])); reviewWrite($availabilityPrivate . '/wrong-resume.jsonl', $wrongResume);
	availabilityTestCli($apply + ['resume' => $availabilityPrivate . '/wrong-resume.jsonl'], 1);
	$availablePreview = availabilityTestCli(); recoveryTestCheck($availablePreview['coverage']['classifications'] === ['copy_available' => 9, 'original_available' => 1], 'Durable copies were not available after backfill');
	$recordPaths = glob($base . '/files/documents/.viewing-copies/7/sales/quote/8001/*/de_DE/copy.json'); $record = recoveryJsonFile($recordPaths[0]); $broken = dirname($recordPaths[0]) . '/' . $record['sha256'] . '.pdf'; $saved = file_get_contents($broken); file_put_contents($broken, 'Broken synthetic copy');
	$damaged = availabilityTestCli([], 2); recoveryTestCheck($damaged['coverage']['blocked'] === 1, 'Damaged stored copy was silently replaced or reported available'); file_put_contents($broken, $saved);
	chmod(dirname($broken), 0755); $unsafe = availabilityTestCli([], 2); recoveryTestCheck($unsafe['coverage']['blocked'] === 1, 'Non-private copy directory was reported available'); chmod(dirname($broken), 0700);
	$registered = $pdo->query("SELECT * FROM documentarchive WHERE documentid = 1 AND module = 'sales' AND controller = 'quote' AND format = 'pdf'")->fetch(PDO::FETCH_ASSOC);
	$pdo->prepare('UPDATE documentarchive SET sha256 = ? WHERE id = ?')->execute([str_repeat('0', 64), $registered['id']]);
	$conflict = availabilityTestCli([], 2); recoveryTestCheck($conflict['coverage']['blocked'] === 1 && $conflict['coverage']['classifications']['copy_available'] === 9, 'Invalid original was hidden by a bulk copy');
	$pdo->prepare('UPDATE documentarchive SET sha256 = ? WHERE id = ?')->execute([$registered['sha256'], $registered['id']]);
	$pdo->exec("UPDATE quote SET notes = 'Stale approved availability plan' WHERE id = 8001");
	$stale = availabilityTestCli($apply, 2); recoveryTestCheck($stale['coverage']['blocked'] === 1, 'Changed snapshot bypassed the reviewed plan'); $pdo->prepare('UPDATE quote SET notes = ? WHERE id = 8001')->execute([$beforeDb['quote'][array_search(8001, array_column($beforeDb['quote'], 'id'))]['notes']]);
	reviewWrite($availabilityPrivate . '/expired-cookie', 'recovery_test_user=43');
	$unauthorized = availabilityTestCli(array_replace($apply, ['cookie-file' => $availabilityPrivate . '/expired-cookie']), 2); recoveryTestCheck($unauthorized['coverage']['http_verified'] === 0 && $unauthorized['coverage']['blocked'] === 10, 'Foreign HTTP session was reported as successful availability');
	$negative = array_merge($availabilityScope, [['module' => 'sales', 'controller' => 'quote', 'id' => 999999], ['module' => 'sales', 'controller' => 'quote', 'id' => 609]]);
	reviewWrite($availabilityPrivate . '/negative.json', reviewJson(['documents' => $negative]));
	$blocked = availabilityTestCli(['scope' => $availabilityPrivate . '/negative.json'], 2); recoveryTestCheck($blocked['coverage']['blocked'] === 2, 'Missing or cross-tenant document was not specifically blocked');
	$all = availabilityTestCli(['scope' => 'all']); recoveryTestCheck($all['coverage']['documents'] >= 10, 'All-issued tenant scope was not assessed');
	$largePath = $availabilityPrivate . '/large.jsonl'; $largeStream = fopen($largePath, 'x+b');
	for ($i = 0; $i < 50001; $i++) fwrite($largeStream, json_encode(['kind' => 'document', 'id' => $i + 1]) . "\n"); fclose($largeStream);
	$memory = memory_get_usage(true); $largeCount = 0; foreach (availabilityReportRows($largePath) as $row) $largeCount++;
	recoveryTestCheck($largeCount === 50001 && memory_get_usage(true) - $memory < 5242880, 'Large availability JSONL was not streamed with bounded memory');
	$db = recoveryBoot(migrationConfig($work . '/database.ini', 'production') + ['unix_socket' => $socket], $base, 7, 42);
	recoveryTestCheck((new Contacts_Model_DbTable_Emailattachment())->getContactDocumentFiles(8100) === [] && (new Contacts_Model_Get())->history(999) === [], 'Ambiguous contacts exposed an unrelated issued-document association');
	$localHttpLog = file_get_contents($work . '/availability-http.log');
	$localOptions = ['verification' => 'local', 'http-base' => null, 'cookie-file' => null];
	$localPreview = availabilityTestCli($localOptions);
	$localApply = $localOptions + ['action' => 'apply', 'plan' => $localPreview['report'], 'confirm-plan' => $localPreview['report_sha256']];
	$localLimited = availabilityTestCli($localApply + ['limit' => 3], 2);
	recoveryTestCheck($localLimited['coverage']['local_verified'] === 3 && $localLimited['coverage']['pending'] === 7 && $localLimited['coverage']['http_verified'] === 0, 'Cookie-free local limit or verification counters differ');
	$localComplete = availabilityTestCli($localApply + ['resume' => $localLimited['report']]);
	recoveryTestCheck($localComplete['coverage']['every_scoped_document_local_verified'] && $localComplete['coverage']['local_verified'] === 10 && $localComplete['coverage']['local_verifications_reused'] === 3 && !$localComplete['coverage']['every_scoped_document_http_verified'], 'Existing K4 copies were not locally verified/resumed independently of HTTP');
	$localRepeat = availabilityTestCli($localApply + ['resume' => $localComplete['report'], 'limit' => 1]);
	recoveryTestCheck($localRepeat['coverage']['local_verifications_reused'] === 10 && $localRepeat['coverage']['local_verified_this_run'] === 0, 'Completed local verification could not resume');
	$localRecords = []; foreach (availabilityReportRows($localComplete['report']) as $row) if ($row['kind'] === 'document') $localRecords[] = $row;
	foreach ($localRecords as $row) recoveryTestCheck($row['local_verified'] && !$row['http_verified'] && isset($row['local_checked_at']) && !isset($row['http']) && !isset($row['http_checked_at']), 'Local result was reported as an HTTP test');
	$localPartialLines = file($localLimited['report']); array_pop($localPartialLines); reviewWrite($availabilityPrivate . '/local-interrupted.jsonl', implode('', $localPartialLines) . '{"kind":"document"');
	$localPartial = availabilityTestCli($localApply + ['resume' => $availabilityPrivate . '/local-interrupted.jsonl']);
	recoveryTestCheck($localPartial['coverage']['local_verified'] === 10, 'Interrupted local report could not resume');
	availabilityTestCli(array_replace($localApply, ['confirm-plan' => str_repeat('0', 64)]), 1);
	availabilityTestCli(array_replace($localApply, ['web-user' => 'root']), 1);
	availabilityTestCli(array_replace($localApply, ['verification' => 'invalid']), 1);
	availabilityTestCli(array_replace($localApply, ['client' => 8]), 1);
	$pdo->exec('UPDATE user SET admin = 0 WHERE id = 42'); availabilityTestCli($localApply, 1); $pdo->exec('UPDATE user SET admin = 1 WHERE id = 42');
	$localNegativePreview = availabilityTestCli($localOptions + ['scope' => $availabilityPrivate . '/negative.json'], 2);
	$localNegative = availabilityTestCli($localOptions + ['action' => 'apply', 'plan' => $localNegativePreview['report'], 'confirm-plan' => $localNegativePreview['report_sha256']], 2);
	recoveryTestCheck($localNegative['coverage']['blocked'] === 2 && $localNegative['coverage']['local_verified'] === 10 && $localNegative['coverage']['http_verified'] === 0, 'Local apply admitted missing or foreign tenant documents');

	// Another locale has no copies yet: exercise generation, missing write rights and mixed resume.
	$localEnglish = availabilityTestCli($localOptions + ['locale' => 'en_US']);
	$localEnglishApply = $localOptions + ['action' => 'apply', 'locale' => 'en_US', 'plan' => $localEnglish['report'], 'confirm-plan' => $localEnglish['report_sha256']];
	$englishRows = []; foreach (availabilityReportRows($localEnglish['report']) as $row) if ($row['kind'] === 'document') $englishRows[reviewKey($row['identity'])] = $row;
	$englishDirectory = $base . '/files/documents/.viewing-copies/7/sales/quote/8001/' . $englishRows['sales/quote/8001']['snapshot_sha256'] . '/en_US';
	mkdir($englishDirectory, 0700); chmod($englishDirectory, 0500);
	$writeDenied = availabilityTestCli($localEnglishApply + ['limit' => 1], 2);
	recoveryTestCheck($writeDenied['coverage']['blocked'] === 1 && $writeDenied['coverage']['http_verified'] === 0, 'Local backfill ignored missing directory write permission'); chmod($englishDirectory, 0700);
	$englishLimited = availabilityTestCli($localEnglishApply + ['limit' => 3], 2);
	recoveryTestCheck($englishLimited['coverage']['local_verified'] === 3 && $englishLimited['coverage']['pending'] === 7, 'Local generation limit differs');
	$englishComplete = availabilityTestCli($localEnglishApply + ['resume' => $englishLimited['report']]);
	recoveryTestCheck($englishComplete['coverage']['every_scoped_document_local_verified'] && $englishComplete['coverage']['local_verified'] === 10 && $englishComplete['coverage']['http_verified'] === 0, 'New local copies were not completely verified');
	foreach ($availabilityScope as $identity) if ($identity['id'] !== 1) {
		$englishCopies = glob($base . '/files/documents/.viewing-copies/7/' . reviewKey($identity) . '/*/en_US/copy.json');
		recoveryTestCheck(count($englishCopies) === 1, 'Local backfill did not create one receipt per new copy');
		$englishReceipt = recoveryJsonFile($englishCopies[0]); $englishPath = dirname($englishCopies[0]) . '/' . $englishReceipt['sha256'] . '.pdf';
		recoveryTestCheck(hash_file('sha256', $englishPath) === $englishReceipt['sha256'] && filesize($englishPath) === $englishReceipt['size'] && fileowner($englishPath) === posix_geteuid() && (fileperms($englishPath) & 0777) === 0600 && (fileperms(dirname($englishPath)) & 0777) === 0700, 'Local copy PDF/receipt/private permissions differ');
	}
	$englishReceiptPath = glob($englishDirectory . '/copy.json')[0]; $englishReceipt = recoveryJsonFile($englishReceiptPath); $englishPath = $englishDirectory . '/' . $englishReceipt['sha256'] . '.pdf';
	$englishBytes = file_get_contents($englishPath); $englishReceiptBytes = file_get_contents($englishReceiptPath);
	file_put_contents($englishPath, 'Damaged local PDF');
	$localDamaged = availabilityTestCli($localEnglishApply + ['resume' => $englishComplete['report']], 2);
	recoveryTestCheck($localDamaged['coverage']['blocked'] === 1 && $localDamaged['coverage']['local_verified'] === 9 && file_get_contents($englishPath) === 'Damaged local PDF', 'Local resume accepted or repaired a corrupt PDF'); file_put_contents($englishPath, $englishBytes);
	chmod($englishPath, 0000); $readDenied = availabilityTestCli($localEnglishApply, 2);
	recoveryTestCheck($readDenied['coverage']['blocked'] === 1 && $readDenied['coverage']['local_verified'] === 9, 'Local verification ignored unreadable PDF'); chmod($englishPath, 0600);
	file_put_contents($englishReceiptPath, reviewJson(array_replace($englishReceipt, ['snapshot_sha256' => str_repeat('0', 64)])));
	$wrongReceipt = availabilityTestCli($localEnglishApply, 2);
	recoveryTestCheck($wrongReceipt['coverage']['blocked'] === 1 && $wrongReceipt['coverage']['local_verified'] === 9, 'Local verification ignored a stale receipt'); file_put_contents($englishReceiptPath, $englishReceiptBytes);
	$pdo->exec("UPDATE quote SET notes = 'Changed local plan snapshot' WHERE id = 8001");
	$localStale = availabilityTestCli($localApply, 2); recoveryTestCheck($localStale['coverage']['blocked'] === 1, 'Local apply ignored a changed approved snapshot');
	$pdo->prepare('UPDATE quote SET notes = ? WHERE id = 8001')->execute([$beforeDb['quote'][array_search(8001, array_column($beforeDb['quote'], 'id'))]['notes']]);
	// A local check must never touch the HTTP fixture, even when HTTP options are supplied.
	$ignoredHttp = availabilityTestCli(array_replace($localApply, ['http-base' => 'http://' . $availabilityAddress, 'cookie-file' => $availabilityPrivate . '/absent-cookie']));
	recoveryTestCheck($ignoredHttp['coverage']['local_verified'] === 10 && $ignoredHttp['coverage']['http_verified'] === 0 && file_get_contents($work . '/availability-http.log') === $localHttpLog, 'Local mode read a cookie or sent an HTTP request');
	$localCopiesBefore = []; foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base . '/files/documents/.viewing-copies/7', FilesystemIterator::SKIP_DOTS)) as $file) if ($file->isFile()) $localCopiesBefore[$file->getPathname()] = [hash_file('sha256', $file->getPathname()), $file->getMTime()];
	availabilityTestCli($localApply); availabilityTestCli($localEnglishApply);
	foreach ($localCopiesBefore as $path => $state) recoveryTestCheck([hash_file('sha256', $path), filemtime($path)] === $state, 'Repeated local verification rewrote an existing copy or receipt');

	// Existing HTTP plans remain usable locally; verification evidence cannot cross resume modes.
	$localFromHttp = availabilityTestCli(array_replace($apply, $localOptions));
	recoveryTestCheck($localFromHttp['coverage']['local_verified'] === 10 && $localFromHttp['coverage']['http_verified'] === 0, 'Reviewed HTTP plan could not authorize cookie-free local apply');
	availabilityTestCli(array_replace($apply, $localOptions, ['resume' => $applied[0]['report']]), 1);
	availabilityTestCli($apply + ['resume' => $localFromHttp['report']], 1);
	availabilityTestCli(array_replace($apply, ['cookie-file' => null]), 1);
	availabilityTestCli(array_replace($localApply, ['verification' => 'http', 'http-base' => 'http://' . $availabilityAddress, 'cookie-file' => $availabilityPrivate . '/cookie']), 1);
	$legacyPlan = ''; foreach (availabilityReportRows($preview['report']) as $row) { if ($row['kind'] === 'header') unset($row['verification']); $legacyPlan .= json_encode($row, JSON_UNESCAPED_SLASHES) . "\n"; }
	reviewWrite($availabilityPrivate . '/legacy-preview.jsonl', $legacyPlan);
	$legacyApply = array_replace($apply, ['plan' => $availabilityPrivate . '/legacy-preview.jsonl', 'confirm-plan' => hash_file('sha256', $availabilityPrivate . '/legacy-preview.jsonl')]);
	$legacyLocal = availabilityTestCli(array_replace($legacyApply, $localOptions));
	recoveryTestCheck($legacyLocal['coverage']['local_verified'] === 10 && $legacyLocal['coverage']['http_verified'] === 0, 'Pre-option HTTP preview was rejected by local apply');
	$legacyHttp = availabilityTestCli($legacyApply);
	$legacyResume = ''; foreach (availabilityReportRows($legacyHttp['report']) as $row) { if ($row['kind'] === 'header') unset($row['verification']); $legacyResume .= json_encode($row, JSON_UNESCAPED_SLASHES) . "\n"; }
	reviewWrite($availabilityPrivate . '/legacy-apply.jsonl', $legacyResume);
	$legacyResumed = availabilityTestCli($legacyApply + ['resume' => $availabilityPrivate . '/legacy-apply.jsonl', 'limit' => 1]);
	recoveryTestCheck($legacyResumed['coverage']['http_verifications_reused'] === 10 && $legacyResumed['coverage']['local_verified'] === 0, 'Pre-option HTTP resume compatibility changed');
	reviewWrite($availabilityPrivate . '/empty-scope.json', reviewJson(['documents' => []]));
	$emptyPreview = availabilityTestCli($localOptions + ['scope' => $availabilityPrivate . '/empty-scope.json']);
	$emptyApply = availabilityTestCli($localOptions + ['action' => 'apply', 'plan' => $emptyPreview['report'], 'confirm-plan' => $emptyPreview['report_sha256']]);
	recoveryTestCheck(!$emptyApply['coverage']['every_scoped_document_http_verified'] && $emptyApply['coverage']['http_verified'] === 0, 'Empty local scope was reported as successful HTTP verification');
	foreach ($beforeDb as $table => $expectedRows) recoveryTestCheck($pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $expectedRows, 'Availability backfill mutated business/archive/email rows: ' . $table);
	foreach ($beforeFiles as $path => $state) recoveryTestCheck([hash_file('sha256', $path), filemtime($path)] === $state, 'Availability changed an original/historical source file');
} finally { proc_terminate($availabilityServer); proc_close($availabilityServer); fclose($availabilityLog); }
echo 'PASS: ' . ($checks - $availabilityStart) . " PDF availability checks; eight types, incomplete/contact gaps, private resumable backfill, real authenticated HTTP routes, concurrency and unchanged archives/business/email rows\n";
