<?php

class Shops_Model_DbTable_Account extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'shopaccount';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		parent::init();
		$this->_shop = Zend_Registry::isRegistered('Shop') ? Zend_Registry::get('Shop') : null;
		if (!$this->hasClientContext() && $this->_shop) $this->setClientId((int)$this->_shop['clientid']);
	}

	public function getAccount($id)
	{
		$id = (int)$id;
		$where = $this->getEntityWhere($id);
		$data = $this->fetchRow($where);
		return $data ? $data->toArray() : $data;
	}

	public function getAccounts($ids)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('sku IN (?)', $ids);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$data = $this->fetchAll($where);
		if (!$row) {
			throw new Exception("Could not find row $ids");
		}
		return $row->toArray();
	}

	public function addAccount($data)
	{
		$data['clientid'] = $this->_client['id'];
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		return $this->create($data);
	}

	public function updateAccount($id, $data)
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

	public function deleteAccount($itemid)
	{
		$itemid = (int)$itemid;
		$where = $this->getAccessWhere();
		$where[] = $this->getAdapter()->quoteInto('itemid = ?', $itemid);
		$this->delete($where);
	}
}
