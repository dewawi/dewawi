<?php

class Items_Service_StockPlanning
{
	protected Items_Model_DbTable_Stockplanning $_planningDb;
	protected Items_Model_DbTable_Itemstock $_stockDb;

	public function __construct()
	{
		$this->_planningDb = new Items_Model_DbTable_Stockplanning();
		$this->_stockDb = new Items_Model_DbTable_Itemstock();
	}

	public function validate(array $data, int $excludeId = 0, bool $checkAvailability = true): array
	{
		$type = (string)($data['type'] ?? '');
		$status = (string)($data['status'] ?? 'draft');
		$itemId = (int)($data['itemid'] ?? 0);
		$warehouseId = (int)($data['warehouseid'] ?? 0);
		$quantity = round((float)($data['quantity'] ?? 0), 4);
		$fulfilled = round((float)($data['fulfilled'] ?? 0), 4);

		if(!in_array($type, ['incoming', 'reservation'], true)) {
			throw new InvalidArgumentException('ITEMS_PLANNING_INVALID_TYPE');
		}
		if(!in_array($status, ['draft', 'planned', 'completed', 'cancelled', 'released'], true)) {
			throw new InvalidArgumentException('ITEMS_PLANNING_INVALID_STATUS');
		}
		if($quantity < 0 || $fulfilled < 0 || $fulfilled > $quantity) {
			throw new InvalidArgumentException('ITEMS_PLANNING_INVALID_QUANTITY');
		}
		if($status === 'completed' && $fulfilled < $quantity) {
			throw new InvalidArgumentException('ITEMS_PLANNING_INVALID_STATUS');
		}

		if($itemId > 0) {
			$item = (new Items_Model_DbTable_Item())->getById($itemId);
			if(!$item) throw new InvalidArgumentException('MESSAGES_ITEM_NOT_FOUND');
		}
		if($warehouseId > 0) {
			$warehouse = (new Application_Model_DbTable_Warehouse())
				->setClientId($this->_planningDb->getClientId())
				->getById($warehouseId);
			if(!$warehouse) throw new InvalidArgumentException('MESSAGES_WAREHOUSE_REQUIRED');
		}

		$this->validateContact((int)($data['contactid'] ?? 0));
		$this->validateReference($data, $itemId);
		$this->validateLinkedPlanning($data, $excludeId);

		if($status !== 'planned') return $data;
		if($itemId <= 0) throw new InvalidArgumentException('MESSAGES_ITEM_NOT_FOUND');
		if($warehouseId <= 0) throw new InvalidArgumentException('MESSAGES_WAREHOUSE_REQUIRED');
		if($quantity <= 0) throw new InvalidArgumentException('ITEMS_PLANNING_INVALID_QUANTITY');
		if(empty($data['planningdate'])) throw new InvalidArgumentException('ITEMS_PLANNING_DATE_REQUIRED');

		$this->validateDate((string)$data['planningdate']);
		$this->validateDuplicate($data, $excludeId);

		if($type === 'reservation' && $checkAvailability) {
			$availability = $this->getAvailability(
				$itemId,
				$warehouseId,
				(string)$data['planningdate'],
				$quantity - $fulfilled,
				$excludeId
			);

			if($availability['shortage'] > 0 && empty($data['allowoverbooking'])) {
				throw new RuntimeException(
					'ITEMS_PLANNING_SHORTAGE:' . number_format($availability['shortage'], 4, '.', '')
				);
			}
		}

		return $data;
	}

	public function getAvailability(
		int $itemId,
		int $warehouseId,
		?string $date = null,
		float $requestedQuantity = 0,
		int $excludeId = 0
	): array {
		$targetDate = $date ?: date('Y-m-d');
		$this->validateDate($targetDate);
		$stock = $this->_stockDb->getByItemWarehouse($itemId, $warehouseId);
		$events = $this->_planningDb->getOpenEvents($itemId, $warehouseId, $excludeId);

		$result = $this->calculateAvailability(
			(float)$stock['quantity'],
			$events,
			$targetDate,
			$requestedQuantity
		);
		$result['reserved'] = (float)$stock['reserved'];
		$result['incoming'] = (float)$stock['incoming'];
		$result['currentAvailable'] = $result['physical'] - $result['reserved'];

		return $result;
	}

