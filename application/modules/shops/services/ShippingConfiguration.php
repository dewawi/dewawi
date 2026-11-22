<?php

class Shops_Service_ShippingConfiguration
{
	public const TAX_POLICY = 'de_ancillary_net_v1';
	public const TABLES = ['classes' => 'shopshippingclass', 'zones' => 'shopshippingzone', 'tariffs' => 'shopshippingtariff', 'assignments' => 'shopitemshipping', 'combinations' => 'shopshippingcombination'];
	private Zend_Db_Adapter_Abstract $db;
	private array $client;
	private array $user;

	public function __construct()
	{
		$this->db = Zend_Db_Table::getDefaultAdapter();
		$this->client = Zend_Registry::get('Client');
		$this->user = Zend_Registry::get('User');
	}

	// Client.id may already have been rewritten for an inherited module.
	public function owner(string $module = 'shops'): int { return (int)($this->client['modules'][$module] ?? $this->user['clientid'] ?? 0); }

	public function requireAdmin(): void
	{
		if (!Zend_Auth::getInstance()->hasIdentity() || empty($this->user['id']) || empty($this->user['admin']) || $this->owner() <= 0 || $this->owner('admin') !== $this->owner()) throw new DomainException('Forbidden', 403);
	}

	private function requireItem(bool $write): void
	{
		$permission = new DEEC_Permission($this->user);
		if (!Zend_Auth::getInstance()->hasIdentity() || empty($this->user['id']) || $this->owner() <= 0 || $this->owner('items') !== $this->owner()
			|| !$permission->hasPermission('items', 'item', 'view') || ($write && !$permission->hasPermission('items', 'item', 'edit'))) throw new DomainException('Forbidden', 403);
	}

	public function shops(): array
	{
		$this->requireAdmin();
		return $this->db->fetchAll($this->db->select()->from('shop', ['id', 'title'])->where('clientid = ?', $this->owner())->where('deleted = 0')->order('id'));
	}

	private function shop(int $shopId, bool $lock = false): array
	{
		$row = $this->db->fetchRow($this->db->select()->from('shop')->where('id = ?', $shopId)->where('clientid = ?', $this->owner())->where('deleted = 0')->forUpdate($lock));
		if (!$row) throw new DomainException('Not found', 404);
		return $row;
	}

	public function rows(int $shopId, string $kind): array
	{
		$this->requireAdmin(); $this->shop($shopId);
		return $this->records($shopId, $kind);
	}

