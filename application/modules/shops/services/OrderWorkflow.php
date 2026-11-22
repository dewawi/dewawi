<?php

class Shops_Service_OrderWorkflow
{
	private array $client;
	private array $user;
	private DEEC_Permission $permission;

	public function __construct()
	{
		$this->client = Zend_Registry::get('Client');
		$this->user = Zend_Registry::get('User');
		$this->permission = new DEEC_Permission($this->user);
	}

	public function owner(string $module): int
	{
		$id = (int)($this->client['modules'][$module] ?? $this->client['id'] ?? 0);
		if ($id <= 0) throw new DomainException('SHOPS_ORDER_DENIED', 403);
		return $id;
	}

	public function allowed(string $right): bool
	{
		return !empty($this->user['id']) && $this->permission->hasPermission('sales', 'salesorder', $right);
	}

	public function requirePermission(string $right): void
	{
		if (!$this->allowed($right)) throw new DomainException('SHOPS_ORDER_DENIED', 403);
	}

	public function db(): Shops_Model_DbTable_Order
	{
		return (new Shops_Model_DbTable_Order())->setClientId($this->owner('shops'));
	}

	public function detail(int $id): array
	{
		$this->requirePermission('view');
		$row = $this->db()->getDetail($id);
		if (!$row) throw new DomainException('SHOPS_ORDER_NOT_FOUND', 404);
		return $row;
	}

	public function contacts(string $query): array
	{
		$this->requirePermission('view');
		if (!$this->permission->hasPermission('contacts', 'contact', 'view') || mb_strlen(trim($query)) < 2) return [];
		return (new Contacts_Model_DbTable_Contact())->setClientId($this->owner('contacts'))->suggestContacts(mb_substr(trim($query), 0, 100), $this->owner('contacts'), 25);
	}

	public function salesorder(array $order): ?array
	{
		$this->requirePermission('view');
		if ((int)($order['salesclientid'] ?? 0) !== $this->owner('sales') || empty($order['salesorderid'])) return null;
		return (new Sales_Model_DbTable_Salesorder())->setClientId($this->owner('sales'))->getById((int)$order['salesorderid']);
	}

	public static function allowedStatuses(array $order): array
	{
		if (in_array($order['status'], ['completed', 'cancelled'], true)) return [];
		if ($order['status'] === 'transferred' || !empty($order['salesorderid'])) return ['completed', 'cancelled'];
		if (!in_array($order['status'], [null, 'new', 'in_progress'], true)) return [];
		return array_values(array_diff(['in_progress', 'completed', 'cancelled'], [$order['status']]));
	}

	public static function canTransfer(array $order): bool
	{
		return empty($order['salesorderid']) && in_array($order['status'], [null, 'new', 'in_progress'], true);
	}

	public function contact(array $order): ?array
	{
		$this->requirePermission('view');
		if (empty($order['contactid']) || !$this->permission->hasPermission('contacts', 'contact', 'view')) return null;
		return (new Contacts_Model_DbTable_Contact())->setClientId($this->owner('contacts'))->getById((int)$order['contactid']);
	}

	public static function notificationStatus(array $message): string
	{
		if (($message['deliverystatus'] ?? '') === 'delivered' && !empty($message['deliverydate'])) return 'SHOPS_ORDER_MAIL_DELIVERED';
		if (in_array($message['deliverystatus'] ?? '', ['bounced', 'rejected', 'failed'], true)) return 'SHOPS_ORDER_MAIL_FAILED';
		if (($message['deliverystatus'] ?? '') === 'accepted' || ($message['response'] ?? '') === 'sent') return 'SHOPS_ORDER_MAIL_ACCEPTED';
		return 'SHOPS_ORDER_MAIL_UNKNOWN';
	}