	public function calculateAvailability(
		float $physical,
		array $events,
		string $targetDate,
		float $requestedQuantity = 0
	): array {
		$this->validateDate($targetDate);
		$today = date('Y-m-d');
		$timeline = [[
			'date' => $today,
			'type' => 'stock',
			'quantity' => $physical,
			'forecast' => $physical,
		]];
		$available = $physical;
		$futureReservations = 0.0;
		$openIncoming = 0.0;
		$grouped = [];

		foreach($events as $event) {
			$type = (string)$event['type'];
			$remaining = max(0, (float)($event['remaining'] ?? ((float)$event['quantity'] - (float)$event['fulfilled'])));
			if($remaining <= 0) continue;

			$date = (string)($event['planningdate'] ?? '');
			if($type === 'incoming') {
				$openIncoming += $remaining;
				if(empty($event['dateconfirmed']) || $date === '') continue;
			} elseif($type === 'reservation') {
				$futureReservations += $remaining;
				if($date === '') $date = $today;
			} else {
				continue;
			}

			$effectiveDate = $date < $today ? $today : $date;
			$grouped[$effectiveDate][] = [
				'id' => (int)($event['id'] ?? 0),
				'type' => $type,
				'quantity' => $remaining,
			];
		}

		ksort($grouped);
		$earliestDate = null;
		foreach($grouped as $eventDate => $dateEvents) {
			usort($dateEvents, static function(array $left, array $right): int {
				return ($left['type'] === 'incoming' ? 0 : 1) <=> ($right['type'] === 'incoming' ? 0 : 1)
					?: $left['id'] <=> $right['id'];
			});

			foreach($dateEvents as $event) {
				$signed = $event['type'] === 'incoming' ? $event['quantity'] : -$event['quantity'];
				$available += $signed;
				$timeline[] = [
					'date' => $eventDate,
					'type' => $event['type'],
					'quantity' => $signed,
					'forecast' => $available,
					'id' => $event['id'],
				];
			}

			if($earliestDate === null && $requestedQuantity > 0 && $available >= $requestedQuantity) {
				$earliestDate = $eventDate;
			}
		}

		if($requestedQuantity > 0) {
			$todayAvailable = $physical;
			foreach($timeline as $row) {
				if($row['date'] === $today && $row['type'] !== 'stock') $todayAvailable = $row['forecast'];
			}
			if($todayAvailable >= $requestedQuantity) $earliestDate = $today;
		}

		$targetProjected = $physical;
		$targetAvailable = $physical;
		foreach($timeline as $row) {
			if($row['type'] === 'stock' || $row['date'] > $targetDate) continue;
			if($row['type'] === 'incoming') $targetProjected += (float)$row['quantity'];
			$targetAvailable = (float)$row['forecast'];
		}

		return [
			'physical' => $physical,
			'openIncoming' => $openIncoming,
			'futureReservations' => $futureReservations,
			'projectedStock' => $targetProjected,
			'available' => $targetAvailable,
			'shortage' => (float)max(0, $requestedQuantity - $targetAvailable),
			'earliestDate' => $earliestDate,
			'timeline' => $timeline,
		];
	}

