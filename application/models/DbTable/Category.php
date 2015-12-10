<?php

class Application_Model_DbTable_Category extends Zend_Db_Table_Abstract
{

	protected $_name = 'category';

	public function getCategory($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function addCategory($parentid, $title, $type, $ordering, $clientid, $created)
	{
		$data = array(
			'title' => $title,
			'type' => $type,
			'parentid' => $parentid,
			'ordering' => $ordering,
			'clientid' => $clientid,
			'created' => $created,
		);
		$this->insert($data);
	}

	public function updateCategory($id, $parentid, $title, $type, $ordering, $modified)
	{
		$data = array(
			'parentid' => $parentid,
			'title' => $title,
			'type' => $type,
			'ordering' => $ordering,
			'modified' => $modified,
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function orderCategory($id, $ordering)
	{
		$data = array(
			'ordering' => $ordering,
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteCategory($id)
	{
		$this->delete('id =' . (int)$id);
	}
}
