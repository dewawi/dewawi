<?php

class Admin_Service_ShopReadiness
{
	private Zend_Db_Adapter_Abstract $db;
	private array $checks = [];

	public function inspect(int $shopId): array
	{
		$user = Zend_Registry::get('User');
		if (!Zend_Auth::getInstance()->hasIdentity() || empty($user['id']) || empty($user['admin'])) throw new DomainException('Forbidden', 403);
		$this->db = Zend_Db_Table::getDefaultAdapter();
		$this->checks = [];
		$shop = (new Admin_Model_DbTable_Shop())->getById($shopId);
		if (!$shop) throw new DomainException('Not found', 404);
		$owner = (int)$shop['clientid'];
		$checkout = !empty($shop['checkoutenabled']);
		$catalog = !empty($shop['catalogenabled']);
		$shipping = new Shops_Service_ShippingConfiguration();
		$client = $this->db->fetchRow($this->db->select()->from('client', ['id', 'country', 'activated'])->where('id = ?', $owner)->where('deleted = 0'));
		$this->add('site', !empty($shop['activated']) && $client && $client['activated'] ? 'ready' : 'blocked');
		$url = DEEC_Site_Context::parseCanonicalUrl((string)$shop['url']);
		$resolved = $url ? (new DEEC_Site_Resolver())->resolveByHost(parse_url($url['origin'], PHP_URL_HOST) . ($url['port'] ? ':' . $url['port'] : ''), ['requestPath' => $url['path'] . '/']) : null;
		$this->add('host', $resolved && $resolved->getSiteId() === $shopId && $resolved->getClientId() === $owner ? 'ready' : 'blocked');
		$this->add('https', $url && $url['scheme'] === 'https' ? 'ready' : 'warning');
		$this->add('indexing', !empty($shop['indexable']) ? 'ready' : 'warning');
		$language = $this->db->fetchOne($this->db->select()->from('language', ['id'])->where('clientid = ?', $owner)->where('code = ?', (string)$shop['language'])->where('deleted = 0'));
		$this->add('settings', $language && in_array($shop['timezone'], timezone_identifiers_list(), true) && trim((string)$shop['title']) !== '' ? 'ready' : 'blocked');
		$modulesMatch = $shipping->owner('admin') === $owner && $shipping->owner() === $owner && (!$catalog && !$checkout || $shipping->owner('items') === $owner);
		$this->add('modules', $modulesMatch ? 'ready' : 'blocked');
		$this->add('entitlements', 'warning', '', true);
		$pages = $this->rows('page', $owner, $shopId, 'activated = 1');
		$this->add('home', count(array_filter($pages, static fn(array $page): bool => $page['type'] === 'home')) === 1 ? 'ready' : 'blocked');
		$this->add('navigation', $this->rows('menu', $owner, $shopId, 'activated = 1') ? 'ready' : 'warning');

		$items = $this->rows('item', $owner, $shopId);
		$categories = $this->rows('category', $owner, $shopId, "activated = 1 AND type = 'shop'");
		$categoryIds = array_column($categories, 'id');
		$buyable = array_values(array_filter($items, static fn(array $item): bool => (bool)$item['shopenabled']));
		if ($catalog || $checkout) {
			$this->add('catalog', $catalog && $items ? 'ready' : 'blocked', (string)count($items));
			$invalidPrices = []; $unreachable = [];
			foreach ($items as $item) {
				if (Shops_Model_ShoppingCart::itemPrice($item) === null) $invalidPrices[] = $item['id'];
				if (!in_array($item['shopcatid'], $categoryIds)) $unreachable[] = $item['id'];
			}
			$this->add('prices', $invalidPrices ? 'blocked' : 'ready', implode(', ', $invalidPrices));
			$this->add('categories', !$categories || $unreachable ? 'blocked' : 'ready', implode(', ', $unreachable));
			$empty = array_filter($categories, static fn(array $category): bool => !in_array($category['id'], array_column($items, 'shopcatid')) && !in_array($category['id'], array_column($categories, 'parentid')));
			$this->add('empty_categories', $empty ? 'warning' : 'ready', implode(', ', array_column($empty, 'id')));
			$this->add('availability', $checkout && !$buyable ? 'blocked' : 'warning', count($buyable) . ' / ' . count($items), true);
			$this->checkImages($owner, $items);
		}
		$this->add('checkout', $checkout && $catalog ? 'ready' : ($checkout ? 'blocked' : 'warning'));
		$this->add('contact', !empty($shop['contactenabled']) || !empty($shop['inquiryenabled']) ? 'ready' : 'warning');
		if (!empty($shop['inquiryenabled'])) {
			$forms = $this->rows('shopinquiryform', $owner, $shopId);
			$valid = (bool)$forms;
			foreach ($forms as $form) {
				try { $valid = (bool)Shops_Model_FormConfig::normalize(Shops_Model_FormConfig::decode((string)$form['fields'])) && $valid; }
				catch (InvalidArgumentException $e) { $valid = false; }
			}
			$this->add('inquiry', $valid ? 'ready' : 'blocked');
		}
		$this->checkNotifications($shop, $owner);

		try {
			$missing = $shipping->missingSchema();
			$this->add('schema', $missing ? ($checkout ? 'blocked' : 'warning') : 'ready', implode(', ', $missing));
			if (!$missing && $modulesMatch) $this->checkShipping($shipping, $shop, $client ?: [], $buyable);
		} catch (Zend_Db_Exception $e) {
			$this->add('schema_access', $checkout ? 'blocked' : 'warning');
		}
		$this->add('legal', 'warning', '', true);
		$this->add('operations', 'warning', '', true);
		$this->add('journey', 'warning', '', true);
		$technical = array_filter($this->checks, static fn(array $check): bool => !$check['manual']);
		return ['status' => $this->status($this->checks), 'technicalStatus' => $this->status($technical), 'checks' => $this->checks];
	}

