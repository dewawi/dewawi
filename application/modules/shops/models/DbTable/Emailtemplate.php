<?php

class Shops_Model_DbTable_Emailtemplate extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'emailtemplate';
	protected ?string $siteField = null;
	protected ?string $deletedField = null;

	public function getEmailtemplate(string $module, string $controller): ?array
	{
		$row = $this->fetchRow(
			$this->getPublicSelect()
				->where('module = ?', $module)
				->where('controller = ?', $controller)
		);

		return $row ? $row->toArray() : null;
	}
}
