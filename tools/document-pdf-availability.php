<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/library/DEEC/UpdateMaintenance.php';
DEEC_UpdateMaintenance::protectInstallation(dirname(__DIR__));
require_once __DIR__ . '/document-historical-recovery.php';

function availabilityScope(Zend_Db_Adapter_Abstract $db, string $scope, int $client): array
{
	$rows = [];
	if ($scope === 'all') {
		foreach (DEEC_DocumentArchive::documents() as $module => $controllers) foreach ($controllers as $controller) {
			$query = $db->query("SELECT * FROM `$controller` WHERE clientid = ? AND deleted = 0 ORDER BY id", [$client]);
			while ($row = $query->fetch()) if (DEEC_Permission::isDocumentReadonly($module, $controller, $row)) $rows[] = ['module' => $module, 'controller' => $controller, 'id' => (int)$row['id']];
		}
	} else {
		DEEC_DocumentReview::privateFile($scope);
		$rows = recoveryJsonFile($scope)['documents'] ?? null;
		if (!is_array($rows) || !array_is_list($rows)) throw new RuntimeException('Scope requires a documents list');
	}
	$seen = [];
	foreach ($rows as &$row) {
		if (!is_array($row) || !DEEC_DocumentArchive::validDocumentType($row['module'] ?? '', $row['controller'] ?? '')) throw new RuntimeException('Invalid scope document type');
		if (isset($row['clientid']) && (int)$row['clientid'] !== $client) throw new RuntimeException('Foreign tenant in scope');
		$row = ['module' => $row['module'], 'controller' => $row['controller'], 'id' => reviewId($row['id'] ?? null)];
		$key = reviewKey($row); if (isset($seen[$key])) throw new RuntimeException('Duplicate scope document'); $seen[$key] = true;
	}
	unset($row);
	return $rows;
}

function availabilityPdf(array $result): array
{
	if (!isset($result['stream'])) return ['availability' => 'copy_can_be_generated', 'filename' => $result['filename']];
	try { $bytes = stream_get_contents($result['stream']); } finally { fclose($result['stream']); }
	if ($bytes === false || strlen($bytes) !== $result['size'] || hash('sha256', $bytes) !== $result['sha256'] || !str_starts_with($bytes, '%PDF-') || !str_ends_with(rtrim($bytes), '%%EOF')) throw new RuntimeException('PDF response bytes failed validation');
	return ['availability' => $result['status'] === 'archived_original' ? 'original_available' : 'copy_available', 'pdf_status' => $result['status'], 'filename' => $result['filename'], 'size' => $result['size'], 'sha256' => $result['sha256']];
}

function availabilityRow(Zend_Db_Adapter_Abstract $db, array $identity, bool $generate = false): array
{
	$row = ['kind' => 'document', 'identity' => $identity, 'classification' => 'blocked_with_specific_reason', 'local_verified' => false, 'http_verified' => false];
	try {
		$snapshot = DEEC_DocumentReview::viewingSnapshot($identity['id'], $identity['module'], $identity['controller']);
		$row['document_number'] = (string)($snapshot['data']['document'][$identity['controller'] . 'id'] ?? '');
		$row['snapshot_sha256'] = $snapshot['sha256'];
		$row += availabilityPdf((new DEEC_DocumentReview())->openViewingCopy($identity['id'], $identity['module'], $identity['controller'], $generate));
		if (DEEC_DocumentReview::viewingSnapshot($identity['id'], $identity['module'], $identity['controller'])['sha256'] !== $row['snapshot_sha256']) throw new RuntimeException('Stored document changed while checking PDF availability');
		$row['classification'] = $row['availability'];
		if (!$generate && $row['availability'] === 'copy_can_be_generated') {
			$recovery = historicalRecoveryRow($db, $identity + ['clientid' => (int)Zend_Registry::get('Client')['id'], 'document_number' => $row['document_number']], '', false, 'file-association');
			$row['original_recovery'] = ['rule' => HISTORICAL_FILE_RULE, 'eligible' => $recovery['action'] === 'recover', 'reason' => $recovery['reason'], 'independent_provenance_verified' => false];
			if ($recovery['action'] === 'recover') $row['classification'] = 'original_recovery_candidate';
		}
	} catch (Throwable $error) { $row['classification'] = 'blocked_with_specific_reason'; $row['reason'] = $error->getMessage(); }
	return $row;
}

