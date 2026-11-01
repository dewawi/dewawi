<?php

class DEEC_Site_SubmissionLimiter
{
	private string $directory;
	private int $maxIdentities;

	public function __construct(?string $directory = null, int $maxIdentities = 5000)
	{
		$this->directory = $directory ?? BASE_PATH . '/cache/submission-rate';
		$this->maxIdentities = max(100, $maxIdentities);
	}

	public function consume(int $clientId, int $siteId, string $operation, string $remoteAddress, int $limit, int $window, ?int $now = null): array
	{
		if ($clientId <= 0 || $siteId <= 0 || !preg_match('/^[a-z0-9_-]+$/D', $operation)) {
			throw new InvalidArgumentException('Invalid submission rate scope');
		}
		if ($limit <= 0 || $window <= 0) throw new InvalidArgumentException('Invalid submission rate limit');

		$now = $now ?? time();
		$scope = $clientId . ':' . $siteId . ':' . $operation;
		$identity = filter_var($remoteAddress, FILTER_VALIDATE_IP) ? $remoteAddress : 'unknown';
		$scopeHash = hash('sha256', $scope);

		if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
			throw new RuntimeException('Could not create submission rate directory');
		}

		$handle = fopen($this->directory . '/' . $scopeHash . '.json', 'c+');
		if (!$handle) throw new RuntimeException('Could not open submission rate storage');

		try {
			if (!flock($handle, LOCK_EX)) throw new RuntimeException('Could not lock submission rate storage');
			$contents = stream_get_contents($handle);
			$state = $contents !== false && $contents !== '' ? json_decode($contents, true) : [];
			$key = is_string($state['key'] ?? null) && preg_match('/^[a-f0-9]{64}$/D', $state['key']) ? $state['key'] : '';
			$secret = $key !== '' ? hex2bin($key) : false;
			if (!is_string($secret) || strlen($secret) !== 32) $secret = random_bytes(32);
			$identityHash = hash_hmac('sha256', $identity, $secret);
			$entries = is_array($state['entries'] ?? null) ? $state['entries'] : [];
			$cutoff = $now - $window;

			foreach ($entries as $entryHash => $entry) {
				$timestamps = array_values(array_filter(
					is_array($entry['timestamps'] ?? null) ? $entry['timestamps'] : [],
					static fn($timestamp) => is_int($timestamp) && $timestamp > $cutoff && $timestamp <= $now
				));
				if (!$timestamps) {
					unset($entries[$entryHash]);
					continue;
				}
				$entries[$entryHash] = ['timestamps' => $timestamps, 'last' => max($timestamps)];
			}

			if (!isset($entries[$identityHash]) && count($entries) >= $this->maxIdentities) {
				uasort($entries, static fn($left, $right) => ($left['last'] ?? 0) <=> ($right['last'] ?? 0));
				while (count($entries) >= $this->maxIdentities) array_shift($entries);
			}

			$timestamps = $entries[$identityHash]['timestamps'] ?? [];
			$allowed = count($timestamps) < $limit;
			if ($allowed) $timestamps[] = $now;
			$retryAfter = $allowed ? 0 : max(1, (int)$timestamps[0] + $window - $now);
			$entries[$identityHash] = ['timestamps' => $timestamps, 'last' => $now];

			$encoded = json_encode(['version' => 1, 'key' => bin2hex($secret), 'entries' => $entries], JSON_UNESCAPED_SLASHES);
			if ($encoded === false || !rewind($handle) || !ftruncate($handle, 0)
				|| fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) {
				throw new RuntimeException('Could not write submission rate storage');
			}

			flock($handle, LOCK_UN);

			return [
				'allowed' => $allowed,
				'retryAfter' => $retryAfter,
				'remaining' => max(0, $limit - count($timestamps)),
			];
		} finally {
			fclose($handle);
		}
	}
}
