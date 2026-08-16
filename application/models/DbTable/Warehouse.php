<?php

class Application_Model_DbTable_Warehouse extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'warehouse';

	public function getWarehouses()
	{
		$where = [];
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('activated = ?', 1);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		return $this->fetchAll($where, ['default DESC', 'title ASC']);
	}

	public function getSelectOptions(): array
	{
		$warehouses = [];

		foreach($this->getWarehouses() as $warehouse) {
			$warehouses[(int)$warehouse->id] = $warehouse->title;
		}

		return $warehouses;
	}
}
