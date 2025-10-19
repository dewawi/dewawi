<?php

class Shops_Model_DbTable_Inquiryform extends Zend_Db_Table_Abstract
{

	protected $_name = 'shopinquiryform';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getInquiryform($id)
	{
		$where = [
			$this->getAdapter()->quoteInto('id = ?', $id),
			$this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']),
			$this->getAdapter()->quoteInto('deleted = ?', 0),
		];
		$row = $this->fetchRow($where);
		return $row ? $row->toArray() : null;
	}
}
