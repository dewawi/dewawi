<?php

class Admin_Model_DbTable_Client extends Zend_Db_Table_Abstract
{

	protected $_name = 'client';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
	    $this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getClient($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getClients()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('id = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$orWhere = array();
		$orWhere[] = $this->getAdapter()->quoteInto('parentid = ?', $this->_client['id']);
		$orWhere[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll(
            $this->select()
                ->where('id = ?', $this->_client['id'])
                ->where('deleted = ?', 0)
                ->orWhere('parentid = ?', $this->_client['id'])
                ->where('deleted = ?', 0)
            );
		return $data;
	}

	public function addClient($data)
	{
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateClient($id, $data)
	{
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
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

	public function deleteClient($id)
	{
		$data = array(
			'deleted' => 1
		);
		$this->update($data, 'id =' . (int)$id);
	}
}
