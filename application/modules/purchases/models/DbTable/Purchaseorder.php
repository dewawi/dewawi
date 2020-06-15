<?php

class Purchases_Model_DbTable_Purchaseorder extends Zend_Db_Table_Abstract
{

	protected $_name = 'purchaseorder';

	protected $_date = null;

	protected $_user = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
	}

	public function getPurchaseorder($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getLatestPurchaseorderID()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_user['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchRow($where, 'purchaseorderid DESC');
		if (!$data) {
			throw new Exception("Could not find row");
		}
		return $data->purchaseorderid;
	}

	public function getLatestPurchaseorders()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('purchaseorderid = ?', 0);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_user['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'id DESC', 5);
		return $data;
	}

	public function addPurchaseorder($data)
	{
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updatePurchaseorder($id, $data)
	{
		$this->update($data, 'id = '.(int)$id);
	}

	public function updateTotal($id, $subtotal, $taxes, $total, $modified, $modifiedby)
	{
		$data = array(
			'subtotal' => $subtotal,
			'taxes' => $taxes,
			'total' => $total
			//'modified' => $modified,
			//'modifiedby' => $modifiedby
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function savePurchaseorder($id, $purchaseorderid, $purchaseorderdate, $state, $modified, $modifiedby)
	{
		$data = array(
			'purchaseorderid' => $purchaseorderid,
			'purchaseorderdate' => $purchaseorderdate,
			'state' => $state,
			'modified' => $modified,
			'modifiedby' => $modifiedby
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function setState($id, $state, $modified, $modifiedby)
	{
		$data = array(
			'state' => $state,
			'modified' => $modified,
			'modifiedby' => $modifiedby
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function lock($id, $locked, $lockedtime)
	{
		$data = array(
			'locked' => $locked,
			'lockedtime' => $lockedtime
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function unlock($id)
	{
		$data = array(
			'locked' => 0
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function deletePurchaseorder($id)
	{
		$data = array(
			'deleted' => 1
		);
		$this->update($data, 'id =' . (int)$id);
	}
}
