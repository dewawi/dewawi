<?php

class Application_Model_DbTable_Uom extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'uom';

	public function getUoms(): array
	{
		$select = $this->select()
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->order('ordering ASC')
			->order('id ASC');

		$rows = $this->fetchAll($select);

		$uoms = [];

		foreach ($rows as $uom) {
			$uoms[(int)$uom->id] = $uom->title;
		}

		return $uoms;
	}

	public function getSelectOptions(): array
	{
		return $this->getUoms();
	}
}
