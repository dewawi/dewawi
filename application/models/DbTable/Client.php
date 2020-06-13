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
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);

		$clients = array();
		foreach($data as $client) {
			$clients[$client->id] = $client->company;
		}
		return $clients;
	}
}
