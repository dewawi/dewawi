<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/site/cms-structure.php (no database required).

error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) {
		require_once $path;
	}
});

require_once $root . '/library/DEEC/Site/Block.php';
require_once $root . '/library/DEEC/Site/Context.php';
require_once $root . '/library/DEEC/Site/Router.php';
require_once $root . '/application/controllers/ErrorController.php';

$checks = 0;

function checkCms(bool $condition, string $message): void
{
	global $checks;
	$checks++;

	if (!$condition) {
		throw new RuntimeException($message);
	}
}

class CmsTestErrorController extends ErrorController
{
	public function isNotFoundError($errors): bool
	{
		return $this->isNotFound($errors);
	}
}

$definitions = DEEC_Site_Block::getDefinitions();
$blockPath = $root . '/application/modules/shops/views/scripts/page/blocks';

foreach ($definitions as $type => $definition) {
	checkCms(isset($definition['label'], $definition['fields']), 'Incomplete block definition: ' . $type);
	checkCms(is_file($blockPath . '/' . $type . '.phtml'), 'Missing block partial: ' . $type);
}

foreach (['de_DE', 'en_US'] as $language) {
	$translations = require $root . '/languages/' . $language . '/admin/admin.php';

	foreach ($definitions as $definition) {
		$labels = [$definition['label']];

		foreach ($definition['fields'] as $field) {
			if (!empty($field['label'])) $labels[] = $field['label'];
			foreach (($field['options'] ?? []) as $label) {
				if (!is_numeric($label)) $labels[] = $label;
			}
		}

		foreach ($labels as $label) {
			checkCms(isset($translations[$label]), 'Missing ' . $language . ' block translation: ' . $label);
		}
	}
}

$view = new Zend_View();
$view->setScriptPath($root . '/application/modules/shops/views/scripts');
$cta = $view->partial('page/blocks/cta.phtml', [
	'data' => [
		'eyebrow' => 'Pilot',
		'title' => 'Contact us',
		'text' => '<p>Ready.</p>',
		'buttonlabel' => 'Start',
		'buttonurl' => 'https://example.test/start',
		'buttonlabel2' => 'Unsafe',
		'buttonurl2' => 'javascript:alert(1)',
		'variant' => 'dark',
		'alignment' => 'center',
	],
]);

checkCms(str_contains($cta, 'dw-site-block--cta-dark'), 'CTA variant is not rendered');
checkCms(str_contains($cta, 'dw-site-block--cta-center'), 'CTA alignment is not rendered');
checkCms(str_contains($cta, 'https://example.test/start'), 'CTA primary link is missing');
checkCms(!str_contains($cta, 'javascript:'), 'CTA renders an unsafe URL scheme');

$context = new DEEC_Site_Context(['theme' => ' Pilot_Theme ']);
checkCms($context->getTheme() === 'pilot_theme', 'Configured theme is not normalized');
$context = new DEEC_Site_Context(['theme' => '../pilot']);
checkCms($context->getTheme() === 'default', 'Invalid theme name is not rejected');

$router = new Zend_Controller_Router_Rewrite();
$method = new ReflectionMethod(DEEC_Site_Router::class, 'registerFallbackRoute');
$method->setAccessible(true);
$method->invoke(new DEEC_Site_Router(), $router);
$fallback = $router->getRoute('shop_fallback')->match('missing-page');

checkCms(($fallback['module'] ?? '') === 'shops', 'Fallback uses the wrong module');
checkCms(($fallback['controller'] ?? '') === 'page', 'Fallback does not use the 404 page controller');
checkCms((int)($fallback['id'] ?? -1) === 0, 'Fallback does not force a missing page');

$controller = (new ReflectionClass(CmsTestErrorController::class))->newInstanceWithoutConstructor();
$error = (object)[
	'type' => Zend_Controller_Plugin_ErrorHandler::EXCEPTION_OTHER,
	'exception' => new Zend_Controller_Action_Exception('Page not found', 404),
];
checkCms($controller->isNotFoundError($error), 'Page action exceptions are not classified as HTTP 404');

echo 'PASS: ' . $checks . " CMS block, CTA, theme and 404 structure cases\n";
