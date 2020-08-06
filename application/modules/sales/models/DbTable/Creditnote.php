<?php

class Sales_Model_DbTable_Creditnote extends Zend_Db_Table_Abstract
{

	protected $_name = 'creditnote';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
	    $this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getCreditnote($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getCreditnotes($contactid)
	{
		$contactid = (int)$contactid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('contactid = ?', $contactid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);
		return $data;
	}

	public function getLatestCreditnoteID()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchRow($where, 'creditnoteid DESC');
		if (!$data) {
			throw new Exception("Could not find row");
		}
		return $data->creditnoteid;
	}

	public function getLatestCreditnotes()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('creditnoteid = ?', 0);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'id DESC', 5);
		return $data;
	}

	public function addCreditnote($data)
	{
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['clientid'] = $this->_client['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateCreditnote($id, $data)
	{
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$this->update($data, 'id = '. (int)$id);
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

	public function saveCreditnote($id, $creditnoteid)
	{
		$data = array();
		$data['creditnoteid'] = $creditnoteid;
		$data['creditnotedate'] = $this->_date;
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

	public function deleteCreditnote($id)
	{
		$data = array(
			'deleted' => 1
		);
		$this->update($data, 'id =' . (int)$id);
	}
}
