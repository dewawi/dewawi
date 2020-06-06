<?php

class Users_Model_DbTable_User extends Zend_Db_Table_Abstract
{

	protected $_name = 'user';

	public function getUser($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getUserByUsername($username)
	{
		$row = $this->fetchRow(
			$this->select()
				->where('username = ?', $username)
		);
		if (!$row) {
			throw new Exception("Could not find row $username");
		}
		return $row->toArray();
	}
}
