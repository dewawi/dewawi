<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/site/search.php (no database required).

error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path;
});

require_once $root . '/library/DEEC/Site/Context.php';
require_once $root . '/library/DEEC/Site/Block.php';
require_once $root . '/library/DEEC/Site/Router.php';
require_once $root . '/application/modules/shops/models/Search.php';
require_once $root . '/application/modules/shops/models/DbTable/Item.php';
require_once $root . '/application/modules/shops/views/helpers/Breadcrumbs.php';

$checks = 0;

function checkSearch(bool $condition, string $message): void
{
	global $checks;
	$checks++;
	if (!$condition) throw new RuntimeException($message);
}

class SearchFixture extends Shops_Model_Search
{
	public array $pages = [];
	public array $categories = [];
	public array $tags = [];
	public array $items = [];

	public function __construct(DEEC_Site_Context $siteContext)
	{
		$this->siteContext = $siteContext;
	}

	protected function preloadPaths(): void
	{
	}

	protected function getPageCandidates(): array
	{
		return $this->pages;
	}

	protected function getCategoryCandidates(): array
	{
		return $this->siteContext->hasFeature('catalog') ? $this->categories : [];
	}

	protected function getTagCandidates(): array
	{
		return $this->siteContext->hasFeature('catalog') ? $this->tags : [];
	}

	protected function countItems(array $terms): int
	{
		return count($this->matchingItems($terms));
	}

	protected function getItemCandidates(array $terms, int $limit): array
	{
		return array_slice($this->matchingItems($terms), 0, $limit);
	}

	public function visibleBlockText(array $blocks): array
	{
		return $this->getVisibleBlockText($blocks);
	}

	public function excerpt(array $candidate, array $terms): string
	{
		return $this->createExcerpt($candidate, $terms);
	}

	public function descriptiveMediaTitle(array $media): bool
	{
		return $this->isDescriptiveMediaTitle($media);
	}

	private function matchingItems(array $terms): array
	{
		return array_values(array_filter($this->items, static function(array $item) use ($terms): bool {
			$values = array_merge($item['identifiers'] ?? [], $item['titles'] ?? [], $item['headings'] ?? [], $item['content'] ?? [], $item['descriptions'] ?? []);
			$haystack = Shops_Model_Search::normalizeForMatch(implode(' ', $values));
			return (bool)array_filter($terms, static fn(string $term): bool => str_contains($haystack, $term));
		}));
	}
}

class SearchBreadcrumbView extends Zend_View
{
	public function url($urlOptions = [], $name = null, $reset = false, $encode = true): string
	{
		return '/';
	}

	public function translate($key, array $args = []): string
	{
		return $key === 'SHOPS_SEARCH' ? 'Suche' : (string)$key;
	}
}

function candidate(string $type, int $id, string $title, string $url, string $content = ''): array
{
	return [
		'type' => $type,
		'id' => $id,
		'title' => $title,
		'url' => $url,
		'titles' => [$title],
		'headings' => [],
		'content' => [$content],
		'descriptions' => [],
	];
}

$context = new DEEC_Site_Context(['id' => 7, 'clientid' => 3, 'title' => 'Test'], [], ['cms', 'catalog']);
$search = new SearchFixture($context);
$search->pages = [
	candidate('page', 1, 'Pumpen', '/pumpen', 'Zentrale Übersicht'),
	candidate('page', 2, 'Doppelt', '/pumpen', 'Pumpen Ersatztext'),
	candidate('page', 3, 'Beratung', '/beratung', 'Pumpen für Industrieanlagen'),
];
$item = candidate('item', 10, 'Industrieventil', '/produkte/ventil', 'Eine lange Beschreibung erwähnt Pumpen nur beiläufig.');
$item['identifiers'] = ['P-100'];
$item['sku'] = 'P-100';
$search->items = [$item];

$result = $search->search('Pumpen');
checkSearch($result['total'] === 3, 'Target URL deduplication failed');
checkSearch($result['results'][0]['type'] === 'page' && $result['results'][0]['title'] === 'Pumpen', 'Exact page title does not outrank body matches');
checkSearch(count(array_filter($result['results'], static fn(array $row): bool => $row['url'] === '/pumpen')) === 1, 'A target URL is listed more than once');
checkSearch($result['results'][0]['excerpt'] !== '', 'Matching result excerpt is missing');

