<?php

class Items_Model_DbTable_Inventory extends Zend_Db_Table_Abstract
{

	protected $_name = 'inventory';

	protected $_date = null;

	protected $_user = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
	}

	public function getInventory($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getInventorys($ids)
	{
		$where = $this->getAdapter()->quoteInto('sku IN (?)', $ids);
		$data = $this->fetchAll($where);
		if (!$row) {
			throw new Exception("Could not find row $ids");
		}
		return $row->toArray();
	}

	public function addInventory($data)
	{
		$data['clientid'] = $this->_user['clientid'];
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateInventory($id, $data)
	{
		$id = (int)$id;
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	public function lock($id)
	{
		$id = (int)$id;
		$data = array();
		$data['locked'] = $this->_user['id'];
		$data['lockedtime'] = $this->_date;
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	public function unlock($id)
	{
		$id = (int)$id;
		$data = array('locked' => 0);
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	public function deleteInventory($id)
	{
		$id = (int)$id;
		$data = array('deleted' => 1);
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}
}
