<?php

class Application_Model_DbTable_Textblock extends Zend_Db_Table_Abstract
{

	protected $_name = 'textblock';

	public function getCountry($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function addCountry($code, $name)
	{
		$data = array(
			'code' => $code,
			'name' => $name,
		);
		$this->insert($data);
		return $this->lastInsertId();
	}

	public function updateCountry($id, $code, $name)
	{
		$data = array(
			'code' => $code,
			'name' => $name,
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteCountry($id)
	{
		$this->delete('id =' . (int)$id);
	}
}
