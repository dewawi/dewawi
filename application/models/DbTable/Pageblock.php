<?php

class Application_Model_DbTable_Pageblock extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'pageblock';

	public function getBlocksByPageId(int $pageId, bool $activatedOnly = false, ?int $parentId = null): array
	{
		$select = $this->select()
			->where('pageid = ?', $pageId)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0);

		if ($activatedOnly) {
			$select->where('activated = ?', 1);
		}

		if ($parentId !== null) {
			$select->where('parentid = ?', $parentId);
		}

		$select
			->order('ordering ASC')
			->order('id ASC');

		return $this->fetchAll($select)->toArray();
	}

	protected function getOrderingContextFields(array $row): array
	{
		return ['pageid', 'parentid'];
	}

	protected function getOrderingGroup(array $row): array
	{
		return $this->getBlocksByPageId((int)$row['pageid'], false, (int)$row['parentid']);
	}

	protected function getHierarchyContextFields(array $row): array
	{
		return ['pageid'];
	}

	public function canDelete(array $row): bool
	{
		return !$this->hasChildren((int)$row['id'], $row);
	}
}
