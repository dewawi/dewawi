<?php

class Application_Model_DbTable_Shippingmethod extends Zend_Db_Table_Abstract
{

	protected $_name = 'shippingmethod';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getShippingmethods()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);

		$shippingmethods = array();
		foreach($data as $shippingmethod) {
			$shippingmethods[$shippingmethod->title] = $shippingmethod->title;
		}
		return $shippingmethods;
	}
}
