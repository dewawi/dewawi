<?php

class Admin_Model_DbTable_User extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'user';

	protected function prepareUpdateData(array $data): array
	{
		$clientId = $data['clientid'] ?? null;
		$data = parent::prepareUpdateData($data);
		if ($clientId !== null) {
			if (!filter_var($clientId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) || empty($this->_user['admin']) || !array_key_exists($clientId, (new Application_Model_DbTable_Client())->getSwitchableClients())) throw new InvalidArgumentException('Invalid user client');
			$data['clientid'] = (int)$clientId;
		}
		return $data;
	}

	public function getUser($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow($this->getEntityWhere($id));
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getUsers()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);
		return $data;
	}
}
