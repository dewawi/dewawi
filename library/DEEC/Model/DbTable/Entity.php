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

	public function getByModuleController(string $module, string $controller): array
	{
		$select = $this->select()
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
			$data[$this->orderingField] = $this->getNextOrdering([
				$this->parentField => $parentid,
				'module' => $module,
				'controller' => $controller,
			]);
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

	public function copyById(int $id): int
	{
		$row = $this->getById($id);

		if (!$row) {
			throw new RuntimeException('Record not found');
		}

		$this->shiftOrderingForCopy($row);

		$data = $this->prepareCopyData($row);

		return $this->create($data);
	}

	protected function shiftOrderingForCopy(array $row): void
	{
		if ($this->orderingField === null || !isset($row[$this->orderingField])) {
			return;
		}

		$ordering = (int)$row[$this->orderingField];

		$where = [
			$this->getAdapter()->quoteInto('clientid = ?', $this->getClientId()),
			$this->getAdapter()->quoteInto('deleted = ?', 0),
			$this->getAdapter()->quoteInto($this->orderingField . ' > ?', $ordering),
		];

		foreach ($this->getOrderingContextFields($row) as $field) {
			if (array_key_exists($field, $row)) {
				$where[] = $this->getAdapter()->quoteInto($field . ' = ?', $row[$field]);
			}
		}

		$this->update(
			[$this->orderingField => new Zend_Db_Expr($this->orderingField . ' + 1')],
			$where
		);
	}

	protected function getOrderingContextFields(array $row): array
	{
		$fields = [];

		foreach ([$this->parentField, 'module', 'controller', 'type', 'shopid', 'position'] as $field) {
			if (array_key_exists($field, $row)) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	protected function prepareCopyData(array $data): array
	{
		unset($data['id']);

		if (!empty($data['title'])) {
			$data['title'] .= ' 2';
		}

		if (!empty($data['name'])) {
			$data['name'] .= ' 2';
		}

		if (!empty($data['company'])) {
			$data['company'] .= ' 2';
		}

		if (!empty($data['username'])) {
			$data['username'] .= '2';
		}

		if (!empty($data['email'])) {
			$data['email'] .= '2';
		}

		if ($this->orderingField !== null && isset($data[$this->orderingField])) {
			$data[$this->orderingField] = (int)$data[$this->orderingField] + 1;
		}

		unset(
			$data['created'],
			$data['createdby'],
			$data['modified'],
			$data['modifiedby']
		);

		$data['locked'] = 0;
		$data['lockedtime'] = null;

		if (array_key_exists('deleted', $data)) {
			$data['deleted'] = 0;
		}

		return $data;
	}

	public function deleteById(int $id): void
	{
		$this->deleteByIds([$id]);
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

	public function hasChildren(int $id, array $row = []): bool
	{
		if (!$this->parentField) {
			return false;
		}

		$select = $this->select()
			->from($this->_name, ['id'])
			->where($this->parentField . ' = ?', $id)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->limit(1);

		foreach ($this->getHierarchyContextFields($row) as $field) {
			if (array_key_exists($field, $row)) {
				$select->where($field . ' = ?', $row[$field]);
			}
		}

		return (bool)$this->fetchRow($select);
	}

	protected function getHierarchyContextFields(array $row): array
	{
		$fields = [];

		foreach (['type', 'shopid', 'module', 'controller'] as $field) {
			if (array_key_exists($field, $row)) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	public function normalizeOrderingByRow(array $row): void
	{
		if ($this->orderingField === null) {
			return;
		}

		$items = $this->getOrderingGroup($row);
		$this->normalizeOrdering($items);
	}

	protected function normalizeIds(array $ids): array
	{
		$ids = array_map('intval', $ids);
		$ids = array_filter($ids, static function ($id) {
			return $id > 0;
		});

		return array_values(array_unique($ids));
	}

	public function moveOrdering(int $id, string $direction): bool
	{
		if ($this->orderingField === null) {
			return false;
		}

		$row = $this->getById($id);

		if (!$row) {
			return false;
		}

		$items = $this->getOrderingGroup($row);
		$currentIndex = null;

		foreach ($items as $index => $item) {
			if ((int)$item['id'] === $id) {
				$currentIndex = $index;
				break;
			}
		}

		if ($currentIndex === null) {
			return false;
		}

		$targetIndex = $currentIndex;

		if ($direction === 'up') {
			$targetIndex = max(0, $currentIndex - 1);
		}

		if ($direction === 'down') {
			$targetIndex = min(count($items) - 1, $currentIndex + 1);
		}

		if ($targetIndex === $currentIndex) {
			$this->normalizeOrdering($items);
			return true;
		}

		$item = $items[$currentIndex];
		array_splice($items, $currentIndex, 1);
		array_splice($items, $targetIndex, 0, [$item]);

		$this->normalizeOrdering($items);

		return true;
	}

	protected function getOrderingGroup(array $row): array
	{
		$select = $this->select()
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->order($this->orderingField . ' ASC')
			->order('id ASC');

		if (isset($row[$this->parentField])) {
			$select->where($this->parentField . ' = ?', (int)$row[$this->parentField]);
		}

		if (isset($row['module'])) {
			$select->where('module = ?', (string)$row['module']);
		}

		if (isset($row['controller'])) {
			$select->where('controller = ?', (string)$row['controller']);
		}

		if (isset($row['type'])) {
			$select->where('type = ?', (string)$row['type']);
		}

		if (isset($row['shopid'])) {
			$select->where('shopid = ?', (int)$row['shopid']);
		}

		if (isset($row['position'])) {
			$select->where('position = ?', (string)$row['position']);
		}

		return $this->fetchAll($select)->toArray();
	}

	protected function normalizeOrdering(array $items): void
	{
		$ordering = 1;

		foreach ($items as $item) {
			if (empty($item['id'])) {
				continue;
			}

			$this->updateById((int)$item['id'], [
				$this->orderingField => $ordering,
			]);

			$ordering++;
		}
	}

	public function getNextOrdering(array $context = []): int
	{
		if ($this->orderingField === null) {
			return 1;
		}

		$select = $this->select()
			->from($this->_name, [$this->orderingField])
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->order($this->orderingField . ' DESC')
			->limit(1);

		foreach ($context as $field => $value) {
			$select->where($field . ' = ?', $value);
		}

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

	public function lock(int $id): void
	{
		$data = [
			'locked' => $this->getUserId(),
			'lockedtime' => $this->_date,
		];

		$where = [
			$this->getAdapter()->quoteInto('id = ?', $id),
			$this->getAdapter()->quoteInto('clientid = ?', $this->getClientId()),
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
			$this->getAdapter()->quoteInto('clientid = ?', $this->getClientId()),
			$this->getAdapter()->quoteInto('deleted = ?', 0),
		];

		$this->update($data, $where);
	}
}
