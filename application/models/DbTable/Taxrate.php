<?php

class Application_Model_DbTable_Taxrate extends Zend_Db_Table_Abstract
{

	protected $_name = 'taxrate';

	protected $_date = null;

	protected $_user = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
	}

	public function getTaxrate($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getTaxrates()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_user['clientid']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);

		$taxrates = array();
		$locale = Zend_Registry::get('Zend_Locale');
		foreach($data as $taxrate) {
			$taxrates[$taxrate->id] = $taxrate->rate;
			//$taxrates[$taxrate->id] = Zend_Locale_Format::toNumber($taxrate->rate,array('precision' => 1,'locale' => $locale)).' %';
		}
		return $taxrates;
	}
}
