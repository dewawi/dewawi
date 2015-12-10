<?php

class Application_Model_DbTable_Client extends Zend_Db_Table_Abstract
{

	protected $_name = 'client';

	public function getClient($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}
}
