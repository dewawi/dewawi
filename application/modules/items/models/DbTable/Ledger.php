<?php

class Items_Model_DbTable_Ledger extends Zend_Db_Table_Abstract
{

	protected $_name = 'ledger';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getLedger($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getLedgerBySKU($sku)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('sku = ?', $sku);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$data = $this->fetchAll($where);
		return $data;
	}

	public function getLedgers($deliveryorderid)
	{
		//$where = $this->getAdapter()->quoteInto('sku IN (?)', $ids);
		$where[] = $this->getAdapter()->quoteInto('deliveryorderid = ?', $deliveryorderid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$data = $this->fetchAll($where);
		if (!$row) {
			throw new Exception("Could not find row $ids");
		}
		return $row->toArray();
	}

	public function getLatestLedgers()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'id DESC', 5);
		return $data;
	}

	public function addLedger($data)
	{
		$data['clientid'] = $this->_client['id'];
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateLedger($id, $data)
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

	public function deleteLedger($id)
	{
		$id = (int)$id;
		$data = array('deleted' => 1);
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}
}
