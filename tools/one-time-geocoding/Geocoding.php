<?php

// This removable tool never reads or writes K2B/K2C state.
class OneTimeGeocodingAddress extends DEEC_Address
{
	public function assess(array $row): array { return $this->review((int)$row['id'], $row, [], true); }
}

class OneTimeGeocoding
{
	public const ENDPOINT = 'https://api-eu.geoapify.com/v1/batch';
	private const SNAPSHOT = ['id', 'clientid', 'parentid', 'module', 'controller', 'type', 'deleted', 'ordering', 'street', 'postcode', 'city', 'country', 'created', 'modified', 'latitude', 'longitude', 'geoupdated'];
	private const NUMBER = '\d+(?: ?\p{L})?(?: ?[-\/–] ?\d+(?: ?\p{L})?)?';
	private string $directory;
	private $lock;
	private $db;
	private ?Contacts_Model_GeocodingBatch $model;
	private ?OneTimeGeocodingAddress $address;

	public function __construct(string $directory, $db = null, ?Contacts_Model_GeocodingBatch $model = null, ?OneTimeGeocodingAddress $address = null)
	{
		self::privateDirectory($directory);
		$this->directory = $directory; $this->db = $db; $this->model = $model; $this->address = $address;
		$file = $this->path('work.lock'); $mask = umask(0077);
		try { $this->lock = fopen($file, 'c'); } finally { umask($mask); }
		if (!$this->lock || !chmod($file, 0600) || !flock($this->lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('work_busy');
	}

	public static function privateDirectory(string $directory): void
	{
		$root = realpath(dirname(__DIR__, 2));
		if ($directory === '' || $directory[0] !== '/' || realpath($directory) !== $directory || (fileperms($directory) & 0077) || $directory === $root || str_starts_with($directory, $root.'/')) throw new RuntimeException('private_directory_required');
	}

	public static function privateConfig(string $file): void
	{
		self::privateDirectory(dirname($file));
		if (!is_file($file) || is_link($file) || (fileperms($file) & 0077)) throw new RuntimeException('private_config_required');
	}

	private function path(string $name): string
	{
		if (!preg_match('/^[a-zA-Z0-9._-]+$/D', $name)) throw new RuntimeException('invalid_filename');
		$file = $this->directory.'/'.$name;
		if (is_link($file) || (file_exists($file) && (!is_file($file) || (fileperms($file) & 0077)))) throw new RuntimeException('unsafe_file');
		return $file;
	}

	public static function digest($data): string { return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)); }
	protected function now(): int { return time(); }
	private function read(string $name): array
	{
		$file = $this->path($name);
		if (!is_file($file) || filesize($file) > 128 * 1024 * 1024) throw new RuntimeException('missing_or_large_file');
		$value = json_decode(file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
		if (!is_array($value)) throw new RuntimeException('invalid_file');
		return $value;
	}

	private function write(string $name, array $data, bool $new = false): void
	{
		$target = $this->path($name);
		if ($new && file_exists($target)) throw new RuntimeException('immutable_file_exists');
		$temp = tempnam($this->directory, '.write-'); $handle = fopen($temp, 'wb');
		try {
			$bytes = json_encode($data, JSON_THROW_ON_ERROR);
			if (!chmod($temp, 0600) || fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle) || !fsync($handle)) throw new RuntimeException('state_write_failed');
			if (!rename($temp, $target)) throw new RuntimeException('state_rename_failed');
			$this->syncDirectory();
		} finally { fclose($handle); if (is_file($temp)) unlink($temp); }
	}

	private function syncDirectory(): void
	{
		$handle = fopen($this->directory, 'r');
		try { if (!fsync($handle)) throw new RuntimeException('directory_sync_failed'); } finally { fclose($handle); }
	}

	private function seal(string $name, array $data): string
	{
		$hash = self::digest($data); $this->write($name, ['sha256' => $hash, 'data' => $data], true); return $hash;
	}

	private function sealed(string $name): array
	{
		$file = $this->read($name);
		if (!is_string($file['sha256'] ?? null) || !is_array($file['data'] ?? null) || !hash_equals($file['sha256'], self::digest($file['data']))) throw new RuntimeException('snapshot_digest_mismatch');
		return $file;
	}

	public static function text(string $value): string { return mb_strtolower(trim(preg_replace('/[\s\p{Z}]+/u', ' ', Normalizer::normalize($value, Normalizer::FORM_C))), 'UTF-8'); }
	private static function number(string $value): ?string
	{
		if (!mb_check_encoding($value, 'UTF-8') || preg_match('/[\p{Cc}\p{Cf}]/u', str_replace(["\r", "\n", "\t"], '', $value))) return null;
		$value = self::text($value);
		return preg_match('/^'.self::NUMBER.'$/uD', $value) ? str_replace([' ', '–'], ['', '-'], $value) : null;
	}

