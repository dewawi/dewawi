<?php

class Items_Model_DbTable_Stockplanning extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'stockplanning';
	protected string $parentField = '';
	protected ?string $orderingField = null;

	public function getOpenByItemId(int $itemId, ?int $warehouseId = null): array
	{
		$select = $this->select()
			->where('itemid = ?', $itemId)
			->where('clientid = ?', $this->getClientId())
			->where('status = ?', 'planned')
			->where('quantity > fulfilled')
			->where('deleted = ?', 0)
			->order('planningdate ASC')
			->order('type ASC')
			->order('id ASC');

		if($warehouseId !== null && $warehouseId > 0) {
			$select->where('warehouseid = ?', $warehouseId);
		}

		return $this->fetchAll($select)->toArray();
	}

	public function getByItemId(int $itemId): array
	{
		return $this->fetchAll(
			$this->select()
				->where('itemid = ?', $itemId)
				->where('clientid = ?', $this->getClientId())
				->where('deleted = ?', 0)
				->order('planningdate ASC')
				->order('id ASC')
		)->toArray();
	}

	public function getOpenEvents(
		int $itemId,
		int $warehouseId,
		int $excludeId = 0
	): array {
		$select = $this->select()
			->from($this->_name, [
				'id',
				'type',
				'planningdate',
				'dateconfirmed',
				'quantity',
				'fulfilled',
				'remaining' => new Zend_Db_Expr('quantity - fulfilled'),
			])
			->where('itemid = ?', $itemId)
			->where('warehouseid = ?', $warehouseId)
			->where('clientid = ?', $this->getClientId())
			->where('status = ?', 'planned')
			->where('quantity > fulfilled')
			->where('deleted = ?', 0)
			->order('planningdate ASC')
			->order(new Zend_Db_Expr("CASE WHEN type = 'incoming' THEN 0 ELSE 1 END ASC"))
			->order('id ASC');

		if($excludeId > 0) {
			$select->where('id != ?', $excludeId);
		}

		return $this->fetchAll($select)->toArray();
	}

	public function canDelete(array $row): bool
	{
		return ($row['status'] ?? '') === 'draft'
			&& (float)($row['fulfilled'] ?? 0) === 0.0;
	}

	protected function prepareCopyData(array $data): array
	{
		$data = parent::prepareCopyData($data);
		$data['fulfilled'] = 0;
		$data['status'] = 'draft';
		return $data;
	}
}
