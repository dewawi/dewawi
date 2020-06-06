<?php

class Application_Model_DbTable_Paymentmethod extends Zend_Db_Table_Abstract
{

	protected $_name = 'paymentmethod';

	public function getPaymentmethod($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}
}
