<?php

class Shops_Model_DbTable_Item extends Zend_Db_Table_Abstract
{

	protected $_name = 'item';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getItem($id, $shopid)
	{
		$id = (int)$id;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('id = ?', $id);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', $shopid);
		$data = $this->fetchRow($where);
		return $data ? $data->toArray() : $data;
	}

	public function getItems($ids)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('sku IN (?)', $ids);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$data = $this->fetchAll($where);
		if (!$row) {
			throw new Exception("Could not find row $ids");
		}
		return $row->toArray();
	}
}
