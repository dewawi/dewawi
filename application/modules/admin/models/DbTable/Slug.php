<?php

class Admin_Model_DbTable_Slug extends DEEC_Model_DbTable_Entity
{
	protected const PUBLIC_CONTROLLERS = ['page', 'category', 'item', 'tag', 'inquiry'];

	protected $_name = 'slug';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getSlug($module, $controller, $shopid, $entityid)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		$where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		$where[] = $this->getAdapter()->quoteInto('entityid = ?', (int)$entityid);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', (int)$shopid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', (int)$this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		$row = $this->fetchRow($where);

		return $row ? $row->toArray() : [];
	}

	public function getEntitySlug($module, $controller, $entityid)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		$where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		$where[] = $this->getAdapter()->quoteInto('entityid = ?', (int)$entityid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', (int)$this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		$row = $this->fetchRow($where);

		return $row ? $row->toArray() : [];
	}

	public function getPath($module, $controller, $shopid, $entityid): ?string
	{
		$item = $this->getSlug($module, $controller, $shopid, $entityid);

		if (!$item) {
			return null;
		}

		$slug = trim((string)($item['slug'] ?? ''), '/');

		if ($slug === '') {
			return null;
		}

		$path = $slug;
		$visited = [(int)$entityid => true];

		while (!empty($item['parentid'])) {
			$parentId = (int)$item['parentid'];

			if (isset($visited[$parentId])) {
				return null;
			}

			$visited[$parentId] = true;
			$item = $this->getSlug($module, $controller, $shopid, $parentId);

			if (!$item) {
				return null;
			}

			$slug = trim((string)($item['slug'] ?? ''), '/');

			if ($slug === '') {
				return null;
			}

			$path = $slug . '/' . $path;
		}

		return $path;
	}

	public function addSlug($module, $controller, $shopid, $parentid, $entityid, $slug)
	{
		$shopid = (int)$shopid;
		$parentid = (int)$parentid;
		$entityid = (int)$entityid;
		if ($module === 'shops' && $shopid > 0) $this->assertShopAccess($shopid);
		$slug = $this->normalizeSlugValue($slug, $entityid);
		$redirects = $this->assertSlugAvailable($module, $controller, $shopid, $parentid, $entityid, $slug);

		$data = array();
		$data['module'] = $module;
		$data['controller'] = $controller;
		$data['shopid'] = $shopid;
		$data['entityid'] = $entityid;
		$data['parentid'] = $parentid;
		$data['slug'] = $slug;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['clientid'] = $this->_client['id'];
		$this->insert($data);
		$id = (int)$this->getAdapter()->lastInsertId();
		$this->saveRedirects($shopid, $redirects);
		return $id;
	}

	public function updateSlug($module, $controller, $shopid, $parentid, $entityid, $slug = null)
	{
		$shopid = (int)$shopid;
		$parentid = (int)$parentid;
		$entityid = (int)$entityid;
		if ($module === 'shops' && $shopid > 0) $this->assertShopAccess($shopid);
		$existing = $this->getSlug($module, $controller, $shopid, $entityid);

		if (!$existing) return;

		$slugValue = $slug !== null
			? $this->normalizeSlugValue($slug, $entityid)
			: (string)$existing['slug'];
		$redirects = $this->assertSlugAvailable($module, $controller, $shopid, $parentid, $entityid, $slugValue, (int)$existing['id']);

		$where = array();
		$where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		$where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		$where[] = $this->getAdapter()->quoteInto('entityid = ?', (int)$entityid);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', (int)$shopid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', (int)$this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		$data = array();
		$data['parentid'] = $parentid;
		if($slug !== null) $data['slug'] = $slugValue;
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];

		$this->update($data, $where);
		$this->saveRedirects($shopid, $redirects);
	}

	public function saveSlug($module, $controller, $shopid, $parentid, $entityid, $slug = null)
	{
		$shopid = (int)$shopid;
		$parentid = (int)$parentid;
		$entityid = (int)$entityid;
		$existing = $this->getEntitySlug($module, $controller, $entityid);

		if ($shopid <= 0) {
			if ($existing) {
				$this->assertSlugHasNoChildren($existing);
				$this->deleteSlugById((int)$existing['id']);
			}

			return 0;
		}

		if ($module === 'shops') $this->assertShopAccess($shopid);

		if ($existing) {
			if ((int)$existing['shopid'] !== $shopid) $this->assertSlugHasNoChildren($existing);

			$data = array();
			$data['shopid'] = $shopid;
			$data['parentid'] = $parentid;
			$slugValue = $slug !== null
				? $this->normalizeSlugValue($slug, $entityid)
				: (string)$existing['slug'];

			if ($slug !== null) $data['slug'] = $slugValue;
			$redirects = $this->assertSlugAvailable($module, $controller, $shopid, $parentid, $entityid, $slugValue, (int)$existing['id']);

			$data['modified'] = $this->_date;
			$data['modifiedby'] = $this->_user['id'];

			$this->update($data, 'id = ' . (int)$existing['id']);
			$this->saveRedirects($shopid, $redirects);

			return (int)$existing['id'];
		}

		return $this->addSlug($module, $controller, $shopid, $parentid, $entityid, $slug);
	}

	public function sortSlug($id, $ordering)
	{
		$data = array();
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$data['ordering'] = $ordering;
		$this->updateById((int)$id, $data);
	}

	public function deleteSlug($module, $controller, $shopid, $entityid)
	{
		$existing = $this->getSlug($module, $controller, $shopid, $entityid);

		if ($existing) $this->assertSlugHasNoChildren($existing);

		$where = array();
		$where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		$where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		$where[] = $this->getAdapter()->quoteInto('shopid = ?', (int)$shopid);
		$where[] = $this->getAdapter()->quoteInto('entityid = ?', (int)$entityid);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', (int)$this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		$this->update(array('deleted' => 1), $where);
	}

	public function deleteSlugById($id)
	{
		$this->update(array(
			'deleted' => 1,
			'modified' => $this->_date,
			'modifiedby' => $this->_user['id'],
		), 'id = ' . (int)$id);
	}

	protected function normalizeSlugValue($slug, int $entityId): string
	{
		$slug = DEEC_Filter::slug((string)$slug);

		return $slug !== '' ? $slug : (string)$entityId;
	}

	protected function assertShopAccess(int $shopId): void
	{
		if (!(new Admin_Model_DbTable_Shop())->getByIdForUpdate($shopId)) {
			throw new DomainException('MESSAGES_SLUG_SHOP_INVALID');
		}
	}

	protected function assertSlugAvailable($module, $controller, int $shopId, int $parentId, int $entityId, string $slug, int $slugId = 0): array
	{
		if ($module !== 'shops' || !in_array($controller, self::PUBLIC_CONTROLLERS, true)) return [];

		$rows = $this->getActiveSlugs($shopId);
		$oldRows = $rows;
		$candidate = [
			'id' => $slugId,
			'module' => $module,
			'controller' => $controller,
			'shopid' => $shopId,
			'parentid' => $parentId,
			'entityid' => $entityId,
			'slug' => $slug,
		];
		$replaced = false;

		foreach ($rows as &$row) {
			if ($slugId > 0 && (int)$row['id'] === $slugId) {
				$row = array_replace($row, $candidate);
				$replaced = true;
				break;
			}
		}
		unset($row);

		if (!$replaced) $rows[] = $candidate;

		$map = [];
		foreach ($rows as $row) {
			if (!in_array((string)$row['controller'], self::PUBLIC_CONTROLLERS, true)) continue;
			$key = $this->getSlugKey($row);
			if (!isset($map[$key])) $map[$key] = $row;
		}
		$candidateKey = $this->getSlugKey($candidate);
		$map[$candidateKey] = $candidate;
		$affected = $this->getAffectedSlugKeys($rows, $candidateKey);
		$paths = [];
		$pathsByKey = [];

		foreach ($rows as $row) {
			if (!in_array((string)$row['controller'], self::PUBLIC_CONTROLLERS, true)) continue;

			$key = $this->getSlugKey($row);
			$path = $this->buildSlugPath($row, $map);

			if (isset($affected[$key])) {
				if ($path === null) throw new DomainException('MESSAGES_SLUG_PARENT_INVALID');
				if (DEEC_Site_Router::isReservedPath($path)) throw new DomainException('MESSAGES_SLUG_RESERVED');
			}

			if ($path === null) continue;
			$pathsByKey[$key] = $path;

			if (isset($paths[$path])) {
				if (isset($affected[$key]) || isset($affected[$paths[$path]['key']])) {
					throw new DomainException('MESSAGES_SLUG_CONFLICT');
				}
				continue;
			}

			$paths[$path] = ['key' => $key, 'id' => (int)($row['id'] ?? 0)];
		}

		$redirectRows = $this->getRedirects($shopId);
		$redirectsByPath = [];

		foreach ($redirectRows as $redirect) {
			$redirectsByPath[(string)$redirect['path']] = $redirect;
		}

		foreach ($pathsByKey as $key => $path) {
			if (!isset($affected[$key], $redirectsByPath[$path])) continue;

			$redirect = $redirectsByPath[$path];
			$targetKey = (string)$redirect['controller'] . ':' . (int)$redirect['entityid'];
			if ($targetKey !== $key) throw new DomainException('MESSAGES_REDIRECT_CONFLICT');
		}

		if ($slugId <= 0) return [];

		$oldPathsByKey = $this->getPathsByKey($oldRows);
		$redirects = [];

		foreach ($affected as $key => $unused) {
			$oldPath = $oldPathsByKey[$key] ?? null;
			$newPath = $pathsByKey[$key] ?? null;

			if (!$oldPath || !$newPath || $oldPath === $newPath) continue;
			if (!DEEC_Site_Router::isRedirectablePath($oldPath)) continue;
			if (isset($paths[$oldPath]) && $paths[$oldPath]['key'] !== $key) throw new DomainException('MESSAGES_REDIRECT_CONFLICT');

			$row = $map[$key] ?? null;
			if (!$row) throw new DomainException('MESSAGES_REDIRECT_TARGET_INVALID');

			if (isset($redirectsByPath[$oldPath])) {
				$redirect = $redirectsByPath[$oldPath];
				$targetKey = (string)$redirect['controller'] . ':' . (int)$redirect['entityid'];
				if ($targetKey !== $key) throw new DomainException('MESSAGES_REDIRECT_CONFLICT');
			}

			$redirects[] = [
				'path' => $oldPath,
				'controller' => (string)$row['controller'],
				'entityid' => (int)$row['entityid'],
			];
		}

		return $redirects;
	}

	protected function assertSlugHasNoChildren(array $slug): void
	{
		$controller = (string)($slug['controller'] ?? '');

		if (!in_array($controller, self::PUBLIC_CONTROLLERS, true)) return;

		foreach ($this->getActiveSlugs((int)$slug['shopid']) as $row) {
			if ((int)$row['parentid'] !== (int)$slug['entityid']) continue;
			if ($this->getParentController((string)$row['controller']) !== $controller) continue;

			throw new DomainException('MESSAGES_SLUG_HAS_CHILDREN');
		}
	}

	protected function getActiveSlugs(int $shopId): array
	{
		return $this->fetchAll(
			$this->select()
				->where('clientid = ?', (int)$this->_client['id'])
				->where('shopid = ?', $shopId)
				->where('module = ?', 'shops')
				->where('deleted = ?', 0)
				->order('id ASC')
				->forUpdate(true)
		)->toArray();
	}

	protected function getRedirects(int $shopId): array
	{
		return (new Admin_Model_DbTable_Slugredirect())->getAllByShopForUpdate($shopId);
	}

	protected function saveRedirects(int $shopId, array $redirects): void
	{
		if (!$redirects) return;

		$db = new Admin_Model_DbTable_Slugredirect();

		foreach ($redirects as $redirect) {
			$db->savePath($shopId, $redirect['path'], $redirect['controller'], (int)$redirect['entityid']);
		}
	}

	protected function getPathsByKey(array $rows): array
	{
		$map = [];

		foreach ($rows as $row) {
			if (!in_array((string)$row['controller'], self::PUBLIC_CONTROLLERS, true)) continue;
			$key = $this->getSlugKey($row);
			if (!isset($map[$key])) $map[$key] = $row;
		}

		$paths = [];

		foreach ($map as $key => $row) {
			$path = $this->buildSlugPath($row, $map);
			if ($path !== null) $paths[$key] = $path;
		}

		return $paths;
	}

	protected function getAffectedSlugKeys(array $rows, string $candidateKey): array
	{
		$affected = [$candidateKey => true];
		$changed = true;

		while ($changed) {
			$changed = false;

			foreach ($rows as $row) {
				$parentId = (int)($row['parentid'] ?? 0);
				if ($parentId <= 0) continue;

				$parentKey = $this->getParentController((string)$row['controller']) . ':' . $parentId;
				$key = $this->getSlugKey($row);

				if (isset($affected[$parentKey]) && !isset($affected[$key])) {
					$affected[$key] = true;
					$changed = true;
				}
			}
		}

		return $affected;
	}

	protected function buildSlugPath(array $row, array $map): ?string
	{
		$segments = [];
		$visited = [];

		while (true) {
			$key = $this->getSlugKey($row);
			if (isset($visited[$key])) return null;
			$visited[$key] = true;

			$slug = trim((string)($row['slug'] ?? ''), '/');
			if ($slug === '') return null;
			array_unshift($segments, $slug);

			$parentId = (int)($row['parentid'] ?? 0);
			if ($parentId <= 0) break;

			$parentKey = $this->getParentController((string)$row['controller']) . ':' . $parentId;
			if (!isset($map[$parentKey])) return null;
			$row = $map[$parentKey];
		}

		return implode('/', $segments);
	}

	protected function getSlugKey(array $row): string
	{
		return (string)$row['controller'] . ':' . (int)$row['entityid'];
	}

	protected function getParentController(string $controller): string
	{
		return $controller === 'item' ? 'category' : $controller;
	}
}
