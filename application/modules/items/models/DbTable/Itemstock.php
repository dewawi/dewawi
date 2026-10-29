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
				`reserved`,
				`incoming`,
				`clientid`,
				`created`,
				`createdby`,
				`deleted`
			)
			VALUES (?, ?, ?, 0, 0, ?, ?, ?, 0)
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

	public function syncPlanningTotals(int $itemId, int $warehouseId): void
	{
		$user = Zend_Registry::get('User');
		$date = date('Y-m-d H:i:s');
		$sql = '
			INSERT INTO `itemstock` (
				`itemid`, `warehouseid`, `quantity`, `reserved`, `incoming`,
				`clientid`, `created`, `createdby`, `deleted`
			)
			SELECT
				?, ?, 0,
				COALESCE(SUM(CASE
					WHEN `type` = "reservation" AND `status` = "planned"
						THEN `quantity` - `fulfilled`
					ELSE 0
				END), 0),
				COALESCE(SUM(CASE
					WHEN `type` = "incoming" AND `status` = "planned"
						THEN `quantity` - `fulfilled`
					ELSE 0
				END), 0),
				?, ?, ?, 0
			FROM `stockplanning`
			WHERE `itemid` = ?
				AND `warehouseid` = ?
				AND `clientid` = ?
				AND `deleted` = 0
			ON DUPLICATE KEY UPDATE
				`reserved` = VALUES(`reserved`),
				`incoming` = VALUES(`incoming`),
				`modified` = VALUES(`created`),
				`modifiedby` = VALUES(`createdby`),
				`deleted` = 0
		';

		$this->getAdapter()->query($sql, [
			$itemId,
			$warehouseId,
			$this->getClientId(),
			$date,
			(int)$user['id'],
			$itemId,
			$warehouseId,
			$this->getClientId(),
		]);
	}

	public function lockPlanningScope(int $itemId, int $warehouseId): void
	{
		$user = Zend_Registry::get('User');
		$this->getAdapter()->query(
			'INSERT IGNORE INTO `itemstock` (
				`itemid`, `warehouseid`, `quantity`, `reserved`, `incoming`,
				`clientid`, `created`, `createdby`, `deleted`
			) VALUES (?, ?, 0, 0, 0, ?, ?, ?, 0)',
			[
				$itemId,
				$warehouseId,
				$this->getClientId(),
				date('Y-m-d H:i:s'),
				(int)$user['id'],
			]
		);

		$this->fetchRow(
			$this->select()
				->where('itemid = ?', $itemId)
				->where('warehouseid = ?', $warehouseId)
				->where('clientid = ?', $this->getClientId())
				->limit(1)
				->forUpdate(true)
		);
	}

	public function getByItemWarehouse(int $itemId, int $warehouseId): array
	{
		$select = $this->select()
			->where('itemid = ?', $itemId)
			->where('warehouseid = ?', $warehouseId)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->limit(1);
		$row = $this->fetchRow($select);

		return $row ? $row->toArray() : [
			'quantity' => 0,
			'reserved' => 0,
			'incoming' => 0,
		];
	}

	public function getByItemId(int $itemId): array
	{
		return $this->fetchAll(
			$this->select()
				->where('itemid = ?', $itemId)
				->where('clientid = ?', $this->getClientId())
				->where('deleted = ?', 0)
				->order('warehouseid ASC')
		)->toArray();
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
						'COALESCE(SUM('
							. (string)Items_Model_Entity_Stock::stockValueExpression()
							. '), 0)'
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
			->join(
				['w' => 'warehouse'],
				'w.id = s.warehouseid'
					. ' AND w.clientid = s.clientid',
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
