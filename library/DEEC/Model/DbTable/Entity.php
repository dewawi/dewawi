<?php

abstract class DEEC_Model_DbTable_Entity extends Zend_Db_Table_Abstract
{
	protected $_date = null;
	protected $_user = null;
	protected $_client = null;
	protected ?int $_clientId = null;

	protected string $parentField = 'parentid';
	protected ?string $orderingField = 'ordering';

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');

		$this->_user = Zend_Registry::isRegistered('User')
			? Zend_Registry::get('User')
			: null;

		$this->_client = Zend_Registry::isRegistered('Client')
			? Zend_Registry::get('Client')
			: null;
	}

	public function setClientId(int $clientId): self
	{
		$this->_clientId = $clientId > 0 ? $clientId : null;
		return $this;
	}

	public function getClientId(): int
	{
		if ($this->_clientId !== null) {
			return $this->_clientId;
		}

		if (is_array($this->_client) && !empty($this->_client['id'])) {
			return (int)$this->_client['id'];
		}

		throw new RuntimeException('Client context is missing');
	}

	public function hasClientContext(): bool
	{
		if ($this->_clientId !== null) {
			return true;
		}

		return is_array($this->_client) && !empty($this->_client['id']);
	}

	protected function getUserId(): int
	{
		if (is_array($this->_user) && !empty($this->_user['id'])) {
			return (int)$this->_user['id'];
		}

		return 0;
	}

	public function getById(int $id): ?array
	{
		$select = $this->select()
			->where('id = ?', $id)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->limit(1);

		$row = $this->fetchRow($select);

		return $row ? $row->toArray() : null;
	}

	public function getByParentId(int $parentid, string $module, string $controller): array
	{
		$select = $this->select()
			->where($this->parentField . ' = ?', $parentid)
			->where('module = ?', $module)
			->where('controller = ?', $controller)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0);

		if ($this->orderingField !== null) {
			$select->order($this->orderingField . ' ASC');
		}

		$select->order('id ASC');

		$rows = $this->fetchAll($select);

		return $rows ? $rows->toArray() : [];
	}

	public function create(array $data): int
	{
		$data = $this->prepareCreateData($data);
		$this->insert($data);

		return (int)$this->getAdapter()->lastInsertId();
	}

	public function createForParent(int $parentid, string $module, string $controller, array $data = []): int
	{
		$data[$this->parentField] = $parentid;
		$data['module'] = $module;
		$data['controller'] = $controller;

		if ($this->orderingField !== null && !array_key_exists($this->orderingField, $data)) {
			$data[$this->orderingField] = $this->getNextOrdering($parentid, $module, $controller);
		}

		return $this->create($data);
	}

	public function updateById(int $id, array $data): void
	{
		$data = $this->prepareUpdateData($data);

		$where = [
			$this->getAdapter()->quoteInto('id = ?', $id),
			$this->getAdapter()->quoteInto('clientid = ?', $this->getClientId()),
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
			$this->getAdapter()->quoteInto('clientid = ?', $this->getClientId()),
		];

		$this->update($data, $where);
	}

	public function deleteByIds(array $ids): int
	{
		$ids = $this->normalizeIds($ids);

		if (!$ids) {
			return 0;
		}

		$data = [
			'deleted' => 1,
			'modified' => $this->_date,
			'modifiedby' => $this->getUserId(),
		];

		$where = [
			'id IN (' . implode(',', $ids) . ')',
			$this->getAdapter()->quoteInto('clientid = ?', $this->getClientId()),
			$this->getAdapter()->quoteInto('deleted = ?', 0),
		];

		return $this->update($data, $where);
	}

	protected function normalizeIds(array $ids): array
	{
		$ids = array_map('intval', $ids);
		$ids = array_filter($ids, static function ($id) {
			return $id > 0;
		});

		return array_values(array_unique($ids));
	}

	protected function getNextOrdering(int $parentid, string $module, string $controller): int
	{
		if ($this->orderingField === null) {
			return 1;
		}

		$select = $this->select()
			->from($this->_name, [$this->orderingField])
			->where($this->parentField . ' = ?', $parentid)
			->where('module = ?', $module)
			->where('controller = ?', $controller)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->order($this->orderingField . ' DESC')
			->limit(1);

		$row = $this->fetchRow($select);

		return $row ? ((int)$row[$this->orderingField]) + 1 : 1;
	}

	protected function prepareCreateData(array $data): array
	{
		$data['clientid'] = $this->getClientId();
		$data['created'] = $this->_date;
		$data['createdby'] = $this->getUserId();

		return $data;
	}

	protected function prepareUpdateData(array $data): array
	{
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->getUserId();

		return $data;
	}

	public function getParentField(): string
	{
		return $this->parentField;
	}

	public function getOrderingField(): ?string
	{
		return $this->orderingField;
	}
}
