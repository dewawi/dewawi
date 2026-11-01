<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/site/conversion-tracking.php
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class) use ($root): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) {
		require_once $path;
		return;
	}
	$parts = explode('_', $class);
	if (count($parts) >= 3) {
		$module = array_shift($parts);
		$base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
		$path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
		if (is_file($path)) require_once $path;
	}
});
require_once $root . '/application/modules/shops/controllers/Action.php';

$checks = 0;
function check(bool $condition, string $message): void
{
	global $checks;
	if (!$condition) throw new RuntimeException($message);
	$checks++;
}

class SiteConversionTestController extends Shops_Controller_Action
{
	public function init() {}
	public function setContext(DEEC_Site_Context $context): void { $this->_siteContext = $context; }
	public function mark(string $name, ?float $value, ?string $currency, int $now, array $cookies, string $environment): void
	{
		$this->markSiteConversion($name, $value, $currency, $now, $cookies, $environment);
	}
	public function consume(int $now, array $cookies, string $environment): array
	{
		return $this->consumeSiteConversions($now, $cookies, $environment);
	}
	public function clear(): void { $this->clearSiteConversions(); }
}

$sessionDirectory = sys_get_temp_dir() . '/dewawi-conversion-session-' . getmypid() . '-' . bin2hex(random_bytes(4));
if (!mkdir($sessionDirectory, 0700)) throw new RuntimeException('Could not create conversion session directory');
Zend_Session::setOptions(['save_path' => $sessionDirectory, 'use_cookies' => false]);

$gtagSnippet = '<script async src="https://www.googletagmanager.com/gtag/js?id=G-TEST123"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("config","G-TEST123");</script>';
$gtmSnippet = '<script>(function(w,d,s,l,i){w[l]=w[l]||[];j.src="https://www.googletagmanager.com/gtm.js?id="+i;})(window,document,"script","dataLayer","GTM-TEST");</script>';
$unsupportedSnippet = '<script src="https://analytics.example.invalid/tracker.js"></script>';

$gtagContext = new DEEC_Site_Context(['id' => 7, 'clientid' => 3, 'analytics' => $gtagSnippet], ['indexable' => true]);
$gtmContext = new DEEC_Site_Context(['id' => 8, 'clientid' => 3, 'analytics' => $gtmSnippet], ['indexable' => true]);
$unsupportedContext = new DEEC_Site_Context(['id' => 9, 'clientid' => 3, 'analytics' => $unsupportedSnippet], ['indexable' => true]);
check($gtagContext->getAnalyticsEventInterface() === 'gtag', 'Direct gtag configuration was not detected');
check($gtmContext->getAnalyticsEventInterface() === 'dataLayer', 'Google Tag Manager dataLayer was not detected');
check($unsupportedContext->getAnalyticsEventInterface() === null, 'Unsupported free analytics snippet received an event interface');
check((new DEEC_Site_Context(['id' => 10, 'clientid' => 3, 'analytics' => $gtagSnippet . $gtmSnippet], ['indexable' => true]))->getAnalyticsEventInterface() === 'gtag', 'Multiple interfaces can cause duplicate event dispatch');

$controller = new SiteConversionTestController(new Zend_Controller_Request_Simple(), new Zend_Controller_Response_Http());
$controller->setContext($gtagContext);
$accepted = [$gtagContext->getAnalyticsConsentCookieName() => $gtagContext->getAnalyticsConsentValue('accepted')];
$declined = [$gtagContext->getAnalyticsConsentCookieName() => $gtagContext->getAnalyticsConsentValue('declined')];
$now = 2000000000;

$controller->mark('contact_submitted', null, null, $now, $accepted, 'production');
$events = $controller->consume($now, $accepted, 'production');
check(count($events) === 1 && $events[0]['name'] === 'contact_submitted' && $events[0]['interface'] === 'gtag', 'Stored contact did not produce its conversion event');
check($events[0]['parameters'] === ['shop_id' => 7], 'Contact event contains data beyond the shop context');
check($controller->consume($now, $accepted, 'production') === [], 'Success page reload repeated a conversion event');

$controller->mark('technical_inquiry_submitted', null, null, $now, $accepted, 'production');
$events = $controller->consume($now, $accepted, 'production');
check(count($events) === 1 && $events[0]['name'] === 'technical_inquiry_submitted', 'Stored technical inquiry did not produce its conversion event');
check($events[0]['parameters'] === ['shop_id' => 7], 'Technical inquiry event contains form data');

$controller->mark('order_submitted', 123.45678, 'eur', $now, $accepted, 'production');
$events = $controller->consume($now, $accepted, 'production');
check(count($events) === 1 && $events[0]['name'] === 'order_submitted', 'Committed order did not produce its conversion event');
check($events[0]['parameters'] === ['shop_id' => 7, 'value' => 123.4568, 'currency' => 'EUR'], 'Order event value or currency is not minimized and normalized');
check(!str_contains(json_encode($events), 'purchase') && !str_contains(json_encode($events), 'payment'), 'Order is incorrectly represented as a payment or purchase');

foreach ([
	[[], 'production'],
	[$declined, 'production'],
	[$accepted, 'development'],
	[$accepted, 'staging'],
] as [$cookies, $environment]) {
	$controller->mark('contact_submitted', null, null, $now, $cookies, $environment);
	check($controller->consume($now, $accepted, 'production') === [], "Conversion marker was created for $environment without valid consent");
}

$controller->mark('contact_submitted', null, null, $now, $accepted, 'production');
check($controller->consume($now, $declined, 'production') === [], 'Consent revocation did not discard a pending conversion');
check($controller->consume($now, $accepted, 'production') === [], 'Revoked conversion was sent after later acceptance');