	private function rows(string $table, int $owner, int $shopId, ?string $condition = null): array
	{
		$query = $this->db->select()->from($table)->where('clientid = ?', $owner)->where('shopid = ?', $shopId)->where('deleted = 0');
		if ($condition !== null) $query->where($condition);
		return $this->db->fetchAll($query);
	}

	private function checkImages(int $owner, array $items): void
	{
		$images = $this->db->fetchAll($this->db->select()->from('media', ['parentid', 'url'])->where('clientid = ?', $owner)->where('module = ?', 'items')
			->where('controller = ?', 'item')->where('type = ?', 'image')->where('deleted = 0')->where('parentid IN (?)', array_column($items, 'id') ?: [0])->order(['ordering', 'id']));
		$first = [];
		foreach ($images as $image) if (!isset($first[$image['parentid']])) $first[$image['parentid']] = $image['url'];
		$missing = [];
		$prefix = 'media/' . (new DEEC_Directory())->getShortUrl($owner) . '/images/';
		foreach ($items as $item) {
			$source = (string)($first[$item['id']] ?? '');
			try {
				if ($source === '' || parse_url($source, PHP_URL_PATH) !== $source) throw new RuntimeException('Invalid image');
				$source = rawurldecode($source);
				if (array_filter(explode('/', $source), static fn(string $part): bool => !DEEC_Directory::validUploadName($part) || preg_match('/\.(?:inc|ini|sql|log)(?:\.|$)/i', $part))) throw new RuntimeException('Invalid image');
				$path = DEEC_Directory::storedPath($prefix . $source);
				if (!str_starts_with((string)(new finfo(FILEINFO_MIME_TYPE))->file($path), 'image/')) throw new RuntimeException('Invalid image');
			} catch (RuntimeException | InvalidArgumentException $e) { $missing[] = $item['id']; }
		}
		$this->add('images', $missing ? 'warning' : 'ready', implode(', ', $missing));
	}

	private function checkNotifications(array $shop, int $owner): void
	{
		$needed = !empty($shop['checkoutenabled']) || !empty($shop['contactenabled']) || !empty($shop['inquiryenabled']);
		if (!$needed) return;
		$host = trim((string)$shop['smtphost']);
		$valid = DEEC_Filter::isValidEmail(DEEC_Filter::email((string)$shop['emailsender'])) && $host !== '' && strlen($host) <= 255
			&& !preg_match('/[\x00-\x20\x7f]/', $host) && trim((string)$shop['smtpuser']) !== '' && (string)$shop['smtppass'] !== '';
		$this->add('notifications', $valid ? 'ready' : 'blocked');
		$controllers = [];
		if (!empty($shop['checkoutenabled'])) $controllers[] = 'checkout';
		if (!empty($shop['contactenabled']) || !empty($shop['checkoutenabled']) && !empty($shop['inquiryenabled'])) $controllers[] = 'contact';
		if (!empty($shop['inquiryenabled'])) $controllers[] = 'inquiry';
		$templates = $this->db->fetchAll($this->db->select()->from('emailtemplate', ['controller', 'subject', 'body'])->where('clientid = ?', $owner)->where('module = ?', 'shops')->where('controller IN (?)', $controllers));
		foreach ($templates as $template) if (trim((string)$template['subject']) !== '' && trim((string)$template['body']) !== '') $controllers = array_diff($controllers, [$template['controller']]);
		$this->add('templates', $controllers ? 'blocked' : 'ready', implode(', ', $controllers));
	}