	public function fulfill(int $id, float $quantity, ?string $ledgerDate = null, ?string $expectedType = null): int
	{
		$quantity = round($quantity, 4);
		if($quantity <= 0) throw new InvalidArgumentException('ITEMS_PLANNING_INVALID_QUANTITY');
		$db = $this->_planningDb;
		$adapter = $db->getAdapter();
		$adapter->beginTransaction();

		try {
			$row = $db->getByIdForUpdate($id);
			if(!$row || $row['status'] !== 'planned') throw new RuntimeException('ITEMS_PLANNING_NOT_OPEN');
			if($expectedType !== null && $row['type'] !== $expectedType) throw new RuntimeException('ITEMS_PLANNING_INVALID_OPERATION');
			$remaining = round((float)$row['quantity'] - (float)$row['fulfilled'], 4);
			if($quantity > $remaining) throw new InvalidArgumentException('ITEMS_PLANNING_QUANTITY_EXCEEDS_REMAINING');

			$ledgerData = [
				'itemid' => (int)$row['itemid'],
				'warehouseid' => (int)$row['warehouseid'],
				'type' => $row['type'] === 'incoming' ? 'inflow' : 'outflow',
				'reason' => $row['type'] === 'incoming' ? 'receipt' : 'delivery',
				'quantity' => $quantity,
				'ledgerdate' => $ledgerDate ?: date('Y-m-d H:i:s'),
				'referencemodule' => 'items',
				'referencetype' => 'stockplanning',
				'referenceid' => $id,
				'referencepositionid' => null,
				'comment' => (string)($row['comment'] ?? ''),
			];
			$stock = new Items_Service_Stock();
			$ledgerData = $stock->prepareCreateData($ledgerData);
			$ledgerDb = new Items_Model_DbTable_Ledger();
			$ledgerId = $ledgerDb->create($ledgerData);
			$ledger = $ledgerDb->getById($ledgerId);
			if(!$ledger) throw new RuntimeException('MESSAGES_LEDGER_NOT_FOUND');
			$stock->apply($ledger);

			$fulfilled = round((float)$row['fulfilled'] + $quantity, 4);
			$db->updateById($id, [
				'fulfilled' => $fulfilled,
				'status' => $fulfilled >= (float)$row['quantity'] ? 'completed' : 'planned',
			]);
			$this->sync((int)$row['itemid'], (int)$row['warehouseid']);
			$adapter->commit();
			return $ledgerId;
		} catch(Throwable $exception) {
			$adapter->rollBack();
			throw $exception;
		}
	}

	public function revertFulfillment(array $ledger): void
	{
		if(($ledger['referencemodule'] ?? '') !== 'items' || ($ledger['referencetype'] ?? '') !== 'stockplanning') return;
		$id = (int)($ledger['referenceid'] ?? 0);
		if($id <= 0) return;

		$row = $this->_planningDb->getByIdForUpdate($id);
		if(!$row) return;
		$fulfilled = max(0, (float)$row['fulfilled'] - (float)$ledger['quantity']);
		$status = $row['status'] === 'completed' ? 'planned' : $row['status'];
		$this->_planningDb->updateById($id, ['fulfilled' => $fulfilled, 'status' => $status]);
		$this->sync((int)$row['itemid'], (int)$row['warehouseid']);
	}

	public function sync(int $itemId, int $warehouseId): void
	{
		if($itemId > 0 && $warehouseId > 0) $this->_stockDb->syncPlanningTotals($itemId, $warehouseId);
	}

	public function lockScope(int $itemId, int $warehouseId): void
	{
		if($itemId > 0 && $warehouseId > 0) $this->_stockDb->lockPlanningScope($itemId, $warehouseId);
	}

	protected function validateDate(string $date): void
	{
		$value = DateTime::createFromFormat('!Y-m-d', $date);
		$errors = DateTime::getLastErrors();
		if(!$value || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $value->format('Y-m-d') !== $date) {
			throw new InvalidArgumentException('ITEMS_PLANNING_INVALID_DATE');
		}
	}

	protected function validateContact(int $contactId): void
	{
		if($contactId <= 0) return;
		$permission = new DEEC_Permission((array)Zend_Registry::get('User'));
		if(!$permission->hasActionPermission('contacts', 'contact', 'view')) throw new InvalidArgumentException('MESSAGES_ACCESS_DENIED');
		$client = Zend_Registry::get('Client');
		$contactClientId = (int)($client['modules']['contacts'] ?? $client['id']);
		$contact = (new Contacts_Model_DbTable_Contact())->setClientId($contactClientId)->getById($contactId);
		if(!$contact) throw new InvalidArgumentException('ITEMS_PLANNING_INVALID_CONTACT');
	}

