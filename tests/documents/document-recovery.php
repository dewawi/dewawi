<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/document-review.php';

function recoveryBoot(array $settings, string $base, int $clientId, int $userId): Zend_Db_Adapter_Abstract
{
	$root = dirname(__DIR__, 2);
	reviewSafePath($base);
	if (realpath($base) !== $base) throw new RuntimeException('A canonical source base is required');
	if (!defined('BASE_PATH')) define('BASE_PATH', $base);
	set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
	spl_autoload_register(static function(string $class) use ($root): void {
		$parts = explode('_', $class);
		$path = $root . '/library/' . str_replace('_', '/', $class) . '.php';
		if (is_file($path)) { require_once $path; return; }
		if (count($parts) < 3) return;
		$module = array_shift($parts);
		$directory = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
		$path = $root . $directory . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
		if (is_file($path)) require_once $path;
	});
	$db = Zend_Db::factory('Pdo_Mysql', $settings + ['charset' => 'utf8mb4']);
	Zend_Db_Table::setDefaultAdapter($db);
	$user = $db->fetchRow('SELECT id, clientid, admin, name, activated, deleted FROM user WHERE id = ? AND activated = 1 AND deleted = 0', [$userId]);
	if (!$user) throw new RuntimeException('Active operator account is required');
	Zend_Registry::set('User', $user);
	if (!array_key_exists($clientId, (new Application_Model_DbTable_Client())->getSwitchableClients())) throw new RuntimeException('Operator cannot access this tenant');
	$client = $db->fetchRow('SELECT * FROM client WHERE id = ? AND deleted = 0', [$clientId]);
	if (!$client) throw new RuntimeException('Tenant is unavailable');
	$client['modules'] = ['contacts' => (int)($client['parentid'] ?: $clientId), 'items' => (int)($client['parentid'] ?: $clientId), 'sales' => $clientId, 'purchases' => $clientId];
	$user['clientid'] = $clientId;
	Zend_Registry::set('User', $user);
	Zend_Registry::set('Client', $client);
	$config = $db->fetchRow('SELECT timezone FROM config WHERE clientid = ? ORDER BY id LIMIT 1', [$clientId]);
	date_default_timezone_set((new DateTimeZone(($config['timezone'] ?? '') ?: 'UTC'))->getName());
	Zend_Controller_Action_HelperBroker::addPath($root . '/application/controllers/helpers', 'Application_Controller_Action_Helper');
	Zend_Registry::set('Zend_Locale', new Zend_Locale('de_DE'));
	Zend_Registry::set('Zend_Currency', new Zend_Currency(['locale' => 'de_DE', 'precision' => 2]));
	return $db;
}

function recoverySnapshot(Zend_Db_Adapter_Abstract $db, array $identity, bool $lock = false): array
{
	$identity = reviewIdentity($identity, (int)Zend_Registry::get('Client')['id']);
	$permission = new DEEC_Permission((array)Zend_Registry::get('User'));
	$context = $permission->getObjectContext($identity['id'], $identity['module'], $identity['controller']);
	if (!$context || $context['clientid'] !== $identity['clientid'] || !$permission->hasPermission($identity['module'], $identity['controller'], 'view') || !$permission->hasPermission($identity['module'], $identity['controller'], 'edit')) throw new RuntimeException('Document recovery permission denied');
	$table = $identity['controller'];
	$suffix = $lock ? ' FOR UPDATE' : '';
	$document = $db->fetchRow("SELECT * FROM `$table` WHERE id = ? AND clientid = ? AND deleted = 0" . $suffix, [$identity['id'], $identity['clientid']]);
	if (!$document || (string)($document[$table . 'id'] ?? '') !== $identity['document_number'] || !DEEC_Permission::isDocumentReadonly($identity['module'], $table, $document)) throw new RuntimeException('Issued document identity changed');
	$snapshot = ['document' => $document];
	foreach (['pos', 'posset'] as $type) $snapshot[$type] = $db->fetchAll("SELECT * FROM `{$table}{$type}` WHERE parentid = ? AND clientid = ? ORDER BY id" . $suffix, [$identity['id'], $identity['clientid']]);
	$snapshot['rules'] = $db->fetchAll("SELECT * FROM pricerulepos WHERE clientid = ? AND module = ? AND controller = ? AND parentid IN (SELECT id FROM `{$table}pos` WHERE parentid = ? AND clientid = ?) ORDER BY id" . $suffix, [$identity['clientid'], $identity['module'], $table . 'pos', $identity['id'], $identity['clientid']]);
	return ['data' => $snapshot, 'sha256' => hash('sha256', reviewJson($snapshot))];
}

