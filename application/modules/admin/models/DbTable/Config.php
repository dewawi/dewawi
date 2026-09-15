<?php

class Admin_Model_DbTable_Config extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'config';
	protected ?string $deletedField = null;

	public function getById(int $id): ?array
	{
		$data = parent::getById($id);

		if ($data) {
			$data['smtppass'] = '';
		}

		return $data;
	}

	public function updateById(int $id, array $data): void
	{
		$data = $this->prepareUpdateData($data);

		$where = [
			$this->getAdapter()->quoteInto('id = ?', $id),
			$this->getAdapter()->quoteInto('clientid = ?', $this->getClientId()),
		];

		$this->update($data, $where);
	}

	public function lock(int $id): void
	{
		$where = [
			$this->getAdapter()->quoteInto('id = ?', $id),
			$this->getAdapter()->quoteInto('clientid = ?', $this->getClientId()),
		];

		$this->update([
			'locked' => $this->getUserId(),
			'lockedtime' => $this->_date,
		], $where);
	}

	public function unlock(int $id): void
	{
		$where = [
			$this->getAdapter()->quoteInto('id = ?', $id),
			$this->getAdapter()->quoteInto('clientid = ?', $this->getClientId()),
		];

		$this->update([
			'locked' => 0,
			'lockedtime' => null,
		], $where);
	}

	public function getConfig($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getConfigByClientID($clientid)
	{
		$clientid = (int)$clientid;
		$row = $this->fetchRow('clientid = ' . $clientid);
		if (!$row) {
			throw new Exception("Could not find row $clientid");
		}
		return $row->toArray();
	}

	public function getConfigs()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$data = $this->fetchAll($where);
		return $data;
	}

	public function addConfig($data, $clientid = 0)
	{
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		if($clientid) {
			$data['clientid'] = $clientid;
		} else {
			$data['clientid'] = $this->_client['id'];
		}
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateConfig($id, $data)
	{
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteConfig($id)
	{
		$data = array();
		$data['deleted'] = 1;
		$this->update($data, 'id =' . (int)$id);
	}
}
