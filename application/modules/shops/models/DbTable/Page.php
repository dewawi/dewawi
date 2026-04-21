<?php

class Shops_Model_DbTable_Page extends Zend_Db_Table_Abstract
{

	protected $_name = 'page';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getPage($id, $shopid)
	{
		$select = $this->select()
			->where('id = ?', $id)
			->where('shopid = ?', $shopid)
			->where('deleted = ?', 0)
			->limit(1);

		$row = $this->fetchRow($select);

		return $row ? $row->toArray() : null;
	}

	public function getPageByType($type, $shopid)
	{
		$select = $this->select()
			->where('type = ?', $type)
			->where('shopid = ?', $shopid)
			->where('deleted = ?', 0)
			->limit(1);

		$row = $this->fetchRow($select);

		return $row ? $row->toArray() : null;
	}

	public function getPages($shopid)
	{
		$shopid = (int)$shopid;

		$where = array();
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', $shopid);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'ordering');

		return $data;
	}
}
