<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/site/seo-metadata.php (no database required).

error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path;
});

require_once $root . '/library/DEEC/Site/Context.php';
require_once $root . '/application/modules/shops/views/helpers/SiteMetadata.php';
require_once $root . '/application/modules/shops/views/helpers/Breadcrumbs.php';

$checks = 0;

function checkMetadata(bool $condition, string $message): void
{
	global $checks;
	$checks++;
	if (!$condition) throw new RuntimeException($message);
}

class SeoMetadataView extends Zend_View
{
	public array $slugPaths = [];
	public Zend_View_Helper_SiteMetadata $metadataHelper;

	public function __construct(DEEC_Site_Context $context, string $root)
	{
		parent::__construct();
		$this->siteContext = $context;
		$this->doctype('HTML5');
		$this->setScriptPath($root . '/application/modules/shops/views/scripts');
		$this->addHelperPath($root . '/application/modules/shops/views/helpers', 'Zend_View_Helper');
		$this->metadataHelper = new Zend_View_Helper_SiteMetadata();
		$this->metadataHelper->setView($this);
	}

	public function SiteMetadata(?array $metadata = null): Zend_View_Helper_SiteMetadata
	{
		return $this->metadataHelper->SiteMetadata($metadata);
	}

	public function SlugUrl($controller, $entityId): ?string
	{
		return $this->slugPaths[$controller . ':' . (int)$entityId] ?? null;
	}

	public function translate($messageId)
	{
		return $messageId === 'SHOPS_HOME' ? 'Home' : $messageId;
	}

	public function url(array $options = [], $name = null, $reset = false, $encode = true): string
	{
		return $name === 'shop_home' ? '/' : '/' . trim((string)$name, '/');
	}

	public function baseUrl($path = null): string
	{
		return $path === null ? '' : (string)$path;
	}

	public function PageByType($type)
	{
		return null;
	}

	public function RenderPageBlocks(array $blocks, bool $allowHeroH1 = true): string
	{
		$html = '';
		$renderedH1 = false;
		foreach ($blocks as $block) {
			if (($block['type'] ?? '') !== 'hero') continue;
			$data = json_decode((string)($block['data'] ?? ''), true);
			$headingLevel = $allowHeroH1 && !$renderedH1 ? 1 : 2;
			if ($headingLevel === 1 && trim((string)($data['title'] ?? '')) !== '') $renderedH1 = true;
			$html .= $this->partial('page/blocks/hero.phtml', ['data' => $data, 'headingLevel' => $headingLevel]);
		}
		return $html;
	}
}

function resetMetadataPlaceholders(): void
{
	$registry = Zend_View_Helper_Placeholder_Registry::getRegistry();
	$registry->deleteContainer('Zend_View_Helper_HeadTitle');
	$registry->deleteContainer('Zend_View_Helper_HeadMeta');
}

resetMetadataPlaceholders();
$context = new DEEC_Site_Context([
	'title' => 'Example & Co',
	'url' => 'https://www.example.test/site',
	'language' => 'de_DE',
	'logo' => 'brand logo.png',
]);
$view = new SeoMetadataView($context, $root);
$metadata = $view->SiteMetadata([
	'title' => 'Wärmepumpen - Example & Co',
	'description' => '<p>Effiziente &amp; sichere Systeme</p>',
	'path' => '/produkte/waermepumpen',
	'type' => 'article',
	'imageDirectory' => 'category',
	'image' => 'wärmepumpe.webp',
]);

$headTitle = $view->headTitle()->toString();
$headMeta = $view->headMeta()->toString();
checkMetadata(substr_count($headTitle, 'Example &amp; Co') === 1, 'The site name is duplicated in the document title');
checkMetadata(str_contains($headTitle, 'Wärmepumpen'), 'The individual SEO title is missing');
checkMetadata(substr_count($headMeta, 'name="description"') === 1, 'The description meta element is missing or duplicated');
checkMetadata(str_contains($headMeta, 'Effiziente &amp; sichere Systeme'), 'The meta description was not cleaned and escaped');
foreach (['og:type', 'og:title', 'og:description', 'og:url', 'og:image', 'og:site_name', 'og:locale'] as $property) {
	checkMetadata(substr_count($headMeta, 'property="' . $property . '"') === 1, 'Open Graph property is missing or duplicated: ' . $property);
}
checkMetadata(str_contains($headMeta, 'https://www.example.test/site/produkte/waermepumpen'), 'Open Graph URL differs from the canonical site path');
checkMetadata(str_contains($headMeta, 'https://www.example.test/site/media/category/w%C3%A4rmepumpe.webp'), 'Open Graph image is not an absolute tenant media URL');
checkMetadata($metadata->absoluteUrl('https://foreign.example/image.jpg') === null, 'An external URL was accepted as a local canonical path');
checkMetadata($metadata->mediaUrl('images', '../secret.pdf') === null, 'A protected or traversing media path was accepted');
checkMetadata($metadata->mediaUrl('downloads', 'public.jpg') === null, 'A non-public media directory was accepted');

