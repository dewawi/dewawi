<?php

class Shops_Model_DbTable_Slide extends Zend_Db_Table_Abstract
{

	protected $_name = 'slide';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getSlide($id)
	{
		$id = (int)$id;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('id = ?', $id);
		$data = $this->fetchRow($where);
		return $data ? $data->toArray() : $data;
	}

	public function getSlides($shopid)
	{
		$shopid = (int)$shopid;

		$where = array();
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', $shopid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'ordering');

		return $data;
	}
}
