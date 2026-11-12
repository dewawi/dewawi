<?php

class DEEC_DocumentArchive
{
	private const DOCUMENTS = [
		'sales' => ['quote', 'salesorder', 'invoice', 'deliveryorder', 'creditnote', 'reminder'],
		'purchases' => ['quoterequest', 'purchaseorder'],
	];
	private const FORMATS = ['pdf', 'xml'];
	private string $base;

	public function __construct(string $base)
	{
		$provided = rtrim($base, '/');
		if ($provided === '' || is_link($provided)) throw new InvalidArgumentException('Invalid archive base');
		$resolved = realpath($provided);
		if (!$resolved || !is_dir($resolved)) throw new InvalidArgumentException('Invalid archive base');
		$this->base = rtrim($resolved, '/');
	}

	public static function documents(): array { return self::DOCUMENTS; }
	public static function validDocumentType(string $module, string $controller): bool { return in_array($controller, self::DOCUMENTS[$module] ?? [], true); }

	public function relativePath(int $clientId, string $module, string $controller, int $documentId, string $format = 'pdf'): string
	{
		$format = strtolower($format);
		if ($clientId <= 0 || $documentId <= 0 || !self::validDocumentType($module, $controller) || !in_array($format, self::FORMATS, true)) throw new InvalidArgumentException('Invalid document archive context');
		return 'files/documents/' . $this->shard($clientId) . '/' . $module . '/' . $controller . '/' . intdiv($documentId, 1000) . '/' . $documentId . '.' . $format;
	}

	public function path(int $clientId, string $module, string $controller, int $documentId, string $format = 'pdf'): string
	{
		return $this->base . '/' . $this->relativePath($clientId, $module, $controller, $documentId, $format);
	}

	public function paths(array $document): array
	{
		$module = (string)($document['module'] ?? '');
		$controller = (string)($document['controller'] ?? '');
		$id = (int)($document['id'] ?? 0);
		$clientId = (int)($document['clientid'] ?? 0);
		$contactNumber = (int)($document['contactid'] ?? 0);
		$contactRecordId = (int)($document['contact_record_id'] ?? 0);
		$filename = (string)($document['filename'] ?? '');
		$format = strtolower((string)($document['format'] ?? 'pdf'));
		if ($contactNumber <= 0 || !DEEC_Emailattachment::validFilename($filename)) throw new InvalidArgumentException('Invalid document archive context');
		$client = $this->shard($clientId);
		return [
			'canonical' => $this->path($clientId, $module, $controller, $id, $format),
			'attachment' => $this->base . '/files/attachments/' . $module . '/' . $controller . '/' . $client . '/' . $this->shard($id) . '/' . $filename,
			'current' => $this->base . '/files/contacts/' . $client . '/' . $this->shard($contactNumber) . '/' . $filename,
			'legacy' => $contactRecordId > 0 ? $this->base . '/files/contacts/' . $client . '/' . $this->shard($contactRecordId) . '/' . $filename : '',
		];
	}

	public function inspect(string $path, ?string $format = null): array
	{
		$result = ['path' => $path, 'status' => 'missing', 'size' => null, 'sha256' => null];
		if ($path === '') return $result;
		$this->assertInside($path);
		if ($this->hasSymlink($path)) return array_replace($result, ['status' => 'symlink']);
		if (!file_exists($path)) return $result;
		if (!is_file($path)) return array_replace($result, ['status' => 'collision']);
		if (!is_readable($path)) return array_replace($result, ['status' => 'unreadable']);
		$size = filesize($path);
		$hash = hash_file('sha256', $path);
		if ($size === 0) return array_replace($result, ['status' => 'empty', 'size' => 0, 'sha256' => $hash]);
		$format = strtolower($format ?: pathinfo($path, PATHINFO_EXTENSION));
		$status = $format === 'xml' ? $this->inspectXml($path) : $this->inspectPdf($path, $size);
		return array_replace($result, ['status' => $status, 'size' => $size, 'sha256' => $hash]);
	}

	private function inspectPdf(string $path, int $size): string
	{
		$handle = @fopen($path, 'rb');
		if (!$handle) return 'unreadable';
		try { $header = fread($handle, 5); fseek($handle, max(0, $size - 2048)); $tail = stream_get_contents($handle); }
		finally { fclose($handle); }
		return $size >= 12 && $header === '%PDF-' && str_ends_with(rtrim((string)$tail), '%%EOF') ? 'ok' : 'invalid_pdf';
	}

