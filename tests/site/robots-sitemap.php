<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/site/robots-sitemap.php (no database required).

error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path;
});

require_once $root . '/library/DEEC/Site/Context.php';
require_once $root . '/library/DEEC/Site/Router.php';
require_once $root . '/application/modules/shops/controllers/Action.php';
require_once $root . '/application/modules/shops/controllers/RobotsController.php';
require_once $root . '/application/modules/shops/controllers/SitemapController.php';
require_once $root . '/application/modules/shops/models/DbTable/Slug.php';

$checks = 0;

function checkSeo(bool $condition, string $message): void
{
	global $checks;
	$checks++;
	if (!$condition) throw new RuntimeException($message);
}

class RobotsControllerFixture extends Shops_RobotsController
{
	public function body(DEEC_Site_Context $context): string
	{
		$this->_siteContext = $context;
		return $this->buildBody();
	}
}

class SitemapControllerFixture extends Shops_SitemapController
{
	public function xml(DEEC_Site_Context $context, Shops_Model_DbTable_Slug $slugDb): string
	{
		$this->_siteContext = $context;
		return $this->buildXml((string)$context->getCanonicalBaseUrl(), $slugDb);
	}
}

class SitemapSlugFixture extends Shops_Model_DbTable_Slug
{
	public array $rows = [];
	public array $paths = [];

	public function __construct()
	{
	}

	public function getSlugs(): Zend_Db_Table_Rowset_Abstract
	{
		return new Zend_Db_Table_Rowset(['data' => $this->rows, 'readOnly' => true]);
	}

	public function getPublicPath(string $controller, int $entityId): ?string
	{
		return $this->paths[$controller . ':' . $entityId] ?? null;
	}
}

$canonical = new DEEC_Site_Context(['url' => ' https://www.example.test/site/ '], ['indexable' => true]);
checkSeo($canonical->getCanonicalBaseUrl() === 'https://www.example.test/site', 'Canonical site URL was not normalized');
checkSeo((new DEEC_Site_Context(['url' => 'HTTPS://WWW.EXAMPLE.TEST:443/site/']))->getCanonicalBaseUrl() === 'https://www.example.test/site', 'Canonical scheme, host or default port was not normalized');
checkSeo((new DEEC_Site_Context(['url' => 'https://www.example.test./site']))->getCanonicalBaseUrl() === 'https://www.example.test/site', 'Canonical fully qualified host was not normalized');
checkSeo((new DEEC_Site_Context(['url' => 'https://www.example.test:8443/site']))->getCanonicalOrigin() === 'https://www.example.test:8443', 'Canonical non-default origin port was lost');
foreach (['', 'javascript:alert(1)', 'https://user:pass@example.test', 'https://example.test/?source=test', 'https://example.test/#section', 'https://example.test/a/../b', 'https://example.test//path', 'https://bad_host.example.test'] as $url) {
	checkSeo((new DEEC_Site_Context(['url' => $url]))->getCanonicalBaseUrl() === null, 'Unsafe canonical site URL was accepted: ' . $url);
}

$robots = (new ReflectionClass(RobotsControllerFixture::class))->newInstanceWithoutConstructor();
$indexableBody = $robots->body($canonical);
checkSeo($indexableBody === "User-agent: *\nAllow: /\nSitemap: https://www.example.test/site/sitemap.xml\n", 'Indexable robots.txt is incomplete');
$nonIndexable = new DEEC_Site_Context(['url' => 'https://www.example.test'], ['indexable' => false]);
checkSeo($robots->body($nonIndexable) === "User-agent: *\nAllow: /\n", 'Non-indexable robots.txt prevents crawlers from reading noindex');
checkSeo(!str_contains($robots->body($nonIndexable), 'Sitemap:'), 'Non-indexable robots.txt advertises a sitemap');
$invalidCanonical = new DEEC_Site_Context(['url' => ''], ['indexable' => true]);
checkSeo($robots->body($invalidCanonical) === "User-agent: *\nAllow: /\n", 'Invalid canonical URL leaked into robots.txt');

$slugDb = new SitemapSlugFixture();
$slugDb->rows = [
	['module' => 'shops', 'controller' => 'page', 'entityid' => 1, 'slug' => 'about'],
	['module' => 'shops', 'controller' => 'category', 'entityid' => 2, 'slug' => 'products'],
	['module' => 'shops', 'controller' => 'item', 'entityid' => 3, 'slug' => 'pump'],
	['module' => 'shops', 'controller' => 'tag', 'entityid' => 4, 'slug' => 'efficient'],
	['module' => 'shops', 'controller' => 'inquiry', 'entityid' => 5, 'slug' => 'project'],
	['module' => 'shops', 'controller' => 'page', 'entityid' => 6, 'slug' => 'duplicate'],
	['module' => 'shops', 'controller' => 'page', 'entityid' => 7, 'slug' => 'disabled'],
	['module' => 'admin', 'controller' => 'page', 'entityid' => 8, 'slug' => 'internal'],
];
$slugDb->paths = [
	'page:1' => 'about',
	'category:2' => 'products',
	'item:3' => 'products/pump',
	'tag:4' => 'efficient',
	'inquiry:5' => 'project',
	'page:6' => 'about',
	'page:8' => 'internal',
];