	// DEEC_Address keeps these normalization helpers private; freeze the same narrow rules here.
	private static function street(string $value): string
	{
		$value = self::text($value);
		if (preg_match('/^(.+?)\s+('.self::NUMBER.')$/uD', $value, $parts)) return $parts[1].' '.self::number($parts[2]);
		if (preg_match('/^(\d+\p{L}?(?: ?[-\/–] ?\d+\p{L}?)?)\s+(.+)$/uD', $value, $parts)) return self::number($parts[1]).' '.$parts[2];
		return $value;
	}

	private static function snapshot(array $row): array
	{
		$result = [];
		foreach (self::SNAPSHOT as $field) { if (!array_key_exists($field, $row)) throw new RuntimeException('incomplete_snapshot'); $result[$field] = $row[$field] === null ? null : (string)$row[$field]; }
		return $result;
	}

	private function authorize(bool $write = false): void
	{
		if (!$this->db || !$this->model || !$this->address) throw new RuntimeException('database_context_required');
		$this->model->authorize();
		if ($write && !(new DEEC_Permission(Zend_Registry::get('User')))->hasPermission('contacts', 'contact', 'edit')) throw new RuntimeException('edit_permission_required');
	}

	private function addresses(array $ids): array
	{
		if (!$ids) return [];
		$rows = $this->db->fetchAll('SELECT * FROM address WHERE clientid = 100 AND id IN ('.implode(',', array_fill(0, count($ids), '?')).')', $ids);
		return array_column($rows, null, 'id');
	}

	public function export(string $target, int $limit = 50000): array
	{
		$this->authorize();
		if ($limit < 1 || $limit > 50000) throw new RuntimeException('invalid_export_limit');
		if (file_exists($this->path('export.json'))) throw new RuntimeException('export_already_exists');
		$records = []; $queries = []; $excluded = []; $selected = 0; $after = null;
		do {
			$rows = $this->model->candidates(min(1000, $limit - $selected), $after);
			$addresses = $this->addresses(array_column($rows, 'address_id'));
			foreach ($rows as $contact) {
				$selected++; $after = Contacts_Model_GeocodingBatch::encodeCursor($contact);
				$row = $addresses[$contact['address_id']] ?? null;
				$review = $row ? $this->address->assess($row) : ['reasons' => ['ineligible_address']];
				if (!isset($review['fingerprint']) || self::validPoint($row)) { $reason = $review['reasons'][0] ?? 'already_valid'; $excluded[$reason] = ($excluded[$reason] ?? 0) + 1; continue; }
				$fields = []; foreach (['street', 'postcode', 'city', 'country'] as $field) $fields[$field] = self::text((string)$row[$field]);
				$fields['street'] = self::street($fields['street']);
				$params = ['text' => implode(', ', $fields), 'filter' => 'countrycode:'.$fields['country']];
				$key = self::digest($params); $queries[$key] = $params;
				$records[] = ['contact_id' => (int)$contact['id'], 'contact_created' => $contact['created'], 'snapshot' => self::snapshot($row), 'fields' => $fields, 'query_key' => $key];
			}
		} while ($rows && $selected < $limit);
		$tail = $after === null ? null : $this->model->page(1, $after)['selection_page'];
		$remaining = $tail === null ? 0 : $tail['selected'] + $tail['remaining_older'];
		$data = ['version' => 1, 'clientid' => 100, 'target' => $target, 'endpoint' => self::ENDPOINT, 'created_at' => $this->now(), 'selected' => $selected, 'remaining_older' => $remaining, 'excluded' => $excluded, 'records' => $records, 'queries' => $queries];
		$hash = $this->seal('export.json', $data);
		return ['export_sha256' => $hash, 'selected' => $selected, 'exported' => count($records), 'unique_requests' => count($queries), 'duplicate_requests_saved' => count($records) - count($queries), 'excluded' => $excluded, 'remaining_older' => $remaining, 'database_writes' => 0];
	}

	private function exportFile(): array
	{
		$file = $this->sealed('export.json'); $data = $file['data'];
		if (($data['version'] ?? 0) !== 1 || ($data['clientid'] ?? 0) !== 100 || ($data['endpoint'] ?? '') !== self::ENDPOINT) throw new RuntimeException('invalid_export_scope');
		return $file;
	}