$organization = $metadata->renderOrganization();
checkMetadata(str_contains($organization, '"@type":"Organization"'), 'Organization structured data is missing');
checkMetadata(str_contains($organization, 'https:\/\/www.example.test\/site\/media\/header\/brand%20logo.png'), 'Organization logo does not use validated site media');
checkMetadata(!str_contains($organization, 'email') && !str_contains($organization, 'address'), 'Organization markup invents unavailable company data');
checkMetadata(str_contains($metadata->jsonLd(['value' => '</script><script>alert(1)</script>']), '<\/script>'), 'JSON-LD can terminate its script element');

resetMetadataPlaceholders();
$otherView = new SeoMetadataView(new DEEC_Site_Context([
	'title' => 'Other Site',
	'url' => 'https://other.example.test',
	'language' => 'en_US',
]), $root);
$otherView->SiteMetadata(['title' => 'Product', 'description' => '{contact}', 'path' => '/product']);
$otherMeta = $otherView->headMeta()->toString();
checkMetadata(str_contains($otherMeta, 'https://other.example.test/product'), 'A second website did not use its own configured URL');
checkMetadata(!str_contains($otherMeta, 'www.example.test'), 'Metadata leaked a different website URL');
checkMetadata(!str_contains($otherMeta, 'name="description"'), 'An empty or placeholder description element was generated');

$otherView->controller = 'item';
$otherView->categories = [
	1 => ['id' => 1, 'parentid' => 0, 'title' => 'Products'],
	2 => ['id' => 2, 'parentid' => 1, 'title' => 'Cooling'],
];
$otherView->category = $otherView->categories[2];
$otherView->item = ['id' => 7, 'title' => 'R290 Chiller'];
$otherView->slugPaths = [
	'category:1' => '/products',
	'category:2' => '/products/cooling',
	'item:7' => '/products/cooling/r290-chiller',
];
$breadcrumbs = new Zend_View_Helper_Breadcrumbs();
$breadcrumbs->setView($otherView);
$items = $breadcrumbs->getItems();
checkMetadata(array_column($items, 'title') === ['Home', 'Products', 'Cooling', 'R290 Chiller'], 'Nested product breadcrumbs have the wrong hierarchy');
$breadcrumbHtml = $breadcrumbs->Breadcrumbs();
checkMetadata(substr_count($breadcrumbHtml, 'class="breadcrumb-item') === 4, 'Visual breadcrumbs do not use the shared hierarchy');
checkMetadata(substr_count($breadcrumbHtml, '"@type":"ListItem"') === 4, 'BreadcrumbList does not use the visual breadcrumb data');
checkMetadata(str_contains($breadcrumbHtml, 'https:\/\/other.example.test\/products\/cooling\/r290-chiller'), 'BreadcrumbList contains a non-canonical product URL');
$otherView->slugPaths['category:1'] = null;
checkMetadata(!str_contains($breadcrumbs->Breadcrumbs(), '"@type":"BreadcrumbList"'), 'An incomplete breadcrumb hierarchy emits partial structured data');
$otherView->slugPaths['category:1'] = '/products';

$otherView->controller = 'page';
$otherView->pages = [
	['id' => 10, 'parentid' => 0, 'title' => 'Company'],
	['id' => 11, 'parentid' => 10, 'title' => 'Team'],
];
$otherView->page = ['id' => 12, 'parentid' => 11, 'title' => 'Engineering'];
$otherView->slugPaths += [
	'page:10' => '/company',
	'page:11' => '/company/team',
	'page:12' => '/company/team/engineering',
];
checkMetadata(array_column($breadcrumbs->getItems(), 'title') === ['Home', 'Company', 'Team', 'Engineering'], 'Nested CMS page breadcrumbs are incomplete');

