<?php

class Contacts_Model_DbTable_Email extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'email';

	public function getEmail($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if(!$row) return false;
		return $row->toArray();
	}
}
