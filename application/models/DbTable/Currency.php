<?php

class Application_Model_DbTable_Currency extends Zend_Db_Table_Abstract
{

	protected $_name = 'currency';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
	    $this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getCurrencies()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
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
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);

		$currencies = array();
		foreach($data as $currency) {
			$currencies[$currency->code] = $currency->symbol;
		}
		return $currencies;
	}
}