function recoveryJsonFile(string $path): array
{
	reviewPrivateFile($path);
	$value = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
	if (!is_array($value)) throw new RuntimeException('Invalid recovery JSON');
	return $value;
}

function recoveryPath(string $root, string $relative): string
{
	if ($relative === '' || $relative[0] === '/') throw new RuntimeException('Relative review path is required');
	$path = $root . '/' . $relative;
	reviewSafePath($path);
	return $path;
}

function recoveryJobs(string $root, string $runFile, int $clientId): array
{
	reviewDirectory($root);
	$binding = recoveryJsonFile($root . '/review.json');
	if (($binding['clientid'] ?? 0) !== $clientId) throw new RuntimeException('Foreign review tenant');
	foreach ([$binding['base'], dirname(__DIR__, 2)] as $webroot) if ($root === $webroot || str_starts_with($root, $webroot . '/') || str_starts_with($webroot, $root . '/')) throw new RuntimeException('Recovery area must be outside source and application webroots');
	reviewSafePath($runFile);
	if (!str_starts_with($runFile, $root . '/runs/')) throw new RuntimeException('Run must belong to the review root');
	$run = recoveryJsonFile($runFile);
	if (($run['clientid'] ?? 0) !== $clientId || ($run['base'] ?? '') !== $binding['base']) throw new RuntimeException('Review run context differs');
	$jobs = [];
	foreach ($run['documents'] as $row) {
		$path = recoveryPath($root, $row['manifest']);
		if (dirname(dirname($path)) !== $root) throw new RuntimeException('Document manifest must be in its own review directory');
		reviewDirectory(dirname($path));
		reviewDirectory(dirname($path) . '/candidates');
		reviewDirectory(dirname($path) . '/external');
		$manifest = recoveryJsonFile($path);
		$identity = reviewIdentity($manifest['selected'], $clientId);
		$key = reviewKey($identity);
		if ($key !== $row['key'] || isset($jobs[$key])) throw new RuntimeException('Review manifest identity differs');
		$jobs[$key] = ['identity' => $identity, 'manifest' => $manifest, 'directory' => dirname($path)];
	}
	return ['binding' => $binding, 'jobs' => $jobs];
}

function recoverySelection(array $job, DEEC_DocumentArchive $reviewArchive, ?array $selection = null): array
{
	$selection = $selection ?? recoveryJsonFile($job['directory'] . '/selection.json');
	if (($selection['decision'] ?? '') !== 'selected' || reviewIdentity($selection['document'] ?? [], $job['identity']['clientid']) !== $job['identity']) throw new RuntimeException('Explicit manual document selection is required');
	foreach (['reason', 'reviewed_at'] as $field) if (!is_string($selection[$field] ?? null) || trim($selection[$field]) === '') throw new RuntimeException('Manual selection reason and review time are required');
	if (!strtotime($selection['reviewed_at']) || reviewId($selection['reviewed_by'] ?? null) <= 0) throw new RuntimeException('Invalid manual reviewer');
	$kind = $selection['origin']['kind'] ?? '';
	if (!in_array($kind, ['historical_pdf', 'datev', 'email', 'reconstructed'], true) || empty($selection['origin']['source_path'])) throw new RuntimeException('Selection provenance is incomplete');
	$relative = $selection['candidate_path'] ?? '';
	if (!is_string($relative) || !preg_match('~^(?:candidates/[a-f0-9]{64}\.pdf|external/[^/]+\.pdf)$~D', $relative)) throw new RuntimeException('Invalid selected review candidate');
	$path = recoveryPath($job['directory'], $relative);
	reviewPrivateFile($path);
	$file = $reviewArchive->inspect($path, 'pdf');
	if ($file['status'] !== 'ok' || !is_string($selection['sha256'] ?? null) || !hash_equals($file['sha256'], $selection['sha256'])) throw new RuntimeException('Selected candidate integrity differs');
	$proven = str_starts_with($relative, 'external/') && in_array($kind, ['datev', 'email', 'historical_pdf'], true);
	if (str_starts_with($relative, 'candidates/')) {
		if (basename($relative, '.pdf') !== $file['sha256']) throw new RuntimeException('Candidate hash filename differs');
		foreach (glob($job['directory'] . '/manifest*.json') as $manifestPath) {
			$manifest = recoveryJsonFile($manifestPath);
			if (reviewIdentity($manifest['selected'] ?? [], $job['identity']['clientid']) !== $job['identity']) throw new RuntimeException('Candidate manifest identity differs');
			foreach ($manifest['candidates'] as $candidate) if ($candidate['path'] === $relative && $candidate['sha256'] === $file['sha256'] && $kind === 'historical_pdf') $proven = true;
		}
		if ($kind === 'reconstructed') {
			$reconstruction = recoveryJsonFile($job['directory'] . '/reconstructions/' . $file['sha256'] . '.json');
			$proven = ($reconstruction['identity'] ?? null) === $job['identity'] && ($reconstruction['sha256'] ?? '') === $file['sha256'];
		}
	}
	if (!$proven) throw new RuntimeException('Selected PDF has no documented provenance');
	return ['selection' => $selection, 'selection_sha256' => hash_file('sha256', $job['directory'] . '/selection.json'), 'file' => $file];
}

