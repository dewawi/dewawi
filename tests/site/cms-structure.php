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

$itemEditSource = file_get_contents($root . '/application/modules/items/views/scripts/item/edit.phtml');
checkCms(str_contains($itemEditSource, "\$this->Media([") && str_contains($itemEditSource, "'path' => 'images'"), 'Item editor does not use the managed media workflow');
checkCms(!str_contains($itemEditSource, '$this->Images()'), 'Item editor still uses the obsolete image workflow');

$itemControllerSource = file_get_contents($root . '/application/modules/items/controllers/ItemController.php');
checkCms(str_contains($itemControllerSource, "getMediaByParentID(\$id, 'items', 'item')"), 'Item editor does not load its managed media');

$pageBlockViewSource = file_get_contents($root . '/application/modules/admin/views/scripts/pageblock/edit.phtml');
$globalJsSource = file_get_contents($root . '/js/global.js');
$blockSource = file_get_contents($root . '/library/DEEC/Site/Block.php');
checkCms(str_contains($pageBlockViewSource, '$this->MediaManager()') && !str_contains($pageBlockViewSource, '$this->FileManager()'), 'Page block editor does not use the public media library');
checkCms(substr_count($blockSource, "'data-filemanager' => 'media'") === 3, 'Page block image fields do not select public media');
checkCms(str_contains($globalJsSource, "=== 'media' ? 'MediaManager' : 'FileManager'") && str_contains($globalJsSource, "'/library/' + manager + '/dialog.php'"), 'File picker does not route public image fields to MediaManager');

$itemViewSource = file_get_contents($root . '/application/modules/shops/views/scripts/item/index.phtml');
checkCms(substr_count($itemViewSource, "'id' => 'mainImage'") === 1, 'Product detail renders duplicate main image IDs');
checkCms(substr_count($itemViewSource, 'id="imageModal"') === 1, 'Product detail renders duplicate image modal IDs');
checkCms(substr_count($itemViewSource, 'id="carouselExampleControls"') === 1, 'Product detail renders duplicate carousel IDs');
checkCms(!str_contains($itemViewSource, 'data-bs-'), 'Product detail mixes Bootstrap 5 controls into its Bootstrap 4 layout');

$imageTemplates = [
	'application/layouts/scripts/site.phtml',
	'application/modules/shops/views/helpers/RenderCategory.php',
	'application/modules/shops/views/helpers/Slide.php',
	'application/modules/shops/views/scripts/page/blocks/hero.phtml',
	'application/modules/shops/views/scripts/page/blocks/image_text.phtml',
	'application/modules/shops/views/scripts/page/blocks/card.phtml',
	'application/modules/shops/views/scripts/category/index.phtml',
	'application/modules/shops/views/scripts/item/index.phtml',
	'application/modules/shops/views/scripts/inquiry/index.phtml',
];
foreach ($imageTemplates as $template) {
	checkCms(str_contains(file_get_contents($root . '/' . $template), 'SiteImage('), 'Public image template bypasses the shared renderer: ' . $template);
}
$mediaConfig = file_get_contents($root . '/library/MediaManager/config/config.php');
checkCms(str_contains($mediaConfig, "'relative_image_creation'                 => true")
	&& str_contains($mediaConfig, "array( '300/', '500/', '800/', '1000/' )"), 'Managed media uploads no longer create the fixed responsive variants');
$pageRenderer = file_get_contents($root . '/application/modules/shops/views/helpers/RenderPageBlocks.php');
$heroTemplate = file_get_contents($root . '/application/modules/shops/views/scripts/page/blocks/hero.phtml');
$shopCss = file_get_contents($root . '/css/shop.css');
$siteLayoutSource = file_get_contents($root . '/application/layouts/scripts/site.phtml');
checkCms(str_contains($pageRenderer, "in_array(\$type, ['hero', 'image_text'], true) && \$html === ''")
	&& str_contains($heroTemplate, "'fetchpriority' => \$priorityImage ? 'high' : ''"), 'CMS images can prioritize more than the first visible media block');
checkCms(str_contains($shopCss, '.main-image-wrapper {') && str_contains($shopCss, 'aspect-ratio: 4 / 3;'), 'Product media does not reserve a responsive aspect ratio');
checkCms(!str_contains($siteLayoutSource, 'as="image"'), 'Public layout preloads an image without a page-specific responsive source');
checkCms(str_contains($siteLayoutSource, 'jquery-3.7.1.min.js') && !str_contains($siteLayoutSource, 'jquery-3.5.1'), 'Public layout does not use jQuery 3.7.1 exclusively');
checkCms(!str_contains($siteLayoutSource, 'jquery-3.7.1.slim') && !str_contains($siteLayoutSource, 'jquery-migrate'), 'Public layout uses an incompatible jQuery build or Migrate');
checkCms(str_contains($siteLayoutSource, 'bootstrap@4.6.2/dist/css/bootstrap.min.css')
	&& str_contains($siteLayoutSource, 'bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js')
	&& !str_contains($siteLayoutSource, 'bootstrap/4.5.2'), 'Public layout does not use Bootstrap 4.6.2 exclusively');
