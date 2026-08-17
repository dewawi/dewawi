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

	public function addOption(
		int $itemId,
		int $optionId
	): int {
		$select = $this->select()
			->where('itemid = ?', $itemId)
			->where('itemoptid = ?', $optionId)
			->where('clientid = ?', $this->getClientId())
			->limit(1);

		$row = $this->fetchRow($select);

		if($row) {
			if((int)$row->deleted === 1) {
				$this->update(
					[
						'deleted' => 0,
						'modified' => $this->_date,
						'modifiedby' => $this->getUserId(),
					],
					[
						$this->getAdapter()->quoteInto(
							'id = ?',
							(int)$row->id
						),
						$this->getAdapter()->quoteInto(
							'clientid = ?',
							$this->getClientId()
						),
					]
				);
			}

			return (int)$row->id;
		}

		return $this->create([
			'itemid' => $itemId,
			'itemoptid' => $optionId,
		]);
	}

	public function deleteOption(
		int $itemId,
		int $optionId
	): void {
		$select = $this->select()
			->where('itemid = ?', $itemId)
			->where('itemoptid = ?', $optionId)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->limit(1);

		$row = $this->fetchRow($select);

		if(!$row) {
			return;
		}

		$this->deleteById(
			(int)$row->id
		);
	}
}
