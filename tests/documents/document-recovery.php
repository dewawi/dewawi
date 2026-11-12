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
	return DEEC_DocumentReview::snapshot($db, $identity, $lock);
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
	return ['binding' => $binding, 'jobs' => $jobs, 'reports' => $run['reports'] ?? []];
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

function recoveryBindReviewable(string $root, int $clientId): void
{
	DEEC_DocumentReview::validateRoot($root, $clientId);
	$locator = DEEC_DocumentReview::locatorPath($clientId);
	reviewSafePath($locator);
	$binding = ['schema_version' => 1, 'clientid' => $clientId, 'base' => BASE_PATH, 'review' => $root, 'binding_sha256' => hash_file('sha256', $root . '/review.json')];
	if (file_exists($locator)) {
		if (recoveryJsonFile($locator) !== $binding) throw new RuntimeException('A different review area is already bound to this tenant');
		return;
	}
	if (!is_dir(dirname($locator)) && !mkdir(dirname($locator), 0700, true)) throw new RuntimeException('Review locator directory is unavailable');
	reviewWrite($locator, reviewJson($binding));
}

function recoveryPublishReviewable(Zend_Db_Adapter_Abstract $db, array $job, string $root, array $confirmation, array $failures): array
{
	$id = $job['identity'];
	if (recoveryArchives($db, $id)) return ['status' => 'already_registered', 'reason' => 'Existing archive registration retained without writes'];
	$snapshot = recoverySnapshot($db, $id);
	$records = glob($job['directory'] . '/reconstructions/*.json');
	if (!$records) return ['status' => 'skipped', 'category' => 'reconstruction_failed', 'reason' => $failures[reviewKey($id)]['error'] ?? 'No prepared reconstruction proof', 'source_report' => $failures[reviewKey($id)]['report'] ?? null, 'next_action' => 'resolve failed reconstruction separately; no PDF generated'];
	$results = []; $errors = [];
	foreach ($records as $path) {
		$sha = basename($path, '.json');
		try {
			$candidate = DEEC_DocumentReview::candidate($root, $job['directory'], $id, $snapshot, $sha);
			$directory = DEEC_DocumentReview::publicationDirectory($root, $id);
			reviewDirectory($directory);
			$target = $directory . '/' . $sha . '.json';
			$receipt = ['schema_version' => 1, 'status' => DEEC_DocumentReview::STATUS, 'identity' => $id, 'directory' => basename($job['directory']), 'sha256' => $sha, 'size' => $candidate['file']['size'],
				'snapshot_sha256' => $snapshot['sha256'], 'proof_sha256' => $candidate['proof_sha256'], 'database_confirmation' => array_diff_key($confirmation, ['scope' => true])];
			$existing = file_exists($target);
			if ($existing) $receipt = recoveryJsonFile($target);
			DEEC_DocumentReview::receipt($receipt, $id, $candidate, $snapshot);
			if ($receipt['directory'] !== basename($job['directory'])) throw new RuntimeException('Published review directory differs');
			if (!$existing) reviewWrite($target, reviewJson($receipt));
			$results[$sha] = ['status' => $existing ? 'already_published' : 'published', 'sha256' => $sha, 'size' => $candidate['file']['size'], 'database_confirmation' => $receipt['database_confirmation']];
		} catch (Throwable $error) { $errors[$sha] = $error->getMessage(); }
	}
	return ['status' => $results ? ($errors ? 'partially_published' : 'published') : 'failed', 'category' => $results ? DEEC_DocumentReview::STATUS : 'invalid_review_candidate',
		'document_date' => $snapshot['data']['document'][$id['controller'] . 'date'] ?? '', 'candidates' => $results, 'candidate_errors' => $errors, 'error' => $errors ? implode('; ', $errors) : '',
		'reason' => 'Internal review only; no original comparison, selection, approval or archive registration', 'next_action' => 'accounting must compare each chosen PDF with an original before the existing recovery release'];
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
		if (($record['snapshot_sha256'] ?? '') !== $snapshot['sha256'] || ($record['validation']['schema_version'] ?? 0) !== 1 || !isset($record['validation']['displayed'])) continue;
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

function recoveryOriginalComparison(array $job, array $selected, DEEC_DocumentArchive $reviewArchive): void
{
	$comparison = $selected['selection']['original_comparison'] ?? [];
	if (($comparison['kind'] ?? '') === 'previous_paper_match') {
		if (($comparison['assertion_type'] ?? '') !== 'retrospective_operator_attestation' || ($comparison['identity'] ?? null) !== $job['identity'] || ($comparison['sha256'] ?? '') !== $selected['file']['sha256']
			|| ($comparison['confirmed_by'] ?? 0) !== (int)$selected['selection']['reviewed_by'] || empty($comparison['confirmed_at']) || !strtotime($comparison['confirmed_at'])
			|| !array_key_exists('comparison_performed_at', $comparison) || $comparison['comparison_performed_at'] !== null || !array_key_exists('paper_location', $comparison) || $comparison['paper_location'] !== null
			|| ($comparison['reference'] ?? '') !== 'Paper document ' . $job['identity']['document_number'] || ($comparison['amounts_match'] ?? false) !== true) throw new RuntimeException('Invalid document-bound retrospective paper attestation');
		return;
	}
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

function recoveryReconstructionProof(array $job, array $selected, array $snapshot, DEEC_DocumentArchive $reviewArchive, bool $requireComparison = false): ?string
{
	if ($selected['selection']['origin']['kind'] !== 'reconstructed') return null;
	$recordPath = $job['directory'] . '/reconstructions/' . $selected['file']['sha256'] . '.json';
	$record = recoveryJsonFile($recordPath);
	if (($record['snapshot_sha256'] ?? '') !== $snapshot['sha256'] || ($record['validation']['schema_version'] ?? 0) !== 1 || !isset($record['validation']['displayed'])) throw new RuntimeException('Reconstruction requires a current amount validation; prepare and review again');
	$publication = DEEC_DocumentReview::publicationDirectory(dirname($job['directory']), $job['identity']) . '/' . $selected['file']['sha256'] . '.json';
	if (file_exists($publication) || is_link($publication)) $requireComparison = true;
	if ($requireComparison || !empty($record['validation']['original_comparison_required']) || !empty($selected['selection']['authorization'])) recoveryOriginalComparison($job, $selected, $reviewArchive);
	return hash_file('sha256', $recordPath);
}

function recoveryApprove(Zend_Db_Adapter_Abstract $db, array $job, DEEC_DocumentArchive $reviewArchive): array
{
	$snapshot = recoverySnapshot($db, $job['identity']);
	$selected = recoverySelection($job, $reviewArchive);
	$userId = (int)Zend_Registry::get('User')['id'];
	if ((int)$selected['selection']['reviewed_by'] !== $userId) throw new RuntimeException('The acting reviewer must explicitly approve their selection');
	$proof = recoveryReconstructionProof($job, $selected, $snapshot, $reviewArchive);
	recoveryReleaseEvidence($db, $job, $selected, $snapshot);
	$approval = ['schema_version' => 1, 'identity' => $job['identity'], 'selection_sha256' => $selected['selection_sha256'], 'sha256' => $selected['file']['sha256'], 'size' => $selected['file']['size'],
		'snapshot_sha256' => $snapshot['sha256'], 'reconstruction_sha256' => $proof, 'approved_by' => $userId, 'approved_at' => gmdate('c'), 'manual' => true,
		'method' => $selected['selection']['authorization']['method'] ?? 'individual_selection', 'authorization' => $selected['selection']['authorization'] ?? null,
		'individual_original_comparison_claimed' => isset($selected['selection']['original_comparison']) && ($selected['selection']['original_comparison']['kind'] ?? '') !== 'previous_paper_match'];
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

function recoveryImport(Zend_Db_Adapter_Abstract $db, array $job, DEEC_DocumentArchive $reviewArchive, ?array $releaseReceipt = null): array
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
	recoveryReleaseEvidence($db, $job, $selected, $snapshot);
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
		'collected_sources' => $job['manifest']['sources'], 'archived_timezone' => date_default_timezone_get(), 'historical_conflicts' => $job['manifest']['selected']['reported_reasons'], 'fiscal_correction' => false, 'release_authorization' => $releaseReceipt];
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

function recoveryDispatchEvidence(Zend_Db_Adapter_Abstract $db, array $job, string $sha, array $snapshot): ?array
{
	$id = $job['identity'];
	$proofs = [];
	foreach ($job['manifest']['sources'] as $source) {
		if ($source['source'] !== 'emailmessageattachment' || ($source['status'] ?? '') !== 'ok' || ($source['sha256'] ?? '') !== $sha || ($source['metadata_integrity'] ?? '') !== 'matches') continue;
		$row = $db->fetchRow('SELECT a.*, m.documentid, m.module, m.controller, m.messagesent, m.deliverystatus FROM emailmessageattachment a JOIN emailmessage m ON m.id = a.emailmessageid AND m.clientid = a.clientid WHERE a.id = ? AND a.clientid = ? AND m.documentid = ? AND m.module = ? AND m.controller = ?', [$source['evidence']['id'] ?? 0, $id['clientid'], $id['id'], $id['module'], $id['controller']]);
		if (!$row || !$row['messagesent'] || !in_array($row['deliverystatus'], ['accepted', 'delivered'], true) || $row['sha256'] !== $sha || $row['filename'] !== ($snapshot['data']['document']['filename'] ?? '')) continue;
		$client = (string)$id['clientid'];
		$expected = 'files/attachments/emailmessage/' . $client[0] . '/' . ($client[1] ?? '0') . '/' . $client . '/' . intdiv((int)$row['emailmessageid'], 1000) . '/' . $row['emailmessageid'] . '/' . $row['ordering'] . '.pdf';
		if ((int)$row['ordering'] < 1 || $row['path'] !== $expected) continue;
		$reference = null;
		foreach (['documentarchiveid' => 'documentarchive', 'emailattachmentid' => 'emailattachment'] as $field => $table) {
			if (empty($row[$field])) continue;
			$reference = $db->fetchRow("SELECT * FROM `$table` WHERE id = ? AND clientid = ? AND documentid = ? AND module = ? AND controller = ? AND filename = ?", [$row[$field], $id['clientid'], $id['id'], $id['module'], $id['controller'], $row['filename']]);
			if ($reference && (int)$reference['filesize'] === (int)$row['filesize'] && ($table !== 'documentarchive' || ((int)$reference['version'] === 1 && $reference['sha256'] === $sha))) break;
			$reference = null;
		}
		if (!$reference) continue;
		$file = (new DEEC_DocumentArchive(BASE_PATH))->inspect(BASE_PATH . '/' . $expected, 'pdf');
		if ($file['status'] === 'ok' && $file['sha256'] === $sha && $file['size'] === (int)$row['filesize']) $proofs[] = ['message_attachment' => $row, 'document_reference' => $reference];
	}
	return $proofs ? ['method' => 'verified_dispatch_original', 'sha256' => hash('sha256', reviewJson($proofs)), 'records' => $proofs] : null;
}

function recoveryReleaseEvidence(Zend_Db_Adapter_Abstract $db, array $job, array $selected, array $snapshot): void
{
	if ($selected['selection']['origin']['kind'] !== 'reconstructed' && isset($selected['selection']['original_comparison'])) recoveryOriginalComparison($job, $selected, new DEEC_DocumentArchive(dirname($job['directory'])));
	$authorization = $selected['selection']['authorization'] ?? null;
	if (!$authorization) return;
	if (!in_array($authorization['method'] ?? '', ['batch_policy', 'operator_attestation'], true) || ($authorization['confirmed_by'] ?? 0) !== (int)$selected['selection']['reviewed_by'] || ($authorization['database_and_file_backup_confirmed'] ?? false) !== true) throw new RuntimeException('Release authorization is invalid');
	if (($authorization['policy'] ?? '') !== ($authorization['method'] === 'batch_policy' ? 'verified-evidence-v1' : 'document_paper_attestation')) throw new RuntimeException('Release policy does not match its authorization method');
	if (($authorization['method'] ?? '') === 'batch_policy' && ($selected['selection']['origin']['kind'] ?? '') === 'historical_pdf') {
		$proof = recoveryDispatchEvidence($db, $job, $selected['file']['sha256'], $snapshot);
		if (!$proof || ($selected['selection']['origin']['dispatch_evidence']['sha256'] ?? '') !== $proof['sha256']) throw new RuntimeException('Verified dispatch evidence is missing or changed');
	}
}

function recoveryReleasePlan(Zend_Db_Adapter_Abstract $db, array $job, DEEC_DocumentArchive $reviewArchive, array $snapshot, array $options): array
{
	$id = $job['identity'];
	$archive = new DEEC_DocumentArchive(BASE_PATH);
	$rows = recoveryArchives($db, $id);
	$canonical = $archive->inspect($archive->path($id['clientid'], $id['module'], $id['controller'], $id['id']), 'pdf');
	if ($rows) {
		if (count($rows) === 1 && (int)$rows[0]['version'] === 1) {
			$original = (new Application_Model_DbTable_Documentarchive())->getForDocument($id['id'], $id['module'], $id['controller']);
			if ($original) {
				if (isset($options['attest-previous-paper-match']) && $options['attest-previous-paper-match'] !== $original['sha256']) throw new RuntimeException('Attested SHA-256 differs from the existing original');
				return ['status' => 'already_archived', 'category' => 'already_archived', 'reason' => 'Verified existing original retained without writes', 'sha256' => $original['sha256'], 'next_action' => 'none'];
			}
		}
		throw new RuntimeException('Existing archive versions or registered original are inconsistent');
	}
	if (!in_array($canonical['status'], ['ok', 'missing'], true)) throw new RuntimeException('Canonical archive path is occupied or invalid');
	$decision = recoveryJsonFile($job['directory'] . '/selection.json');
	$reuse = ($decision['decision'] ?? '') === 'selected';
	$attest = $options['attest-previous-paper-match'] ?? null;
	$historical = [];
	foreach ($job['manifest']['candidates'] as $candidate) $historical[$candidate['sha256']] = $candidate;
	$reconstructed = [];
	foreach (glob($job['directory'] . '/reconstructions/*.json') as $path) {
		$record = recoveryJsonFile($path);
		if (($record['identity'] ?? null) !== $id) throw new RuntimeException('Foreign reconstruction record');
		if (($record['snapshot_sha256'] ?? '') === $snapshot['sha256'] && ($record['validation']['schema_version'] ?? 0) === 1 && isset($record['validation']['displayed'])) $reconstructed[$record['sha256']] = ['path' => 'candidates/' . $record['sha256'] . '.pdf', 'sha256' => $record['sha256']];
	}
	$basis = $reuse ? ($decision['authorization']['method'] ?? 'existing_individual_selection') : null;
	if ($attest !== null || !$reuse) {
		$reuse = false;
		$sha = $attest;
		$kind = 'reconstructed';
		if ($sha === null && count($historical) > 1) return ['status' => 'skipped', 'category' => 'historical_conflict', 'reason' => 'Different historical PDFs require individual selection', 'next_action' => 'compare historical versions'];
		if ($sha === null && count($historical) === 1) { $sha = array_key_first($historical); $kind = 'historical_pdf'; }
		if ($sha === null && count($reconstructed) === 1) $sha = array_key_first($reconstructed);
		if ($sha !== null && isset($historical[$sha])) $kind = 'historical_pdf';
		$candidate = ($kind === 'historical_pdf' ? $historical : $reconstructed)[$sha ?? ''] ?? null;
		if (!$candidate && $attest !== null) throw new RuntimeException('Attested SHA-256 has no current candidate for this document');
		if (!$candidate) {
			$failure = $options['_preparation_failures'][reviewKey($id)] ?? null;
			return ['status' => 'skipped', 'category' => $failure ? recoveryIssueCategory($failure['error']) : 'technical_failed', 'reason' => $failure['error'] ?? 'No unique current candidate', 'next_action' => 'review failed preparation or specify an exact candidate', 'source_report' => $failure['report'] ?? null];
		}
		$decision = ['schema_version' => 1, 'document' => $id, 'decision' => 'selected', 'candidate_path' => $candidate['path'], 'sha256' => $sha, 'reviewed_by' => (int)Zend_Registry::get('User')['id'], 'reviewed_at' => gmdate('c'),
			'origin' => ['kind' => $kind, 'source_path' => $kind === 'reconstructed' ? 'stored business snapshot' : 'verified historical dispatch'], 'reason' => 'Release policy requires verified original evidence; numerical consistency alone is insufficient'];
		if ($attest !== null) {
			$decision['original_comparison'] = ['kind' => 'previous_paper_match', 'assertion_type' => 'retrospective_operator_attestation', 'identity' => $id, 'sha256' => $sha,
				'confirmed_by' => $decision['reviewed_by'], 'confirmed_at' => gmdate('c'), 'comparison_performed_at' => null, 'paper_location' => null, 'reference' => 'Paper document ' . $id['document_number'], 'amounts_match' => true];
			$decision['reason'] = 'Operator retrospectively confirms a previously completed full match with the identified paper document; original comparison date and paper location are unknown';
			$basis = 'operator_attestation';
		} elseif ($kind === 'historical_pdf') {
			$basis = recoveryDispatchEvidence($db, $job, $sha, $snapshot);
			if (!$basis) return ['status' => 'skipped', 'category' => 'candidate_evidence_missing', 'reason' => 'A single historical PDF is not proof of an original', 'sha256' => $sha, 'next_action' => 'document original provenance or perform an original comparison'];
			$decision['origin']['dispatch_evidence'] = $basis;
			$basis = 'batch_policy';
		}
	}
	$selected = recoverySelection($job, $reviewArchive, $decision);
	if ($canonical['status'] === 'ok' && ($canonical['sha256'] !== $selected['file']['sha256'] || $canonical['size'] !== $selected['file']['size'])) throw new RuntimeException('Canonical archive path is occupied by different bytes');
	if ((int)$decision['reviewed_by'] !== (int)Zend_Registry::get('User')['id']) return ['status' => 'skipped', 'category' => 'candidate_evidence_missing', 'reason' => 'Existing selection belongs to another reviewer', 'sha256' => $selected['file']['sha256'], 'next_action' => 'original reviewer must release or document a new comparison'];
	try {
		$proof = recoveryReconstructionProof($job, $selected, $snapshot, $reviewArchive, true);
		$approvalPath = $job['directory'] . '/approvals/' . $selected['selection_sha256'] . '.json';
		$hasApproval = false;
		if ($reuse && file_exists($approvalPath)) {
			$approval = recoveryJsonFile($approvalPath);
			if (($approval['identity'] ?? null) !== $id || ($approval['selection_sha256'] ?? '') !== $selected['selection_sha256'] || ($approval['snapshot_sha256'] ?? '') !== $snapshot['sha256'] || ($approval['approved_by'] ?? 0) !== (int)$decision['reviewed_by'] || ($approval['sha256'] ?? '') !== $selected['file']['sha256'] || ($approval['size'] ?? 0) !== $selected['file']['size'] || ($approval['manual'] ?? false) !== true || ($approval['reconstruction_sha256'] ?? null) !== $proof) throw new RuntimeException('Existing approval is stale or invalid');
			$hasApproval = true;
		}
		if ($decision['origin']['kind'] !== 'reconstructed' && isset($decision['original_comparison'])) recoveryOriginalComparison($job, $selected, $reviewArchive);
		if ($reuse && $decision['origin']['kind'] !== 'reconstructed' && !isset($decision['original_comparison']) && empty($decision['authorization']) && !$hasApproval) throw new RuntimeException('Existing historical selection has no valid manual approval');
		recoveryReleaseEvidence($db, $job, $selected, $snapshot);
	} catch (Throwable $error) { return ['status' => 'skipped', 'category' => 'original_comparison_required', 'reason' => $error->getMessage(), 'sha256' => $selected['file']['sha256'], 'next_action' => 'supply a valid document-bound original comparison']; }
	return ['status' => 'eligible', 'category' => 'eligible', 'reason' => $decision['reason'], 'sha256' => $selected['file']['sha256'], 'next_action' => 'explicit release with confirmed database and file backup', 'decision' => $decision, 'reuse_selection' => $reuse, 'method' => $basis ?? 'existing_individual_selection'];
}

function recoveryRelease(Zend_Db_Adapter_Abstract $db, array $job, DEEC_DocumentArchive $reviewArchive, array $snapshot, array $options, array $authorization): array
{
	$plan = recoveryReleasePlan($db, $job, $reviewArchive, $snapshot, $options);
	if ($plan['status'] !== 'eligible') return $plan;
	if (!$plan['reuse_selection']) {
		$decision = $plan['decision'];
		$decision['authorization'] = $authorization + ['method' => $plan['method']];
		recoverySelect($job, $decision, (int)Zend_Registry::get('User')['id'], $reviewArchive);
	}
	recoveryApprove($db, $job, $reviewArchive);
	$result = recoveryImport($db, $job, $reviewArchive, $authorization + ['identity' => $job['identity'], 'sha256' => $plan['sha256'], 'selection_method' => $plan['method']]);
	$original = (new Application_Model_DbTable_Documentarchive())->getForDocument($job['identity']['id'], $job['identity']['module'], $job['identity']['controller']);
	if (!$original || $original['sha256'] !== $plan['sha256']) throw new RuntimeException('Post-import original verification failed');
	return $result + ['category' => 'archived', 'reason' => $plan['reason'], 'next_action' => 'none'];
}

function recoveryIssueCategory(string $reason): string
{
	if (stripos($reason, 'contact') !== false || stripos($reason, 'recipient') !== false) return 'contact_unresolved';
	if (stripos($reason, 'comparison') !== false || stripos($reason, 'attestation') !== false) return 'original_comparison_required';
	if (preg_match('/incomplete|missing.*(?:number|date|currency|positions)|positions.*missing|number, date or currency/i', $reason)) return 'business_data_incomplete';
	if (preg_match('/original|archive|canonical|versions/i', $reason)) return 'historical_conflict';
	return 'technical_failed';
}

function recoveryReportRows(array $results, array $jobs): array
{
	$rows = [];
	foreach ($results as $key => $result) {
		$id = $jobs[$key]['identity'];
		$date = $result['document_date'] ?? $jobs[$key]['manifest']['document']['document_date'] ?? $jobs[$key]['manifest']['selected']['reported_date'] ?? '';
		$rows[] = ['clientid' => $id['clientid'], 'type' => $id['module'] . '/' . $id['controller'], 'id' => $id['id'], 'document_number' => $id['document_number'], 'year' => preg_match('/^[0-9]{4}-/', $date) ? substr($date, 0, 4) : 'unknown',
			'status' => $result['category'] ?? ($result['status'] === 'failed' ? recoveryIssueCategory($result['error'] ?? '') : $result['status']), 'reason' => $result['reason'] ?? $result['error'] ?? '', 'candidate_sha256' => $result['sha256'] ?? '', 'next_action' => $result['next_action'] ?? 'review the protected document report'];
	}
	return $rows;
}

function recoveryPreparationFailures(string $root, int $clientId): array
{
	$failures = [];
	$paths = glob($root . '/runs/recovery-*.json');
	sort($paths, SORT_STRING);
	foreach ($paths as $path) {
		$report = recoveryJsonFile($path);
		if (($report['action'] ?? '') !== 'prepare' || ($report['clientid'] ?? 0) !== $clientId) continue;
		foreach ($report['results'] as $key => $row) {
			if ($row['status'] !== 'failed') { unset($failures[$key]); continue; }
			$worker = isset($row['output']) ? json_decode($row['output'], true) : null;
			$failures[$key] = ['error' => $worker['results'][$key]['error'] ?? $row['error'] ?? 'Unknown preparation failure', 'report' => $path];
		}
	}
	return $failures;
}

function recoveryInventoryRows(array $reports, int $clientId, ?string $explicit): array
{
	$rows = [];
	if ($explicit !== null) $reports = [['path' => $explicit]];
	foreach ($reports as $report) {
		if ($explicit === null && !str_ends_with($report['path'], '.jsonl')) continue;
		reviewSafePath($report['path']);
		if (isset($report['sha256']) && hash_file('sha256', $report['path']) !== $report['sha256']) throw new RuntimeException('Baseline report hash differs');
		$summary = null;
		$stream = fopen($report['path'], 'rb');
		if (!$stream) throw new RuntimeException('Baseline report is unavailable');
		try { while (($line = fgets($stream)) !== false) {
			if (trim($line) === '') continue;
			if ($summary !== null) throw new RuntimeException('Records after baseline summary');
			$entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
			if ($entry['kind'] === 'summary') $summary = $entry['summary'];
			if (!in_array($entry['kind'], ['excluded_document', 'unassigned_file'], true)) continue;
			$record = $entry['record'] ?? [];
			if (isset($record['clientid']) && (int)$record['clientid'] !== $clientId) throw new RuntimeException('Foreign baseline inventory');
			$rows[] = ['clientid' => $clientId, 'type' => isset($record['module']) ? $record['module'] . '/' . $record['controller'] : '', 'id' => $record['id'] ?? '', 'document_number' => $record['document_number'] ?? '', 'year' => substr($record['document_date'] ?? '', 0, 4) ?: 'unknown',
				'status' => $entry['kind'] === 'excluded_document' ? (($record['exclusion_reason'] ?? '') === 'soft_deleted_issued' ? 'excluded_issued' : 'excluded_document') : 'unassigned_file', 'reason' => $record['exclusion_reason'] ?? $entry['classification'], 'candidate_sha256' => $entry['file']['sha256'] ?? '', 'next_action' => 'manual classification; no automatic import', 'source_path' => $entry['file']['path'] ?? ''];
		}
		} finally { fclose($stream); }
		if (!$summary || (int)$summary['clientid'] !== $clientId || $summary['mode'] !== 'dry-run') throw new RuntimeException('A completed tenant baseline dry-run is required');
	}
	return $rows;
}

function recoveryWriteReports(string $report, array $data, array $rows): void
{
	$groups = ['type' => [], 'year' => [], 'cause' => []];
	foreach ($rows as $row) foreach (['type' => $row['type'], 'year' => $row['year'], 'cause' => $row['status'] . ': ' . $row['reason']] as $group => $key) $groups[$group][$key] = ($groups[$group][$key] ?? 0) + 1;
	reviewWrite($report, reviewJson($data + ['rows' => $rows, 'summary' => $groups]));
	$stream = fopen('php://temp', 'w+');
	$fields = ['clientid', 'type', 'id', 'document_number', 'year', 'status', 'reason', 'candidate_sha256', 'next_action', 'source_path'];
	fputcsv($stream, $fields, ',', '"', '');
	foreach ($rows as $row) fputcsv($stream, array_map(static function(string $field) use ($row): string { $value = (string)($row[$field] ?? ''); return preg_match('/^[=+@-]/', $value) ? "'" . $value : $value; }, $fields), ',', '"', '');
	rewind($stream);
	reviewWrite(substr($report, 0, -5) . '.csv', stream_get_contents($stream));
	fclose($stream);
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
$options = getopt('', ['config:', 'environment:', 'review:', 'run:', 'client:', 'operator-user:', 'action:', 'only:', 'decisions:', 'confirm-client:', 'backup-verified', 'authorize-batch:', 'attest-previous-paper-match:', 'baseline:', 'retry-failed-from:', 'database-values-verified']);
$db = null;
try {
	foreach (['config', 'review', 'run', 'client', 'operator-user', 'action'] as $field) if (!is_string($options[$field] ?? null)) throw new RuntimeException('Required: --config --review --run --client --operator-user --action=publish-reviewable|release-plan|release|prepare|select|approve|import|inspect|retention-plan');
	$clientId = reviewId($options['client']);
	$userId = reviewId($options['operator-user']);
	$root = rtrim($options['review'], '/');
	$context = recoveryJobs($root, $options['run'], $clientId);
	$action = $options['action'];
	if (!in_array($action, ['prepare', 'select', 'approve', 'import', 'inspect', 'retention-plan', 'release-plan', 'release', 'publish-reviewable'], true)) throw new RuntimeException('Invalid recovery action');
	if ($action === 'publish-reviewable' && ((string)($options['confirm-client'] ?? '') !== (string)$clientId || !array_key_exists('database-values-verified', $options))) throw new RuntimeException('Review publication requires --confirm-client and --database-values-verified; this does not confirm an original comparison');
	if (in_array($action, ['import', 'release'], true) && ((string)($options['confirm-client'] ?? '') !== (string)$clientId || !array_key_exists('backup-verified', $options))) throw new RuntimeException('Import requires --confirm-client and --backup-verified for both database and file backups');
	$jobs = $context['jobs'];
	if (isset($options['only'])) { if (!isset($jobs[$options['only']])) throw new RuntimeException('Unknown document key'); $jobs = [$options['only'] => $jobs[$options['only']]]; }
	if (isset($options['attest-previous-paper-match']) && (!in_array($action, ['release', 'release-plan'], true) || !isset($options['only']) || count($jobs) !== 1 || !preg_match('/^[a-f0-9]{64}$/D', $options['attest-previous-paper-match']))) throw new RuntimeException('Paper attestation requires release/release-plan, exactly one --only document and its exact SHA-256');
	if ($action === 'release' && !isset($options['attest-previous-paper-match']) && ($options['authorize-batch'] ?? '') !== 'verified-evidence-v1') throw new RuntimeException('Release requires explicit --authorize-batch=verified-evidence-v1');
	if (isset($options['retry-failed-from'])) {
		if ($action !== 'prepare' || !str_starts_with($options['retry-failed-from'], $root . '/runs/')) throw new RuntimeException('Retry requires a recovery preparation report in this review area');
		$previous = recoveryJsonFile($options['retry-failed-from']);
		if (($previous['action'] ?? '') !== 'prepare' || ($previous['clientid'] ?? 0) !== $clientId) throw new RuntimeException('Foreign retry report');
		$jobs = array_intersect_key($jobs, array_filter($previous['results'], static fn(array $row): bool => $row['status'] === 'failed'));
	}
	$inventory = in_array($action, ['release-plan', 'release'], true) ? recoveryInventoryRows($context['reports'], $clientId, $options['baseline'] ?? null) : [];
	if (in_array($action, ['release-plan', 'release'], true)) $options['_preparation_failures'] = recoveryPreparationFailures($root, $clientId);
	$authorization = ['policy' => isset($options['attest-previous-paper-match']) ? 'document_paper_attestation' : ($options['authorize-batch'] ?? null), 'confirmed_by' => $userId, 'confirmed_at' => gmdate('c'),
		'database_and_file_backup_confirmed' => array_key_exists('backup-verified', $options), 'scope_sha256' => hash('sha256', reviewJson(array_keys($jobs))), 'documents_in_scope' => count($jobs), 'run' => $options['run'], 'run_sha256' => hash_file('sha256', $options['run'])];
	if ($action === 'publish-reviewable') $authorization = array_replace($authorization, ['policy' => 'internal_reconstruction_review', 'database_values_verified' => true, 'original_comparison_performed' => false,
		'scope' => array_column($jobs, 'identity'), 'scope_sha256' => hash('sha256', reviewJson(array_column($jobs, 'identity')))]);
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
			$worker = json_decode($out, true);
			$results[$key] = ($worker['results'][$key] ?? ['status' => $code === 0 ? 'prepared_or_retained' : 'failed', 'error' => file_get_contents($errorPath)]) + ['error_log' => $errorPath];
		}
	} else {
		$db = recoveryBoot(migrationConfig($options['config'], $options['environment'] ?? 'production'), $context['binding']['base'], $clientId, $userId);
		if ((int)$context['binding']['contacts_clientid'] !== (int)Zend_Registry::get('Client')['modules']['contacts']) throw new RuntimeException('Review contact tenant is not authorized by the current tenant relationship');
		$reviewArchive = new DEEC_DocumentArchive($root);
		reviewPrivateFile($root . '/.collector.lock');
		$lock = fopen($root . '/.collector.lock', 'r+b');
		if (!flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Review area is busy');
		if ($action === 'publish-reviewable') {
			recoveryBindReviewable($root, $clientId);
			$options['_preparation_failures'] = recoveryPreparationFailures($root, $clientId);
		}
		foreach ($jobs as $key => $job) {
			try {
				if (!in_array($action, ['import', 'release'], true)) $db->query('SET TRANSACTION READ ONLY');
				$db->beginTransaction();
				$snapshot = $action === 'publish-reviewable' ? null : recoverySnapshot($db, $job['identity']);
				if ($action === 'publish-reviewable') $results[$key] = recoveryPublishReviewable($db, $job, $root, $authorization, $options['_preparation_failures']);
				elseif ($action === 'prepare') {
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
				elseif ($action === 'release-plan') $results[$key] = recoveryReleasePlan($db, $job, $reviewArchive, $snapshot, $options);
				elseif ($action === 'release') $results[$key] = recoveryRelease($db, $job, $reviewArchive, $snapshot, $options, $authorization);
				elseif ($action === 'select') $results[$key] = recoverySelect($job, $decisions[$key], $userId, $reviewArchive);
				else $results[$key] = recoveryInspection($db, $job, $reviewArchive, $action === 'retention-plan');
				$results[$key]['document_date'] = $results[$key]['document_date'] ?? (string)($snapshot['data']['document'][$job['identity']['controller'] . 'date'] ?? '');
				unset($results[$key]['decision']);
				if (in_array($action, ['import', 'release'], true)) $db->commit(); else $db->rollBack();
			} catch (Throwable $exception) {
				try { $db->rollBack(); } catch (Throwable $ignored) {}
				$results[$key] = ['status' => 'failed', 'error' => $exception->getMessage()];
			}
		}
		flock($lock, LOCK_UN); fclose($lock);
	}
	$report = $root . '/runs/recovery-' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(6)) . '.json';
	$publicationSummary = [];
	if ($action === 'publish-reviewable') foreach ($results as $result) {
		$status = $result['category'] ?? $result['status'];
		$publicationSummary[$status] = ($publicationSummary[$status] ?? 0) + 1;
		$publicationSummary['available_candidates'] = ($publicationSummary['available_candidates'] ?? 0) + count($result['candidates'] ?? []);
		$publicationSummary['invalid_candidates'] = ($publicationSummary['invalid_candidates'] ?? 0) + count($result['candidate_errors'] ?? []);
	}
	recoveryWriteReports($report, ['action' => $action, 'clientid' => $clientId, 'operator_user' => $userId, 'executed_at' => gmdate('c'), 'authorization' => in_array($action, ['release', 'publish-reviewable'], true) ? $authorization : null, 'results' => $results, 'publication_summary' => $publicationSummary], array_merge(recoveryReportRows($results, $jobs), $inventory));
	if ($action === 'inspect') reviewWrite(substr($report, 0, -5) . '.html', recoveryHtml($results, $root));
	echo reviewJson(['report' => $report, 'results' => $results]);
	$exit = count(array_filter($results, static fn(array $row): bool => $row['status'] === 'failed' || !empty($row['candidate_errors']))) ? 1 : 0;
} catch (Throwable $exception) { fwrite(STDERR, $exception->getMessage() . "\n"); $exit = 1; }
finally { if ($db) $db->closeConnection(); }
exit($exit);
