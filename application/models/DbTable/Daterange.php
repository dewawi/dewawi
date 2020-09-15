<?php

class Application_Model_DbTable_Daterange extends Zend_Db_Table_Abstract
{

	protected $_name = 'daterange';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getDateranges()
	{
		$dateranges = array(
			'0' => 'TOOLBAR_ALL',
			'today' => 'TOOLBAR_TODAY',
			'yesterday' => 'TOOLBAR_YESTERDAY',
			'last7days' => 'TOOLBAR_LAST_7_DAYS',
			'last14days' => 'TOOLBAR_LAST_14_DAYS',
			'last30days' => 'TOOLBAR_LAST_30_DAYS',
			'thisMonth' => 'TOOLBAR_THIS_MONTH',
			'lastMonth' => 'TOOLBAR_LAST_MONTH',
			'thisYear' => 'TOOLBAR_THIS_YEAR',
			'lastYear' => 'TOOLBAR_LAST_YEAR',
			'custom' => 'TOOLBAR_CUSTOM'
		);
		return $dateranges;
	}
}
