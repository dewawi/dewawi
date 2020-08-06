<?php

class Application_Model_DbTable_State extends Zend_Db_Table_Abstract
{

	protected $_name = 'state';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
	    $this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getStates()
	{
		$states = array(
			'100' => 'STATES_CREATED',
			'101' => 'STATES_IN_PROCESS',
			'102' => 'STATES_PLEASE_CHECK',
			'103' => 'STATES_PLEASE_DELETE',
			'104' => 'STATES_RELEASED',
			'105' => 'STATES_COMPLETED',
			'106' => 'STATES_CANCELLED'
		);
		return $states;
	}
}
