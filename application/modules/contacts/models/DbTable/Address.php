<?php

class Contacts_Model_DbTable_Address extends Zend_Db_Table_Abstract
{

	protected $_name = 'address';

	public function getAddress($contactid)
	{
		$contactid = (int)$contactid;
		$row = $this->fetchAll('contactid = ' . $contactid);
		if(!$row) {
			throw new Exception("Could not find row $contactid");
		}
		return $row->toArray();
	}

	public function addAddress($data)
	{
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateAddress($id, $data)
	{
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteAddress($id)
	{
		$this->delete('id =' . (int)$id);
	}
}
