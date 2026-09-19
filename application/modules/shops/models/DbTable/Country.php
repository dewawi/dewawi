<?php

class Shops_Model_DbTable_Country extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'country';
	protected ?string $siteField = null;
	protected ?string $deletedField = null;

	public function getCountries(): array
	{
		$countries = [];
		$translate = Zend_Registry::get('DEEC_Translate');

		foreach ($this->fetchAll($this->getPublicSelect(), 'name') as $country) {
			$countries[$country->code] = $translate->t($country->code);
		}

		$language = Zend_Registry::get('Zend_Locale');
		$collator = Collator::create($language);
		$collator->asort($countries);

		return $countries;
	}
}
