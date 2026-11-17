<?php

class DEEC_AddressBatch extends DEEC_Address
{
	private Contacts_Model_GeocodingBatch $model;
	private array $policy;
	private ?array $activeApprovals = null;
	private const STATES = ['pending', 'retry', 'updated', 'skipped_valid', 'changed', 'manual_review'];
	private const COUNTS = ['selected', 'unreviewable', 'already_valid', 'not_approved', 'approval_changed', 'approved'];

	public function configureBatch(Contacts_Model_GeocodingBatch $model, array $policy): void
	{
		$this->validateGeocodingOptions();
		$this->model = $model;
		$this->policy = $policy + ['enabled' => false, 'daily_limit' => 500, 'max_contacts' => 28506, 'usage_approval_reference' => '', 'privacy_approval_reference' => '', 'approval_valid_until' => ''];
		foreach (['daily_limit' => 500, 'max_contacts' => 28506] as $field => $maximum) {
			$value = filter_var($this->policy[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $maximum]]);
			if ($value === false) throw new InvalidArgumentException('Invalid batch limit');
			$this->policy[$field] = $value;
		}
		$this->authorize(false);
	}

	private function authorize(bool $write): void
	{
		$this->model->authorize();
		if ($write && !(new DEEC_Permission(Zend_Registry::get('User')))->hasPermission('contacts', 'contact', 'edit')) throw new RuntimeException('Batch edit permission denied', 403);
	}

	public function dryRun(?string $approvals = null): array
	{
		$this->authorize(false);
		$manifest = $approvals === null ? ['addresses' => []] : $this->manifest($approvals);
		[, $counts] = $this->inventory($manifest['addresses']);
		return ['status' => 'dry_run', 'clientid' => 100, 'counts' => $counts, 'activation_blockers' => $this->activationBlockers(), 'external_requests' => 0, 'database_writes' => 0];
	}

	public function exportReview(string $output): array
	{
		$this->authorize(false);
		$this->privateFile($output, false);
		if (file_exists($output)) throw new RuntimeException('Review output already exists');
		[$entries, $counts] = $this->inventory([], true);
		$data = ['version' => 1, 'clientid' => 100, 'endpoint' => $this->geocoding['endpoint'], 'reviewed_by' => (int)Zend_Registry::get('User')['id'], 'addresses' => $entries];
		$handle = fopen($output, 'x');
		if (!$handle) throw new RuntimeException('Cannot create review file');
		try {
			$encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
			if (!chmod($output, 0600) || fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) throw new RuntimeException('Cannot write review file');
		} finally { fclose($handle); }
		return ['status' => 'review_exported', 'counts' => $counts, 'approved' => 0, 'external_requests' => 0, 'database_writes' => 0];
	}

	public function prepare(string $approvals): array
	{
		$this->authorize(true);
		$manifest = $this->manifest($approvals);
		if ($manifest['reviewed_by'] !== (int)Zend_Registry::get('User')['id']) throw new RuntimeException('Preparing user must attest the address review');
		return $this->locked(function() use ($approvals, $manifest): array {
			if (file_exists($this->geocoding['state_dir'].'/batch.json')) throw new RuntimeException('A batch already exists; resume it or archive a completed batch explicitly');
			[$jobs, $counts] = $this->inventory($manifest['addresses']);
			if (!$jobs) return ['status' => 'no_approved_candidates', 'counts' => $counts, 'external_requests' => 0, 'database_writes' => 0];
			$state = ['version' => 1, 'clientid' => 100, 'run_id' => bin2hex(random_bytes(16)), 'endpoint' => $this->geocoding['endpoint'], 'manifest_path' => $approvals, 'manifest_digest' => $manifest['digest'], 'prepared_by' => $manifest['reviewed_by'], 'created_at' => $this->geocodingTime(), 'updated_at' => $this->geocodingTime(), 'cursor' => 0, 'counts' => $counts, 'jobs' => $jobs];
			$this->writeGeocodingState($state, 'batch.json');
			return $this->summary($state) + ['external_requests' => 0, 'database_writes' => 0];
		});
	}

	public function status(): array
	{
		$this->authorize(false);
		$state = $this->batchState();
		$blockers = $this->activationBlockers();
		if ($state !== null) {
			if ($state['endpoint'] !== $this->geocoding['endpoint']) $blockers[] = 'endpoint_changed';
			try { if (!hash_equals($state['manifest_digest'], $this->manifest($state['manifest_path'])['digest'])) $blockers[] = 'approvals_changed'; }
			catch (Throwable $error) { $blockers[] = 'approvals_unavailable'; }
		}
		return ($state === null ? ['status' => 'not_prepared', 'clientid' => 100] : $this->summary($state)) + ['activation_blockers' => $blockers, 'external_requests' => 0, 'database_writes' => 0];
	}

	public function run(string $runId): array
	{
		$this->authorize(true);
		$blockers = $this->activationBlockers();
		if ($blockers) return ['status' => 'blocked', 'activation_blockers' => $blockers, 'external_requests' => 0, 'database_writes' => 0];
		return $this->locked(function() use ($runId): array {
			$state = $this->batchState();
			if ($state === null || !hash_equals($state['run_id'], $runId)) throw new RuntimeException('Explicit prepared run ID required');
			$manifest = $this->manifest($state['manifest_path']);
			if (!hash_equals($state['manifest_digest'], $manifest['digest']) || $state['endpoint'] !== $this->geocoding['endpoint']) throw new RuntimeException('Prepared approvals or endpoint changed');
			$requests = $writes = 0; $stopped = null;
			for ($local = 0; $local < 100; $local++) {
				$index = $state['cursor'] < count($state['jobs']) ? $state['cursor'] : null;
				if ($index === null) foreach ($state['jobs'] as $i => $job) if ($job['status'] === 'retry' && $job['retry_at'] <= $this->geocodingTime()) { $index = $i; break; }
				if ($index === null) break;
				$job = $state['jobs'][$index];
				$approval = $manifest['addresses'][$job['id'].':'.$job['address_id']] ?? [];
				if (!$this->approved($approval) || !hash_equals($job['fingerprint'], $approval['fingerprint'])) throw new RuntimeException('Explicit address approval missing');
				$current = $this->model->current($job['id']);
				if (!$current || (int)$current['address_id'] !== $job['address_id'] || $current['created'] !== $job['created']) $outcome = ['status' => 'changed'];
				elseif ($this->hasCoordinates($current)) $outcome = ['status' => 'skipped_valid'];
				else {
					$this->activeApprovals = [$state['manifest_path'], $state['manifest_digest']];
					try { $this->authorizeGeocodingRequest(); $outcome = $this->geocodeBatchAddress($job['address_id'], $job['fingerprint'], ['id' => $job['id'], 'created' => $job['created']], $this->policy['daily_limit']); }
					finally { $this->activeApprovals = null; }
				}
				if ($outcome['status'] === 'busy' || $outcome['status'] === 'daily_limit' || (in_array($outcome['status'], ['backoff', 'manual_review_required'], true) && ($outcome['scope'] ?? '') === 'provider')) { $stopped = $outcome; break; }
				if ($outcome['status'] === 'updated') { $job['status'] = 'updated'; $job['cached'] = $outcome['cached']; $writes++; }
				elseif (in_array($outcome['status'], ['retry', 'no_match', 'backoff'], true)) { $job['status'] = 'retry'; $job['retry_at'] = $this->geocodingTime() + max(1, $outcome['retry_after']); }
				elseif (in_array($outcome['status'], ['existing_coordinates', 'unchanged', 'skipped_valid'], true)) $job['status'] = 'skipped_valid';
				elseif (in_array($outcome['status'], ['review', 'blocked', 'manual_review_required', 'incomplete_or_unreviewable'], true)) $job['status'] = 'manual_review';
				else $job['status'] = 'changed';
				$job['last_status'] = $outcome['status'];
				$job['updated_at'] = $this->geocodingTime();
				$state['jobs'][$index] = $job;
				if ($index === $state['cursor']) $state['cursor']++;
				$state['updated_at'] = $this->geocodingTime();
				$this->writeGeocodingState($state, 'batch.json');
				if (!empty($outcome['external_requested'])) { $requests++; break; }
			}
			return $this->summary($state) + ['stopped' => $stopped, 'external_requests' => $requests, 'database_writes' => $writes];
		});
	}

	protected function authorizeGeocodingRequest(): void
	{
		if ($this->activeApprovals === null) return;
		if ($this->activationBlockers()) throw new RuntimeException('Batch approval expired before request');
		[$path, $digest] = $this->activeApprovals;
		$this->privateFile($path, true);
		if (!hash_equals($digest, hash_file('sha256', $path))) throw new RuntimeException('Address approvals revoked before request');
	}

	private function inventory(array $approvals, bool $template = false): array
	{
		$counts = array_fill_keys(self::COUNTS, 0);
		$entries = [];
		foreach ($this->model->candidates($this->policy['max_contacts']) as $row) {
			$counts['selected']++;
			// Inventory has no use for a full cache reload for each missing point.
			$review = $this->review((int)$row['address_id'], $this->getAddress((int)$row['address_id']), []);
			if (!isset($review['fingerprint'])) { $counts['unreviewable']++; continue; }
			if (in_array($review['status'], ['existing_coordinates', 'unchanged', 'postal_address_changed'], true)) { $counts['already_valid']++; continue; }
			$entry = ['id' => (int)$row['id'], 'address_id' => (int)$row['address_id'], 'fingerprint' => $review['fingerprint']];
			if ($template) { $entries[] = $entry + ['public' => false, 'non_personal' => false, 'non_confidential' => false, 'review_reference' => '']; continue; }
			$approval = $approvals[$entry['id'].':'.$entry['address_id']] ?? [];
			if (!$this->approved($approval)) { $counts['not_approved']++; continue; }
			if (!hash_equals($entry['fingerprint'], $approval['fingerprint'])) { $counts['approval_changed']++; continue; }
			$entries[] = $entry + ['created' => $row['created'], 'status' => 'pending', 'retry_at' => 0];
			$counts['approved']++;
		}
		return [$entries, $counts];
	}

	private function approved(array $entry): bool
	{
		return ($entry['public'] ?? false) === true && ($entry['non_personal'] ?? false) === true && ($entry['non_confidential'] ?? false) === true && $this->reference($entry['review_reference'] ?? '');
	}

	private function manifest(string $path): array
	{
		$this->privateFile($path, true);
		$bytes = file_get_contents($path);
		$data = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($data) || ($data['version'] ?? 0) !== 1 || ($data['clientid'] ?? 0) !== 100 || ($data['endpoint'] ?? '') !== $this->geocoding['endpoint'] || !is_int($data['reviewed_by'] ?? null) || $data['reviewed_by'] < 1 || !is_array($data['addresses'] ?? null) || count($data['addresses']) > 28506) throw new InvalidArgumentException('Invalid address approvals');
		$entries = [];
		foreach ($data['addresses'] as $entry) {
			if (!is_array($entry) || !is_int($entry['id'] ?? null) || $entry['id'] < 1 || !is_int($entry['address_id'] ?? null) || $entry['address_id'] < 1 || !preg_match('/^[a-f0-9]{64}$/D', $entry['fingerprint'] ?? '') || !is_string($entry['review_reference'] ?? null)) throw new InvalidArgumentException('Invalid address approval entry');
			foreach (['public', 'non_personal', 'non_confidential'] as $flag) if (!is_bool($entry[$flag] ?? null)) throw new InvalidArgumentException('Explicit boolean address classification required');
			$key = $entry['id'].':'.$entry['address_id'];
			if (isset($entries[$key])) throw new InvalidArgumentException('Duplicate address approval');
			$entries[$key] = $entry;
		}
		return ['addresses' => $entries, 'reviewed_by' => $data['reviewed_by'], 'digest' => hash('sha256', $bytes)];
	}

	private function activationBlockers(): array
	{
		$reasons = [];
		if (filter_var($this->policy['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== true) $reasons[] = 'batch_disabled';
		foreach (['usage_approval_reference', 'privacy_approval_reference'] as $field) if (!$this->reference($this->policy[$field])) $reasons[] = $field;
		$date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $this->policy['approval_valid_until'], new DateTimeZone('UTC'));
		if (!$date || $date->format('Y-m-d\TH:i:s\Z') !== $this->policy['approval_valid_until'] || $date->getTimestamp() <= $this->geocodingTime()) $reasons[] = 'approval_expired_or_missing';
		return $reasons;
	}

	private function reference(string $value): bool { return (bool)preg_match('~^[A-Za-z0-9][A-Za-z0-9._:/-]{0,119}$~D', $value); }

	private function privateFile(string $path, bool $existing): void
	{
		$parent = realpath(dirname($path)); $base = realpath($this->basePath);
		if ($path === '' || $path[0] !== '/' || !$parent || (fileperms($parent) & 0077) || is_link($path) || ($base && ($parent === $base || str_starts_with($parent, $base.'/'))) || ($existing && (!is_file($path) || (fileperms($path) & 0077) || filesize($path) > 20 * 1024 * 1024))) throw new InvalidArgumentException('Private approval/output path required');
	}

	private function locked(callable $operation): array
	{
		$directory = $this->geocoding['state_dir'];
		if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new RuntimeException('Cannot create batch state directory');
		$this->validateGeocodingOptions();
		$lock = fopen($directory.'/batch.lock', 'c');
		if (!$lock) throw new RuntimeException('Cannot open batch lock');
		try { if (!flock($lock, LOCK_EX | LOCK_NB)) return ['status' => 'busy', 'external_requests' => 0, 'database_writes' => 0]; return $operation(); }
		finally { fclose($lock); }
	}

	private function batchState(): ?array
	{
		$file = $this->geocoding['state_dir'].'/batch.json';
		if (!file_exists($file)) return null;
		$this->privateFile($file, true);
		$state = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($state) || ($state['version'] ?? 0) !== 1 || ($state['clientid'] ?? 0) !== 100 || !preg_match('/^[a-f0-9]{32}$/D', $state['run_id'] ?? '') || !is_array($state['jobs'] ?? null) || count($state['jobs']) > 28506 || !is_int($state['cursor'] ?? null) || $state['cursor'] < 0 || $state['cursor'] > count($state['jobs']) || !is_string($state['manifest_path'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $state['manifest_digest'] ?? '')) throw new RuntimeException('Invalid batch state');
		if (!is_string($state['endpoint'] ?? null) || !is_int($state['prepared_by'] ?? null) || $state['prepared_by'] < 1 || !is_array($state['counts'] ?? null) || array_keys($state['counts']) !== self::COUNTS) throw new RuntimeException('Invalid batch metadata');
		foreach ($state['counts'] as $count) if (!is_int($count) || $count < 0) throw new RuntimeException('Invalid batch counts');
		if ($state['counts']['approved'] !== count($state['jobs'])) throw new RuntimeException('Invalid batch job count');
		$previous = null;
		foreach ($state['jobs'] as $index => $job) {
			if (!is_array($job) || !is_int($job['id'] ?? null) || $job['id'] < 1 || !is_int($job['address_id'] ?? null) || $job['address_id'] < 1 || !array_key_exists('created', $job) || ($job['created'] !== null && !preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $job['created'])) || !preg_match('/^[a-f0-9]{64}$/D', $job['fingerprint'] ?? '') || !in_array($job['status'] ?? '', self::STATES, true) || !is_int($job['retry_at'] ?? null) || $job['retry_at'] < 0) throw new RuntimeException('Invalid batch job');
			if ($previous && (($job['created'] ?? '') > ($previous['created'] ?? '') || ($job['created'] === $previous['created'] && $job['id'] >= $previous['id']))) throw new RuntimeException('Invalid batch ordering');
			if (($index < $state['cursor']) === ($job['status'] === 'pending')) throw new RuntimeException('Invalid batch cursor');
			$previous = $job;
		}
		return $state;
	}

	private function summary(array $state): array
	{
		$counts = array_fill_keys(self::STATES, 0); $cached = 0; $next = null;
		foreach ($state['jobs'] as $job) { $counts[$job['status']]++; if (!empty($job['cached'])) $cached++; if ($job['status'] === 'retry') $next = $next === null ? $job['retry_at'] : min($next, $job['retry_at']); }
		return ['status' => $counts['pending'] ? 'ready' : ($counts['retry'] ? 'waiting' : 'complete'), 'clientid' => 100, 'run_id' => $state['run_id'], 'cursor' => $state['cursor'], 'total' => count($state['jobs']), 'counts' => $counts, 'cached' => $cached, 'next_retry_at' => $next, 'selection' => $state['counts']];
	}

	private function hasCoordinates(array $row): bool
	{
		return is_numeric($row['latitude']) && is_numeric($row['longitude']) && abs((float)$row['latitude']) <= 90 && abs((float)$row['longitude']) <= 180;
	}
}
