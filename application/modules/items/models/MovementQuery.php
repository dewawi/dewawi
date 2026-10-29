<?php

class Items_Model_MovementQuery
{
	protected array $appliedParams = [];

	public function fetch(array $params, array $options = []): array
	{
		$params = $this->normalizeParams($params);
		$ledgerDb = new Items_Model_DbTable_Ledger();
		$db = $ledgerDb->getAdapter();
		$clientId = $ledgerDb->getClientId();

		$select = $db->select()->from(
			['movement' => $this->buildUnion($db, $clientId)],
			['*']
		);

		$this->applyFilters($select, $params);

		$countSelect = clone $select;
		$countSelect->reset(Zend_Db_Select::COLUMNS);
		$countSelect->columns([new Zend_Db_Expr('COUNT(*)')]);
		$records = (int)$db->fetchOne($countSelect);

		if(!$params['_export'] && $records > 0) {
			$pages = (int)ceil($records / $params['limit']);
			if($params['page'] > $pages) {
				$params['page'] = $pages;
				$params['offset'] = ($pages - 1) * $params['limit'];
			}
		}

		$this->applyOrdering($select, $params);

		if(!$params['_export']) {
			$select->limit($params['limit'], $params['offset']);
		}

		$this->appliedParams = $params;

		return [$db->fetchAll($select), $records, $params];
	}

	public function getAppliedParams(): array
	{
		return $this->appliedParams;
	}

	protected function buildUnion(Zend_Db_Adapter_Abstract $db, int $clientId): Zend_Db_Select
	{
		$ledger = $db->select()
			->from(['l' => 'ledger'], [
				'id',
				'source' => new Zend_Db_Expr($db->quote('ledger')),
				'controller' => new Zend_Db_Expr($db->quote('ledger')),
				'movementdate' => 'l.ledgerdate',
				'itemid',
				'warehouseid',
				'movementtype' => 'l.type',
				'reason' => 'l.reason',
				'signedquantity' => new Zend_Db_Expr("CASE WHEN l.type = 'outflow' THEN -l.quantity ELSE l.quantity END"),
				'status' => new Zend_Db_Expr($db->quote('booked')),
				'referencemodule',
				'referencetype',
				'referenceid',
				'referencepositionid',
				'externalnumber' => new Zend_Db_Expr('NULL'),
				'comment',
			])
			->joinLeft(
				['i' => 'item'],
				'i.id = l.itemid AND i.clientid = l.clientid AND i.deleted = 0',
				['sku', 'itemtitle' => 'title']
			)
			->joinLeft(
				['w' => 'warehouse'],
				'w.id = l.warehouseid AND w.clientid = l.clientid',
				['warehousecode' => 'code', 'warehousetitle' => 'title']
			)
			->where('l.clientid = ?', $clientId)
			->where('l.deleted = ?', 0);

		$planning = $db->select()
			->from(['sp' => 'stockplanning'], [
				'id',
				'source' => new Zend_Db_Expr($db->quote('stockplanning')),
				'controller' => new Zend_Db_Expr($db->quote('stockplanning')),
				'movementdate' => 'sp.planningdate',
				'itemid',
				'warehouseid',
				'movementtype' => 'sp.type',
				'reason' => new Zend_Db_Expr("''"),
				'signedquantity' => new Zend_Db_Expr("CASE WHEN sp.type = 'reservation' THEN -sp.quantity ELSE sp.quantity END"),
				'status',
				'referencemodule',
				'referencetype',
				'referenceid',
				'referencepositionid',
				'externalnumber',
				'comment',
			])
			->joinLeft(
				['i' => 'item'],
				'i.id = sp.itemid AND i.clientid = sp.clientid AND i.deleted = 0',
				['sku', 'itemtitle' => 'title']
			)
			->joinLeft(
				['w' => 'warehouse'],
				'w.id = sp.warehouseid AND w.clientid = sp.clientid',
				['warehousecode' => 'code', 'warehousetitle' => 'title']
			)
			->where('sp.clientid = ?', $clientId)
			->where('sp.deleted = ?', 0);

		return $db->select()->union([$ledger, $planning], Zend_Db_Select::SQL_UNION_ALL);
	}

