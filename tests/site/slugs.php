<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/site/slugs.php (no database required).

error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path;
});

require_once $root . '/library/DEEC/Filter.php';
require_once $root . '/library/DEEC/Site/Router.php';
require_once $root . '/application/modules/admin/models/DbTable/Slug.php';

$checks = 0;

function checkSlug(bool $condition, string $message): void
{
	global $checks;
	$checks++;
	if (!$condition) throw new RuntimeException($message);
}

function expectSlugError(string $message, callable $callback): void
{
	try {
		$callback();
	} catch (DomainException $exception) {
		checkSlug($exception->getMessage() === $message, 'Unexpected slug error: ' . $exception->getMessage());
		return;
	}

	checkSlug(false, 'Expected slug error was not raised: ' . $message);
}

function slugRow(int $id, int $shopId, string $controller, int $entityId, int $parentId, string $slug): array
{
	return [
		'id' => $id,
		'module' => 'shops',
		'controller' => $controller,
		'shopid' => $shopId,
		'entityid' => $entityId,
		'parentid' => $parentId,
		'slug' => $slug,
		'deleted' => 0,
	];
}

class SlugTestTable extends Admin_Model_DbTable_Slug
{
	public array $rows;

	public function __construct(array $rows = [])
	{
		$this->rows = $rows;
	}

	public function normalize($slug, int $entityId): string
	{
		return $this->normalizeSlugValue($slug, $entityId);
	}

	public function validate(string $controller, int $shopId, int $parentId, int $entityId, string $slug, int $slugId = 0): void
	{
		$this->assertSlugAvailable('shops', $controller, $shopId, $parentId, $entityId, $slug, $slugId);
	}

	public function validateRemoval(array $slug): void
	{
		$this->assertSlugHasNoChildren($slug);
	}

	protected function getActiveSlugs(int $shopId): array
	{
		return array_values(array_filter($this->rows, static function(array $row) use ($shopId): bool {
			return (int)$row['shopid'] === $shopId
				&& ($row['module'] ?? '') === 'shops'
				&& empty($row['deleted']);
		}));
	}
}

class SlugTestAdapter
{
	public int $lastInsertId = 0;

	public function quoteInto(string $text, $value): string
	{
		$value = is_numeric($value) ? (string)$value : "'" . str_replace("'", "''", (string)$value) . "'";

		return preg_replace('/\?/', $value, $text, 1);
	}

	public function lastInsertId(): int
	{
		return $this->lastInsertId;
	}
}

class SlugStorageTestTable extends SlugTestTable
{
	private SlugTestAdapter $adapter;

	public function __construct(array $rows = [])
	{
		parent::__construct($rows);
		$this->adapter = new SlugTestAdapter();
		$this->_date = '2026-11-01 12:00:00';
		$this->_user = ['id' => 7];
		$this->_client = ['id' => 3];
	}

	public function getAdapter()
	{
		return $this->adapter;
	}

	public function getSlug($module, $controller, $shopid, $entityid)
	{
		foreach ($this->rows as $row) {
			if (($row['module'] ?? '') === $module && ($row['controller'] ?? '') === $controller
				&& (int)$row['shopid'] === (int)$shopid && (int)$row['entityid'] === (int)$entityid && empty($row['deleted'])) {
				return $row;
			}
		}

		return [];
	}

	public function getEntitySlug($module, $controller, $entityid)
	{
		foreach ($this->rows as $row) {
			if (($row['module'] ?? '') === $module && ($row['controller'] ?? '') === $controller
				&& (int)$row['entityid'] === (int)$entityid && empty($row['deleted'])) {
				return $row;
			}
		}

		return [];
	}

	public function insert(array $data)
	{
		$id = count($this->rows) ? max(array_column($this->rows, 'id')) + 1 : 1;
		$data['id'] = $id;
		$data['deleted'] = 0;
		$this->rows[] = $data;
		$this->adapter->lastInsertId = $id;

		return $id;
	}

	public function update(array $data, $where)
	{
		$updated = 0;

		foreach ($this->rows as &$row) {
			if (!$this->matchesWhere($row, $where)) continue;
			$row = array_replace($row, $data);
			$updated++;
		}
		unset($row);

		return $updated;
	}

	protected function assertShopAccess(int $shopId): void
	{
	}

