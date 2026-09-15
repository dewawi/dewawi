<?php

class Admin_Model_DbTable_Client extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'client';

	protected function prepareCreateData(array $data): array
	{
		$data['created'] = $this->_date;
		$data['createdby'] = $this->getUserId();

		return $data;
	}

	public function getById(int $id): ?array
	{
		$select = $this->select();

		foreach ($this->getEntityWhere($id) as $where) {
			$select->where($where);
		}

		$row = $this->fetchRow($select->limit(1));

		return $row ? $row->toArray() : null;
	}

	public function updateById(int $id, array $data): void
	{
		$this->update(
			$this->prepareUpdateData($data),
			$this->getEntityWhere($id)
		);
	}

	public function deleteById(int $id): void
	{
		$data = [
			'deleted' => 1,
			'modified' => $this->_date,
			'modifiedby' => $this->getUserId(),
		];

		$where = [
			$this->getAdapter()->quoteInto('id = ?', $id),
			$this->getAdapter()->quoteInto('deleted = ?', 0),
		];

		$this->update($data, $where);
	}

	protected function getAccessWhere(): array
	{
		if (!empty($this->_user['admin'])) {
			return [];
		}

		$clientId = (int)$this->_user['clientid'];

		return [
			'('
				. $this->getAdapter()->quoteInto('id = ?', $clientId)
				. ' OR '
				. $this->getAdapter()->quoteInto('parentid = ?', $clientId)
			. ')',
		];
	}

	public function lock(int $id): void
	{
		$this->update([
			'locked' => $this->getUserId(),
			'lockedtime' => $this->_date,
		], $this->getEntityWhere($id));
	}

	public function unlock(int $id): void
	{
		$this->update([
			'locked' => 0,
			'lockedtime' => null,
		], $this->getEntityWhere($id));
	}
}
