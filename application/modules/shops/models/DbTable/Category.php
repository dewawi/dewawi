<?php

class Shops_Model_DbTable_Category extends Zend_Db_Table_Abstract
{

	protected $_name = 'category';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getCategory($id)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('id = ?', (int)$id);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', (int)$this->_shop['id']);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', (int)$this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('type = ?', 'shop');
		$where[] = $this->getAdapter()->quoteInto('activated = ?', 1);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		return $this->fetchRow($where);
	}

	public function getCategories($parentid = null)
	{
		$where = [];

		if ($parentid !== null) {
			$where[] = $this->getAdapter()->quoteInto('parentid = ?', (int)$parentid);
		}

		$where[] = $this->getAdapter()->quoteInto('shopid = ?', (int)$this->_shop['id']);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', (int)$this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('type = ?', 'shop');
		$where[] = $this->getAdapter()->quoteInto('activated = ?', 1);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		$data = $this->fetchAll($where, 'ordering');

		$categories = [];

		foreach ($data as $category) {
			$categories[$category->id] = [
				'id' => $category->id,
				'type' => $category->type,
				'title' => $category->title,
				'subtitle' => $category->subtitle,
				'image' => $category->image,
				'description' => $category->description,
				'minidescription' => $category->minidescription,
				'shortdescription' => $category->shortdescription,
				'footer' => $category->footer,
				'parentid' => $category->parentid,
				'ordering' => $category->ordering,
				'activated' => $category->activated,
				'shopid' => $category->shopid ?? null,
			];
		}

		foreach ($data as $category) {
			if ($category->parentid && isset($categories[$category->parentid])) {
				$categories[$category->parentid]['childs'][] = $category->id;
			}
		}

		return $categories;
	}
}
