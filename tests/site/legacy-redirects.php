<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/site/legacy-redirects.php (no database required).

error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path;
});

require_once $root . '/library/DEEC/Site/Router.php';
require_once $root . '/library/DEEC/Site/Context.php';
require_once $root . '/application/modules/admin/models/DbTable/Slugredirect.php';
require_once $root . '/application/modules/admin/controllers/ShopController.php';

$checks = 0;

function checkLegacy(bool $condition, string $message): void
{
	global $checks;
	$checks++;
	if (!$condition) throw new RuntimeException($message);
}

function expectLegacy(string $message, callable $callback): void
{
	try {
		$callback();
	} catch (DomainException $e) {
		checkLegacy($e->getMessage() === $message, 'Unexpected redirect error: ' . $e->getMessage());
		return;
	}

	checkLegacy(false, 'Expected redirect error was not raised: ' . $message);
}

class LegacyRedirectAdapter
{
	private LegacyRedirectTable $table;
	private ?array $snapshot = null;

	public function __construct(LegacyRedirectTable $table)
	{
		$this->table = $table;
	}

	public function beginTransaction(): void
	{
		$this->snapshot = [$this->table->redirects, $this->table->nextId];
	}

	public function commit(): void
	{
		$this->snapshot = null;
	}

	public function rollBack(): void
	{
		if ($this->snapshot === null) return;
		[$this->table->redirects, $this->table->nextId] = $this->snapshot;
		$this->snapshot = null;
	}

	public function lastInsertId(): int
	{
		return $this->table->nextId - 1;
	}
}

class LegacyRedirectTable extends Admin_Model_DbTable_Slugredirect
{
	public array $redirects = [];
	public array $targets = [];
	public array $available = [];
	public array $shops = [7];
	public int $nextId = 1;
	public ?string $failAfterPath = null;
	private LegacyRedirectAdapter $adapter;

	public function __construct()
	{
		$this->_date = '2026-11-01 12:00:00';
		$this->_user = ['id' => 9];
		$this->_client = ['id' => 3];
		$this->adapter = new LegacyRedirectAdapter($this);
	}

	public function getAdapter()
	{
		return $this->adapter;
	}

	public function getClientId(): int
	{
		return 3;
	}

	public function saveManualPath(int $shopId, string $path, string $targetPath): int
	{
		$id = parent::saveManualPath($shopId, $path, $targetPath);
		if ($path === $this->failAfterPath) throw new RuntimeException('Simulated storage failure');
		return $id;
	}

	public function insert(array $data)
	{
		$data['id'] = $this->nextId++;
		$this->redirects[] = $data;
		return $data['id'];
	}

	public function update(array $data, $where)
	{
		if (!preg_match('/id = ([0-9]+)/', (string)$where, $match)) return 0;
		$updated = 0;
		foreach ($this->redirects as &$row) {
			if ((int)$row['id'] !== (int)$match[1]) continue;
			$row = array_replace($row, $data);
			$updated++;
		}
		unset($row);
		return $updated;
	}

	protected function assertShopAccess(int $shopId, bool $forUpdate): void
	{
		if (!in_array($shopId, $this->shops, true)) throw new DomainException('MESSAGES_SLUG_SHOP_INVALID');
	}

	protected function getTargetSlugRows(int $shopId): array
	{
		return $shopId === 7 ? $this->targets : [];
	}

	protected function isTargetAvailable(int $shopId, string $controller, int $entityId, bool $publicOnly): bool
	{
		return !empty($this->available[$shopId][$controller . ':' . $entityId]);
	}

	protected function fetchRedirectRows(int $shopId, bool $forUpdate): array
	{
		return array_values(array_filter($this->redirects, static fn(array $row): bool => (int)$row['shopid'] === $shopId && (int)$row['clientid'] === 3));
	}

	protected function findRedirectByPath(int $shopId, string $path, bool $forUpdate): ?array
	{
		foreach ($this->fetchRedirectRows($shopId, $forUpdate) as $row) {
			if ((string)$row['pathhash'] === hash('sha256', $path)) return $row;
		}
		return null;
	}

	protected function findRedirectById(int $shopId, int $id, bool $forUpdate): ?array
	{
		foreach ($this->fetchRedirectRows($shopId, $forUpdate) as $row) {
			if ((int)$row['id'] === $id) return $row;
		}
		return null;
	}
}

$db = new LegacyRedirectTable();
$db->targets = [
	['controller' => 'page', 'entityid' => 11, 'path' => 'informationen/aktuell'],
	['controller' => 'category', 'entityid' => 12, 'path' => 'produkte'],
	['controller' => 'item', 'entityid' => 13, 'path' => 'produkte/geraet'],
	['controller' => 'tag', 'entityid' => 14, 'path' => 'themen/energie'],
	['controller' => 'inquiry', 'entityid' => 15, 'path' => 'projekt/anfrage'],
	['controller' => 'page', 'entityid' => 16, 'path' => 'fremder-mandant'],
	['controller' => 'page', 'entityid' => 17, 'path' => 'geloeschtes-ziel'],
];
$db->available[7] = ['page:11' => true, 'category:12' => true, 'item:13' => true, 'tag:14' => true, 'inquiry:15' => true];

