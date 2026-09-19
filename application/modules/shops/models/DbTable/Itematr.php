<?php

class Shops_Model_DbTable_Itematr extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'itematr';
	protected ?string $siteField = null;

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
}
