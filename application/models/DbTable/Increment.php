<?php

class Application_Model_DbTable_Increment extends Zend_Db_Table_Abstract
{

	protected $_name = 'increment';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getIncrement($type)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$data = $this->fetchRow($where);
		if(!$data) {
			throw new Exception("Could not find row $type");
		}
		return $data->$type;
	}

	public function setIncrement($increment, $type)
	{
		// Increment by 3
		// TODO move increment value to the db
		$increment += 3;

		$data = array(
		    $type => $increment,
		);

		// Update only the specific column for the client
		$where = $this->getAdapter()->quoteInto('clientid = ?', (int)$this->_client['id']);
		$this->update($data, $where);
	}
}
