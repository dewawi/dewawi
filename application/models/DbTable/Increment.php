<?php

class Application_Model_DbTable_Increment extends DEEC_Model_DbTable_Entity
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

	public function getIncrement($type, bool $forUpdate = false)
	{
		if ($forUpdate && !$this->getAdapter()->getConnection()->inTransaction()) {
			throw new RuntimeException('Increment lock requires an active transaction');
		}

		// Keep the client counter locked until document finalization commits or rolls back.
		$select = $this->select()->where('clientid = ?', $this->_client['id'])->forUpdate($forUpdate);
		$data = $this->fetchRow($select);
		if(!$data) {
			throw new Exception("Could not find row $type");
		}
		return $data->$type;
	}

	public function setIncrement($increment, $type)
	{
		// Increment by 3
		// TODO move increment value to the db
		$increment += 1;

		$data = array(
			$type => $increment,
		);

		// Update only the specific column for the client
		$where = $this->getAdapter()->quoteInto('clientid = ?', (int)$this->_client['id']);
		$this->update($data, $where);
	}
}