	private function inspectXml(string $path): string
	{
		$previous = libxml_use_internal_errors(true);
		try { $document = new DOMDocument(); return $document->load($path, LIBXML_NONET) && $document->documentElement ? 'ok' : 'invalid_xml'; }
		finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
	}

	public function plan(array $document, array $metadata = []): array
	{
		$format = strtolower((string)($document['format'] ?? 'pdf'));
		$result = ['module' => (string)($document['module'] ?? ''), 'controller' => (string)($document['controller'] ?? ''), 'id' => (int)($document['id'] ?? 0),
			'clientid' => (int)($document['clientid'] ?? 0), 'document_number' => (string)($document['document_number'] ?? ''), 'filename' => (string)($document['filename'] ?? ''),
			'format' => $format, 'status' => 'blocked', 'action' => 'none', 'reason' => '', 'source' => null, 'files' => [], 'metadata' => []];
		if (count($metadata) === 1 && self::hasRecoveryApproval($metadata[0])) {
			$row = $metadata[0];
			$relative = $this->relativePath((int)$document['clientid'], $document['module'], $document['controller'], (int)$document['id'], $format);
			$file = $this->inspect($this->base . '/' . $relative, $format);
			$matches = ['clientid' => $document['clientid'], 'documentid' => $document['id'], 'module' => $document['module'], 'controller' => $document['controller'], 'format' => $format, 'path' => $relative];
			$valid = $file['status'] === 'ok' && (int)$row['filesize'] === $file['size'] && hash_equals($row['sha256'], $file['sha256']);
			foreach ($matches as $key => $value) if ((string)$row[$key] !== (string)$value) $valid = false;
			if (DEEC_Emailattachment::validFilename((string)($document['filename'] ?? '')) && $row['filename'] !== $document['filename']) $valid = false;
			if ($valid) {
				$parameters = json_decode($row['parameters'], true);
				$result['historical_conflicts'] = $parameters['recovery']['historical_conflicts'] ?? [];
				$result['files']['canonical'] = $file;
				if ((int)($document['contact_match_count'] ?? 1) > 1) $result['historical_conflicts'][] = 'contact_mapping_ambiguous';
				else try {
					$hashes = [];
					foreach ($this->paths($document) as $name => $path) {
						$found = $this->inspect($path, $format);
						$result['files'][$name] = $found;
						if ($name === 'canonical') continue;
						if ($found['status'] === 'ok') $hashes[] = $found['sha256'];
						elseif ($found['status'] !== 'missing') $result['historical_conflicts'][] = 'invalid_historical_source';
					}
					if (count(array_unique($hashes)) > 1) $result['historical_conflicts'][] = 'historical_versions_differ';
					if (array_diff($hashes, [$file['sha256']])) $result['historical_conflicts'][] = 'canonical_version_differs';
				} catch (InvalidArgumentException $ignored) { $result['historical_conflicts'][] = 'invalid_document_context'; }
				$result['historical_conflicts'] = array_values(array_unique($result['historical_conflicts']));
				return array_replace($result, ['status' => 'complete', 'source' => 'canonical', 'metadata' => ['active' => 1, 'deleted' => 0, 'foreign' => 0], 'manually_approved' => true]);
			}
		}
		if ((int)($document['contact_match_count'] ?? 1) > 1) { $result['reason'] = 'contact_mapping_ambiguous'; return $result; }
		try { $paths = $this->paths($document); } catch (Throwable $exception) { $result['reason'] = 'invalid_document_context'; return $result; }
		foreach ($paths as $name => $path) $result['files'][$name] = $this->inspect($path, $format);
		$canonical = $result['files']['canonical'];
		$sources = [];
		foreach (['attachment', 'current', 'legacy'] as $name) {
			$file = $result['files'][$name];
			if ($file['status'] === 'ok') $sources[$name] = $file;
			elseif ($file['status'] !== 'missing') { $result['reason'] = 'invalid_historical_source'; return $result; }
		}
		$sourceHashes = array_values(array_unique(array_column($sources, 'sha256')));
		if (count($sourceHashes) > 1) { $result['reason'] = 'historical_versions_differ'; return $result; }
		if (!in_array($canonical['status'], ['ok', 'missing'], true)) { $result['reason'] = 'canonical_collision_or_invalid'; return $result; }
		if ($canonical['status'] === 'ok' && $sourceHashes && !hash_equals($canonical['sha256'], $sourceHashes[0])) { $result['reason'] = 'canonical_version_differs'; return $result; }

		$active = array_values(array_filter($metadata, static fn(array $row): bool => empty($row['deleted'])));
		$result['metadata'] = ['active' => count($active), 'deleted' => count($metadata) - count($active),
			'foreign' => count(array_filter($metadata, static fn(array $row): bool => (int)($row['clientid'] ?? 0) !== (int)$document['clientid']))];
		$expected = $canonical['status'] === 'ok' ? $canonical : ($sources ? reset($sources) : null);
		$relative = substr($paths['canonical'], strlen($this->base) + 1);
		foreach ($active as $row) {
			if ((int)($row['clientid'] ?? 0) !== (int)$document['clientid'] || (string)($row['module'] ?? '') !== $document['module']
				|| (string)($row['controller'] ?? '') !== $document['controller'] || (int)($row['documentid'] ?? 0) !== (int)$document['id']
				|| (string)($row['filename'] ?? '') !== $document['filename'] || (string)($row['format'] ?? '') !== $format || (string)($row['path'] ?? '') !== $relative
				|| ($expected && (int)($row['filesize'] ?? -1) !== (int)$expected['size']) || ($expected && !hash_equals((string)($row['sha256'] ?? ''), (string)$expected['sha256']))) {
				$result['reason'] = 'metadata_differs'; return $result;
			}
		}
		if (count($active) > 1) { $result['reason'] = 'metadata_ambiguous'; return $result; }
		if ($canonical['status'] === 'missing' && !$sources) { $result['reason'] = 'original_missing'; return $result; }
		$result['source'] = $canonical['status'] === 'ok' ? 'canonical' : array_key_first($sources);
		$result['status'] = 'ready';
		if ($canonical['status'] === 'missing') $result['action'] = 'copy_and_register';
		elseif (!$active) $result['action'] = 'register_metadata';
		else { $result['status'] = 'complete'; $result['action'] = 'none'; }
		return $result;
	}

