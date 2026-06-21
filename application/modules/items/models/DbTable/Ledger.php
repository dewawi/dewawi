<?php

class Items_Model_DbTable_Ledger extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'ledger';

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
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);
		return $data;
	}

	public function getLedgers($ledgerid)
	{
		//$where = $this->getAdapter()->quoteInto('sku IN (?)', $ids);
		$where[] = $this->getAdapter()->quoteInto('ledgerid = ?', $ledgerid);
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
}