	protected function validateReference(array $data, int $itemId): void
	{
		$module = trim((string)($data['referencemodule'] ?? ''));
		$type = trim((string)($data['referencetype'] ?? ''));
		$referenceId = (int)($data['referenceid'] ?? 0);
		$positionId = (int)($data['referencepositionid'] ?? 0);
		if($module === '' && $type === '' && $referenceId === 0 && $positionId === 0) return;

		$allowed = [
			'purchases:purchaseorder' => ['Purchases_Model_DbTable_Purchaseorder', 'Purchases_Model_DbTable_Purchaseorderpos'],
			'sales:salesorder' => ['Sales_Model_DbTable_Salesorder', 'Sales_Model_DbTable_Salesorderpos'],
		];
		$key = $module . ':' . $type;
		if(!isset($allowed[$key]) || $referenceId <= 0) throw new InvalidArgumentException('ITEMS_PLANNING_INVALID_REFERENCE');
		$expectedReference = ($data['type'] ?? '') === 'incoming' ? 'purchases:purchaseorder' : 'sales:salesorder';
		if($key !== $expectedReference) throw new InvalidArgumentException('ITEMS_PLANNING_INVALID_REFERENCE');

		$permission = new DEEC_Permission((array)Zend_Registry::get('User'));
		if(!$permission->hasActionPermission($module, $type, 'view')) throw new InvalidArgumentException('MESSAGES_ACCESS_DENIED');
		$client = Zend_Registry::get('Client');
		$clientId = (int)($client['modules'][$module] ?? $client['id']);
		[$documentClass, $positionClass] = $allowed[$key];
		$document = (new $documentClass())->setClientId($clientId)->getById($referenceId);
		if(!$document) throw new InvalidArgumentException('ITEMS_PLANNING_INVALID_REFERENCE');

		if($positionId > 0) {
			$position = (new $positionClass())->setClientId($clientId)->getById($positionId);
			if(!$position || (int)$position['parentid'] !== $referenceId || ($itemId > 0 && (int)$position['itemid'] !== $itemId)) {
				throw new InvalidArgumentException('ITEMS_PLANNING_INVALID_REFERENCE_POSITION');
			}
			$item = $itemId > 0 ? (new Items_Model_DbTable_Item())->getById($itemId) : null;
			if($item && !empty($position['uom']) && !empty($item['uomid'])) {
				$uom = (new Application_Model_DbTable_Uom())
					->setClientId($this->_planningDb->getClientId())
					->getById((int)$item['uomid']);
				if($uom && trim((string)$position['uom']) !== trim((string)$uom['title'])) {
					throw new InvalidArgumentException('ITEMS_PLANNING_UOM_MISMATCH');
				}
			}
		}
	}

	protected function validateLinkedPlanning(array $data, int $excludeId): void
	{
		$linkId = (int)($data['linkedplanningid'] ?? 0);
		if($linkId <= 0) return;
		if(($data['type'] ?? '') !== 'reservation') throw new InvalidArgumentException('ITEMS_PLANNING_INVALID_LINK');
		$link = $this->_planningDb->getById($linkId);
		if(!$link || $linkId === $excludeId || $link['type'] !== 'incoming'
			|| (int)$link['itemid'] !== (int)($data['itemid'] ?? 0)
			|| (int)$link['warehouseid'] !== (int)($data['warehouseid'] ?? 0)) {
			throw new InvalidArgumentException('ITEMS_PLANNING_INVALID_LINK');
		}
	}

	protected function validateDuplicate(array $data, int $excludeId): void
	{
		$positionId = (int)($data['referencepositionid'] ?? 0);
		$external = trim((string)($data['externalnumber'] ?? ''));
		if($positionId <= 0 && $external === '') return;

		$select = $this->_planningDb->select()
			->from('stockplanning', ['id'])
			->where('type = ?', (string)$data['type'])
			->where('itemid = ?', (int)$data['itemid'])
			->where('warehouseid = ?', (int)$data['warehouseid'])
			->where('clientid = ?', $this->_planningDb->getClientId())
			->where('status IN (?)', ['draft', 'planned'])
			->where('deleted = ?', 0)
			->limit(1);
		if($positionId > 0) {
			$select->where('referencemodule = ?', (string)$data['referencemodule'])
				->where('referencetype = ?', (string)$data['referencetype'])
				->where('referenceid = ?', (int)$data['referenceid'])
				->where('referencepositionid = ?', $positionId);
		} else {
			$select->where('externalnumber = ?', $external);
		}
		if($excludeId > 0) $select->where('id != ?', $excludeId);
		if($this->_planningDb->fetchRow($select)) throw new RuntimeException('ITEMS_PLANNING_DUPLICATE');
	}
}