	public static function pdfProvenance(array $registration): string
	{
		$parameters = json_decode((string)($registration['parameters'] ?? ''), true);
		if (!is_array($parameters)) return 'registered_archive_origin_unconfirmed';
		$recovery = $parameters['recovery'] ?? null;
		if (!is_array($recovery)) return array_key_exists('recovery', $parameters) ? 'registered_archive_origin_unconfirmed' : 'issued_original';
		$origin = $recovery['selection']['origin'] ?? [];
		$approved = self::hasRecoveryApproval($registration);
		if (($origin['kind'] ?? '') === 'reconstructed' || ($origin['category'] ?? '') === 'reviewed_reconstruction_duplicate' || !empty($recovery['reconstruction']) || !empty($recovery['approval']['reconstruction_sha256'])) return $approved ? 'reviewed_reconstruction_duplicate' : 'reconstruction_origin_unconfirmed';
		return $approved && ($origin['category'] ?? '') === 'verified_recovered_original' ? 'verified_recovered_original' : 'registered_archive_origin_unconfirmed';
	}

	public static function hasRecoveryApproval(array $row): bool
	{
		$parameters = json_decode((string)($row['parameters'] ?? ''), true);
		$approval = $parameters['recovery']['approval'] ?? [];
		if ((int)($row['version'] ?? 0) !== 1 || ($row['format'] ?? '') !== 'pdf' || !DEEC_Emailattachment::validFilename((string)($row['filename'] ?? '')) || ($approval['manual'] ?? false) !== true
			|| (int)($approval['approved_by'] ?? 0) <= 0 || empty($approval['approved_at']) || ($approval['sha256'] ?? '') !== ($row['sha256'] ?? null) || (int)($approval['size'] ?? -1) !== (int)($row['filesize'] ?? -2)) return false;
		foreach (['clientid' => 'clientid', 'module' => 'module', 'controller' => 'controller', 'id' => 'documentid'] as $key => $field) if ((string)($approval['identity'][$key] ?? '') !== (string)($row[$field] ?? '')) return false;
		return true;
	}

	public function copy(array $plan): array
	{
		if (($plan['status'] ?? '') !== 'ready' || ($plan['action'] ?? '') !== 'copy_and_register') throw new InvalidArgumentException('Archive plan is not copyable');
		$source = $plan['files'][$plan['source']]['path'];
		$target = $plan['files']['canonical']['path'];
		$format = (string)($plan['format'] ?? 'pdf');
		$before = $this->inspect($source, $format);
		if ($before['status'] !== 'ok' || !hash_equals((string)$plan['files'][$plan['source']]['sha256'], (string)$before['sha256'])) throw new RuntimeException('Archive source changed');
		$this->copyVerified($source, $target, $before, $format);
		return $this->inspect($target, $format);
	}

