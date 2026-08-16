<?php

class Items_Model_DbTable_Itemvariantopt extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'itemvariantopt';

	public function getByItemId(int $itemId)
	{
		return $this->fetchAll([
			$this->getAdapter()->quoteInto(
				'itemid = ?',
				$itemId
			),
			$this->getAdapter()->quoteInto(
				'clientid = ?',
				$this->getClientId()
			),
			$this->getAdapter()->quoteInto(
				'deleted = ?',
				0
			),
		]);
	}
}
