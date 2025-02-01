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

	public function getPage($id)
	{
		$id = (int)$id;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('id = ?', $id);
		$data = $this->fetchRow($where);
		return $data ? $data->toArray() : $data;
	}

	public function getPageByTitle($title)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('title = ?', $title);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$data = $this->fetchRow($where);
		return $data ? $data->toArray() : $data;
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
