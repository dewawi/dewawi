<?php

class Application_Model_DbTable_Config extends Zend_Db_Table_Abstract
{

	protected $_name = 'config';

	public function getConfig()
	{
		$row = $this->fetchRow();
		if (!$row) {
			throw new Exception("Could not find config row");
		}
		return $row->toArray();
	}
}
