<?php

class Shops_Model_DbTable_Itemopt extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'itemopt';
	protected ?string $siteField = null;
	public const INQUIRY_OPTION_LIMIT = 200;

	public function getInquiryOptions(int $parentId): array
	{
		return $this->fetchAll($this->getPublicSelect()->where('parentid = ?', $parentId)
			->order('ordering ASC')->order('id ASC')->limit(self::INQUIRY_OPTION_LIMIT + 1))->toArray();
	}

	public function getPositions(int $parentId, ?int $setId = null): Zend_Db_Table_Rowset_Abstract
	{
		$select = $this->getPublicSelect()
			->where('parentid = ?', $parentId)
			->order('ordering ASC');

		if ($setId !== null) {
			$select->where('optsetid = ?', $setId);
		}

		return $this->fetchAll($select);
	}
}
