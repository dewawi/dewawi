<?php

class Items_Model_DbTable_Inventory extends Zend_Db_Table_Abstract
{

	protected $_name = 'inventory';

	public function getInventory($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getInventorys($ids)
	{
		$row = $this->fetchAll("sku IN ('" . implode("', '", $ids) . "')");
		if (!$row) {
			throw new Exception("Could not find row $ids");
		}
		return $row->toArray();
	}

	public function addInventory($data)
	{
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateInventory($id, $data)
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

	public function deleteInventory($id)
	{
		$data = array(
			'deleted' => 1
		);
		$this->update($data, 'id =' . (int)$id);
	}
}
