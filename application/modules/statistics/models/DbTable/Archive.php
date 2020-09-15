<?php

class Statistics_Model_DbTable_Archive extends Zend_Db_Table_Abstract
{

	protected $_name = 'archive';

	public function getArchive($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function addArchive($month, $data, $clientid)
	{
		$data = array(
			'month' => $month,
			'data' => $data,
			'clientid' => $clientid,
		);
		$this->insert($data);
	}

	public function updateArchive($id, $year, $month, $data)
	{
		$data = array(
			'id' => $id,
			'year' => $year,
			'month' => $month,
			'data' => $data,
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteArchive($id)
	{
		$this->delete('id =' . (int)$id);
	}
}
