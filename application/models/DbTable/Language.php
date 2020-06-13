<?php

class Application_Model_DbTable_Language extends Zend_Db_Table_Abstract
{

	protected $_name = 'language';

	protected $_date = null;

	protected $_user = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
	}

	public function getLanguages()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);

		$languages = array();
		foreach($data as $language) {
			$languages[$language->code] = $language->name;
		}
		return $languages;
	}
}
