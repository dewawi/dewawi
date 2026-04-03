<?php

class Shops_Model_DbTable_Itematrset extends Zend_Db_Table_Abstract
{

	protected $_name = 'itematrset';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getPositionSets($parentid)
	{
		$parentid = (int)$parentid;

		$select = $this->select()
			->where('parentid = ?', $parentid)
			->where('clientid = ?', $this->_shop['clientid'])
			->where('deleted = ?', 0)
			->order('ordering');

		$data = $this->fetchAll($select);
		if (!$data) {
			throw new Exception("Could not find row $parentid");
		}

		return $data->toArray();
	}
}