	public function store(string $bytes, int $clientId, string $module, string $controller, int $documentId, string $format): array
	{
		$target = $this->path($clientId, $module, $controller, $documentId, $format);
		$this->ensureDirectory(dirname($target));
		$temp = tempnam(dirname($target), '.archive-');
		if ($temp === false) throw new RuntimeException('Archive staging file could not be created');
		try {
			if (file_put_contents($temp, $bytes, LOCK_EX) !== strlen($bytes)) throw new RuntimeException('Archive staging write failed');
			$source = $this->inspect($temp, $format);
			if ($source['status'] !== 'ok') throw new RuntimeException('Archive content is invalid');
			$this->copyVerified($temp, $target, $source, $format);
			return $this->inspect($target, $format);
		} finally { if (is_file($temp)) unlink($temp); }
	}

	public function storeEvidence(string $source, string $target): array
	{
		$file = $this->inspect($source, 'pdf');
		if ($file['status'] !== 'ok') throw new RuntimeException('Invalid PDF evidence');
		$this->copyVerified($source, $target, $file, 'pdf');
		if (!chmod($target, 0600)) throw new RuntimeException('PDF evidence permissions failed');
		$result = $this->inspect($target, 'pdf');
		if ($result['status'] !== 'ok' || $result['size'] !== $file['size'] || $result['sha256'] !== $file['sha256']) throw new RuntimeException('PDF evidence copy differs');
		return $result;
	}

	public function storeFile(string $source, int $clientId, string $module, string $controller, int $documentId, string $format, string $sha): array
	{
		$before = (new self(dirname($source)))->inspect($source, $format);
		if ($before['status'] !== 'ok' || !hash_equals($sha, $before['sha256'])) throw new RuntimeException('Archive source integrity differs');
		$target = $this->path($clientId, $module, $controller, $documentId, $format);
		$this->copyVerified($source, $target, $before, $format);
		return $this->inspect($target, $format);
	}

	private function copyVerified(string $source, string $target, array $before, string $format): void
	{
		$this->ensureDirectory(dirname($target));
		if ($this->hasSymlink($target)) throw new RuntimeException('Archive target contains a symlink');
		$output = @fopen($target, 'x+b');
		if (!$output) throw new RuntimeException(file_exists($target) || is_link($target) ? 'Archive target collision' : 'Archive target is not writable');
		$input = @fopen($source, 'rb');
		try {
			if (!$input || stream_copy_to_stream($input, $output) !== $before['size']) throw new RuntimeException('Archive copy failed');
			if (!fflush($output)) throw new RuntimeException('Archive flush failed');
			if (function_exists('fsync') && !fsync($output)) throw new RuntimeException('Archive sync failed');
		} catch (Throwable $exception) {
			fclose($output); if ($input) fclose($input); if (is_file($target) && !is_link($target)) unlink($target); throw $exception;
		}
		fclose($output); if ($input) fclose($input);
		if (!chmod($target, 0666 & ~umask())) { unlink($target); throw new RuntimeException('Archive permissions could not be set'); }
		$after = $this->inspect($target, $format);
		if ($after['status'] !== 'ok' || $after['size'] !== $before['size'] || !hash_equals($before['sha256'], $after['sha256'])) {
			if (is_file($target) && !is_link($target)) unlink($target);
			throw new RuntimeException('Archive integrity verification failed');
		}
	}

	private function shard(int $id): string { $value = (string)$id; return $value[0] . '/' . ($value[1] ?? '0') . '/' . $value; }
	private function assertInside(string $path): void
	{
		if (!str_starts_with($path, $this->base . '/') || str_contains($path, "\0") || str_contains($path, '\\')) throw new InvalidArgumentException('Archive path escapes base');
	}
	private function hasSymlink(string $path): bool
	{
		$current = $this->base;
		foreach (explode('/', substr($path, strlen($this->base) + 1)) as $part) {
			if ($part === '' || $part === '.' || $part === '..') return true;
			$current .= '/' . $part;
			if (is_link($current)) return true;
		}
		return false;
	}
	private function ensureDirectory(string $directory): void
	{
		$this->assertInside($directory);
		$current = $this->base;
		foreach (explode('/', substr($directory, strlen($this->base) + 1)) as $part) {
			if ($part === '' || $part === '.' || $part === '..') throw new RuntimeException('Invalid archive directory');
			$current .= '/' . $part;
			if (is_link($current)) throw new RuntimeException('Archive directory contains a symlink');
			if (file_exists($current)) { if (!is_dir($current)) throw new RuntimeException('Archive directory collision'); continue; }
			if (!mkdir($current, 0775)) throw new RuntimeException('Archive directory is not writable');
		}
	}
}
