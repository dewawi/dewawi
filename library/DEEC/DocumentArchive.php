<?php

class DEEC_DocumentArchive
{
	private const DOCUMENTS = [
		'sales' => ['quote', 'salesorder', 'invoice', 'deliveryorder', 'creditnote', 'reminder'],
		'purchases' => ['quoterequest', 'purchaseorder'],
	];

	private string $base;

	public function __construct(string $base)
	{
		$base = realpath($base);
		if (!$base || !is_dir($base) || is_link($base)) throw new InvalidArgumentException('Invalid archive base');
		$this->base = rtrim($base, '/');
	}

	public static function documents(): array
	{
		return self::DOCUMENTS;
	}

	public static function validDocumentType(string $module, string $controller): bool
	{
		return in_array($controller, self::DOCUMENTS[$module] ?? [], true);
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
		if (!self::validDocumentType($module, $controller) || $id <= 0 || $clientId <= 0 || $contactNumber <= 0
			|| !DEEC_Emailattachment::validFilename($filename)) throw new InvalidArgumentException('Invalid document archive context');
		$client = $this->shard($clientId);
		return [
			'canonical' => $this->base . '/files/attachments/' . $module . '/' . $controller . '/' . $client . '/' . $this->shard($id) . '/' . $filename,
			'current' => $this->base . '/files/contacts/' . $client . '/' . $this->shard($contactNumber) . '/' . $filename,
			'legacy' => $contactRecordId > 0 ? $this->base . '/files/contacts/' . $client . '/' . $this->shard($contactRecordId) . '/' . $filename : '',
		];
	}

