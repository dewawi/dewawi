<?php

class Contacts_Model_DbTable_Bankaccount extends Zend_Db_Table_Abstract
{

	protected $_name = 'bankaccount';

	public function getBankaccount($contactid)
	{
		$contactid = (int)$contactid;
		$row = $this->fetchAll('contactid = ' . $contactid);
		if(!$row) {
			throw new Exception("Could not find row $contactid");
		}
		return $row->toArray();
	}

	public function addBankaccount($contactid, $iban, $bic, $ordering)
	{
		$data = array(
			'contactid' => $contactid,
			'iban' => $iban,
			'bic' => $bic,
			'ordering' => $ordering
		);
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateBankaccount($id, $data)
	{
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteBankaccount($id)
	{
		$this->delete('id =' . (int)$id);
	}
}
