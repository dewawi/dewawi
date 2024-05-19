<?php

class Admin_Model_DbTable_Shopcategory extends Zend_Db_Table_Abstract
{

	protected $_name = 'shops_categories';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getShopcategory($id)
	{
		$id = (int)$id;
		$select = $this->select()
		               ->setIntegrityCheck(false)
		               ->from(array('c' => 'category'))
		               ->joinLeft(array('sc' => 'shops_categories'), 'c.id = sc.catid', array('id as shopcatid', 'slug AS shop_slug', 'description AS shop_description', 'shortdescription AS shop_shortdescription', 'minidescription AS shop_minidescription', 'header AS shop_header', 'footer AS shop_footer'))
		               ->where('c.id = ?', $id);

		$row = $this->fetchRow($select);
		if (!$row) {
		    throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getShopcategories($shopid)
	{
		$select = $this->select()
		               ->setIntegrityCheck(false)
		               ->from(array('c' => 'category'))
		               ->joinLeft(array('sc' => 'shops_categories'), 'c.id = sc.catid', array('slug'))
		               ->where('c.type = ?', 'shop')
		               ->where('c.clientid = ?', $this->_client['id'])
		               ->where('c.shopid = ?', $shopid)
		               ->where('c.deleted = ?', 0);

		if ($parentid !== null) {
		    $select->where('c.parentid = ?', $parentid);
		}

		$select->order('c.ordering');

		$data = $this->fetchAll($select);

		$categories = array();
		foreach($data as $category) {
			if(!$category->parentid) {
				$categories[$category->id]['id'] = $category->id;
				$categories[$category->id]['type'] = $category->type;
				$categories[$category->id]['title'] = $category->title;
				$categories[$category->id]['slug'] = $category->slug;
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
				$categories[$category->id]['type'] = $category->type;
				$categories[$category->id]['title'] = $category->title;
				$categories[$category->id]['slug'] = $category->slug;
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

	public function addShopcategory($data, $clientid = 0)
	{
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		if($clientid) {
			$data['clientid'] = $clientid;
		} else {
			$data['clientid'] = $this->_client['id'];
		}
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateShopcategory($id, $data)
	{
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$this->update($data, 'id = '. (int)$id);
	}

	public function sortShopcategory($id, $ordering)
	{
		$data = array();
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$data['ordering'] = $ordering;
		$this->update($data, 'id = '. (int)$id);
	}

	public function lock($id)
	{
		$data = array();
		$data['locked'] = $this->_user['id'];
		$data['lockedtime'] = $this->_date;
		$this->update($data, 'id = '. (int)$id);
	}

	public function unlock($id)
	{
		$data = array();
		$data['locked'] = 0;
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteShopcategory($id)
	{
		$data = array();
		$data['deleted'] = 1;
		$this->update($data, 'id =' . (int)$id);
	}
}
