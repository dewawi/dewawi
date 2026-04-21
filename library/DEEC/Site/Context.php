<?php

class DEEC_Site_Context
{
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

	public function getTheme()
	{
		if (!empty($this->site['theme'])) {
			return $this->site['theme'];
		}

		return 'default';
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
