<?php

class Application_Controller_Action_Helper_SupplierOrderStatus extends Zend_Controller_Action_Helper_Abstract
{
	public function getSupplierOrderStatus()
	{
		$supplierorderstatus = array();
		$supplierorderstatus['supplierNotOrdered'] = 'PROCESSES_SUPPLIER_NOT_ORDERED';
		$supplierorderstatus['supplierOrdered'] = 'PROCESSES_SUPPLIER_ORDERED';
		$supplierorderstatus['supplierPayed'] = 'PROCESSES_SUPPLIER_PAYED';
		return $supplierorderstatus;
	}
}