	private function matchesWhere(array $row, $where): bool
	{
		$where = is_array($where) ? $where : [$where];

		foreach ($where as $condition) {
			if (!preg_match("/^([a-z]+) = (?:'((?:''|[^'])*)'|([0-9]+))$/", (string)$condition, $matches)) return false;
			$value = $matches[2] !== '' ? str_replace("''", "'", $matches[2]) : $matches[3];
			if ((string)($row[$matches[1]] ?? '') !== (string)$value) return false;
		}

		return true;
	}
}

$normalization = [
	'Wärmepumpen' => 'waermepumpen',
	'R290 Wärmepumpen' => 'r290-waermepumpen',
	'Kaltwassersätze' => 'kaltwassersaetze',
	'Freikühlung' => 'freikuehlung',
	'Große Geräte' => 'grosse-geraete',
	'Ä Ö Ü ẞ' => 'ae-oe-ue-ss',
	'Crème brûlée' => 'creme-brulee',
	'Español / Français' => 'espanol-francais',
	'  Multiple___separators -- here  ' => 'multiple-separators-here',
	'R290' => 'r290',
	'--- !!! ---' => '',
	'' => '',
];

foreach ($normalization as $input => $expected) {
	checkSlug(DEEC_Filter::slug($input) === $expected, 'Slug normalization failed for: ' . $input);
}

$table = new SlugTestTable();
checkSlug($table->normalize('', 42) === '42', 'Empty stored slug does not use the entity fallback');
checkSlug($table->normalize('Kühlung', 42) === 'kuehlung', 'Stored slug does not use the central filter');

$storage = new SlugStorageTestTable([slugRow(1, 1, 'page', 40, 0, 'Legacy_Übersicht')]);
$storage->saveSlug('shops', 'page', 1, 0, 40);
checkSlug($storage->getEntitySlug('shops', 'page', 40)['slug'] === 'Legacy_Übersicht', 'Saving unrelated fields changed an existing legacy slug');
$storage->addSlug('shops', 'page', 1, 0, 41, 'Große Geräte');
checkSlug($storage->getEntitySlug('shops', 'page', 41)['slug'] === 'grosse-geraete', 'New stored slug was not normalized centrally');
$storage->updateSlug('shops', 'page', 1, 0, 41, 'Kühlung & Service');
checkSlug($storage->getEntitySlug('shops', 'page', 41)['slug'] === 'kuehlung-service', 'Updated stored slug was not normalized centrally');
$rowCount = count($storage->rows);
expectSlugError('MESSAGES_SLUG_CONFLICT', static fn() => $storage->addSlug('shops', 'category', 1, 0, 43, 'Kühlung & Service'));
checkSlug(count($storage->rows) === $rowCount, 'Colliding slug insert left a partial row');
$storage->addSlug('shops', 'page', 1, 0, 42, '!!!');
checkSlug($storage->getEntitySlug('shops', 'page', 42)['slug'] === '42', 'Stored special-character slug did not use the entity fallback');
expectSlugError('MESSAGES_SLUG_CONFLICT', static fn() => $storage->updateSlug('shops', 'page', 1, 0, 42, 'Kühlung & Service'));
checkSlug($storage->getEntitySlug('shops', 'page', 42)['slug'] === '42', 'Colliding slug update changed the stored value');

foreach (['search', 'search/results', 'sitemap.xml', 'contact/send', 'inquiry/success', 'cart', 'checkout/send', 'product/12', 'products-de.xml'] as $path) {
	checkSlug(DEEC_Site_Router::isReservedPath($path), 'Reserved route is not protected: ' . $path);
}
checkSlug(!DEEC_Site_Router::isReservedPath('products') && !DEEC_Site_Router::isReservedPath('contact-us'), 'Regular public path is reserved');

$rows = [
	slugRow(1, 1, 'category', 10, 0, 'waermepumpen'),
	slugRow(2, 1, 'item', 20, 10, 'r290'),
	slugRow(3, 1, 'category', 11, 0, 'industrie'),
	slugRow(4, 1, 'item', 21, 11, 'r290'),
	slugRow(5, 1, 'page', 30, 0, 'ueber-uns'),
	slugRow(6, 2, 'page', 31, 0, 'ueber-uns'),
];
$table = new SlugTestTable($rows);

$table->validate('item', 1, 11, 22, 'steuerung');
$table->validate('page', 2, 0, 32, 'ueber-uns-neu');
expectSlugError('MESSAGES_SLUG_CONFLICT', static fn() => $table->validate('item', 1, 10, 22, 'r290'));
expectSlugError('MESSAGES_SLUG_CONFLICT', static fn() => $table->validate('category', 1, 0, 12, 'ueber-uns'));
expectSlugError('MESSAGES_SLUG_PARENT_INVALID', static fn() => $table->validate('item', 1, 999, 22, 'r290-neu'));
expectSlugError('MESSAGES_SLUG_RESERVED', static fn() => $table->validate('page', 1, 0, 31, 'search'));

