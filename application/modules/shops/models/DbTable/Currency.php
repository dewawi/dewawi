<?php

class Shops_Model_DbTable_Currency extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'currency';
	protected ?string $siteField = null;

	public function getPrimaryCurrency(): array
	{
		$row = $this->fetchRow(
			$this->getPublicSelect()
				->order('ordering ASC')
				->limit(1)
		);

		if (!$row) {
			throw new RuntimeException('Could not find currency');
		}

		return $row->toArray();
	}
}
