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
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('id = ?', (int)$id);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', (int)$shopid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', (int)$this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('shopenabled = ?', 1);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		$data = $this->fetchRow($where);

		return $data ? $data->toArray() : null;
	}

	public function getItemBySku($sku, $shopid)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('sku = ?', $sku);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', (int)$shopid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', (int)$this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('shopenabled = ?', 1);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		$data = $this->fetchRow($where);

		return $data ? $data->toArray() : null;
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
