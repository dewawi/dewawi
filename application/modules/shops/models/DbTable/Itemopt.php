<?php

class Shops_Model_DbTable_Itemopt extends Zend_Db_Table_Abstract
{

	protected $_name = 'itemopt';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function itemOptions($parentid, $setid = null)
	{
		$parentid = (int)$parentid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('parentid = ?', $parentid);
		if($setid !== null) $where[] = $this->getAdapter()->quoteInto('optsetid = ?', $setid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'ordering');
		if (!$data) {
			throw new Exception("Could not find row $parentid");
		}
		return $data;
	}
}
