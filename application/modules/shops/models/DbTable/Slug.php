<?php

class Shops_Model_DbTable_Slug extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'slug';
	protected $_pathCache = [];

	public function getEntitySlug(string $controller, int $entityId): ?array
	{
		$row = $this->fetchRow(
			$this->getPublicSelect()
				->where('module = ?', 'shops')
				->where('controller = ?', $controller)
				->where('entityid = ?', $entityId)
				->limit(1)
		);

		return $row ? $row->toArray() : null;
	}

	public function getPath(string $controller, int $entityId): ?string
	{
		$key = $controller . ':' . $entityId;

		if (array_key_exists($key, $this->_pathCache)) {
			return $this->_pathCache[$key];
		}

		$item = $this->getEntitySlug($controller, $entityId);

		if (!$item || empty($item['slug'])) {
			return $this->_pathCache[$key] = null;
		}

		$path = trim($item['slug'], '/');
		$visited = [];

		while (!empty($item['parentid'])) {
			$parentController = $item['controller'] === 'item' ? 'category' : $item['controller'];
			$parentKey = $parentController . ':' . (int)$item['parentid'];

			if (isset($visited[$parentKey])) {
				break;
			}

			$visited[$parentKey] = true;
			$parent = $this->getEntitySlug($parentController, (int)$item['parentid']);

			if (!$parent || empty($parent['slug'])) {
				break;
			}

			$item = $parent;
			$path = trim($item['slug'], '/') . '/' . $path;
		}

		return $this->_pathCache[$key] = $path;
	}

	public function getSlugs(): Zend_Db_Table_Rowset_Abstract
	{
		return $this->fetchAll(
			$this->getPublicSelect()->order('id ASC')
		);
	}
}
