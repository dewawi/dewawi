<?php

class Admin_Model_DbTable_Slugredirect extends DEEC_Model_DbTable_Entity
{
	public const MAX_CSV_SIZE = 262144;
	public const MAX_CSV_ROWS = 500;
	public const STATUS_REDIRECT = 'redirect';
	public const STATUS_GONE = 'gone';
	protected const CONTROLLERS = ['page', 'category', 'item', 'tag', 'inquiry'];

	protected $_name = 'slugredirect';
	protected ?string $orderingField = null;

	public function getAllByShop(int $shopId): array
	{
		$this->assertShopAccess($shopId, false);

		return $this->fetchRedirectRows($shopId, false);
	}

	public function getAllByShopForUpdate(int $shopId): array
	{
		$this->assertShopAccess($shopId, true);

		return $this->fetchRedirectRows($shopId, true);
	}

	public function getTargetsByShop(int $shopId): array
	{
		$this->assertShopAccess($shopId, false);
		$targets = [];
		$ambiguous = [];

		foreach ($this->getTargetSlugRows($shopId) as $row) {
			$controller = (string)($row['controller'] ?? '');
			$entityId = (int)($row['entityid'] ?? 0);
			$path = (string)($row['path'] ?? '');

			if (!in_array($controller, self::CONTROLLERS, true) || !DEEC_Site_Router::isRedirectablePath($path)) continue;
			if (!$this->isTargetAvailable($shopId, $controller, $entityId, true)) continue;
			if (isset($targets[$path])) {
				unset($targets[$path]);
				$ambiguous[$path] = true;
			} elseif (!isset($ambiguous[$path])) {
				$targets[$path] = ['path' => $path, 'controller' => $controller, 'entityid' => $entityId];
			}
		}

		ksort($targets, SORT_NATURAL | SORT_FLAG_CASE);

		return array_values($targets);
	}

	public function getManagementRows(int $shopId): array
	{
		$targets = [];
		foreach ($this->getTargetsByShop($shopId) as $target) {
			$targets[(string)$target['controller'] . ':' . (int)$target['entityid']] = (string)$target['path'];
		}

		$rows = $this->getAllByShop($shopId);
		foreach ($rows as &$row) {
			$row['targetpath'] = $targets[(string)$row['controller'] . ':' . (int)$row['entityid']] ?? null;
		}
		unset($row);

		return $rows;
	}

	public function saveManualPath(int $shopId, string $path, string $targetPath): int
	{
		$target = $this->resolveTargetPath($shopId, $targetPath);

		return $this->saveAssignment($shopId, $path, $target['controller'], (int)$target['entityid'], true);
	}

	public function savePath(int $shopId, string $path, string $controller, int $entityId): int
	{
		return $this->saveAssignment($shopId, $path, $controller, $entityId, false);
	}

	public function saveGonePath(int $shopId, string $path): int
	{
		$this->assertShopAccess($shopId, true);
		$this->validatePath($path);

		foreach ($this->getTargetSlugRows($shopId) as $target) {
			if ((string)$target['path'] === $path) throw new DomainException('MESSAGES_REDIRECT_CURRENT_PATH_CONFLICT');
		}

		$row = $this->findRedirectByPath($shopId, $path, true);
		if ($row && ((string)$row['path'] !== $path || (string)($row['status'] ?? self::STATUS_REDIRECT) !== self::STATUS_GONE)) {
			throw new DomainException('MESSAGES_REDIRECT_CONFLICT');
		}
		if ($row && empty($row['deleted'])) return (int)$row['id'];

		$data = [
			'path' => $path,
			'pathhash' => hash('sha256', $path),
			'status' => self::STATUS_GONE,
			'module' => 'shops',
			'controller' => '',
			'entityid' => 0,
			'deleted' => 0,
		];

		if ($row) {
			$data['modified'] = $this->_date;
			$data['modifiedby'] = $this->getUserId();
			$this->update($data, 'id = ' . (int)$row['id']);

			return (int)$row['id'];
		}

		$data['shopid'] = $shopId;
		$data['clientid'] = $this->getClientId();
		$data['created'] = $this->_date;
		$data['createdby'] = $this->getUserId();
		$this->insert($data);

		return (int)$this->getAdapter()->lastInsertId();
	}

