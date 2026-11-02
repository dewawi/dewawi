<?php

class DEEC_Site_Context
{
	public const ANALYTICS_CONSENT_VERSION = 1;

	protected $site = array();
	protected $domain = array();
	protected $features = array();

	public function __construct(array $site, array $domain = array(), array $features = array())
	{
		$this->site = $site;
		$this->domain = $domain;
		$this->features = $features;
	}

	public function getSite()
	{
		return $this->site;
	}

	public function getSiteId()
	{
		return isset($this->site['id']) ? (int) $this->site['id'] : 0;
	}

	public function getClientId()
	{
		return isset($this->site['clientid']) ? (int) $this->site['clientid'] : 0;
	}

	public function getTitle()
	{
		return isset($this->site['title']) ? $this->site['title'] : '';
	}

	public function getLanguage()
	{
		return isset($this->site['language']) ? $this->site['language'] : 'de';
	}

	public function getTimezone()
	{
		return isset($this->site['timezone']) ? $this->site['timezone'] : 'Europe/Berlin';
	}

	public function isIndexable()
	{
		return !array_key_exists('indexable', $this->domain) || (bool)$this->domain['indexable'];
	}

	public function getCanonicalBaseUrl(): ?string
	{
		$parts = self::parseCanonicalUrl((string)($this->site['url'] ?? ''));

		return $parts['url'] ?? null;
	}

	public function getCanonicalOrigin(): ?string
	{
		$parts = self::parseCanonicalUrl((string)($this->site['url'] ?? ''));

		return $parts['origin'] ?? null;
	}

	public function getBasePath(): string
	{
		$parts = self::parseCanonicalUrl((string)($this->site['url'] ?? ''));

		return (string)($parts['path'] ?? '');
	}

	public static function parseCanonicalUrl(string $url): ?array
	{
		$url = trim($url);
		if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return null;

		$parts = parse_url($url);
		if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) return null;

