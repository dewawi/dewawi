<?php

class Admin_Model_DbTable_Permission extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'permission';

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
}
