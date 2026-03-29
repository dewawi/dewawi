<?php

abstract class DEEC_Model_DbTable_Entity extends Zend_Db_Table_Abstract
{
	protected $_date = null;
	protected $_user = null;
	protected $_client = null;

	/**
	 * Parent foreign key column name.
	 */
	protected string $parentField = 'parentid';

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	/**
	 * Return one active row by primary id.
	 */
	public function getById(int $id): ?array
	{
		$select = $this->select()
			->where('id = ?', $id)
			->where('clientid = ?', $this->_client['id'])
			->where('deleted = ?', 0)
			->limit(1);

		$row = $this->fetchRow($select);

		if (!$row) {
			return null;
		}

		return $row->toArray();
	}

	/**
	 * Return all active rows for one parent.
	 */
	public function getByParentId(int $parentid): array
	{
		$select = $this->select()
			->where($this->parentField . ' = ?', $parentid)
			->where('clientid = ?', $this->_client['id'])
			->where('deleted = ?', 0)
			->order('ordering ASC')
			->order('id ASC');

		$rows = $this->fetchAll($select);

		if (!$rows) {
			return [];
		}

		return $rows->toArray();
	}

	/**
	 * Create one row.
	 */
	public function create(array $data): int
	{
		$data = $this->prepareCreateData($data);

		$this->insert($data);

		return (int)$this->getAdapter()->lastInsertId();
	}

	/**
	 * Create one row for a parent and assign default ordering if missing.
	 */
	public function createForParent(int $parentid, array $data = []): int
	{
		$data[$this->parentField] = $parentid;

		if (!array_key_exists('ordering', $data)) {
			$data['ordering'] = $this->getNextOrdering($parentid);
		}

		return $this->create($data);
	}

	/**
	 * Update one active row by id.
	 */
	public function updateById(int $id, array $data): void
	{
		$data = $this->prepareUpdateData($data);

		$where = [
			$this->getAdapter()->quoteInto('id = ?', $id),
			$this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']),
			$this->getAdapter()->quoteInto('deleted = ?', 0),
		];

		$this->update($data, $where);
	}

	/**
	 * Soft-delete one row by id.
	 */
	public function deleteById(int $id): void
	{
		$data = [
			'deleted' => 1,
			'modified' => $this->_date,
			'modifiedby' => $this->_user['id'],
		];

		$where = [
			$this->getAdapter()->quoteInto('id = ?', $id),
			$this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']),
		];

		$this->update($data, $where);
	}

	/**
	 * Return next ordering value for one parent.
	 */
	protected function getNextOrdering(int $parentid): int
	{
		$select = $this->select()
			->from($this->_name, ['ordering'])
			->where($this->parentField . ' = ?', $parentid)
			->where('clientid = ?', $this->_client['id'])
			->where('deleted = ?', 0)
			->order('ordering DESC')
			->limit(1);

		$row = $this->fetchRow($select);

		if (!$row) {
			return 1;
		}

		return ((int)$row['ordering']) + 1;
	}

	/**
	 * Prepare create payload with common metadata.
	 */
	protected function prepareCreateData(array $data): array
	{
		$data['clientid'] = $this->_client['id'];
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];

		return $data;
	}

	/**
	 * Prepare update payload with common metadata.
	 */
	protected function prepareUpdateData(array $data): array
	{
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];

		return $data;
	}

	public function getParentField(): string
	{
		return $this->parentField;
	}
}
