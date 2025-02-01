<?php

class Shops_Model_DbTable_Emailtemplate extends Zend_Db_Table_Abstract
{

	protected $_name = 'emailtemplate';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getEmailtemplate($module, $controller)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		$where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$data = $this->fetchRow($where);
		return $data ? $data->toArray() : $data;
	}
}
