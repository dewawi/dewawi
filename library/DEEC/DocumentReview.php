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
		$suffix = $lock ? ' FOR UPDATE' : '';
		$document = $db->fetchRow("SELECT * FROM `$table` WHERE id = ? AND clientid = ? AND deleted = 0" . $suffix, [$identity['id'], $identity['clientid']]);
		if (!$document || (string)($document[$table . 'id'] ?? '') !== $identity['document_number'] || !DEEC_Permission::isDocumentReadonly($module, $table, $document)) throw new RuntimeException('Issued document identity changed');
		$snapshot = ['document' => $document];
		foreach (['pos', 'posset'] as $type) $snapshot[$type] = $db->fetchAll("SELECT * FROM `{$table}{$type}` WHERE parentid = ? AND clientid = ? ORDER BY id" . $suffix, [$identity['id'], $identity['clientid']]);
		$snapshot['rules'] = $db->fetchAll("SELECT * FROM pricerulepos WHERE clientid = ? AND module = ? AND controller = ? AND parentid IN (SELECT id FROM `{$table}pos` WHERE parentid = ? AND clientid = ?) ORDER BY id" . $suffix, [$identity['clientid'], $module, $table . 'pos', $identity['id'], $identity['clientid']]);
		return ['data' => $snapshot, 'sha256' => hash('sha256', self::json($snapshot))];
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