	public function inspect(string $path): array
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
		$handle = @fopen($path, 'rb');
		if (!$handle) return array_replace($result, ['status' => 'unreadable', 'size' => $size, 'sha256' => $hash]);
		try {
			$header = fread($handle, 5);
			fseek($handle, max(0, $size - 2048));
			$tail = stream_get_contents($handle);
		} finally {
			fclose($handle);
		}
		$status = $size >= 12 && $header === '%PDF-' && str_ends_with(rtrim((string)$tail), '%%EOF') ? 'ok' : 'invalid_pdf';
		return array_replace($result, ['status' => $status, 'size' => $size, 'sha256' => $hash]);
	}

	public function plan(array $document, array $metadata = []): array
	{
		$result = ['module' => (string)($document['module'] ?? ''), 'controller' => (string)($document['controller'] ?? ''),
			'id' => (int)($document['id'] ?? 0), 'clientid' => (int)($document['clientid'] ?? 0),
			'document_number' => (string)($document['document_number'] ?? ''), 'filename' => (string)($document['filename'] ?? ''),
			'status' => 'blocked', 'action' => 'none', 'reason' => '', 'source' => null, 'files' => [], 'metadata' => []];
		if ((int)($document['contact_match_count'] ?? 1) > 1) {
			$result['reason'] = 'contact_mapping_ambiguous';
			return $result;
		}
		try {
			$paths = $this->paths($document);
		} catch (Throwable $exception) {
			$result['reason'] = 'invalid_document_context';
			return $result;
		}
		foreach ($paths as $name => $path) $result['files'][$name] = $this->inspect($path);
		$canonical = $result['files']['canonical'];
		$sources = [];
		foreach (['current', 'legacy'] as $name) {
			$file = $result['files'][$name];
			if ($file['status'] === 'ok') $sources[$name] = $file;
			elseif ($file['status'] !== 'missing') {
				$result['reason'] = 'invalid_historical_source';
				return $result;
			}
		}
		$sourceHashes = array_values(array_unique(array_column($sources, 'sha256')));
		if (count($sourceHashes) > 1) {
			$result['reason'] = 'historical_versions_differ';
			return $result;
		}
		if ($canonical['status'] !== 'ok' && $canonical['status'] !== 'missing') {
			$result['reason'] = 'canonical_collision_or_invalid';
			return $result;
		}
		if ($canonical['status'] === 'ok' && $sourceHashes && !hash_equals($canonical['sha256'], $sourceHashes[0])) {
			$result['reason'] = 'canonical_version_differs';
			return $result;
		}

		$active = array_values(array_filter($metadata, static fn(array $row): bool => empty($row['deleted'])));
		$foreign = count(array_filter($metadata, static fn(array $row): bool => (int)($row['clientid'] ?? 0) !== (int)$document['clientid']));
		$result['metadata'] = ['active' => count($active), 'deleted' => count($metadata) - count($active), 'foreign' => $foreign];
		$canonicalDirectory = dirname($paths['canonical']);
		$firstSource = $sources ? reset($sources) : null;
		$expectedSize = $canonical['status'] === 'ok' ? $canonical['size'] : ($firstSource['size'] ?? null);
		foreach ($active as $row) {
			if ((int)($row['clientid'] ?? 0) !== (int)$document['clientid'] || (string)($row['module'] ?? '') !== $document['module']
				|| (string)($row['controller'] ?? '') !== $document['controller'] || (int)($row['documentid'] ?? 0) !== (int)$document['id']
				|| (string)($row['filename'] ?? '') !== $document['filename'] || rtrim((string)($row['location'] ?? ''), '/') !== $canonicalDirectory
				|| ($expectedSize !== null && (int)($row['filesize'] ?? -1) !== (int)$expectedSize)
				|| (!empty($row['filetype']) && (string)$row['filetype'] !== 'application/pdf')) {
				$result['reason'] = 'metadata_differs';
				return $result;
			}
		}
		if (count($active) > 1) {
			$result['reason'] = 'metadata_ambiguous';
			return $result;
		}
		if ($canonical['status'] === 'missing' && !$sources) {
			$result['reason'] = 'original_missing';
			return $result;
		}
		$result['source'] = $canonical['status'] === 'ok' ? 'canonical' : array_key_first($sources);
		$result['status'] = 'ready';
		if ($canonical['status'] === 'missing') $result['action'] = 'copy_and_register';
		elseif (!$active) $result['action'] = 'register_metadata';
		else {
			$result['status'] = 'complete';
			$result['action'] = 'none';
		}
		return $result;
	}

	public function copy(array $plan): array
	{
		if (($plan['status'] ?? '') !== 'ready' || ($plan['action'] ?? '') !== 'copy_and_register') {
			throw new InvalidArgumentException('Archive plan is not copyable');
		}
		$source = $plan['files'][$plan['source']]['path'];
		$target = $plan['files']['canonical']['path'];
		$before = $this->inspect($source);
		if ($before['status'] !== 'ok' || !hash_equals((string)$plan['files'][$plan['source']]['sha256'], (string)$before['sha256'])) {
			throw new RuntimeException('Archive source changed');
		}
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
			fclose($output);
			if ($input) fclose($input);
			if (is_file($target) && !is_link($target)) unlink($target);
			throw $exception;
		}
		fclose($output);
		if ($input) fclose($input);
		if (!chmod($target, 0666 & ~umask())) {
			unlink($target);
			throw new RuntimeException('Archive permissions could not be set');
		}
		$after = $this->inspect($target);
		if ($after['status'] !== 'ok' || $after['size'] !== $before['size'] || !hash_equals($before['sha256'], $after['sha256'])) {
			if (is_file($target) && !is_link($target)) unlink($target);
			throw new RuntimeException('Archive integrity verification failed');
		}
		return $after;
	}

	private function shard(int $id): string
	{
		$value = (string)$id;
		return $value[0] . '/' . ($value[1] ?? '0') . '/' . $value;
	}

	private function assertInside(string $path): void
	{
		if (!str_starts_with($path, $this->base . '/') || str_contains($path, "\0") || str_contains($path, '\\')) {
			throw new InvalidArgumentException('Archive path escapes base');
		}
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
			if (file_exists($current)) {
				if (!is_dir($current)) throw new RuntimeException('Archive directory collision');
				continue;
			}
			if (!mkdir($current, 0775)) throw new RuntimeException('Archive directory is not writable');
		}
	}
}
