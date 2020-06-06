<?php

class Application_Model_DbTable_Template extends Zend_Db_Table_Abstract
{

	protected $_name = 'template';

	public function getTemplate($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id . ' AND activated = 1');
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getDefaultTemplate()
	{
		$row = $this->fetchRow('`default` = 1 AND `activated` = 1');
		if($row) {
		    return $row->toArray();
		}
	}

	public function getTemplates($clientid)
	{
		$row = $this->fetchAll('clientid = ' . $clientid . ' AND activated = 1');
		if (!$row) {
			throw new Exception("Could not find row $clientid");
		}
		return $row->toArray();
	}
}
