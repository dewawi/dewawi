<?php

class Application_Model_DbTable_Client extends DEEC_Model_DbTable_Entity
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
		return $this->getSwitchableClients();
	}

	public function getSwitchableClients(): array
	{
		// Anchor access to the persisted account, not the selected session client.
		$userDb = new Users_Model_DbTable_User();
		$user = $userDb->getActiveUserById((int)$this->_user['id']);
		if(!$user) return [];

		$select = $this->select()->where('deleted = ?', 0);

		if(empty($user['admin'])) {
			$clientid = (int)$user['clientid'];
			$home = $this->fetchRow($this->select()->where('id = ?', $clientid)->where('deleted = ?', 0));
			if(!$home) return [];

			$adapter = $this->getAdapter();
			$scope = [
				$adapter->quoteInto('id = ?', $clientid),
				$adapter->quoteInto('parentid = ?', $clientid),
			];
			if((int)$home->parentid > 0) $scope[] = $adapter->quoteInto('id = ?', (int)$home->parentid);
			$select->where('(' . implode(' OR ', $scope) . ')');
		}

		$data = $this->fetchAll($select);
		$clients = array();
		foreach($data as $client) {
			$clients[$client->id] = $client->company;
		}
		return $clients;
	}
}
