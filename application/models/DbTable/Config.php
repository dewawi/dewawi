<?php

class Application_Model_DbTable_Config extends Zend_Db_Table_Abstract
{

	protected $_name = 'config';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getConfig()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$row = $this->fetchRow($where);
		return $row->toArray();
	}
}
