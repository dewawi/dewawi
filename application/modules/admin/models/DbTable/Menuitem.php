<?php

class Admin_Model_DbTable_Menuitem extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'menuitem';

	public function getChildren(int $parentId): array
	{
		$select = $this->select()
			->where('parentid = ?', $parentId)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->order('ordering ASC')
			->order('id ASC');

		return $this->fetchAll($select)->toArray();
	}

	public function hasItemsForMenu(int $menuId): bool
	{
		$select = $this->select()
			->from($this->_name, ['id'])
			->where('menuid = ?', $menuId)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->limit(1);

		return (bool)$this->fetchRow($select);
	}

	public function getItemsByMenuId(int $menuId): array
	{
		$select = $this->select()
			->where('menuid = ?', $menuId)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->order('parentid ASC')
			->order('ordering ASC')
			->order('id ASC');

		return $this->fetchAll($select)->toArray();
	}

	public function getSelectOptions(int $menuId = 0, int $excludeId = 0): array
	{
		$select = $this->select()
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0);

		if ($menuId > 0) {
			$select->where('menuid = ?', $menuId);
		}

		if ($excludeId > 0) {
			$select->where('id != ?', $excludeId);
		}

		$select
			->order('ordering ASC')
			->order('title ASC');

		$options = [];

		foreach ($this->fetchAll($select)->toArray() as $row) {
			$options[(string)$row['id']] = $menuId > 0 ? (string)$row['title'] : $row['menuid'].':'.(string)$row['title'];
		}

		return $options;
	}
}
