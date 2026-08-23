<?php

class Items_Model_DbTable_Itemstock extends Zend_Db_Table_Abstract
{
	protected $_name = 'itemstock';

	public function changeQuantity(
		int $itemId,
		int $warehouseId,
		float $delta
	): void {
		$client = Zend_Registry::get('Client');
		$user = Zend_Registry::get('User');
		$date = date('Y-m-d H:i:s');

		$sql = '
			INSERT INTO `itemstock` (
				`itemid`,
				`warehouseid`,
				`quantity`,
				`clientid`,
				`created`,
				`createdby`
			)
			VALUES (?, ?, ?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				`quantity` = `quantity` + VALUES(`quantity`),
				`modified` = VALUES(`created`),
				`modifiedby` = VALUES(`createdby`)
		';

		$this->getAdapter()->query($sql, [
			$itemId,
			$warehouseId,
			$delta,
			(int)$client['id'],
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
			->where(
				'clientid = ?',
				(int)Zend_Registry::get('Client')['id']
			);

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
		$clientId = $this->getClientId();

		$select = $this->select()
			->setIntegrityCheck(false)
			->from(
				['s' => $this->_name],
				[
					'total' => new Zend_Db_Expr(
						'SUM(
							s.quantity * (
								CASE
									WHEN i.parentid > 0
										AND (
											i.cost IS NULL
											OR i.cost = 0
										)
										THEN p.cost
									ELSE i.cost
								END
							)
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
			->where('s.clientid = ?', $clientId);

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
