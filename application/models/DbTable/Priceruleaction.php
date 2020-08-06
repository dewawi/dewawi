<?php

class Application_Model_DbTable_Priceruleaction extends Zend_Db_Table_Abstract
{

	protected $_name = 'pricerule';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
	    $this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getPriceruleactions()
	{
        $priceruleaction = array(
                'bypercent' => 'ITEMS_PRICE_RULE_BY_PERCENT',
                'byfixed' => 'ITEMS_PRICE_RULE_BY_FIXED',
                'topercent' => 'ITEMS_PRICE_RULE_TO_PERCENT',
                'tofixed' => 'ITEMS_PRICE_RULE_TO_FIXED'
                );
		return $priceruleaction;
	}
}