function recoveryArchives(Zend_Db_Adapter_Abstract $db, array $identity, bool $lock = false): array
{
	return $db->fetchAll('SELECT * FROM documentarchive WHERE clientid = ? AND module = ? AND controller = ? AND documentid = ? AND format = ? ORDER BY version' . ($lock ? ' FOR UPDATE' : ''), [$identity['clientid'], $identity['module'], $identity['controller'], $identity['id'], 'pdf']);
}

function recoveryPrepare(Zend_Db_Adapter_Abstract $db, array $job, string $root, DEEC_Pdf $renderer): array
{
	$identity = $job['identity'];
	$snapshot = recoverySnapshot($db, $identity);
	$archive = new DEEC_DocumentArchive(BASE_PATH);
	$canonical = $archive->inspect($archive->path($identity['clientid'], $identity['module'], $identity['controller'], $identity['id']), 'pdf');
	if (recoveryArchives($db, $identity) || $canonical['status'] !== 'missing') throw new RuntimeException('Existing archive registration or canonical file must not be regenerated');
	foreach (glob($job['directory'] . '/reconstructions/*.json') as $path) {
		$record = recoveryJsonFile($path);
		if (($record['identity'] ?? null) !== $identity) throw new RuntimeException('Foreign reconstruction record');
		if (($record['snapshot_sha256'] ?? '') !== $snapshot['sha256'] || ($record['validation']['schema_version'] ?? 0) !== 1
			|| ($record['dependencies']['renderer_sha256'] ?? '') !== hash_file('sha256', dirname(__DIR__, 2) . '/library/DEEC/Pdf.php')) continue;
		$file = (new DEEC_DocumentArchive($root))->inspect($job['directory'] . '/candidates/' . $record['sha256'] . '.pdf', 'pdf');
		if ($file['status'] === 'ok' && $file['sha256'] === $record['sha256']) return ['status' => 'already_prepared', 'sha256' => $file['sha256'], 'warnings' => $record['validation']['warnings'], 'original_comparison_required' => $record['validation']['original_comparison_required']];
	}
	$rendered = $renderer->reconstruct($identity['id'], $identity['module'], $identity['controller']);
	if (recoverySnapshot($db, $identity)['sha256'] !== $snapshot['sha256']) throw new RuntimeException('Rendering changed stored business values');
	$sha = hash('sha256', $rendered['bytes']);
	if (strlen($rendered['bytes']) < 12 || !str_starts_with($rendered['bytes'], '%PDF-') || !str_ends_with(rtrim($rendered['bytes']), '%%EOF')) throw new RuntimeException('Reconstructed PDF is incomplete');
	$path = $job['directory'] . '/candidates/' . $sha . '.pdf';
	reviewWrite($path, $rendered['bytes']);
	$file = (new DEEC_DocumentArchive($root))->inspect($path, 'pdf');
	if ($file['status'] !== 'ok' || $file['sha256'] !== $sha || $file['size'] !== strlen($rendered['bytes'])) throw new RuntimeException('Reconstructed PDF is incomplete');
	reviewDirectory($job['directory'] . '/reconstructions');
	$record = ['schema_version' => 1, 'identity' => $identity, 'generated_at' => gmdate('c'), 'generated_by' => (int)Zend_Registry::get('User')['id'], 'size' => $file['size'], 'sha256' => $sha,
		'snapshot_sha256' => $snapshot['sha256'], 'dependencies' => $rendered['dependencies'], 'validation' => $rendered['validation'], 'fiscal_correction' => false, 'manual_approval_required' => true];
	reviewWrite($job['directory'] . '/reconstructions/' . $sha . '.json', reviewJson($record));
	return ['status' => 'prepared', 'sha256' => $sha, 'candidate_path' => 'candidates/' . $sha . '.pdf', 'warnings' => $rendered['validation']['warnings'], 'original_comparison_required' => $rendered['validation']['original_comparison_required']];
}

