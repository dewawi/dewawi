<?php

// Shared recovery operations used by the CLI and the authenticated document review.
function reviewId($value): int
{
	$id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
	if (!$id) throw new RuntimeException('Invalid document or tenant ID');
	return $id;
}

function reviewIdentity(array $row, int $clientId): array
{
	if (reviewId($row['clientid'] ?? null) !== $clientId) throw new RuntimeException('Foreign tenant in input');
	$module = $row['module'] ?? '';
	$controller = $row['controller'] ?? '';
	if (!is_string($module) || !is_string($controller) || !DEEC_DocumentArchive::validDocumentType($module, $controller)) throw new RuntimeException('Invalid document type');
	$number = $row['document_number'] ?? '';
	if ((!is_string($number) && !is_int($number)) || ((string)$number !== '' && !DEEC_Emailattachment::validFilename(str_replace('/', '-', (string)$number)))) throw new RuntimeException('Invalid visible document number');
	return ['module' => $module, 'controller' => $controller, 'id' => reviewId($row['id'] ?? null), 'clientid' => $clientId, 'document_number' => (string)$number];
}

function reviewKey(array $row): string { return $row['module'] . '/' . $row['controller'] . '/' . $row['id']; }

function reviewSafePath(string $path): void
{
	if ($path === '' || $path[0] !== '/' || str_contains($path, '\\') || preg_match('~[\x00-\x1f\x7f]|/(?:\.{1,2})(?:/|$)|//~', $path)) throw new RuntimeException('Unsafe filesystem path');
	$current = '';
	foreach (explode('/', substr($path, 1)) as $part) {
		$current .= '/' . $part;
		clearstatcache(true, $current);
		if (is_link($current)) throw new RuntimeException('Symlink in filesystem path');
	}
}

function reviewDirectory(string $path): void
{
	reviewSafePath($path);
	if (!file_exists($path)) {
		if (!is_dir(dirname($path))) reviewDirectory(dirname($path));
		if (!mkdir($path, 0700)) throw new RuntimeException('Review directory could not be created');
	}
	clearstatcache(true, $path);
	if (!is_dir($path) || (fileperms($path) & 0777) !== 0700) throw new RuntimeException('Review directories require mode 0700');
}

function reviewPrivateFile(string $path): void
{
	reviewSafePath($path);
	$stat = lstat($path);
	if (!$stat || ($stat['mode'] & 0170000) !== 0100000 || ($stat['mode'] & 0777) !== 0600 || $stat['nlink'] !== 1) throw new RuntimeException('Unsafe existing review file');
}

function reviewWrite(string $path, string $bytes): void
{
	reviewSafePath($path);
	$temp = dirname($path) . '/.write-' . bin2hex(random_bytes(12));
	$handle = @fopen($temp, 'x+b');
	if (!$handle) throw new RuntimeException('Review output could not be staged');
	try {
		try {
			if (!chmod($temp, 0600) || fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle) || (function_exists('fsync') && !fsync($handle))) throw new RuntimeException('Review output write failed');
		} finally { fclose($handle); }
		if (!@link($temp, $path)) throw new RuntimeException('Review output is already occupied');
	} finally { unlink($temp); }
}

