<?php

class Shops_Model_DbTable_Filename extends Zend_Db_Table_Abstract
{

	protected $_name = 'filename';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getFilename($type, $language)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('language = ?', $language);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
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
		$this->update($data, 'clientid = '. (int)$this->_shop['clientid']);
	}
}
