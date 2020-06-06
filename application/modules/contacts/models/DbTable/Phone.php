<?php

class Contacts_Model_DbTable_Phone extends Zend_Db_Table_Abstract
{

	protected $_name = 'phone';

	public function getPhone($contactid)
	{
		$contactid = (int)$contactid;
		$row = $this->fetchAll('contactid = ' . $contactid);
		if(!$row) {
			throw new Exception("Could not find row $contactid");
		}
		return $row->toArray();
	}

	public function addPhone($contactid, $type, $phone, $ordering)
	{
		$data = array(
			'contactid' => $contactid,
			'type' => $type,
			'phone' => $phone,
			'ordering' => $ordering
		);
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updatePhone($id, $data)
	{
		$this->update($data, 'id = '. (int)$id);
	}

	public function deletePhone($id)
	{
		$data = array(
			'deleted' => 1
		);
		$this->update($data, 'id =' . (int)$id);
	}
}
