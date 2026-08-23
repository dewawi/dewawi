<?php

class Items_Model_DbTable_Itemstock extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'itemstock';

	public function changeQuantity(
		int $itemId,
		int $warehouseId,
		float $delta
	): void {
		$user = Zend_Registry::get('User');
		$date = date('Y-m-d H:i:s');

		$sql = '
			INSERT INTO `itemstock` (
				`itemid`,
				`warehouseid`,
				`quantity`,
				`clientid`,
				`created`,
				`createdby`,
				`deleted`
			)
			VALUES (?, ?, ?, ?, ?, ?, 0)
			ON DUPLICATE KEY UPDATE
				`quantity` = COALESCE(`quantity`, 0)
					+ VALUES(`quantity`),
				`modified` = VALUES(`created`),
				`modifiedby` = VALUES(`createdby`),
				`deleted` = 0
		';

		$this->getAdapter()->query($sql, [
			$itemId,
			$warehouseId,
			$delta,
			$this->getClientId(),
			$date,
			(int)$user['id'],
		]);
	}

	public function getTotalsByItemId(int $itemId): array
	{
		$select = $this->select()
			->from(
				$this->_name,
				[
					'quantity' => new Zend_Db_Expr(
						'COALESCE(SUM(quantity), 0)'
					),
					'reserved' => new Zend_Db_Expr(
						'COALESCE(SUM(reserved), 0)'
					),
					'incoming' => new Zend_Db_Expr(
						'COALESCE(SUM(incoming), 0)'
					),
				]
			)
			->where('itemid = ?', $itemId)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0);

		$row = $this->fetchRow($select);

		return $row
			? $row->toArray()
			: [
				'quantity' => 0,
				'reserved' => 0,
				'incoming' => 0,
			];
	}

	public function getTotalValue(
		?int $warehouseId = null
	): float {
		$select = $this->select()
			->setIntegrityCheck(false)
			->from(
				['s' => $this->_name],
				[
					'total' => new Zend_Db_Expr(
						'COALESCE(
							SUM(
								COALESCE(s.quantity, 0) * (
									CASE
										WHEN i.parentid > 0
											AND (
												i.cost IS NULL
												OR i.cost = 0
											)
											THEN COALESCE(p.cost, 0)
										ELSE COALESCE(i.cost, 0)
									END
								)
							),
							0
						)'
					),
				]
			)
			->join(
				['i' => 'item'],
				'i.id = s.itemid'
					. ' AND i.clientid = s.clientid',
				[]
			)
			->joinLeft(
				['p' => 'item'],
				'p.id = i.parentid'
					. ' AND p.clientid = i.clientid',
				[]
			)
			->where('s.clientid = ?', $this->getClientId())
			->where('s.deleted = ?', 0)
			->where('i.deleted = ?', 0);

		if($warehouseId !== null && $warehouseId > 0) {
			$select->where(
				's.warehouseid = ?',
				$warehouseId
			);
		}

		$value = $this->getAdapter()->fetchOne($select);

		return (float)$value;
	}
}
