<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path;
});
require_once $root . '/application/modules/shops/models/ShoppingCart.php';
Zend_Session::setOptions(['save_path' => sys_get_temp_dir(), 'use_cookies' => false]);
Zend_Session::setId('cartfixture' . bin2hex(random_bytes(8)));
$mode = $argv[1] ?? 'normal';
$context = new DEEC_Site_Context(['id' => 41, 'clientid' => 7], [], $mode === 'disabled' ? ['cart'] : ['cart', 'checkout']);
Zend_Registry::set('SiteContext', $context);
$cart = new Shops_Model_ShoppingCart();
if ($mode !== 'empty') $cart->addItem(7, 'Wärmepumpe mit sehr langer Artikelbezeichnung <img src=x onerror=alert(1)>', 'P-7', 19.5, 1.25, 'Meter');
class CartFixtureView extends Zend_View {
	public function url($options = [], $name = null, $reset = false, $encode = true) { return '/site/' . str_replace('_', '/', (string)$name); }
	public function SlugUrl($type, $id) { return '/site/' . $type . '/' . $id; }
	public function PageByType($type) { return ['id' => 99]; }
	public function Messages() { return ''; }
}
$view = new CartFixtureView();
$view->setScriptPath($root . '/application/modules/shops/views/scripts');
$messages = require $root . '/languages/de_DE/shops/shops.php';
$view->translate()->setTranslator(new Zend_Translate(['adapter' => 'array', 'content' => $messages, 'locale' => 'de_DE']));
$view->cart = $cart;
$view->siteContext = $context;
$currency = new Zend_Currency('EUR', 'de_DE');
$items = $cart->getItems();
foreach ($items as &$item) {
	$item['total'] = $currency->toCurrency(round($item['price'] * $item['quantity'], 2));
	$item['price'] = $currency->toCurrency($item['price']);
}
unset($item);
$view->items = $items;
$view->total = $currency->toCurrency($cart->getTotalPrice());
echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/bootstrap.min.css"><link rel="stylesheet" href="/css/shop.css"></head><body><span id="cart-count">1,25</span>';
echo $view->render('cart/_state.phtml');
echo $view->render('cart/index.phtml');
echo '<script src="/assets/jquery.min.js"></script><script src="/js/shops.js"></script></body></html>';
Zend_Session::destroy();