$id = $db->saveManualPath(7, 'alte-seite', 'informationen/aktuell');
checkLegacy($id === 1 && count($db->redirects) === 1 && $db->redirects[0]['controller'] === 'page', 'Manual redirect was not stored by current entity target');
checkLegacy($db->saveManualPath(7, 'alte-seite', 'informationen/aktuell') === 1 && count($db->redirects) === 1, 'Identical redirect was inserted twice');
$db->disablePath(7, 1);
checkLegacy((int)$db->redirects[0]['deleted'] === 1, 'Redirect was not disabled');
checkLegacy($db->saveManualPath(7, 'alte-seite', 'informationen/aktuell') === 1 && (int)$db->redirects[0]['deleted'] === 0, 'Disabled redirect was not restored');
checkLegacy($db->savePath(7, 'automatisch-alt', 'page', 11) === 2, 'Automatic redirect storage no longer uses the central validation path');

expectLegacy('MESSAGES_REDIRECT_CURRENT_PATH_CONFLICT', fn() => $db->saveManualPath(7, 'produkte', 'informationen/aktuell'));
expectLegacy('MESSAGES_REDIRECT_CONFLICT', fn() => $db->saveManualPath(7, 'alte-seite', 'produkte'));
foreach (['search', 'files/manual.pdf', 'asset.css', '../secret', 'https://example.test/page', '/absolute', 'trailing/'] as $path) {
	expectLegacy('MESSAGES_REDIRECT_PATH_INVALID', fn() => $db->saveManualPath(7, $path, 'informationen/aktuell'));
}
expectLegacy('MESSAGES_REDIRECT_TARGET_INVALID', fn() => $db->saveManualPath(7, 'missing-target', 'nicht-vorhanden'));
expectLegacy('MESSAGES_REDIRECT_TARGET_INVALID', fn() => $db->saveManualPath(7, 'foreign-tenant', 'fremder-mandant'));
expectLegacy('MESSAGES_REDIRECT_TARGET_INVALID', fn() => $db->saveManualPath(7, 'foreign-shop-target', 'anderer-shop/ziel'));
$db->available[7]['page:11'] = false;
expectLegacy('MESSAGES_REDIRECT_TARGET_INVALID', fn() => $db->saveManualPath(7, 'deleted-target', 'informationen/aktuell'));
$db->available[7]['page:11'] = true;
$db->redirects[] = ['id' => $db->nextId++, 'clientid' => 3, 'shopid' => 8, 'path' => 'other-shop-row', 'pathhash' => hash('sha256', 'other-shop-row'), 'controller' => 'page', 'entityid' => 11, 'deleted' => 0];
$db->redirects[] = ['id' => $db->nextId++, 'clientid' => 4, 'shopid' => 7, 'path' => 'other-tenant-row', 'pathhash' => hash('sha256', 'other-tenant-row'), 'controller' => 'page', 'entityid' => 11, 'deleted' => 0];
$db->shops = [7, 8];
expectLegacy('MESSAGES_REDIRECT_NOT_FOUND', fn() => $db->disablePath(7, 3));
expectLegacy('MESSAGES_REDIRECT_NOT_FOUND', fn() => $db->disablePath(7, 4));
$db->shops = [7];
expectLegacy('MESSAGES_SLUG_SHOP_INVALID', fn() => $db->saveManualPath(8, 'foreign-shop', 'informationen/aktuell'));
$db->shops = [];
expectLegacy('MESSAGES_SLUG_SHOP_INVALID', fn() => $db->getAllByShop(7));
$db->shops = [7];

$csv = "\xEF\xBB\xBFold_path,target_path\nalt-eins,informationen/aktuell\nalt-zwei,produkte/geraet\n";
$rows = $db->parseCsv($csv);
$preview = $db->previewImport(7, $rows);
checkLegacy(count($preview) === 2 && !$preview[0]['error'] && $preview[0]['status'] === 'new', 'Valid CSV preview failed');
$result = $db->importPaths(7, $rows);
checkLegacy($result === ['created' => 2, 'skipped' => 0], 'Valid CSV import counts are incorrect');
$result = $db->importPaths(7, $rows);
checkLegacy($result === ['created' => 0, 'skipped' => 2], 'Repeated import was not idempotent');

