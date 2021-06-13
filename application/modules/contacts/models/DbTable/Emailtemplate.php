<?php

class Contacts_Model_DbTable_Emailtemplate extends Zend_Db_Table_Abstract
{

	protected $_name = 'emailtemplate';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getEmailtemplate($module = NULL, $controller = NULL)
	{
		$where = array();
		if($module) $where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		if($controller) $where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchRow($where);
		return $data ? $data->toArray() : $data;
	}
}