function reviewJson(array $value): string { return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n"; }

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
	if (file_exists($job['directory'] . '/diagnostics/' . $file['sha256'] . '.json')) throw new RuntimeException('Diagnostic PDFs cannot be selected or approved as originals');
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

function recoveryOriginalComparison(array $job, array $selected, DEEC_DocumentArchive $reviewArchive): void
{
	$comparison = $selected['selection']['original_comparison'] ?? [];
	if (($comparison['kind'] ?? '') === 'customer_archive_attestation') {
		if (($comparison['assertion_type'] ?? '') !== 'retrospective_customer_archive_attestation' || ($comparison['identity'] ?? null) !== $job['identity'] || ($comparison['sha256'] ?? '') !== $selected['file']['sha256']
			|| ($comparison['confirmed_by'] ?? 0) !== (int)$selected['selection']['reviewed_by'] || !is_string($comparison['confirmed_at'] ?? null) || !strtotime($comparison['confirmed_at']) || strtotime($comparison['confirmed_at']) > time()
			|| !preg_match('/^[a-f0-9]{64}$/D', $comparison['plan_sha256'] ?? '') || !preg_match('/^[a-f0-9]{64}$/D', $comparison['declaration_sha256'] ?? '') || $selected['selection']['origin']['kind'] !== 'historical_pdf') throw new RuntimeException('Customer archive attestation is invalid');
		DEEC_DocumentRecovery::customerArchiveAttestation($comparison['statement'] ?? [], $job['identity'], $comparison['snapshot_sha256'] ?? '', $selected['file']['sha256']);
		return;
	}
	if (($comparison['kind'] ?? '') === 'verified_historical_origin') {
		if (($comparison['assertion_type'] ?? '') !== 'explicit_original_provenance' || ($comparison['identity'] ?? null) !== $job['identity'] || ($comparison['sha256'] ?? '') !== $selected['file']['sha256']
			|| ($comparison['compared_by'] ?? 0) !== (int)$selected['selection']['reviewed_by'] || empty($comparison['compared_at']) || !strtotime($comparison['compared_at']) || empty($comparison['reference']) || ($comparison['amounts_match'] ?? false) !== true
			|| $selected['selection']['origin']['kind'] === 'reconstructed') throw new RuntimeException('Verified historical origin confirmation is invalid');
		return;
	}
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
		'individual_original_comparison_claimed' => in_array($selected['selection']['original_comparison']['kind'] ?? '', ['paper_document', 'original_document', 'verified_dispatch_copy'], true),
		'original_provenance_verified' => in_array($selected['selection']['original_comparison']['kind'] ?? '', ['verified_historical_origin', 'customer_archive_attestation'], true)];
	if (($selected['selection']['original_comparison']['kind'] ?? '') === 'customer_archive_attestation') $approval['retrospective_customer_archive_comparison_attested'] = true;
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
		$canonical = $archive->storeFile($selected['file']['path'], $identity['clientid'], $identity['module'], $identity['controller'], $identity['id'], 'pdf', $selected['file']['sha256']);
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

function recoveryReleaseEvidence(Zend_Db_Adapter_Abstract $db, array $job, array $selected, array $snapshot): void
{
	if ($selected['selection']['origin']['kind'] !== 'reconstructed' && isset($selected['selection']['original_comparison'])) recoveryOriginalComparison($job, $selected, new DEEC_DocumentArchive(dirname($job['directory'])));
	if (($selected['selection']['original_comparison']['kind'] ?? '') === 'customer_archive_attestation' && ($selected['selection']['original_comparison']['snapshot_sha256'] ?? '') !== $snapshot['sha256']) throw new RuntimeException('Customer archive attestation data fingerprint is stale');
	$authorization = $selected['selection']['authorization'] ?? null;
	if (!$authorization) return;
	if (!in_array($authorization['method'] ?? '', ['batch_policy', 'operator_attestation'], true) || ($authorization['confirmed_by'] ?? 0) !== (int)$selected['selection']['reviewed_by'] || ($authorization['database_and_file_backup_confirmed'] ?? false) !== true) throw new RuntimeException('Release authorization is invalid');
	if (($authorization['policy'] ?? '') !== ($authorization['method'] === 'batch_policy' ? 'verified-evidence-v1' : 'document_paper_attestation')) throw new RuntimeException('Release policy does not match its authorization method');
	if (($authorization['method'] ?? '') === 'batch_policy' && ($selected['selection']['origin']['kind'] ?? '') === 'historical_pdf') {
		$proof = recoveryDispatchEvidence($db, $job, $selected['file']['sha256'], $snapshot);
		if (!$proof || ($selected['selection']['origin']['dispatch_evidence']['sha256'] ?? '') !== $proof['sha256']) throw new RuntimeException('Verified dispatch evidence is missing or changed');
	}
}

class DEEC_DocumentRecovery
{
	public static function customerArchiveAttestation(array $statement, array $identity, string $snapshot, string $sha): void
	{
		if (($identity['module'] ?? '') !== 'sales' || ($identity['controller'] ?? '') !== 'invoice') throw new RuntimeException('Customer archive invoice attestation requires sales/invoice');
		if (($statement['document'] ?? null) !== $identity || ($statement['sha256'] ?? '') !== $sha || ($statement['snapshot_sha256'] ?? '') !== $snapshot) throw new RuntimeException('Document-bound declaration identity, SHA or data fingerprint differs');
		foreach (['original_provenance_verified', 'identity_verified', 'customer_archive_compared', 'business_values_compared', 'positions_taxes_totals_match'] as $field) if (($statement[$field] ?? false) !== true) throw new RuntimeException('Explicit original provenance and archive/business-value comparison are required');
		if (($statement['unresolved_differences'] ?? null) !== false || ($statement['differences'] ?? null) !== []) throw new RuntimeException('Unresolved PDF/business-data differences require manual clarification');
		foreach (['provenance', 'comparison_reference'] as $field) if (!is_string($statement[$field] ?? null) || trim($statement[$field]) === '' || strlen($statement[$field]) > 4000) throw new RuntimeException('Concrete original provenance and comparison reference are required');
		$time = $statement['comparison_performed_at'] ?? null;
		if (!array_key_exists('comparison_performed_at', $statement)) throw new RuntimeException('Comparison time must explicitly be known or null');
		if ($time !== null) {
			if (!is_string($time) || !preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:Z|[+-]\d\d:\d\d)$/D', $time)) throw new RuntimeException('Invalid comparison time');
			$date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $time);
			$errors = DateTimeImmutable::getLastErrors();
			if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) || $date->getTimestamp() > time()) throw new RuntimeException('Comparison time must be an actual known ISO timestamp or null');
		}
	}

	public static function token(array $identity): string
	{
		$session = new Zend_Session_Namespace('DocumentRecovery');
		$key = $identity['clientid'] . '/' . reviewKey($identity) . '/' . Zend_Registry::get('User')['id'];
		$tokens = $session->tokens ?? [];
		if (!isset($tokens[$key])) { $tokens[$key] = bin2hex(random_bytes(32)); $session->tokens = $tokens; }
		return $tokens[$key];
	}

	public function job(int $id, string $module, string $controller, bool $create = false): array
	{
		$user = Zend_Registry::get('User'); $clientId = (int)Zend_Registry::get('Client')['id'];
		$permission = new DEEC_Permission((array)$user);
		$context = $permission->getObjectContext($id, $module, $controller);
		if (!$context || $context['clientid'] !== $clientId || !$permission->hasPermission($module, $controller, 'view') || !DEEC_Permission::isDocumentReadonly($module, $controller, $context['parent'])) throw new RuntimeException('Issued document review access denied');
		$identity = ['module' => $module, 'controller' => $controller, 'id' => $id, 'clientid' => $clientId, 'document_number' => (string)($context['parent'][$controller . 'id'] ?? '')];
		$location = DEEC_DocumentReview::read(DEEC_DocumentReview::locatorPath($clientId));
		if (($location['clientid'] ?? 0) !== $clientId || ($location['base'] ?? '') !== BASE_PATH) throw new RuntimeException('Foreign review locator');
		$root = $location['review']; DEEC_DocumentReview::validateRoot($root, $clientId);
		if (($location['binding_sha256'] ?? '') !== hash_file('sha256', $root . '/review.json')) throw new RuntimeException('Review binding changed');
		$directories = glob($root . '/' . $module . '-' . $controller . '-' . $id . '-*');
		if (is_dir($root . '/' . $module . '-' . $controller . '-' . $id)) $directories[] = $root . '/' . $module . '-' . $controller . '-' . $id;
		foreach ([false, true] as $diagnostic) foreach (glob(DEEC_DocumentReview::publicationDirectory($root, $identity, $diagnostic) . '/*.json') as $path) {
			$receipt = DEEC_DocumentReview::read($path);
			if (($receipt['identity'] ?? null) !== $identity || !preg_match('/^[a-zA-Z0-9_.%~-]+$/D', $receipt['directory'] ?? '')) throw new RuntimeException('Review publication identity differs');
			$directories[] = $root . '/' . $receipt['directory'];
		}
		$matches = [];
		foreach (array_unique($directories) as $directory) foreach (glob($directory . '/manifest*.json') as $path) {
			$manifest = recoveryJsonFile($path);
			if (reviewIdentity($manifest['selected'] ?? [], $clientId) !== $identity) continue;
			if (!isset($matches[$directory])) $matches[$directory] = $manifest + ['sources' => [], 'candidates' => []];
			else { $matches[$directory]['sources'] = array_merge($matches[$directory]['sources'], $manifest['sources'] ?? []); $matches[$directory]['candidates'] = array_merge($matches[$directory]['candidates'], $manifest['candidates'] ?? []); }
			$matches[$directory]['web_context_manifests'][] = ['file' => basename($path), 'sha256' => hash_file('sha256', $path)];
		}
		if (count($matches) > 1) throw new RuntimeException('Multiple review directories require operator reconciliation');
		if ($matches) { $directory = array_key_first($matches); $manifest = $matches[$directory]; }
		else {
			$directory = $root . '/' . $module . '-' . $controller . '-' . $id . '-' . ($identity['document_number'] === '' ? 'unnumbered' : substr(rawurlencode($identity['document_number']), 0, 90));
			$manifest = ['schema_version' => 1, 'selected' => $identity + ['reported_reasons' => ['web_original_review']], 'sources' => [], 'candidates' => [], 'observed_at' => gmdate('c')];
			if ($create) { reviewDirectory($directory . '/external'); reviewDirectory($directory . '/candidates'); reviewWrite($directory . '/manifest.json', reviewJson($manifest)); reviewWrite($directory . '/selection.json', reviewJson(['document' => $identity, 'decision' => 'pending'])); }
		}
		return ['identity' => $identity, 'manifest' => $manifest, 'directory' => $directory, 'root' => $root];
	}

	public function unreviewed(array $job, array $snapshot): array
	{
		$results = [];
		foreach ($job['manifest']['candidates'] as $entry) {
			if (!preg_match('~^candidates/[a-f0-9]{64}\.pdf$~D', $entry['path'] ?? '')) throw new RuntimeException('Invalid historical candidate path');
			if (is_file($job['directory'] . '/reconstructions/' . $entry['sha256'] . '.json') || is_file($job['directory'] . '/diagnostics/' . $entry['sha256'] . '.json')) continue;
			$path = recoveryPath($job['directory'], $entry['path']); reviewPrivateFile($path);
			$file = (new DEEC_DocumentArchive($job['root']))->inspect($path, 'pdf');
			if ($file['status'] !== 'ok' || $file['sha256'] !== ($entry['sha256'] ?? '') || $file['size'] !== ($entry['size'] ?? null)) continue;
			$results[$file['sha256']] = ['file' => $file, 'status' => DEEC_DocumentReview::UNREVIEWED_STATUS, 'kind' => 'historical_pdf', 'candidate_path' => $entry['path'], 'proof' => ['generated_at' => $job['manifest']['observed_at'] ?? '', 'snapshot_sha256' => $snapshot['sha256']], 'proof_sha256' => hash('sha256', reviewJson($job['manifest'])), 'receipt' => ['database_confirmation' => ['database_values_verified' => false, 'original_comparison_performed' => false]]];
		}
		foreach (glob($job['directory'] . '/uploads/*.json') as $path) {
			$record = recoveryJsonFile($path);
			$sha = basename($path, '.json');
			if (($record['identity'] ?? null) !== $job['identity'] || ($record['snapshot_sha256'] ?? '') !== $snapshot['sha256'] || !preg_match('/^[a-f0-9]{64}$/D', $sha)) continue;
			$filePath = $job['directory'] . '/external/upload-' . $sha . '.pdf'; reviewPrivateFile($filePath);
			$file = (new DEEC_DocumentArchive($job['root']))->inspect($filePath, 'pdf');
			if ($file['status'] !== 'ok' || $file['sha256'] !== $sha || $file['size'] !== $record['size']) continue;
			$results[$sha] = ['file' => $file, 'status' => DEEC_DocumentReview::UNREVIEWED_STATUS, 'kind' => 'historical_pdf', 'candidate_path' => 'external/upload-' . $sha . '.pdf', 'upload' => $record, 'proof' => ['generated_at' => $record['uploaded_at'], 'snapshot_sha256' => $snapshot['sha256']], 'proof_sha256' => hash_file('sha256', $path), 'receipt' => ['database_confirmation' => ['database_values_verified' => false, 'original_comparison_performed' => false]]];
		}
		return $results;
	}

	private function authorize(array $job, array $post): array
	{
		$id = $job['identity']; $permission = new DEEC_Permission((array)Zend_Registry::get('User'));
		if (!$permission->hasPermission($id['module'], $id['controller'], 'edit')) throw new RuntimeException('Original review edit permission denied');
		if (!is_string($post['csrf_token'] ?? null) || !hash_equals(self::token($id), $post['csrf_token'])) throw new RuntimeException('Invalid original review token');
		$snapshot = recoverySnapshot(Zend_Db_Table::getDefaultAdapter(), $id);
		if (($post['expected_number'] ?? null) !== $id['document_number'] || ($post['snapshot_sha256'] ?? '') !== $snapshot['sha256']) throw new RuntimeException('Document identity or data changed; reload the review');
		return $snapshot;
	}

	public function upload(array $job, array $post, string $path, string $filename): array
	{
		$snapshot = $this->authorize($job, $post); $id = $job['identity'];
		if (!(new DEEC_Permission((array)Zend_Registry::get('User')))->hasPermission($id['module'], $id['controller'], 'add')) throw new RuntimeException('Original upload permission denied');
		if (recoveryArchives(Zend_Db_Table::getDefaultAdapter(), $id)) throw new RuntimeException('Existing archive original is protected');
		if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf' || !DEEC_Emailattachment::validUpload($filename, $path)) throw new RuntimeException('Invalid original PDF upload');
		$file = (new DEEC_DocumentArchive(dirname($path)))->inspect($path, 'pdf');
		if ($file['status'] !== 'ok') throw new RuntimeException('Incomplete original PDF upload');
		if (glob($job['root'] . '/*/diagnostics/' . $file['sha256'] . '.json')) throw new RuntimeException('Diagnostic PDFs cannot be uploaded as originals');
		if (glob($job['root'] . '/*/reconstructions/' . $file['sha256'] . '.json')) throw new RuntimeException('Known reconstructions require their existing reviewed candidate');
		$reference = $this->text($post, 'reference');
		return $this->locked($job, function() use ($job, $id, $snapshot, $path, $filename, $file, $reference): array {
			if (recoveryArchives(Zend_Db_Table::getDefaultAdapter(), $id) || recoverySnapshot(Zend_Db_Table::getDefaultAdapter(), $id)['sha256'] !== $snapshot['sha256']) throw new RuntimeException('Document changed during original upload');
			$job = $this->job($id['id'], $id['module'], $id['controller'], true);
			reviewDirectory($job['directory'] . '/uploads'); reviewDirectory($job['directory'] . '/external');
			$target = $job['directory'] . '/external/upload-' . $file['sha256'] . '.pdf';
			if (!file_exists($target)) reviewWrite($target, file_get_contents($path));
			$stored = (new DEEC_DocumentArchive($job['root']))->inspect($target, 'pdf');
			if ($stored['status'] !== 'ok' || $stored['sha256'] !== $file['sha256'] || $stored['size'] !== $file['size']) throw new RuntimeException('Uploaded original integrity differs');
			$receiptPath = $job['directory'] . '/uploads/' . $file['sha256'] . '.json';
			$receipt = ['schema_version' => 1, 'status' => DEEC_DocumentReview::UNREVIEWED_STATUS, 'identity' => $id, 'snapshot_sha256' => $snapshot['sha256'], 'sha256' => $file['sha256'], 'size' => $file['size'], 'original_filename' => $filename, 'reference' => $reference, 'uploaded_by' => (int)Zend_Registry::get('User')['id'], 'uploaded_at' => gmdate('c')];
			if (!file_exists($receiptPath)) reviewWrite($receiptPath, reviewJson($receipt));
			return ['status' => 'uploaded_unreviewed', 'sha256' => $file['sha256']];
		});
	}

	private function text(array $post, string $field): string
	{
		$value = $post[$field] ?? null;
		if (!is_string($value) || trim($value) === '' || mb_strlen($value) > 2000) throw new RuntimeException('Original review reference and reason are required');
		return trim($value);
	}

	public function approve(array $job, array $post): array
	{
		$snapshot = $this->authorize($job, $post); $id = $job['identity']; $db = Zend_Db_Table::getDefaultAdapter();
		$sha = $post['candidate_sha256'] ?? '';
		if (!is_string($sha) || !preg_match('/^[a-f0-9]{64}$/D', $sha) || ($post['identity_match'] ?? '') !== '1' || ($post['content_match'] ?? '') !== '1') throw new RuntimeException('Explicit document identity and content confirmation are required');
		return $this->locked($job, function() use ($job, $id, $post, $snapshot, $sha, $db): array {
			$existing = recoveryArchives($db, $id);
			if ($existing) {
				if (count($existing) === 1 && $existing[0]['sha256'] === $sha && (int)$existing[0]['version'] === 1) {
					$file = (new DEEC_DocumentArchive(BASE_PATH))->inspect(BASE_PATH . '/' . $existing[0]['path'], 'pdf');
					if ($file['status'] === 'ok' && $file['sha256'] === $sha && $file['size'] === (int)$existing[0]['filesize']) return ['status' => 'already_registered'];
				}
				throw new RuntimeException('Existing archive original is protected');
			}
			$candidates = (new DEEC_DocumentReview())->candidates($id['id'], $id['module'], $id['controller']);
			if (array_filter($candidates, static fn(array $version): bool => $version['status'] === DEEC_DocumentReview::DIAGNOSTIC_STATUS)) throw new RuntimeException('Current diagnostic issues require resolution before original approval');
			$candidate = $candidates[$sha] ?? null;
			if (!$candidate || $candidate['status'] === DEEC_DocumentReview::DIAGNOSTIC_STATUS) throw new RuntimeException('Candidate unavailable or diagnostic approval forbidden');
			$reference = $this->text($post, 'reference'); $reason = $this->text($post, 'reason');
			$userId = (int)Zend_Registry::get('User')['id']; $now = gmdate('c');
			$reconstructed = $candidate['status'] === DEEC_DocumentReview::STATUS || is_file($job['directory'] . '/reconstructions/' . $sha . '.json');
			$kind = $post['comparison_kind'] ?? '';
			if (!in_array($kind, ['paper_document', 'original_document', 'verified_dispatch_copy', 'verified_historical_origin'], true) || ($reconstructed && $kind === 'verified_historical_origin')) throw new RuntimeException('Reconstructed duplicates require an actual original comparison');
			$comparison = ['kind' => $kind, 'reference' => $reference, 'amounts_match' => true, 'compared_by' => $userId, 'compared_at' => $now];
			if ($kind === 'verified_historical_origin') $comparison += ['identity' => $id, 'sha256' => $sha, 'assertion_type' => 'explicit_original_provenance'];
			if (in_array($kind, ['original_document', 'verified_dispatch_copy'], true)) {
				$evidenceSha = $post['evidence_sha256'] ?? '';
				if (!is_string($evidenceSha) || !preg_match('/^[a-f0-9]{64}$/D', $evidenceSha)) throw new RuntimeException('Invalid comparison PDF identity');
				$evidence = $candidates[$evidenceSha] ?? null;
				if (!$evidence || $evidence['status'] !== DEEC_DocumentReview::UNREVIEWED_STATUS || $evidence['file']['sha256'] === $sha) throw new RuntimeException('A different verified comparison PDF is required');
				$evidencePath = 'external/evidence-' . $evidence['file']['sha256'] . '.pdf';
				reviewDirectory($job['directory'] . '/external');
				if (!file_exists($job['directory'] . '/' . $evidencePath)) {
					$copied = (new DEEC_DocumentArchive($job['root']))->storeEvidence($evidence['file']['path'], $job['directory'] . '/' . $evidencePath);
					if ($copied['sha256'] !== $evidence['file']['sha256']) throw new RuntimeException('Comparison PDF changed');
				}
				$comparison += ['evidence_path' => $evidencePath, 'evidence_sha256' => $evidence['file']['sha256']];
			}
			$decision = ['document' => $id, 'decision' => 'selected', 'candidate_path' => $reconstructed ? 'candidates/' . $sha . '.pdf' : $candidate['candidate_path'], 'sha256' => $sha, 'reason' => $reason,
				'origin' => ['kind' => $reconstructed ? 'reconstructed' : 'historical_pdf', 'source_path' => $reference, 'category' => $reconstructed ? 'reviewed_reconstruction_duplicate' : 'verified_recovered_original', 'upload' => $candidate['upload'] ?? null], 'original_comparison' => $comparison];
			try {
				$db->beginTransaction();
				if (recoverySnapshot($db, $id, true)['sha256'] !== $snapshot['sha256']) throw new RuntimeException('Business data changed during original review');
				if (recoveryArchives($db, $id, true)) throw new RuntimeException('Existing archive original is protected');
				recoverySelect($job, $decision, $userId, new DEEC_DocumentArchive($job['root']));
				recoveryApprove($db, $job, new DEEC_DocumentArchive($job['root']));
				$result = recoveryImport($db, $job, new DEEC_DocumentArchive($job['root']));
				$db->commit();
				return $result;
			} catch (Throwable $error) { if ($db->getConnection()->inTransaction()) $db->rollBack(); throw $error; }
		});
	}

	private function locked(array $job, callable $operation): array
	{
		reviewPrivateFile($job['root'] . '/.collector.lock');
		$lock = fopen($job['root'] . '/.collector.lock', 'r+b');
		if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); throw new RuntimeException('Review area is busy'); }
		try { return $operation(); } finally { flock($lock, LOCK_UN); fclose($lock); }
	}
}
