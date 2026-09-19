<?php

class Shops_Model_DbTable_Taxrate extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'taxrate';
	protected ?string $siteField = null;

	public function getTaxRate(int $id): ?array
	{
		return $this->getById($id);
	}

	public function getTaxRates(): array
	{
		$taxrates = [];

		foreach ($this->fetchAll($this->getPublicSelect()) as $taxrate) {
			$taxrates[(int)$taxrate->id] = $taxrate->rate;
		}

		return $taxrates;
	}
}
