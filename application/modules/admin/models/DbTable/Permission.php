<?php

class Admin_Model_DbTable_Permission extends Zend_Db_Table_Abstract
{

	protected $_name = 'permission';

	protected $_date = null;

	protected $_user = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
	    $this->_user = Zend_Registry::get('User');
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
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_user['clientid']);
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
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updatePermission($id, $data)
	{
		$this->update($data, 'id = '. (int)$id);
	}

	public function lock($id, $locked, $lockedtime)
	{
		$data = array(
			'locked' => $locked,
			'lockedtime' => $lockedtime
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

	public function deletePermission($id)
	{
		$data = array(
			'deleted' => 1
		);
		$this->update($data, 'id =' . (int)$id);
	}
}
