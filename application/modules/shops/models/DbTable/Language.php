<?php

class Shops_Model_DbTable_Language extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'language';
	protected ?string $siteField = null;

	public function getPrimaryLanguage(): array
	{
		$row = $this->fetchRow(
			$this->getPublicSelect()
				->order('ordering ASC')
				->limit(1)
		);

		if (!$row) {
			throw new RuntimeException('Could not find language');
		}

		return $row->toArray();
	}
}