$duplicates = $db->parseCsv("old_path,target_path\ndoppelt,produkte\ndoppelt,produkte\n");
$preview = $db->previewImport(7, $duplicates);
checkLegacy($preview[0]['status'] === 'new' && $preview[1]['status'] === 'duplicate', 'Duplicate CSV rows were not detected');
$conflict = $db->parseCsv("old_path,target_path\nkonflikt,produkte\nkonflikt,informationen/aktuell\n");
$preview = $db->previewImport(7, $conflict);
checkLegacy($preview[1]['status'] === 'error' && $preview[1]['error'] === 'MESSAGES_REDIRECT_CONFLICT', 'Conflicting CSV rows were accepted');
expectLegacy('MESSAGES_REDIRECT_CSV_HEADER_INVALID', fn() => $db->parseCsv("source,target\na,b\n"));
expectLegacy('MESSAGES_REDIRECT_CSV_INVALID', fn() => $db->parseCsv("old_path,target_path\n"));
expectLegacy('MESSAGES_REDIRECT_CSV_INVALID', fn() => $db->parseCsv("old_path,target_path\na,\xFF\n"));
expectLegacy('MESSAGES_REDIRECT_CSV_INVALID', fn() => $db->parseCsv(str_repeat('x', Admin_Model_DbTable_Slugredirect::MAX_CSV_SIZE + 1)));
$tooManyRows = "old_path,target_path\n";
for ($i = 0; $i <= Admin_Model_DbTable_Slugredirect::MAX_CSV_ROWS; $i++) $tooManyRows .= "old-$i,produkte\n";
expectLegacy('MESSAGES_REDIRECT_CSV_TOO_MANY_ROWS', fn() => $db->parseCsv($tooManyRows));
$badRows = $db->parseCsv("old_path,target_path\ngut,produkte\nbad,missing\n");
expectLegacy('MESSAGES_REDIRECT_IMPORT_INVALID', fn() => $db->importPaths(7, $badRows));
checkLegacy(!array_filter($db->redirects, static fn(array $row): bool => ($row['path'] ?? '') === 'gut'), 'Invalid CSV produced a partial import');

$rollbackRows = $db->parseCsv("old_path,target_path\nrollback-one,produkte\nrollback-two,produkte/geraet\n");
$before = $db->redirects;
$db->failAfterPath = 'rollback-two';
try {
	$db->importPaths(7, $rollbackRows);
	checkLegacy(false, 'Simulated import storage failure was not raised');
} catch (RuntimeException $e) {
	checkLegacy($e->getMessage() === 'Simulated storage failure', 'Unexpected simulated storage error');
}
checkLegacy($db->redirects === $before, 'Import transaction did not roll back all rows');

checkLegacy(DEEC_Site_Router::getRelativeRequestPath('/old-page', '') === 'old-page', 'Root installation path failed');
checkLegacy(DEEC_Site_Router::getRelativeRequestPath('/site/old-page?x=1', '/site') === 'old-page', 'Subdirectory installation path failed');
checkLegacy(DEEC_Site_Router::getRelativeRequestPath('/other/old-page', '/site') === null, 'Foreign subdirectory path was accepted');
checkLegacy((new DEEC_Site_Context(['url' => 'https://example.test/site/']))->getBasePath() === '/site', 'Canonical subdirectory was not exposed to redirect routing');

$controllerSource = file_get_contents($root . '/application/modules/admin/controllers/ShopController.php');
$viewSource = file_get_contents($root . '/application/modules/admin/views/scripts/shop/_redirects.phtml');
$publicControllerSource = file_get_contents($root . '/application/modules/shops/controllers/RedirectController.php');
checkLegacy(str_contains($controllerSource, 'isValidRedirectCsrf') && str_contains($controllerSource, 'is_uploaded_file'), 'CSRF or secure upload validation is missing');
checkLegacy(str_contains($viewSource, '$this->escape') && !str_contains($viewSource, 'name="controller"') && !str_contains($viewSource, 'name="entityid"'), 'Admin output or target IDs are client-manipulable');
checkLegacy(substr_count($publicControllerSource, 'getBasePath()') >= 2, 'Public redirect does not preserve a configured installation base path');

session_save_path(sys_get_temp_dir());
session_id('dewawi-legacy-redirect-test');
Zend_Session::start();
$csrfSession = new Zend_Session_Namespace('LegacyRedirectTest');
$csrfSession->csrf = 'expected-token';
$controller = (new ReflectionClass(Admin_ShopController::class))->newInstanceWithoutConstructor();
$csrfMethod = new ReflectionMethod(Admin_ShopController::class, 'isValidRedirectCsrf');
$csrfMethod->setAccessible(true);
checkLegacy($csrfMethod->invoke($controller, $csrfSession, '') === false, 'Missing CSRF token was accepted');
checkLegacy($csrfMethod->invoke($controller, $csrfSession, 'wrong-token') === false, 'Invalid CSRF token was accepted');
checkLegacy($csrfMethod->invoke($controller, $csrfSession, 'expected-token') === true, 'Valid CSRF token was rejected');

echo 'PASS: ' . $checks . " legacy redirect management, import, isolation and rollback cases\n";
