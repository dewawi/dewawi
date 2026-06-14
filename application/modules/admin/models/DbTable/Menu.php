<?php

class Admin_Model_DbTable_Menu extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'menu';

	public function getMenusByShopId(int $shopId): array
	{
		$select = $this->select()
			->where('shopid = ?', $shopId)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->order('ordering ASC')
			->order('title ASC');

		return $this->fetchAll($select)->toArray();
	}

	public function getMenuByPosition(int $shopId, string $position): ?array
	{
		$select = $this->select()
			->where('shopid = ?', $shopId)
			->where('position = ?', $position)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->limit(1);

		$row = $this->fetchRow($select);

		return $row ? $row->toArray() : null;
	}

	public function hasPosition(int $shopId, string $position, int $excludeId = 0): bool
	{
		$select = $this->select()
			->from($this->_name, ['id'])
			->where('shopid = ?', $shopId)
			->where('position = ?', $position)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0);

		if ($excludeId > 0) {
			$select->where('id != ?', $excludeId);
		}

		return (bool)$this->fetchRow($select);
	}

	public function getSelectOptions(int $shopId = 0): array
	{
		$select = $this->select()
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0);

		if ($shopId > 0) {
			$select->where('shopid = ?', $shopId);
		}

		$select
			->order('ordering ASC')
			->order('title ASC');

		$options = [];

		foreach ($this->fetchAll($select)->toArray() as $row) {
			$options[(string)$row['id']] = $row['shopid'].':'.(string)$row['title'];
		}

		return $options;
	}
}
