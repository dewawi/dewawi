<?php

class DEEC_Address {

	// Preserve the existing cron scope until broader tenant processing is explicitly enabled.
	private const GEOCODING_CLIENT_ID = 100;

	protected $basePath;

	protected $connection;

	public function __construct($basePath, $host, $username, $password, $dbname) {
		$this->basePath = $basePath;
		$this->connection = mysqli_connect($host, $username, $password, $dbname);
	}

	public function getAddress($id) {
		$where = 'id = '.$id.' AND deleted = 0';
		$query = '
				SELECT
					* FROM address
				WHERE
					'.$where.'
				ORDER
					BY id;';
		//echo $query;
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
			return mysqli_fetch_assoc($result);
		} else {
			return false;
		}
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
		$address = $this->getLatestAddress();
		if (!$address || trim((string)$address['street']) === '') return false;
		if (!empty($address['geoupdated']) && $address['geoupdated'] >= ($address['modified'] ?? $address['created'] ?? '')) return false;

		$location = $address['street'].' '.$address['postcode'].' '.$address['city'];
		[$json, $headers] = $this->requestGeocode($location);
		if ($json === false || !preg_match('~^HTTP/\S+ 200(?:\s|$)~', $headers[0] ?? '')) return false;

		$results = json_decode($json, true);
		if (!is_array($results) || !isset($results[0]) || !is_array($results[0])) return false;

		return $this->updateAddress($address['id'], [
			'latitude' => $results[0]['lat'] ?? null,
			'longitude' => $results[0]['lon'] ?? null,
			'geoupdated' => date('Y-m-d H:i:s'),
		], $address);
	}

	protected function requestGeocode(string $location): array {
		$context = stream_context_create(['http' => [
			'header' => "User-Agent: DEWAWI\r\n",
			'timeout' => 10,
			'follow_location' => 0,
		]]);
		$url = 'https://nominatim.openstreetmap.org/search?addressdetails=1&q='.urlencode($location).'&format=jsonv2&limit=1';
		$json = @file_get_contents($url, false, $context);
		return [$json, $http_response_header ?? []];
	}

	public function updateAddress($id, $data, array $expected = []) {
		$id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if ($id === false || !$this->validCoordinates($data)) return false;

		$query = 'UPDATE address SET latitude = ?, longitude = ?, geoupdated = ? WHERE id = ? AND clientid = ? AND deleted = 0';
		$values = [(float)$data['latitude'], (float)$data['longitude'], $data['geoupdated'], $id, self::GEOCODING_CLIENT_ID];
		$types = 'ddsii';

		// Compare address text byte-for-byte, including changes within the same second.
		if ($expected) {
			foreach (['street', 'postcode', 'city', 'country', 'module', 'controller', 'parentid', 'type', 'modified', 'geoupdated', 'latitude', 'longitude'] as $field) {
				if (!array_key_exists($field, $expected)) return false;
				$query .= ' AND BINARY '.$field.' <=> BINARY ?';
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
