<?php

class DEEC_Albis
{
	private const URLS = ['test' => 'https://test-api.albis-leasing.de', 'live' => 'https://api.albis-leasing.de'];
	private DEEC_Site_Context $context;
	private Zend_Cache_Core $cache;
	private array $credentials;
	private ?Zend_Http_Client_Adapter_Interface $adapter;
	private string $scope;

	public function __construct(DEEC_Site_Context $context, Zend_Cache_Core $cache, ?Zend_Http_Client_Adapter_Interface $adapter = null, ?array $credentials = null)
	{
		$this->context = $context;
		$this->cache = $cache;
		$this->adapter = $adapter;
		$prefix = 'DEWAWI_ALBIS_' . $context->getClientId() . '_' . $context->getSiteId() . '_' . strtoupper((string)$context->get('albisenvironment', 'test')) . '_';
		$this->credentials = $credentials ?? [
			'client_id' => (string)getenv($prefix . 'CLIENT_ID'),
			'client_secret' => (string)getenv($prefix . 'CLIENT_SECRET'),
			'cache_key' => (string)getenv($prefix . 'CACHE_KEY'),
		];
		$this->scope = 'albis_' . hash('sha256', serialize([
			$context->getClientId(), $context->getSiteId(), $context->get('albisenvironment', 'test'),
			defined('APPLICATION_ENV') ? APPLICATION_ENV : 'production', $this->credentials,
		]));
	}

