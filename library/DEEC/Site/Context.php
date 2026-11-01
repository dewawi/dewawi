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
		$url = trim((string)($this->site['url'] ?? ''));

		if (!filter_var($url, FILTER_VALIDATE_URL)) return null;

		$parts = parse_url($url);
		if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) return null;
		if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) return null;

		return rtrim($url, '/');
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
