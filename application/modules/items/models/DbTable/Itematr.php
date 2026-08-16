<?php

class Items_Model_DbTable_Itematr extends DEEC_Model_DbTable_Position
{
	protected $_name = 'itematr';

	protected string $setField = 'atrsetid';

	public function getPositionsBySku(
		string $sku,
		?int $setId = null
	) {
		$select = $this->select()
			->where('sku = ?', $sku)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->order('ordering ASC');

		if($setId !== null) {
			$select->where(
				'atrsetid = ?',
				$setId
			);
		}

		return $this->fetchAll($select);
	}
}
