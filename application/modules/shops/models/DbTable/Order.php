<?php

class Shops_Model_DbTable_Order extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'shoporder';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		parent::init();
		$this->_shop = Zend_Registry::isRegistered('Shop') ? Zend_Registry::get('Shop') : null;
		if (!$this->hasClientContext() && $this->_shop) $this->setClientId((int)$this->_shop['clientid']);
	}

	public function addOrder($data)
	{
		$data['created'] = $this->_date;
		//$data['createdby'] = $this->_user['id'];
		return $this->create($data);
	}

	public function getOrder($itemid, $orderid)
	{
		$itemid = (int)$itemid;
		$where = $this->getAccessWhere();
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$where[] = $this->getAdapter()->quoteInto('itemid = ?', $itemid);
		$where[] = $this->getAdapter()->quoteInto('orderid = ?', $orderid);
		$data = $this->fetchRow($where);
		return $data ? $data->toArray() : $data;
	}

	public function getOrders($ids)
	{
		$where = $this->getAdapter()->quoteInto('sku IN (?)', $ids);
		$data = $this->fetchAll($where);
		if (!$row) {
			throw new Exception("Could not find row $ids");
		}
		return $row->toArray();
	}

	public function updateOrder($id, $data)
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

	public function deleteOrder($itemid)
	{
		$itemid = (int)$itemid;
		$where = $this->getAccessWhere();
		$where[] = $this->getAdapter()->quoteInto('itemid = ?', $itemid);
		$this->delete($where);
	}
}