	private function state(string $digest): array
	{
		$state = file_exists($this->path('jobs.json')) ? $this->read('jobs.json') : ['export_sha256' => $digest, 'jobs' => []];
		if (($state['export_sha256'] ?? '') !== $digest || !is_array($state['jobs'] ?? null)) throw new RuntimeException('invalid_job_state');
		$seen = [];
		foreach ($state['jobs'] as $job) {
			if (!is_array($job['keys'] ?? null) || !$job['keys'] || count($job['keys']) > 1000 || !in_array($job['status'] ?? '', ['submitting', 'unknown', 'pending', 'done', 'expired', 'invalid_response', 'abandoned'], true) || !is_int($job['next_at'] ?? null) || $job['next_at'] < 0 || !is_int($job['submitted_at'] ?? null) || !is_bool($job['poll_reserved'] ?? null)) throw new RuntimeException('invalid_job_state');
			foreach ($job['keys'] as $key) { if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key) || isset($seen[$key])) throw new RuntimeException('invalid_job_keys'); $seen[$key] = true; }
			if (in_array($job['status'], ['pending', 'done'], true) && !preg_match('/^[a-f0-9]{32}$/D', $job['provider_id'] ?? '')) throw new RuntimeException('invalid_provider_job');
			if ($job['status'] === 'done' && !preg_match('/^[a-f0-9]{64}$/D', $job['result_sha256'] ?? '')) throw new RuntimeException('invalid_result_digest');
		}
		return $state;
	}

	public function status(): array
	{
		$export = $this->exportFile(); $state = $this->state($export['sha256']); $counts = [];
		foreach ($state['jobs'] as $job) $counts[$job['status']] = ($counts[$job['status']] ?? 0) + 1;
		return ['export_sha256' => $export['sha256'], 'exported' => count($export['data']['records']), 'unique_requests' => count($export['data']['queries']), 'jobs' => $counts, 'external_requests' => 0, 'database_writes' => 0];
	}

	private static function reference($value): bool { return is_string($value) && strlen(trim($value)) >= 3 && strlen($value) <= 250 && !preg_match('/[\x00-\x1f\x7f]/', $value); }
	private static function limit(array $config, string $name, int $default, int $max): int
	{
		$value = filter_var($config[$name] ?? $default, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max]]);
		if ($value === false) throw new RuntimeException('invalid_provider_limit'); return $value;
	}

	public function geocode(array $config, string $execute): array
	{
		$export = $this->exportFile();
		if (!hash_equals($export['sha256'], $execute) || filter_var($config['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN) !== true || !self::reference($config['processing_approval'] ?? null) || !self::reference($config['terms_approval'] ?? null) || !preg_match('/^[a-zA-Z0-9_-]{16,200}$/D', $config['api_key'] ?? '')) throw new RuntimeException('external_processing_not_released');
		$batchSize = self::limit($config, 'batch_size', 500, 1000); $dailyCredits = self::limit($config, 'daily_credits', 2800, 3000); $dailyInputs = self::limit($config, 'daily_inputs', 5000, 5000);
		$poll = self::limit($config, 'poll_seconds', 300, 3600); if ($poll < 60) throw new RuntimeException('poll_interval_too_short');
		$quotaDir = $config['quota_dir'] ?? ''; self::privateDirectory($quotaDir);
		$quota = new self($quotaDir);
		$ledger = file_exists($quota->path('quota.json')) ? $quota->read('quota.json') : ['key_hash' => hash('sha256', $config['api_key']), 'next_at' => 0, 'entries' => []];
		if (($ledger['key_hash'] ?? '') !== hash('sha256', $config['api_key'])) throw new RuntimeException('quota_key_changed');
		if (!is_int($ledger['next_at'] ?? null) || $ledger['next_at'] < 0 || !is_array($ledger['entries'] ?? null)) throw new RuntimeException('invalid_quota_state');
		foreach ($ledger['entries'] as $entry) foreach (['at', 'credits', 'inputs'] as $field) if (!is_int($entry[$field] ?? null) || $entry[$field] < 0) throw new RuntimeException('invalid_quota_state');
		$state = $this->state($export['sha256']); $index = null;
		foreach ($state['jobs'] as $job) foreach ($job['keys'] as $key) if (!isset($export['data']['queries'][$key])) throw new RuntimeException('foreign_job_key');
		foreach ($state['jobs'] as $i => $job) {
			if (in_array($job['status'], ['submitting', 'unknown'], true)) return ['status' => 'submission_unknown', 'job' => $i, 'external_requests' => 0];
			if ($job['status'] === 'pending') { $index = $i; break; }
		}
		if ($index === null) {
			$used = []; foreach ($state['jobs'] as $job) foreach ($job['keys'] as $key) $used[$key] = true;
			$keys = array_slice(array_keys(array_diff_key($export['data']['queries'], $used)), 0, $batchSize);
			if (!$keys) return ['status' => 'complete', 'external_requests' => 0];
			$index = count($state['jobs']); $job = ['keys' => $keys, 'status' => 'submitting', 'submitted_at' => $this->now(), 'next_at' => 0, 'poll_reserved' => true];
		} else $job = $state['jobs'][$index];
		$now = $this->now();
		if (max($ledger['next_at'], $job['next_at']) > $now) return ['status' => 'waiting', 'next_at' => max($ledger['next_at'], $job['next_at']), 'external_requests' => 0];
		$isPost = $job['status'] === 'submitting'; $inputs = $isPost ? count($job['keys']) : 0;
		// Priority 0.5; reserve creation plus the first GET before submitting.
		$credits = $isPost ? (int)ceil($inputs * .5) + 2 : ($job['poll_reserved'] && $job['submitted_at'] > $now - 86400 ? 0 : 1);
		$ledger['entries'] = array_values(array_filter($ledger['entries'], fn($entry) => $entry['at'] > $now - 86400));
		if (array_sum(array_column($ledger['entries'], 'credits')) + $credits > $dailyCredits || array_sum(array_column($ledger['entries'], 'inputs')) + $inputs > $dailyInputs) return ['status' => 'quota_wait', 'external_requests' => 0];
		$ledger['entries'][] = ['at' => $now, 'credits' => $credits, 'inputs' => $inputs]; $ledger['next_at'] = $now + 60;
		$quota->write('quota.json', $ledger);
		$state['jobs'][$index] = $job; $this->write('jobs.json', $state);
		$body = $isPost ? ['api' => '/v1/geocode/search', 'priority' => .5, 'params' => ['format' => 'geojson', 'limit' => 3], 'inputs' => array_map(fn($key) => ['params' => $export['data']['queries'][$key]], $job['keys'])] : null;
		try { [$code, $response, $retry] = $this->http($isPost ? 'POST' : 'GET', $job['provider_id'] ?? null, $body, $config['api_key']); }
		catch (Throwable $error) { $code = 0; $response = []; $retry = 0; }
		if (!$isPost) $job['poll_reserved'] = false;
		if (!$isPost && $code !== 200 && $code !== 202) {
			if ($code === 404) $job['status'] = 'expired';
			$job['next_at'] = max($now + 3600, $retry);
		} elseif (in_array($code, [200, 202], true) && preg_match('/^[a-f0-9]{32}$/D', $response['id'] ?? '') && ($isPost || $response['id'] === $job['provider_id'])) {
			$job['provider_id'] = $response['id']; $job['status'] = 'pending'; $job['next_at'] = $now + $poll;
			$state['jobs'][$index] = $job; $this->write('jobs.json', $state);
			if ($code === 200) {
				try { $results = $this->mappedResults($response, $job, $export['data']['queries']); }
				catch (Throwable $error) { $job['status'] = 'invalid_response'; }
				if ($job['status'] !== 'invalid_response') {
					$name = 'results-'.$index.'.json';
					if (file_exists($this->path($name))) {
						$existing = $this->sealed($name);
						if ($existing['sha256'] !== self::digest($results)) throw new RuntimeException('completed_results_changed');
						$job['result_sha256'] = $existing['sha256'];
					} else $job['result_sha256'] = $this->seal($name, $results);
					$job['status'] = 'done';
				}
			}
		} else $job['status'] = $isPost ? 'unknown' : 'invalid_response';
		$state['jobs'][$index] = $job; $this->write('jobs.json', $state);
		return ['status' => $job['status'], 'job' => $index, 'reserved_credits' => $credits, 'external_requests' => 1, 'database_writes' => 0];
	}

	protected function http(string $method, ?string $id, ?array $body, string $key): array
	{
		$url = self::ENDPOINT.($id === null ? '' : '?id='.rawurlencode($id)); $buffer = ''; $retry = 0;
		$curl = curl_init($url);
		try {
			curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => ['x-api-key: '.$key, 'Content-Type: application/json', 'Accept: application/json'], CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
				CURLOPT_WRITEFUNCTION => static function($handle, string $bytes) use (&$buffer): int { if (strlen($buffer) + strlen($bytes) > 16 * 1024 * 1024) return 0; $buffer .= $bytes; return strlen($bytes); },
				CURLOPT_HEADERFUNCTION => function($handle, string $header) use (&$retry): int { if (preg_match('/^Retry-After:\s*(.+)/i', $header, $match)) $retry = ctype_digit(trim($match[1])) ? $this->now() + (int)trim($match[1]) : (int)strtotime(trim($match[1])); return strlen($header); }]);
			if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
			if (curl_exec($curl) === false) return [0, [], 0];
			return [(int)curl_getinfo($curl, CURLINFO_HTTP_CODE), json_decode($buffer, true, 64, JSON_THROW_ON_ERROR), $retry];
		} finally { curl_close($curl); }
	}

	private function mappedResults(array $response, array $job, array $queries): array
	{
		if (($response['api'] ?? '/v1/geocode/search') !== '/v1/geocode/search' || !is_array($response['results'] ?? null) || count($response['results']) !== count($job['keys'])) throw new RuntimeException('invalid_batch_mapping');
		$mapped = [];
		foreach ($response['results'] as $position => $item) {
			if (!is_int($position) || !isset($job['keys'][$position]) || !is_array($item)) throw new RuntimeException('invalid_batch_mapping');
			// The documented result order binds inputs when optional echoed params are absent.
			$params = $item['params'] ?? $queries[$job['keys'][$position]];
			$params = array_intersect_key($params, array_flip(['text', 'filter']));
			$params = ['text' => $params['text'] ?? null, 'filter' => $params['filter'] ?? null];
			$key = self::digest($params);
			if (!in_array($key, $job['keys'], true) || isset($mapped[$key]) || $params !== $queries[$key]) throw new RuntimeException('invalid_batch_mapping');
			$features = $item['result']['features'] ?? null; $clean = [];
			if (is_array($features)) foreach ($features as $feature) {
				if (!is_array($feature)) { $clean[] = []; continue; }
				$feature = array_intersect_key($feature, array_flip(['type', 'properties', 'geometry', 'bbox']));
				$feature['properties'] = array_intersect_key(is_array($feature['properties'] ?? null) ? $feature['properties'] : [], array_flip(['lat', 'lon', 'country_code', 'postcode', 'city', 'street', 'housenumber', 'result_type', 'rank', 'datasource']));
				$clean[] = $feature;
			}
			$mapped[$key] = is_array($features) ? ['type' => $item['result']['type'] ?? '', 'features' => $clean] : ['error' => true];
		}
		return $mapped;
	}

	public function recoverJob(int $index, string $id, string $execute): array
	{
		$export = $this->exportFile(); $state = $this->state($export['sha256']);
		if (!hash_equals($export['sha256'], $execute) || !preg_match('/^[a-f0-9]{32}$/D', $id) || !isset($state['jobs'][$index]) || !in_array($state['jobs'][$index]['status'], ['unknown', 'submitting'], true)) throw new RuntimeException('invalid_recovery');
		foreach ($state['jobs'] as $job) if (($job['provider_id'] ?? '') === $id) throw new RuntimeException('duplicate_provider_job');
		$state['jobs'][$index]['provider_id'] = $id; $state['jobs'][$index]['status'] = 'pending'; $state['jobs'][$index]['next_at'] = $this->now();
		$this->write('jobs.json', $state); return ['status' => 'recovered', 'job' => $index, 'external_requests' => 0];
	}

	public function abandonJob(int $index, string $execute): array
	{
		$export = $this->exportFile(); $state = $this->state($export['sha256']);
		if (!hash_equals($export['sha256'], $execute) || !isset($state['jobs'][$index]) || !in_array($state['jobs'][$index]['status'], ['submitting', 'unknown'], true)) throw new RuntimeException('invalid_abandon');
		$state['jobs'][$index]['status'] = 'abandoned'; $this->write('jobs.json', $state);
		return ['status' => 'abandoned', 'job' => $index, 'external_requests' => 0];
	}

	private static function validPoint(array $row): bool
	{
		foreach (['latitude' => 90, 'longitude' => 180] as $field => $limit) if (!is_numeric($row[$field] ?? null) || !is_finite((float)$row[$field]) || abs((float)$row[$field]) > $limit) return false;
		return true;
	}

	public static function validate(array $result, array $fields): array
	{
		if (($result['type'] ?? '') !== 'FeatureCollection' || !is_array($result['features'] ?? null)) return ['rejected' => 'api_error'];
		if (count($result['features']) !== 1) return ['rejected' => 'missing_or_ambiguous'];
		$feature = $result['features'][0] ?? null;
		if (!is_array($feature) || !is_array($feature['properties'] ?? null) || !is_array($feature['geometry'] ?? null) || !is_array($feature['properties']['rank'] ?? null)) return ['rejected' => 'malformed_feature'];
		$p = $feature['properties']; $rank = $p['rank']; $coordinates = $feature['geometry']['coordinates'] ?? null;
		if (($feature['geometry']['type'] ?? '') !== 'Point' || !is_array($coordinates) || !array_is_list($coordinates) || count($coordinates) !== 2 || !self::validPoint(['latitude' => $p['lat'] ?? null, 'longitude' => $p['lon'] ?? null]) || (float)$p['lat'] === 0.0 && (float)$p['lon'] === 0.0) return ['rejected' => 'invalid_coordinates'];
		if (!self::validPoint(['longitude' => $coordinates[0], 'latitude' => $coordinates[1]]) || (float)$coordinates[0] !== (float)$p['lon'] || (float)$coordinates[1] !== (float)$p['lat']) return ['rejected' => 'geometry_mismatch'];
		if (($p['result_type'] ?? '') !== 'building' || !in_array($rank['match_type'] ?? '', ['full_match', 'match_by_building'], true)) return ['rejected' => 'imprecise'];
		foreach (['confidence', 'confidence_city_level', 'confidence_street_level', 'confidence_building_level'] as $field) if (!is_numeric($rank[$field] ?? null) || !is_finite((float)$rank[$field]) || $rank[$field] < .95 || $rank[$field] > 1) return ['rejected' => 'low_or_missing_accuracy'];
		foreach (['country_code', 'postcode', 'city', 'street', 'housenumber'] as $field) if (!is_string($p[$field] ?? null) || !mb_check_encoding($p[$field], 'UTF-8') || preg_match('/[\p{Cc}\p{Cf}]/u', $p[$field])) return ['rejected' => 'incomplete_components'];
		if (self::text($p['country_code']) !== $fields['country']) return ['rejected' => 'country_mismatch'];
		if (str_replace(' ', '', self::text($p['postcode'])) !== str_replace(' ', '', $fields['postcode'])) return ['rejected' => 'postcode_mismatch'];
		if (self::text($p['city']) !== $fields['city']) return ['rejected' => 'city_mismatch'];
		$number = self::number($p['housenumber']); $road = self::text($p['street']);
		if ($number === null || !in_array($fields['street'], [$road.' '.$number, $number.' '.$road], true)) return ['rejected' => 'street_or_number_mismatch'];
		if (isset($feature['bbox'])) {
			$box = $feature['bbox'];
			if (!is_array($box) || !array_is_list($box) || count($box) !== 4 || count(array_filter($box, static fn($value) => is_numeric($value) && is_finite((float)$value))) !== 4) return ['rejected' => 'invalid_bbox'];
			[$west, $south, $east, $north] = array_map('floatval', $box);
			if ($west < -180 || $east > 180 || $south < -90 || $north > 90 || $east - $west > .1 || $north - $south > .1 || $west > $p['lon'] || $east < $p['lon'] || $south > $p['lat'] || $north < $p['lat']) return ['rejected' => 'invalid_bbox'];
		}
		return ['point' => ['latitude' => number_format((float)$p['lat'], 9, '.', ''), 'longitude' => number_format((float)$p['lon'], 9, '.', '')]];
	}

	private function results(array $export): array
	{
		$state = $this->state($export['sha256']); $results = []; $pending = count($export['data']['queries']);
		foreach ($state['jobs'] as $index => $job) {
			if ($job['status'] === 'done') {
				$file = $this->sealed('results-'.$index.'.json');
				if (!hash_equals($job['result_sha256'], $file['sha256'])) throw new RuntimeException('results_changed');
				$results += $file['data']; $pending -= count($job['keys']);
			} elseif (in_array($job['status'], ['expired', 'invalid_response', 'abandoned'], true)) { foreach ($job['keys'] as $key) $results[$key] = ['error' => true]; $pending -= count($job['keys']); }
		}
		return ['data' => $results, 'pending' => $pending, 'sha256' => self::digest($state)];
	}

	private function current(array $record): ?array
	{
		$contact = $this->model->current($record['contact_id']);
		if (!$contact || (string)$contact['address_id'] !== $record['snapshot']['id'] || $contact['created'] !== $record['contact_created']) return null;
		$row = $this->db->fetchRow('SELECT * FROM address WHERE id = ? AND clientid = 100', [$record['snapshot']['id']]);
		return $row ? self::snapshot($row) : null;
	}

	private function currentHashes(array $records): array
	{
		if (!$records) return [];
		$wanted = []; foreach ($records as $record) $wanted[$record['contact_id']] = $record;
		$hashes = []; $after = null;
		do {
			$rows = $this->model->candidates(1000, $after); $ids = []; $owners = [];
			foreach ($rows as $contact) {
				$after = Contacts_Model_GeocodingBatch::encodeCursor($contact); $expected = $wanted[(int)$contact['id']] ?? null;
				if ($expected === null || (string)$contact['address_id'] !== $expected['snapshot']['id'] || $contact['created'] !== $expected['contact_created']) continue;
				$ids[] = $contact['address_id']; $owners[$contact['address_id']] = (int)$contact['id'];
			}
			foreach ($this->addresses($ids) as $id => $row) $hashes[$owners[$id]] = self::digest(self::snapshot($row));
		} while ($rows);
		return $hashes;
	}

	public function plan(string $name, string $target): array
	{
		if (!preg_match('/^plan(?:-[a-zA-Z0-9_-]+)?\.json$/D', $name)) throw new RuntimeException('invalid_plan_name');
		$this->authorize(); $export = $this->exportFile();
		if ($export['data']['target'] !== $target) throw new RuntimeException('target_changed');
		$results = $this->results($export); $currentHashes = $this->currentHashes($export['data']['records']);
		$updates = []; $counts = ['planned' => 0, 'conflicts' => 0, 'rejected' => 0, 'pending' => 0]; $reasons = [];
		foreach ($export['data']['records'] as $record) {
			$result = $results['data'][$record['query_key']] ?? null;
			if ($result === null) { $counts['pending']++; continue; }
			$check = self::validate($result, $record['fields']);
			if (isset($check['rejected'])) { $counts['rejected']++; $reasons[$check['rejected']] = ($reasons[$check['rejected']] ?? 0) + 1; continue; }
			if (self::validPoint($record['snapshot']) || !hash_equals($currentHashes[$record['contact_id']] ?? '', self::digest($record['snapshot']))) { $counts['conflicts']++; continue; }
			$updates[] = ['record' => $record, 'point' => $check['point']]; $counts['planned']++;
		}
		$plan = ['version' => 1, 'clientid' => 100, 'target' => $target, 'export_sha256' => $export['sha256'], 'results_sha256' => $results['sha256'], 'counts' => $counts, 'rejected_reasons' => $reasons, 'updates' => $updates, 'pending_queries' => $results['pending']];
		return ['plan_sha256' => $this->seal($name, $plan), 'counts' => $counts, 'rejected_reasons' => $reasons, 'pending_queries' => $results['pending'], 'database_writes' => 0];
	}

	private function append(string $name, array $entry): void
	{
		$file = $this->path($name); $mask = umask(0077);
		try { $handle = fopen($file, 'ab'); } finally { umask($mask); }
		try { $bytes = json_encode($entry, JSON_THROW_ON_ERROR)."\n"; if (!chmod($file, 0600) || fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle) || !fsync($handle)) throw new RuntimeException('journal_write_failed'); }
		finally { fclose($handle); }
		$this->syncDirectory();
	}

	public function import(string $name, string $target, array $permit, string $execute, int $limit = 100): array
	{
		if (!preg_match('/^plan(?:-[a-zA-Z0-9_-]+)?\.json$/D', $name)) throw new RuntimeException('invalid_plan_name');
		$this->authorize(true); $file = $this->sealed($name); $plan = $file['data']; $export = $this->exportFile(); $results = $this->results($export);
		if ($limit < 1 || $limit > 1000 || filter_var($permit['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN) !== true || !hash_equals($file['sha256'], $execute) || ($permit['plan_sha256'] ?? '') !== $execute || !self::reference($permit['approval_reference'] ?? null) || !self::reference($permit['backup_reference'] ?? null) || !self::reference($permit['attribution_reference'] ?? null)) throw new RuntimeException('import_not_released');
		if (($plan['clientid'] ?? 0) !== 100 || $plan['target'] !== $target || $plan['export_sha256'] !== $export['sha256'] || $plan['results_sha256'] !== $results['sha256'] || $plan['pending_queries'] !== 0) throw new RuntimeException('import_snapshot_changed');
		$original = []; foreach ($export['data']['records'] as $record) $original[$record['snapshot']['id']] = $record;
		$seen = [];
		foreach ($plan['updates'] as $update) {
			$record = $update['record']; $id = $record['snapshot']['id'];
			if (isset($seen[$id]) || self::validPoint($record['snapshot']) || ($original[$id] ?? null) !== $record || (self::validate($results['data'][$record['query_key']] ?? [], $record['fields'])['point'] ?? null) !== $update['point']) throw new RuntimeException('invalid_planned_update');
			$seen[$id] = true;
		}
		$backup = 'backup-'.$execute.'.json'; $journal = 'import-'.$execute.'.jsonl';
		if (!file_exists($this->path($backup))) $this->seal($backup, $plan);
		elseif (!hash_equals(self::digest(['sha256' => $execute, 'data' => $plan]), hash_file('sha256', $this->path($backup)))) throw new RuntimeException('backup_changed');
		$entries = []; $done = [];
		if (file_exists($this->path($journal))) foreach (file($this->path($journal), FILE_IGNORE_NEW_LINES) as $line) {
			$entry = json_decode($line, true, 16, JSON_THROW_ON_ERROR);
			if (!is_array($entry) || ($entry['plan_sha256'] ?? '') !== $execute || !is_string($entry['address_id'] ?? null) || !isset($seen[$entry['address_id']]) || !in_array($entry['status'] ?? '', ['intent', 'updated', 'conflicts', 'recovered'], true) || !is_string($entry['geoupdated'] ?? null)) throw new RuntimeException('invalid_journal');
			$date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $entry['geoupdated']);
			if (!$date || $date->format('Y-m-d H:i:s') !== $entry['geoupdated']) throw new RuntimeException('invalid_journal_date');
			if ($entry['status'] === 'intent') $entries[$entry['address_id']] = $entry;
			else $done[$entry['address_id']] = true;
		}
		$counts = ['updated' => 0, 'conflicts' => 0, 'recovered' => 0, 'already_logged' => 0]; $processed = 0;
		foreach ($plan['updates'] as $update) {
			$record = $update['record']; $id = $record['snapshot']['id'];
			if (isset($done[$id])) { $counts['already_logged']++; continue; }
			if ($processed++ >= $limit) break;
			$entry = $entries[$id] ?? ['status' => 'intent', 'plan_sha256' => $execute, 'address_id' => $id, 'operator' => (int)Zend_Registry::get('User')['id'], 'at' => gmdate('c', $this->now()), 'geoupdated' => date('Y-m-d H:i:s', $this->now())];
			$current = $this->current($record); $expectedAfter = array_replace($record['snapshot'], $update['point'], ['geoupdated' => $entry['geoupdated']]);
			if (isset($entries[$id]) && $current === $expectedAfter) $status = 'recovered';
			elseif ($current !== $record['snapshot']) $status = 'conflicts';
			else {
				$this->append($journal, $entry);
				$this->db->beginTransaction();
				try { $status = $this->apply($record, $update['point'], $entry['geoupdated']) ? 'updated' : 'conflicts'; $this->db->commit(); }
				catch (Throwable $error) { $this->db->rollBack(); throw $error; }
			}
			$this->append($journal, array_replace($entry, ['status' => $status])); $counts[$status]++;
		}
		$finished = count(array_intersect_key($done, $seen)) + $counts['updated'] + $counts['conflicts'] + $counts['recovered'];
		$remaining = count($plan['updates']) - $finished;
		return ['status' => $remaining ? 'partial' : 'complete', 'remaining' => $remaining, 'counts' => $counts, 'database_writes' => $counts['updated'], 'backup' => $backup, 'journal' => $journal];
	}

	protected function apply(array $record, array $point, string $updated): bool
	{
		$sql = "UPDATE address a JOIN contact c ON c.id = a.parentid AND c.clientid = a.clientid AND c.deleted = 0 LEFT JOIN address earlier ON earlier.parentid = c.id AND earlier.clientid = c.clientid AND earlier.module = 'contacts' AND earlier.controller = 'contact' AND earlier.type = 'billing' AND earlier.deleted = 0 AND ((earlier.ordering IS NULL AND a.ordering IS NOT NULL) OR earlier.ordering < a.ordering OR (earlier.ordering <=> a.ordering AND earlier.id < a.id)) SET a.latitude = ?, a.longitude = ?, a.geoupdated = ? WHERE earlier.id IS NULL AND a.clientid = 100 AND a.deleted = 0 AND a.module = 'contacts' AND a.controller = 'contact' AND a.type = 'billing' AND (a.latitude IS NULL OR a.longitude IS NULL OR a.latitude NOT BETWEEN -90 AND 90 OR a.longitude NOT BETWEEN -180 AND 180) AND c.id = ? AND BINARY c.created <=> BINARY ?";
		$bind = [$point['latitude'], $point['longitude'], $updated, $record['contact_id'], $record['contact_created']];
		foreach ($record['snapshot'] as $field => $value) {
			if (!in_array($field, self::SNAPSHOT, true)) throw new RuntimeException('invalid_snapshot_field');
			$sql .= ' AND BINARY a.`'.$field.'` <=> BINARY ?'; $bind[] = $value;
		}
		return $this->db->query($sql, $bind)->rowCount() === 1;
	}
}
