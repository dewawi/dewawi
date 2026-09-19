<?php

class Shops_Model_DbTable_Itemoptset extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'itemoptset';
	protected ?string $siteField = null;

	public function getPositionSets(int $parentId): Zend_Db_Table_Rowset_Abstract
	{
		return $this->fetchAll(
			$this->getPublicSelect()
				->where('parentid = ?', $parentId)
				->order('ordering ASC')
		);
	}
}