	public function disablePath(int $shopId, int $id): void
	{
		$this->assertShopAccess($shopId, true);
		$row = $this->findRedirectById($shopId, $id, true);
		if (!$row) throw new DomainException('MESSAGES_REDIRECT_NOT_FOUND');

		$this->update([
			'deleted' => 1,
			'modified' => $this->_date,
			'modifiedby' => $this->getUserId(),
		], 'id = ' . (int)$row['id']);
	}

	public function parseCsv(string $contents): array
	{
		if ($contents === '' || strlen($contents) > self::MAX_CSV_SIZE || !mb_check_encoding($contents, 'UTF-8')) {
			throw new DomainException('MESSAGES_REDIRECT_CSV_INVALID');
		}

		$stream = fopen('php://temp', 'w+b');
		fwrite($stream, $contents);
		rewind($stream);
		$header = fgetcsv($stream, 0, ',', '"', '');

		if ($header === false) {
			fclose($stream);
			throw new DomainException('MESSAGES_REDIRECT_CSV_INVALID');
		}

		$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
		if ($header !== ['old_path', 'target_path']) {
			fclose($stream);
			throw new DomainException('MESSAGES_REDIRECT_CSV_HEADER_INVALID');
		}

		$rows = [];
		$line = 1;
		while (($cells = fgetcsv($stream, 0, ',', '"', '')) !== false) {
			$line++;
			if (count($cells) === 1 && trim((string)$cells[0]) === '') continue;
			if (count($cells) !== 2) {
				$rows[] = ['line' => $line, 'old_path' => '', 'target_path' => '', 'parse_error' => 'MESSAGES_REDIRECT_CSV_ROW_INVALID'];
			} else {
				$rows[] = ['line' => $line, 'old_path' => (string)$cells[0], 'target_path' => (string)$cells[1]];
			}
			if (count($rows) > self::MAX_CSV_ROWS) {
				fclose($stream);
				throw new DomainException('MESSAGES_REDIRECT_CSV_TOO_MANY_ROWS');
			}
		}
		fclose($stream);

		if (!$rows) throw new DomainException('MESSAGES_REDIRECT_CSV_INVALID');

		return $rows;
	}

	public function previewImport(int $shopId, array $rows): array
	{
		$this->assertShopAccess($shopId, false);
		$seen = [];
		$preview = [];

		foreach ($rows as $row) {
			$item = $row + ['line' => 0, 'old_path' => '', 'target_path' => ''];
			$item['status'] = 'new';
			$item['error'] = $item['parse_error'] ?? null;

			try {
				if ($item['error']) throw new DomainException((string)$item['error']);
				$target = $this->resolveTargetPath($shopId, (string)$item['target_path']);
				$result = $this->inspectAssignment($shopId, (string)$item['old_path'], $target['controller'], (int)$target['entityid']);
				$key = hash('sha256', (string)$item['old_path']);
				$targetKey = $target['controller'] . ':' . (int)$target['entityid'];

				if (isset($seen[$key]) && $seen[$key] !== $targetKey) throw new DomainException('MESSAGES_REDIRECT_CONFLICT');
				if (isset($seen[$key])) $item['status'] = 'duplicate';
				else $item['status'] = $result;
				$seen[$key] = $targetKey;
			} catch (DomainException $e) {
				$item['status'] = 'error';
				$item['error'] = $e->getMessage();
			}

			$preview[] = $item;
		}

		return $preview;
	}

