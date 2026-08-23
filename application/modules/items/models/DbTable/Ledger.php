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

	public function getStockAt(
		string $date,
		?int $warehouseId = null
	): array {
		$clientId = $this->getClientId();

		$select = $this->select()
			->setIntegrityCheck(false)
			->from(
				['l' => $this->_name],
				[
					'itemid',
					'warehouseid',
					'quantity' => new Zend_Db_Expr(
						'SUM(
							CASE
								WHEN l.type = "outflow"
									THEN -l.quantity
								ELSE l.quantity
							END
						)'
					),
				]
			)
			->join(
				['i' => 'item'],
				'i.id = l.itemid'
					. ' AND i.clientid = l.clientid',
				[
					'sku',
					'itemtitle' => 'title',
				]
			)
			->join(
				['w' => 'warehouse'],
				'w.id = l.warehouseid'
					. ' AND w.clientid = l.clientid',
				[
					'warehousecode' => 'code',
					'warehousetitle' => 'title',
				]
			)
			->where('l.clientid = ?', $clientId)
			->where('l.deleted = ?', 0)
			->where('l.ledgerdate <= ?', $date)
			->group([
				'l.itemid',
				'l.warehouseid',
				'i.sku',
				'i.title',
				'w.code',
				'w.title',
			])
			->order('i.sku ASC');

		if($warehouseId !== null && $warehouseId > 0) {
			$select->where(
				'l.warehouseid = ?',
				$warehouseId
			);
		}

		return $this->fetchAll($select)->toArray();
	}
}
