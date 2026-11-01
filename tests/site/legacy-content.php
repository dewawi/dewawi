<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/site/legacy-content.php (no database required).

error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path;
});

require_once $root . '/library/DEEC/Filter.php';
require_once $root . '/library/DEEC/Site/Block.php';
require_once $root . '/application/modules/admin/forms/Page.php';
require_once $root . '/application/modules/admin/forms/Category.php';
require_once $root . '/application/modules/admin/forms/Shop.php';
require_once $root . '/application/modules/admin/forms/Tag.php';
require_once $root . '/application/modules/items/forms/Item.php';

$checks = 0;

function checkLegacyContent(bool $condition, string $message): void
{
	global $checks;
	$checks++;
	if (!$condition) throw new RuntimeException($message);
}

$format = [
	'type' => 'html',
	'managedMediaOnly' => true,
	'allowTags' => ['a', 'p', 'span', 'img', 'div', 'br', 'strong', 'em', 'h1', 'h2'],
	'allowAttribs' => ['href', 'src', 'title', 'alt', 'class', 'id', 'style', 'width', 'height', 'onclick', 'onerror', 'srcdoc'],
];
$input = '<h1>Main heading</h1>'
	. '<div id="section" class="copy" style="text-align:center; background-image:url(https://tracker.invalid/x.png)" onclick="alert(1)">Body</div>'
	. '<a href="">Empty</a><a href="#">Hash</a><a href="#section">Fragment</a>'
	. '<a href="javascript:alert(1)">Unsafe</a><a href="https://example.invalid/page">External</a>'
	. '<a href="/library/private.pdf">Protected</a><a href="../private">Traversal</a>'
	. '<img src="/media/images/pump.webp" alt="Heat pump" width="800" height="600" onerror="alert(1)">'
	. '<img src="https://tracker.invalid/pixel.gif" alt="Pixel">'
	. '<iframe src="https://video.invalid/embed/1"></iframe><script>alert(1)</script>';
$filtered = DEEC_Filter::applyAll(['content' => $input], ['content' => $format])['content'];

checkLegacyContent(str_contains($filtered, '<h1>Main heading</h1>'), 'A meaningful legacy H1 was changed');
checkLegacyContent(str_contains($filtered, 'href="#section"'), 'A valid internal fragment link was removed');
checkLegacyContent(str_contains($filtered, 'href="https://example.invalid/page"'), 'A normal external hyperlink was removed');
checkLegacyContent(!str_contains($filtered, 'href=""') && !str_contains($filtered, 'href="#"'), 'An empty link target survived filtering');
checkLegacyContent(!str_contains($filtered, 'javascript:') && !str_contains($filtered, '/library/private.pdf') && !str_contains($filtered, 'href="../private"'), 'An unsafe, traversing or protected link survived filtering');
checkLegacyContent(str_contains($filtered, 'src="/media/images/pump.webp"') && str_contains($filtered, 'alt="Heat pump"'), 'A managed informative image was removed');
checkLegacyContent(!str_contains($filtered, 'tracker.invalid/pixel.gif'), 'An external WYSIWYG image survived managed-media filtering');
checkLegacyContent(!preg_match('/\son(?:click|error)\s*=/i', $filtered), 'An executable event attribute survived filtering');
checkLegacyContent(str_contains($filtered, 'style="text-align: center"') && !str_contains($filtered, 'background-image'), 'Safe and unsafe inline styles were not separated');
checkLegacyContent(!str_contains($filtered, '<iframe') && !str_contains($filtered, '<script'), 'Executable or embedded markup survived filtering');

$unchanged = '<h1 id="main">Existing heading</h1><p class="lead">Existing <strong>customer content</strong>.</p>';
checkLegacyContent(DEEC_Filter::removeEmptyHeadings($unchanged) === $unchanged, 'Valid stored customer content changed during H1 output handling');
checkLegacyContent(DEEC_Filter::removeEmptyHeadings('<h1>&nbsp;<span> </span><br></h1><p>Text</p>') === '<p>Text</p>', 'An empty H1 was not removed safely at output time');
$multiple = '<h1>First</h1><h1>Second</h1>';
checkLegacyContent(DEEC_Filter::removeEmptyHeadings($multiple) === $multiple, 'Multiple meaningful H1 elements were changed without editorial approval');
checkLegacyContent(DEEC_Filter::removeEmptyHeadings('<h2> </h2>', 2) === '', 'The targeted heading level was not handled');