function recoveryReconstructionProof(array $job, array $selected, array $snapshot, DEEC_DocumentArchive $reviewArchive): ?string
{
	if ($selected['selection']['origin']['kind'] !== 'reconstructed') return null;
	$recordPath = $job['directory'] . '/reconstructions/' . $selected['file']['sha256'] . '.json';
	$record = recoveryJsonFile($recordPath);
	if (($record['snapshot_sha256'] ?? '') !== $snapshot['sha256'] || ($record['validation']['schema_version'] ?? 0) !== 1 || !isset($record['validation']['displayed'])) throw new RuntimeException('Reconstruction requires a current amount validation; prepare and review again');
	if (!empty($record['validation']['original_comparison_required'])) {
		$comparison = $selected['selection']['original_comparison'] ?? [];
		if (!in_array($comparison['kind'] ?? '', ['original_document', 'verified_dispatch_copy', 'paper_document'], true) || !is_string($comparison['reference'] ?? null) || trim($comparison['reference']) === ''
			|| ($comparison['amounts_match'] ?? false) !== true || ($comparison['compared_by'] ?? 0) !== (int)$selected['selection']['reviewed_by'] || !is_string($comparison['compared_at'] ?? null) || !strtotime($comparison['compared_at'])) throw new RuntimeException('Reconstruction warnings require an explicit documented comparison with an original, verified dispatch copy or paper document');
		if ($comparison['kind'] !== 'paper_document') {
			if (!is_string($comparison['evidence_path'] ?? null) || !preg_match('~^external/[^/]+\.pdf$~D', $comparison['evidence_path'])) throw new RuntimeException('Original comparison requires a private external evidence PDF');
			$path = recoveryPath($job['directory'], $comparison['evidence_path']);
			reviewPrivateFile($path);
			$file = $reviewArchive->inspect($path, 'pdf');
			if ($file['status'] !== 'ok' || ($comparison['evidence_sha256'] ?? '') !== $file['sha256'] || $file['sha256'] === $selected['file']['sha256']) throw new RuntimeException('Original comparison evidence is missing, changed or is the reconstruction itself');
		}
	}
	return hash_file('sha256', $recordPath);
}

function recoveryApprove(Zend_Db_Adapter_Abstract $db, array $job, DEEC_DocumentArchive $reviewArchive): array
{
	$snapshot = recoverySnapshot($db, $job['identity']);
	$selected = recoverySelection($job, $reviewArchive);
	$userId = (int)Zend_Registry::get('User')['id'];
	if ((int)$selected['selection']['reviewed_by'] !== $userId) throw new RuntimeException('The acting reviewer must explicitly approve their selection');
	$proof = recoveryReconstructionProof($job, $selected, $snapshot, $reviewArchive);
	$approval = ['schema_version' => 1, 'identity' => $job['identity'], 'selection_sha256' => $selected['selection_sha256'], 'sha256' => $selected['file']['sha256'], 'size' => $selected['file']['size'],
		'snapshot_sha256' => $snapshot['sha256'], 'reconstruction_sha256' => $proof, 'approved_by' => $userId, 'approved_at' => gmdate('c'), 'manual' => true];
	reviewDirectory($job['directory'] . '/approvals');
	$path = $job['directory'] . '/approvals/' . $selected['selection_sha256'] . '.json';
	if (file_exists($path)) {
		$existing = recoveryJsonFile($path);
		if ($existing['snapshot_sha256'] !== $approval['snapshot_sha256'] || $existing['approved_by'] !== $userId || ($existing['reconstruction_sha256'] ?? null) !== $proof) throw new RuntimeException('Previous approval is stale; create a new explicit selection');
		return ['status' => 'already_approved'];
	}
	reviewWrite($path, reviewJson($approval));
	return ['status' => 'approved', 'approval' => basename($path)];
}

