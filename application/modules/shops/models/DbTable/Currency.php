<?php

class Shops_Model_DbTable_Currency extends Zend_Db_Table_Abstract
{

	protected $_name = 'currency';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getPrimaryCurrency()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchRow($where, 'ordering');
		if(!$data) {
			throw new Exception("Could not find currency");
		}
		return $data->toArray();
	}

	public function getCurrencies()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);

		$currencies = array();
		foreach($data as $currency) {
			$currencies[$currency->code] = $currency->code.' ('.$currency->symbol.')';
		}
		return $currencies;
	}

	public function getCurrencySymbols()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);

		$currencies = array();
		foreach($data as $currency) {
			$currencies[$currency->code] = $currency->symbol;
		}
		return $currencies;
	}
}
