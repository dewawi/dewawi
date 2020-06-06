<?php

class Contacts_Model_DbTable_Internet extends Zend_Db_Table_Abstract
{

	protected $_name = 'internet';

	public function getInternet($contactid)
	{
		$contactid = (int)$contactid;
		$row = $this->fetchAll('contactid = ' . $contactid);
		if(!$row) {
			throw new Exception("Could not find row $contactid");
		}
		return $row->toArray();
	}

	public function addInternet($contactid, $internet, $ordering)
	{
		$data = array(
			'contactid' => $contactid,
			'internet' => $internet,
			'ordering' => $ordering
		);
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateInternet($id, $internet, $ordering)
	{
		$data = array(
			'internet' => $internet,
			'ordering' => $ordering
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteInternet($id)
	{
		$data = array(
			'deleted' => 1
		);
		$this->update($data, 'id =' . (int)$id);
	}
}
