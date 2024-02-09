<?php

class Campaigns_Model_DbTable_Campaignposset extends Zend_Db_Table_Abstract
{

	protected $_name = 'campaignposset';

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
		$row = $this->fetchRow('id = ' . $id);
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
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'ordering');
		if (!$data) {
			throw new Exception("Could not find row $parentid");
		}
		return $data;
	}

	public function addPositionSet($data)
	{
		$data['clientid'] = $this->_client['id'];
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
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	public function sortPositionSet($id, $ordering)
	{
		$data = array(
			'ordering' => $ordering,
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function sortPositionSets($orderings)
	{
		$ids = implode(',', array_keys($orderings));
		$sql = "UPDATE ".$this->_name." SET ordering = CASE id ";
		foreach ($orderings as $id => $ordering) {
			$sql .= sprintf("WHEN %d THEN %d ", $id, $ordering);
		}
		$sql .= "END WHERE id IN (".$ids.")";
		$this->_db->query($sql);
	}

	public function deletePositionSet($id)
	{
		$data = array(
			'deleted' => 1
		);
		$this->update($data, 'id =' . (int)$id);
	}

	public function deletePositionSets($ids)
	{
		$data = array(
			'deleted' => 1
		);
		$where = $this->getAdapter()->quoteInto('id IN (?)', $ids);
		$this->update($data, $where);
	}
}
