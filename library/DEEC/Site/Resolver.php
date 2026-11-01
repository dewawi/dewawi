<?php

class DEEC_Site_Resolver
{
	public function resolveByHost($host, array $options = array())
	{
		$authority = DEEC_Site_Context::parseAuthority((string)$host);
		if ($authority === null) return null;

		$matches = array();

		foreach ($this->getActiveSites() as $shop) {
			$shopData = is_array($shop) ? $shop : $shop->toArray();
			$canonical = DEEC_Site_Context::parseCanonicalUrl((string)($shopData['url'] ?? ''));

			if ($canonical !== null && $this->authorityMatches($authority, $canonical)) $matches[] = [$shopData, $canonical];
		}
		if (array_key_exists('requestPath', $options)) $matches = $this->matchRequestPath($matches, (string)$options['requestPath']);

		if (count($matches) !== 1) return null;

		[$shopData, $canonical] = $matches[0];

		return new DEEC_Site_Context($shopData, array(
			'host' => $authority['host'],
			'port' => $authority['port'],
			'canonicalHost' => $canonical['host'],
			'canonicalPort' => $canonical['port'],
			'indexable' => $this->isIndexable($shopData, $options),
		), $this->detectFeatures($shopData));
	}

	protected function getActiveSites()
	{
		return (new Zend_Db_Table('shop'))->fetchAll(array('activated = ?' => 1));
	}

	protected function authorityMatches(array $request, array $canonical): bool
	{
		if ($request['host'] !== $canonical['host']) return false;
		if ($canonical['port'] !== null) return $request['port'] === $canonical['port'];

		return $request['port'] === null || in_array($request['port'], [80, 443], true);
	}

	protected function matchRequestPath(array $matches, string $requestPath): array
	{
		$decodedPath = rawurldecode($requestPath);
		if ($decodedPath === '' || $decodedPath[0] !== '/' || str_starts_with($decodedPath, '//') || str_contains($decodedPath, '\\')
			|| preg_match('/[\x00-\x1f\x7f]/', $decodedPath)
			|| preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $decodedPath)) return [];

		$decodedPath = $decodedPath === '/' ? '/' : rtrim($decodedPath, '/');
		$matched = [];
		$length = -1;
		foreach ($matches as $match) {
			$basePath = rawurldecode((string)$match[1]['path']);
			if ($basePath !== '' && $decodedPath !== $basePath && !str_starts_with($decodedPath, $basePath . '/')) continue;

			$baseLength = strlen($basePath);
			if ($baseLength > $length) {
				$matched = [$match];
				$length = $baseLength;
			} elseif ($baseLength === $length) {
				$matched[] = $match;
			}
		}

		return $matched;
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

	protected function isIndexable(array $site, array $options)
	{
		$siteIndexable = array_key_exists('indexable', $site) ? (bool)$site['indexable'] : true;

		return ($options['environment'] ?? 'production') === 'production'
			&& $siteIndexable;
	}

}
