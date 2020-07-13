<?php

class Application_Model_DbTable_Client extends Zend_Db_Table_Abstract
{

	protected $_name = 'client';

	protected $_date = null;

	protected $_user = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
	    $this->_user = Zend_Registry::get('User');
	}

	public function getClient()
	{
		$id = (int)$this->_user['clientid'];
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getClients($parentid)
	{
		$data = $this->fetchAll(
            $this->select()
                ->where('id = ?', $this->_user['clientid'])
                ->where('deleted = ?', 0)
                ->orWhere('parentid = ?', $this->_user['clientid'])
                ->where('deleted = ?', 0)
                ->orWhere('id = ?', $parentid)
                ->where('deleted = ?', 0)
            );

		$clients = array();
		foreach($data as $client) {
			$clients[$client->id] = $client->company;
		}
		return $clients;
	}

	public function getAllClients()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);

		$clients = array();
		foreach($data as $client) {
			$clients[$client->id] = $client->company;
		}
		return $clients;
	}
}