$search->pages = [candidate('page', 1, 'Industriepumpen', '/pumpen', 'Robuste Anlagen für Wasser')];
$search->items = [candidate('item', 2, 'Wasserfilter', '/filter', 'Industrie Zubehör')];
$result = $search->search('Industrie Wasser');
checkSearch($result['results'][0]['url'] === '/pumpen', 'All-term match is not preferred');
checkSearch($result['typeCounts']['page'] === 1 && $result['typeCounts']['item'] === 1, 'Content type counts are incorrect');
checkSearch($search->search('Industrie', 1, 'item')['results'][0]['type'] === 'item', 'Content type filter is not applied');

$search->pages = [candidate('page', 1, 'P-100 Informationen', '/info', 'Artikelnummer P-100')];
$skuItem = candidate('item', 2, 'Ersatzteil', '/ersatzteil', 'Produkt');
$skuItem['identifiers'] = ['P-100'];
$search->items = [$skuItem];
checkSearch($search->search('P-100')['results'][0]['type'] === 'item', 'Exact SKU does not outrank regular text matches');

$search->pages = [];
$search->categories = [candidate('category', 3, 'Pumpen', '/kategorie', 'Anwendungen')];
$search->tags = [candidate('tag', 4, 'Wasser', '/thema', 'Anwendungen')];
$search->items = [];
$result = $search->search('Anwendungen');
checkSearch($result['typeCounts']['category'] === 1 && $result['typeCounts']['tag'] === 1, 'Categories or tags are not searchable');

$search->pages = [];
$search->items = [];
for ($i = 1; $i <= 25; $i++) {
	$search->items[] = candidate('item', $i, sprintf('Teil %02d', $i), '/teil-' . $i, 'Paginationstest');
}
$result = $search->search('Paginationstest', 2);
checkSearch($result['total'] === 25 && $result['pages'] === 2, 'Pagination total is incorrect');
checkSearch(count($result['results']) === 5 && $result['page'] === 2, 'Second result page is incorrect');

$cmsOnly = new SearchFixture(new DEEC_Site_Context(['id' => 7, 'clientid' => 3], [], ['cms']));
$cmsOnly->pages = [candidate('page', 1, 'Hilfe', '/hilfe', 'Kataloglos')];
$cmsOnly->items = [candidate('item', 1, 'Privates Produkt', '/privat', 'Kataloglos')];
$result = $cmsOnly->search('Kataloglos');
checkSearch($result['total'] === 1 && !isset($result['typeCounts']['item']), 'Disabled catalog content is searchable');

$blocks = [
	['id' => 1, 'parentid' => 0, 'type' => 'cards', 'data' => json_encode(['title' => 'Lösungen', 'columns' => '3', 'buttonurl' => '/mehr', 'buttonlabel' => 'Mehr']), 'activated' => 1, 'deleted' => 0],
	['id' => 2, 'parentid' => 1, 'type' => 'card', 'data' => json_encode(['title' => 'Verschachtelte Karte', 'text' => '<p>Öl &amp; Gas</p>', 'image' => '/internal/image.jpg', 'imagealt' => 'Anlagenfoto']), 'activated' => 1, 'deleted' => 0],
	['id' => 3, 'parentid' => 0, 'type' => 'text', 'data' => json_encode(['title' => 'Geheim']), 'activated' => 0, 'deleted' => 0],
	['id' => 4, 'parentid' => 99, 'type' => 'card', 'data' => json_encode(['title' => 'Unsichtbares Kind']), 'activated' => 1, 'deleted' => 0],
];
$blockText = $search->visibleBlockText($blocks);
$visible = Shops_Model_Search::cleanText(implode(' ', array_merge($blockText['headings'], $blockText['content'])));
checkSearch(str_contains($visible, 'Verschachtelte Karte') && str_contains($visible, 'Öl & Gas'), 'Nested visible block text is missing');
checkSearch(str_contains($visible, 'Mehr') && str_contains($visible, 'Anlagenfoto'), 'Visible button or image alternative text is missing');
checkSearch(!str_contains($visible, 'Geheim') && !str_contains($visible, 'Unsichtbares Kind'), 'Disabled parent/block content is included');
checkSearch(!str_contains($visible, '/internal/image.jpg') && !str_contains($visible, '/mehr'), 'Technical block values are included');