function recoveryImport(Zend_Db_Adapter_Abstract $db, array $job, DEEC_DocumentArchive $reviewArchive): array
{
	$identity = $job['identity'];
	$snapshot = recoverySnapshot($db, $identity, true);
	$selected = recoverySelection($job, $reviewArchive);
	$approval = recoveryJsonFile($job['directory'] . '/approvals/' . $selected['selection_sha256'] . '.json');
	$userId = (int)Zend_Registry::get('User')['id'];
	if (($approval['identity'] ?? null) !== $identity || ($approval['selection_sha256'] ?? '') !== $selected['selection_sha256'] || ($approval['snapshot_sha256'] ?? '') !== $snapshot['sha256'] || ($approval['approved_by'] ?? 0) !== $userId || ($approval['manual'] ?? false) !== true
		|| ($approval['sha256'] ?? '') !== $selected['file']['sha256'] || ($approval['size'] ?? 0) !== $selected['file']['size']) throw new RuntimeException('Manual approval is missing, stale or belongs to another operator');
	$proof = recoveryReconstructionProof($job, $selected, $snapshot, $reviewArchive);
	if (($approval['reconstruction_sha256'] ?? null) !== $proof) throw new RuntimeException('Approved reconstruction validation has changed');
	$archive = new DEEC_DocumentArchive(BASE_PATH);
	$relative = $archive->relativePath($identity['clientid'], $identity['module'], $identity['controller'], $identity['id']);
	$canonical = $archive->inspect(BASE_PATH . '/' . $relative, 'pdf');
	$existing = recoveryArchives($db, $identity, true);
	if (count($existing) > 1 || ($existing && (int)$existing[0]['version'] !== 1)) throw new RuntimeException('Existing archive versions cannot be replaced');
	if ($existing && ($existing[0]['sha256'] !== $selected['file']['sha256'] || (int)$existing[0]['filesize'] !== $selected['file']['size'] || $existing[0]['path'] !== $relative)) throw new RuntimeException('Registered original differs; exact original bytes are required');
	if ($existing && (!DEEC_Emailattachment::validFilename((string)$existing[0]['filename']) || (DEEC_Emailattachment::validFilename((string)($snapshot['data']['document']['filename'] ?? '')) && $existing[0]['filename'] !== $snapshot['data']['document']['filename']))) throw new RuntimeException('Registered original filename differs; registration is retained');
	if ($canonical['status'] !== 'missing' && ($canonical['status'] !== 'ok' || $canonical['sha256'] !== $selected['file']['sha256'] || $canonical['size'] !== $selected['file']['size'])) throw new RuntimeException('Canonical archive path is occupied; no overwrite is allowed');
	if ($canonical['status'] === 'missing') {
		$bytes = file_get_contents($selected['file']['path']);
		if (strlen($bytes) !== $selected['file']['size'] || hash('sha256', $bytes) !== $selected['file']['sha256']) throw new RuntimeException('Selected source changed');
		$canonical = $archive->store($bytes, $identity['clientid'], $identity['module'], $identity['controller'], $identity['id'], 'pdf');
	}
	if ($canonical['sha256'] !== $selected['file']['sha256'] || $canonical['size'] !== $selected['file']['size'] || hash_file('sha256', $selected['file']['path']) !== $selected['file']['sha256']) throw new RuntimeException('Archive copy integrity differs');
	$filename = (string)($snapshot['data']['document']['filename'] ?? '');
	if (!DEEC_Emailattachment::validFilename($filename)) $filename = $identity['controller'] . '-' . ($identity['document_number'] ?: $identity['id']) . '.pdf';
	if (!DEEC_Emailattachment::validFilename($filename)) $filename = $identity['controller'] . '-' . $identity['id'] . '.pdf';
	$reconstruction = $selected['selection']['origin']['kind'] === 'reconstructed' ? recoveryJsonFile($job['directory'] . '/reconstructions/' . $selected['file']['sha256'] . '.json') : null;
	$recovery = ['approval' => $approval, 'selection' => $selected['selection'], 'reconstruction' => $reconstruction, 'review_directory' => $job['directory'], 'review_manifest_sha256' => hash('sha256', reviewJson($job['manifest'])), 'collection_observed_at' => $job['manifest']['observed_at'] ?? null,
		'collected_sources' => $job['manifest']['sources'], 'archived_timezone' => date_default_timezone_get(), 'historical_conflicts' => $job['manifest']['selected']['reported_reasons'], 'fiscal_correction' => false];
	if (!$existing) {
		$db->insert('documentarchive', ['clientid' => $identity['clientid'], 'module' => $identity['module'], 'controller' => $identity['controller'], 'documentid' => $identity['id'], 'format' => 'pdf', 'version' => 1, 'correctionarchiveid' => null,
			'filename' => $filename, 'path' => $relative, 'filesize' => $canonical['size'], 'sha256' => $canonical['sha256'], 'archived' => date('Y-m-d H:i:s'), 'archivedby' => $userId,
			'parameters' => reviewJson(['recovery' => $recovery])]);
	} else {
		$parameters = json_decode((string)$existing[0]['parameters'], true);
		if (!is_array($parameters)) $parameters = ['previous_parameters' => $existing[0]['parameters']];
		if (($parameters['recovery']['approval']['selection_sha256'] ?? '') !== $selected['selection_sha256'] || !DEEC_DocumentArchive::hasRecoveryApproval($existing[0])) {
			if (isset($parameters['recovery'])) $parameters['recovery_history'][] = $parameters['recovery'];
			$parameters['recovery'] = array_diff_key($recovery, ['archived_timezone' => true]) + ['reviewed_existing_at' => gmdate('c'), 'reviewed_timezone' => date_default_timezone_get()];
			$db->update('documentarchive', ['parameters' => reviewJson($parameters)], [$db->quoteInto('id = ?', $existing[0]['id']), $db->quoteInto('clientid = ?', $identity['clientid'])]);
		}
	}
	if (recoverySnapshot($db, $identity)['sha256'] !== $snapshot['sha256']) throw new RuntimeException('Archive registration changed business values');
	return ['status' => $existing ? 'already_registered' : 'registered', 'path' => $relative, 'sha256' => $canonical['sha256']];
}