function availabilityHttp(string $base, string $cookie, array $row): array
{
	$results = [];
	foreach (['download' => 'attachment', 'preview' => 'inline'] as $action => $disposition) {
		$id = $row['identity']; $url = $base . '/' . $id['module'] . '/' . $id['controller'] . '/' . $action . '/id/' . $id['id'];
		$client = new Zend_Http_Client($url, ['timeout' => 60, 'maxredirects' => 0]);
		$client->getAdapter()->setStreamContext(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]]);
		$client->setHeaders(['Cookie' => $cookie, 'Accept' => 'application/pdf', 'Accept-Encoding' => 'identity']);
		$response = $client->request('GET'); $bytes = $response->getBody();
		if ($response->getStatus() !== 200 || strtolower(trim(explode(';', (string)$response->getHeader('Content-Type'))[0])) !== 'application/pdf'
			|| !str_starts_with((string)$response->getHeader('Content-Disposition'), $disposition . ';') || !str_contains((string)$response->getHeader('Content-Disposition'), "filename*=UTF-8''" . rawurlencode($row['filename']))
			|| !str_contains((string)$response->getHeader('Cache-Control'), 'private') || !str_contains((string)$response->getHeader('Cache-Control'), 'no-store') || $response->getHeader('X-Content-Type-Options') !== 'nosniff'
			|| strlen($bytes) !== $row['size'] || hash('sha256', $bytes) !== $row['sha256']) throw new RuntimeException('Authenticated HTTP PDF verification failed for ' . $action . ' (status ' . $response->getStatus() . '); check session, locale, tenant, permissions and web-server file access');
		$results[$action] = ['status' => 200, 'mime' => 'application/pdf', 'disposition' => $disposition, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes)];
	}
	return ['http_verified' => true, 'http_checked_at' => gmdate('c'), 'http' => $results];
}

function availabilityReportRows(string $path, bool $partial = false): Generator
{
	DEEC_DocumentReview::privateFile($path);
	$stream = fopen($path, 'rb');
	try {
		while (($line = fgets($stream, 8388609)) !== false) {
			if (strlen($line) >= 8388608) throw new RuntimeException('Availability report line exceeds limit');
			try { $row = json_decode($line, true, 64, JSON_THROW_ON_ERROR); }
			catch (JsonException $error) { if ($partial && feof($stream) && !str_ends_with($line, "\n")) break; throw $error; }
			if (!is_array($row)) throw new RuntimeException('Invalid availability report row');
			yield $row;
		}
		if (!feof($stream)) throw new RuntimeException('Availability report read failed');
	} finally { fclose($stream); }
}

