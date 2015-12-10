<?php

class Application_Model_DbTable_Footer extends Zend_Db_Table_Abstract
{
	protected $_name = 'footer';

	public function getFooter($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function addFooter($name, $clientid, $created)
	{
		$data = array(
			'name' => $name,
			'clientid' => $clientid,
			'created' => $created,
		);
		$this->insert($data);
	}

	public function updateFooter($id, $name, $modified)
	{
		$data = array(
			'name' => $name,
			'modified' => $modified,
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteFooter($id)
	{
		$this->delete('id =' . (int)$id);
	}
}
