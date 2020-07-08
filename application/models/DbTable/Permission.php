<?php

class Application_Model_DbTable_Permission extends Zend_Db_Table_Abstract
{

	protected $_name = 'permission';

	protected $_date = null;

	protected $_user = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
	    $this->_user = Zend_Registry::get('User');
	}

	public function getPermissions()
	{
		$id = (int)$this->_user['id'];
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}
}
