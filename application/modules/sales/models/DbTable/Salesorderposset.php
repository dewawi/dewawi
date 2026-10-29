<?php

class Sales_Model_DbTable_Salesorderposset extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'salesorderposset';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getPositionSet($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow($this->getEntityWhere($id));
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getPositionSets($parentid)
	{
		$parentid = (int)$parentid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('parentid = ?', $parentid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->getClientId());
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'ordering');
		if (!$data) {
			throw new Exception("Could not find row $parentid");
		}
		return $data;
	}

	public function addPositionSet($data)
	{
		$data['clientid'] = $this->getClientId();
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updatePositionSet($id, $data)
	{
		$id = (int)$id;
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$this->updateById($id, $data);
	}

	public function sortPositionSet($id, $ordering)
	{
		$data = array(
			'ordering' => $ordering,
		);
		$this->updateById((int)$id, $data);
	}

	public function sortPositionSets($orderings)
	{
		foreach ($orderings as $id => $ordering) $this->updateById((int)$id, ['ordering' => (int)$ordering]);
	}

	public function deletePositionSet($id)
	{
		$data = array(
			'deleted' => 1
		);
		$this->updateById((int)$id, $data);
	}

	public function deletePositionSets($ids)
	{
		$data = array(
			'deleted' => 1
		);
		$ids = $this->normalizeIds((array)$ids);
		if (!$ids) return;
		$where = $this->getEntitiesWhere($ids);
		$this->update($data, $where);
	}
}