	public function changeStatus(int $id, string $status): void
	{
		if (!isset(Shops_Model_DbTable_Order::STATUSES[$status])) throw new DomainException('SHOPS_ORDER_STATUS_INVALID');
		$this->requirePermission('view');
		$this->requirePermission('edit');
		$db = $this->db(); $adapter = $db->getAdapter();
		try {
			$adapter->beginTransaction();
			$order = $db->getByIdForUpdate($id);
			if (!$order) throw new DomainException('SHOPS_ORDER_NOT_FOUND', 404);
			if (($order['status'] ?? '') !== $status) {
				if (!in_array($status, self::allowedStatuses($order), true)) throw new DomainException('SHOPS_ORDER_STATUS_INVALID');
				$db->recordStatus($order, $status);
			}
			$adapter->commit();
		} catch (Throwable $e) { $adapter->rollBack(); throw $e; }
	}

	public function createSalesorder(int $id, int $contactId): array
	{
		$this->requirePermission('view');
		$this->requirePermission('edit');
		$this->requirePermission('add');
		if (!$this->permission->hasPermission('contacts', 'contact', 'view')) throw new DomainException('SHOPS_ORDER_DENIED', 403);
		$db = $this->db(); $adapter = $db->getAdapter();
		try {
			$adapter->beginTransaction();
			$order = $db->getByIdForUpdate($id);
			if (!$order) throw new DomainException('SHOPS_ORDER_NOT_FOUND', 404);
			if (!empty($order['salesorderid'])) {
				$salesorder = $this->salesorder($order);
				if (!$salesorder) throw new DomainException('SHOPS_ORDER_LINK_UNAVAILABLE');
				$adapter->commit();
				return ['id' => (int)$salesorder['id'], 'created' => false];
			}
			if (!self::canTransfer($order)) throw new DomainException('SHOPS_ORDER_STATUS_INVALID');
			$positions = (new Shops_Model_DbTable_Orderpos())->setClientId($this->owner('shops'))->getForOrder($order);
			if (Shops_Service_OrderSnapshot::problems($order, $positions)) throw new DomainException('SHOPS_ORDER_INCOMPLETE');
			$shop = $adapter->fetchRow($adapter->select()->from('shop', ['id'])->where('id = ?', (int)$order['shopid'])->where('clientid = ?', $this->owner('shops')));
			if (!$shop) throw new DomainException('SHOPS_ORDER_INCOMPLETE');
			$contact = (new Contacts_Model_DbTable_Contact())->setClientId($this->owner('contacts'))->getByIdForUpdate($contactId);
			if (!$contact) throw new DomainException('SHOPS_ORDER_SELECT_CONTACT');
			$matches = (int)$adapter->fetchOne($adapter->select()->from('contact', ['COUNT(*)'])->where('clientid = ?', $this->owner('contacts'))->where('contactid = ?', (int)$contact['contactid'])->where('deleted = ?', 0));
			if ($matches !== 1 || (int)$contact['contactid'] <= 0) throw new DomainException('SHOPS_ORDER_SELECT_CONTACT');
			$snapshot = Shops_Service_OrderSnapshot::decode($order['snapshot']);
			$originalClient = Zend_Registry::get('Client');
			try {
				Zend_Registry::set('Client', array_replace($originalClient, ['id' => $this->owner('sales')]));
				if (!array_key_exists($order['currency'], (new Application_Model_DbTable_Currency())->getCurrencies())) throw new DomainException('SHOPS_ORDER_CURRENCY_INVALID');
				$data = (new Sales_Service_CreateDataFactory())->build('salesorder');
			} finally { Zend_Registry::set('Client', $originalClient); }
			$data = array_merge($data, [
				'contactid' => (int)$contact['contactid'], 'currency' => $order['currency'], 'taxfree' => 0,
				'orderdate' => $order['orderdate'], 'reference' => (string)$order['orderid'],
				'contactperson' => $snapshot['billing']['name'], 'responsibleid' => (int)$this->user['id'], 'responsible' => (string)($this->user['name'] ?? ''),
				'info' => trim($snapshot['message'] . "\n" . $snapshot['email'] . "\n" . $snapshot['billing']['phone']),
				'subtotal' => $order['total'], 'taxes' => $order['taxes'], 'total' => $order['grosstotal'],
			]);
			foreach (['billing', 'shipping'] as $prefix) {
				$address = $snapshot[$prefix];
				$data[$prefix . 'name1'] = $address['company'] ?: $address['name'];
				$data[$prefix . 'name2'] = $address['company'] !== '' ? $address['name'] : '';
				foreach (['department', 'street', 'postcode', 'city', 'country'] as $field) $data[$prefix . $field] = $address[$field];
			}
			$data['shippingphone'] = $snapshot['shipping']['phone'];
			$freight = $snapshot['freight'] ?? null;
			if (($freight['status'] ?? '') === 'calculated') $data['shippingmethod'] = $freight['label'];
			if (empty($data['templateid']) || empty($data['language']) || (int)$data['state'] !== 100) throw new DomainException('SHOPS_ORDER_DEFAULTS_MISSING');
			$salesDb = (new Sales_Model_DbTable_Salesorder())->setClientId($this->owner('sales'));
			$salesId = $salesDb->create($data);
			$posDb = (new Sales_Model_DbTable_Salesorderpos())->setClientId($this->owner('sales'));
			foreach ($positions as $position) {
				$row = Shops_Service_OrderSnapshot::decode($position['snapshot']);
				$item = (new Items_Model_DbTable_Item())->setClientId($this->owner('items'))->getById((int)$position['itemid']);
				$posDb->addPosition([
					'parentid' => $salesId, 'possetid' => 0, 'itemid' => $item ? (int)$item['id'] : null,
					'sku' => $row['sku'], 'title' => $row['title'], 'description' => $row['description'],
					'price' => $position['price'], 'quantity' => $position['quantity'], 'taxrate' => $row['taxrate'],
					'currency' => $order['currency'], 'uom' => $row['uom'],
				]);
			}
			if (($freight['status'] ?? '') === 'calculated') {
				// ERP freight uses an ordinary document position without an invented stock item or SKU.
				$allocations = $snapshot['version'] === 3 ? $freight['tax_allocations'] : [['net' => $freight['net'], 'taxrate' => $freight['taxrate']]];
				foreach ($allocations as $allocation) $posDb->addPosition(['parentid' => $salesId, 'possetid' => 0, 'itemid' => null, 'sku' => null,
					'title' => $freight['label'], 'description' => '', 'price' => $allocation['net'], 'quantity' => 1,
					'taxrate' => $allocation['taxrate'], 'currency' => $freight['currency'], 'uom' => null]);
			}
			$calculation = Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate')->direct($salesId, 'sales', 'salesorder', $this->owner('sales'))['row'];
			foreach (['subtotal' => 'total', 'taxes' => 'taxes', 'total' => 'grosstotal'] as $calculated => $stored) {
				$value = $calculated === 'taxes' ? $calculation['taxes']['total'] : $calculation[$calculated];
				if (abs((float)$value - (float)$order[$stored]) > 0.0001) throw new DomainException('SHOPS_ORDER_TOTAL_MISMATCH');
			}
			if ($snapshot['version'] === 3) foreach ($snapshot['tax']['groups'] as $group) {
				if (!isset($calculation['taxes'][(string)(float)$group['taxrate']]) || abs((float)$calculation['taxes'][(string)(float)$group['taxrate']] - (float)$group['taxes']) > 0.0001) throw new DomainException('SHOPS_ORDER_TOTAL_MISMATCH');
			}
			$db->updateById($id, ['contactid' => $contactId, 'salesorderid' => $salesId, 'salesclientid' => $this->owner('sales')]);
			$db->recordStatus($order, 'transferred');
			$adapter->commit();
			return ['id' => $salesId, 'created' => true];
		} catch (Throwable $e) { $adapter->rollBack(); throw $e; }
	}
}
