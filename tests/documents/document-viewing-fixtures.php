<?php

// Included only by the disposable recovery database regression.
$viewingBefore = $checks;
$viewingHashes = [];
$viewingDocuments = [];
$viewingId = 600;
foreach (DEEC_DocumentArchive::documents() as $module => $controllers) foreach ($controllers as $controller) {
	$viewingId++;
	$document = $pdo->query('SELECT * FROM `' . $controller . '` ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
	$document = array_replace($document, ['id' => $viewingId, 'clientid' => 7, 'contactid' => 12, $controller . 'id' => 9000 + $viewingId, $controller . 'date' => '2010-01-02', 'filename' => '', 'state' => 105, 'completed' => 1, 'cancelled' => 0, 'templateid' => 1, 'currency' => 'EUR', 'subtotal' => 100, 'taxes' => 19, 'total' => 119, 'billingname1' => 'Stored viewing buyer', 'billingstreet' => 'Stored street', 'billingcity' => 'Berlin', 'billingcountry' => 'DE']);
	foreach ($document as $field => $value) if (str_starts_with($field, 'pdfshow')) $document[$field] = in_array($field, ['pdfshowcover', 'pdfshowheader', 'pdfshowprices'], true) ? 1 : 0;
	recoveryTestInsert($controller, $document);
	$position = $pdo->query('SELECT * FROM `' . $controller . 'pos` ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
	$position = array_replace($position, ['id' => $viewingId, 'clientid' => 7, 'parentid' => $viewingId, 'itemid' => 0, 'title' => 'Stored viewing position', 'description' => '', 'price' => 100, 'quantity' => 1, 'taxrate' => 19, 'total' => 100, 'currency' => 'EUR', 'deleted' => 0, 'masterid' => 0]);
	recoveryTestInsert($controller . 'pos', $position);
	$viewingDocuments[] = ['module' => $module, 'controller' => $controller, 'id' => $viewingId];
}
$childViewing = array_replace($pdo->query('SELECT * FROM quote WHERE id = 9')->fetch(PDO::FETCH_ASSOC), ['id' => 609, 'quoteid' => 9609, 'filename' => '', 'subtotal' => 100, 'taxes' => 19, 'total' => 119]);
recoveryTestInsert('quote', $childViewing);
recoveryTestInsert('quotepos', array_replace($pdo->query('SELECT * FROM quotepos WHERE parentid = 9')->fetch(PDO::FETCH_ASSOC), ['id' => 609, 'parentid' => 609, 'total' => 100]));

function viewingPdfPages(string $bytes): array
{
	global $work;
	$path = $work . '/viewing-check.pdf';
	file_put_contents($path, $bytes);
	$process = proc_open(['pdftotext', '-layout', $path, '-'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$text = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
	fclose($pipes[1]); fclose($pipes[2]);
	recoveryTestCheck(proc_close($process) === 0, 'Viewing PDF is invalid: ' . $error);
	return array_values(array_filter(explode("\f", $text), static fn(string $page): bool => trim($page) !== ''));
}

function viewingCheckMarks(array $response, string $mark, int $minimumPages = 1): array
{
	global $work;
	if ($response['code'] !== 200) throw new RuntimeException('PDF request failed: ' . $response['headers'] . "\n" . substr(file_get_contents($work . '/viewing-http.log'), -5000));
	recoveryTestCheck($response['code'] === 200 && str_contains($response['headers'], 'private, no-store') && str_contains($response['headers'], 'application/pdf'), 'Private PDF response failed: ' . $response['headers'] . substr($response['bytes'], 0, 200));
	$pages = viewingPdfPages($response['bytes']);
	recoveryTestCheck(count($pages) >= $minimumPages, 'Multipage viewing fixture lost its pages');
	foreach ($pages as $number => $page) recoveryTestCheck(str_contains($page, $mark), 'Page ' . ($number + 1) . ' lost marking ' . $mark . ': ' . substr($page, 0, 300) . ' (' . $response['headers'] . ')');
	return $pages;
}

recoveryTestInsert('quote', array_replace($pdo->query('SELECT * FROM quote WHERE id = 601')->fetch(PDO::FETCH_ASSOC), ['id' => 690, 'quoteid' => 9690]));
recoveryTestInsert('quotepos', array_replace($pdo->query('SELECT * FROM quotepos WHERE parentid = 601')->fetch(PDO::FETCH_ASSOC), ['id' => 690, 'parentid' => 690]));
// A separate synthetic English document; existing stored document languages remain untouched.
recoveryTestInsert('quote', array_replace($pdo->query('SELECT * FROM quote WHERE id = 601')->fetch(PDO::FETCH_ASSOC), ['id' => 691, 'quoteid' => 9691, 'language' => 'en_US']));
recoveryTestInsert('quotepos', array_replace($pdo->query('SELECT * FROM quotepos WHERE parentid = 601')->fetch(PDO::FETCH_ASSOC), ['id' => 691, 'parentid' => 691]));

// Synthetic equivalent of the reported invoice; no production data is used.
recoveryTestInsert('invoice', array_replace($pdo->query('SELECT * FROM invoice WHERE id = 603')->fetch(PDO::FETCH_ASSOC), ['id' => 59505, 'invoiceid' => 112326, 'filename' => 'Rechnung-112326.pdf']));
recoveryTestInsert('invoicepos', array_replace($pdo->query('SELECT * FROM invoicepos WHERE parentid = 603')->fetch(PDO::FETCH_ASSOC), ['id' => 59505, 'parentid' => 59505]));

$viewingTables = ['documentarchive', 'emailattachment', 'emailmessage', 'emailmessageattachment', 'pricerulepos'];
foreach (DEEC_DocumentArchive::documents() as $controllers) foreach ($controllers as $controller) $viewingTables = array_merge($viewingTables, [$controller, $controller . 'pos', $controller . 'posset']);
$viewingDbBefore = [];
foreach ($viewingTables as $table) $viewingDbBefore[$table] = $pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$viewingFilesBefore = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base . '/files', FilesystemIterator::SKIP_DOTS)) as $file) if ($file->isFile()) $viewingFilesBefore[$file->getPathname()] = [hash_file('sha256', $file->getPathname()), $file->getMTime()];
$httpLog = fopen($work . '/viewing-http.log', 'wb');
$httpServer = proc_open([PHP_BINARY, '-d', 'error_reporting=8191', '-S', $httpAddress, __DIR__ . '/document-recovery-download-fixture.php'], [0 => ['pipe', 'r'], 1 => $httpLog, 2 => $httpLog], $httpPipes, null, array_replace(getenv(), ['DEWAWI_REVIEW_TEST_SOCKET' => $socket])); fclose($httpPipes[0]);
try {
	for ($i = 0; $i < 100; $i++) { $ready = @stream_socket_client('tcp://' . $httpAddress); if ($ready) { fclose($ready); break; } usleep(50000); }
	// No review locator, candidate, cache, approval or edit right is required.
	$locator = DEEC_DocumentReview::locatorPath(7); $locatorBytes = file_get_contents($locator); unlink($locator);
	$viewPermissions = $permissions;
	foreach (DEEC_DocumentArchive::documents() as $module => $controllers) {
		$viewPermissions[$module] = json_encode(array_fill_keys($controllers, ['view']));
		$pdo->prepare('UPDATE permission SET `' . $module . '` = ? WHERE userid = 42')->execute([$viewPermissions[$module]]);
	}
	foreach ($viewingDocuments as $query) {
		$response = recoveryTestDownload($query + ['action' => 'reconstructed']);
		$viewingHashes[] = hash('sha256', $response['bytes']);
		$download = recoveryTestDownload($query + ['action' => 'download']);
		recoveryTestCheck($download['code'] === 200 && $download['bytes'] === $response['bytes'] && str_contains($download['headers'], 'attachment;'), 'Normal Download did not reuse the permanent copy');
		$pages = viewingCheckMarks($response, 'KOPIE', 2);
		recoveryTestCheck(str_contains($response['headers'], 'historical_reconstructed_viewing_copy') && str_contains(implode('', $pages), 'Stored viewing buyer') && str_contains(implode('', $pages), 'Stored viewing position'), 'Stored content was not reconstructed: ' . json_encode($query));
		recoveryTestCheck(!str_contains(implode('', $pages), 'MUSTER'), 'Finalized viewing copy was marked as a draft');
		recoveryTestCheck(recoveryTestDownload($query + ['action' => 'reconstructed'], 42, 'HEAD')['bytes'] === '' && recoveryTestDownload($query + ['action' => 'reconstructed'], 42, 'POST')['code'] === 405, 'Viewing method restrictions failed');
		recoveryTestCheck(recoveryTestDownload($query + ['action' => 'reconstructed'], 43)['code'] === 404 && recoveryTestDownload($query + ['action' => 'reconstructed'], 44)['code'] === 404 && recoveryTestDownload($query + ['action' => 'reconstructed'], null)['code'] === 401, 'Viewing tenant/authentication isolation failed');
	}
	$quoteQuery = $viewingDocuments[0];
	$copyRecords = glob($base . '/files/documents/.viewing-copies/7/sales/quote/601/*/de_DE/copy.json');
	recoveryTestCheck(count($copyRecords) === 1, 'Permanent copy receipt missing');
	$initialCopy = recoveryJsonFile($copyRecords[0]); $initialCopyPath = dirname($copyRecords[0]) . '/' . $initialCopy['sha256'] . '.pdf';
	$initialCopyBytes = file_get_contents($initialCopyPath);
	recoveryTestCheck((fileperms($initialCopyPath) & 0777) === 0600 && !(new Contacts_Model_DbTable_Emailattachment())->canReadStoredFile(substr($initialCopyPath, strlen($base) + 1)), 'Permanent copy leaked into a direct file route');
	unlink($copyRecords[0]);
	foreach ([['id' => 0], ['id' => ['invalid']], ['module' => 'shops'], ['controller' => 'user'], ['id' => 999999]] as $invalid) recoveryTestCheck(recoveryTestDownload(array_replace($quoteQuery, ['action' => 'reconstructed'], $invalid))['code'] === 404, 'Invalid viewing identity was accepted');
	// Concurrent first readers publish one permanent copy and keep any orphaned previous PDF.
	$port = stream_socket_server('tcp://127.0.0.1:0'); $secondAddress = stream_socket_get_name($port, false); fclose($port);
	$secondServer = proc_open([PHP_BINARY, '-d', 'error_reporting=8191', '-S', $secondAddress, __DIR__ . '/document-recovery-download-fixture.php'], [0 => ['pipe', 'r'], 1 => $httpLog, 2 => $httpLog], $secondPipes, null, array_replace(getenv(), ['DEWAWI_REVIEW_TEST_SOCKET' => $socket])); fclose($secondPipes[0]);
	try {
		for ($i = 0; $i < 100; $i++) { $ready = @stream_socket_client('tcp://' . $secondAddress); if ($ready) { fclose($ready); break; } usleep(50000); }
		$workers = [];
		foreach ([$httpAddress, $secondAddress] as $address) {
			$process = proc_open(['curl', '--silent', '--show-error', '--fail', '-H', 'X-Review-Test-User: 42', 'http://' . $address . '/?' . http_build_query(array_replace($quoteQuery, ['id' => 690, 'action' => 'reconstructed']))], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
			$workers[] = [$process, $pipes];
		}
		$concurrentHashes = [];
		foreach ($workers as [$process, $pipes]) {
			$bytes = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
			recoveryTestCheck(proc_close($process) === 0, 'Concurrent viewing failed: ' . $error);
			$concurrentHashes[] = hash('sha256', $bytes); $viewingHashes[] = hash('sha256', $bytes);
			foreach (viewingPdfPages($bytes) as $page) recoveryTestCheck(str_contains($page, 'KOPIE'), 'Concurrent viewing lost copy marking');
		}
		recoveryTestCheck(count(array_unique($concurrentHashes)) === 1 && file_get_contents($initialCopyPath) === $initialCopyBytes, 'Concurrent cache miss changed a stored copy or returned differing bytes');
	} finally { proc_terminate($secondServer); proc_close($secondServer); }
	recoveryTestCheck(recoveryTestDownload($quoteQuery + ['action' => 'reconstructed'])['code'] === 200, 'Missing copy receipt could not be recreated while retaining the old PDF');
	$copy = recoveryJsonFile($copyRecords[0]); $copyPath = dirname($copyRecords[0]) . '/' . $copy['sha256'] . '.pdf'; $copyBytes = file_get_contents($copyPath);
	file_put_contents($copyPath, 'Synthetic corrupted stored copy');
	recoveryTestCheck(recoveryTestDownload($quoteQuery + ['action' => 'download'])['code'] === 404, 'Corrupt permanent copy was silently replaced');
	file_put_contents($copyPath, $copyBytes); chmod($copyPath, 0600);
	unlink($copyPath);
	recoveryTestCheck(recoveryTestDownload($quoteQuery + ['action' => 'download'])['code'] === 404, 'Missing permanent PDF behind a receipt was silently replaced');
	file_put_contents($copyPath, $copyBytes); chmod($copyPath, 0600);
	$copyIdentity = ['module' => 'sales', 'controller' => 'quote', 'id' => 601, 'clientid' => 7, 'document_number' => '9601'];
	$copyJob = ['identity' => $copyIdentity, 'directory' => $review . '/viewing-copy-601']; reviewDirectory($copyJob['directory'] . '/external');
	reviewWrite($copyJob['directory'] . '/external/viewing.pdf', $copyBytes);
	$copyDecision = ['decision' => 'selected', 'document' => $copyIdentity, 'reason' => 'Synthetic forbidden viewing promotion', 'reviewed_by' => 42, 'reviewed_at' => gmdate('c'), 'candidate_path' => 'external/viewing.pdf', 'sha256' => $copy['sha256'], 'origin' => ['kind' => 'historical_pdf', 'source_path' => $copyPath]];
	try { recoverySelection($copyJob, new DEEC_DocumentArchive($review), $copyDecision); throw new LogicException('Viewing copy entered privileged recovery selection'); }
	catch (RuntimeException $error) { recoveryTestCheck($error->getMessage() === 'Viewing copies cannot be selected or approved as originals', 'Unexpected viewing copy selection refusal'); }
	$copyGuardUser = Zend_Registry::get('User'); $copyGuardClient = Zend_Registry::get('Client'); $copyGuardAdapter = Zend_Db_Table::getDefaultAdapter();
	$copyGuardDb = recoveryBoot(migrationConfig($work . '/database.ini', 'production') + ['unix_socket' => $socket], $base, 7, 42);
	$copyArchive = new DEEC_DocumentArchive($base); $copyPublication = $copyArchive->storeFile($copyPath, 7, 'sales', 'quote', 601, 'pdf', $copy['sha256']);
	try {
		(new Application_Model_DbTable_Documentarchive())->registerOriginal($pdo->query('SELECT * FROM quote WHERE id = 601')->fetch(PDO::FETCH_ASSOC), 'sales', 'quote', 'pdf', 'synthetic-viewing-copy.pdf');
		throw new LogicException('Viewing copy entered original registration');
	} catch (RuntimeException $error) { recoveryTestCheck($error->getMessage() === 'Viewing copies cannot be registered as originals', 'Unexpected viewing copy registration refusal: ' . $error->getMessage()); }
	finally { $copyArchive->discardUncommitted($copyPublication); $copyGuardDb->closeConnection(); Zend_Db_Table::setDefaultAdapter($copyGuardAdapter); Zend_Registry::set('User', $copyGuardUser); Zend_Registry::set('Client', $copyGuardClient); }
	foreach ($viewingDocuments as $query) recoveryTestCheck(glob($base . '/cache/document/' . $query['module'] . '/' . $query['controller'] . '/7/' . $query['id'] . '/*') === [], 'Viewing created a PDF cache entry');
	foreach (['de_DE', 'en_US'] as $locale) {
		$panel = recoveryTestDownload($quoteQuery + ['view' => 1, 'locale' => $locale]);
		recoveryTestCheck(str_contains($panel['bytes'], 'action=preview') && str_contains($panel['bytes'], '<iframe') && !str_contains($panel['bytes'], 'action=reconstructed') && !str_contains($panel['bytes'], 'DOCUMENTS_'), 'Missing-original panel still embeds a failing original or lacks translation');
		preg_match('~<section[^>]*data-document-status="historical_viewing_copy".*?</section>~s', $panel['bytes'], $match);
		file_put_contents($work . '/viewing-panel-' . $locale . '.html', '<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="file://' . $root . '/css/ui.css"><body>' . $panel['bytes'] . '</body></html>');
	}
	recoveryTestCheck($pdo->query('SELECT language FROM quote WHERE id = 601')->fetchColumn() === 'de_DE', 'German document fixture lost its explicit stored language');
	viewingCheckMarks(recoveryTestDownload($quoteQuery + ['action' => 'reconstructed', 'locale' => 'en_US']), 'KOPIE', 2);
	foreach (['de_DE', 'en_US'] as $uiLocale) viewingCheckMarks(recoveryTestDownload(['module' => 'sales', 'controller' => 'quote', 'id' => 691, 'action' => 'reconstructed', 'locale' => $uiLocale]), 'COPY', 2);
	// Missing stored amounts, contradictory totals and unavailable layouts remain visible as raw data.
	foreach (['UPDATE quotepos SET total = NULL WHERE parentid = 601', 'UPDATE quote SET total = 200 WHERE id = 601', 'UPDATE quote SET templateid = 999999 WHERE id = 601', "UPDATE quote SET currency = '' WHERE id = 601", "UPDATE quote SET billingstreet = '' WHERE id = 601", 'UPDATE quote SET quoteid = NULL, quotedate = NULL WHERE id = 601', 'UPDATE quotepos SET deleted = 1 WHERE parentid = 601'] as $sql) {
		$pdo->exec($sql);
		$response = recoveryTestDownload($quoteQuery + ['action' => 'reconstructed']);
		$pages = viewingCheckMarks($response, 'KOPIE', 2);
		recoveryTestCheck(str_contains($response['headers'], 'historical_diagnostic_viewing_copy') && str_contains(implode('', $pages), 'Gespeicherte Daten: document'), 'Unreliable reconstruction did not expose stored data');
		$pdo->exec("UPDATE quote SET quoteid = 9601, quotedate = '2010-01-02', total = 119, templateid = 1, currency = 'EUR', billingstreet = 'Stored street' WHERE id = 601");
		$pdo->exec('UPDATE quotepos SET total = 100, deleted = 0 WHERE parentid = 601');
	}
	// An authorized child uses its own document and the permitted parent contact owner.
	$childView = recoveryTestDownload(['module' => 'sales', 'controller' => 'quote', 'id' => 609, 'action' => 'reconstructed'], 44);
	$childPages = viewingCheckMarks($childView, 'KOPIE');
	recoveryTestCheck(str_contains($childView['headers'], 'historical_reconstructed_viewing_copy') && str_contains(implode('', $childPages), $childViewing['billingname1']), 'Parent contact sharing lost the stored child recipient');
	$pdo->exec("UPDATE permission SET sales = '{}' WHERE userid = 42");
	recoveryTestCheck(recoveryTestDownload($quoteQuery + ['action' => 'reconstructed'])['code'] === 404, 'Viewing ignored document permissions');
	$pdo->prepare('UPDATE permission SET sales = ? WHERE userid = 42')->execute([$viewPermissions['sales']]);
	// A registered valid original wins, including through the new action, without marking or rendering.
	$originalRow = $pdo->query("SELECT * FROM documentarchive WHERE module = 'sales' AND controller = 'quote' AND documentid = 1 AND format = 'pdf'")->fetch(PDO::FETCH_ASSOC);
	$originalPath = $base . '/' . $originalRow['path']; $originalBytes = file_get_contents($originalPath);
	$original = recoveryTestDownload(['module' => 'sales', 'controller' => 'quote', 'id' => 1, 'action' => 'reconstructed']);
	recoveryTestCheck($original['code'] === 200 && $original['bytes'] === $originalBytes && str_contains($original['headers'], 'archived_original'), 'Viewing changed a verified original');
	$originalPanel = recoveryTestDownload(['module' => 'sales', 'controller' => 'quote', 'id' => 1, 'view' => 1]);
	recoveryTestCheck(str_contains($originalPanel['bytes'], 'action=download') && str_contains($originalPanel['bytes'], '<iframe') && !str_contains($originalPanel['bytes'], 'action=reconstructed'), 'Valid original lost its existing download and preview workflow');
	$storedFilename = $pdo->query('SELECT filename FROM quote WHERE id = 1')->fetchColumn();
	$quotedFilename = 'Beleg "Ä".pdf';
	$pdo->prepare('UPDATE quote SET filename = ? WHERE id = 1')->execute([$quotedFilename]);
	$pdo->prepare('UPDATE documentarchive SET filename = ? WHERE id = ?')->execute([$quotedFilename, $originalRow['id']]);
	$quotedOriginal = recoveryTestDownload(['module' => 'sales', 'controller' => 'quote', 'id' => 1, 'action' => 'reconstructed']);
	recoveryTestCheck($quotedOriginal['code'] === 200 && $quotedOriginal['bytes'] === $originalBytes && str_contains($quotedOriginal['headers'], "filename*=UTF-8''" . rawurlencode($quotedFilename)), 'Original filename was not safely encoded for the PDF response');
	$pdo->prepare('UPDATE quote SET filename = ? WHERE id = 1')->execute([$storedFilename]);
	$pdo->prepare('UPDATE documentarchive SET filename = ? WHERE id = ?')->execute([$originalRow['filename'], $originalRow['id']]);
	foreach (['missing', 'corrupt'] as $failure) {
		if ($failure === 'missing') unlink($originalPath); else file_put_contents($originalPath, '%PDF-corrupt');
		if ($failure === 'missing') viewingCheckMarks(recoveryTestDownload(['module' => 'sales', 'controller' => 'quote', 'id' => 1, 'action' => 'reconstructed']), 'KOPIE');
		else foreach (['preview', 'download', 'reconstructed'] as $action) recoveryTestCheck(recoveryTestDownload(['module' => 'sales', 'controller' => 'quote', 'id' => 1, 'action' => $action])['code'] === 404, 'Corrupt original was silently replaced by a copy');
		recoveryTestCheck($failure === 'missing' ? !file_exists($originalPath) : file_get_contents($originalPath) === '%PDF-corrupt', 'Viewing repaired or overwrote a registered original');
	}
	file_put_contents($originalPath, $originalBytes); touch($originalPath, $viewingFilesBefore[$originalPath][1]);
	$reportedQuery = ['module' => 'sales', 'controller' => 'invoice', 'id' => 59505];
	$reportedDownload = recoveryTestDownload($reportedQuery + ['action' => 'download']); viewingCheckMarks($reportedDownload, 'KOPIE');
	recoveryTestCheck(str_contains($reportedDownload['headers'], 'attachment; filename="Rechnung-112326.pdf"') && !str_contains($reportedDownload['headers'], 'reconstructed-copy') && !str_contains($reportedDownload['headers'], '59505.pdf'), 'Synthetic invoice download lost its stored normal filename');
	$reportedRecords = glob($base . '/files/documents/.viewing-copies/7/sales/invoice/59505/*/de_DE/copy.json');
	$reportedReceipt = file_get_contents($reportedRecords[0]); $reportedCopy = recoveryJsonFile($reportedRecords[0]); $reportedCopyPath = dirname($reportedRecords[0]) . '/' . $reportedCopy['sha256'] . '.pdf'; $reportedCopyState = [hash_file('sha256', $reportedCopyPath), filemtime($reportedCopyPath)];
	$reportedPreview = recoveryTestDownload($reportedQuery + ['action' => 'preview']);
	recoveryTestCheck($reportedPreview['code'] === 200 && $reportedPreview['bytes'] === $reportedDownload['bytes'] && str_contains($reportedPreview['headers'], 'inline; filename="Rechnung-112326.pdf"') && str_contains($reportedPreview['headers'], 'nosniff') && str_contains($reportedPreview['headers'], 'sandbox;'), 'Normal inline preview differs from the protected Download copy');
	$ajax = recoveryTestDownload($reportedQuery + ['action' => 'preview'], 42, 'POST', ['X-Requested-With: XMLHttpRequest']);
	if ($ajax['code'] !== 200 || !str_starts_with($ajax['bytes'], '{')) throw new RuntimeException('AJAX preview failed: ' . $ajax['headers'] . ' ' . $ajax['bytes'] . substr(file_get_contents($work . '/viewing-http.log'), -4000));
	$previewJson = json_decode($ajax['bytes'], true, 512, JSON_THROW_ON_ERROR);
	recoveryTestCheck($ajax['code'] === 200 && $previewJson['ok'] === true && $previewJson['filename'] === 'Rechnung-112326.pdf' && str_contains($previewJson['url'], 'action=preview') && !str_contains($ajax['bytes'], $base) && !str_contains($ajax['bytes'], 'files/documents') && str_contains($ajax['headers'], 'private, no-store'), 'AJAX preview leaked a private path or failed its ordinary protected route');
	recoveryTestCheck(file_get_contents($reportedRecords[0]) === $reportedReceipt && [hash_file('sha256', $reportedCopyPath), filemtime($reportedCopyPath)] === $reportedCopyState, 'Filename/preview access rewrote immutable copy bytes or its receipt');
	foreach (['preview', 'download'] as $action) {
		recoveryTestCheck(recoveryTestDownload($reportedQuery + ['action' => $action], null)['code'] === 401 && recoveryTestDownload($reportedQuery + ['action' => $action], 43)['code'] !== 200, 'Normal document PDF access leaked across tenant/auth boundaries');
		recoveryTestCheck(recoveryTestDownload($reportedQuery + ['action' => $action], 42, 'HEAD')['bytes'] === '', 'PDF HEAD leaked content');
	}
	$copyGuardDb = recoveryBoot(migrationConfig($work . '/database.ini', 'production') + ['unix_socket' => $socket], $base, 7, 42);
	$contactFiles = (new Contacts_Model_DbTable_Emailattachment())->getContactDocumentFiles(500);
	$reportedFiles = array_values(array_filter($contactFiles, static fn(array $file): bool => $file['module'] === 'sales' && $file['controller'] === 'invoice' && $file['id'] === 59505));
	recoveryTestCheck(count($reportedFiles) === 1 && $reportedFiles[0]['filename'] === 'Rechnung-112326.pdf' && $reportedFiles[0]['status'] === 'copy_available' && $reportedFiles[0]['documentUrl'] === '/sales/invoice/download/id/59505' && $reportedFiles[0]['relative'] === '', 'Contact issued-document files did not use the normal authorized Download identity');
	Zend_Controller_Front::getInstance()->setControllerDirectory(['default' => $root . '/application/controllers', 'sales' => $root . '/application/modules/sales/controllers', 'contacts' => $root . '/application/modules/contacts/controllers']);
	Zend_Controller_Front::getInstance()->getRouter()->addRoute('default', new Zend_Controller_Router_Route_Module(['module' => 'default', 'controller' => 'index', 'action' => 'index']));
	foreach (['de_DE', 'en_US'] as $locale) {
		$translator = new DEEC_Translate($locale); $translator->loadDir('default', $root . '/languages/' . $locale . '/default'); $translator->loadDir('contacts', $root . '/languages/' . $locale . '/contacts'); Zend_Registry::set('DEEC_Translate', $translator);
		$view = new Zend_View(['scriptPath' => $root . '/application/modules/contacts/views/scripts']); $view->addHelperPath($root . '/application/views/helpers', 'Zend_View_Helper'); $view->documentFiles = $reportedFiles;
		$html = $view->render('contact/document-files.phtml');
		recoveryTestCheck(str_contains($html, '/sales/invoice/download/id/59505') && str_contains($html, '>Rechnung-112326.pdf</a>') && !str_contains($html, 'CONTACTS_DOCUMENT_FILE_COPY_AVAILABLE'), 'Contact filename was not a localized document-route link');
		file_put_contents($work . '/contact-pdf-panel-' . $locale . '.html', '<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="file://' . $root . '/css/ui.css"><body>' . $html . '<script src="file://' . $root . '/library/jQuery/jquery-1.12.4.min.js"></script><script src="file://' . $root . '/library/jQuery/jquery.cookie.js"></script><script>var language="en",module="contacts",controller="contact",action="fixture",id=500,baseUrl="",cookiePath="/fixture"; var fixtureErrors=[]; window.addEventListener("error",function(e){fixtureErrors.push(e.message);}); $.fn.datepicker=function(){return this;}; function autosize(){}</script><script src="file://' . $root . '/js/global.js"></script></body></html>');
	}
	$copyGuardDb->closeConnection();
	foreach (['sha256' => str_repeat('0', 64), 'version' => 2, 'path' => 'files/documents/incorrect.pdf', 'filesize' => 1] as $field => $invalidValue) {
		$pdo->prepare('UPDATE documentarchive SET `' . $field . '` = ? WHERE id = ?')->execute([$invalidValue, $originalRow['id']]);
		foreach (['preview', 'download', 'reconstructed'] as $action) recoveryTestCheck(recoveryTestDownload(['module' => 'sales', 'controller' => 'quote', 'id' => 1, 'action' => $action])['code'] === 404, 'Archive conflict was hidden by a viewing copy: ' . $field);
		$badAjax = recoveryTestDownload(['module' => 'sales', 'controller' => 'quote', 'id' => 1, 'action' => 'preview'], 42, 'POST', ['X-Requested-With: XMLHttpRequest']);
		recoveryTestCheck($badAjax['code'] === 404 && json_decode($badAjax['bytes'], true, 512, JSON_THROW_ON_ERROR)['ok'] === false && !str_contains($badAjax['bytes'], $base), 'AJAX preview hid an archive conflict or disclosed a private path');
		$pdo->prepare('UPDATE documentarchive SET `' . $field . '` = ? WHERE id = ?')->execute([$originalRow[$field], $originalRow['id']]);
	}
	// All eight document types: draft previews with/without covers and finalized state rules.
	foreach ($viewingDocuments as $query) {
		$table = $query['controller']; $id = $query['id'];
		foreach ([0, 1] as $cover) {
			$pdo->exec("UPDATE `$table` SET `{$table}id` = NULL, state = 100, completed = 0, pdfshowcover = $cover WHERE id = $id");
			$response = recoveryTestDownload($query + ['action' => 'preview']);
			viewingCheckMarks($response, 'MUSTER', $cover ? 2 : 1);
			if ($table === 'quote') viewingCheckMarks(recoveryTestDownload($query + ['action' => 'download', 'locale' => 'en_US']), 'MUSTER', $cover ? 2 : 1);
			$pdo->exec("UPDATE `$table` SET `{$table}id` = " . (9000 + $id) . ", state = 105, completed = 1, pdfshowcover = 1 WHERE id = $id");
		}
		foreach (['completed = 1', 'cancelled = 1', 'state = 105', 'state = 106'] as $state) {
			$pdo->exec("UPDATE `$table` SET `{$table}id` = NULL, state = 100, completed = 0, cancelled = 0 WHERE id = $id");
			$pdo->exec("UPDATE `$table` SET $state WHERE id = $id");
			$response = recoveryTestDownload($query + ['action' => 'reconstructed']);
			$pages = viewingCheckMarks($response, 'KOPIE');
			recoveryTestCheck(!str_contains(implode('', $pages), 'MUSTER'), 'Server-side finalized state was treated as a draft');
			$pdo->exec("UPDATE `$table` SET `{$table}id` = " . (9000 + $id) . ", state = 105, completed = 1, cancelled = 0 WHERE id = $id");
		}
	}
	file_put_contents($locator, $locatorBytes); chmod($locator, 0600); touch($locator, $viewingFilesBefore[$locator][1]);
} finally {
	foreach (DEEC_DocumentArchive::documents() as $module => $controllers) $pdo->prepare('UPDATE permission SET `' . $module . '` = ? WHERE userid = 42')->execute([$permissions[$module]]);
	proc_terminate($httpServer); proc_close($httpServer); fclose($httpLog);
}
foreach ($viewingDbBefore as $table => $rows) recoveryTestCheck($pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $rows, 'Viewing changed stored business, email or original archive data: ' . $table);
foreach ($viewingFilesBefore as $path => $state) recoveryTestCheck([hash_file('sha256', $path), filemtime($path)] === $state, 'Viewing changed an original or review file');
foreach ($viewingDocuments as $query) recoveryTestCheck(!is_file((new DEEC_DocumentArchive($base))->path(7, $query['module'], $query['controller'], $query['id'])), 'Viewing created a canonical original');
$viewingDb = recoveryBoot(migrationConfig($work . '/database.ini', 'production') + ['unix_socket' => $socket], $base, 7, 42);
$viewingUser = Zend_Registry::get('User'); Zend_Registry::set('User', array_replace($viewingUser, ['admin' => 1]));
$viewingExportDir = $base . '/files/export/7/0/7/';
$viewingExport = (new DEEC_Export_Gobd($viewingDb, 7, 42))->export($viewingExportDir, '2010-01-01', '2010-12-31', true);
$viewingZip = new ZipArchive(); $viewingZip->open($viewingExportDir . $viewingExport);
for ($i = 0; $i < $viewingZip->numFiles; $i++) {
	$name = $viewingZip->getNameIndex($i);
	if (str_ends_with($name, '.pdf')) recoveryTestCheck(!in_array(hash('sha256', $viewingZip->getFromIndex($i)), $viewingHashes, true), 'A permanent viewing copy entered the original GoBD delivery');
}
$viewingZip->close(); Zend_Registry::set('User', $viewingUser); $viewingDb->closeConnection();
$viewingDb = recoveryBoot(migrationConfig($work . '/database.ini', 'production') + ['unix_socket' => $socket], $base, 7, 42);
require_once $root . '/application/modules/sales/controllers/helpers/Positions.php';
$changingRenderer = new class extends DEEC_Pdf {
	protected function renderPayload(TCPDF $pdf, array $payload): void
	{
		parent::renderPayload($pdf, $payload);
		global $pdo;
		$pdo->exec("UPDATE quote SET notes = 'Concurrent stored-data change' WHERE id = 601");
	}
};
$storedNote = $pdo->query('SELECT notes FROM quote WHERE id = 601')->fetchColumn();
try {
	$changingRenderer->viewingCopy(601, 'sales', 'quote');
	throw new LogicException('A changing snapshot was delivered as a reliable viewing copy');
} catch (RuntimeException $error) { recoveryTestCheck($error->getMessage() === 'Stored document changed during viewing', 'Snapshot race failed unexpectedly: ' . $error->getMessage()); }
finally { $pdo->prepare('UPDATE quote SET notes = ? WHERE id = 601')->execute([$storedNote]); $viewingDb->closeConnection(); }
echo 'PASS: ' . ($checks - $viewingBefore) . " historical viewing and draft checks; read-only database account, marked pages, original and tenant isolation\n";
