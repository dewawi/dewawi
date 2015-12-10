<?php

class Application_Model_DbTable_Template extends Zend_Db_Table_Abstract
{

	protected $_name = 'template';

	public function getTemplate($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function addTemplate($description, $filename, $clientid, $created)
	{
		$data = array(
			'description' => $description,
			'filename' => $filename,
			'clientid' => $clientid,
			'created' => $created
		);
		$this->insert($data);
		return $this->lastInsertId();
	}

	public function updateTemplate($description, $filename, $modified)
	{
		$data = array(
			'description' => $description,
			'filename' => $filename,
			'modified' => $modified
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteTemplate($id)
	{
		$this->delete('id =' . (int)$id);
	}
}