	protected function normalizeParams(array $params): array
	{
		$params['_export'] = !empty($params['_export']);
		$params['page'] = max(1, (int)($params['page'] ?? 1));
		$params['limit'] = (int)($params['limit'] ?? 25);

		if($params['limit'] <= 0) $params['limit'] = 25;
		if($params['limit'] > 100) $params['limit'] = 100;

		$params['offset'] = $params['_export'] ? 0 : (($params['page'] - 1) * $params['limit']);
		$params['keyword'] = trim((string)($params['keyword'] ?? ''));
		$params['movement'] = (string)($params['movement'] ?? 'all');
		$params['itemid'] = max(0, (int)($params['itemid'] ?? 0));
		$params['item'] = trim((string)($params['item'] ?? ''));
		$params['warehouseid'] = max(0, (int)($params['warehouseid'] ?? 0));
		$params['status'] = (string)($params['status'] ?? 'all');
		$params['daterange'] = (string)($params['daterange'] ?? 'all');
		$params['from'] = (string)($params['from'] ?? '');
		$params['to'] = (string)($params['to'] ?? '');
		$params['order'] = (string)($params['order'] ?? 'movementdate');
		$params['sort'] = DEEC_List_Ordering::direction($params['sort'] ?? 'DESC');

		return $params;
	}

	protected function applyFilters(Zend_Db_Select $select, array $params): void
	{
		$db = $select->getAdapter();

		if($params['keyword'] !== '') {
			$words = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $params['keyword']));

			foreach(array_filter($words) as $word) {
				$value = $db->quote('%' . $word . '%');
				$select->where(
					'(movement.sku LIKE ' . $value
					. ' OR movement.itemtitle LIKE ' . $value
					. ' OR movement.warehousecode LIKE ' . $value
					. ' OR movement.warehousetitle LIKE ' . $value
					. ' OR movement.movementtype LIKE ' . $value
					. ' OR movement.reason LIKE ' . $value
					. ' OR movement.status LIKE ' . $value
					. ' OR movement.referencetype LIKE ' . $value
					. ' OR movement.referenceid LIKE ' . $value
					. ' OR movement.externalnumber LIKE ' . $value
					. ' OR movement.comment LIKE ' . $value . ')'
				);
			}
		}

		switch($params['movement']) {
			case 'booked':
				$select->where('movement.source = ?', 'ledger');
				break;
			case 'planned':
				$select->where('movement.source = ?', 'stockplanning');
				break;
			case 'incoming':
			case 'reservation':
				$select->where('movement.source = ?', 'stockplanning');
				$select->where('movement.movementtype = ?', $params['movement']);
				break;
			case 'all':
				break;
			default:
				$select->where('1 = 0');
		}

		if($params['itemid'] > 0) {
			$select->where('movement.itemid = ?', $params['itemid']);
		} elseif($params['item'] !== '') {
			$value = $db->quote('%' . preg_replace('/[^\p{L}\p{N}._\-\s]/u', '', $params['item']) . '%');
			$select->where('(movement.sku LIKE ' . $value . ' OR movement.itemtitle LIKE ' . $value . ')');
		}

		if($params['warehouseid'] > 0) {
			$select->where('movement.warehouseid = ?', $params['warehouseid']);
		}

		$statuses = ['booked', 'draft', 'planned', 'completed', 'cancelled', 'released'];
		if($params['status'] !== 'all') {
			if(in_array($params['status'], $statuses, true)) {
				$select->where('movement.status = ?', $params['status']);
			} else {
				$select->where('1 = 0');
			}
		}

		if($params['daterange'] !== 'all') {
			$from = $this->normalizeDate($params['from']);
			$to = $this->normalizeDate($params['to']);

			if($from !== null && $to !== null) {
				$select->where('movement.movementdate >= ?', $from . ' 00:00:00');
				$select->where('movement.movementdate <= ?', $to . ' 23:59:59');
			} else {
				$select->where('1 = 0');
			}
		}
	}

	protected function normalizeDate(string $date): ?string
	{
		$value = DateTime::createFromFormat('!Y-m-d', $date);
		return $value && $value->format('Y-m-d') === $date ? $date : null;
	}

	protected function applyOrdering(Zend_Db_Select $select, array $params): void
	{
		$orders = [
			'movementdate' => 'movement.movementdate',
			'sku' => 'movement.sku',
			'itemtitle' => 'movement.itemtitle',
			'warehousetitle' => 'movement.warehousetitle',
			'movementtype' => 'movement.movementtype',
			'signedquantity' => 'movement.signedquantity',
			'status' => 'movement.status',
		];
		$order = $orders[$params['order']] ?? $orders['movementdate'];
		$select->order($order . ' ' . $params['sort']);
		$select->order('movement.movementdate DESC');
		$select->order('movement.id DESC');
		$select->order('movement.source ASC');
	}
}