		$scheme = strtolower((string)($parts['scheme'] ?? ''));
		$host = rtrim(strtolower(trim((string)($parts['host'] ?? ''), '[]')), '.');
		$port = isset($parts['port']) ? (int)$parts['port'] : null;
		if (!in_array($scheme, ['http', 'https'], true) || !self::isValidHost($host) || ($port !== null && ($port < 1 || $port > 65535))) return null;
		if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) $port = null;

		$path = (string)($parts['path'] ?? '');
		$decodedPath = rawurldecode($path);
		if (($path !== '' && $path[0] !== '/') || preg_match('~%(?:25)~i', $path) || preg_match('~%(?![0-9a-f]{2})~i', $path)
			|| str_contains($decodedPath, '//') || str_contains($decodedPath, '\\')
			|| preg_match('/[\x00-\x20\x7f]/', $decodedPath)
			|| preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $decodedPath)) return null;

		$path = $path === '/' ? '' : rtrim($path, '/');
		$authority = self::formatHost($host) . ($port !== null ? ':' . $port : '');
		$origin = $scheme . '://' . $authority;

		return [
			'url' => $origin . $path,
			'origin' => $origin,
			'scheme' => $scheme,
			'host' => $host,
			'port' => $port,
			'path' => $path,
		];
	}

	public static function parseAuthority(string $authority): ?array
	{
		$authority = strtolower(trim($authority));
		if ($authority === '' || preg_match('/[\x00-\x20\x7f\/@?#]/', $authority)) return null;

		$host = '';
		$port = null;
		if ($authority[0] === '[') {
			if (!preg_match('/^\[([^\]]+)\](?::([0-9]+))?$/D', $authority, $match)) return null;
			$host = $match[1];
			$port = isset($match[2]) && $match[2] !== '' ? (int)$match[2] : null;
			if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return null;
		} else {
			if (substr_count($authority, ':') > 1 || !preg_match('/^([^:]+)(?::([0-9]+))?$/D', $authority, $match)) return null;
			$host = rtrim($match[1], '.');
			$port = isset($match[2]) && $match[2] !== '' ? (int)$match[2] : null;
			if (!self::isValidHost($host)) return null;
		}

		if ($port !== null && ($port < 1 || $port > 65535)) return null;

		return ['host' => $host, 'port' => $port];
	}

	public static function normalizeOrigin(string $url): ?string
	{
		$url = trim($url);
		if ($url === '' || preg_match('/[\x00-\x1f\x7f]/', $url)) return null;

		$parts = parse_url($url);
		if (!is_array($parts)) return null;

		$scheme = strtolower((string)($parts['scheme'] ?? ''));
		$host = strtolower(trim((string)($parts['host'] ?? ''), '[]'));
		$port = isset($parts['port']) ? (int)$parts['port'] : null;
		if (!in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass']) || !self::isValidHost($host)) return null;
		if ($port !== null && ($port < 1 || $port > 65535)) return null;
		if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) $port = null;

		return $scheme . '://' . self::formatHost($host) . ($port !== null ? ':' . $port : '');
	}

	private static function isValidHost(string $host): bool
	{
		if ($host === '' || strlen($host) > 253) return false;
		if (filter_var($host, FILTER_VALIDATE_IP)) return true;

		return (bool)preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $host);
	}

	private static function formatHost(string $host): string
	{
		return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '[' . $host . ']' : $host;
	}

	public function getTheme()
	{
		$theme = strtolower(trim((string)($this->site['theme'] ?? '')));

		return $theme !== '' && preg_match('/^[a-z0-9_-]+$/', $theme) ? $theme : 'default';
	}

	public function hasAnalytics(): bool
	{
		return trim((string)($this->site['analytics'] ?? '')) !== '';
	}

	public function getAnalyticsEventInterface(): ?string
	{
		$analytics = (string)($this->site['analytics'] ?? '');

		if (stripos($analytics, 'googletagmanager.com/gtag/js') !== false
			&& preg_match('/\bgtag\s*\(/i', $analytics)) {
			return 'gtag';
		}

		if (stripos($analytics, 'googletagmanager.com/gtm.js') !== false
			&& stripos($analytics, 'dataLayer') !== false) {
			return 'dataLayer';
		}

		return null;
	}

	public function getAnalyticsConsentCookieName(): string
	{
		return 'dw_site_consent_' . $this->getClientId() . '_' . $this->getSiteId();
	}

	public function getAnalyticsConsentValue(string $state): ?string
	{
		if ($this->getClientId() <= 0 || $this->getSiteId() <= 0) return null;
		if (!in_array($state, ['accepted', 'declined'], true)) return null;

		return self::ANALYTICS_CONSENT_VERSION . ':' . $state . ':' . $this->getClientId() . ':' . $this->getSiteId();
	}

	public function getAnalyticsConsentState(?array $cookies = null): ?string
	{
		$cookies = $cookies ?? $_COOKIE;
		$value = (string)($cookies[$this->getAnalyticsConsentCookieName()] ?? '');

		foreach (['accepted', 'declined'] as $state) {
			$expected = $this->getAnalyticsConsentValue($state);
			if ($expected !== null && hash_equals($expected, $value)) return $state;
		}

		return null;
	}

	public function canLoadAnalytics(?array $cookies = null, ?string $environment = null): bool
	{
		$environment = $environment ?? (defined('APPLICATION_ENV') ? APPLICATION_ENV : 'production');

		return $environment === 'production'
			&& $this->isIndexable()
			&& $this->hasAnalytics()
			&& $this->getAnalyticsConsentState($cookies) === 'accepted';
	}

	public function hasFeature($featureKey)
	{
		return in_array($featureKey, $this->features, true);
	}

	public function getFeatures()
	{
		return $this->features;
	}

	public function get($key, $default = null)
	{
		return array_key_exists($key, $this->site) ? $this->site[$key] : $default;
	}
}