$sitemap = (new ReflectionClass(SitemapControllerFixture::class))->newInstanceWithoutConstructor();
$allFeatures = new DEEC_Site_Context(['url' => 'https://www.example.test'], ['indexable' => true], ['cms', 'catalog', 'inquiry']);
$xml = $sitemap->xml($allFeatures, $slugDb);
foreach (['https://www.example.test/', '/about', '/products', '/products/pump', '/efficient', '/project'] as $url) {
	checkSeo(str_contains($xml, $url), 'Canonical public URL is missing from sitemap: ' . $url);
}
checkSeo(substr_count($xml, '<loc>https://www.example.test/about</loc>') === 1, 'Sitemap contains a duplicate canonical URL');
checkSeo(!str_contains($xml, 'disabled') && !str_contains($xml, 'internal'), 'Sitemap contains a disabled or internal URL');
checkSeo(!str_contains($xml, 'slugredirect'), 'Sitemap contains historical redirect data');

$cmsOnly = new DEEC_Site_Context(['url' => 'https://www.example.test'], ['indexable' => true], ['cms']);
$cmsXml = $sitemap->xml($cmsOnly, $slugDb);
checkSeo(substr_count($cmsXml, '<url>') === 2 && str_contains($cmsXml, '/about'), 'Sitemap ignored disabled website features');
checkSeo(!str_contains($cmsXml, '/products') && !str_contains($cmsXml, '/project'), 'Sitemap exposed a disabled catalog or inquiry feature');

$router = new Zend_Controller_Router_Rewrite();
$routeMethod = new ReflectionMethod(DEEC_Site_Router::class, 'registerBaseRoutes');
$routeMethod->setAccessible(true);
$routeMethod->invoke(new DEEC_Site_Router(), $router, $allFeatures);
checkSeo(($router->getRoute('robots')->match('robots.txt')['controller'] ?? '') === 'robots', 'Dynamic robots.txt route is missing');
checkSeo(($router->getRoute('sitemap')->match('sitemap.xml')['controller'] ?? '') === 'sitemap', 'Sitemap route changed');
checkSeo(DEEC_Site_Router::isReservedPath('robots.txt'), 'robots.txt can be claimed by a public slug');

$robotsSource = file_get_contents($root . '/application/modules/shops/controllers/RobotsController.php');
$sitemapSource = file_get_contents($root . '/application/modules/shops/controllers/SitemapController.php');
foreach ([$robotsSource, $sitemapSource] as $source) {
	checkSeo(str_contains($source, 'isGet()') && str_contains($source, 'isHead()') && str_contains($source, 'setHttpResponseCode(404)'), 'SEO endpoint accepts writing requests');
}
checkSeo(str_contains($robotsSource, 'text/plain; charset=UTF-8'), 'robots.txt content type is incorrect');
checkSeo(str_contains($sitemapSource, 'application/xml; charset=UTF-8') && str_contains($sitemapSource, "'X-Robots-Tag', 'noindex, follow'"), 'Sitemap response headers are incomplete');
checkSeo(!str_contains($sitemapSource, 'getHttpHost()'), 'Sitemap trusts the request Host header for canonical URLs');

$structure = file_get_contents($root . '/install/config/structure.sql');
$migration = file_get_contents($root . '/install/config/update-1.1.7-1.1.8.sql');
preg_match('~CREATE TABLE IF NOT EXISTS `slugredirect` \(.*?\) ENGINE=InnoDB DEFAULT CHARSET=utf8;~s', $structure, $structureMatch);
preg_match('~CREATE TABLE IF NOT EXISTS `slugredirect` \(.*?\) ENGINE=InnoDB DEFAULT CHARSET=utf8;~s', $migration, $migrationMatch);
checkSeo(isset($structureMatch[0], $migrationMatch[0]) && $structureMatch[0] === $migrationMatch[0], 'Redirect schema differs between fresh installation and the current 1.1.8 migration');
checkSeo(!is_file($root . '/install/config/update-1.1.8-1.1.9.sql'), 'An unsupported migration version was created');

echo 'PASS: ' . $checks . " robots, sitemap, canonical URL, routing and isolation cases\n";
