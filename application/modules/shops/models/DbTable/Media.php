<?php

class Shops_Model_DbTable_Media extends Zend_Db_Table_Abstract
{

	protected $_name = 'media';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		//$this->_user = Zend_Registry::get('User');
		//$this->_client = Zend_Registry::get('Client');
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
		//$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$data = $this->fetchRow($where);
		return $data ? $data->toArray() : $data;
	}
}
