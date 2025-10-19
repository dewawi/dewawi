<?php

class Shops_Model_DbTable_Language extends Zend_Db_Table_Abstract
{

	protected $_name = 'language';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getPrimaryLanguage()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchRow($where, 'ordering');
		if(!$data) {
			throw new Exception("Could not find language");
		}
		return $data->toArray();
	}

	public function getLanguages()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_shop['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);

		$languages = array();
		foreach($data as $language) {
			$languages[$language->code] = $language->name;
		}
		return $languages;
	}
}