resetMetadataPlaceholders();
$renderView = new SeoMetadataView($context, $root);
$renderView->shop = $context->getSite();
$renderView->canonicalPath = '/legacy';
$renderView->page = [
	'title' => 'Legacy page',
	'metatitle' => '',
	'metadescription' => '',
	'content' => '<h1>Stored heading</h1><p>Stored content</p>',
];
$renderView->pageblocks = [];
$legacyPage = $renderView->render('page/index.phtml');
checkMetadata(substr_count(strtolower($legacyPage), '<h1') === 1, 'A legacy CMS H1 receives a duplicate template H1');

$renderView->page['content'] = '<h1>First heading</h1><h1>Second heading</h1>';
$multipleLegacyH1 = $renderView->render('page/index.phtml');
checkMetadata(substr_count(strtolower($multipleLegacyH1), '<h1') === 2, 'Multiple meaningful stored H1 elements were changed without editorial approval');

$renderView->page['content'] = '<h1>&nbsp;<span> </span></h1><p>Content</p>';
$emptyLegacyH1 = $renderView->render('page/index.phtml');
checkMetadata(substr_count(strtolower($emptyLegacyH1), '<h1') === 1 && !str_contains($emptyLegacyH1, '&nbsp;'), 'An empty stored H1 suppresses the meaningful template H1');

$renderView->page['content'] = '<h1>Stored heading</h1>';
$renderView->pageblocks = [['type' => 'hero', 'data' => '{"title":"Hero heading"}']];
$heroAndLegacy = $renderView->render('page/index.phtml');
checkMetadata(substr_count(strtolower($heroAndLegacy), '<h1') === 1 && str_contains($heroAndLegacy, '<h2>Hero heading</h2>'), 'Hero and legacy H1 rendering creates a duplicate main heading');

resetMetadataPlaceholders();
$renderView = new SeoMetadataView($context, $root);
$renderView->shop = $context->getSite();
$renderView->canonicalPath = '/empty';
$renderView->page = ['title' => 'Empty page', 'metatitle' => '', 'metadescription' => '', 'content' => ''];
$renderView->pageblocks = [];
$emptyPage = $renderView->render('page/index.phtml');
checkMetadata(substr_count(strtolower($emptyPage), '<h1') === 1, 'A CMS page without content has no unique main heading');

resetMetadataPlaceholders();
$renderView = new SeoMetadataView($context, $root);
$renderView->shop = $context->getSite();
$renderView->page = ['title' => 'Home', 'metatitle' => '', 'metadescription' => '', 'content' => ''];
$renderView->pageblocks = [];
$renderView->categories = [];
$renderView->slideImages = [];
$homePage = $renderView->render('index/index.phtml');
checkMetadata(substr_count(strtolower($homePage), '<h1') === 1, 'The homepage does not render exactly one H1');

resetMetadataPlaceholders();
$renderView = new SeoMetadataView($context, $root);
$renderView->shop = $context->getSite();
$renderView->canonicalPath = '/products';
$renderView->category = [
	'id' => 1,
	'parentid' => 0,
	'title' => 'Products',
	'subtitle' => '',
	'metatitle' => '',
	'metadescription' => '',
	'header' => '',
	'shortdescription' => '',
	'description' => '',
	'footer' => '',
];
$renderView->categories = [1 => $renderView->category];
$renderView->images = ['categories' => [1 => []], 'items' => []];
$renderView->items = [];
$renderView->tagEntities = [];
$categoryPage = $renderView->render('category/index.phtml');
checkMetadata(substr_count(strtolower($categoryPage), '<h1') === 1, 'A category page does not render exactly one H1');
$renderView->category['description'] = '<h1>Stored category heading</h1>';
$renderView->categories[1] = $renderView->category;
$legacyCategory = $renderView->render('category/index.phtml');
checkMetadata(substr_count(strtolower($legacyCategory), '<h1') === 1, 'A legacy category H1 receives a duplicate template H1');

