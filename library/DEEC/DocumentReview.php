<?php

class DEEC_DocumentReview
{
	public const STATUS = 'historical_reconstruction_pending_original_comparison';
	public const DIAGNOSTIC_STATUS = 'historical_diagnostic_reconstruction';
	public const UNREVIEWED_STATUS = 'historical_unreviewed_candidate';

	public static function json(array $value): string
	{
		return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
	}

	public static function locatorPath(int $clientId): string
	{
		if ($clientId <= 0) throw new RuntimeException('Invalid review tenant');
		return BASE_PATH . '/files/documents/.review-roots/' . $clientId . '.json';
	}

	public static function privateFile(string $path): void
	{
		if ($path === '' || $path[0] !== '/' || str_contains($path, '\\') || preg_match('~[\x00-\x1f\x7f]|/(?:\.{1,2})(?:/|$)|//~', $path)) throw new RuntimeException('Unsafe review path');
		$current = '';
		foreach (explode('/', substr($path, 1)) as $part) {
			$current .= '/' . $part;
			clearstatcache(true, $current);
			if (is_link($current)) throw new RuntimeException('Symlink in review path');
		}
		$stat = @lstat($path);
		if (!$stat || ($stat['mode'] & 0170000) !== 0100000 || ($stat['mode'] & 0777) !== 0600 || $stat['nlink'] !== 1 || !is_readable($path)) throw new RuntimeException('Review file is not private or readable');
	}

