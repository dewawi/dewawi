<?php

class Shops_Model_DbTable_Itematr extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'itematr';
	protected ?string $siteField = null;
	public const INQUIRY_SCAN_LIMIT = 5000;

	public function getInquiryAttributes(array $itemIds, array $titles): array
	{
		if(!$itemIds || !$titles) return [];
		$select = $this->getPublicSelect()->from($this->_name, ['id', 'parentid', 'atrsetid', 'title', 'value', 'description' => new Zend_Db_Expr('LEFT(description, 1001)'), 'uom'])
			->where('parentid IN (?)', $itemIds)->where('title IN (?)', $titles)
			->where('(atrsetid = 0 OR EXISTS (SELECT 1 FROM itematrset s WHERE s.id = itematr.atrsetid AND s.parentid = itematr.parentid AND s.clientid = itematr.clientid AND s.deleted = 0))')
			->order('parentid ASC')->order('id ASC')->limit(self::INQUIRY_SCAN_LIMIT + 1);
		return $this->fetchAll($select)->toArray();
	}

	public function getPositions(int $parentId, ?int $setId = null): Zend_Db_Table_Rowset_Abstract
	{
		$select = $this->getPublicSelect()
			->where('parentid = ?', $parentId)
			->order('ordering ASC');

		if ($setId !== null) {
			$select->where('atrsetid = ?', $setId);
		}

		return $this->fetchAll($select);
	}

	public function getPositionsBySku(string $sku, ?int $setId = null): Zend_Db_Table_Rowset_Abstract
	{
		$select = $this->getPublicSelect()
			->where('sku = ?', $sku)
			->order('ordering ASC');

		if ($setId !== null) {
			$select->where('atrsetid = ?', $setId);
		}

		return $this->fetchAll($select);
	}

	public function getPositionsByTitle(string $title, ?int $setId = null): Zend_Db_Table_Rowset_Abstract
	{
		$select = $this->getPublicSelect()
			->where('title = ?', $title)
			->order('ordering ASC');

		if ($setId !== null) {
			$select->where('atrsetid = ?', $setId);
		}

		return $this->fetchAll($select);
	}

	public function getPositionsBySkuAndTitle(string $sku, string $title, ?int $setId = null): Zend_Db_Table_Rowset_Abstract
	{
		$select = $this->getPublicSelect()
			->where('sku = ?', $sku)
			->where('title = ?', $title)
			->order('ordering ASC');

		if ($setId !== null) {
			$select->where('atrsetid = ?', $setId);
		}

		return $this->fetchAll($select);
	}
}