checkCms(substr_count($siteLayoutSource, 'integrity="sha384-') === 3 && substr_count($siteLayoutSource, 'crossorigin="anonymous"') === 3, 'External public assets are not protected by SRI and anonymous CORS');
checkCms(str_contains($siteLayoutSource, 'sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs')
	&& str_contains($siteLayoutSource, 'sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N')
	&& str_contains($siteLayoutSource, 'sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct'), 'Public asset integrity hashes changed unexpectedly');
checkCms(substr_count($siteLayoutSource, '<script src=') === 2 && substr_count($siteLayoutSource, '/js/shops.js') === 1, 'Public layout loads duplicate JavaScript libraries');
checkCms(strpos($siteLayoutSource, 'jquery-3.7.1.min.js') < strpos($siteLayoutSource, 'bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js')
	&& strpos($siteLayoutSource, 'bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js') < strpos($siteLayoutSource, '/js/shops.js'), 'Public script dependency order is invalid');
checkCms(substr_count($shopCss, '.dw-site-header {') === 1 && !preg_match('/^header(?:\.home)?\s*\{/m', $shopCss), 'Public stylesheet retains duplicate generic header rules');

$menuSource = file_get_contents($root . '/application/modules/shops/views/helpers/RenderMenuItems.php');
$categoryNavigationSource = file_get_contents($root . '/application/modules/shops/views/helpers/RenderCategoryNavigation.php');
$subCategorySource = file_get_contents($root . '/application/modules/shops/views/helpers/RenderSubCategory.php');
$slideSource = file_get_contents($root . '/application/modules/shops/views/helpers/Slide.php');
$shopJsSource = file_get_contents($root . '/js/shops.js');
$searchViewSource = file_get_contents($root . '/application/modules/shops/views/scripts/search/index.phtml');
checkCms(substr_count($siteLayoutSource, '<main id="main-content"') === 1 && !str_contains($searchViewSource, '<main'), 'Public pages do not have exactly one template-owned main landmark');
checkCms(str_contains($siteLayoutSource, 'class="dw-skip-link"') && str_contains($siteLayoutSource, 'aria-label="<?php echo $this->translate(\'SHOPS_MAIN_NAVIGATION\')'), 'Public layout lacks its skip link or labelled main navigation');
checkCms(str_contains($siteLayoutSource, "translate('SHOPS_FOOTER_NAVIGATION')"), 'Footer navigation is not labelled');
checkCms(str_contains($menuSource, 'dw-mega-toggle" type="button" data-mega-toggle aria-expanded="false" aria-controls=')
	&& str_contains($menuSource, 'class="dw-mega-menu" id=') && str_contains($menuSource, ' hidden><div'), 'Mega-menu controls are not connected to hidden panels');
checkCms(!str_contains($categoryNavigationSource, 'href="#"') && str_contains($categoryNavigationSource, 'dw-submenu-toggle--root'), 'Category fallback still uses a placeholder link');
checkCms(str_contains($categoryNavigationSource, 'aria-controls=') && str_contains($subCategorySource, 'aria-controls='), 'Category submenu buttons lack controlled panel references');
checkCms(str_contains($slideSource, 'data-carousel-toggle') && str_contains($slideSource, 'SHOPS_SLIDER_PAUSE') && str_contains($slideSource, 'SHOPS_SLIDER_PLAY'), 'Automatic slider lacks a pause control');
checkCms(str_contains($shopCss, '@media (prefers-reduced-motion: reduce)') && str_contains($shopCss, ':focus-visible'), 'Public CSS lacks reduced-motion or visible-focus handling');
checkCms(!str_contains($shopJsSource, '${item.title}') && !str_contains($shopJsSource, '${item.sku}')
	&& str_contains($shopJsSource, "$('<strong>', { class: 'product-title' }).text(title)"), 'Cart response text is still interpolated as HTML');
checkCms(str_contains($shopJsSource, "trigger('focus')") && str_contains($shopJsSource, "event.key !== 'Escape'"), 'Navigation does not restore focus after Escape');
checkCms(!preg_match('/<button(?![^>]*\\btype=)/', $itemViewSource), 'Product detail contains a button without an explicit type');

echo 'PASS: ' . $checks . " CMS block, CTA, theme and 404 structure cases\n";
