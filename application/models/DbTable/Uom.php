<?php

class Application_Model_DbTable_Uom extends Zend_Db_Table_Abstract
{

	protected $_name = 'uom';

	public function getUom($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}
}