function recoverySelect(array $job, array $decision, int $userId, DEEC_DocumentArchive $reviewArchive): array
{
	if (reviewIdentity($decision['document'] ?? [], $job['identity']['clientid']) !== $job['identity']) throw new RuntimeException('Decision identity differs');
	$decision['reviewed_by'] = $userId;
	$decision['reviewed_at'] = gmdate('c');
	$decision['schema_version'] = 1;
	recoverySelection($job, $reviewArchive, $decision);
	$old = $job['directory'] . '/selection.json';
	$previous = recoveryJsonFile($old);
	reviewDirectory($job['directory'] . '/selections');
	$history = $job['directory'] . '/selections/' . hash('sha256', reviewJson($previous)) . '.json';
	if (!file_exists($history)) reviewWrite($history, reviewJson($previous));
	$temp = $job['directory'] . '/selection.' . bin2hex(random_bytes(8)) . '.json';
	reviewWrite($temp, reviewJson($decision));
	try {
		$file = $reviewArchive->inspect(recoveryPath($job['directory'], $decision['candidate_path']), 'pdf');
		if ($file['status'] !== 'ok' || $file['sha256'] !== $decision['sha256']) throw new RuntimeException('Decision candidate integrity differs');
		if (!rename($temp, $old)) throw new RuntimeException('Selection pointer could not be updated');
	} finally { if (is_file($temp)) unlink($temp); }
	return ['status' => 'selected'];
}

function recoveryInspection(Zend_Db_Adapter_Abstract $db, array $job, DEEC_DocumentArchive $reviewArchive, bool $retention): array
{
	$candidates = $job['manifest']['candidates'];
	foreach (glob($job['directory'] . '/reconstructions/*.json') as $path) {
		$record = recoveryJsonFile($path);
		if (($record['identity'] ?? null) !== $job['identity']) throw new RuntimeException('Foreign reconstruction record');
		$candidates[] = ['path' => 'candidates/' . $record['sha256'] . '.pdf', 'sha256' => $record['sha256'], 'size' => $record['size'], 'generated_at' => $record['generated_at'], 'origin' => 'reconstructed', 'validation' => $record['validation'] ?? null];
	}
	foreach ($candidates as &$candidate) $candidate['observed'] = $reviewArchive->inspect(recoveryPath($job['directory'], $candidate['path']), 'pdf');
	unset($candidate);
	$result = ['status' => 'inspected', 'identity' => $job['identity'], 'directory' => $job['directory'], 'candidates' => $candidates, 'suggestion' => $job['manifest']['suggestion'], 'manual_approval_required' => true];
	if (!$retention) return $result;
	$identity = $job['identity'];
	$archive = new DEEC_DocumentArchive(BASE_PATH);
	$canonical = $archive->inspect($archive->path($identity['clientid'], $identity['module'], $identity['controller'], $identity['id']), 'pdf');
	$metadata = recoveryArchives($db, $identity);
	$result['relocation_plan'] = [];
	foreach ($job['manifest']['sources'] as $source) {
		if (empty($source['path']) || $source['source'] === 'canonical' || !empty($source['status']) && !in_array($source['status'], ['ok', 'missing'], true)) continue;
		$observed = $archive->inspect($source['path'], 'pdf');
		$relative = substr($source['path'], strlen(BASE_PATH) + 1);
		$references = $db->fetchAll('SELECT id, deleted FROM emailattachment WHERE clientid = ? AND location = ? AND filename = ?', [$identity['clientid'], dirname($source['path']), basename($source['path'])]);
		$mail = $db->fetchAll('SELECT id, emailmessageid FROM emailmessageattachment WHERE clientid = ? AND path = ?', [$identity['clientid'], $relative]);
		$result['relocation_plan'][] = ['source' => $source['path'], 'observed' => $observed, 'identical_to_registered_original' => count($metadata) === 1 && $canonical['status'] === 'ok' && $observed['status'] === 'ok' && $canonical['sha256'] === $observed['sha256'] && $metadata[0]['sha256'] === $canonical['sha256'],
			'attachment_references' => $references, 'message_references' => $mail, 'preserve_message_copy' => $source['source'] === 'emailmessageattachment', 'external_references_need_manual_check' => true, 'relocation_authorized' => false];
	}
	return $result;
}