checkSearch(Shops_Model_Search::normalize('<b>ÄPFEL</b> &amp; Öl') === 'äpfel & öl', 'Unicode/HTML normalization failed');
checkSearch(Shops_Model_Search::cleanText('<p>Kälte</p><p>Kühlung &amp; Wärme</p>') === 'Kälte Kühlung & Wärme', 'HTML elements are joined without word boundaries');
checkSearch(Shops_Model_Search::normalizeForMatch('Wärmepumpe') === Shops_Model_Search::normalizeForMatch('Waermepumpe'), 'Wärmepumpe spelling variants differ');
checkSearch(Shops_Model_Search::normalizeForMatch('Kälte') === Shops_Model_Search::normalizeForMatch('Kaelte'), 'Kälte spelling variants differ');
checkSearch(Shops_Model_Search::normalizeForMatch('Kühlung') === Shops_Model_Search::normalizeForMatch('Kuehlung'), 'Kühlung spelling variants differ');
checkSearch(Shops_Model_Search::normalizeForMatch('Groß-Kühlung') === Shops_Model_Search::normalizeForMatch('GROSSKUEHLUNG'), 'Case, sharp-s or hyphen normalization differs');
checkSearch(Shops_Model_Search::normalizeForMatch('Kaltwassersatz') === Shops_Model_Search::normalizeForMatch('Kaltwassersätze'), 'Kaltwassersatz inflection differs');

$search->pages = [candidate('page', 1, 'Prozesskühler', '/prozess', 'Ein Kaltwassersatz wird beiläufig erwähnt.')];
$search->categories = [candidate('category', 2, 'Kaltwassersätze', '/kaltwassersaetze', 'Effiziente Lösungen')];
$search->tags = [];
$search->items = [];
$result = $search->search('kaltwassersatz');
checkSearch($result['results'][0]['url'] === '/kaltwassersaetze', 'Inflected category title does not outrank a body match');
checkSearch($search->search('Kaltwassersätze')['results'][0]['url'] === '/kaltwassersaetze', 'Plural query ranking is inconsistent');

$search->pages = [candidate('page', 1, 'Waermepumpe', '/ascii', '')];
$search->categories = [candidate('category', 2, 'Wärmepumpe', '/umlaut', '')];
$result = $search->search('Waermepumpe');
checkSearch($result['results'][0]['url'] === '/ascii', 'Exact title spelling does not outrank a normalized variant');

$repeated = candidate('page', 1, 'Ratgeber', '/ratgeber', 'Kühlung Kühlung Kühlung');
$repeated['headings'] = ['Kühlung'];
$repeated['descriptions'] = ['Kühlung'];
$search->pages = [$repeated];
$search->categories = [candidate('category', 2, 'Kuehlung', '/kuehlung', '')];
$result = $search->search('Kühlung');
checkSearch($result['results'][0]['url'] === '/kuehlung', 'Repeated low-priority fields outweigh a matching title');

$search->pages = [];
$search->categories = [candidate('category', 1, 'Prozesskühler', '/prozess', 'kaltwassersatz-arn-3.jpg')];
checkSearch($search->search('kaltwassersatz')['total'] === 0, 'Technical filename is searchable as content');
checkSearch(!$search->descriptiveMediaTitle(['title' => 'kaltwassersatz-arn-3.jpg', 'url' => '/img/kaltwassersatz-arn-3.jpg']), 'Technical media filename is treated as a descriptive title');
checkSearch($search->descriptiveMediaTitle(['title' => 'Kaltwassersatz für Prozesskühlung', 'url' => '/img/arn-3.jpg']), 'Descriptive media title is excluded');

$excerptCandidate = candidate('page', 3, 'Details', '/details', '<p>kaltwassersatz-arn-3.jpg</p><p>Dieser technische Vorspann ist absichtlich lang und enthält keine relevante Aussage.</p><p>Der Kaltwassersatz kühlt zuverlässig den laufenden Prozess.</p>');
$excerpt = $search->excerpt($excerptCandidate, ['kaltwassersatz']);
checkSearch($excerpt === 'Der Kaltwassersatz kühlt zuverlässig den laufenden Prozess.', 'Excerpt does not select a readable matching sentence');
checkSearch(!str_contains($excerpt, '.jpg') && !str_contains($excerpt, '  '), 'Excerpt contains a filename or duplicate whitespace');