function availabilityMain(array $options): array
{
	umask(0077);
	foreach (['config', 'base', 'client', 'operator-user', 'web-user', 'action', 'output'] as $key) if (!is_string($options[$key] ?? null) || $options[$key] === '') throw new RuntimeException('Required option: --' . $key);
	$action = $options['action']; if (!in_array($action, ['preview', 'apply'], true)) throw new RuntimeException('Action must be preview or apply');
	$verification = $options['verification'] ?? 'http';
	if (!in_array($verification, ['http', 'local'], true)) throw new RuntimeException('Verification must be http or local');
	$verifiedField = $verification . '_verified'; $checkedAtField = $verification . '_checked_at';
	$uid = DEEC_DocumentArchive::requirePublicationUser($options['web-user']);
	$httpBase = null;
	if ($verification === 'http') {
		if (!is_string($options['http-base'] ?? null) || $options['http-base'] === '') throw new RuntimeException('Required option: --http-base');
		$httpBase = rtrim($options['http-base'], '/'); $url = parse_url($httpBase);
		if (!$url || !isset($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || !in_array($url['scheme'] ?? '', ['http', 'https'], true) || ($url['scheme'] === 'http' && !in_array($url['host'], ['127.0.0.1', 'localhost', '[::1]'], true))) throw new RuntimeException('HTTP base must be HTTPS, without credentials/query/fragment (HTTP allowed only on loopback)');
	}
	$locale = $options['locale'] ?? 'de_DE'; if (!in_array($locale, ['de_DE', 'en_US'], true)) throw new RuntimeException('Locale must be de_DE or en_US');
	$limit = isset($options['limit']) ? reviewId($options['limit']) : PHP_INT_MAX;
	DEEC_DocumentReview::privateFile($options['config']);
	$db = recoveryBoot(migrationConfig($options['config'], $options['environment'] ?? 'production'), $options['base'], reviewId($options['client']), reviewId($options['operator-user']));
	if (empty(Zend_Registry::get('User')['admin'])) throw new RuntimeException('PDF availability requires an authorized administrator');
	Zend_Registry::set('Zend_Locale', new Zend_Locale($locale)); Zend_Registry::set('Zend_Currency', new Zend_Currency(['locale' => $locale, 'precision' => 2]));
	$translator = new DEEC_Translate($locale); $translator->loadDir('default', dirname(__DIR__) . '/languages/' . $locale . '/default'); Zend_Registry::set('DEEC_Translate', $translator);
	Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer')->view = new Zend_View();
	$header = ['kind' => 'header', 'schema_version' => 1, 'base' => BASE_PATH, 'clientid' => (int)$options['client'], 'operator_user' => (int)$options['operator-user'], 'publication_uid' => $uid, 'locale' => $locale, 'verification' => $verification, 'http_base' => $httpBase];
	$planRows = []; $planSha = null; $cookie = ''; $resume = []; $resumeSha = null;
	if ($action === 'preview') $scope = availabilityScope($db, $options['scope'] ?? '', $header['clientid']);
	else {
		DEEC_DocumentReview::privateFile($options['plan'] ?? ''); $planSha = hash_file('sha256', $options['plan']);
		if (!is_string($options['confirm-plan'] ?? null) || !hash_equals($planSha, $options['confirm-plan'])) throw new RuntimeException('Apply requires the exact reviewed --confirm-plan SHA-256');
		$planHeader = null; $complete = false;
		foreach (availabilityReportRows($options['plan']) as $row) {
			if ($complete) throw new RuntimeException('Unexpected records after plan summary');
			if ($row['kind'] === 'header' && $planHeader === null && !$planRows) $planHeader = $row;
			elseif ($row['kind'] === 'document' && $planHeader !== null) { $key = reviewKey($row['identity']); if (isset($planRows[$key])) throw new RuntimeException('Duplicate plan document'); $planRows[$key] = array_intersect_key($row, array_flip(['identity', 'snapshot_sha256'])); }
			elseif ($row['kind'] === 'summary') $complete = true;
			else throw new RuntimeException('Invalid plan record');
		}
		if (!hash_equals($planSha, hash_file('sha256', $options['plan']))) throw new RuntimeException('Reviewed plan changed while reading');
		// A reviewed HTTP preview can also authorize local-only backfill; its URL is not local evidence.
		foreach ($header as $key => $value) {
			if ($key === 'verification' || ($verification === 'local' && $key === 'http_base')) continue;
			if (($planHeader[$key] ?? null) !== $value) throw new RuntimeException('Reviewed availability plan context differs: ' . $key);
		}
		$planVerification = $planHeader['verification'] ?? 'http';
		if (!in_array($planVerification, ['http', 'local'], true) || ($verification === 'http' && $planVerification !== 'http')) throw new RuntimeException('Reviewed availability plan verification differs');
		if (!$complete || ($planHeader['action'] ?? '') !== 'preview' || ($planHeader['documents'] ?? null) !== count($planRows)) throw new RuntimeException('A complete reviewed preview is required');
		$scope = array_column($planRows, 'identity');
		if ($verification === 'http') {
			DEEC_DocumentReview::privateFile($options['cookie-file'] ?? ''); $cookie = trim(file_get_contents($options['cookie-file']));
			if ($cookie === '' || strlen($cookie) > 16384 || preg_match('/[\x00-\x1f\x7f]/', $cookie)) throw new RuntimeException('Private cookie file must contain one Cookie header value');
		}
		if (isset($options['resume'])) {
			$first = true; DEEC_DocumentReview::privateFile($options['resume']); $resumeSha = hash_file('sha256', $options['resume']);
			foreach (availabilityReportRows($options['resume'], true) as $row) {
				if ($first) {
					if (!isset($row['verification'])) $row['verification'] = 'http';
					foreach ($header + ['action' => 'apply', 'plan_sha256' => $planSha] as $key => $value) if (($row[$key] ?? null) !== $value) throw new RuntimeException('Resume report context differs');
					$first = false;
				} elseif (($row['kind'] ?? '') === 'document' && !empty($row[$verifiedField])) $resume[reviewKey($row['identity'])] = array_intersect_key($row, array_flip(['snapshot_sha256', 'sha256', 'filename', 'size', $checkedAtField]));
			}
			if ($first || !hash_equals($resumeSha, hash_file('sha256', $options['resume']))) throw new RuntimeException('Resume report empty or changed while reading');
		}
	}
	$header += ['action' => $action, 'plan_sha256' => $planSha, 'scope_sha256' => hash('sha256', reviewJson($scope)), 'documents' => count($scope), 'recorded_at' => gmdate('c')];
	if ($resumeSha !== null) $header['resume_report'] = ['path' => $options['resume'], 'sha256' => $resumeSha];
	if ($action === 'apply') $header['approval'] = ['method' => 'administrator_reviewed_copy_backfill', 'approved_by' => $header['operator_user'], 'approved_at' => gmdate('c'), 'plan_sha256' => $planSha, 'original_registration_allowed' => false];
	if ($action === 'apply' && ($planHeader['scope_sha256'] ?? '') !== $header['scope_sha256']) throw new RuntimeException('Reviewed scope differs');
	$output = $options['output']; reviewSafePath($output); DEEC_DocumentReview::privateDirectory(dirname($output));
	if (file_exists($output) || is_link($output)) throw new RuntimeException('A new private output directory is required');
	reviewDirectory($output); $report = $output . '/report.jsonl'; $stream = fopen($report, 'x+b'); chmod($report, 0600);
	$write = static function(array $row) use ($stream): void { $bytes = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"; if (fwrite($stream, $bytes) !== strlen($bytes) || !fflush($stream)) throw new RuntimeException('Private report write failed'); };
	$summary = ['kind' => 'summary', 'documents' => count($scope), 'classifications' => [], 'local_verified' => 0, 'local_verifications_reused' => 0, 'http_verified' => 0, 'http_verifications_reused' => 0, 'pending' => 0, 'blocked' => 0]; $attempted = 0;
	try {
		$write($header);
		foreach ($scope as $index => $identity) {
			$row = availabilityRow($db, $identity); $key = reviewKey($identity);
			if ($action === 'apply' && $row['classification'] !== 'blocked_with_specific_reason') try {
				$planned = $planRows[$key];
				if (($planned['snapshot_sha256'] ?? '') !== $row['snapshot_sha256']) throw new RuntimeException('Stored document changed since preview; review a fresh preview');
				$prior = $resume[$key] ?? null;
				if ($prior && ($prior['snapshot_sha256'] ?? '') === $row['snapshot_sha256'] && ($prior['sha256'] ?? '') === ($row['sha256'] ?? null) && ($prior['filename'] ?? '') === $row['filename'] && ($prior['size'] ?? '') === ($row['size'] ?? null)) {
					$row[$verifiedField] = true; $row[$checkedAtField] = $prior[$checkedAtField]; $row['verification_reused'] = true;
				} elseif ($attempted < $limit) {
					$attempted++;
					if ($verification === 'http' || $row['availability'] === 'copy_can_be_generated') $row = availabilityRow($db, $identity, true);
					if ($row['classification'] === 'blocked_with_specific_reason') throw new RuntimeException($row['reason']);
					if ($row['snapshot_sha256'] !== $planned['snapshot_sha256']) throw new RuntimeException('Stored document changed during backfill; review a fresh preview');
					if ($verification === 'http') $row = array_replace($row, availabilityHttp($httpBase, $cookie, $row));
					else {
						if (!in_array($row['availability'], ['original_available', 'copy_available'], true)) throw new RuntimeException('PDF unavailable after local backfill');
						// openViewingCopy already validates the selected locale's immutable receipt and PDF bytes.
						$row['local_verified'] = true; $row['local_checked_at'] = gmdate('c');
					}
					if (DEEC_DocumentReview::viewingSnapshot($identity['id'], $identity['module'], $identity['controller'])['sha256'] !== $planned['snapshot_sha256']) throw new RuntimeException('Stored document changed during ' . $verification . ' verification');
				} else { $row['pending'] = true; $summary['pending']++; }
			} catch (Throwable $error) { $row['classification'] = 'blocked_with_specific_reason'; $row['local_verified'] = false; $row['http_verified'] = false; $row['reason'] = $error->getMessage(); }
			$summary['classifications'][$row['classification']] = ($summary['classifications'][$row['classification']] ?? 0) + 1;
			$summary['local_verifications_reused'] += (int)($row['local_verified'] && !empty($row['verification_reused']));
			$summary['local_verified'] += (int)$row['local_verified'];
			$summary['http_verifications_reused'] += (int)($row['http_verified'] && !empty($row['verification_reused']));
			$summary['http_verified'] += (int)$row['http_verified']; $summary['blocked'] += (int)($row['classification'] === 'blocked_with_specific_reason'); $write($row);
			if (($index + 1) % 100 === 0 || $index + 1 === count($scope)) fwrite(STDERR, ($index + 1) . '/' . count($scope) . " assessed\n");
		}
		$summary['local_verified_this_run'] = $summary['local_verified'] - $summary['local_verifications_reused'];
		$summary['every_scoped_document_local_verified'] = $action === 'apply' && $verification === 'local' && $summary['local_verified'] === count($scope);
		$summary['http_verified_this_run'] = $summary['http_verified'] - $summary['http_verifications_reused'];
		$summary['every_scoped_document_http_verified'] = $action === 'apply' && $verification === 'http' && $summary['http_verified'] === count($scope); $write($summary);
	} finally { fclose($stream); }
	reviewWrite($output . '/summary.json', reviewJson($header + ['coverage' => $summary]));
	return ['report' => $report, 'report_sha256' => hash_file('sha256', $report), 'coverage' => $summary, 'exit' => $summary['blocked'] || $summary['pending'] ? 2 : 0];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;
try {
	$result = availabilityMain(getopt('', ['config:', 'environment:', 'base:', 'client:', 'operator-user:', 'web-user:', 'action:', 'output:', 'scope:', 'locale:', 'plan:', 'confirm-plan:', 'http-base:', 'cookie-file:', 'resume:', 'limit:', 'verification:']));
	echo reviewJson($result); exit($result['exit']);
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
