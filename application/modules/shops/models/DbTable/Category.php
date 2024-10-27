<?php

class Shops_Model_DbTable_Category extends Zend_Db_Table_Abstract
{

	protected $_name = 'category';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		//$this->_user = Zend_Registry::get('User');
		//$this->_client = Zend_Registry::get('Client');
	}

	public function getCategory($id, $shopid)
	{
		$id = (int)$id;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('id = ?', $id);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', $shopid);
		$data = $this->fetchRow($where);
		return $data;
	}

	public function getCategories($type, $shopid, $parentid = null)
	{
		// Prepare the where conditions
		$where = [];
		if ($parentid !== null) {
			$where[] = $this->getAdapter()->quoteInto('parentid = ?', $parentid);
		}
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', $shopid);
		$where[] = $this->getAdapter()->quoteInto('type = ?', $type);
		$where[] = $this->getAdapter()->quoteInto('activated = ?', 1);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		// Fetch the data
		$data = $this->fetchAll($where, 'ordering');

		// Initialize categories array
		$categories = [];

		// Iterate through the data
		foreach ($data as $category) {
			// Prepare the category array
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
				'shopid' => isset($category->shopid) ? $category->shopid : null,
				//'shopcatid' => isset($category->shopcatid) ? $category->shopcatid : null
			];
		}
		// If the category has a parent, add it to the parent's 'childs' array
		foreach ($data as $category) {
			if ($category->parentid && isset($categories[$category->parentid])) {
				$categories[$category->parentid]['childs'][] = $category->id;
			}
		}

		return $categories;
	}
}
