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

	public function addAddress($contactid, $type, $street, $postcode, $city, $country, $ordering)
	{
		$data = array(
			'contactid' => $contactid,
			'type' => $type,
			'street' => $street,
			'postcode' => $postcode,
			'city' => $city,
			'country' => $country,
			'ordering' => $ordering
        );
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateAddress($id, $data)
	{
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteAddress($id)
	{
		$data = array(
			'deleted' => 1
		);
		$this->update($data, 'id =' . (int)$id);
	}
}
