<?php

class Shops_Model_DbTable_Slug extends Zend_Db_Table_Abstract
{

	protected $_name = 'slug';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getSlug($id)
	{
		$id = (int)$id;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('id = ?', $id);
		$data = $this->fetchRow($where);
		return $data ? $data->toArray() : $data;
	}

	public function getEntitySlug($controller, $entityid)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('module = ?', 'shops');
		$where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		$where[] = $this->getAdapter()->quoteInto('entityid = ?', (int)$entityid);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', (int)$this->_shop['id']);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', (int)$this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		$data = $this->fetchRow($where);

		return $data ? $data->toArray() : null;
	}

	public function getPath($controller, $entityid)
	{
		$key = $controller . ':' . (int)$entityid;

		if (array_key_exists($key, $this->_pathCache)) {
			return $this->_pathCache[$key];
		}

		$item = $this->getEntitySlug($controller, $entityid);

		if (!$item || empty($item['slug'])) {
			return $this->_pathCache[$key] = null;
		}

		$path = trim($item['slug'], '/');
		$visited = array();

		while (!empty($item['parentid'])) {
			$parentController = $item['controller'] === 'item' ? 'category' : $item['controller'];
			$parentKey = $parentController . ':' . (int)$item['parentid'];

			if (isset($visited[$parentKey])) {
				break;
			}

			$visited[$parentKey] = true;
			$parent = $this->getEntitySlug($parentController, $item['parentid']);

			if (!$parent || empty($parent['slug'])) {
				break;
			}

			$item = $parent;
			$path = trim($item['slug'], '/') . '/' . $path;
		}

		return $this->_pathCache[$key] = $path;
	}

	public function getSlugs($shopid)
	{
		$shopid = (int)$shopid;

		$where = array();
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', $shopid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'ordering');

		return $data;
	}
}
