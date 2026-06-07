<?php

class Shops_Model_DbTable_Slide extends Zend_Db_Table_Abstract
{
	protected $_name = 'slide';

	protected $_shop = null;

	public function init()
	{
		$this->_shop = Zend_Registry::get('Shop');
	}

	public function getByPosition(string $position, int $shopid): ?array
	{
		$select = $this->select()
			->where('shopid = ?', $shopid)
			->where('position = ?', $position)
			->where('clientid = ?', (int)$this->_shop['clientid'])
			->where('deleted = ?', 0)
			->where('activated = ?', 1)
			->order('ordering ASC')
			->limit(1);

		$row = $this->fetchRow($select);

		return $row ? $row->toArray() : null;
	}
}
