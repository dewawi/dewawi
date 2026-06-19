<?php

class Sales_Model_DbTable_Creditnote extends DEEC_Model_DbTable_Entity
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

	public function saveCreditnote($id, $creditnoteid, $filename)
	{
		$data = array();
		$data['creditnoteid'] = $creditnoteid;
		$data['creditnotedate'] = $this->_date;
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

	public function deleteCreditnote($id)
	{
		$data = array(
			'deleted' => 1
		);
		$this->update($data, 'id =' . (int)$id);
	}

	protected function prepareCopyData(array $data): array
	{
		$data = parent::prepareCopyData($data);

		unset($data['creditnoteid']);

		$data['creditnotedate'] = null;
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
