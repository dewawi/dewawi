<?php

class Application_Model_DbTable_Increment extends Zend_Db_Table_Abstract
{

	protected $_name = 'increment';

	protected $_date = null;

	protected $_user = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
	}

	public function getIncrement($type)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_user['clientid']);
		$data = $this->fetchRow($where);
		if(!$data) {
			throw new Exception("Could not find row $type");
		}
		return $data->$type;
	}

	public function setIncrement($id, $type)
	{
		$data = array(
			$type => $id,
		);
		$this->update($data, 'clientid = '. (int)$this->_user['clientid']);
	}
}