resetMetadataPlaceholders();
$renderView = new SeoMetadataView($context, $root);
$renderView->shop = $context->getSite();
$renderView->canonicalPath = '/products/pump';
$renderView->slugPaths = ['item:7' => '/products/pump'];
$renderView->item = [
	'id' => 7,
	'title' => 'Pump',
	'shopdescriptionshort' => '',
	'shopdescription' => '',
	'description' => 'Efficient pump',
	'sku' => 'P-7',
	'currency' => 'EUR',
	'manufacturerid' => 0,
	'downloads' => '',
];
$renderView->images = [];
$renderView->prices = [
	'raw' => 0,
	'rawtax' => 0,
	'rawspecialprice' => 0,
	'rawspecialpricetax' => 0,
	'formatted' => '0.00',
	'formattedtax' => '0.00',
	'formattedspecialprice' => '0.00',
	'formattedspecialpricetax' => '0.00',
];
$renderView->manufacturers = [];
$renderView->categoryImages = [];
$renderView->parentCategoryImages = [];
$renderView->attributeSets = [];
$renderView->optionSets = [];
$renderView->imagePath = '0/0/0';
$itemPage = $renderView->render('item/index.phtml');
checkMetadata(substr_count(strtolower($itemPage), '<h1') === 1, 'A product page does not render exactly one H1');
checkMetadata(!str_contains($itemPage, '"@type":"Offer"'), 'A product without a real price emits an Offer');
$renderView->item['description'] = '<h1>Stored product heading</h1>';
$legacyItem = $renderView->render('item/index.phtml');
checkMetadata(substr_count(strtolower($legacyItem), '<h1') === 1, 'A legacy product H1 receives a duplicate template H1');

resetMetadataPlaceholders();
$renderView = new SeoMetadataView($context, $root);
$renderView->shop = $context->getSite();
$renderView->canonicalPath = '/solutions';
$renderView->tag = ['id' => 3, 'title' => 'Solutions', 'description' => '', 'header' => '', 'footer' => ''];
$renderView->categories = [];
$renderView->tagEntities = [];
$tagPage = $renderView->render('tag/index.phtml');
checkMetadata(substr_count(strtolower($tagPage), '<h1') === 1, 'A tag page does not render exactly one H1');
$renderView->tag['description'] = '<h1>Stored tag heading</h1>';
$legacyTag = $renderView->render('tag/index.phtml');
checkMetadata(substr_count(strtolower($legacyTag), '<h1') === 1, 'A legacy tag H1 receives a duplicate template H1');

$heroH1 = $renderView->partial('page/blocks/hero.phtml', ['data' => ['title' => 'Hero'], 'headingLevel' => 1]);
$heroH2 = $renderView->partial('page/blocks/hero.phtml', ['data' => ['title' => 'Second hero'], 'headingLevel' => 2]);
checkMetadata(substr_count(strtolower($heroH1), '<h1') === 1 && !str_contains(strtolower($heroH1), '<h2'), 'The primary hero does not render as H1');
checkMetadata(substr_count(strtolower($heroH2), '<h2') === 1 && !str_contains(strtolower($heroH2), '<h1'), 'A secondary hero renders another H1');

$categorySource = file_get_contents($root . '/application/modules/shops/views/scripts/category/index.phtml');
$itemSource = file_get_contents($root . '/application/modules/shops/views/scripts/item/index.phtml');
checkMetadata(!str_contains($categorySource, "'@type' => 'Product'"), 'Category lists still emit Product structured data for every result');
checkMetadata(str_contains($itemSource, "if (\$productUrl !== null && \$price > 0") && str_contains($itemSource, "preg_match('/^[A-Z]{3}$/', \$currency)"), 'Product offers are not guarded by complete price, currency and URL data');
checkMetadata(str_contains($itemSource, "if (\$itemTitle !== '')"), 'Incomplete Product structured data can be emitted without a name');

$structure = file_get_contents($root . '/install/config/structure.sql');
$migration = file_get_contents($root . '/install/config/update-1.1.7-1.1.8.sql');
checkMetadata(!is_file($root . '/install/config/update-1.1.8-1.1.9.sql'), 'An unsupported migration version was created');
checkMetadata(str_contains($structure, 'CREATE TABLE IF NOT EXISTS `slugredirect`') && str_contains($migration, 'CREATE TABLE IF NOT EXISTS `slugredirect`'), 'The current redirect migration was lost');

echo 'PASS: ' . $checks . " SEO metadata, Open Graph, breadcrumb, structured data and heading cases\n";
