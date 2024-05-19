<?php

class Shops_Model_DbTable_Item extends Zend_Db_Table_Abstract
{

	protected $_name = 'item';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		//$this->_user = Zend_Registry::get('User');
		//$this->_client = Zend_Registry::get('Client');
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

	public function getItemBySlug($slug, $shopid)
	{
		$shopid = (int)$shopid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('slug = ?', $slug);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', $shopid);
		//$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$data = $this->fetchRow($where);
		return $data ? $data->toArray() : $data;
	}

	public function getItems($ids)
	{
		$where = $this->getAdapter()->quoteInto('sku IN (?)', $ids);
		$data = $this->fetchAll($where);
		if (!$row) {
			throw new Exception("Could not find row $ids");
		}
		return $row->toArray();
	}

	public function addItem($data)
	{
		$data['clientid'] = 100;
		$data['created'] = $this->_date;
		$data['createdby'] = 1;
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function deleteItem($id)
	{
		$id = (int)$id;
		$data = array('deleted' => 1);
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	public function deleteItemByItemId($itemid)
	{
		$itemid = (int)$itemid;
		$data = array('deleted' => 1);
		$where = $this->getAdapter()->quoteInto('itemid = ?', $itemid);
		$this->update($data, $where);
	}
}
