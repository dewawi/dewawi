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
				->order('id ASC')
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

		if (!$item) {
			return $this->_pathCache[$key] = null;
		}

		$slug = trim((string)$item['slug'], '/');

		if ($slug === '') {
			return $this->_pathCache[$key] = null;
		}

		$path = $slug;
		$visited = [$key => true];

		while (!empty($item['parentid'])) {
			$parentController = $item['controller'] === 'item' ? 'category' : $item['controller'];
			$parentKey = $parentController . ':' . (int)$item['parentid'];

			if (isset($visited[$parentKey])) {
				return $this->_pathCache[$key] = null;
			}

			$visited[$parentKey] = true;
			$parent = $this->getEntitySlug($parentController, (int)$item['parentid']);

			if (!$parent) {
				return $this->_pathCache[$key] = null;
			}

			$parentSlug = trim((string)$parent['slug'], '/');

			if ($parentSlug === '') {
				return $this->_pathCache[$key] = null;
			}

			$item = $parent;
			$path = $parentSlug . '/' . $path;
		}

		return $this->_pathCache[$key] = $path;
	}

	public function resolvePath(string $path): ?array
	{
		$path = trim($path, '/');

		if ($path === '') {
			return null;
		}

		$segments = explode('/', $path);
		$slug = (string)end($segments);

		if ($slug === '') {
			return null;
		}

		$rows = $this->fetchAll(
			$this->getPublicSelect()
				->where('module = ?', 'shops')
				->where('slug = ?', $slug)
				->order('id ASC')
		);

		$resolved = null;

		foreach ($rows as $row) {
			$item = $row->toArray();

			if (empty($item['controller']) || empty($item['entityid'])) {
				continue;
			}

			if ($this->getPath((string)$item['controller'], (int)$item['entityid']) !== $path) {
				continue;
			}

			if ($resolved !== null) {
				return null;
			}

			$resolved = $item;
		}

		return $resolved;
	}

	public function getSlugs(): Zend_Db_Table_Rowset_Abstract
	{
		return $this->fetchAll(
			$this->getPublicSelect()->order('id ASC')
		);
	}
}
