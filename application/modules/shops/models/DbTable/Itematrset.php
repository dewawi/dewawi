<?php

class Shops_Model_DbTable_Itematrset extends Zend_Db_Table_Abstract
{

	protected $_name = 'itematrset';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		//$this->_user = Zend_Registry::get('User');
		//$this->_client = Zend_Registry::get('Client');
	}

	public function itemAttributeSets($parentid)
	{
		$parentid = (int)$parentid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('parentid = ?', $parentid);
		//$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'ordering');
		if (!$data) {
			throw new Exception("Could not find row $parentid");
		}
		return $data;
	}
}
