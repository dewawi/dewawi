<?php

class Contacts_Model_DbTable_Emailmessage extends Zend_Db_Table_Abstract
{

	protected $_name = 'emailmessage';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getEmailmessage($id)
	{
		$contactid = (int)$contactid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('contactid = ?', $contactid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);
		if(!$data) {
			throw new Exception("Could not find row $contactid");
		}
		return $data->toArray();
	}

	public function getEmailmessages($contactid, $documentid = NULL, $module = NULL, $controller = NULL)
	{
		$contactid = (int)$contactid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('contactid = ?', $contactid);
		if($documentid) $where[] = $this->getAdapter()->quoteInto('documentid = ?', $documentid);
		if($module) $where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		if($controller) $where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'id DESC');
		if(!$data) {
			throw new Exception("Could not find row $contactid");
		}
		return $data->toArray();
	}

	public function addEmailmessage($data)
	{
		$data['clientid'] = $this->_client['id'];
		$data['messagesent'] = $this->_date;
		$data['messagesentby'] = $this->_user['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateEmailmessage($id, $data)
	{
		$id = (int)$id;
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	public function deleteEmailmessage($id)
	{
		$id = (int)$id;
		$data = array('deleted' => 1);
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}
}
