<?php

class Contacts_Model_DbTable_Email extends Zend_Db_Table_Abstract
{

	protected $_name = 'email';

	public function getEmail($contactid)
	{
		$contactid = (int)$contactid;
		$row = $this->fetchAll('contactid = ' . $contactid);
		if(!$row) {
			throw new Exception("Could not find row $contactid");
		}
		return $row->toArray();
	}

	public function addEmail($contactid, $email, $ordering)
	{
		$data = array(
			'contactid' => $contactid,
			'email' => $email,
			'ordering' => $ordering
		);
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateEmail($id, $email, $ordering)
	{
		$data = array(
			'email' => $email,
			'ordering' => $ordering
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteEmail($id)
	{
		$this->delete('id =' . (int)$id);
	}
}
