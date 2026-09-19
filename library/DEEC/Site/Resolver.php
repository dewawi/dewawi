<?php

class DEEC_Site_Resolver
{
	public function resolveByHost($host)
	{
		$host = $this->normalizeHost($host);

		if ($host === '') {
			return null;
		}

		$shopsTable = new Zend_Db_Table('shop');
		$shops = $shopsTable->fetchAll(array('activated = ?' => 1));

		foreach ($shops as $shop) {
			$shopData = $shop->toArray();
			$shopHost = $this->normalizeHost(parse_url($shopData['url'], PHP_URL_HOST));

			if ($shopHost !== '' && $shopHost === $host) {
				$features = $this->detectFeatures($shopData);

				return new DEEC_Site_Context($shopData, array('host' => $host), $features);
			}
		}

		return null;
	}

	protected function detectFeatures(array $site)
	{
		$features = array('cms');

		if (!empty($site['catalogenabled'])) {
			$features[] = 'catalog';
		}

		if (!empty($site['checkoutenabled'])) {
			$features[] = 'cart';
			$features[] = 'checkout';
		}

		if (!empty($site['contactenabled'])) {
			$features[] = 'contact';
		}

		if (!empty($site['inquiryenabled'])) {
			$features[] = 'inquiry';
		}

		return $features;
	}

	protected function normalizeHost($host)
	{
		$host = strtolower(trim((string) $host));
		$host = preg_replace('/:\d+$/', '', $host);
		$host = preg_replace('/^www\./', '', $host);

		return $host;
	}
}
