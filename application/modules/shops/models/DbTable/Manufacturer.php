<?php

class Shops_Model_DbTable_Manufacturer extends Zend_Db_Table_Abstract
{

	protected $_name = 'manufacturer';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getManufacturer($id)
	{
		$id = (int)$id;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('id = ?', $id);
		$data = $this->fetchRow($where);
		return $data ? $data->toArray() : $data;
	}

	public function getManufacturers()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);

		$manufacturers = array();
		foreach($data as $manufacturer) {
			$manufacturers[$manufacturer->id] = $manufacturer->name;
		}
		return $manufacturers;
	}
}
