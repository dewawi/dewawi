<?php

class Shops_Model_DbTable_Country extends Zend_Db_Table_Abstract
{

	protected $_name = 'country';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getCountries()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$data = $this->fetchAll($where, 'name');

		$countries = array();
		$translate = Zend_Registry::get('Zend_Translate');
		foreach($data as $country) {
			$countries[$country->code] = $translate->translate($country->code);
		}
		//Sort countries with current locale
		$language = Zend_Registry::get('Zend_Locale');
		$collator = Collator::create($language);
		$collator->asort($countries);
		return $countries;
	}
}
