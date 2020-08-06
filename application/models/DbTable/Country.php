<?php

class Application_Model_DbTable_Country extends Zend_Db_Table_Abstract
{

	protected $_name = 'country';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
	    $this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getCountries()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$data = $this->fetchAll($where);

		$countries = array();
		foreach($data as $country) {
			$countries[$country->code] = $country->name;
		}
		asort($countries);
		return $countries;
	}
}
