<?php

class Application_Model_DbTable_Shippingmethod extends Zend_Db_Table_Abstract
{

	protected $_name = 'shippingmethod';

	protected $_date = null;

	protected $_user = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
	}

	public function getShippingmethods()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_user['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);

		$shippingmethods = array();
		foreach($data as $shippingmethod) {
			$shippingmethods[$shippingmethod->title] = $shippingmethod->title;
		}
		return $shippingmethods;
	}
}
