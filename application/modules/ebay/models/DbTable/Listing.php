<?php

class Ebay_Model_DbTable_Listing extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'ebaylisting';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getListing($itemid, $accountid)
	{
		$itemid = (int)$itemid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('itemid = ?', $itemid);
		$where[] = $this->getAdapter()->quoteInto('accountid = ?', $accountid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$data = $this->fetchRow($where);
		return $data ? $data->toArray() : $data;
	}

	public function getListings($ids)
	{
		$where = $this->getAdapter()->quoteInto('sku IN (?)', $ids);
		$data = $this->fetchAll($where);
		if (!$row) {
			throw new Exception("Could not find row $ids");
		}
		return $row->toArray();
	}

	public function addListing($data)
	{
		$data['clientid'] = $this->_client['id'];
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		return $this->create($data);
	}

	public function updateListing($id, $data)
	{
		$id = (int)$id;
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$this->updateById($id, $data);
	}

	public function lock($id): void
	{
		$id = (int)$id;
		$data = array();
		$data['locked'] = $this->_user['id'];
		$data['lockedtime'] = $this->_date;
		$this->updateById($id, $data);
	}

	public function unlock($id): void
	{
		$id = (int)$id;
		$data = array('locked' => 0);
		$this->updateById($id, $data);
	}

	public function deleteListing($id)
	{
		$id = (int)$id;
		$data = array('deleted' => 1);
		$this->updateById($id, $data);
	}

	public function deleteListingByItemID($itemid)
	{
		$itemid = (int)$itemid;
		$data = array('deleted' => 1);
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('itemid = ?', $itemid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$this->update($data, $where);
	}
}
