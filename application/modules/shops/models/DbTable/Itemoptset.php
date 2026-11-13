<?php

class Shops_Model_DbTable_Itemoptset extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'itemoptset';
	protected ?string $siteField = null;
	public const INQUIRY_GROUP_LIMIT = 200;

	public function getInquiryGroups(int $parentId): array
	{
		return $this->fetchAll($this->getPublicSelect()->where('parentid = ?', $parentId)
			->order('id ASC')->limit(self::INQUIRY_GROUP_LIMIT + 1))->toArray();
	}

	public function getPositionSets(int $parentId): Zend_Db_Table_Rowset_Abstract
	{
		return $this->fetchAll(
			$this->getPublicSelect()
				->where('parentid = ?', $parentId)
				->order('ordering ASC')
		);
	}
}
