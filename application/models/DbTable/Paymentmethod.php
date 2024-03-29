<?php

class Application_Model_DbTable_Paymentmethod extends Zend_Db_Table_Abstract
{

	protected $_name = 'paymentmethod';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getPaymentmethods()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$data = $this->fetchAll($where);

		$paymentmethods = array();
		foreach($data as $paymentmethod) {
			$paymentmethods[$paymentmethod->title] = $paymentmethod->title;
		}
		return $paymentmethods;
	}
}
