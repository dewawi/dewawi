<?php

class Application_Model_DbTable_Manufacturer extends Zend_Db_Table_Abstract
{
	protected $_name = 'manufacturer';

	protected $_date = null;

	protected $_user = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
	}

	public function getManufacturer($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getManufacturers()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_user['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);

		$manufacturers = array();
		foreach($data as $manufacturer) {
			$manufacturers[$manufacturer->id] = $manufacturer->name;
		}
		return $manufacturers;
	}
}
