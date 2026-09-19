<?php

class Shops_Model_DbTable_Menu extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'menu';
	protected ?string $publicField = 'activated';

	public function getMenu(int $id): ?array
	{
		return $this->getPublicById($id);
	}

	public function getMenus(): Zend_Db_Table_Rowset_Abstract
	{
		return $this->fetchAll(
			$this->getPublicSelect()
				->order('ordering ASC')
		);
	}
}
