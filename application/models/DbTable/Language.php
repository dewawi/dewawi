<?php

class Application_Model_DbTable_Language extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'language';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getPrimaryLanguage()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
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
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);

		$languages = array();
		foreach($data as $language) {
			$languages[$language->code] = $language->name;
		}
		return $languages;
	}

	public function getSelectOptions(): array
	{
		return $this->getLanguages();
	}
}
