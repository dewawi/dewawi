<?php

// Included only by the disposable recovery database regression.
if (!isset($pdo, $work, $review, $base) || !preg_match('~^/tmp/dewawi-document-recovery-db-[a-zA-Z0-9_]+$~D', $work)) throw new RuntimeException('Isolated recovery fixtures are required');
$webDb = recoveryBoot(migrationConfig($work . '/archive.ini', 'production') + ['unix_socket' => $socket], $base, 7, 42);
$webService = new DEEC_DocumentRecovery();
if (!is_dir($work . '/sessions')) mkdir($work . '/sessions', 0700);
Zend_Session::setOptions(['save_path' => $work . '/sessions']);
$webJobs = []; $webSnapshots = []; $webReconstructed = [];
$originalsBeforeWeb = $pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$webPermissions = $permissions;
foreach (DEEC_DocumentArchive::documents() as $module => $controllers) $webPermissions[$module] = json_encode(array_fill_keys($controllers, ['view', 'edit', 'add']));
$pdo->prepare('UPDATE permission SET sales = ?, purchases = ? WHERE userid = 42')->execute([$webPermissions['sales'], $webPermissions['purchases']]);
$fixtureId = 300;
foreach ($identities as $identity) {
	$fixtureId++; $module = $identity['module']; $controller = $identity['controller'];
	$document = $pdo->query('SELECT * FROM `' . $controller . '` WHERE id = ' . $identity['id'])->fetch(PDO::FETCH_ASSOC);
	recoveryTestInsert($controller, array_replace($document, ['id' => $fixtureId, $controller . 'id' => 9000 + $fixtureId, 'filename' => 'web-' . $fixtureId . '.pdf', 'subtotal' => 100, 'taxes' => 19, 'total' => 119]));
	$position = $pdo->query('SELECT * FROM `' . $controller . 'pos` WHERE parentid = ' . $identity['id'] . ' ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
	recoveryTestInsert($controller . 'pos', array_replace($position, ['id' => 5000 + $fixtureId, 'parentid' => $fixtureId, 'price' => 100, 'quantity' => 1, 'taxrate' => 19, 'total' => 119, 'masterid' => 0, 'pricerulemaster' => 0]));
	$job = $webService->job($fixtureId, $module, $controller, true);
	$webJobs[] = $job;
	$snapshot = recoverySnapshot($webDb, $job['identity']); $webSnapshots[$fixtureId] = $snapshot['sha256'];
	$webRunFile = $review . '/runs/web-' . $fixtureId . '.json';
	reviewWrite($webRunFile, reviewJson(['clientid' => 7, 'base' => $base, 'documents' => [['key' => reviewKey($job['identity']), 'manifest' => basename($job['directory']) . '/manifest.json']]]));
	$preparedWeb = recoveryTestCli(['action' => 'prepare', 'run' => $webRunFile])['json']['results'][reviewKey($job['identity'])];
	$webReconstructed[$fixtureId] = $preparedWeb['sha256'];
	$publication = ['schema_version' => 1, 'status' => DEEC_DocumentReview::STATUS, 'identity' => $job['identity'], 'directory' => basename($job['directory']), 'sha256' => $preparedWeb['sha256'], 'size' => filesize($job['directory'] . '/candidates/' . $preparedWeb['sha256'] . '.pdf'),
		'snapshot_sha256' => $snapshot['sha256'], 'proof_sha256' => hash_file('sha256', $job['directory'] . '/reconstructions/' . $preparedWeb['sha256'] . '.json'),
		'database_confirmation' => ['database_values_verified' => true, 'original_comparison_performed' => false, 'confirmed_by' => 42, 'confirmed_at' => gmdate('c'), 'run_sha256' => str_repeat('1', 64), 'scope_sha256' => str_repeat('2', 64)]];
	reviewDirectory(DEEC_DocumentReview::publicationDirectory($review, $job['identity']));
	reviewWrite(DEEC_DocumentReview::publicationDirectory($review, $job['identity']) . '/' . $preparedWeb['sha256'] . '.json', reviewJson($publication));
}
function originalWebRequest(array $query, ?array $post = null, ?array $upload = null, string $cookie = '', ?int $user = 42): array
{
	global $httpAddress;
	$headers = $user === null ? [] : ['X-Review-Test-User: ' . $user];
	if ($cookie !== '') $headers[] = 'Cookie: ' . $cookie;
	$body = '';
	if ($post !== null) {
		if ($upload) {
			$boundary = 'dewawi-' . bin2hex(random_bytes(12));
			$headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
			foreach ($post as $key => $value) $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"" . $key . "\"\r\n\r\n" . $value . "\r\n";
			$body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"original_pdf\"; filename=\"" . $upload['name'] . "\"\r\nContent-Type: application/pdf\r\n\r\n" . $upload['bytes'] . "\r\n--" . $boundary . "--\r\n";
		} else { $headers[] = 'Content-Type: application/x-www-form-urlencoded'; $body = http_build_query($post); }
	}
	$context = stream_context_create(['http' => ['method' => $post === null ? 'GET' : 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 20]]);
	$bytes = file_get_contents('http://' . $httpAddress . '/?' . http_build_query($query), false, $context);
	$header = implode("\n", $http_response_header);
	preg_match('/Set-Cookie: ([^;]+)/i', $header, $match);
	return ['code' => (int)explode(' ', $http_response_header[0])[1], 'bytes' => $bytes, 'headers' => $header, 'cookie' => $match[1] ?? $cookie];
}
function originalWebForm(array $query, ?int $user = 42): array
{
	$response = originalWebRequest($query + ['view' => 1], null, null, '', $user);
	$dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $response['bytes']);
	$post = [];
	foreach ((new DOMXPath($dom))->query('//section[contains(@class,"document-original-review")]//input[@type="hidden"]') as $input) $post[$input->getAttribute('name')] = $input->getAttribute('value');
	return [$response, $post];
}
$httpLog = fopen($work . '/original-review-http.log', 'wb');
$httpServer = proc_open([PHP_BINARY, '-d', 'error_reporting=8191', '-S', $httpAddress, __DIR__ . '/document-recovery-download-fixture.php'], [0 => ['pipe', 'r'], 1 => $httpLog, 2 => $httpLog], $httpPipes, null, array_replace(getenv(), ['DEWAWI_REVIEW_TEST_SOCKET' => $socket])); fclose($httpPipes[0]);
try {
	for ($i = 0; $i < 100; $i++) { $ready = @stream_socket_client('tcp://' . $httpAddress); if ($ready) { fclose($ready); break; } usleep(50000); }
	foreach ($webJobs as $index => $job) {
		$id = $job['identity']; $query = ['module' => $id['module'], 'controller' => $id['controller'], 'id' => $id['id']];
		[$page, $post] = originalWebForm($query);
		recoveryTestCheck($page['code'] === 200 && isset($post['csrf_token']) && str_contains($page['bytes'], 'data-autosave="false"') && str_contains($page['bytes'], 'name="original_pdf"'), 'Original review forms unavailable for ' . reviewKey($id) . ': ' . $page['bytes']);
		foreach (['de_DE', 'en_US'] as $locale) {
			$panel = originalWebRequest($query + ['view' => 1, 'locale' => $locale]);
			if ($index === 0) { preg_match('~<section class="document-original-review".*?</section>~s', $panel['bytes'], $match); reviewWrite($work . '/original-review-panel-' . $locale . '.html', '<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="file://' . $root . '/css/ui.css"><body class="edit">' . $match[0] . '<script src="file://' . $root . '/library/jQuery/jquery-1.12.4.min.js"></script><script src="file://' . $root . '/library/jQuery/jquery.cookie.js"></script><script>var language="en",module="sales",controller="quote",action="view",id=301,baseUrl="/fixture",cookiePath="/fixture"; var fixtureErrors=[]; window.addEventListener("error",function(e){fixtureErrors.push(e.message);}); $.fn.datepicker=function(){return this;}; function autosize(){}</script><script src="file://' . $root . '/js/global.js"></script></body></html>'); }
		}
		$action = $query + ['action' => 'originalreview'];
		$uploadBytes = "%PDF-1.4\nRecovered original fixture " . reviewKey($id) . "\n%%EOF\n";
		$uploadSha = hash('sha256', $uploadBytes);
		$uploadPost = array_replace($post, ['operation' => 'upload', 'reference' => 'Verified historical carrier fixture/' . $id['id']]);
		$beforeCount = (int)$pdo->query('SELECT COUNT(*) FROM documentarchive')->fetchColumn();
		$upload = originalWebRequest($action, $uploadPost, ['name' => 'original.pdf', 'bytes' => $uploadBytes], $page['cookie']);
		recoveryTestCheck($upload['code'] === 302 && (int)$pdo->query('SELECT COUNT(*) FROM documentarchive')->fetchColumn() === $beforeCount, 'Upload failed or automatically archived: ' . $upload['headers'] . $upload['bytes'] . file_get_contents($work . '/original-review-http.log'));
		$uploaded = (new DEEC_DocumentReview())->candidates($id['id'], $id['module'], $id['controller'])[$uploadSha] ?? null;
		recoveryTestCheck($uploaded && $uploaded['status'] === DEEC_DocumentReview::UNREVIEWED_STATUS && $uploaded['upload']['uploaded_by'] === 42 && strtotime($uploaded['upload']['uploaded_at']) > 0 && (fileperms($uploaded['file']['path']) & 0777) === 0600, 'Uploaded provenance or protected candidate status differs');
		$download = originalWebRequest($query + ['sha256' => $uploadSha]);
		recoveryTestCheck($download['code'] === 200 && hash('sha256', $download['bytes']) === $uploadSha && str_contains($download['headers'], DEEC_DocumentReview::UNREVIEWED_STATUS), 'Uploaded PDF is not available through protected download');
		if ($index === 0) {
			$actor = Zend_Registry::get('User'); Zend_Registry::set('User', array_replace($actor, ['admin' => 1]));
			$exportDir = $base . '/files/export/7/0/7/';
			$internalName = (new DEEC_Export_Gobd($webDb, 7, 42))->export($exportDir, '2010-01-01', '2010-12-31', false, true);
			$internal = new ZipArchive(); $internal->open($exportDir . $internalName);
			$stream = $internal->getStream('document_manifest.csv'); $columns = fgetcsv($stream, 0, ';', '"', ''); $found = false;
			while (($values = fgetcsv($stream, 0, ';', '"', '')) !== false) {
				$entry = array_combine($columns, $values);
				if ($entry['sha256'] === $uploadSha) { $found = $entry['status'] === DEEC_DocumentReview::UNREVIEWED_STATUS && str_starts_with($entry['zip_path'], 'candidates/') && hash('sha256', $internal->getFromName($entry['zip_path'])) === $uploadSha; }
			}
			fclose($stream); $internal->close();
			recoveryTestCheck($found, 'Internal review export omitted or promoted an uploaded candidate');
			$externalName = (new DEEC_Export_Gobd($webDb, 7, 42))->export($exportDir, '2010-01-01', '2010-12-31', true);
			$external = new ZipArchive(); $external->open($exportDir . $externalName);
			for ($i = 0; $i < $external->numFiles; $i++) if (str_ends_with($external->getNameIndex($i), '.pdf')) recoveryTestCheck(hash('sha256', $external->getFromIndex($i)) !== $uploadSha, 'Unreviewed upload entered GoBD originals');
			$external->close(); Zend_Registry::set('User', $actor);
		}
		$receiptBytes = file_get_contents($job['directory'] . '/uploads/' . $uploadSha . '.json');
		recoveryTestCheck(originalWebRequest($action, $uploadPost, ['name' => 'original.pdf', 'bytes' => $uploadBytes], $page['cookie'])['code'] === 302 && file_get_contents($job['directory'] . '/uploads/' . $uploadSha . '.json') === $receiptBytes, 'Repeated upload rewrote provenance');
		recoveryTestCheck(originalWebRequest($action, $uploadPost, ['name' => 'relabel.pdf', 'bytes' => file_get_contents($job['directory'] . '/candidates/' . $webReconstructed[$id['id']] . '.pdf')], $page['cookie'])['code'] === 409, 'Known reconstruction was relabeled through original upload');
		$reconstructed = $index % 2 === 0;
		$approve = array_replace($post, ['operation' => 'approve', 'candidate_sha256' => $reconstructed ? $webReconstructed[$id['id']] : $uploadSha, 'comparison_kind' => $reconstructed ? 'paper_document' : 'verified_historical_origin', 'identity_match' => '1', 'content_match' => '1', 'reference' => 'Paper fixture cabinet / verified original carrier ' . $id['id'], 'reason' => 'Explicit selection after checking all saved values and competing versions']);
		foreach ([['csrf_token' => 'invalid'], ['identity_match' => ''], ['content_match' => ''], ['snapshot_sha256' => str_repeat('0', 64)], ['expected_number' => 'foreign number'], ['candidate_sha256' => str_repeat('0', 64)], ['reason' => ''], ['reference' => '']] as $bad) recoveryTestCheck(originalWebRequest($action, array_replace($approve, $bad), null, $page['cookie'])['code'] === 409, 'Invalid original approval was accepted');
		if ($reconstructed) recoveryTestCheck(originalWebRequest($action, array_replace($approve, ['comparison_kind' => 'verified_historical_origin']), null, $page['cookie'])['code'] === 409, 'Reconstruction was mislabeled as a recovered original');
		if ($index === 0) {
			recoveryTestCheck(originalWebRequest($action)['code'] === 405 && originalWebRequest($action, $approve, null, $page['cookie'], 43)['code'] === 409 && originalWebRequest($action, $approve, null, $page['cookie'], 44)['code'] === 409 && originalWebRequest($action, $approve, null, $page['cookie'], null)['code'] === 401, 'Original approval method, authentication or tenant checks failed');
			$pdo->exec("UPDATE permission SET sales = '{\"quote\":[\"view\"]}' WHERE userid = 42");
			recoveryTestCheck(originalWebRequest($action, $approve, null, $page['cookie'])['code'] === 409 && !str_contains(originalWebForm($query)[0]['bytes'], 'name="csrf_token"'), 'View-only user could approve or saw approval forms');
			$pdo->prepare('UPDATE permission SET sales = ? WHERE userid = 42')->execute([$webPermissions['sales']]);
			$collectorLock = fopen($review . '/.collector.lock', 'r+b'); flock($collectorLock, LOCK_EX);
			recoveryTestCheck(originalWebRequest($action, $approve, null, $page['cookie'])['code'] === 409, 'Concurrent collector did not block web mutation');
			flock($collectorLock, LOCK_UN); fclose($collectorLock);
			$digital = array_replace($approve, ['comparison_kind' => 'verified_dispatch_copy', 'evidence_sha256' => $uploadSha]);
			foreach (['', ['invalid'], $webReconstructed[$id['id']]] as $invalidEvidence) recoveryTestCheck(originalWebRequest($action, array_replace($digital, ['evidence_sha256' => $invalidEvidence]), null, $page['cookie'])['code'] === 409, 'Invalid or identical digital comparison evidence was accepted');
			$approve = $digital;
			$pdo->exec("CREATE TRIGGER web_fail BEFORE INSERT ON documentarchive FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'isolated web insert failure'");
			recoveryTestCheck(originalWebRequest($action, $approve, null, $page['cookie'])['code'] >= 400 && (int)$pdo->query('SELECT COUNT(*) FROM documentarchive')->fetchColumn() === $beforeCount, 'Insert error committed a web archive');
			$pdo->exec('DROP TRIGGER web_fail');
		}
		$approved = originalWebRequest($action, $approve, null, $page['cookie']);
		recoveryTestCheck($approved['code'] === 302, 'Explicit original approval failed: ' . $approved['headers'] . $approved['bytes'] . file_get_contents($work . '/original-review-http.log'));
		$row = $pdo->query('SELECT * FROM documentarchive WHERE module = ' . $pdo->quote($id['module']) . ' AND controller = ' . $pdo->quote($id['controller']) . ' AND documentid = ' . $id['id'])->fetch(PDO::FETCH_ASSOC);
		$recovery = json_decode($row['parameters'], true)['recovery'];
		recoveryTestCheck($row['sha256'] === $approve['candidate_sha256'] && $recovery['approval']['approved_by'] === 42 && strtotime($recovery['approval']['approved_at']) > 0 && $recovery['selection']['origin']['category'] === ($reconstructed ? 'reviewed_reconstruction_duplicate' : 'verified_recovered_original') && $recovery['approval']['original_provenance_verified'] === !$reconstructed && $recovery['approval']['individual_original_comparison_claimed'] === $reconstructed, 'Archive provenance category, actual user/time or original comparison is wrong');
		recoveryTestCheck(hash_file('sha256', $base . '/' . $row['path']) === $approve['candidate_sha256'] && recoverySnapshot($webDb, $id)['sha256'] === $webSnapshots[$id['id']], 'Original approval changed bytes or business rows');
		recoveryTestCheck(originalWebRequest($action, $approve, null, $page['cookie'])['code'] === 302 && $pdo->query('SELECT * FROM documentarchive WHERE id = ' . $row['id'])->fetch(PDO::FETCH_ASSOC) === $row, 'Repeated approval changed an existing original');
		$other = $reconstructed ? $uploadSha : $webReconstructed[$id['id']];
		recoveryTestCheck(originalWebRequest($action, array_replace($approve, ['candidate_sha256' => $other]), null, $page['cookie'])['code'] === 409 && originalWebRequest($action, $uploadPost, ['name' => 'original.pdf', 'bytes' => $uploadBytes], $page['cookie'])['code'] === 409, 'Existing original was replaceable');
		recoveryTestCheck(!str_contains(originalWebForm($query)[0]['bytes'], 'name="csrf_token"') && str_contains(originalWebForm($query)[0]['bytes'], $reconstructed ? 'Inhaltlich überprüftes Rekonstruktionsdoppel' : 'Verifiziert wiedergefundene historische Originaldatei') && count(glob($job['directory'] . '/selections/*.json')) >= 1 && count(glob($job['directory'] . '/approvals/*.json')) >= 1, 'Existing original still offers mutation or recovery history was lost');
	}
	$childQuery = ['module' => 'sales', 'controller' => 'quote', 'id' => 9];
	[$childPage, $childPost] = originalWebForm($childQuery, 44);
	$childApprove = array_replace($childPost, ['operation' => 'approve', 'candidate_sha256' => $childSha, 'comparison_kind' => 'paper_document', 'identity_match' => '1', 'content_match' => '1', 'reference' => 'Child paper fixture cabinet', 'reason' => 'Owner review using authorized parent contact snapshot']);
	$childValues = $pdo->query('SELECT * FROM quote WHERE id = 9')->fetch(PDO::FETCH_ASSOC);
	recoveryTestCheck(originalWebRequest($childQuery + ['action' => 'originalreview'], $childApprove, null, $childPage['cookie'], 42)['code'] === 409 && originalWebRequest($childQuery + ['action' => 'originalreview'], $childApprove, null, $childPage['cookie'], 44)['code'] === 302, 'Parent-child ownership or authorized shared-contact approval failed');
	$childArchive = $pdo->query("SELECT * FROM documentarchive WHERE clientid = 9 AND module = 'sales' AND controller = 'quote' AND documentid = 9")->fetch(PDO::FETCH_ASSOC);
	recoveryTestCheck($childArchive['sha256'] === $childSha && (int)$childArchive['archivedby'] === 44 && $pdo->query('SELECT * FROM quote WHERE id = 9')->fetch(PDO::FETCH_ASSOC) === $childValues, 'Child approval changed ownership or saved business values');
	$diagnosticJob = $webService->job(209, 'sales', 'invoice');
	[$diagnosticPage, $diagnosticPost] = originalWebForm(['module' => 'sales', 'controller' => 'invoice', 'id' => 209]);
	$diagnosticApprove = array_replace($diagnosticPost, ['operation' => 'approve', 'candidate_sha256' => $blockedSha, 'comparison_kind' => 'paper_document', 'identity_match' => '1', 'content_match' => '1', 'reference' => 'paper fixture', 'reason' => 'must remain forbidden']);
	recoveryTestCheck(originalWebRequest(['module' => 'sales', 'controller' => 'invoice', 'id' => 209, 'action' => 'originalreview'], $diagnosticApprove, null, $diagnosticPage['cookie'])['code'] === 409, 'Diagnostic approved through document view');
	$diagnosticOriginalBytes = "%PDF-1.4\nRecovered diagnostic comparison fixture\n%%EOF\n";
	$diagnosticAction = ['module' => 'sales', 'controller' => 'invoice', 'id' => 209, 'action' => 'originalreview'];
	recoveryTestCheck(originalWebRequest($diagnosticAction, array_replace($diagnosticPost, ['operation' => 'upload', 'reference' => 'Original carrier for resolving diagnostic case']), ['name' => 'found-original.pdf', 'bytes' => $diagnosticOriginalBytes], $diagnosticPage['cookie'])['code'] === 302, 'Diagnostic case could not collect an original for investigation');
	recoveryTestCheck(originalWebRequest($diagnosticAction, array_replace($diagnosticApprove, ['candidate_sha256' => hash('sha256', $diagnosticOriginalBytes)]), null, $diagnosticPage['cookie'])['code'] === 409, 'Unresolved diagnostic business errors were bypassed through another candidate');
	$localPost = array_replace($diagnosticPost, ['csrf_token' => DEEC_DocumentRecovery::token($diagnosticJob['identity']), 'reference' => 'Local upload validation fixture']);
	foreach (['bad.php', '../bad.pdf', '.hidden.pdf'] as $name) {
		try { $webService->upload($diagnosticJob, $localPost, $diagnosticPdfPath, $name); throw new LogicException('Unsafe original filename accepted'); }
		catch (RuntimeException $error) { recoveryTestCheck($error->getMessage() === 'Invalid original PDF upload', 'Filename test failed before validating its filename: ' . $error->getMessage()); }
	}
	try { $webService->upload($diagnosticJob, $localPost, $diagnosticPdfPath, 'copy.pdf'); throw new LogicException('Known diagnostic PDF accepted'); }
	catch (RuntimeException $error) { recoveryTestCheck($error->getMessage() === 'Diagnostic PDFs cannot be uploaded as originals', 'Diagnostic upload test failed for a different reason: ' . $error->getMessage()); }
} finally { proc_terminate($httpServer); proc_close($httpServer); fclose($httpLog); }
foreach ($originalsBeforeWeb as $row) recoveryTestCheck($pdo->query('SELECT * FROM documentarchive WHERE id = ' . (int)$row['id'])->fetch(PDO::FETCH_ASSOC) === $row, 'Web review changed an existing archive original');
$pdo->prepare('UPDATE permission SET sales = ?, purchases = ? WHERE userid = 42')->execute([$permissions['sales'], $permissions['purchases']]);
$webDb->closeConnection();