$otherShop = new SlugTestTable($rows);
$otherShop->validate('page', 3, 0, 30, 'ueber-uns');
checkSlug(true, 'Same slug in another shop was rejected');

$nestedRows = [
	slugRow(10, 1, 'category', 100, 0, 'produkte'),
	slugRow(11, 1, 'category', 101, 100, 'pumpen'),
	slugRow(12, 1, 'item', 102, 101, 'r290'),
	slugRow(13, 2, 'category', 100, 0, 'produkte'),
];
$nested = new SlugTestTable($nestedRows);
$nested->validate('item', 1, 101, 102, 'r290', 12);
expectSlugError('MESSAGES_SLUG_PARENT_INVALID', static fn() => $nested->validate('category', 1, 101, 100, 'produkte', 10));
expectSlugError('MESSAGES_SLUG_HAS_CHILDREN', static fn() => $nested->validateRemoval($nestedRows[0]));
$nested->validateRemoval($nestedRows[2]);
checkSlug(true, 'Leaf slug could not be removed');

$reservedParent = new SlugTestTable([slugRow(20, 1, 'category', 200, 0, 'contact')]);
expectSlugError('MESSAGES_SLUG_RESERVED', static fn() => $reservedParent->validate('item', 1, 200, 201, 'service'));

$itemController = file_get_contents($root . '/application/modules/items/controllers/ItemController.php');
checkSlug(!str_contains($itemController, 'function slugify') && !str_contains($itemController, '->slugify('), 'Item slug generation is not centralized');
checkSlug(str_contains($itemController, 'saveShopSlug((int)$item[\'id\'], array_replace($item, $updateData))'), 'Item import can overwrite an existing public slug');
checkSlug(str_contains($itemController, '$adapter->beginTransaction();') && str_contains($itemController, 'catch (DomainException $exception)')
	&& str_contains($itemController, '$adapter->rollBack();'), 'Imported item and slug are not stored atomically');

foreach (['PageController.php', 'CategoryController.php'] as $controller) {
	$source = file_get_contents($root . '/application/modules/admin/controllers/' . $controller);
	checkSlug(!str_contains($source, "['slug', 'title', 'type'"), $controller . ' still changes slugs after title-only edits');
}

$slugSource = file_get_contents($root . '/application/modules/admin/models/DbTable/Slug.php');
foreach (['addSlug', 'updateSlug', 'saveSlug'] as $method) {
	checkSlug(str_contains($slugSource, 'function ' . $method), 'Missing slug storage path: ' . $method);
}
checkSlug(str_contains($slugSource, 'normalizeSlugValue') && str_contains($slugSource, 'assertSlugAvailable'), 'Slug storage does not normalize and validate centrally');
checkSlug(str_contains($slugSource, 'assertShopAccess') && str_contains($slugSource, "where('clientid = ?'"), 'Slug storage lacks shop or tenant separation');

$actionSource = file_get_contents($root . '/library/DEEC/Controller/Action.php');
checkSlug(substr_count($actionSource, '$this->splitEditValues($values)') === 2, 'Normal and AJAX saves do not both separate the slug field');
checkSlug(substr_count($actionSource, 'str_starts_with($e->getMessage(), \'MESSAGES_SLUG_\')') === 2
	&& str_contains($actionSource, '$this->_flashMessenger->addMessage($e->getMessage())'), 'Slug save errors are not safely reported in both form save modes');

$tagSource = file_get_contents($root . '/application/modules/admin/controllers/TagController.php');
checkSlug(substr_count($tagSource, 'beginTransaction()') >= 3 && substr_count($tagSource, 'rollBack()') >= 3, 'Independent tag slug writes are not transactional');
checkSlug(substr_count($tagSource, "'ok' => true") >= 4, 'Independent tag saves do not return the autosave success contract');

$globalJs = file_get_contents($root . '/js/global.js');
checkSlug(str_contains($globalJs, 'response && response.message') && str_contains($globalJs, 'Dewawi.message(response.message)'), 'Autosave does not translate a slug conflict message');

echo 'PASS: ' . $checks . " slug normalization, hierarchy, collision, reserved-route and compatibility cases\n";
