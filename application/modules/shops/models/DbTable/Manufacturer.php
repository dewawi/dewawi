<?php

class Shops_Model_DbTable_Manufacturer extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'manufacturer';
	protected ?string $siteField = null;

	public function getManufacturers(): array
	{
		$manufacturers = [];

		foreach ($this->fetchAll($this->getPublicSelect()) as $manufacturer) {
			$manufacturers[(int)$manufacturer->id] = $manufacturer->name;
		}

		return $manufacturers;
	}
}
