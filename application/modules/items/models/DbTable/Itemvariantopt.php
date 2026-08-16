<?php

class Items_Model_DbTable_Itemvariantopt extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'itemvariantopt';

	public function getByItemId(int $itemId)
	{
		$select = $this->select()
			->where('itemid = ?', $itemId)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->order('id ASC');

		return $this->fetchAll($select);
	}
}
