<?php

abstract class DEEC_Model_DbTable_Position extends DEEC_Model_DbTable_Entity
{
	protected string $setField = 'possetid';
	protected string $masterField = 'masterid';

	public function getPosition(int $id): array
	{
		$row = $this->getById($id);

		if ($row === null) {
			throw new RuntimeException(
				sprintf('Position %d was not found', $id)
			);
		}

		return $row;
	}

	public function getPositions(
		int $parentId,
		?int $setId = null,
		?int $masterId = null
	) {
		$select = $this->select()
			->where($this->parentField . ' = ?', $parentId)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->order($this->orderingField . ' ASC')
			->order('id ASC');

		if ($setId !== null) {
			$select->where(
				$this->setField . ' = ?',
				$setId
			);
		}

		if ($masterId !== null) {
			$this->addMasterCondition(
				$select,
				$masterId
			);
		}

		return $this->fetchAll($select);
	}

	public function addPosition(array $data): int
	{
		$data = $this->preparePositionData($data);

		$this->normalizeOrderingByRow($data);

		$data[$this->orderingField] =
			$this->getNextPositionOrdering(
				(int)$data[$this->parentField],
				(int)$data[$this->setField],
				$data[$this->masterField]
			);

		return $this->create($data);
	}

	public function updatePosition(int $id, array $data): void
	{
		if (array_key_exists($this->setField, $data)) {
			$data[$this->setField] =
				max(0, (int)$data[$this->setField]);
		}

		if (array_key_exists($this->masterField, $data)) {
			$data[$this->masterField] =
				$this->normalizeMasterId(
					$data[$this->masterField]
				);
		}

		if (array_key_exists($this->orderingField, $data)) {
			$data[$this->orderingField] =
				max(1, (int)$data[$this->orderingField]);
		}

		$this->updateById($id, $data);
	}

	public function sortPosition(
		int $id,
		int $ordering
	): void {
		$this->updateById($id, [
			$this->orderingField => max(1, $ordering),
		]);
	}

	public function sortPositions(array $orderings): void
	{
		$orderings = $this->normalizeOrderings($orderings);

		if (!$orderings) {
			return;
		}

		$adapter = $this->getAdapter();
		$adapter->beginTransaction();

		try {
			foreach ($orderings as $id => $ordering) {
				$this->updateById($id, [
					$this->orderingField => $ordering,
				]);
			}

			$adapter->commit();
		} catch (Throwable $exception) {
			$adapter->rollBack();
			throw $exception;
		}
	}

	public function copyPosition(int $id): int
	{
		$row = $this->getPosition($id);
		$children = [];

		if (empty($row[$this->masterField])) {
			$children = $this->getPositions(
				(int)$row[$this->parentField],
				(int)$row[$this->setField],
				$id
			);
		}

		$adapter = $this->getAdapter();
		$adapter->beginTransaction();

		try {
			$this->shiftPositionOrderingForCopy($row);

			$data = $this->preparePositionCopyData(
				$row
			);

			$newId = $this->create($data);

			foreach ($children as $child) {
				$childData =
					$this->preparePositionCopyData(
						$child->toArray()
					);

				$childData[$this->masterField] =
					$newId;

				$this->addPosition($childData);
			}

			$adapter->commit();

			return $newId;
		} catch (Throwable $exception) {
			$adapter->rollBack();
			throw $exception;
		}
	}

	protected function preparePositionCopyData(
		array $data
	): array {
		unset(
			$data['id'],
			$data['created'],
			$data['createdby'],
			$data['modified'],
			$data['modifiedby']
		);

		if (array_key_exists('deleted', $data)) {
			$data['deleted'] = 0;
		}

		return $data;
	}

	protected function shiftPositionOrderingForCopy(
		array $row
	): void {
		$ordering = (int)$row[$this->orderingField];

		$where = [
			$this->getAdapter()->quoteInto(
				'clientid = ?',
				$this->getClientId()
			),
			$this->getAdapter()->quoteInto(
				'deleted = ?',
				0
			),
			$this->getAdapter()->quoteInto(
				$this->parentField . ' = ?',
				(int)$row[$this->parentField]
			),
			$this->getAdapter()->quoteInto(
				$this->setField . ' = ?',
				(int)$row[$this->setField]
			),
			$this->getAdapter()->quoteInto(
				$this->orderingField . ' > ?',
				$ordering
			),
		];

		$masterId = $this->normalizeMasterId(
			$row[$this->masterField] ?? null
		);

		if ($masterId === null) {
			$where[] = $this->masterField
				. ' IS NULL';
		} else {
			$where[] = $this->getAdapter()->quoteInto(
				$this->masterField . ' = ?',
				$masterId
			);
		}

		$this->update(
			[
				$this->orderingField =>
					new Zend_Db_Expr(
						$this->orderingField . ' + 1'
					),
			],
			$where
		);
	}

	public function deletePosition(int $id): void
	{
		$this->deletePositions([$id]);
	}

