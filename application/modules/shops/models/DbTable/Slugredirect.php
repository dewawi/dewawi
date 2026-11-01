<?php

class Shops_Model_DbTable_Slugredirect extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'slugredirect';
	protected ?string $orderingField = null;

	public function resolvePath(string $path): ?string
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
		$controller = (string)$redirect['controller'];

		if (!in_array($controller, ['page', 'category', 'item', 'tag', 'inquiry'], true)) return null;
		if (in_array($controller, ['category', 'item', 'tag'], true) && !$this->_siteContext->hasFeature('catalog')) return null;
		if ($controller === 'inquiry' && !$this->_siteContext->hasFeature('inquiry')) return null;

		$target = (new Shops_Model_DbTable_Slug())->getPublicPath(
			$controller,
			(int)$redirect['entityid']
		);

		if (!$target || $target === $path || !DEEC_Site_Router::isRedirectablePath($target)) return null;

		return $target;
	}
}
