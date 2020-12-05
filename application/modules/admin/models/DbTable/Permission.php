<?php

class Admin_Model_DbTable_Permission extends Zend_Db_Table_Abstract
{

	protected $_name = 'permission';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getPermission($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getPermissions()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$data = $this->fetchAll($where);

		$permissions = array();
		foreach($data as $id => $permission) {
			$permission['default'] = json_decode($permission['default']);
			$permission['contacts'] = json_decode($permission['contacts']);
			$permission['items'] = json_decode($permission['items']);
			$permission['processes'] = json_decode($permission['processes']);
			$permission['purchases'] = json_decode($permission['purchases']);
			$permission['sales'] = json_decode($permission['sales']);
			$permission['statistics'] = json_decode($permission['statistics']);
			$permissions[$id] = $permission;
		}
		return $permissions;
	}

	public function addPermission($data)
	{
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['clientid'] = $this->_client['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updatePermission($id, $data)
	{
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$this->update($data, 'id = '. (int)$id);
	}

	public function lock($id)
	{
		$data = array();
		$data['locked'] = $this->_user['id'];
		$data['lockedtime'] = $this->_date;
		$this->update($data, 'id = '. (int)$id);
	}

	public function unlock($id)
	{
		$data = array();
		$data['locked'] = 0;
		$this->update($data, 'id = '. (int)$id);
	}

	public function deletePermission($id)
	{
		$data = array();
		$data['deleted'] = 1;
		$this->update($data, 'id =' . (int)$id);
	}
}