	public function importPaths(int $shopId, array $rows): array
	{
		$adapter = $this->getAdapter();
		$adapter->beginTransaction();

		try {
			$preview = $this->previewImport($shopId, $rows);
			foreach ($preview as $item) {
				if ($item['status'] === 'error') throw new DomainException('MESSAGES_REDIRECT_IMPORT_INVALID');
			}

			$created = 0;
			$skipped = 0;
			foreach ($preview as $item) {
				if (in_array($item['status'], ['existing', 'duplicate'], true)) {
					$skipped++;
					continue;
				}
				$this->saveManualPath($shopId, (string)$item['old_path'], (string)$item['target_path']);
				$created++;
			}

			$adapter->commit();
			return ['created' => $created, 'skipped' => $skipped];
		} catch (Throwable $e) {
			$adapter->rollBack();
			throw $e;
		}
	}

	protected function saveAssignment(int $shopId, string $path, string $controller, int $entityId, bool $publicOnly): int
	{
		$this->assertShopAccess($shopId, true);
		$status = $this->inspectAssignment($shopId, $path, $controller, $entityId, $publicOnly);
		$row = $this->findRedirectByPath($shopId, $path, true);

		if ($status === 'existing' && $row && empty($row['deleted'])) return (int)$row['id'];

		$hash = hash('sha256', $path);
		$data = [
			'path' => $path,
			'pathhash' => $hash,
			'status' => self::STATUS_REDIRECT,
			'module' => 'shops',
			'controller' => $controller,
			'entityid' => $entityId,
			'deleted' => 0,
		];

		if ($row) {
			$data['modified'] = $this->_date;
			$data['modifiedby'] = $this->getUserId();
			$this->update($data, 'id = ' . (int)$row['id']);

			return (int)$row['id'];
		}

		$data['shopid'] = $shopId;
		$data['clientid'] = $this->getClientId();
		$data['created'] = $this->_date;
		$data['createdby'] = $this->getUserId();
		$this->insert($data);

		return (int)$this->getAdapter()->lastInsertId();
	}

	protected function inspectAssignment(int $shopId, string $path, string $controller, int $entityId, bool $publicOnly = true): string
	{
		$this->validatePath($path);
		if ($entityId <= 0 || !in_array($controller, self::CONTROLLERS, true)
			|| !$this->isTargetAvailable($shopId, $controller, $entityId, $publicOnly)) {
			throw new DomainException('MESSAGES_REDIRECT_TARGET_INVALID');
		}

		foreach ($this->getTargetSlugRows($shopId) as $target) {
			if ((string)$target['path'] !== $path) continue;
			throw new DomainException('MESSAGES_REDIRECT_CURRENT_PATH_CONFLICT');
		}

		$row = $this->findRedirectByPath($shopId, $path, false);
		if (!$row) return 'new';
		if ((string)$row['path'] !== $path || (string)($row['status'] ?? self::STATUS_REDIRECT) !== self::STATUS_REDIRECT
			|| (string)$row['controller'] !== $controller || (int)$row['entityid'] !== $entityId) {
			throw new DomainException('MESSAGES_REDIRECT_CONFLICT');
		}

		return empty($row['deleted']) ? 'existing' : 'restore';
	}

	protected function resolveTargetPath(int $shopId, string $targetPath): array
	{
		$this->validatePath($targetPath);
		$found = null;

		foreach ($this->getTargetsByShop($shopId) as $target) {
			if ($target['path'] !== $targetPath) continue;
			if ($found !== null) throw new DomainException('MESSAGES_REDIRECT_TARGET_INVALID');
			$found = $target;
		}

		if ($found === null) throw new DomainException('MESSAGES_REDIRECT_TARGET_INVALID');

		return $found;
	}

	protected function validatePath(string $path): void
	{
		if ($path === '' || $path !== trim($path) || str_starts_with($path, '/') || str_ends_with($path, '/')
			|| !DEEC_Site_Router::isRedirectablePath($path)) {
			throw new DomainException('MESSAGES_REDIRECT_PATH_INVALID');
		}
	}

