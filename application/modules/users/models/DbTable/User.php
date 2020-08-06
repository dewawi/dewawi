<?php

class Users_Model_DbTable_User extends Zend_Db_Table_Abstract
{

	protected $_name = 'user';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
	    if(Zend_Registry::isRegistered('User')) $this->_user = Zend_Registry::get('User');
		if(Zend_Registry::isRegistered('Client')) $this->_client = Zend_Registry::get('Client');
	}

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

	public function lock($id)
	{
		$id = (int)$id;
		$data = array();
		$data['locked'] = $this->_user['id'];
		$data['lockedtime'] = $this->_date;
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	public function unlock($id)
	{
		$id = (int)$id;
		$data = array('locked' => 0);
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}
}
