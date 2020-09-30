<?php

class Application_Model_DbTable_Filename extends Zend_Db_Table_Abstract
{

	protected $_name = 'filename';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getFilename($type, $language)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('language = ?', $language);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$data = $this->fetchRow($where);
		if(!$data) {
			throw new Exception("Could not find row $type");
		}
		return $data->$type;
	}

	public function setFilename($id, $type)
	{
		$data = array(
			$type => $id,
		);
		$this->update($data, 'clientid = '. (int)$this->_client['id']);
	}
}
