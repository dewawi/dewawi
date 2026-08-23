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
}