$controller->mark('contact_submitted', null, null, $now, $accepted, 'production');
$controller->clear();
check($controller->consume($now, $accepted, 'production') === [], 'Explicit consent revocation did not clear its pending conversion immediately');

$controller->mark('contact_submitted', null, null, $now, $accepted, 'production');
$controller->mark('contact_submitted', null, null, $now, $declined, 'production');
check($controller->consume($now, $accepted, 'production') === [], 'A submission without consent retained an older conversion for later dispatch');

$controller->mark('contact_submitted', null, null, $now, $accepted, 'production');
check($controller->consume($now + 601, $accepted, 'production') === [], 'Expired conversion marker was dispatched');
$controller->mark('invalid_event', null, null, $now, $accepted, 'production');
$controller->mark('order_submitted', -1, 'EUR', $now, $accepted, 'production');
$controller->mark('order_submitted', 1, 'EURO', $now, $accepted, 'production');
check($controller->consume($now, $accepted, 'production') === [], 'Invalid conversion name, value or currency was accepted');

$controller->setContext($unsupportedContext);
$unsupportedAccepted = [$unsupportedContext->getAnalyticsConsentCookieName() => $unsupportedContext->getAnalyticsConsentValue('accepted')];
$controller->mark('contact_submitted', null, null, $now, $unsupportedAccepted, 'production');
check($controller->consume($now, $unsupportedAccepted, 'production') === [], 'Unsupported analytics configuration received a conversion');

$controller->setContext($gtagContext);
$controller->mark('contact_submitted', null, null, $now, $accepted, 'production');
$controller->setContext($gtmContext);
$gtmAccepted = [$gtmContext->getAnalyticsConsentCookieName() => $gtmContext->getAnalyticsConsentValue('accepted')];
check($controller->consume($now, $gtmAccepted, 'production') === [], 'Conversion marker crossed the shop boundary');
$controller->setContext($gtagContext);
check($controller->consume($now, $accepted, 'production') === [], 'Foreign shop navigation restored a discarded conversion marker');

$conversionSession = new Zend_Session_Namespace('SiteConversion');
$conversionSession->events = [[
	'id' => 'invalid',
	'name' => 'contact_submitted',
	'parameters' => ['shop_id' => 7],
	'interface' => 'gtag',
	'clientid' => 3,
	'siteid' => 7,
	'created' => $now,
]];
check($controller->consume($now, $accepted, 'production') === [], 'Malformed server-side conversion marker was replaced and dispatched');

for ($i = 0; $i < 12; $i++) $controller->mark('contact_submitted', null, null, $now + $i, $accepted, 'production');
check(count($controller->consume($now + 12, $accepted, 'production')) === 10, 'Conversion session queue is not bounded');

$contactSource = file_get_contents($root . '/application/modules/shops/controllers/ContactController.php');
$inquirySource = file_get_contents($root . '/application/modules/shops/controllers/InquiryController.php');
$checkoutSource = file_get_contents($root . '/application/modules/shops/controllers/CheckoutController.php');
$contactInsertedAt = strpos($contactSource, "if (\$stored['inserted'])");
$contactEventAt = strpos($contactSource, "markSiteConversion('contact_submitted')");
check($contactInsertedAt !== false && $contactEventAt !== false && $contactInsertedAt < $contactEventAt, 'Contact replay or honeypot can mark a conversion');
$inquiryDuplicateAt = strpos($inquirySource, "if (!\$stored['inserted'])");
$inquiryEventAt = strpos($inquirySource, "markSiteConversion('technical_inquiry_submitted')");
check($inquiryDuplicateAt !== false && $inquiryEventAt !== false && $inquiryDuplicateAt < $inquiryEventAt, 'Inquiry replay or intermediate step can mark a conversion');
$checkoutInsertedAt = strpos($checkoutSource, "if (\$result['inserted'])");
$checkoutEventAt = strpos($checkoutSource, "markSiteConversion('order_submitted'");
$checkoutEmailAt = strpos($checkoutSource, "sendEmail('shops', 'checkout'");
check($checkoutInsertedAt !== false && $checkoutEventAt !== false && $checkoutInsertedAt < $checkoutEventAt, 'Checkout replay or rollback can mark a conversion');
check($checkoutEmailAt !== false && $checkoutEventAt < $checkoutEmailAt, 'Order conversion incorrectly depends on successful email delivery');

$layout = file_get_contents($root . '/application/layouts/scripts/site.phtml');
check(str_contains($layout, 'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT'), 'Conversion payload is not safely JSON serialized');
$consentController = file_get_contents($root . '/application/modules/shops/controllers/ConsentController.php');
check(str_contains($consentController, "if (\$state === 'declined') \$this->clearSiteConversions()"), 'Consent revocation does not immediately discard pending conversions');
$javascript = file_get_contents($root . '/js/shops.js');
foreach (['name', 'email', 'phone', 'address', 'message', 'token'] as $personalField) {
	check(!preg_match('/parameters\.' . preg_quote($personalField, '/') . '\b/', $javascript), 'Frontend conversion supports personal field: ' . $personalField);
}
check(!str_contains($javascript, "gtag('event', 'purchase'") && !str_contains($javascript, "event: 'purchase'"), 'Frontend emits an unproven purchase event');
check(!is_file($root . '/install/config/update-1.1.8-1.1.9.sql'), 'Conversion tracking introduced an unsupported migration version');

Zend_Session::writeClose();
foreach (glob($sessionDirectory . '/*') as $file) unlink($file);
rmdir($sessionDirectory);

echo "PASS: {$checks} consent, minimization, deduplication, interface and isolation checks\n";
