<?php

class Admin_Model_DbTable_Uom extends Zend_Db_Table_Abstract
{

	protected $_name = 'uom';

	public function getUom($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function addUom($data)
	{
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateUom($id, $data)
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

	public function deleteUom($id)
	{
		$this->delete('id =' . (int)$id);
	}
}
