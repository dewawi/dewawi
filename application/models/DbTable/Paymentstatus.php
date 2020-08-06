<?php

class Application_Model_DbTable_Paymentstatus extends Zend_Db_Table_Abstract
{

	protected $_name = 'paymentstatus';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
	    $this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getPaymentstatus()
	{
		$paymentstatus = array(
			'waitingForPayment' => 'PROCESSES_WAITING_FOR_PAYMENT',
			'prepaymentReceived' => 'PROCESSES_PREPAYMENT_RECEIVED',
			'paymentCompleted' => 'PROCESSES_PAYMENT_COMPLETED'
		);
		return $paymentstatus;
	}
}
