<?php

class Admin_Model_DbTable_Client extends Zend_Db_Table_Abstract
{

	protected $_name = 'client';

	public function getClient($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function addClient($data)
	{
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateClient($id, $data)
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

	public function deleteClient($id)
	{
		$this->delete('id =' . (int)$id);
	}
}
