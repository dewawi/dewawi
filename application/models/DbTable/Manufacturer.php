<?php

class Application_Model_DbTable_Manufacturer extends Zend_Db_Table_Abstract
{
	protected $_name = 'manufacturer';

	public function getManufacturer($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function addManufacturer($name, $clientid, $created)
	{
		$data = array(
			'name' => $name,
			'clientid' => $clientid,
			'created' => $created,
		);
		$this->insert($data);
	}

	public function updateManufacturer($id, $name, $modified)
	{
		$data = array(
			'name' => $name,
			'modified' => $modified,
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteManufacturer($id)
	{
		$this->delete('id =' . (int)$id);
	}
}
