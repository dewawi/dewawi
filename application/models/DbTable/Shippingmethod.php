<?php

class Application_Model_DbTable_Shippingmethod extends Zend_Db_Table_Abstract
{

	protected $_name = 'shippingmethod';

	public function getShippingmethod($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}
}
