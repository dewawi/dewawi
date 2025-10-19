<?php

class Shops_Model_DbTable_Inquirydata extends Zend_Db_Table_Abstract
{

	protected $_name = 'shopinquirydata';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function save($formid, $shopid, $token, array $data, $clientid)
	{
		// Insert new
		return (int)$this->insert([
			'formid' => $formid,
			'shopid' => $shopid,
			'clientid' => $clientid,
			'token' => $token,
			'data' => json_encode($data),
			'created' => $this->_date,
			'modified' => $this->_date,
		]);
	}
}
