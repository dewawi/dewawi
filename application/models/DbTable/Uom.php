<?php

class Application_Model_DbTable_Uom extends Zend_Db_Table_Abstract
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

	public function getUoms()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);

		$uoms = array();
		foreach($data as $uom) {
			$uoms[$uom->id] = $uom->title;
		}
		return $uoms;
	}
}
