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
		$pages = viewingCheckMarks($response, 'KOPIE – REKONSTRUKTION, KEIN ORIGINAL', 2);
		recoveryTestCheck(str_contains($response['headers'], 'historical_reconstructed_viewing_copy') && str_contains(implode('', $pages), 'Stored viewing buyer') && str_contains(implode('', $pages), 'Stored viewing position'), 'Stored content was not reconstructed: ' . json_encode($query));
		recoveryTestCheck(!str_contains(implode('', $pages), 'MUSTER – NICHT AUSGESTELLT'), 'Finalized viewing copy was marked as a draft');
		recoveryTestCheck(recoveryTestDownload($query + ['action' => 'reconstructed'], 42, 'HEAD')['bytes'] === '' && recoveryTestDownload($query + ['action' => 'reconstructed'], 42, 'POST')['code'] === 405, 'Viewing method restrictions failed');
		recoveryTestCheck(recoveryTestDownload($query + ['action' => 'reconstructed'], 43)['code'] === 404 && recoveryTestDownload($query + ['action' => 'reconstructed'], 44)['code'] === 404 && recoveryTestDownload($query + ['action' => 'reconstructed'], null)['code'] === 401, 'Viewing tenant/authentication isolation failed');
	}
	$quoteQuery = $viewingDocuments[0];
	foreach ([['id' => 0], ['id' => ['invalid']], ['module' => 'shops'], ['controller' => 'user'], ['id' => 999999]] as $invalid) recoveryTestCheck(recoveryTestDownload(array_replace($quoteQuery, ['action' => 'reconstructed'], $invalid))['code'] === 404, 'Invalid viewing identity was accepted');
	// Independent workers render the same document without a shared output path or cache.
	$port = stream_socket_server('tcp://127.0.0.1:0'); $secondAddress = stream_socket_get_name($port, false); fclose($port);
	$secondServer = proc_open([PHP_BINARY, '-d', 'error_reporting=8191', '-S', $secondAddress, __DIR__ . '/document-recovery-download-fixture.php'], [0 => ['pipe', 'r'], 1 => $httpLog, 2 => $httpLog], $secondPipes, null, array_replace(getenv(), ['DEWAWI_REVIEW_TEST_SOCKET' => $socket])); fclose($secondPipes[0]);
	try {
		for ($i = 0; $i < 100; $i++) { $ready = @stream_socket_client('tcp://' . $secondAddress); if ($ready) { fclose($ready); break; } usleep(50000); }
		$workers = [];
		foreach ([$httpAddress, $secondAddress] as $address) {
			$process = proc_open(['curl', '--silent', '--show-error', '--fail', '-H', 'X-Review-Test-User: 42', 'http://' . $address . '/?' . http_build_query($quoteQuery + ['action' => 'reconstructed'])], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
			$workers[] = [$process, $pipes];
		}
		foreach ($workers as [$process, $pipes]) {
			$bytes = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
			recoveryTestCheck(proc_close($process) === 0, 'Concurrent viewing failed: ' . $error);
			foreach (viewingPdfPages($bytes) as $page) recoveryTestCheck(str_contains($page, 'KOPIE – REKONSTRUKTION, KEIN ORIGINAL'), 'Concurrent viewing lost copy marking');
		}
	} finally { proc_terminate($secondServer); proc_close($secondServer); }
	foreach ($viewingDocuments as $query) recoveryTestCheck(glob($base . '/cache/document/' . $query['module'] . '/' . $query['controller'] . '/7/' . $query['id'] . '/*') === [], 'Viewing created a PDF cache entry');
	foreach (['de_DE', 'en_US'] as $locale) {
		$panel = recoveryTestDownload($quoteQuery + ['view' => 1, 'locale' => $locale]);
		recoveryTestCheck(str_contains($panel['bytes'], 'action=reconstructed') && !str_contains($panel['bytes'], '<iframe') && !str_contains($panel['bytes'], 'DOCUMENTS_'), 'Missing-original panel still embeds a failing original or lacks translation');
		preg_match('~<section[^>]*data-document-status="historical_viewing_copy".*?</section>~s', $panel['bytes'], $match);
		file_put_contents($work . '/viewing-panel-' . $locale . '.html', '<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="file://' . $root . '/css/ui.css"><body>' . $match[0] . '</body></html>');
	}
	viewingCheckMarks(recoveryTestDownload($quoteQuery + ['action' => 'reconstructed', 'locale' => 'en_US']), 'COPY – RECONSTRUCTION, NOT AN ORIGINAL', 2);
	// Missing stored amounts, contradictory totals and unavailable layouts remain visible as raw data.
	foreach (['UPDATE quotepos SET total = NULL WHERE parentid = 601', 'UPDATE quote SET total = 200 WHERE id = 601', 'UPDATE quote SET templateid = 999999 WHERE id = 601', "UPDATE quote SET currency = '' WHERE id = 601", "UPDATE quote SET billingstreet = '' WHERE id = 601", 'UPDATE quote SET quoteid = NULL, quotedate = NULL WHERE id = 601', 'UPDATE quotepos SET deleted = 1 WHERE parentid = 601'] as $sql) {
		$pdo->exec($sql);
		$response = recoveryTestDownload($quoteQuery + ['action' => 'reconstructed']);
		$pages = viewingCheckMarks($response, 'KOPIE – REKONSTRUKTION, KEIN ORIGINAL', 2);
		recoveryTestCheck(str_contains($response['headers'], 'historical_diagnostic_viewing_copy') && str_contains(implode('', $pages), 'Gespeicherte Daten: document'), 'Unreliable reconstruction did not expose stored data');
		$pdo->exec("UPDATE quote SET quoteid = 9601, quotedate = '2010-01-02', total = 119, templateid = 1, currency = 'EUR', billingstreet = 'Stored street' WHERE id = 601");
		$pdo->exec('UPDATE quotepos SET total = 100, deleted = 0 WHERE parentid = 601');
	}
	// An authorized child uses its own document and the permitted parent contact owner.
	$childView = recoveryTestDownload(['module' => 'sales', 'controller' => 'quote', 'id' => 609, 'action' => 'reconstructed'], 44);
	$childPages = viewingCheckMarks($childView, 'KOPIE – REKONSTRUKTION, KEIN ORIGINAL');
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
		viewingCheckMarks(recoveryTestDownload(['module' => 'sales', 'controller' => 'quote', 'id' => 1, 'action' => 'reconstructed']), 'KOPIE – REKONSTRUKTION, KEIN ORIGINAL');
		recoveryTestCheck($failure === 'missing' ? !file_exists($originalPath) : file_get_contents($originalPath) === '%PDF-corrupt', 'Viewing repaired or overwrote a registered original');
	}
	file_put_contents($originalPath, $originalBytes); touch($originalPath, $viewingFilesBefore[$originalPath][1]);
	// All eight document types: draft previews with/without covers and finalized state rules.
	foreach ($viewingDocuments as $query) {
		$table = $query['controller']; $id = $query['id'];
		foreach ([0, 1] as $cover) {
			$pdo->exec("UPDATE `$table` SET `{$table}id` = NULL, state = 100, completed = 0, pdfshowcover = $cover WHERE id = $id");
			$response = recoveryTestDownload($query + ['action' => 'preview']);
			viewingCheckMarks($response, 'MUSTER – NICHT AUSGESTELLT', $cover ? 2 : 1);
			if ($table === 'quote') viewingCheckMarks(recoveryTestDownload($query + ['action' => 'download', 'locale' => 'en_US']), 'SAMPLE – NOT ISSUED', $cover ? 2 : 1);
			$pdo->exec("UPDATE `$table` SET `{$table}id` = " . (9000 + $id) . ", state = 105, completed = 1, pdfshowcover = 1 WHERE id = $id");
		}
		foreach (['completed = 1', 'cancelled = 1', 'state = 105', 'state = 106'] as $state) {
			$pdo->exec("UPDATE `$table` SET `{$table}id` = NULL, state = 100, completed = 0, cancelled = 0 WHERE id = $id");
			$pdo->exec("UPDATE `$table` SET $state WHERE id = $id");
			$response = recoveryTestDownload($query + ['action' => 'reconstructed']);
			$pages = viewingCheckMarks($response, 'KOPIE – REKONSTRUKTION, KEIN ORIGINAL');
			recoveryTestCheck(!str_contains(implode('', $pages), 'MUSTER – NICHT AUSGESTELLT'), 'Server-side finalized state was treated as a draft');
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
	if (str_ends_with($name, '.pdf')) recoveryTestCheck(!in_array(hash('sha256', $viewingZip->getFromIndex($i)), $viewingHashes, true), 'An on-demand viewing copy entered the original GoBD delivery');
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
