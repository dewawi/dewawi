<?php

class Users_Model_DbTable_Usertracking extends Zend_Db_Table_Abstract
{

	protected $_name = 'usertracking';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		if(Zend_Registry::isRegistered('User')) $this->_user = Zend_Registry::get('User');
		if(Zend_Registry::isRegistered('Client')) $this->_client = Zend_Registry::get('Client');
	}

	public function addUsertracking($user, $target)
	{
		$useragent = $_SERVER['HTTP_USER_AGENT'];
		$remoteip = $_SERVER['REMOTE_ADDR'];
		$ipOctets = explode('.', $remoteip);
		$remoteip = $ipOctets[0].'.'.$ipOctets[1].'.'.$ipOctets[2].'.0';

		$data = array();
		$data['userid'] = $user->id;
		$data['username'] = $user->username;
		$data['name'] = $user->name;
		$data['email'] = $user->email;
		$data['admin'] = $user->admin;
		$data['target'] = $target;
		$data['useragent'] = $useragent;
		$data['remoteip'] = $remoteip;
		$data['clientid'] = $user->clientid;
		$data['accesstime'] = $this->_date;
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}
}
