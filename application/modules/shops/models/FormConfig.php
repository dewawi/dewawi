<?php

class Shops_Model_FormConfig
{
	protected $_db;

	public function __construct()
	{
		$this->_db = Zend_Db_Table::getDefaultAdapter();
	}

	public function getConfig()
	{
		$row = $this->_db->fetchRow("SELECT fields FROM shopinquiryform WHERE deleted = 0");
		return json_decode($row['fields'], true);
	}
}