	public static function read(string $path): array
	{
		self::privateFile($path);
		$value = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($value)) throw new RuntimeException('Invalid review record');
		return $value;
	}

	public static function privateDirectory(string $path): void
	{
		if (realpath($path) !== $path || !is_dir($path) || (fileperms($path) & 0777) !== 0700) throw new RuntimeException('A canonical private directory with mode 0700 is required');
		foreach ([BASE_PATH, dirname(__DIR__, 2)] as $root) if ($path === $root || str_starts_with($path, $root . '/')) throw new RuntimeException('Private operator files must be outside the installation and repository');
	}

	public static function snapshot(Zend_Db_Adapter_Abstract $db, array $identity, bool $lock = false): array
	{
		if (!Zend_Registry::isRegistered('User') || !Zend_Registry::isRegistered('Client')) throw new RuntimeException('Document review access denied');
		$module = $identity['module'];
		$table = $identity['controller'];
		if (!DEEC_DocumentArchive::validDocumentType($module, $table) || (int)$identity['id'] <= 0) throw new RuntimeException('Invalid review document');
		$user = Zend_Registry::get('User');
		$client = Zend_Registry::get('Client');
		$permission = new DEEC_Permission((array)$user);
		$context = $permission->getObjectContext($identity['id'], $module, $table);
		if (empty($user['id']) || (int)$user['clientid'] !== $identity['clientid'] || (int)$client['id'] !== $identity['clientid'] || !$context || $context['clientid'] !== $identity['clientid'] || !$permission->hasPermission($module, $table, 'view')) throw new RuntimeException('Document review access denied');
		return self::storedSnapshot($db, $identity, $lock);
	}

	public static function viewingSnapshot(int $id, string $module, string $controller): array
	{
		if (!Zend_Registry::isRegistered('User') || !Zend_Registry::isRegistered('Client') || !DEEC_DocumentArchive::validDocumentType($module, $controller)) throw new RuntimeException('Document viewing access denied');
		$user = (array)Zend_Registry::get('User');
		$client = Zend_Registry::get('Client');
		$permission = new DEEC_Permission($user);
		$context = $permission->getObjectContext($id, $module, $controller);
		if (empty($user['id']) || (int)$user['clientid'] !== (int)$client['id'] || !$context || !$permission->hasPermission($module, $controller, 'view')) throw new RuntimeException('Document viewing access denied');
		$identity = ['id' => $id, 'clientid' => $context['clientid'], 'module' => $module, 'controller' => $controller, 'document_number' => (string)($context['parent'][$controller . 'id'] ?? '')];
		return self::storedSnapshot(Zend_Db_Table::getDefaultAdapter(), $identity, false);
	}

	private static function storedSnapshot(Zend_Db_Adapter_Abstract $db, array $identity, bool $lock): array
	{
		$module = $identity['module'];
		$table = $identity['controller'];
		$suffix = $lock ? ' FOR UPDATE' : '';
		$document = $db->fetchRow("SELECT * FROM `$table` WHERE id = ? AND clientid = ? AND deleted = 0" . $suffix, [$identity['id'], $identity['clientid']]);
		if (!$document || (string)($document[$table . 'id'] ?? '') !== $identity['document_number'] || !DEEC_Permission::isDocumentReadonly($module, $table, $document)) throw new RuntimeException('Issued document identity changed');
		$snapshot = ['document' => $document];
		foreach (['pos', 'posset'] as $type) $snapshot[$type] = $db->fetchAll("SELECT * FROM `{$table}{$type}` WHERE parentid = ? AND clientid = ? ORDER BY id" . $suffix, [$identity['id'], $identity['clientid']]);
		$snapshot['rules'] = $db->fetchAll("SELECT * FROM pricerulepos WHERE clientid = ? AND module = ? AND controller = ? AND parentid IN (SELECT id FROM `{$table}pos` WHERE parentid = ? AND clientid = ?) ORDER BY id" . $suffix, [$identity['clientid'], $module, $table . 'pos', $identity['id'], $identity['clientid']]);
		return ['data' => $snapshot, 'sha256' => hash('sha256', self::json($snapshot))];
	}

	public function openViewingCopy(int $id, string $module, string $controller, bool $generate = true): array
	{
		$snapshot = self::viewingSnapshot($id, $module, $controller);
		$archiveDb = new Application_Model_DbTable_Documentarchive();
		$original = $archiveDb->getForDocument($id, $module, $controller, 'pdf', true);
		if ($original) {
			$bytes = file_get_contents($archiveDb->getPath($original));
			if ($bytes === false || strlen($bytes) !== (int)$original['filesize'] || !hash_equals($original['sha256'], hash('sha256', $bytes))) throw new RuntimeException('Original changed before viewing');
			$filename = $original['filename'];
			$status = 'archived_original';
		} else {
			$result = $this->storedViewingCopy($snapshot, $id, $module, $controller, $generate);
			if ($result === null) return ['status' => 'copy_can_be_generated', 'filename' => self::documentFilename($snapshot['data']['document'], $controller)];
			$bytes = $result['bytes'];
			$filename = self::documentFilename($snapshot['data']['document'], $controller);
			$status = $result['status'];
		}
		$stream = fopen('php://memory', 'w+b');
		if (!$stream) throw new RuntimeException('Viewing stream unavailable');
		if (fwrite($stream, $bytes) !== strlen($bytes) || !rewind($stream)) { fclose($stream); throw new RuntimeException('Viewing stream failed'); }
		return ['stream' => $stream, 'filename' => $filename, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'status' => $status];
	}

	public static function documentFilename(array $document, string $controller): string
	{
		$stored = (string)($document['filename'] ?? '');
		if (DEEC_Emailattachment::validFilename($stored) && strtolower(pathinfo($stored, PATHINFO_EXTENSION)) === 'pdf') return $stored;
		$number = (string)($document[$controller . 'id'] ?? '');
		$filename = $controller . ($number === '' ? '' : '-' . $number) . '.pdf';
		return DEEC_Emailattachment::validFilename($filename) ? $filename : $controller . '.pdf';
	}

	public static function isViewingCopy(int $clientId, string $sha): bool
	{
		if ($clientId <= 0 || !preg_match('/^[a-f0-9]{64}$/D', $sha)) throw new RuntimeException('Invalid viewing copy identity');
		// Orphaned files in this protected area remain non-originals as well.
		return (bool)glob(BASE_PATH . '/files/documents/.viewing-copies/' . $clientId . '/*/*/*/*/*/' . $sha . '.pdf');
	}

	private function storedViewingCopy(array $snapshot, int $id, string $module, string $controller, bool $generate): ?array
	{
		require_once __DIR__ . '/DocumentRecovery.php';
		$identity = ['clientid' => (int)$snapshot['data']['document']['clientid'], 'module' => $module, 'controller' => $controller, 'id' => $id];
		$locale = str_starts_with((string)Zend_Registry::get('Zend_Locale'), 'en') ? 'en_US' : 'de_DE';
		$directory = BASE_PATH . '/files/documents/.viewing-copies/' . $identity['clientid'] . '/' . $module . '/' . $controller . '/' . $id . '/' . $snapshot['sha256'] . '/' . $locale;
		if (!$generate) return $this->readStoredViewingCopy($snapshot, $identity, $locale, $directory);
		$privateRoot = BASE_PATH . '/files/documents/.viewing-copies'; reviewSafePath($privateRoot);
		if (!is_dir($privateRoot) && !@mkdir($privateRoot, 0700, true) && !is_dir($privateRoot)) throw new RuntimeException('Viewing copy directory unavailable');
		reviewDirectory($privateRoot); reviewSafePath($directory);
		if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Viewing copy directory unavailable');
		reviewDirectory($directory);
		$lockPath = $directory . '/.lock'; reviewSafePath($lockPath);
		if (file_exists($lockPath)) self::privateFile($lockPath);
		$lock = fopen($lockPath, 'c+b');
		if (!$lock) throw new RuntimeException('Viewing copy lock unavailable');
		try {
			if (!chmod($lockPath, 0600) || !flock($lock, LOCK_EX)) throw new RuntimeException('Viewing copy lock failed');
			self::privateFile($lockPath);
			$recordPath = $directory . '/copy.json';
			if (!file_exists($recordPath)) {
				$result = (new DEEC_Pdf())->viewingCopy($id, $module, $controller);
				if (self::viewingSnapshot($id, $module, $controller)['sha256'] !== $snapshot['sha256']) throw new RuntimeException('Stored document changed before copy publication');
				$sha = hash('sha256', $result['bytes']); $path = $directory . '/' . $sha . '.pdf';
				$archive = new DEEC_DocumentArchive(BASE_PATH);
				if (file_exists($path)) self::privateFile($path);
				else {
					$archive->storeAt($result['bytes'], $path, 'pdf');
					if (!chmod($path, 0600)) throw new RuntimeException('Viewing copy permissions failed');
				}
				$file = $archive->inspect($path, 'pdf');
				if ($file['status'] !== 'ok' || $file['sha256'] !== $sha || $file['size'] !== strlen($result['bytes'])) throw new RuntimeException('Stored viewing copy differs');
				reviewWrite($recordPath, self::json(['schema_version' => 1, 'identity' => $identity, 'snapshot_sha256' => $snapshot['sha256'], 'locale' => $locale, 'status' => $result['status'], 'sha256' => $sha, 'size' => $file['size'], 'generated_at' => gmdate('c'), 'generated_by' => (int)Zend_Registry::get('User')['id'], 'original' => false, 'archive_registration_allowed' => false]));
			}
			return $this->readStoredViewingCopy($snapshot, $identity, $locale, $directory);
		} finally { flock($lock, LOCK_UN); fclose($lock); }
	}

	private function readStoredViewingCopy(array $snapshot, array $identity, string $locale, string $directory): ?array
	{
		reviewSafePath($directory);
		$current = BASE_PATH . '/files/documents/.viewing-copies';
		foreach (array_merge([''], explode('/', substr($directory, strlen($current) + 1))) as $part) {
			if ($part !== '') $current .= '/' . $part;
			clearstatcache(true, $current);
			if (!file_exists($current)) return null;
			if (!is_dir($current) || (fileperms($current) & 0777) !== 0700 || !is_readable($current) || !is_executable($current)) throw new RuntimeException('Viewing copy directory is not private or accessible');
		}
		$recordPath = $directory . '/copy.json';
		if (!file_exists($recordPath)) return null;
		$record = self::read($recordPath);
		if (($record['schema_version'] ?? null) !== 1 || ($record['identity'] ?? null) !== $identity || ($record['snapshot_sha256'] ?? '') !== $snapshot['sha256'] || ($record['locale'] ?? '') !== $locale
			|| !in_array($record['status'] ?? '', ['historical_reconstructed_viewing_copy', 'historical_diagnostic_viewing_copy'], true) || ($record['original'] ?? null) !== false || ($record['archive_registration_allowed'] ?? null) !== false || !preg_match('/^[a-f0-9]{64}$/D', $record['sha256'] ?? '')) throw new RuntimeException('Invalid stored viewing copy record');
		$path = $directory . '/' . $record['sha256'] . '.pdf'; self::privateFile($path);
		$bytes = file_get_contents($path);
		if ($bytes === false || strlen($bytes) !== $record['size'] || !hash_equals($record['sha256'], hash('sha256', $bytes)) || (new DEEC_DocumentArchive(BASE_PATH))->inspect($path, 'pdf')['status'] !== 'ok') throw new RuntimeException('Stored viewing copy integrity differs');
		return ['bytes' => $bytes, 'status' => $record['status']];
	}

	public static function validateRoot(string $root, int $clientId): array
	{
		if (realpath($root) !== $root || !is_dir($root) || (fileperms($root) & 0777) !== 0700) throw new RuntimeException('Invalid private review area');
		foreach ([BASE_PATH, dirname(__DIR__, 2)] as $webroot) if ($root === $webroot || str_starts_with($root, $webroot . '/') || str_starts_with($webroot, $root . '/')) throw new RuntimeException('Review PDFs must remain outside webroots');
		$binding = self::read($root . '/review.json');
		if (($binding['clientid'] ?? 0) !== $clientId || ($binding['base'] ?? '') !== BASE_PATH) throw new RuntimeException('Foreign review area');
		return $binding;
	}

	public static function publicationDirectory(string $root, array $identity, bool $diagnostic = false): string
	{
		if (!DEEC_DocumentArchive::validDocumentType($identity['module'], $identity['controller']) || (int)$identity['id'] <= 0) throw new RuntimeException('Invalid review document');
		return $root . ($diagnostic ? '/published-diagnostics/' : '/published/') . $identity['module'] . '-' . $identity['controller'] . '-' . $identity['id'];
	}

	public static function candidate(string $root, string $directory, array $identity, array $snapshot, string $sha, bool $diagnostic = false): array
	{
		if (!preg_match('/^[a-f0-9]{64}$/D', $sha) || dirname($directory) !== $root || !preg_match('/^[a-zA-Z0-9_.%~-]+$/D', basename($directory))) throw new RuntimeException('Invalid review candidate');
		$proofPath = $directory . ($diagnostic ? '/diagnostics/' : '/reconstructions/') . $sha . '.json';
		$proof = self::read($proofPath);
		if (($proof['schema_version'] ?? 0) !== 1 || ($proof['identity'] ?? null) !== $identity || ($proof['sha256'] ?? '') !== $sha || ($proof['snapshot_sha256'] ?? '') !== $snapshot['sha256']
			|| ($proof['fiscal_correction'] ?? null) !== false || ($proof['manual_approval_required'] ?? null) !== true || empty($proof['generated_by']) || empty($proof['generated_at']) || !strtotime($proof['generated_at'])) throw new RuntimeException('Reconstruction proof is invalid or stale');
		if ($diagnostic) {
			if (($proof['status'] ?? '') !== self::DIAGNOSTIC_STATUS || ($proof['archive_approval_allowed'] ?? null) !== false || ($proof['diagnosis']['schema_version'] ?? 0) !== 1 || ($proof['diagnosis']['accounting_basis_verified'] ?? null) !== false || !is_array($proof['diagnosis']['issues'] ?? null) || !is_bool($proof['diagnosis']['incomplete'] ?? null)) throw new RuntimeException('Diagnostic proof is invalid');
		} elseif (($proof['validation']['schema_version'] ?? 0) !== 1 || empty($proof['validation']['displayed']['header']) || empty($proof['validation']['displayed']['positions']) || isset($proof['diagnosis'])) throw new RuntimeException('Validated reconstruction proof is missing');
		$path = $directory . ($diagnostic ? '/diagnostic-candidates/' : '/candidates/') . $sha . '.pdf';
		self::privateFile($path);
		$file = (new DEEC_DocumentArchive($root))->inspect($path, 'pdf');
		if ($file['status'] !== 'ok' || $file['sha256'] !== $sha || $file['size'] !== ($proof['size'] ?? null)) throw new RuntimeException('Review PDF structure, size or SHA-256 differs');
		return ['file' => $file, 'proof' => $proof, 'proof_sha256' => hash_file('sha256', $proofPath), 'status' => $diagnostic ? self::DIAGNOSTIC_STATUS : self::STATUS];
	}

	public static function receipt(array $receipt, array $identity, array $candidate, array $snapshot): void
	{
		$confirmation = $receipt['database_confirmation'] ?? [];
		$diagnostic = ($candidate['status'] ?? self::STATUS) === self::DIAGNOSTIC_STATUS;
		if (($receipt['schema_version'] ?? 0) !== 1 || ($receipt['status'] ?? '') !== ($diagnostic ? self::DIAGNOSTIC_STATUS : self::STATUS) || ($receipt['identity'] ?? null) !== $identity
			|| ($receipt['sha256'] ?? '') !== $candidate['file']['sha256'] || ($receipt['size'] ?? 0) !== $candidate['file']['size'] || ($receipt['snapshot_sha256'] ?? '') !== $snapshot['sha256'] || ($receipt['proof_sha256'] ?? '') !== $candidate['proof_sha256']
			|| ($confirmation['database_values_verified'] ?? null) !== !$diagnostic || ($confirmation['original_comparison_performed'] ?? null) !== false || empty($confirmation['confirmed_by']) || empty($confirmation['confirmed_at']) || !strtotime($confirmation['confirmed_at'])
			|| !preg_match('/^[a-f0-9]{64}$/D', $confirmation['run_sha256'] ?? '') || !preg_match('/^[a-f0-9]{64}$/D', $confirmation['scope_sha256'] ?? '')) throw new RuntimeException('Review publication is invalid or stale');
	}

	public function candidates(int $id, string $module, string $controller): array
	{
		return $this->inspection($id, $module, $controller)['candidates'];
	}

	public function inspection(int $id, string $module, string $controller): array
	{
		if (!Zend_Registry::isRegistered('User') || !Zend_Registry::isRegistered('Client')) throw new RuntimeException('Document review access denied');
		$clientId = (int)Zend_Registry::get('Client')['id'];
		$permission = new DEEC_Permission((array)Zend_Registry::get('User'));
		$context = $permission->getObjectContext($id, $module, $controller);
		if (!$context || $context['clientid'] !== $clientId || !$permission->hasPermission($module, $controller, 'view')) throw new RuntimeException('Document review access denied');
		$identity = ['module' => $module, 'controller' => $controller, 'id' => $id, 'clientid' => $clientId, 'document_number' => (string)($context['parent'][$controller . 'id'] ?? '')];
		$db = Zend_Db_Table::getDefaultAdapter();
		$locator = self::locatorPath($clientId);
		$results = ['candidates' => [], 'invalid' => [], 'diagnostic_publications' => 0];
		if (!file_exists($locator)) return $results;
		if ($db->fetchOne('SELECT COUNT(*) FROM documentarchive WHERE clientid = ? AND module = ? AND controller = ? AND documentid = ? AND format = ?', [$clientId, $module, $controller, $id, 'pdf'])) return $results;
		$snapshot = self::snapshot($db, $identity);
		$location = self::read($locator);
		if (($location['clientid'] ?? 0) !== $clientId || ($location['base'] ?? '') !== BASE_PATH) throw new RuntimeException('Foreign review locator');
		$root = $location['review'];
		self::validateRoot($root, $clientId);
		if (($location['binding_sha256'] ?? '') !== hash_file('sha256', $root . '/review.json')) throw new RuntimeException('Review area binding changed');
		foreach ([false, true] as $diagnostic) foreach (glob(self::publicationDirectory($root, $identity, $diagnostic) . '/*.json') as $path) {
			if ($diagnostic) $results['diagnostic_publications']++;
			$sha = basename($path, '.json');
			try {
				$receipt = self::read($path);
				$candidate = self::candidate($root, $root . '/' . $receipt['directory'], $identity, $snapshot, $sha, $diagnostic);
				self::receipt($receipt, $identity, $candidate, $snapshot);
				$results['candidates'][$sha] = $candidate + ['receipt' => $receipt];
			} catch (RuntimeException | InvalidArgumentException | JsonException $error) {
				$results['invalid'][$sha] = $error->getMessage();
				error_log('Historical review candidate unavailable: ' . $error->getMessage());
			}
		}
		try {
			$recovery = new DEEC_DocumentRecovery();
			foreach ($recovery->unreviewed($recovery->job($id, $module, $controller), $snapshot) as $sha => $candidate) if (!isset($results['candidates'][$sha])) $results['candidates'][$sha] = $candidate;
		} catch (RuntimeException | InvalidArgumentException | JsonException $error) { $results['invalid']['review_context'] = $error->getMessage(); }
		return $results;
	}

	public function open(int $id, string $module, string $controller, string $sha): array
	{
		if (!preg_match('/^[a-f0-9]{64}$/D', $sha)) throw new RuntimeException('Invalid review candidate');
		$candidate = $this->candidates($id, $module, $controller)[$sha] ?? null;
		if (!$candidate) throw new RuntimeException('Review candidate unavailable');
		$path = $candidate['file']['path'];
		$stream = @fopen($path, 'rb');
		if (!$stream) throw new RuntimeException('Review PDF unavailable');
		try {
			$stat = fstat($stream);
			$hash = hash_init('sha256');
			$size = hash_update_stream($hash, $stream);
			if ($stat['size'] !== $candidate['file']['size'] || $size !== $stat['size'] || hash_final($hash) !== $sha || !rewind($stream)) throw new RuntimeException('Review PDF changed before download');
			return ['stream' => $stream, 'size' => $size, 'sha256' => $sha, 'status' => $candidate['status'], 'filename' => $controller . '-' . $id . ($candidate['status'] === self::DIAGNOSTIC_STATUS ? '-diagnostic-' : '-pending-original-comparison-') . substr($sha, 0, 12) . '.pdf'];
		} catch (Throwable $error) { fclose($stream); throw $error; }
	}
}
