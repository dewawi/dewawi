<?php

class Admin_Model_DbTable_Paymentmethod extends Zend_Db_Table_Abstract
{

	protected $_name = 'paymentmethod';

	public function getPaymentmethod($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function addPaymentmethod($data)
	{
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updatePaymentmethod($id, $data)
	{
		$this->update($data, 'id = '. (int)$id);
	}

	public function lock($id, $locked, $lockedtime)
	{
		$data = array(
			'locked' => $locked,
			'lockedtime' => $lockedtime
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function unlock($id)
	{
		$data = array(
			'locked' => 0
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function deletePaymentmethod($id)
	{
		$this->delete('id =' . (int)$id);
	}
}
