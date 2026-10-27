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
		?int $warehouseId = null,
		array $filters = []
	): array {
		return $this->fetchAll(
			$this->buildStockAtSelect($date, $warehouseId, $filters)
		)->toArray();
	}

	protected function buildStockAtSelect(
		string $date,
		?int $warehouseId = null,
		array $filters = []
	): Zend_Db_Table_Select {
		$clientId = $this->getClientId();
		$quantityExpression = '
			SUM(
				CASE
					WHEN l.type = "outflow"
						THEN -l.quantity
					ELSE l.quantity
				END
			)
		';

		$select = $this->select()
			->setIntegrityCheck(false)
			->from(
				['l' => $this->_name],
				[
					'itemid',
					'warehouseid',
					'quantity' => new Zend_Db_Expr($quantityExpression),
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
			->where('i.deleted = ?', 0)
			->where('l.ledgerdate <= ?', $date)
			->group([
				'l.itemid',
				'l.warehouseid',
				'i.sku',
				'i.title',
				'w.code',
				'w.title',
			]);

		if($warehouseId !== null && $warehouseId > 0) {
			$select->where(
				'l.warehouseid = ?',
				$warehouseId
			);
		}

		$categoryIds = array_values(array_filter(
			array_map('intval', (array)($filters['categoryids'] ?? [])),
			static fn(int $id): bool => $id > 0
		));

		if($categoryIds) {
			// Category assignments are current master data because they are not historized.
			$select->where('i.catid IN (?)', array_unique($categoryIds));
		}

		$manufacturerId = (int)($filters['manufacturerid'] ?? 0);

		if($manufacturerId > 0) {
			// Manufacturer assignments are current master data because they are not historized.
			$select->where('i.manufacturerid = ?', $manufacturerId);
		}

		$keyword = trim((string)($filters['keyword'] ?? ''));

		if($keyword !== '') {
			$words = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $keyword));

			foreach(array_filter($words) as $word) {
				$quoted = $this->getAdapter()->quote('%' . $word . '%');
				$select->where(
					'(i.sku LIKE ' . $quoted
					. ' OR i.title LIKE ' . $quoted
					. ' OR w.code LIKE ' . $quoted
					. ' OR w.title LIKE ' . $quoted . ')'
				);
			}
		}

		$statusOperators = [
			'positive' => '>',
			'zero' => '=',
			'negative' => '<',
		];
		$stockStatus = (string)($filters['stockstatus'] ?? 'all');

		if($stockStatus !== 'all' && isset($statusOperators[$stockStatus])) {
			$select->having(
				$quantityExpression . ' ' . $statusOperators[$stockStatus] . ' ?',
				0
			);
		} elseif($stockStatus !== 'all' && $stockStatus !== '') {
			$select->having('1 = 0');
		}

		$orderColumns = [
			'sku' => 'i.sku',
			'itemtitle' => 'i.title',
			'warehousecode' => 'w.code',
			'warehousetitle' => 'w.title',
			'quantity' => 'quantity',
		];
		$order = (string)($filters['order'] ?? 'sku');
		$sort = strtoupper((string)($filters['sort'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
		$select->order(isset($orderColumns[$order])
			? $orderColumns[$order] . ' ' . $sort
			: 'i.sku ASC'
		);

		return $select;
	}
}