$formInput = ['content' => $input];
$pageForm = new Admin_Form_Page();
checkLegacyContent($pageForm->isValid($formInput), 'Normal page form input was rejected before HTML filtering');
$normal = $pageForm->getFilteredValues()['content'];
$pageForm = new Admin_Form_Page();
checkLegacyContent($pageForm->isValidPartial($formInput), 'Page auto-save input was rejected before HTML filtering');
$partial = $pageForm->getFilteredValuesPartial($formInput)['content'];
checkLegacyContent($normal === $partial && !str_contains($partial, 'javascript:'), 'Normal save and auto-save apply different HTML filters');

foreach ([new Admin_Form_Category(), new Admin_Form_Shop(), new Admin_Form_Tag()] as $form) {
	$field = $form instanceof Admin_Form_Shop ? 'contact' : 'description';
	$values = $form->getFilteredValuesPartial([$field => $input]);
	checkLegacyContent(isset($values[$field]) && !str_contains($values[$field], '<iframe') && !str_contains($values[$field], 'tracker.invalid/pixel.gif'), get_class($form) . ' bypasses public HTML filtering');
}
$itemForm = new Items_Form_Item();
$itemHtml = $itemForm->getFilteredValuesPartial(['shopdescription' => $input])['shopdescription'];
checkLegacyContent(!str_contains($itemHtml, '<img') && !str_contains($itemHtml, '<script') && str_contains($itemHtml, 'href="#section"'), 'Product WYSIWYG filtering differs from its declared field capabilities');

foreach (DEEC_Site_Block::getDefinitions() as $definition) {
	foreach ($definition['fields'] as $field) {
		if (($field['format']['type'] ?? null) !== 'html') continue;
		$blockHtml = DEEC_Filter::applyAll(['text' => $input], ['text' => $field['format']])['text'];
		checkLegacyContent(!str_contains($blockHtml, '<script') && !str_contains($blockHtml, '<iframe') && !str_contains($blockHtml, 'javascript:'), 'CMS block HTML filtering permits executable content');
	}
}

$tinyMce = file_get_contents($root . '/application/views/helpers/TinyMCE.php');
checkLegacyContent(!preg_match('/onmouseover|onmouseout|srcdoc|formaction/i', $tinyMce), 'TinyMCE still advertises executable or unsafe attributes');
checkLegacyContent(str_contains($tinyMce, 'img[class|src|alt|title|width|height]') && str_contains($tinyMce, 'valid_styles'), 'TinyMCE and the server-side media/style rules are not aligned');

$tagController = file_get_contents($root . '/application/modules/admin/controllers/TagController.php');
checkLegacyContent(substr_count($tagController, 'getFilteredValuesPartial(') >= 2, 'Independent tag auto-save paths bypass the form filter');
$action = file_get_contents($root . '/library/DEEC/Controller/Action.php');
checkLegacyContent(substr_count($action, 'getFilteredValuesPartial(') >= 1 && substr_count($action, 'getFilteredValues()') >= 1, 'Generic normal and auto-save paths do not both use form filtering');

foreach (['category/index.phtml', 'item/index.phtml', 'inquiry/index.phtml'] as $template) {
	$source = file_get_contents($root . '/application/modules/shops/views/scripts/' . $template);
	checkLegacyContent(!preg_match('/\son[a-z]+\s*=/i', $source), 'Public template still emits an inline event handler: ' . $template);
}
$shopsJs = file_get_contents($root . '/js/shops.js');
checkLegacyContent(str_contains($shopsJs, "[data-show-tab]") && str_contains($shopsJs, "[data-show-section]") && str_contains($shopsJs, "'submit', '#inquiry-form'"), 'Removed inline public handlers lack delegated replacements');
$siteImage = file_get_contents($root . '/application/modules/shops/views/helpers/SiteImage.php');
checkLegacyContent(!str_contains($siteImage, "'onclick'"), 'The managed public image helper still accepts inline event handlers');

echo 'PASS: ' . $checks . " legacy HTML, heading, link, image, embed and save-path cases\n";
