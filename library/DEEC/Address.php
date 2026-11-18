<?php

class DEEC_Address {

	// Public Nominatim is restricted to explicitly reviewed single addresses.
	private const GEOCODING_CLIENT_ID = 100;
	private const FORMATTED_HOUSE_NUMBER = '\d+(?: ?\p{L})?(?: ?[-\/–] ?\d+(?: ?\p{L})?)?';
	protected array $geocoding = [];
	private ?array $batchContext = null;
	private bool $geocodingRequested = false;

	protected $basePath;

	protected $connection;

	public function __construct($basePath, $host, $username, $password, $dbname, array $geocoding = [], int $port = 0, ?string $socket = null) {
		$this->basePath = $basePath;
		$this->connection = mysqli_connect($host, $username, $password, $dbname, $port, $socket);
		$this->connection->set_charset('utf8mb4');
		$this->geocoding = $geocoding;
	}

	public function getAddress($id) {
		$id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if ($id === false) return false;
		$query = "SELECT a.* FROM address a JOIN contact c ON c.id = a.parentid AND c.clientid = a.clientid AND c.deleted = 0 WHERE a.id = ? AND a.clientid = ? AND a.deleted = 0 AND a.module = 'contacts' AND a.controller = 'contact' AND a.type = 'billing'";
		if ($this->batchContext !== null) $query .= " AND c.id = ? AND BINARY c.created <=> BINARY ? AND a.id = (SELECT billing.id FROM address billing WHERE billing.parentid = c.id AND billing.clientid = c.clientid AND billing.module = 'contacts' AND billing.controller = 'contact' AND billing.type = 'billing' AND billing.deleted = 0 ORDER BY billing.ordering, billing.id LIMIT 1)";
		$statement = $this->connection->prepare($query);
		$client = self::GEOCODING_CLIENT_ID;
		try {
			$values = [$id, $client];
			if ($this->batchContext !== null) $values = array_merge($values, [$this->batchContext['id'], $this->batchContext['created']]);
			$statement->bind_param($this->batchContext === null ? 'ii' : 'iiis', ...$values);
			$statement->execute();
			return $statement->get_result()->fetch_assoc() ?: false;
		} finally { $statement->close(); }
	}

