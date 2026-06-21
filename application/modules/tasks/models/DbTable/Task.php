<?php

class Tasks_Model_DbTable_Task extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'task';

	public function getTask($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getTasks($contactid)
	{
		$contactid = (int)$contactid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('contactid = ?', $contactid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);
		return $data;
	}

	public function getLatestTasks()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'id DESC', 5);
		return $data;
	}

	public function addTask($data)
	{
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['clientid'] = $this->_client['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateTask($id, $data)
	{
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$this->update($data, 'id = '.(int)$id);
	}

	public function saveTask($id, $taskid, $taskdate, $state)
	{
		$data = array(
			'taskid' => $taskid,
			'taskdate' => $taskdate,
			'state' => $state,
			'modified' => $this->_date,
			'modifiedby' => $this->_user['id']
		);
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
}
