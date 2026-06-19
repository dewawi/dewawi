<?php

class Sales_Model_DbTable_Salesorder extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'salesorder';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getSalesorders($contactid)
	{
		$contactid = (int)$contactid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('contactid = ?', $contactid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);
		return $data;
	}

	public function getLatestSalesorderID()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchRow($where, 'salesorderid DESC');
		if (!$data) {
			throw new Exception("Could not find row");
		}
		return $data->salesorderid;
	}

	public function getLatestSalesorders()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('salesorderid = ?', 0);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'id DESC', 5);
		return $data;
	}

	public function addSalesorder($data)
	{
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['clientid'] = $this->_client['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateSalesorder($id, $data)
	{
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$this->update($data, 'id = '.(int)$id);
	}

	public function updateTotal($id, $subtotal, $taxes, $total)
	{
		$data = array(
			'subtotal' => $subtotal,
			'taxes' => $taxes,
			'total' => $total,
			'modified' => $this->_date,
			'modifiedby' => $this->_user['id']
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function saveSalesorder($id, $salesorderid, $filename)
	{
		$data = array();
		$data['salesorderid'] = $salesorderid;
		$data['salesorderdate'] = $this->_date;
		$data['filename'] = $filename;
		$data['state'] = 105;
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$this->update($data, 'id = '. (int)$id);
	}

	public function setState($id, $state)
	{
		$data = array(
			'state' => $state,
			'modified' => $this->_date,
			'modifiedby' => $this->_user['id']
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteSalesorder($id)
	{
		$data = array(
			'deleted' => 1
		);
		$this->update($data, 'id =' . (int)$id);
	}

	protected function prepareCopyData(array $data): array
	{
		$data = parent::prepareCopyData($data);

		unset($data['salesorderid']);

		$data['salesorderdate'] = null;
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['pinned'] = 0;
		$data['modified'] = null;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = null;

		return $data;
	}
}
