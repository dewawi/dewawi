<?php

class Shops_Model_DbTable_Itematr extends Zend_Db_Table_Abstract
{

	protected $_name = 'itematr';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getPositions($parentid, $setid = null)
	{
		$parentid = (int)$parentid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('parentid = ?', $parentid);
		if($setid !== null) $where[] = $this->getAdapter()->quoteInto('atrsetid = ?', $setid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'ordering');
		if (!$data) {
			throw new Exception("Could not find row $parentid");
		}
		return $data;
	}

	public function getPositionsBySku($sku, $setid = null)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('sku = ?', $sku);
		if($setid !== null) $where[] = $this->getAdapter()->quoteInto('atrsetid = ?', $setid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'ordering');
		if (!$data) {
			throw new Exception("Could not find row $parentid");
		}
		return $data;
	}

	public function getPositionsByTitle($title, $setid = null)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('title = ?', $title);
		if($setid !== null) $where[] = $this->getAdapter()->quoteInto('atrsetid = ?', $setid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'ordering');
		if (!$data) {
			throw new Exception("Could not find row $parentid");
		}
		return $data;
	}
}
