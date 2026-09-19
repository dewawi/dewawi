<?php

class Shops_Model_DbTable_Menuitem extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'menuitem';
	protected ?string $siteField = null;
	protected ?string $publicField = 'activated';

	public function getMenuitem(int $id): ?array
	{
		return $this->getById($id);
	}

	public function getMenuitems(int $menuid): Zend_Db_Table_Rowset_Abstract
	{
		return $this->fetchAll(
			$this->getPublicSelect()
				->where('menuid = ?', $menuid)
				->order('ordering ASC')
		);
	}
}
