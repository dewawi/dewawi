<?php

class Purchases_Model_DbTable_Quoterequest extends Zend_Db_Table_Abstract
{

	protected $_name = 'quoterequest';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getQuoterequest($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getQuoterequests($contactid)
	{
		$contactid = (int)$contactid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('contactid = ?', $contactid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);
		return $data;
	}

	public function getLatestQuoterequestID()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchRow($where, 'quoterequestid DESC');
		if (!$data) {
			throw new Exception("Could not find row");
		}
		return $data->quoterequestid;
	}

	public function getLatestQuoterequests()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('quoterequestid = ?', 0);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'id DESC', 5);
		return $data;
	}

	public function addQuoterequest($data)
	{
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['clientid'] = $this->_client['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateQuoterequest($id, $data)
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

	public function saveQuoterequest($id, $quoterequestid, $filename)
	{
		$data = array();
		$data['quoterequestid'] = $quoterequestid;
		$data['quoterequestdate'] = $this->_date;
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

	public function lock($id)
	{
		$data = array(
			'locked' => $this->_user['id'],
			'lockedtime' => $this->_date
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

	public function deleteQuoterequest($id)
	{
		$data = array(
			'deleted' => 1
		);
		$this->update($data, 'id =' . (int)$id);
	}
}
