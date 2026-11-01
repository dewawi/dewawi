<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/site/technical-baseline.php (no database required).

error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path;
});

require_once $root . '/library/DEEC/Site/Context.php';
require_once $root . '/library/DEEC/Site/Resolver.php';
require_once $root . '/application/plugins/SecurityHeaders.php';

$checks = 0;

function checkBaseline(bool $condition, string $message): void
{
	global $checks;
	$checks++;
	if (!$condition) throw new RuntimeException($message);
}

function responseHeaders(Zend_Controller_Response_Http $response): array
{
	$headers = [];
	foreach ($response->getHeaders() as $header) $headers[strtolower($header['name'])] = $header['value'];
	return $headers;
}

function applySecurityHeaders(string $module, ?DEEC_Site_Context $context = null): array
{
	if (Zend_Registry::isRegistered('SiteContext')) Zend_Registry::getInstance()->offsetUnset('SiteContext');
	if ($context) Zend_Registry::set('SiteContext', $context);

	$request = new Zend_Controller_Request_Http();
	$request->setModuleName($module);
	$response = new Zend_Controller_Response_Http();
	$plugin = new Application_Plugin_SecurityHeaders();
	$plugin->setRequest($request);
	$plugin->setResponse($response);
	$plugin->dispatchLoopShutdown();

	return responseHeaders($response);
}

class BaselineSiteResolver extends DEEC_Site_Resolver
{
	public function siteIsIndexable(array $site, array $options): bool
	{
		return $this->isIndexable($site, $options);
	}
}

$indexable = new DEEC_Site_Context([], ['indexable' => true]);
$nonIndexable = new DEEC_Site_Context([], ['indexable' => false]);
checkBaseline($indexable->isIndexable(), 'An indexable site is treated as noindex');
checkBaseline(!$nonIndexable->isIndexable(), 'A non-indexable site is treated as indexable');
checkBaseline((new DEEC_Site_Context([]))->isIndexable(), 'Legacy site contexts lost their indexable default');

$resolver = new BaselineSiteResolver();
checkBaseline(!$resolver->siteIsIndexable(['indexable' => 0], ['environment' => 'production']), 'Disabled production site is indexable');
checkBaseline($resolver->siteIsIndexable(['indexable' => 1], ['environment' => 'production']), 'Enabled production site is not indexable');
checkBaseline(!$resolver->siteIsIndexable(['indexable' => 1], ['environment' => 'staging']), 'Staging site is indexable');
checkBaseline($resolver->siteIsIndexable([], ['environment' => 'production']), 'Legacy production site lost its compatible default');

$internalHeaders = applySecurityHeaders('admin');
checkBaseline(($internalHeaders['x-robots-tag'] ?? '') === 'noindex, nofollow', 'Internal responses are indexable');
checkBaseline(($internalHeaders['x-frame-options'] ?? '') === 'SAMEORIGIN', 'Internal frame protection is missing');
checkBaseline(($internalHeaders['content-security-policy'] ?? '') === "frame-ancestors 'self'", 'Internal CSP frame protection is missing');

$stagingHeaders = applySecurityHeaders('shops', $nonIndexable);
checkBaseline(($stagingHeaders['x-robots-tag'] ?? '') === 'noindex, nofollow', 'Non-indexable public sites lack the robots header');
checkBaseline(!isset($stagingHeaders['x-frame-options'], $stagingHeaders['content-security-policy']), 'Public site embedding policy changed without approval');

$productionHeaders = applySecurityHeaders('shops', $indexable);
checkBaseline(!isset($productionHeaders['x-robots-tag']), 'Indexable public sites receive a global noindex header');
foreach ([$internalHeaders, $stagingHeaders, $productionHeaders] as $headers) {
	checkBaseline(($headers['x-content-type-options'] ?? '') === 'nosniff', 'X-Content-Type-Options is missing');
	checkBaseline(($headers['referrer-policy'] ?? '') === 'strict-origin-when-cross-origin', 'Referrer-Policy is missing');
	checkBaseline(($headers['permissions-policy'] ?? '') === 'camera=(), microphone=(), geolocation=()', 'Permissions-Policy is missing');
}

$config = file_get_contents($root . '/configs/application.ini');
checkBaseline(str_contains($config, 'resources.view.doctype = "HTML5"'), 'HTML5 is not the configured view doctype');
checkBaseline(!str_contains($config, 'noindexHosts'), 'Installation-specific indexing configuration is tracked');
checkBaseline(str_contains($config, '[staging : production]'), 'The staging environment is not explicitly supported');

$shopForm = file_get_contents($root . '/application/modules/admin/forms/Shop.php');
$structure = file_get_contents($root . '/install/config/structure.sql');
$migration = file_get_contents($root . '/install/config/update-1.1.7-1.1.8.sql');
checkBaseline(str_contains($shopForm, "'name' => 'indexable'") && str_contains($shopForm, "'default' => 0"), 'Shop indexing control is missing or defaults to enabled');
checkBaseline(str_contains($structure, '`indexable` tinyint(1) NOT NULL DEFAULT 0'), 'Fresh installations do not default new shops to noindex');
checkBaseline(str_contains($migration, 'ADD `indexable` tinyint(1) NOT NULL DEFAULT 1') && str_contains($migration, 'SET DEFAULT 0'), 'Existing shop publication is not preserved by the migration');

$bootstrap = file_get_contents($root . '/application/Bootstrap.php');
checkBaseline(str_contains($bootstrap, 'new Application_Plugin_SecurityHeaders()'), 'Security headers plugin is not registered');
$analyticsPlugin = file_get_contents($root . '/application/plugins/Analytics.php');
checkBaseline(str_contains($analyticsPlugin, "APPLICATION_ENV !== 'production'"), 'Backend analytics is enabled outside production');

foreach (['layout.phtml', 'plain.phtml', 'site.phtml'] as $layout) {
	$source = file_get_contents($root . '/application/layouts/scripts/' . $layout);
	checkBaseline(!str_contains($source, "appendHttpEquiv('Content-Type'"), 'Legacy content meta remains in ' . $layout);
	checkBaseline(str_contains($source, '<meta charset="UTF-8">'), 'Charset meta is missing in ' . $layout);
}

$siteLayout = file_get_contents($root . '/application/layouts/scripts/site.phtml');
checkBaseline(str_contains($siteLayout, "setName('robots', 'noindex, nofollow')"), 'Public noindex meta handling is missing');
checkBaseline(str_contains($siteLayout, 'if($this->siteContext->isIndexable())'), 'Analytics is not gated by site indexability');

$siteController = file_get_contents($root . '/application/modules/shops/controllers/Action.php');
checkBaseline(str_contains($siteController, 'if (!$this->_siteContext->isIndexable())'), 'Non-indexable sites can emit canonical links');

$sitemapController = file_get_contents($root . '/application/modules/shops/controllers/SitemapController.php');
checkBaseline(str_contains($sitemapController, 'if (!$this->_siteContext->isIndexable())')
	&& str_contains($sitemapController, 'setHttpResponseCode(404)'), 'Non-indexable sites can expose a sitemap');

echo 'PASS: ' . $checks . " HTML5, indexing and response security baseline cases\n";