	private function checkShipping(Shops_Service_ShippingConfiguration $shipping, array $shop, array $client, array $items): void
	{
		$checkout = !empty($shop['checkoutenabled']);
		if (!$checkout) return;
		if (empty($shop['shippingcurrency'])) { $this->add('legacy', 'blocked'); return; }
		$country = (string)($client['country'] ?? '');
		// Zend's legacy key maps alpha-2 codes to numeric codes; 900+ identifies private regions.
		$numeric = preg_match('/^[A-Z]{2}$/D', $country) ? Zend_Locale_Data::getContent('en', 'numerictoterritory', $country) : '';
		$known = $numeric !== '' && (int)$numeric > 0 && (int)$numeric < 900 && $this->db->fetchOne($this->db->select()->from('country', ['id'])->where('clientid = ?', $shop['clientid'])->where('code = ?', $country)->where('deleted = 0'));
		$this->add('country', $known ? 'ready' : 'blocked');
		try {
			$config = $shipping->configuration((int)$shop['id']);
		} catch (DomainException | JsonException $e) { $this->add('shipping_config', 'blocked', $e->getMessage()); return; }
		$this->add('shipping_config', $config['currency'] === 'EUR' && $config['zones'] && $config['tariffs'] ? 'ready' : 'blocked');
		$calculator = new Shops_Service_ShippingCalculator();
		$failures = []; $selection = []; $positiveFreight = false;
		foreach ($items as $item) $selection[] = ['itemid' => (int)$item['id'], 'quantity' => '1'];
		foreach ($config['zones'] as $zone) foreach ($zone['countries'] as $destination) {
			foreach ($selection as $line) {
				$result = $calculator->calculate(['clientid' => (int)$shop['clientid'], 'shopid' => (int)$shop['id']], $config, $items, [$line], ['country' => $destination, 'method' => 'delivery']);
				if ($result['status'] === 'calculated' && (float)$result['net'] > 0) $positiveFreight = true;
				if ($result['status'] !== 'calculated') $failures[] = '#' . $line['itemid'] . ' / ' . $destination . ': ' . $this->freightReasons($result);
			}
			if (count($selection) > 1) {
				$result = $calculator->calculate(['clientid' => (int)$shop['clientid'], 'shopid' => (int)$shop['id']], $config, $items, $selection, ['country' => $destination, 'method' => 'delivery']);
				if ($result['status'] === 'calculated' && (float)$result['net'] > 0) $positiveFreight = true;
				if ($result['status'] !== 'calculated') $failures[] = $destination . ': ' . $this->freightReasons($result);
			}
		}
		$this->add('freight', $failures || !$selection || !$config['zones'] ? 'blocked' : 'ready', implode('; ', $failures));
		$rates = $this->db->fetchPairs($this->db->select()->from('taxrate', ['id', 'rate'])->where('clientid = ?', $shop['clientid'])->where('deleted = 0'));
		$unknown = array_filter($items, static fn(array $item): bool => !isset($rates[$item['taxid']]) || !is_numeric($rates[$item['taxid']]) || (float)$rates[$item['taxid']] <= 0 || (float)$rates[$item['taxid']] > 100);
		$this->add('taxes', $unknown ? 'blocked' : 'ready', implode(', ', array_column($unknown, 'id')));
		$profile = ($shop['shippingtaxpolicy'] ?? null) === Shops_Service_ShippingConfiguration::TAX_POLICY && $country === 'DE';
		$itemRates = array_unique(array_map(static fn(array $item) => $rates[$item['taxid']] ?? null, $items));
		$profileRequired = count($itemRates) > 1 && $positiveFreight;
		$this->add('tax_policy', $profile ? 'ready' : ($profileRequired || !empty($shop['shippingtaxpolicy']) ? 'blocked' : 'warning'), array_key_exists('shippingtaxpolicy', $shop) ? '' : 'shop.shippingtaxpolicy', !$profileRequired && empty($shop['shippingtaxpolicy']));
		$foreign = [];
		foreach ($config['zones'] as $zone) foreach ($zone['countries'] as $destination) if ($destination !== $country) $foreign[] = $destination;
		$this->add('tax_destinations', $foreign ? 'warning' : 'ready', implode(', ', array_unique($foreign)), true);
		$this->add('tax_limits', 'warning', '', true);
	}

	private function freightReasons(array $result): string
	{
		$reasons = $result['reasons'];
		foreach ($result['groups'] as $group) $reasons = array_merge($reasons, $group['reasons']);
		return implode(', ', array_unique($reasons));
	}

	private function add(string $key, string $status, string $detail = '', bool $manual = false): void
	{
		$this->checks[] = ['key' => $key, 'status' => $status, 'detail' => $detail, 'manual' => $manual];
	}

	private function status(array $checks): string
	{
		$statuses = array_column($checks, 'status');
		return in_array('blocked', $statuses, true) ? 'blocked' : (in_array('warning', $statuses, true) ? 'warning' : 'ready');
	}
}