function recoveryHtml(array $results, string $root): string
{
	$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$html = '<!doctype html><html lang="de"><meta charset="utf-8"><title>Dokumentprüfung</title><h1>Dokumentprüfung</h1><p>Keine automatische Originalauswahl. Rekonstruktionen mit historischen Nachweisen vergleichen; erforderlichenfalls Papierbelege prüfen.</p>';
	foreach ($results as $key => $row) {
		$html .= '<section><h2>' . $escape($key) . ' — ' . $escape($row['identity']['document_number'] ?? '') . '</h2>';
		if ($row['status'] === 'failed') { $html .= '<p>' . $escape($row['error']) . '</p></section>'; continue; }
		$links = [];
		foreach ($row['candidates'] ?? [] as $candidate) {
			$relative = substr($row['directory'], strlen($root) + 1) . '/' . $candidate['path'];
			$url = '../' . implode('/', array_map('rawurlencode', explode('/', $relative)));
			$links[] = $url;
			$html .= '<p><a href="' . $escape($url) . '">' . $escape($candidate['sha256']) . '</a> — ' . $escape($candidate['size']) . ' Bytes, ' . $escape($candidate['origin'] ?? 'historische Fassung') . ' ' . $escape($candidate['generated_at'] ?? '') . '</p>';
			if (!empty($candidate['validation']['warnings'])) $html .= '<p>Originalvergleich vor Freigabe erforderlich.</p><pre>' . $escape(reviewJson($candidate['validation'])) . '</pre>';
		}
		foreach (array_slice($links, 0, 2) as $link) $html .= '<iframe title="PDF-Kandidat" style="width:48%;height:70vh" src="' . $escape($link) . '"></iframe>';
		$html .= '</section>';
	}
	return $html . '</html>';
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;

umask(0077);
$options = getopt('', ['config:', 'environment:', 'review:', 'run:', 'client:', 'operator-user:', 'action:', 'only:', 'decisions:', 'confirm-client:', 'backup-verified']);
$db = null;
try {
	foreach (['config', 'review', 'run', 'client', 'operator-user', 'action'] as $field) if (!is_string($options[$field] ?? null)) throw new RuntimeException('Required: --config --review --run --client --operator-user --action=prepare|select|approve|import|inspect|retention-plan');
	$clientId = reviewId($options['client']);
	$userId = reviewId($options['operator-user']);
	$root = rtrim($options['review'], '/');
	$context = recoveryJobs($root, $options['run'], $clientId);
	$action = $options['action'];
	if (!in_array($action, ['prepare', 'select', 'approve', 'import', 'inspect', 'retention-plan'], true)) throw new RuntimeException('Invalid recovery action');
	if ($action === 'import' && ((string)($options['confirm-client'] ?? '') !== (string)$clientId || !array_key_exists('backup-verified', $options))) throw new RuntimeException('Import requires --confirm-client and --backup-verified');
	$jobs = $context['jobs'];
	if (isset($options['only'])) { if (!isset($jobs[$options['only']])) throw new RuntimeException('Unknown document key'); $jobs = [$options['only'] => $jobs[$options['only']]]; }
	$decisions = [];
	if ($action === 'select') {
		reviewPrivateFile($options['decisions'] ?? '');
		foreach (file($options['decisions'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
			$decision = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
			$id = reviewIdentity($decision['document'] ?? [], $clientId);
			$key = reviewKey($id);
			if (!isset($jobs[$key]) || isset($decisions[$key])) throw new RuntimeException('Unknown or duplicate batch decision');
			$decisions[$key] = $decision;
		}
		$jobs = array_intersect_key($jobs, $decisions);
	}
	$results = [];
	if ($action === 'prepare' && !isset($options['only'])) {
		// Module position helpers share a legacy PHP class name; isolate each renderer.
		foreach ($jobs as $key => $job) {
			$command = [PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'pdo_mysql.default_socket=' . ini_get('pdo_mysql.default_socket'), __FILE__];
			foreach ($options as $name => $value) if (is_string($value)) $command[] = '--' . $name . '=' . $value;
			$command[] = '--only=' . $key;
			$errorPath = $root . '/runs/renderer-' . bin2hex(random_bytes(12)) . '.log';
			$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['file', $errorPath, 'x']], $pipes);
			$out = stream_get_contents($pipes[1]);
			fclose($pipes[1]);
			$code = proc_close($process);
			$results[$key] = ['status' => $code === 0 ? 'prepared_or_retained' : 'failed', 'output' => $out, 'error_log' => $errorPath, 'error' => file_get_contents($errorPath)];
		}
	} else {
		$db = recoveryBoot(migrationConfig($options['config'], $options['environment'] ?? 'production'), $context['binding']['base'], $clientId, $userId);
		if ((int)$context['binding']['contacts_clientid'] !== (int)Zend_Registry::get('Client')['modules']['contacts']) throw new RuntimeException('Review contact tenant is not authorized by the current tenant relationship');
		$reviewArchive = new DEEC_DocumentArchive($root);
		reviewPrivateFile($root . '/.collector.lock');
		$lock = fopen($root . '/.collector.lock', 'r+b');
		if (!flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Review area is busy');
		foreach ($jobs as $key => $job) {
			try {
				if ($action !== 'import') $db->query('SET TRANSACTION READ ONLY');
				$db->beginTransaction();
				$snapshot = recoverySnapshot($db, $job['identity']);
				if ($action === 'prepare') {
					$module = $job['identity']['module'];
					require_once dirname(__DIR__, 2) . '/application/modules/' . $module . '/controllers/helpers/Positions.php';
					$locale = $snapshot['data']['document']['language'] ?? 'de_DE';
					Zend_Registry::set('Zend_Locale', new Zend_Locale($locale));
					Zend_Registry::set('Zend_Currency', new Zend_Currency(['locale' => $locale, 'precision' => 2]));
					$translator = new DEEC_Translate($locale);
					$translator->loadDir('default', BASE_PATH . '/languages/' . $locale . '/default');
					$translator->loadDir($module, BASE_PATH . '/languages/' . $locale . '/' . $module);
					Zend_Registry::set('DEEC_Translate', $translator);
					$results[$key] = recoveryPrepare($db, $job, $root, new DEEC_Pdf());
				} elseif ($action === 'approve') $results[$key] = recoveryApprove($db, $job, $reviewArchive);
				elseif ($action === 'import') $results[$key] = recoveryImport($db, $job, $reviewArchive);
				elseif ($action === 'select') $results[$key] = recoverySelect($job, $decisions[$key], $userId, $reviewArchive);
				else $results[$key] = recoveryInspection($db, $job, $reviewArchive, $action === 'retention-plan');
				if ($action === 'import') $db->commit(); else $db->rollBack();
			} catch (Throwable $exception) {
				try { $db->rollBack(); } catch (Throwable $ignored) {}
				$results[$key] = ['status' => 'failed', 'error' => $exception->getMessage()];
			}
		}
		flock($lock, LOCK_UN); fclose($lock);
	}
	$report = $root . '/runs/recovery-' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(6)) . '.json';
	reviewWrite($report, reviewJson(['action' => $action, 'clientid' => $clientId, 'operator_user' => $userId, 'executed_at' => gmdate('c'), 'results' => $results]));
	if ($action === 'inspect') reviewWrite(substr($report, 0, -5) . '.html', recoveryHtml($results, $root));
	echo reviewJson(['report' => $report, 'results' => $results]);
	$exit = count(array_filter($results, static fn(array $row): bool => $row['status'] === 'failed')) ? 1 : 0;
} catch (Throwable $exception) { fwrite(STDERR, $exception->getMessage() . "\n"); $exit = 1; }
finally { if ($db) $db->closeConnection(); }
exit($exit);
