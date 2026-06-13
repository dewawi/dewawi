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
}
