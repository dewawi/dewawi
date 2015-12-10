<?php

class Sales_Model_DbTable_Quotepos extends Zend_Db_Table_Abstract
{

	protected $_name = 'quotepos';

	public function getPosition($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function addPosition($data)
	{
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updatePosition($id, $data)
	{
		$this->update($data, 'id = '. (int)$id);
	}

	public function sortPosition($id, $ordering)
	{
		$data = array(
			'ordering' => $ordering,
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function sortPositions($orderings)
	{
		$ids = implode(',', array_keys($orderings));
		$sql = "UPDATE ".$this->_name." SET ordering = CASE id ";
		foreach ($orderings as $id => $ordering) {
			$sql .= sprintf("WHEN %d THEN %d ", $id, $ordering);
		}
		$sql .= "END WHERE id IN (".$ids.")";
		$this->_db->query($sql);
	}

	public function deletePosition($id)
	{
		$this->delete('id =' . (int)$id);
	}

	public function deletePositions($ids)
	{
		$where = $this->getAdapter()->quoteInto('id IN (?)', $ids);
		$this->delete($where);
	}
}
