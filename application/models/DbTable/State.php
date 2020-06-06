<?php

class Application_Model_DbTable_State extends Zend_Db_Table_Abstract
{

	protected $_name = 'state';

	public function getState($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}
}
