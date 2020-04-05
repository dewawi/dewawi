<?php

class Items_Model_DbTable_Item extends Zend_Db_Table_Abstract
{

	protected $_name = 'item';

	public function getItem($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getItemId($sku)
	{
		$row = $this->fetchRow('sku = "' . $sku . '"');
		if (!$row) {
			throw new Exception("Could not find row $sku");
		}
		return $row->toArray();
	}

	public function getItems($ids)
	{
		$row = $this->fetchAll("sku IN ('" . implode("', '", $ids) . "')");
		if (!$row) {
			throw new Exception("Could not find row $ids");
		}
		return $row->toArray();
	}

	public function getItemsByCategory($catid)
	{
		$catid = (int)$catid;
		$row = $this->fetchAll('catid = ' . $catid);
		if (!$row) {
			throw new Exception("Could not find row $catid");
		}
		return $row->toArray();
	}

	public function addItem($data)
	{
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateItem($id, $data)
	{
		$this->update($data, 'id = '. (int)$id);
	}

	public function quantityItem($id, $quantity, $modified, $modifiedby)
	{
		$data = array(
			'quantity' => $quantity,
			'modified' => $modified,
			'modifiedby' => $modifiedby,
		);
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

	public function deleteItem($id)
	{
		$this->delete('id =' . (int)$id);
	}
}