$breadcrumbView = new SearchBreadcrumbView();
$breadcrumbView->controller = 'search';
$breadcrumb = (new Zend_View_Helper_Breadcrumbs())->setView($breadcrumbView)->Breadcrumbs();
checkSearch(str_contains($breadcrumb, '>Suche</li>') && !str_contains($breadcrumb, '>search</li>'), 'Search breadcrumb is not translated');

checkSearch($search->search("%' OR 1=1 --")['total'] === 0, 'Injection-like input returns unrelated results');
checkSearch($search->search(str_repeat('x', 101))['valid'] === false, 'Oversized query is accepted');

$router = new Zend_Controller_Router_Rewrite();
$method = new ReflectionMethod(DEEC_Site_Router::class, 'registerBaseRoutes');
$method->setAccessible(true);
$method->invoke(new DEEC_Site_Router(), $router, new DEEC_Site_Context([], [], ['cms']));
$route = $router->getRoute('search')->match('search');
checkSearch(($route['controller'] ?? '') === 'search', 'Search route is not registered without catalog');
$collisionRouter = new Zend_Controller_Router_Rewrite();
$collisionRouter->addRoute('conflicting_slug', new Zend_Controller_Router_Route('search', ['module' => 'shops', 'controller' => 'page', 'action' => 'index', 'id' => 99]));
$method->invoke(new DEEC_Site_Router(), $collisionRouter, new DEEC_Site_Context([], [], ['cms']));
$request = new Zend_Controller_Request_Http();
$request->setPathInfo('/search');
$collisionRouter->route($request);
checkSearch($request->getControllerName() === 'search', 'A CMS slug can override the reserved search route');

$controllerSource = file_get_contents($root . '/application/modules/shops/controllers/SearchController.php');
checkSearch(str_contains($controllerSource, 'X-Robots-Tag') && str_contains($controllerSource, "setName('robots', 'noindex, follow')"), 'Search noindex protection is missing');
$aclSource = file_get_contents($root . '/application/plugins/Acl.php');
foreach (["getModuleName() === 'shops'", "getControllerName() === 'search'", "getActionName() === 'index'", "['GET', 'HEAD']", "isRegistered('SiteContext')", 'instanceof DEEC_Site_Context'] as $aclGuard) {
	checkSearch(str_contains($aclSource, $aclGuard), 'Missing public search ACL guard: ' . $aclGuard);
}
$sitemapSource = file_get_contents($root . '/application/modules/shops/controllers/SitemapController.php');
checkSearch(!str_contains($sitemapSource, "'controller' => 'search'"), 'Search URLs are present in the sitemap');
$layoutSource = file_get_contents($root . '/application/layouts/scripts/site.phtml');
checkSearch(str_contains($layoutSource, "'search', true") && str_contains($layoutSource, 'placeholder="Was suchen Sie?"'), 'Global search form is missing');
$searchViewSource = file_get_contents($root . '/application/modules/shops/views/scripts/search/index.phtml');
checkSearch(substr_count($searchViewSource, '$this->escape(') >= 8, 'Search output is not consistently escaped');
$searchSource = file_get_contents($root . '/application/modules/shops/models/Search.php');
foreach (['i.clientid = ?', 'i.shopid = ?', 'c.clientid = ?', 'c.shopid = ?', 'i.shopenabled = ?', 'c.activated = ?', 's.deleted = 0'] as $tenantFilter) {
	checkSearch(str_contains($searchSource, $tenantFilter), 'Missing item tenant/public filter: ' . $tenantFilter);
}
$pageblockSource = file_get_contents($root . '/application/models/DbTable/Pageblock.php');
checkSearch(str_contains($pageblockSource, "where('clientid = ?', \$this->getClientId())") && str_contains($pageblockSource, "where('activated = ?', 1)") && str_contains($pageblockSource, "where('deleted = ?', 0)"), 'Page block public/tenant filters are incomplete');
$itemDefaults = (new ReflectionClass(Shops_Model_DbTable_Item::class))->getDefaultProperties();
checkSearch(($itemDefaults['publicField'] ?? null) === 'shopenabled', 'Public item model does not enforce shop approval');

echo 'PASS: ' . $checks . " website search, visibility, ranking, pagination and SEO cases\n";