	public function getLatestAddress() {
		$where = "deleted = 0 AND street IS NOT NULL AND TRIM(street) <> ''";
		$query = '
				SELECT
					* FROM address
				WHERE
					'.$where.' AND clientid = '.self::GEOCODING_CLIENT_ID.'
					AND (geoupdated IS NULL OR geoupdated < COALESCE(modified, created))
				ORDER
					BY geoupdated ASC, modified DESC, created DESC
				LIMIT 1';
		//echo $query;
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
			return mysqli_fetch_assoc($result);
		} else {
			return false;
		}
	}

	public function geocodeLatestAddress(): bool {
		// There is no persisted classification proving that cron candidates are public.
		return false;
	}

	public function inspectGeocoding(int $id): array {
		$this->validateGeocodingOptions();
		return $this->review($id, $this->getAddress($id), $this->readGeocodingState());
	}

	public function geocodeAddress(int $id, string $publicAddressFingerprint, bool $confirmedPostalChange = false): array {
		$this->geocodingRequested = false;
		$review = $this->inspectGeocoding($id);
		if (in_array($review['status'], ['unchanged', 'existing_coordinates'], true) && !($review['status'] === 'existing_coordinates' && $confirmedPostalChange)) return $review;
		if (!isset($review['fingerprint']) || !hash_equals($review['fingerprint'], $publicAddressFingerprint)) return ['status' => 'public_review_required'];

		$directory = $this->geocoding['state_dir'];
		if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new RuntimeException('Cannot create geocoding state directory');
		$this->validateGeocodingOptions();
		$lock = fopen($directory.'/manual.lock', 'c');
		if (!$lock) throw new RuntimeException('Cannot open geocoding lock');
		try {
			if (!flock($lock, LOCK_EX | LOCK_NB)) return ['status' => 'busy'];
			$state = $this->readGeocodingState();
			$address = $this->getAddress($id);
			$review = $this->review($id, $address, $state);
			if (in_array($review['status'], ['unchanged', 'existing_coordinates'], true) && !($review['status'] === 'existing_coordinates' && $confirmedPostalChange)) return $review;
			if (!isset($review['fingerprint']) || !hash_equals($review['fingerprint'], $publicAddressFingerprint)) return ['status' => 'address_changed'];
			$postal = $this->postalAddress($address);
			$key = $postal['key'];
			$entry = $state['entries'][$key] ?? [];
			$now = $this->geocodingTime();
			$cached = ($entry['status'] ?? '') === 'success';
			if (!$cached) {
				if (($state['blocked_endpoint'] ?? '') === $this->geocoding['endpoint']) return ['status' => 'manual_review_required'] + ($this->batchContext === null ? [] : ['scope' => 'provider']);
				if (in_array($entry['status'] ?? '', ['review', 'blocked'], true)) return ['status' => 'manual_review_required'];
				$providerNext = (int)$state['next_request_at'];
				if ($this->batchContext !== null) $providerNext = max($providerNext, ($state['last_request_at'] ?? max(0, $providerNext - 15)) + 60);
				$next = max((int)($entry['next_attempt_at'] ?? 0), $providerNext);
				if ($next > $now) return ['status' => 'backoff', 'retry_after' => $next - $now] + ($this->batchContext === null ? [] : ['scope' => $providerNext >= ($entry['next_attempt_at'] ?? 0) ? 'provider' : 'address']);
				$day = gmdate('Y-m-d', $now);
				if (($state['day'] ?? '') !== $day) { $state['day'] = $day; $state['requests_today'] = 0; $state['manual_requests_today'] = 0; }
				$state['manual_requests_today'] = $state['manual_requests_today'] ?? $state['requests_today'];
				if ($this->batchContext !== null) { $state['batch_day'] = $day; $state['batch_daily_limit'] = $this->batchContext['daily_limit']; }
				$batchDay = ($state['batch_day'] ?? '') === $day;
				if ($state['requests_today'] >= ($batchDay ? $state['batch_daily_limit'] : 50) || ($this->batchContext === null && $state['manual_requests_today'] >= 50)) return ['status' => 'daily_limit'];
				$attempts = (int)($entry['attempts'] ?? 0) + 1;
				$this->authorizeGeocodingRequest();
				// Persist quota and a crash backoff before any network I/O.
				$state['next_request_at'] = $now + ($batchDay ? 60 : 15);
				$state['last_request_at'] = $now;
				$state['requests_today']++;
				if ($this->batchContext === null) $state['manual_requests_today']++;
				$state['entries'][$key] = ['status' => 'retry', 'attempts' => $attempts, 'next_attempt_at' => $now + 3600];
				$this->writeGeocodingState($state);
				$query = http_build_query(['street' => $postal['fields']['street'], 'postalcode' => $postal['fields']['postcode'], 'city' => $postal['fields']['city'], 'countrycodes' => $postal['fields']['country'], 'format' => 'jsonv2', 'addressdetails' => 1, 'limit' => 3, 'layer' => 'address'], '', '&', PHP_QUERY_RFC3986);
				$this->geocodingRequested = true;
				try { [$body, $headers] = $this->requestGeocode($query); }
				catch (Throwable $error) { $body = false; $headers = []; }
				preg_match('~^HTTP/\S+ (\d{3})(?:\s|$)~', $headers[0] ?? '', $status);
				$http = (int)($status[1] ?? 0);
				$point = $http === 200 && is_string($body) ? $this->geocodingPoint($body, $postal['fields']) : null;
				if (is_array($point)) {
					$entry = ['status' => 'success', 'point' => $point];
				} else {
					$delay = min(86400, 3600 * (2 ** min($attempts - 1, 5)));
					$retryAfter = $this->retryAfter($headers, $now);
					$entry = ['status' => $point === false ? 'review' : 'retry', 'attempts' => $attempts, 'next_attempt_at' => max($now + $delay, $retryAfter)];
					if ($http === 200 && is_string($body) && preg_match('/^\s*\[\s*\]\s*$/D', $body)) $entry = ['status' => 'no_match', 'attempts' => $attempts, 'next_attempt_at' => $now + 7 * 86400];
					if ($http === 429 || $http === 503) $state['next_request_at'] = $entry['next_attempt_at'];
					if ($http >= 400 && $http < 500 && $http !== 429) $entry['status'] = 'blocked';
					if ($http === 403) $state['blocked_endpoint'] = $this->geocoding['endpoint'];
					if ($attempts >= 5 && $entry['status'] === 'retry') $entry['status'] = 'review';
				}
				$state['entries'][$key] = $entry;
				$this->writeGeocodingState($state);
				if ($entry['status'] !== 'success') return ['status' => $entry['status'], 'retry_after' => max(0, $entry['next_attempt_at'] - $now)];
			}
			$point = $entry['point'];
			if (!$this->validCoordinates($point)) throw new RuntimeException('Invalid cached geocoding point');
			$data = ['latitude' => $point['latitude'], 'longitude' => $point['longitude'], 'geoupdated' => date('Y-m-d H:i:s', $now)];
			if (!$this->updateAddress($id, $data, $address)) return ['status' => 'address_changed'];
			$state['addresses'][(string)$id] = ['key' => $key, 'point' => $point];
			$this->writeGeocodingState($state);
			return ['status' => 'updated', 'cached' => $cached, 'attribution' => '© OpenStreetMap contributors, ODbL 1.0', 'license_url' => 'https://www.openstreetmap.org/copyright'];
		} finally { fclose($lock); }
	}

	protected function geocodingTime(): int { return time(); }
	protected function authorizeGeocodingRequest(): void {}

	protected function geocodeBatchAddress(int $id, string $fingerprint, array $selection, int $dailyLimit): array {
		if ($dailyLimit < 1 || $dailyLimit > 500 || empty($selection['id']) || !array_key_exists('created', $selection)) throw new InvalidArgumentException('Invalid batch scope');
		$this->batchContext = ['id' => (int)$selection['id'], 'created' => $selection['created'], 'daily_limit' => $dailyLimit];
		try { return $this->geocodeAddress($id, $fingerprint) + ['external_requested' => $this->geocodingRequested]; }
		finally { $this->batchContext = null; }
	}

	protected function review(int $id, $address, array $state, bool $explain = false): array {
		if (!$address) return ['status' => 'ineligible'] + ($explain ? ['reasons' => ['ineligible_address']] : []);
		$postal = $this->postalAddress($address, $issues, $formats);
		if ($postal === null) return ['status' => 'incomplete_or_unreviewable'] + ($explain ? ['reasons' => $issues, 'format_details' => $formats] : []);
		$key = $postal['key'];
		$last = $state['addresses'][(string)$id] ?? [];
		$status = 'needs_geocoding';
		if ($this->validCoordinates($address)) {
			$status = 'existing_coordinates';
			// Legacy keys preserve tracked points; requests use only the canonical key.
			if ($this->batchContext === null && isset($last['point']) && $this->samePoint($last['point'], $address)) $status = in_array($last['key'] ?? '', [$key, $postal['legacy_key']], true) ? 'unchanged' : 'postal_address_changed';
		}
		$snapshot = array_intersect_key($address, array_flip(['street', 'postcode', 'city', 'country', 'parentid', 'type', 'modified', 'geoupdated', 'latitude', 'longitude']));
		return ['status' => $status, 'fingerprint' => hash('sha256', $id.':'.$key.':'.json_encode($snapshot))] + ($explain ? ['format_details' => $formats, 'newly_reviewable' => $postal['newly_reviewable']] : []);
	}

	private function postalAddress(array $address, ?array &$issues = null, ?array &$formats = null): ?array {
		$fields = []; $issues = []; $formats = []; $legacyControl = false;
		foreach (['street', 'postcode', 'city', 'country'] as $field) {
			$value = (string)($address[$field] ?? '');
			if (!mb_check_encoding($value, 'UTF-8')) { $issues[] = 'invalid_utf8_'.$field; continue; }
			foreach (["\r" => 'carriage_return', "\n" => 'line_feed', "\t" => 'tab'] as $character => $kind) if (str_contains($value, $character)) $formats[] = $kind.'_'.$field;
			if (preg_match('/(?! )\p{Z}/u', $value)) $formats[] = 'unicode_whitespace_'.$field;
			if (preg_match('/\p{Cc}/u', str_replace(["\r", "\n", "\t"], '', $value))) { $issues[] = 'control_characters_'.$field; $formats[] = 'problematic_control_'.$field; continue; }
			if (preg_match('/\p{Cf}/u', $value)) { $issues[] = 'format_characters_'.$field; $formats[] = 'invisible_format_'.$field; continue; }
			$legacyControl = $legacyControl || (bool)preg_match('/[\x00-\x1f\x7f]/u', $value);
			$fields[$field] = $this->normalize($value);
			if ($fields[$field] === '') $issues[] = 'missing_'.$field;
		}
		if (isset($fields['country']) && $fields['country'] !== '') {
			if (!preg_match('/^[a-z]{2}$/D', $fields['country'])) $issues[] = 'invalid_country_code';
			else {
				$country = strtoupper($fields['country']);
				$statement = $this->connection->prepare('SELECT code FROM country WHERE clientid = ? AND BINARY code = ? AND deleted = 0 LIMIT 1');
				$client = self::GEOCODING_CLIENT_ID;
				try {
					$statement->bind_param('is', $client, $country);
					$statement->execute();
					if (!$statement->get_result()->fetch_assoc()) $issues[] = 'country_not_configured';
				} finally { $statement->close(); }
			}
		}
		$legacyFields = $fields; $legacyNumber = true;
		if (isset($fields['street']) && $fields['street'] !== '') {
			$legacyNumber = $this->streetParts($fields['street'], false) !== null;
			$parts = $this->streetParts($fields['street']);
			if ($parts === null) {
				$issues[] = 'unrecognized_house_number';
				$formats[] = preg_match('/\d/u', $fields['street']) ? 'house_number_digits_unrecognized' : 'house_number_no_digits';
			} else {
				if (str_contains($parts[3], ' ')) $formats[] = 'house_number_spacing';
				if (str_contains($parts[3], '–')) $formats[] = 'house_number_range_dash';
				$fields['street'] = $parts[2];
			}
		}
		if ($issues) return null;
		// Identical query fields retain their cache key; changed queries invalidate approvals.
		$key = hash('sha256', json_encode([self::GEOCODING_CLIENT_ID, $this->geocoding['endpoint'], 1, $fields]));
		return ['fields' => $fields, 'key' => $key, 'legacy_key' => $legacyFields === $fields ? $key : hash('sha256', json_encode([self::GEOCODING_CLIENT_ID, $this->geocoding['endpoint'], 1, $legacyFields])), 'newly_reviewable' => $legacyControl || !$legacyNumber];
	}

	private function normalize(string $value): string {
		return mb_strtolower(trim(preg_replace('/[\s\p{Z}]+/u', ' ', Normalizer::normalize($value, Normalizer::FORM_C))), 'UTF-8');
	}

	private function streetParts(string $street, bool $formatted = true): ?array {
		$legacy = '\d+\p{L}?(?:[-\/]\d+\p{L}?)?';
		$suffix = $formatted ? self::FORMATTED_HOUSE_NUMBER : $legacy;
		// A spaced letter before a road name may belong to the road (e.g. "12 A Street").
		$prefix = $formatted ? '\d+\p{L}?(?: ?[-\/–] ?\d+\p{L}?)?' : $legacy;
		if (preg_match('/^(.+?)\s+('.$suffix.')$/uD', $street, $parts)) {
			$number = $this->normalizeHouseNumber($parts[2]);
			return [$parts[1], $number, $parts[1].' '.$number, $parts[2]];
		}
		if (preg_match('/^('.$prefix.')\s+(.+)$/uD', $street, $parts)) {
			$number = $this->normalizeHouseNumber($parts[1]);
			return [$parts[2], $number, $number.' '.$parts[2], $parts[1]];
		}
		return null;
	}

	private function normalizeHouseNumber(string $number): ?string {
		if (preg_match('/[\p{Cc}\p{Cf}]/u', str_replace(["\r", "\n", "\t"], '', $number))) return null;
		$number = $this->normalize($number);
		if (!preg_match('/^'.self::FORMATTED_HOUSE_NUMBER.'$/uD', $number)) return null;
		return str_replace([' ', '–'], ['', '-'], $number);
	}

	protected function geocodingPoint(string $body, array $fields) {
		$results = json_decode($body, true);
		if (!is_array($results) || substr(ltrim($body), 0, 1) !== '[') return null;
		if (!$results) return null;
		[$road, $number] = $this->streetParts($fields['street']);
		$points = [];
		foreach ($results as $result) {
			if (!is_array($result)) return null;
			$point = ['latitude' => $result['lat'] ?? null, 'longitude' => $result['lon'] ?? null];
			if (!$this->validCoordinates($point)) return null;
			$details = $result['address'] ?? null;
			if (!is_array($details) || !is_string($details['country_code'] ?? null) || strtolower($details['country_code']) !== $fields['country'] || filter_var($result['place_rank'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 28, 'max_range' => 30]]) === false) continue;
			if (!is_string($details['road'] ?? null) || $this->normalize($details['road']) !== $road || !is_string($details['house_number'] ?? null) || $this->normalizeHouseNumber($details['house_number']) !== $number) continue;
			if (!is_string($details['postcode'] ?? null) || preg_replace('/\s+/u', '', $this->normalize($details['postcode'])) !== preg_replace('/\s+/u', '', $fields['postcode'])) continue;
			$localities = array_intersect_key($details, array_flip(['city', 'town', 'village', 'municipality', 'hamlet', 'suburb']));
			if (!in_array($fields['city'], array_map(fn($value) => is_string($value) ? $this->normalize($value) : '', $localities), true)) continue;
			$box = $result['boundingbox'] ?? [];
			if (!is_array($box) || count($box) !== 4 || count(array_filter($box, 'is_numeric')) !== 4) continue;
			[$south, $north, $west, $east] = array_map('floatval', array_values($box));
			if ($south < -90 || $north > 90 || $west < -180 || $east > 180 || $south > (float)$point['latitude'] || $north < (float)$point['latitude'] || $west > (float)$point['longitude'] || $east < (float)$point['longitude'] || $north - $south > .1 || $east - $west > .1 || ((float)$point['latitude'] === 0.0 && (float)$point['longitude'] === 0.0)) continue;
			$point = array_map(static fn($value) => number_format((float)$value, 9, '.', ''), $point);
			$points[implode(',', $point)] = $point;
		}
		return count($points) === 1 ? reset($points) : false;
	}

	private function samePoint(array $left, array $right): bool {
		return $this->validCoordinates($left) && $this->validCoordinates($right) && (float)$left['latitude'] === (float)$right['latitude'] && (float)$left['longitude'] === (float)$right['longitude'];
	}

	private function retryAfter(array $headers, int $now): int {
		foreach ($headers as $header) {
			if (!preg_match('/^Retry-After:\s*(.+)$/iD', $header, $match)) continue;
			$value = trim($match[1]);
			return ctype_digit($value) ? $now + min((int)$value, PHP_INT_MAX - $now) : max($now, (int)strtotime($value));
		}
		return $now;
	}

	protected function validateGeocodingOptions(): void {
		$url = parse_url($this->geocoding['endpoint'] ?? '');
		$agent = $this->geocoding['user_agent'] ?? '';
		$directory = $this->geocoding['state_dir'] ?? '';
		if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['query']) || isset($url['fragment']) || isset($url['user']) || isset($url['pass']) || preg_match('/[\x00-\x20\x7f]/', $this->geocoding['endpoint'])) throw new InvalidArgumentException('Invalid geocoding endpoint');
		if (!is_string($agent) || strlen($agent) > 200 || !preg_match('~^DEWAWI/[a-zA-Z0-9. -]+ \(\+https://[^\s()]+\)$~D', $agent) || str_contains($agent, 'example.')) throw new InvalidArgumentException('Configure an identifying user agent with a real contact URL');
		if (!is_string($directory) || $directory === '' || $directory[0] !== '/' || is_link($directory) || (is_dir($directory) && (fileperms($directory) & 0077))) throw new InvalidArgumentException('Geocoding state requires a private absolute directory');
		$base = realpath($this->basePath);
		$resolved = realpath($directory) ?: $directory;
		if ($base && ($resolved === $base || str_starts_with($resolved, $base.'/'))) throw new InvalidArgumentException('Geocoding state must be outside the web root');
	}

	private function readGeocodingState(): array {
		$file = $this->geocoding['state_dir'].'/manual.json';
		if (!file_exists($file)) return ['version' => 1, 'entries' => [], 'addresses' => [], 'next_request_at' => 0, 'requests_today' => 0];
		if (is_link($file) || !is_readable($file)) throw new RuntimeException('Unsafe geocoding state');
		$state = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($state) || ($state['version'] ?? 0) !== 1 || !is_array($state['entries'] ?? null) || !is_array($state['addresses'] ?? null) || !is_int($state['next_request_at'] ?? null) || $state['next_request_at'] < 0 || !is_int($state['requests_today'] ?? null) || $state['requests_today'] < 0) throw new RuntimeException('Invalid geocoding state');
		foreach (['last_request_at', 'manual_requests_today', 'batch_daily_limit'] as $field) if (isset($state[$field]) && (!is_int($state[$field]) || $state[$field] < 0 || ($field === 'batch_daily_limit' && ($state[$field] < 1 || $state[$field] > 500)))) throw new RuntimeException('Invalid shared geocoding quota');
		foreach ($state['entries'] as $key => $entry) {
			if (!preg_match('/^[a-f0-9]{64}$/D', (string)$key) || !is_array($entry) || !in_array($entry['status'] ?? '', ['success', 'retry', 'no_match', 'review', 'blocked'], true)) throw new RuntimeException('Invalid geocoding cache entry');
			if ($entry['status'] === 'success') {
				if (!is_array($entry['point'] ?? null) || !$this->validCoordinates($entry['point'])) throw new RuntimeException('Invalid cached coordinates');
			} elseif (!is_int($entry['attempts'] ?? null) || $entry['attempts'] < 1 || !is_int($entry['next_attempt_at'] ?? null) || $entry['next_attempt_at'] < 0) throw new RuntimeException('Invalid geocoding retry state');
		}
		foreach ($state['addresses'] as $id => $address) if (!ctype_digit((string)$id) || !is_array($address) || !preg_match('/^[a-f0-9]{64}$/D', $address['key'] ?? '') || !is_array($address['point'] ?? null) || !$this->validCoordinates($address['point'])) throw new RuntimeException('Invalid geocoding address state');
		return $state;
	}

	protected function writeGeocodingState(array $state, string $name = 'manual.json'): void {
		$directory = $this->geocoding['state_dir'];
		$file = tempnam($directory, '.manual-');
		if (!$file) throw new RuntimeException('Cannot write geocoding state');
		try {
			$encoded = json_encode($state, JSON_THROW_ON_ERROR);
			if (file_put_contents($file, $encoded) !== strlen($encoded) || !chmod($file, 0600) || !rename($file, $directory.'/'.$name)) throw new RuntimeException('Cannot persist geocoding state');
		} finally { if (is_file($file)) unlink($file); }
	}

	protected function requestGeocode(string $location): array {
		$context = stream_context_create(['http' => [
			'header' => 'User-Agent: '.$this->geocoding['user_agent']."\r\n",
			'timeout' => 10,
			'follow_location' => 0,
			'ignore_errors' => true,
		]]);
		$url = $this->geocoding['endpoint'].'?'.$location;
		$json = @file_get_contents($url, false, $context, 0, 131072);
		return [$json, $http_response_header ?? []];
	}

	public function updateAddress($id, $data, array $expected = []) {
		$id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if ($id === false || !$this->validCoordinates($data)) return false;

		$earlier = $this->batchContext === null ? '' : " LEFT JOIN address earlier ON earlier.parentid = c.id AND earlier.clientid = c.clientid AND earlier.module = 'contacts' AND earlier.controller = 'contact' AND earlier.type = 'billing' AND earlier.deleted = 0 AND (earlier.ordering < a.ordering OR (earlier.ordering = a.ordering AND earlier.id < a.id))";
		$query = "UPDATE address a JOIN contact c ON c.id = a.parentid AND c.clientid = a.clientid AND c.deleted = 0".$earlier." SET a.latitude = ?, a.longitude = ?, a.geoupdated = ? WHERE a.id = ? AND a.clientid = ? AND a.deleted = 0 AND a.module = 'contacts' AND a.controller = 'contact' AND a.type = 'billing'";
		$values = [(float)$data['latitude'], (float)$data['longitude'], $data['geoupdated'], $id, self::GEOCODING_CLIENT_ID];
		$types = 'ddsii';
		if ($this->batchContext !== null) {
			$query .= ' AND earlier.id IS NULL AND c.id = ? AND BINARY c.created <=> BINARY ? AND (a.latitude IS NULL OR a.longitude IS NULL OR a.latitude NOT BETWEEN -90 AND 90 OR a.longitude NOT BETWEEN -180 AND 180)';
			$values = array_merge($values, [$this->batchContext['id'], $this->batchContext['created']]);
			$types .= 'is';
		}

		// Compare address text byte-for-byte, including changes within the same second.
		if ($expected) {
			foreach (['street', 'postcode', 'city', 'country', 'module', 'controller', 'parentid', 'type', 'modified', 'geoupdated', 'latitude', 'longitude'] as $field) {
				if (!array_key_exists($field, $expected)) return false;
				$query .= ' AND BINARY a.'.$field.' <=> BINARY ?';
				$values[] = $expected[$field];
				$types .= 's';
			}
		}

		$statement = $this->connection->prepare($query);
		try {
			$statement->bind_param($types, ...$values);
			return $statement->execute() && $statement->affected_rows === 1;
		} finally {
			$statement->close();
		}
	}

	private function validCoordinates(array $data): bool {
		foreach (['latitude' => 90, 'longitude' => 180] as $field => $limit) {
			$value = $data[$field] ?? null;
			if (!is_numeric($value) || !is_finite((float)$value) || abs((float)$value) > $limit) return false;
		}
		return true;
	}
}
