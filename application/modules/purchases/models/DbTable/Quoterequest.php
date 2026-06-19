<?php

class Purchases_Model_DbTable_Quoterequest extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'quoterequest';

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

	protected function prepareCopyData(array $data): array
	{
		$data = parent::prepareCopyData($data);

		unset($data['quoterequestid']);

		$data['quoterequestdate'] = null;
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
