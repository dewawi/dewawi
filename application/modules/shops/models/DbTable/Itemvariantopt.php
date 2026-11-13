<?php

class Shops_Model_DbTable_Itemvariantopt extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'itemvariantopt';
	protected ?string $siteField = null;
	public const INQUIRY_RELATION_LIMIT = 2400;

	public function getInquiryRelations(array $itemIds): array
	{
		if(!$itemIds) return [];
		return $this->fetchAll($this->getPublicSelect()->where('itemid IN (?)', $itemIds)
			->order('id ASC')->limit(self::INQUIRY_RELATION_LIMIT + 1))->toArray();
	}
}
