<?php

class Shops_Model_DbTable_Footer extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'footer';
	protected ?string $siteField = null;

	public function getFooters(int $templateId): Zend_Db_Table_Rowset_Abstract
	{
		return $this->fetchAll(
			$this->getPublicSelect()
				->where('templateid = ?', $templateId)
				->order('column ASC')
				->order('id ASC')
		);
	}
}
