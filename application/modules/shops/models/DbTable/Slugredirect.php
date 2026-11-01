<?php

class Shops_Model_DbTable_Slugredirect extends DEEC_Model_DbTable_SiteEntity
{
	public const STATUS_REDIRECT = 'redirect';
	public const STATUS_GONE = 'gone';

	protected $_name = 'slugredirect';
	protected ?string $orderingField = null;

	public function resolve(string $path): ?array
	{
		$path = trim($path, '/');

		if (!DEEC_Site_Router::isRedirectablePath($path)) return null;

		$row = $this->fetchRow(
			$this->getPublicSelect()
				->where('pathhash = ?', hash('sha256', $path))
				->where('path = ?', $path)
				->where('module = ?', 'shops')
				->order('id ASC')
				->limit(1)
		);

		if (!$row) return null;

		$redirect = $row->toArray();
		$status = (string)($redirect['status'] ?? self::STATUS_REDIRECT);
		if ($status === self::STATUS_GONE) return ['status' => self::STATUS_GONE, 'target' => null];
		if ($status !== self::STATUS_REDIRECT) return null;
		$controller = (string)$redirect['controller'];

		if (!in_array($controller, ['page', 'category', 'item', 'tag', 'inquiry'], true)) return null;
		if (in_array($controller, ['category', 'item', 'tag'], true) && !$this->_siteContext->hasFeature('catalog')) return null;
		if ($controller === 'inquiry' && !$this->_siteContext->hasFeature('inquiry')) return null;

		$target = (new Shops_Model_DbTable_Slug())->getPublicPath(
			$controller,
			(int)$redirect['entityid']
		);

		if (!$target || $target === $path || !DEEC_Site_Router::isRedirectablePath($target)) return null;

		return ['status' => self::STATUS_REDIRECT, 'target' => $target];
	}

	public function resolvePath(string $path): ?string
	{
		$result = $this->resolve($path);

		return $result && $result['status'] === self::STATUS_REDIRECT ? $result['target'] : null;
	}
}
