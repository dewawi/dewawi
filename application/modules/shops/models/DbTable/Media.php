<?php

class Shops_Model_DbTable_Media extends Zend_Db_Table_Abstract
{

	protected $_name = 'media';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getItemMedia($items) {
		$images = array();
		foreach($items as $key => $item) {
			$images[$item->id] = $this->getMedia($item->id, 'items', 'item');
		}
		return $images;
	}

	public function getCategoryMedia($categories) {
		$images = array();
		foreach($categories as $key => $category) {
			$images[$category['id']] = $this->getMedia($category['id'], 'shops', 'category');
		}
		//print_r($images);
		return $images;
	}

	public function getMediaByParentID($parentid, $module, $controller)
	{
		$parentid = (int)$parentid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('parentid = ?', $parentid);
		$where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		$where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);
		return $data;
	}

	public function getCategoryMediaById($id) {
		$images = $this->getMedia($id, 'shops', 'category');
		return $images;
	}

	public function getMedia($parentid, $module, $controller)
	{
		$select = $this->select()
			//->from($this->_name)
			->where('parentid = ?', $parentid)
			->where('module = ?', $module)
			->where('controller = ?', $controller)
			->where('deleted = ?', 0);
		$imagesData = $this->fetchAll($select);

		$images = [];
		foreach ($imagesData as $imageData) {
			$image = new stdClass();
			$image->url = $imageData['url'];
			$image->title = $imageData['title'];
			$image->type = $imageData['type'];
			$images[] = $image;
		}

		return $images;
	}

	public function getItem($itemid, $shopid)
	{
		$itemid = (int)$itemid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('itemid = ?', $itemid);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', $shopid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$data = $this->fetchRow($where);
		return $data ? $data->toArray() : $data;
	}
}