	protected function assertShopAccess(int $shopId, bool $forUpdate): void
	{
		$shop = $forUpdate
			? (new Admin_Model_DbTable_Shop())->getByIdForUpdate($shopId)
			: (new Admin_Model_DbTable_Shop())->getById($shopId);
		if (!$shop) throw new DomainException('MESSAGES_SLUG_SHOP_INVALID');
	}

	protected function getTargetSlugRows(int $shopId): array
	{
		return (new Admin_Model_DbTable_Slug())->getPublicPathsByShop($shopId);
	}

	protected function isTargetAvailable(int $shopId, string $controller, int $entityId, bool $publicOnly): bool
	{
		$table = ['page' => 'page', 'category' => 'category', 'item' => 'item', 'tag' => 'tag', 'inquiry' => 'shopinquiryform'][$controller] ?? null;
		if (!$table) return false;

		if ($publicOnly && in_array($controller, ['category', 'item', 'tag', 'inquiry'], true)) {
			$feature = $controller === 'inquiry' ? 'inquiryenabled' : 'catalogenabled';
			$enabled = $this->getAdapter()->fetchOne($this->getAdapter()->select()->from('shop', [$feature])->where('id = ?', $shopId)->where('clientid = ?', $this->getClientId())->where('deleted = ?', 0)->limit(1));
			if (!(int)$enabled) return false;
		}

		$select = $this->getAdapter()->select()->from($table, ['id'])->where('id = ?', $entityId)->where('shopid = ?', $shopId)->where('clientid = ?', $this->getClientId())->where('deleted = ?', 0)->limit(1);
		if ($publicOnly && in_array($controller, ['page', 'category'], true)) $select->where('activated = ?', 1);
		if ($controller === 'page') $select->where('type != ?', 'home');
		if ($controller === 'category') $select->where('type = ?', 'shop');
		$row = $this->getAdapter()->fetchRow($select);
		if (!$row) return false;

		if ($controller === 'item' && $publicOnly) {
			$item = $this->getAdapter()->fetchRow($this->getAdapter()->select()->from('item', ['shopcatid'])->where('id = ?', $entityId)->where('shopid = ?', $shopId)->where('clientid = ?', $this->getClientId())->where('deleted = ?', 0)->limit(1));
			if (!$item || empty($item['shopcatid'])) return false;
			$category = $this->getAdapter()->fetchRow($this->getAdapter()->select()->from('category', ['id'])->where('id = ?', (int)$item['shopcatid'])->where('shopid = ?', $shopId)->where('clientid = ?', $this->getClientId())->where('type = ?', 'shop')->where('activated = ?', 1)->where('deleted = ?', 0)->limit(1));
			if (!$category) return false;
		}

		return true;
	}

	protected function fetchRedirectRows(int $shopId, bool $forUpdate): array
	{
		return $this->fetchAll($this->select()->where('clientid = ?', $this->getClientId())->where('shopid = ?', $shopId)->order('id ASC')->forUpdate($forUpdate))->toArray();
	}

	protected function findRedirectByPath(int $shopId, string $path, bool $forUpdate): ?array
	{
		$row = $this->fetchRow($this->select()->where('clientid = ?', $this->getClientId())->where('shopid = ?', $shopId)->where('pathhash = ?', hash('sha256', $path))->limit(1)->forUpdate($forUpdate));

		return $row ? $row->toArray() : null;
	}

	protected function findRedirectById(int $shopId, int $id, bool $forUpdate): ?array
	{
		$row = $this->fetchRow($this->select()->where('id = ?', $id)->where('clientid = ?', $this->getClientId())->where('shopid = ?', $shopId)->limit(1)->forUpdate($forUpdate));

		return $row ? $row->toArray() : null;
	}
}
