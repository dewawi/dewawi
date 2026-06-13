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
		$select = $this->select()
			->where('id = ?', $id)
			->where('deleted = ?', 0)
			->limit(1);

		$row = $this->fetchRow($select);

		return $row ? $row->toArray() : null;
	}

	public function updateById(int $id, array $data): void
	{
		$data = $this->prepareUpdateData($data);

		$where = [
			$this->getAdapter()->quoteInto('id = ?', $id),
			$this->getAdapter()->quoteInto('deleted = ?', 0),
		];

		$this->update($data, $where);
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

	public function lock(int $id): void
	{
		$data = [
			'locked' => $this->getUserId(),
			'lockedtime' => $this->_date,
		];

		$where = [
			$this->getAdapter()->quoteInto('id = ?', $id),
			$this->getAdapter()->quoteInto('deleted = ?', 0),
		];

		$this->update($data, $where);
	}

	public function unlock(int $id): void
	{
		$data = [
			'locked' => 0,
			'lockedtime' => null,
		];

		$where = [
			$this->getAdapter()->quoteInto('id = ?', $id),
			$this->getAdapter()->quoteInto('deleted = ?', 0),
		];

		$this->update($data, $where);
	}
}
