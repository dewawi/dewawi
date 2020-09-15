<?php

class Application_Model_DbTable_Deliverystatus extends Zend_Db_Table_Abstract
{

	protected $_name = 'deliverystatus';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getDeliverystatus()
	{
		$deliverystatus = array();
		$deliverystatus['deliveryIsWaiting'] = 'PROCESSES_DELIVERY_IS_WAITING';
		$deliverystatus['partialDelivered'] = 'PROCESSES_PARTIAL_DElIVERED';
		$deliverystatus['deliveryCompleted'] = 'PROCESSES_DELIVERY_COMPLETED';
		return $deliverystatus;
	}
}
