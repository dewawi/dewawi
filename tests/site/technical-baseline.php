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

function applySecurityHeaders(string $module, ?DEEC_Site_Context $context = null, ?string $cacheControl = null): array
{
	if (Zend_Registry::isRegistered('SiteContext')) Zend_Registry::getInstance()->offsetUnset('SiteContext');
	if ($context) Zend_Registry::set('SiteContext', $context);

	$request = new Zend_Controller_Request_Http();
	$request->setModuleName($module);
	$response = new Zend_Controller_Response_Http();
	if ($cacheControl !== null) $response->setHeader('Cache-Control', $cacheControl, true);
	$plugin = new Application_Plugin_SecurityHeaders();
	$plugin->setRequest($request);
	$plugin->setResponse($response);
	$plugin->dispatchLoopShutdown();

	return responseHeaders($response);
}

class BaselineSiteResolver extends DEEC_Site_Resolver
{
	public array $sites = [];

	public function siteIsIndexable(array $site, array $options): bool
	{
		return $this->isIndexable($site, $options);
	}

	protected function getActiveSites()
	{
		return $this->sites;
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

$resolver->sites = [
	['id' => 11, 'clientid' => 7, 'url' => 'https://www.alpha.example.test/site', 'catalogenabled' => 1],
	['id' => 12, 'clientid' => 8, 'url' => 'https://beta.example.test'],
	['id' => 13, 'clientid' => 9, 'url' => 'https://port.example.test:8443/base'],
];
$alpha = $resolver->resolveByHost('www.alpha.example.test', ['environment' => 'production']);
checkBaseline($alpha instanceof DEEC_Site_Context && $alpha->getSiteId() === 11 && $alpha->getClientId() === 7, 'Canonical host resolved to the wrong site or tenant');
checkBaseline($alpha->getCanonicalBaseUrl() === 'https://www.alpha.example.test/site' && $alpha->hasFeature('catalog'), 'Subdirectory canonical URL or site features were lost');
checkBaseline($resolver->resolveByHost('www.alpha.example.test:443')->getSiteId() === 11, 'Canonical standard HTTPS port did not resolve');
checkBaseline($resolver->resolveByHost('www.alpha.example.test:80')->getSiteId() === 11, 'Canonical host on the HTTP redirect port did not resolve');
checkBaseline($resolver->resolveByHost('alpha.example.test') === null, 'Non-www host was silently treated as a configured www host');
checkBaseline($resolver->resolveByHost('www.alpha.example.test:8443') === null, 'Unconfigured public port resolved to a site');
checkBaseline($resolver->resolveByHost('port.example.test') === null, 'Explicit canonical port was ignored');
checkBaseline($resolver->resolveByHost('port.example.test:8443')->getSiteId() === 13, 'Explicit canonical port did not resolve');
foreach (['', 'www.alpha.example.test:0', 'www.alpha.example.test:65536', 'www.alpha.example.test:443:80', 'user@www.alpha.example.test', "www.alpha.example.test\r\nX-Test: yes", 'www.alpha.example.test/path'] as $host) {
	checkBaseline($resolver->resolveByHost($host) === null, 'Malformed Host authority resolved to a site: ' . json_encode($host));
}
$resolver->sites[] = ['id' => 14, 'clientid' => 10, 'url' => 'https://www.alpha.example.test/other'];
checkBaseline($resolver->resolveByHost('www.alpha.example.test') === null, 'Ambiguous canonical authority selected an arbitrary site');
checkBaseline($resolver->resolveByHost('www.alpha.example.test', ['requestPath' => '/site/page'])->getSiteId() === 11, 'Configured site base path did not select the correct shop');
checkBaseline($resolver->resolveByHost('www.alpha.example.test', ['requestPath' => '/other/page'])->getSiteId() === 14, 'Second site base path did not remain tenant-isolated');
checkBaseline($resolver->resolveByHost('www.alpha.example.test', ['requestPath' => '/outside']) === null, 'Request outside configured site base paths selected a shop');
checkBaseline($resolver->resolveByHost('www.alpha.example.test', ['requestPath' => '/site/../other']) === null, 'Unsafe request path selected a shop');

$resolver->sites[] = ['id' => 15, 'clientid' => 11, 'url' => 'https://www.alpha.example.test/site'];
checkBaseline($resolver->resolveByHost('www.alpha.example.test', ['requestPath' => '/site/page']) === null, 'Duplicate canonical host and base path selected an arbitrary tenant');

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
	checkBaseline(($headers['cache-control'] ?? '') === 'private, no-store', 'Dynamic response is publicly cacheable');
	checkBaseline(($headers['x-content-type-options'] ?? '') === 'nosniff', 'X-Content-Type-Options is missing');
	checkBaseline(($headers['referrer-policy'] ?? '') === 'strict-origin-when-cross-origin', 'Referrer-Policy is missing');
	checkBaseline(($headers['permissions-policy'] ?? '') === 'camera=(), microphone=(), geolocation=()', 'Permissions-Policy is missing');
	checkBaseline(!isset($headers['strict-transport-security']), 'HSTS was enabled before installation-wide HTTPS verification');
}
$explicitCacheHeaders = applySecurityHeaders('shops', $indexable, 'public, max-age=60');
checkBaseline(($explicitCacheHeaders['cache-control'] ?? '') === 'public, max-age=60', 'Explicit route cache policy was overwritten');

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
checkBaseline(str_contains($siteLayout, '$analyticsAllowed = $this->siteContext->canLoadAnalytics()'), 'Analytics is not gated by the consent context');
checkBaseline(str_contains($siteLayout, 'if($analyticsAllowed)') && !str_contains($siteLayout, 'if($this->siteContext->isIndexable()) echo'), 'Analytics bypasses consent gating');
checkBaseline(str_contains($siteLayout, 'data-consent-panel') && str_contains($siteLayout, "'site_consent'"), 'The public consent controls are missing');
$shopTranslations = file_get_contents($root . '/languages/de_DE/shops/shops.php');
checkBaseline(!str_contains($shopTranslations, 'Mit der Nutzung unserer Website stimmen Sie'), 'The legacy implied-consent notice remains in the translations');
checkBaseline(str_contains($shopTranslations, "'SHOPS_CONSENT_ACCEPT'") && str_contains($shopTranslations, "'SHOPS_CONSENT_DECLINE'"), 'Consent choices are not translated');
checkBaseline(str_contains($shopForm, "'description' => 'ADMIN_ANALYTICS_INFO'"), 'The analytics configuration lacks a consent explanation');

$consentSite = ['id' => 7, 'clientid' => 3, 'analytics' => '<script src="/analytics.js"></script>'];
$consentContext = new DEEC_Site_Context($consentSite, ['indexable' => true]);
$consentCookie = $consentContext->getAnalyticsConsentCookieName();
$accepted = $consentContext->getAnalyticsConsentValue('accepted');
$declined = $consentContext->getAnalyticsConsentValue('declined');
checkBaseline($consentCookie === 'dw_site_consent_3_7', 'Consent cookie is not isolated by client and site');
checkBaseline($accepted === '1:accepted:3:7' && $declined === '1:declined:3:7', 'Consent values are not versioned and site-bound');
checkBaseline($consentContext->getAnalyticsConsentState([]) === null, 'Missing consent is treated as accepted');
checkBaseline($consentContext->getAnalyticsConsentState([$consentCookie => $accepted]) === 'accepted', 'Valid acceptance is not recognized');
checkBaseline($consentContext->getAnalyticsConsentState([$consentCookie => $declined]) === 'declined', 'Valid refusal is not recognized');
checkBaseline((new DEEC_Site_Context(['analytics' => 'configured'], ['indexable' => true]))->getAnalyticsConsentValue('accepted') === null, 'Unscoped consent can be stored');
foreach (['invalid', '2:accepted:3:7', '1:accepted:3:8', '1:accepted:4:7'] as $value) {
	checkBaseline($consentContext->getAnalyticsConsentState([$consentCookie => $value]) === null, 'Malformed or foreign consent is accepted: ' . $value);
}
checkBaseline($consentContext->canLoadAnalytics([$consentCookie => $accepted], 'production'), 'Accepted production analytics is blocked');
checkBaseline(!$consentContext->canLoadAnalytics([$consentCookie => $declined], 'production'), 'Declined analytics is loaded');
checkBaseline(!$consentContext->canLoadAnalytics([$consentCookie => $accepted], 'development'), 'Development analytics is loaded');
checkBaseline(!$consentContext->canLoadAnalytics([$consentCookie => $accepted], 'staging'), 'Staging analytics is loaded');
checkBaseline(!(new DEEC_Site_Context($consentSite, ['indexable' => false]))->canLoadAnalytics([$consentCookie => $accepted], 'production'), 'Non-indexable analytics is loaded');
checkBaseline(!(new DEEC_Site_Context(['id' => 7, 'clientid' => 3], ['indexable' => true]))->canLoadAnalytics([$consentCookie => $accepted], 'production'), 'Empty analytics configuration is treated as active');

$consentController = file_get_contents($root . '/application/modules/shops/controllers/ConsentController.php');
checkBaseline(str_contains($consentController, 'isPost()') && str_contains($consentController, 'isSameOriginRequest()'), 'Consent writes are not restricted to same-origin POST requests');
checkBaseline(str_contains($consentController, "'httponly' => true") && str_contains($consentController, "'samesite' => 'Lax'"), 'Consent cookie security attributes are missing');
checkBaseline(str_contains($consentController, "'secure' => \$secure") && str_contains($consentController, "'path' => \$this->cookiePath()"), 'Consent cookie transport or path scope is missing');
checkBaseline(str_contains($consentController, "preg_match('~^/(?!/)[^\\r\\n]*$~D'"), 'Consent return path is not constrained locally');

require_once $root . '/application/modules/shops/controllers/Action.php';
require_once $root . '/application/modules/shops/controllers/ConsentController.php';
$consentReflection = new ReflectionClass(Shops_ConsentController::class);
$consentInstance = $consentReflection->newInstanceWithoutConstructor();
$sameOriginMethod = $consentReflection->getMethod('isSameOriginRequest');
$sameOriginMethod->setAccessible(true);
$returnPathMethod = $consentReflection->getMethod('returnPath');
$returnPathMethod->setAccessible(true);
$siteContextProperty = $consentReflection->getProperty('_siteContext');
$siteContextProperty->setAccessible(true);
$siteContextProperty->setValue($consentInstance, new DEEC_Site_Context(['url' => 'https://shop.example.test/site']));
$requestProperty = new ReflectionProperty(Zend_Controller_Action::class, '_request');
$requestProperty->setAccessible(true);
$consentRequest = new Zend_Controller_Request_Http();
$requestProperty->setValue($consentInstance, $consentRequest);
$originalServer = $_SERVER;

foreach ([
	[[], false, 'Consent request without browser origin signals was accepted'],
	[['HTTP_ORIGIN' => 'https://shop.example.test'], true, 'Same-origin consent request was rejected'],
	[['HTTP_ORIGIN' => 'https://shop.example.test:443'], true, 'Explicit default consent origin port was rejected'],
	[['HTTP_ORIGIN' => 'http://shop.example.test'], false, 'Cross-scheme consent request was accepted'],
	[['HTTP_ORIGIN' => 'https://shop.example.test:8443'], false, 'Cross-port consent request was accepted'],
	[['HTTP_ORIGIN' => 'https://other.example.test'], false, 'Cross-origin consent request was accepted'],
	[['HTTP_REFERER' => 'https://shop.example.test/page?return=1'], true, 'Same-origin consent referrer fallback was rejected'],
	[['HTTP_REFERER' => 'https://other.example.test/page'], false, 'Cross-origin consent referrer fallback was accepted'],
	[['HTTP_SEC_FETCH_SITE' => 'same-origin'], true, 'Same-origin Fetch Metadata request was rejected'],
	[['HTTP_SEC_FETCH_SITE' => 'cross-site'], false, 'Cross-site Fetch Metadata request was accepted'],
] as [$headers, $expected, $message]) {
	$_SERVER = ['HTTP_HOST' => 'manipulated.example.test'] + $headers;
	checkBaseline($sameOriginMethod->invoke($consentInstance) === $expected, $message);
}

foreach ([
	['/site/category?order=title', '/site/category?order=title', 'Valid subdirectory consent return path changed'],
	['/site/k%C3%BChlung?filter=%2F', '/site/k%C3%BChlung?filter=%2F', 'Valid encoded category consent return path changed'],
	['/site/', '/site/', 'Website home consent return path changed'],
	['/outside', '/site/', 'Consent return escaped the active website base path'],
	['//other.example.test/path', '/site/', 'Protocol-relative consent return was accepted'],
	['/site//broken', '/site/', 'Consent return accepted a double slash path'],
	['/site/../outside', '/site/', 'Consent return accepted a dot segment'],
	['/site/%2e%2e/outside', '/site/', 'Consent return accepted an encoded dot segment'],
	['/site/%252e%252e/outside', '/site/', 'Consent return accepted a double-encoded dot segment'],
	['/site/category%2Foutside', '/site/', 'Consent return accepted an encoded path separator'],
	['/site/cookie-consent', '/site/', 'Consent return pointed back to the POST-only endpoint'],
	["/site/page\r\nLocation: /outside", '/site/', 'Consent return accepted response splitting'],
] as [$returnPath, $expected, $message]) {
	$consentRequest->setParam('return', $returnPath);
	checkBaseline($returnPathMethod->invoke($consentInstance) === $expected, $message);
}

$_SERVER = $originalServer;

$siteController = file_get_contents($root . '/application/modules/shops/controllers/Action.php');
checkBaseline(str_contains($siteController, 'if (!$this->_siteContext->isIndexable())'), 'Non-indexable sites can emit canonical links');

$sitemapController = file_get_contents($root . '/application/modules/shops/controllers/SitemapController.php');
checkBaseline(str_contains($sitemapController, 'if (!$this->_siteContext->isIndexable())')
	&& str_contains($sitemapController, 'setHttpResponseCode(404)'), 'Non-indexable sites can expose a sitemap');

echo 'PASS: ' . $checks . " HTML5, indexing and response security baseline cases\n";