	public static function eligiblePrice(DEEC_Site_Context $context, array $item): ?float
	{
		$environment = defined('APPLICATION_ENV') ? APPLICATION_ENV : 'production';
		if ($environment === 'production' && $context->get('albisenvironment', 'test') !== 'live') return null;
		if (!$context->get('albisenabled') || !$context->get('activated') || !$context->hasFeature('catalog') || !$context->hasFeature('inquiry')
			|| $context->getClientId() <= 0 || $context->getSiteId() <= 0
			|| (int)($item['shopid'] ?? 0) !== $context->getSiteId() || (int)($item['clientid'] ?? 0) !== $context->getClientId() || !empty($item['deleted'])
			|| empty($item['shopenabled']) || empty($item['albiseligible']) || (int)($item['shopcatid'] ?? 0) <= 0
			|| strtoupper(trim((string)($item['currency'] ?? ''))) !== 'EUR'
			|| !isset(self::URLS[$context->get('albisenvironment', 'test')])) return null;

		foreach (['albisproductgroup' => 23, 'albiscontracttype' => 1, 'albispaymentmode' => 2] as $field => $default) {
			if (!filter_var($context->get($field, $default), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) return null;
		}
		$threshold = self::amount($context->get('albisthreshold', 5000));
		$fee = self::amount($context->get('albisfeenet'));
		$price = self::amount($item['price'] ?? null);
		$special = $item['specialprice'] ?? null;
		if ($special !== null && $special !== '' && (!is_numeric($special) || (float)$special != 0.0)) $price = self::amount($special);
		if ($threshold === null || $fee === null || $price === null || $price <= 0) return null;
		$price = round($price, 2);
		return $price > $threshold ? $price : null;
	}

	public function quote(array $item): ?array
	{
		$price = self::eligiblePrice($this->context, $item);
		$key = '';
		if ($price === null || !$this->ready()) return null;
		try {
			$params = [
				'kaufpreis' => $price,
				'prodgrp' => (int)$this->context->get('albisproductgroup', 23),
				'vertragsart' => (int)$this->context->get('albiscontracttype', 1),
				'zahlweise' => (int)$this->context->get('albispaymentmode', 2),
				'mietsz' => 0,
			];
			$key = $this->key('quote', [$params, $this->context->get('albisfeenet')]);
			$cached = $this->cache->load($key);
			if ($cached !== false) return $cached ?: null;
			if ($this->cache->load($this->key('unavailable')) !== false) return null;

			$masters = $this->masters();
			$group = self::find($masters['groups'], $params['prodgrp']);
			$contract = self::find($masters['contracts'], $params['vertragsart']);
			$payment = self::find($masters['payments'], $params['zahlweise']);
			if (!$group || !$contract || !$payment || !isset($payment['monate']) || !in_array((int)$payment['monate'], [1, 3, 6, 12], true)) {
				$this->cache->save([], $key, [], 300);
				return null;
			}

			$result = $this->request('POST', '/v1/shop/calculate_rate', $params, $this->token());
			// The API description uses a raten wrapper; its response example is a list.
			$rows = $result['raten'] ?? $result;
			if (!is_array($rows) || !array_is_list($rows)) throw new RuntimeException('Invalid ALBIS rates');
			$rates = [];
			foreach ($rows as $row) {
				if (!is_array($row)) throw new RuntimeException('Invalid ALBIS rate');
				$term = filter_var($row['laufzeit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 240]]);
				$rate = self::amount($row['rate'] ?? null);
				$insurance = self::amount($row['versicherung'] ?? null);
				$final = array_key_exists('schlusszahlung', $row) ? self::amount($row['schlusszahlung']) : 0.0;
				if (!$term || $rate === null || $rate <= 0 || $insurance === null || $final === null || isset($rates[$term])) throw new RuntimeException('Incomplete ALBIS rate');
				$rate = round($rate, 2);
				$insurance = round($insurance, 2);
				$rates[$term] = ['term' => $term, 'rate' => $rate, 'insurance' => $insurance, 'final' => round($final, 2), 'monthly' => round($rate + $insurance, 2)];
			}
			usort($rates, static fn(array $a, array $b): int => ($a['monthly'] <=> $b['monthly']) ?: ($b['term'] <=> $a['term']));
			$quote = $rates ? [
				'rates' => $rates, 'price' => $price, 'fee' => round((float)$this->context->get('albisfeenet'), 2),
				'paymentMonths' => (int)$payment['monate'], 'contract' => (string)($contract['bezeichnung'] ?? ''),
			] : [];
			$this->cache->save($quote, $key, [], $quote ? 900 : 300);
			return $quote ?: null;
		} catch (Throwable $e) {
			// Never log HTTP exceptions: requests and responses can contain credentials.
			try {
				$this->cache->save(true, $this->key('unavailable'), [], 60);
				if ($key !== '') $this->cache->save([], $key, [], 60);
			} catch (Throwable $ignored) {}
			return null;
		}
	}

	private function ready(): bool
	{
		return !empty($this->credentials['client_id']) && !empty($this->credentials['client_secret'])
			&& strlen((string)base64_decode($this->credentials['cache_key'] ?? '', true)) === 32;
	}

	private function key(string $kind, array $params = []): string
	{
		return $this->scope . '_' . $kind . '_' . hash('sha256', json_encode($params));
	}

	private function token(): string
	{
		$key = $this->key('token');
		$secret = base64_decode($this->credentials['cache_key'], true);
		$cached = $this->cache->load($key);
		if (is_array($cached) && isset($cached['iv'], $cached['tag'], $cached['cipher'])) {
			$token = openssl_decrypt($cached['cipher'], 'aes-256-gcm', $secret, OPENSSL_RAW_DATA, $cached['iv'], $cached['tag'], $this->scope);
			if (is_string($token) && $token !== '') return $token;
		}
		$result = $this->request('POST', '/v1/auth/request_session_token', array_intersect_key($this->credentials, array_flip(['client_id', 'client_secret'])));
		if (array_is_list($result)) $result = $result[0] ?? [];
		$token = $result['access_token'] ?? null;
		if (!is_string($token) || !preg_match('/^[A-Za-z0-9_.-]+$/D', $token)) throw new RuntimeException('Invalid ALBIS token');
		$parts = explode('.', $token);
		$claims = count($parts) === 3 ? json_decode((string)base64_decode(strtr($parts[1], '-_', '+/'), true), true) : null;
		$lifetime = isset($claims['exp']) && is_numeric($claims['exp']) ? min(600, (int)$claims['exp'] - time() - 30) : 60;
		if ($lifetime <= 0) throw new RuntimeException('Expired ALBIS token');
		$iv = random_bytes(12);
		$tag = '';
		$cipher = openssl_encrypt($token, 'aes-256-gcm', $secret, OPENSSL_RAW_DATA, $iv, $tag, $this->scope);
		if ($cipher === false) throw new RuntimeException('Cannot cache ALBIS token');
		$this->cache->save(['iv' => $iv, 'tag' => $tag, 'cipher' => $cipher], $key, [], $lifetime);
		return $token;
	}

	private function masters(): array
	{
		$key = $this->key('masters');
		$result = $this->cache->load($key);
		if ($result !== false) return $result;
		$token = $this->token();
		$result = [
			'groups' => $this->request('POST', '/v1/shop/get_product_groups', null, $token),
			'contracts' => $this->request('GET', '/v1/shop/contract_types', null, $token),
			'payments' => $this->request('GET', '/v1/shop/get_payment_modes', null, $token),
		];
		foreach ($result as $rows) {
			if (!array_is_list($rows)) throw new RuntimeException('Invalid ALBIS master data');
		}
		$this->cache->save($result, $key, [], 3600);
		return $result;
	}

	private function request(string $method, string $path, ?array $body = null, ?string $token = null): array
	{
		$client = new Zend_Http_Client(self::URLS[$this->context->get('albisenvironment', 'test')] . $path, [
			'adapter' => $this->adapter ?? 'Zend_Http_Client_Adapter_Curl', 'timeout' => 2, 'request_timeout' => 3,
			'maxredirects' => 0, 'keepalive' => false,
			'curloptions' => [CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_CONNECTTIMEOUT_MS => 2000, CURLOPT_TIMEOUT_MS => 3000],
		]);
		$client->setHeaders('Accept', 'application/json');
		if ($token !== null) $client->setHeaders('Authorization', 'Bearer ' . $token);
		if ($body !== null) $client->setRawData(json_encode($body, JSON_THROW_ON_ERROR), 'application/json');
		$response = $client->request($method);
		if ($response->getStatus() === 401) $this->cache->remove($this->key('token'));
		if (!$response->isSuccessful()) throw new RuntimeException('ALBIS unavailable');
		$data = json_decode($response->getBody(), true, 32, JSON_THROW_ON_ERROR);
		if (!is_array($data)) throw new RuntimeException('Invalid ALBIS response');
		return $data;
	}

	private static function find(array $rows, int $id): ?array
	{
		foreach ($rows as $row) {
			if (is_array($row) && (int)($row['id'] ?? 0) === $id) return $row;
		}
		return null;
	}

	private static function amount($value): ?float
	{
		return is_numeric($value) && is_finite((float)$value) && (float)$value >= 0 && (float)$value <= 99999999 ? (float)$value : null;
	}
}
