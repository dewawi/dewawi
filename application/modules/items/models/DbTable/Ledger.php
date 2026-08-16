<?php

class Items_Model_DbTable_Ledger extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'ledger';

	public function getByItemId(int $itemId)
	{
		$select = $this->select()
			->where('itemid = ?', $itemId)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->order('ledgerdate DESC')
			->order('id DESC');

		return $this->fetchAll($select);
	}

	public function getByReference(
		string $module,
		string $type,
		int $referenceId
	): array {
		$select = $this->select()
			->where('referencemodule = ?', $module)
			->where('referencetype = ?', $type)
			->where('referenceid = ?', $referenceId)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->order('ledgerdate ASC')
			->order('id ASC');

		return $this->fetchAll($select)->toArray();
	}
}