	public function missingSchema(): array
	{
		$required = [
			'shop' => ['shippingcurrency', 'pickupenabled'],
			'item' => ['packweight', 'packlength', 'packwidth', 'packheight'],
			'shopshippingclass' => ['title', 'combine_same_class', 'required_fields'],
			'shopshippingzone' => ['countries'],
			'shopshippingtariff' => ['classid', 'zoneid', 'currency', 'model', 'parameters'],
			'shopitemshipping' => ['itemid', 'classid', 'packages_per_unit', 'separate'],
			'shopshippingcombination' => ['hostclassid', 'guestclassid', 'priority', 'max_guest_packages', 'max_guest_weight', 'max_packlength', 'max_packwidth', 'max_packheight'],
		];
		$columns = [];
		foreach ($this->db->fetchAll($this->db->select()->from('information_schema.COLUMNS', ['TABLE_NAME', 'COLUMN_NAME'])
			->where('TABLE_SCHEMA = DATABASE()')->where('TABLE_NAME IN (?)', array_keys($required))) as $row) $columns[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
		$missing = [];
		foreach ($required as $table => $fields) {
			if (!isset($columns[$table])) { $missing[] = $table; continue; }
			if (in_array($table, self::TABLES, true)) $fields = array_merge($fields, ['id', 'clientid', 'shopid', 'active', 'deleted', 'ordering']);
			foreach (array_diff($fields, $columns[$table]) as $field) $missing[] = $table . '.' . $field;
		}
		return $missing;
	}

	public function configuration(int $shopId): array
	{
		$this->requireAdmin();
		$shop = $this->shop($shopId);
		if ($this->missingSchema()) throw new DomainException('SHIPPING_SCHEMA_MISSING');
		$config = $this->load($shop);
		(new Shops_Service_ShippingCalculator())->validateConfiguration(['clientid' => $this->owner(), 'shopid' => $shopId], $config);
		return $config;
	}

	private function records(int $shopId, string $kind, bool $active = false, ?int $owner = null): array
	{
		if (!isset(self::TABLES[$kind])) throw new DomainException('Not found', 404);
		$query = $this->db->select()->from(self::TABLES[$kind])->where('clientid = ?', $owner ?? $this->owner())->where('shopid = ?', $shopId)->where('deleted = 0')->order(['ordering ASC', 'id ASC']);
		if ($active) $query->where('active = 1');
		return $this->db->fetchAll($query);
	}

	public function edit(int $shopId, string $kind, int $id): array
	{
		$this->requireAdmin();
		$shop = $this->shop($shopId);
		if ($kind === 'settings') return ['currency' => $shop['shippingcurrency'] ?? '', 'pickup' => (int)$shop['pickupenabled'], 'taxpolicy' => $shop['shippingtaxpolicy'] ?? ''];
		if ($id === 0) return [];
		foreach ($this->records($shopId, $kind) as $row) if ((int)$row['id'] === $id) return $this->expand($row, $kind);
		throw new DomainException('Not found', 404);
	}

	public function choices(int $shopId, array $current = []): array
	{
		$this->requireAdmin(); $shop = $this->shop($shopId);
		$options = $this->options($shopId);
		$options['taxPolicyAvailable'] = array_key_exists('shippingtaxpolicy', $shop);
		foreach (['classes' => ['classid','hostclassid','guestclassid'], 'zones' => ['zoneid']] as $kind => $fields) foreach ($this->records($shopId, $kind) as $row) {
			foreach ($fields as $field) if ((int)($current[$field] ?? 0) === (int)$row['id']) $options[$kind === 'classes' ? 'classid' : 'zoneid'][$row['id']] = $row['title'] . ' (#' . $row['id'] . ')';
		}
		return $options;
	}

	private function options(int $shopId): array
	{
		$result = ['classid' => ['' => 'SHIPPING_SELECT'], 'zoneid' => ['' => 'SHIPPING_SELECT'], 'currency' => []];
		foreach (['classes' => 'classid', 'zones' => 'zoneid'] as $kind => $field) foreach ($this->records($shopId, $kind, true) as $row) $result[$field][$row['id']] = $row['title'] . ' (#' . $row['id'] . ')';
		foreach ($this->db->fetchAll($this->db->select()->from('currency', ['code'])->where('clientid = ?', $this->owner())->where('deleted = 0')->order('code')) as $row) if (preg_match('/^[A-Z]{3}$/D', $row['code']) && $this->minorUnits($row['code']) === 2) $result['currency'][$row['code']] = $row['code'];
		return $result;
	}

	public function expand(array $row, string $kind): array
	{
		if ($kind === 'classes') {
			foreach (json_decode($row['required_fields'] ?: '[]', true, 512, JSON_THROW_ON_ERROR) as $field) $row['require_' . $field] = 1;
		} elseif ($kind === 'zones') $row['countries'] = json_decode($row['countries'] ?: '[]', true, 512, JSON_THROW_ON_ERROR);
		elseif ($kind === 'tariffs') {
			$params = json_decode($row['parameters'] ?: '{}', true, 512, JSON_THROW_ON_ERROR);
			$row = $row + $params;
			$row['tiers'] = $params['tiers'] ?? [];
		}
		return $row;
	}

	/** Entry points enforce authorization; final checkout and administration serialize on the shop row. */
	private function load(array $shop, ?int $owner = null): array
	{
		$owner = $owner ?? $this->owner();
		$shopId = (int)$shop['id'];
		if ($shop['shippingcurrency']) $this->currency($shop['shippingcurrency'], $owner);
		$config = ['version' => 1, 'clientid' => $owner, 'shopid' => $shopId, 'currency' => $shop['shippingcurrency'], 'minor_units' => 2, 'pickup' => (bool)$this->flag($shop['pickupenabled'], 'pickup')];
		foreach (self::TABLES as $kind => $table) {
			$config[$kind] = [];
			foreach ($this->records($shopId, $kind, true, $owner) as $row) {
				$base = ['id' => (int)$row['id'], 'clientid' => $owner, 'shopid' => $shopId];
				switch ($kind) {
					case 'classes': $value = ['title' => $row['title'], 'combine_same_class' => (bool)$this->flag($row['combine_same_class'], 'combine_same_class'), 'required_fields' => json_decode($row['required_fields'], true, 512, JSON_THROW_ON_ERROR)]; break;
					case 'zones': $value = ['countries' => json_decode($row['countries'], true, 512, JSON_THROW_ON_ERROR)]; break;
					case 'tariffs':
						if ($row['currency'] !== $shop['shippingcurrency']) throw new DomainException('SHIPPING_CURRENCY_MISMATCH');
						$value = ['classid' => $row['classid'], 'zoneid' => $row['zoneid'], 'model' => $row['model']] + json_decode($row['parameters'], true, 512, JSON_THROW_ON_ERROR); break;
					case 'assignments':
						$item = $this->db->fetchRow($this->db->select()->from('item', ['id', 'shopid'])->where('clientid = ?', $owner)->where('id = ?', $row['itemid'])->where('deleted = 0'));
						if (!$item || (int)$item['shopid'] !== $shopId) continue 2;
						$value = ['itemid' => $row['itemid'], 'classid' => $row['classid'], 'packages_per_unit' => $row['packages_per_unit'], 'separate' => (bool)$this->flag($row['separate'], 'separate')]; break;
					case 'combinations':
						$value = array_intersect_key($row, array_flip(['hostclassid', 'guestclassid', 'priority', 'max_guest_packages', 'max_guest_weight', 'max_packlength', 'max_packwidth', 'max_packheight']));
						$value = array_filter($value, static fn($v): bool => $v !== null); break;
				}
				$config[$kind][] = $base + $value;
			}
		}
		return $config;
	}

	/** Public read access is bound to the router's trusted site, never to caller-supplied shop IDs. */
	public function calculateCheckout(array $selection, array $destination): array
	{
		$context = Zend_Registry::isRegistered('SiteContext') ? Zend_Registry::get('SiteContext') : null;
		if (!$context instanceof DEEC_Site_Context || !$context->hasFeature('checkout') || !$context->hasFeature('cart')
			|| $context->getClientId() <= 0 || $context->getSiteId() <= 0) throw new DomainException('Forbidden', 403);
		$owner = $context->getClientId(); $shopId = $context->getSiteId();
		$shop = $this->db->fetchRow($this->db->select()->from('shop')->where('id = ?', $shopId)->where('clientid = ?', $owner)->where('deleted = 0')->where('checkoutenabled = 1'));
		if (!$shop) throw new DomainException('Not found', 404);
		$base = ['version' => 1, 'status' => 'quote_required', 'price_basis' => 'net', 'net' => null, 'currency' => null,
			'clientid' => $owner, 'shopid' => $shopId, 'destination' => $destination, 'groups' => [], 'reasons' => []];
		if (empty($shop['shippingcurrency'])) return $base + ['legacy' => true];
		if ($this->missingSchema()) {
			$base['reasons'] = ['shipping_schema_missing'];
			return $base;
		}
		try {
			$config = $this->load($shop, $owner);
			$ids = array_column($selection, 'itemid');
			$items = $this->db->fetchAll($this->db->select()->from('item')->where('clientid = ?', $owner)->where('shopid = ?', $shopId)->where('deleted = 0')->where('shopenabled = 1')->where('id IN (?)', $ids ?: [0]));
			$result = (new Shops_Service_ShippingCalculator())->calculate(['clientid' => $owner, 'shopid' => $shopId], $config, $items, $selection, $destination);
			$result['tax_policy'] = $shop['shippingtaxpolicy'] ?? null;
			$result['configuration_hash'] = hash('sha256', json_encode([$config, $result['tax_policy']], JSON_THROW_ON_ERROR));
			$result['origin_country'] = $this->db->fetchOne($this->db->select()->from('client', ['country'])->where('id = ?', $owner)->where('deleted = 0')) ?: null;
			return $result;
		} catch (JsonException | DomainException $e) {
			$base['reasons'] = ['invalid_configuration'];
			return $base;
		}
	}

	public function calculate(int $shopId, array $selection, array $destination): array
	{
		$this->requireAdmin();
		$this->db->beginTransaction();
		try {
			$shop = $this->shop($shopId, true);
			$config = $this->load($shop);
			$ids = [];
			foreach ($selection as $line) if (is_array($line) && ($id = filter_var($line['itemid'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]))) $ids[] = $id;
			$items = $this->db->fetchAll($this->db->select()->from('item')->where('clientid = ?', $this->owner())->where('shopid = ?', $shopId)->where('deleted = 0')->where('shopenabled = 1')->where('id IN (?)', $ids ?: [0]));
			$result = (new Shops_Service_ShippingCalculator())->calculate(['clientid' => $this->owner(), 'shopid' => $shopId], $config, $items, $selection, $destination);
			$this->db->commit();
			return $result;
		} catch (Throwable $e) {
			$this->db->rollBack();
			if ($e instanceof JsonException || ($e instanceof DomainException && !in_array($e->getCode(), [403,404], true))) {
				$result = (new Shops_Service_ShippingCalculator())->calculate(['clientid' => $this->owner(), 'shopid' => $shopId], [], [], $selection, $destination);
				$result['reasons'] = ['invalid_configuration'];
				return $result;
			}
			throw $e;
		}
	}

	public function save(int $shopId, string $kind, int $id, array $input): array
	{
		$this->requireAdmin();
		return $this->write($shopId, $kind, $id, $input);
	}

	private function write(int $shopId, string $kind, int $id, array $input): array
	{
		if (!isset(self::TABLES[$kind]) && $kind !== 'settings') throw new DomainException('Not found', 404);
		$this->db->beginTransaction();
		try {
			$shop = $this->shop($shopId, true);
			if ($kind === 'settings') {
				$this->allowed($input, ['currency', 'pickup', 'taxpolicy']);
				$currency = $this->currency($input['currency'] ?? null);
				$settings = ['shippingcurrency' => $currency, 'pickupenabled' => $this->flag($input['pickup'] ?? 0, 'pickup'), 'modified' => date('Y-m-d H:i:s'), 'modifiedby' => (int)$this->user['id']];
				if (array_key_exists('taxpolicy', $input)) {
					if (!array_key_exists('shippingtaxpolicy', $shop)) throw new DomainException('taxpolicy:SHIPPING_TAX_SCHEMA_MISSING');
					if (!in_array($input['taxpolicy'], ['', self::TAX_POLICY], true)) throw new DomainException('taxpolicy:SHIPPING_INVALID_TAX_POLICY');
					$settings['shippingtaxpolicy'] = $input['taxpolicy'] ?: null;
				}
				$this->db->update('shop', $settings, ['id = ?' => $shopId, 'clientid = ?' => $this->owner()]);
			} else {
				$old = null;
				foreach ($this->records($shopId, $kind) as $row) if ((int)$row['id'] === $id) $old = $row;
				if ($id && !$old) throw new DomainException('Not found', 404);
				$data = $this->normalize($kind, $input);
				if ($kind === 'assignments') {
					$item = $this->db->fetchRow($this->db->select()->from('item', ['id'])->where('id = ?', $data['itemid'])->where('shopid = ?', $shopId)->where('clientid = ?', $this->owner())->where('deleted = 0')->forUpdate());
					if (!$item) throw new DomainException('itemid:SHIPPING_INVALID_ITEM');
				}
				foreach (['classid', 'zoneid', 'hostclassid', 'guestclassid'] as $field) if (array_key_exists($field, $data) && $data[$field] === null && $data['active']) throw new DomainException($field . ':SHIPPING_REQUIRED');
				$this->references($shopId, $kind, $id, $data);
				$data['modified'] = date('Y-m-d H:i:s'); $data['modifiedby'] = (int)$this->user['id'];
				if (!$id) {
					$data += ['clientid' => $this->owner(), 'shopid' => $shopId, 'created' => $data['modified'], 'createdby' => (int)$this->user['id']];
					$this->db->insert(self::TABLES[$kind], $data); $id = (int)$this->db->lastInsertId();
				} else $this->db->update(self::TABLES[$kind], $data, ['id = ?' => $id, 'clientid = ?' => $this->owner(), 'shopid = ?' => $shopId]);
			}
			$updated = $this->shop($shopId);
			if ($updated['shippingcurrency'] || $kind === 'settings' || !empty($input['active'])) {
				try { (new Shops_Service_ShippingCalculator())->validateConfiguration(['clientid' => $this->owner(), 'shopid' => $shopId], $this->load($updated)); }
				catch (DomainException $e) { $field = ['ambiguous_zone' => 'countries', 'invalid_combination' => 'hostclassid', 'combination_chain_not_supported' => 'hostclassid'][$e->getMessage()] ?? ($kind === 'settings' ? 'currency' : 'active'); throw new DomainException($field . ':SHIPPING_CONFIGURATION_CONFLICT'); }
			}
			$this->db->commit();
			return ['id' => $id, 'errors' => []];
		} catch (Throwable $e) {
			$this->db->rollBack();
			if ($e instanceof DomainException && in_array($e->getCode(), [403, 404], true)) throw $e;
			if ($e instanceof DomainException && str_contains($e->getMessage(), ':')) { [$field, $key] = explode(':', $e->getMessage(), 2); return ['id' => $id, 'errors' => [$field => $key]]; }
			if ($e instanceof Zend_Db_Exception) return ['id' => $id, 'errors' => ['classid' => 'SHIPPING_REFERENCE_CONFLICT']];
			throw $e;
		}
	}

	private function references(int $shopId, string $kind, int $id, array $data): void
	{
		foreach (['classid' => 'classes', 'zoneid' => 'zones', 'hostclassid' => 'classes', 'guestclassid' => 'classes'] as $field => $target) {
			if (empty($data[$field])) continue;
			$ref = $this->db->fetchRow($this->db->select()->from(self::TABLES[$target], ['id','active'])->where('id = ?', $data[$field])->where('clientid = ?', $this->owner())->where('shopid = ?', $shopId)->where('deleted = 0'));
			if (!$ref || ($data['active'] && !$ref['active'])) throw new DomainException($field . ':SHIPPING_REFERENCE_CONFLICT');
		}
		$keys = ['tariffs' => [['classid','zoneid','currency']], 'assignments' => [['itemid']], 'combinations' => [['hostclassid','guestclassid'], ['priority']]][$kind] ?? [];
		foreach ($keys as $fields) {
			$query = $this->db->select()->from(self::TABLES[$kind], ['id'])->where('clientid = ?', $this->owner())->where('shopid = ?', $shopId)->where('id <> ?', $id);
			foreach ($fields as $field) { if (($data[$field] ?? null) === null) continue 2; $query->where($field . ' = ?', $data[$field]); }
			if ($this->db->fetchOne($query)) throw new DomainException($fields[0] . ':SHIPPING_REFERENCE_CONFLICT');
		}
	}

	public function itemAssignment(int $itemId): array
	{
		$this->requireItem(false);
		$item = $this->db->fetchRow($this->db->select()->from('item', ['id', 'shopid'])->where('id = ?', $itemId)->where('clientid = ?', $this->owner('items'))->where('deleted = 0'));
		if (!$item) throw new DomainException('Not found', 404);
		if (!(int)$item['shopid']) return ['shopid' => 0, 'classes' => [], 'values' => []];
		$this->shop((int)$item['shopid']);
		$values = $this->db->fetchRow($this->db->select()->from('shopitemshipping')->where('itemid = ?', $itemId)->where('clientid = ?', $this->owner())->where('shopid = ?', $item['shopid'])->where('deleted = 0')) ?: [];
		$classes = ['' => 'SHIPPING_SELECT'];
		foreach ($this->records((int)$item['shopid'], 'classes') as $class) if ($class['active'] || (int)($values['classid'] ?? 0) === (int)$class['id']) $classes[$class['id']] = $class['title'] . ' (#' . $class['id'] . ')';
		return ['shopid' => (int)$item['shopid'], 'classes' => $classes, 'values' => $values];
	}

	public function saveItemAssignment(int $itemId, array $input): array
	{
		$this->requireItem(true);
		$assignment = $this->itemAssignment($itemId);
		if (!$assignment['shopid']) throw new DomainException('Not found', 404);
		if (array_diff(array_keys($input), ['classid', 'packages_per_unit', 'separate', 'active'])) return ['id' => 0, 'errors' => ['active' => 'SHIPPING_INVALID_INPUT']];
		return $this->write($assignment['shopid'], 'assignments', (int)($assignment['values']['id'] ?? 0), $input + ['itemid' => $itemId, 'ordering' => $assignment['values']['ordering'] ?? 0]);
	}

	public function countries(): array
	{
		$this->requireAdmin(); $countries = [];
		foreach ($this->db->fetchAll($this->db->select()->from('country', ['code', 'name'])->where('clientid = ?', $this->owner())->where('deleted = 0')->order('name')) as $row) $countries[$row['code']] = $row['name'];
		return $countries;
	}
	public static function csrfToken(): string
	{
		$session = new Zend_Session_Namespace('ShippingManagement');
		if (empty($session->csrf)) $session->csrf = bin2hex(random_bytes(32));
		return $session->csrf;
	}
	public static function validCsrf($token): bool { return is_string($token) && hash_equals(self::csrfToken(), $token); }

	private function allowed(array $data, array $fields): void { if (array_diff(array_keys($data), $fields)) throw new DomainException('active:SHIPPING_INVALID_INPUT'); }
	private function flag($value, string $field): int { if (!in_array($value, [0, 1, '0', '1', false, true], true)) throw new DomainException($field . ':SHIPPING_INVALID_INPUT'); return (int)$value; }
	private function number($value, string $field, int $precision = 4, bool $required = true): ?string
	{
		if (!$required && ($value === '' || $value === null)) return null;
		if (!is_scalar($value) || is_bool($value) || !preg_match($precision ? '/^[0-9]{1,8}(?:\.[0-9]{1,' . $precision . '})?$/D' : '/^[0-9]{1,9}$/D', (string)$value)) throw new DomainException($field . ':SHIPPING_INVALID_NUMBER');
		return (string)$value;
	}
	private function reference($value, string $field): ?int { $value = $this->number($value, $field, 0, false); return $value === null || (int)$value === 0 ? null : (int)$value; }
	private function currency($value, ?int $owner = null): string
	{
		if (!is_string($value) || !preg_match('/^[A-Z]{3}$/D', $value) || $this->minorUnits($value) !== 2 || !$this->db->fetchOne($this->db->select()->from('currency', ['code'])->where('clientid = ?', $owner ?? $this->owner())->where('code = ?', $value)->where('deleted = 0'))) throw new DomainException('currency:SHIPPING_INVALID_CURRENCY');
		return $value;
	}

	private function minorUnits(string $currency): int
	{
		$digits = Zend_Locale_Data::getContent('en_US', 'currencyfraction', $currency);
		return (int)($digits === false ? Zend_Locale_Data::getContent('en_US', 'currencyfraction') : $digits);
	}

	private function normalize(string $kind, array $data): array
	{
		$fields = ['classes' => ['title', 'combine_same_class', 'require_packweight', 'require_packlength', 'require_packwidth', 'require_packheight'],
			'zones' => ['title', 'countries'], 'tariffs' => ['title', 'classid', 'zoneid', 'currency', 'model', 'amount', 'base', 'additional', 'percent', 'tiers', 'min_weight', 'max_weight', 'min_packages', 'max_packages', 'min_value', 'max_value'],
			'assignments' => ['itemid', 'classid', 'packages_per_unit', 'separate'],
			'combinations' => ['hostclassid', 'guestclassid', 'priority', 'max_guest_packages', 'max_guest_weight', 'max_packlength', 'max_packwidth', 'max_packheight']][$kind];
		$this->allowed($data, array_merge($fields, ['active', 'ordering']));
		$out = ['active' => $this->flag($data['active'] ?? 0, 'active'), 'ordering' => (int)$this->number($data['ordering'] ?? 0, 'ordering', 0)];
		if (in_array('title', $fields, true)) {
			if (!is_string($data['title'] ?? null) || mb_strlen(trim($data['title'])) > 255 || ($out['active'] && trim($data['title']) === '')) throw new DomainException('title:SHIPPING_REQUIRED');
			$out['title'] = trim($data['title']);
		}
		switch ($kind) {
			case 'classes':
				$out['combine_same_class'] = $this->flag($data['combine_same_class'] ?? 0, 'combine_same_class'); $required = [];
				foreach (['packweight', 'packlength', 'packwidth', 'packheight'] as $field) if ($this->flag($data['require_' . $field] ?? 0, 'require_' . $field)) $required[] = $field;
				$out['required_fields'] = json_encode($required); break;
			case 'zones':
				$countries = $data['countries'] ?? [];
				if (!is_array($countries)) throw new DomainException('countries:SHIPPING_REQUIRED');
				$countries = array_values(array_filter($countries, static fn($value): bool => $value !== ''));
				$known = $this->db->fetchCol($this->db->select()->from('country', ['code'])->where('clientid = ?', $this->owner())->where('deleted = 0'));
				foreach ($countries as $country) if (!is_string($country) || !preg_match('/^[A-Z]{2}$/D', $country) || !in_array($country, $known, true)) throw new DomainException('countries:SHIPPING_INVALID_COUNTRIES');
				if ($out['active'] && !$countries) throw new DomainException('countries:SHIPPING_REQUIRED');
				sort($countries); $out['countries'] = json_encode(array_values(array_unique($countries))); break;
			case 'tariffs':
				$out['classid'] = $this->reference($data['classid'] ?? '', 'classid'); $out['zoneid'] = $this->reference($data['zoneid'] ?? '', 'zoneid');
				$out['currency'] = $this->currency($data['currency'] ?? null);
				$model = $data['model'] ?? '';
				if (!is_string($model) || !in_array($model, ['fixed', 'weight', 'additional_packages', 'percentage', 'manual'], true)) throw new DomainException('model:SHIPPING_REQUIRED');
				$out['model'] = $model; $params = [];
				foreach (['fixed' => ['amount'], 'weight' => [], 'additional_packages' => ['base', 'additional'], 'percentage' => ['percent'], 'manual' => []][$model] as $field) $params[$field] = $this->number($data[$field] ?? '', $field, $field === 'percent' ? 4 : 2, (bool)$out['active']);
				foreach (['min_weight', 'max_weight', 'min_packages', 'max_packages', 'min_value', 'max_value'] as $field) if (($data[$field] ?? '') !== '') $params[$field] = $this->number($data[$field], $field, str_ends_with($field, 'packages') ? 0 : (str_ends_with($field, 'value') ? 2 : 4));
				if ($model === 'percentage' && isset($params['percent']) && (float)$params['percent'] > 100) throw new DomainException('percent:SHIPPING_INVALID_NUMBER');
				if ($model === 'weight') {
					$tiers = $data['tiers'] ?? []; if (!is_array($tiers) || count($tiers) > 100) throw new DomainException('tiers:SHIPPING_INVALID_TIERS');
					$params['tiers'] = []; $last = 0;
					foreach ($tiers as $tier) {
						if (!is_array($tier) || array_diff(array_keys($tier), ['up_to', 'amount'])) throw new DomainException('tiers:SHIPPING_INVALID_TIERS');
						if (($tier['up_to'] ?? '') === '' && ($tier['amount'] ?? '') === '') continue;
						$weight = $this->number($tier['up_to'] ?? '', 'tiers'); $amount = $this->number($tier['amount'] ?? '', 'tiers', 2);
						if ((float)$weight <= $last) throw new DomainException('tiers:SHIPPING_INVALID_TIERS');
						$last = (float)$weight; $params['tiers'][] = ['up_to' => $weight, 'amount' => $amount];
					}
					if ($out['active'] && !$params['tiers']) throw new DomainException('tiers:SHIPPING_REQUIRED');
				}
				foreach (['weight', 'packages', 'value'] as $field) if (isset($params['min_' . $field], $params['max_' . $field]) && (float)$params['min_' . $field] > (float)$params['max_' . $field]) throw new DomainException('max_' . $field . ':SHIPPING_INVALID_BOUNDS');
				$out['parameters'] = json_encode($params); break;
			case 'assignments':
				$out['itemid'] = $this->reference($data['itemid'] ?? '', 'itemid'); $out['classid'] = $this->reference($data['classid'] ?? '', 'classid');
				$out['packages_per_unit'] = (int)$this->number($data['packages_per_unit'] ?? '1', 'packages_per_unit', 0);
				if ($out['packages_per_unit'] <= 0) throw new DomainException('packages_per_unit:SHIPPING_INVALID_NUMBER');
				$out['separate'] = $this->flag($data['separate'] ?? 1, 'separate'); break;
			case 'combinations':
				foreach (['hostclassid', 'guestclassid'] as $field) $out[$field] = $this->reference($data[$field] ?? '', $field);
				foreach (['priority', 'max_guest_packages', 'max_guest_weight', 'max_packlength', 'max_packwidth', 'max_packheight'] as $field) $out[$field] = $this->number($data[$field] ?? '', $field, in_array($field, ['priority', 'max_guest_packages'], true) ? 0 : 4, false);
				if ($out['active']) foreach (['priority', 'max_guest_packages', 'max_guest_weight'] as $field) if ($out[$field] === null || (float)$out[$field] <= 0) throw new DomainException($field . ':SHIPPING_REQUIRED');
				break;
		}
		return $out;
	}
}
