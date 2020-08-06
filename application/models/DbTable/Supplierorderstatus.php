<?php

class Application_Model_DbTable_Supplierorderstatus extends Zend_Db_Table_Abstract
{

	protected $_name = 'supplierorderstatus';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
	    $this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getSupplierorderstatus()
	{
		$supplierorderstatus = array();
		$supplierorderstatus['supplierNotOrdered'] = 'PROCESSES_SUPPLIER_NOT_ORDERED';
		$supplierorderstatus['supplierOrdered'] = 'PROCESSES_SUPPLIER_ORDERED';
		$supplierorderstatus['supplierPayed'] = 'PROCESSES_SUPPLIER_PAYED';
		return $supplierorderstatus;
	}
}