	public function deletePositions(array $ids): void
	{
		$ids = $this->normalizePositionIds($ids);

		if (!$ids) {
			return;
		}

		$groups = [];
		$deleteIds = $ids;

		foreach ($ids as $id) {
			$row = $this->getById($id);

			if ($row === null) {
				continue;
			}

			$groups[$this->getOrderingGroupKey($row)] = $row;

			if (!empty($row[$this->masterField])) {
				continue;
			}

			$children = $this->getPositions(
				(int)$row[$this->parentField],
				(int)$row[$this->setField],
				(int)$row['id']
			);

			foreach ($children as $child) {
				$deleteIds[] = (int)$child->id;
			}
		}

		$deleteIds = array_values(
			array_unique($deleteIds)
		);

		$adapter = $this->getAdapter();
		$adapter->beginTransaction();

		try {
			$this->deleteByIds($deleteIds);

			foreach ($groups as $row) {
				$this->normalizeOrderingByRow($row);
			}

			$adapter->commit();
		} catch (Throwable $exception) {
			$adapter->rollBack();
			throw $exception;
		}
	}

	public function moveToOrdering(
		int $id,
		int $targetOrdering
	): bool {
		$row = $this->getById($id);

		if (!$row) {
			return false;
		}

		$items = $this->getOrderingGroup($row);

		if (!$items) {
			return false;
		}

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

		$targetIndex = max(
			0,
			min(
				count($items) - 1,
				$targetOrdering - 1
			)
		);

		if ($currentIndex === $targetIndex) {
			$this->normalizeOrdering($items);

			return true;
		}

		$item = $items[$currentIndex];

		array_splice(
			$items,
			$currentIndex,
			1
		);

		array_splice(
			$items,
			$targetIndex,
			0,
			[$item]
		);

		$this->normalizeOrdering($items);

		return true;
	}

	public function getNextPositionOrdering(
		int $parentId,
		int $setId = 0,
		?int $masterId = null
	): int {
		$select = $this->select()
			->from($this->_name, [
				$this->orderingField,
			])
			->where(
				$this->parentField . ' = ?',
				$parentId
			)
			->where(
				$this->setField . ' = ?',
				$setId
			)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->order($this->orderingField . ' DESC')
			->order('id DESC')
			->limit(1);

		$this->addMasterCondition(
			$select,
			$masterId
		);

		$row = $this->fetchRow($select);

		if (!$row) {
			return 1;
		}

		return max(
			1,
			(int)$row[$this->orderingField] + 1
		);
	}

	public function getPositionOrderings(
		int $parentId,
		int $setId = 0,
		?int $masterId = null
	): array {
		$positions = $this->getPositions(
			$parentId,
			$setId,
			$masterId ?? 0
		);

		$orderings = [];
		$count = count($positions);

		for ($ordering = 1; $ordering <= $count; $ordering++) {
			$orderings[$ordering] = $ordering;
		}

		return $orderings;
	}

	protected function getOrderingGroup(array $row): array
	{
		if (
			!array_key_exists($this->parentField, $row)
			|| !array_key_exists($this->setField, $row)
		) {
			return [];
		}

		$select = $this->select()
			->where(
				$this->parentField . ' = ?',
				(int)$row[$this->parentField]
			)
			->where(
				$this->setField . ' = ?',
				(int)$row[$this->setField]
			)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->order($this->orderingField . ' ASC')
			->order('id ASC');

		$this->addMasterCondition(
			$select,
			$this->normalizeMasterId(
				$row[$this->masterField] ?? null
			)
		);

		return $this->fetchAll($select)->toArray();
	}

	protected function preparePositionData(array $data): array
	{
		if (
			!array_key_exists($this->parentField, $data)
			|| (int)$data[$this->parentField] < 1
		) {
			throw new InvalidArgumentException(
				'Position parent ID is missing'
			);
		}

		$data[$this->parentField] =
			(int)$data[$this->parentField];

		$data[$this->setField] =
			isset($data[$this->setField])
				? max(0, (int)$data[$this->setField])
				: 0;

		$data[$this->masterField] =
			$this->normalizeMasterId(
				$data[$this->masterField] ?? null
			);

		return $data;
	}

	protected function addMasterCondition(
		Zend_Db_Select $select,
		?int $masterId
	): void {
		if ($masterId === null || $masterId === 0) {
			$select->where(
				$this->masterField . ' IS NULL'
			);
			return;
		}

		$select->where(
			$this->masterField . ' = ?',
			$masterId
		);
	}

	protected function normalizeMasterId($masterId): ?int
	{
		if ($masterId === null || $masterId === '') {
			return null;
		}

		$masterId = (int)$masterId;

		return $masterId > 0
			? $masterId
			: null;
	}

	protected function normalizeOrderings(
		array $orderings
	): array {
		$normalized = [];

		foreach ($orderings as $id => $ordering) {
			$id = (int)$id;
			$ordering = (int)$ordering;

			if ($id < 1 || $ordering < 1) {
				continue;
			}

			$normalized[$id] = $ordering;
		}

		return $normalized;
	}

	protected function normalizePositionIds(array $ids): array
	{
		$ids = array_map('intval', $ids);

		$ids = array_filter(
			$ids,
			static function (int $id): bool {
				return $id > 0;
			}
		);

		return array_values(array_unique($ids));
	}

	protected function getOrderingGroupKey(array $row): string
	{
		$masterId = $this->normalizeMasterId(
			$row[$this->masterField] ?? null
		);

		return implode(':', [
			(int)$row[$this->parentField],
			(int)$row[$this->setField],
			$masterId ?? 'null',
		]);
	}
}
