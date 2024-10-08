<?php

class Application_Model_DbTable_Category extends Zend_Db_Table_Abstract
{

	protected $_name = 'category';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getCategories($type, $parentid = null)
	{
		$where = array();
		if($parentid !== null) {
			$where[] = $this->getAdapter()->quoteInto('parentid = ?', $parentid);
		}
		$where[] = $this->getAdapter()->quoteInto('type = ?', $type);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'ordering');

		$categories = array();
		foreach($data as $category) {
			if(!$category->parentid) {
				$categories[$category->id]['id'] = $category->id;
				if(isset($category->shopid)) {
					$categories[$category->id]['shopid'] = $category->shopid;
					//$categories[$category->id]['shopcatid'] = $category->shopcatid;
				}
				$categories[$category->id]['type'] = $category->type;
				$categories[$category->id]['title'] = $category->title;
				$categories[$category->id]['image'] = $category->image;
				$categories[$category->id]['description'] = $category->description;
				$categories[$category->id]['footer'] = $category->footer;
				$categories[$category->id]['parentid'] = $category->parentid;
				$categories[$category->id]['ordering'] = $category->ordering;
				if($category->parentid) {
					if(!isset($categories[$category->parentid])) $categories[$category->parentid] = array();
					if(!isset($categories[$category->parentid]['childs'])) $categories[$category->parentid]['childs'] = array();
					array_push($categories[$category->parentid]['childs'], $category->id);
				}
			}
		}
		foreach($data as $category) {
			if($category->parentid) {
				$categories[$category->id]['id'] = $category->id;
				if(isset($category->shopid)) {
					$categories[$category->id]['shopid'] = $category->shopid;
					//$categories[$category->id]['shopcatid'] = $category->shopcatid;
				}
				$categories[$category->id]['type'] = $category->type;
				$categories[$category->id]['title'] = $category->title;
				$categories[$category->id]['image'] = $category->image;
				$categories[$category->id]['description'] = $category->description;
				$categories[$category->id]['footer'] = $category->footer;
				$categories[$category->id]['parentid'] = $category->parentid;
				$categories[$category->id]['ordering'] = $category->ordering;
				if($category->parentid) {
					if(!isset($categories[$category->parentid])) $categories[$category->parentid] = array();
					if(!isset($categories[$category->parentid]['childs'])) $categories[$category->parentid]['childs'] = array();
					array_push($categories[$category->parentid]['childs'], $category->id);
				}
			}
		}
		return $categories;
	}
}
