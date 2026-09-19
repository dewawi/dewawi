<?php

class Shops_Model_DbTable_Itematrset extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'itematrset';
	protected ?string $siteField = null;

	public function getPositionSets(int $parentId): array
	{
		return $this->fetchAll(
			$this->getPublicSelect()
				->where('parentid = ?', $parentId)
				->order('ordering ASC')
		)->toArray();
	}
}
