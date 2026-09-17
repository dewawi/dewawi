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
}
