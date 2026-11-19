<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);
define('APPLICATION_ENV', 'development');
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { require_once $path; return; }
	$parts = explode('_', $class);
	if (count($parts) < 3) return;
	$module = strtolower(array_shift($parts));
	$path = $root . '/application/modules/' . $module . '/' . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
	if (is_file($path)) require_once $path;
});
$checks = 0;
function check(bool $value, string $message): void {
	global $checks;
	$checks++;
	if (!$value) throw new RuntimeException($message);
}
class AlbisTestCache extends Zend_Cache_Core {
	public array $entries = [];
	public int $now = 1000;
	public function load($id, $doNotTestCacheValidity = false, $doNotUnserialize = false) {
		return isset($this->entries[$id]) && $this->entries[$id]['expires'] > $this->now ? $this->entries[$id]['data'] : false;
	}
	public function save($data, $id = null, $tags = [], $specificLifetime = false, $priority = 8) {
		$this->entries[$id] = ['data' => $data, 'expires' => $this->now + $specificLifetime]; return true;
	}
	public function remove($id) { unset($this->entries[$id]); return true; }
}
class AlbisTestHttp extends Zend_Http_Client_Adapter_Test {
	public array $requests = [];
	public bool $fail = false;
	public function write($method, $uri, $http_ver = '1.1', $headers = [], $body = '') {
		$this->requests[] = ['method' => $method, 'url' => (string)$uri, 'headers' => $headers, 'body' => $body, 'config' => $this->config];
		if ($this->fail) throw new RuntimeException('Sensitive HTTP exception must not be logged');
		return parent::write($method, $uri, $http_ver, $headers, $body);
	}
}
class AlbisTestView extends Zend_View {
	public array $texts;
	public function __construct(string $locale) {
		global $root;
		parent::__construct();
		$this->texts = require $root . '/languages/' . $locale . '/shops/shops.php';
		$this->setScriptPath($root . '/application/modules/shops/views/scripts');
	}
	public function translate($messageId) { return $this->texts[$messageId] ?? $messageId; }
}
$site = ['id' => 41, 'clientid' => 7, 'activated' => 1, 'albisenabled' => 1, 'albisenvironment' => 'test', 'albisfeenet' => '75.00'];
$item = ['id' => 12, 'clientid' => 7, 'shopid' => 41, 'shopcatid' => 51, 'deleted' => 0, 'shopenabled' => 1, 'albiseligible' => 1, 'currency' => 'EUR', 'price' => '7000.0000', 'specialprice' => null, 'sku' => 'OVEN-12'];
$context = static fn(array $changes = [], array $features = ['catalog', 'inquiry']): DEEC_Site_Context => new DEEC_Site_Context(array_replace($site, $changes), [], $features);
$credentials = ['client_id' => 'fixture-client', 'client_secret' => 'fixture-secret', 'cache_key' => base64_encode(str_repeat('x', 32))];
$rates = [['laufzeit' => 36, 'rate' => 80, 'versicherung' => 30, 'schlusszahlung' => 250], ['laufzeit' => 60, 'rate' => 90, 'versicherung' => 5]];
$token = 'header.' . rtrim(strtr(base64_encode(json_encode(['exp' => time() + 900])), '+/', '-_'), '=') . '.signature';
$response = static fn($body, int $status = 200): string => "HTTP/1.1 $status Fixture\r\nContent-Type: application/json\r\n\r\n" . (is_string($body) ? $body : json_encode($body));
$setup = static function(?array $offered = null, ?array $groups = null, ?array $payments = null) use ($response, $token, $rates): AlbisTestHttp {
	$http = new AlbisTestHttp();
	$http->setResponse([
		$response(['access_token' => $token]), $response($groups ?? [['id' => 23]]),
		$response([['id' => 1, 'bezeichnung' => 'VA-Leasingvertrag']]), $response($payments ?? [['id' => 2, 'monate' => 1]]),
		$response($offered ?? ['raten' => $rates]),
	]);
	return $http;
};
foreach ([['4999.99', null], ['5000.00', null], ['5000.004', null], ['5000.01', 5000.01], ['7000', 7000.0]] as [$price, $expected]) {
	check(DEEC_Albis::eligiblePrice($context(), array_replace($item, ['price' => $price])) === $expected, 'Wrong strict net boundary: ' . $price);
}
foreach ([['specialprice' => '5000'], ['specialprice' => '-10'], ['specialprice' => 'invalid'], ['price' => 0], ['price' => null], ['currency' => 'USD'], ['albiseligible' => 0], ['shopenabled' => 0], ['deleted' => 1], ['shopid' => 42], ['shopcatid' => 0]] as $changes) {
	check(DEEC_Albis::eligiblePrice($context(), array_replace($item, $changes)) === null, 'Ineligible product accepted: ' . json_encode($changes));
}
check(DEEC_Albis::eligiblePrice($context(), array_replace($item, ['specialprice' => '6000'])) === 6000.0, 'Special price not used');
check(DEEC_Albis::eligiblePrice($context(), array_replace($item, ['specialprice' => '0.0000'])) === 7000.0, 'Zero special price suppressed base price');
foreach ([['albisenabled' => 0], ['activated' => 0], ['albisfeenet' => null], ['albisfeenet' => -1], ['albisthreshold' => -1], ['albisproductgroup' => 0], ['albiscontracttype' => 0], ['albispaymentmode' => 0], ['albisenvironment' => 'unknown']] as $changes) {
	check(DEEC_Albis::eligiblePrice($context($changes), $item) === null, 'Invalid shop configuration accepted');
}
check(DEEC_Albis::eligiblePrice($context(['albisfeenet' => 0]), $item) === 7000.0, 'Explicitly confirmed zero fee refused');
check(DEEC_Albis::eligiblePrice($context(['albisthreshold' => 8000]), $item) === null, 'Configured threshold ignored');
check(DEEC_Albis::eligiblePrice($context([], ['catalog']), $item) === null, 'Inquiry entitlement ignored');
check(DEEC_Albis::eligiblePrice($context([], ['inquiry']), $item) === null, 'Catalog entitlement ignored');
$productionCheck = '$root = ' . var_export($root, true) . '; set_include_path($root . "/library"); spl_autoload_register(function($class) {require str_replace("_", "/", $class) . ".php";}); define("APPLICATION_ENV", "production"); $site = ' . var_export($site, true) . '; $item = ' . var_export($item, true) . '; $ctx = new DEEC_Site_Context($site, [], ["catalog", "inquiry"]); exit(DEEC_Albis::eligiblePrice($ctx, $item) === null ? 0 : 1);';
$process = proc_open([PHP_BINARY, '-r', $productionCheck], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
foreach ($pipes as $pipe) { stream_get_contents($pipe); fclose($pipe); }
check(proc_close($process) === 0, 'Production can display test rates');
$cache = new AlbisTestCache(); $http = $setup();
$albis = new DEEC_Albis($context(), $cache, $http, $credentials);
$quote = $albis->quote($item);
check($quote !== null && count($quote['rates']) === 2, 'Valid ALBIS response rejected');
check($quote['rates'][0]['monthly'] === 95.0 && $quote['rates'][0]['term'] === 60, 'Advertised minimum excludes insurance');
check($quote['rates'][1]['final'] === 250.0 && $quote['fee'] === 75.0, 'Final payment or fee lost');
check(count($http->requests) === 5, 'Wrong authentication/master/calculation flow');
check(json_decode($http->requests[0]['body'], true) === array_intersect_key($credentials, array_flip(['client_id', 'client_secret'])), 'Authentication payload differs from specification');
check(json_decode($http->requests[4]['body'], true) === ['kaufpreis' => 7000, 'prodgrp' => 23, 'vertragsart' => 1, 'zahlweise' => 2, 'mietsz' => 0], 'Calculation payload differs from specification');
foreach ($http->requests as $request) {
	check(parse_url($request['url'], PHP_URL_HOST) === 'test-api.albis-leasing.de', 'Unexpected API environment: ' . $request['url']);
	check(!str_contains($request['url'], 'request_application'), 'Application endpoint used');
	check($request['config']['maxredirects'] === 0 && $request['config']['curloptions'][CURLOPT_SSL_VERIFYPEER] === true && $request['config']['curloptions'][CURLOPT_SSL_VERIFYHOST] === 2 && $request['config']['curloptions'][CURLOPT_TIMEOUT_MS] === 3000, 'TLS/timeouts/redirect protection missing');
}
$serialized = serialize($cache->entries);
check(!str_contains($serialized, $token) && !str_contains($serialized, $credentials['client_secret']) && !str_contains($serialized, $credentials['cache_key']), 'Credentials persisted in cache');
check($albis->quote($item) === $quote && count($http->requests) === 5, 'Quote cache missed');
$http->setResponse($response($rates));
check($albis->quote(array_replace($item, ['specialprice' => '6500']))['price'] === 6500.0 && count($http->requests) === 6, 'Price change reused stale quote');
check(in_array('Authorization: Bearer ' . $token, $http->requests[5]['headers'], true), 'Encrypted token not reused');
$http->setResponse($response($rates));
check((new DEEC_Albis($context(['albisfeenet' => '100']), $cache, $http, $credentials))->quote($item)['fee'] === 100.0 && count($http->requests) === 7, 'Fee change reused stale quote');
$cache->now += 901;
$http = new AlbisTestHttp(); $http->setResponse([$response(['access_token' => $token]), $response($rates)]);
check((new DEEC_Albis($context(), $cache, $http, $credentials))->quote($item) !== null && count($http->requests) === 2, 'Expired quote/token not renewed or master data cache missed');
foreach ([['clientid' => 8], ['id' => 42], ['albisenvironment' => 'live']] as $changes) {
	$http = $setup(); $altered = array_replace($item, ['shopid' => $changes['id'] ?? 41, 'clientid' => $changes['clientid'] ?? 7]);
	check((new DEEC_Albis($context($changes), $cache, $http, $credentials))->quote($altered) !== null && count($http->requests) === 5, 'Tenant/shop/environment caches mixed');
}
$http = $setup();
check((new DEEC_Albis($context(), $cache, $http, array_replace($credentials, ['client_secret' => 'rotated-fixture-secret'])))->quote($item) !== null && count($http->requests) === 5, 'Credential rotation reused cached authorization');
foreach ([['albisproductgroup' => 99], ['albiscontracttype' => 99], ['albispaymentmode' => 99]] as $changes) {
	$http = $setup(); $separate = new AlbisTestCache(); $service = new DEEC_Albis($context($changes), $separate, $http, $credentials);
	check($service->quote($item) === null && count($http->requests) === 4, 'Unapproved master data used for calculation');
	check($service->quote($item) === null && count($http->requests) === 4, 'Unsupported configuration not cached');
}
foreach ([[], [['laufzeit' => 60, 'rate' => 50]], [['laufzeit' => 0, 'rate' => 10, 'versicherung' => 2]], [['laufzeit' => 60, 'rate' => -1, 'versicherung' => 2]], [['laufzeit' => 60, 'rate' => 1, 'versicherung' => -1]], [['laufzeit' => 60, 'rate' => 1, 'versicherung' => 2, 'schlusszahlung' => null]], [$rates[0], $rates[0]]] as $invalid) {
	$http = $setup(['raten' => $invalid]); $separate = new AlbisTestCache(); $service = new DEEC_Albis($context(), $separate, $http, $credentials);
	check($service->quote($item) === null, 'Empty or incomplete rate displayed');
	check($service->quote($item) === null && count($http->requests) === 5, 'Missing rates not cached');
}
$http = $setup(null, null, [['id' => 2, 'monate' => 0]]);
check((new DEEC_Albis($context(), new AlbisTestCache(), $http, $credentials))->quote($item) === null && count($http->requests) === 4, 'Invalid payment interval calculated');
foreach ([401, 403, 422, 429, 500, 302] as $status) {
	$http = new AlbisTestHttp(); $http->setResponse($response(['detail' => 'private'], $status)); $separate = new AlbisTestCache();
	$service = new DEEC_Albis($context(), $separate, $http, $credentials);
	check($service->quote($item) === null && $service->quote(array_replace($item, ['price' => '7500'])) === null && count($http->requests) === 1, 'HTTP failure/cooldown not handled: ' . $status);
}
foreach (['not-json', 'null', '{"access_token":"bad token"}', '{"access_token":"a.' . base64_encode('{"exp":1}') . '.b"}'] as $body) {
	$http = new AlbisTestHttp(); $http->setResponse($response($body));
	check((new DEEC_Albis($context(), new AlbisTestCache(), $http, $credentials))->quote($item) === null, 'Invalid authentication accepted');
}
$http = $setup(); $http->fail = true;
check((new DEEC_Albis($context(), new AlbisTestCache(), $http, $credentials))->quote($item) === null, 'Network error escaped');
$http = $setup();
check((new DEEC_Albis($context(), new AlbisTestCache(), $http, []))->quote($item) === null && !$http->requests, 'Missing credentials contacted API');
check((new DEEC_Albis($context(), new AlbisTestCache(), $http, array_replace($credentials, ['cache_key' => 'invalid'])))->quote($item) === null && !$http->requests, 'Invalid encryption key contacted API');
$directory = sys_get_temp_dir() . '/dewawi-albis-cache-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
try {
	$fileCache = Zend_Cache::factory('Core', 'File', ['automatic_serialization' => true], ['cache_dir' => $directory, 'cache_file_perm' => 0600]);
	$fileHttp = $setup();
	$fileService = new DEEC_Albis($context(), $fileCache, $fileHttp, $credentials);
	check($fileService->quote($item) !== null, 'Real Zend file cache rejects quote');
	$anotherService = new DEEC_Albis($context(), $fileCache, $fileHttp, $credentials);
	check($anotherService->quote($item) !== null && count($fileHttp->requests) === 5, 'Real Zend cache does not share quote across requests');
	foreach (glob($directory . '/*') as $file) {
		$contents = file_get_contents($file);
		check(!str_contains($contents, $token) && !str_contains($contents, $credentials['client_secret']), 'File cache contains plaintext credentials');
	}
} finally {
	foreach (glob($directory . '/*') as $file) unlink($file);
	rmdir($directory);
}
foreach (['de_DE', 'en_US'] as $locale) {
	Zend_Registry::set('Zend_Locale', new Zend_Locale($locale));
	$view = new AlbisTestView($locale); $view->quote = $quote; $view->item = array_replace($item, ['sku' => '<script>alert(1)</script>']);
	$html = $view->render('item/_leasing.phtml');
	check(!str_contains($html, 'SHOPS_ALBIS_'), 'Missing translated ALBIS text: ' . $locale);
	check(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'ALBIS view failed to escape data');
	check(substr_count($html, '<option value=') === 2 && str_contains($html, 'value="60"'), 'Calculator invented terms');
	check(str_contains($html, '75') && str_contains($html, '250') && str_contains($html, '95'), 'Costs missing from UI');
	if ($locale === 'de_DE' && in_array('--fixture', $argv, true)) file_put_contents('/tmp/dewawi-albis-fixture.html', $html);
}
$admin = new Admin_Form_Shop();
check($admin->getValue('albisenabled') == 0 && $admin->getValue('albisfeenet') === null, 'Admin defaults enable unconfirmed advertising');
check($admin->isValidPartial(['albisenvironment' => 'evil']) === false && $admin->isValidPartial(['albisproductgroup' => -1]) === false, 'Admin accepts invalid API configuration');
check((new Items_Form_Item())->getValue('albiseligible') == 0, 'Item defaults enable leasing');
check($admin->isValidPartial(['albisfeenet' => '0,00']), 'Explicit zero fee fails validation');
check($admin->getFilteredValuesPartial(['albisfeenet' => '0,00'])['albisfeenet'] === 0.0, 'Confirmed zero fee lost during auto-save');
check($admin->isValidPartial(['albisfeenet' => '-1,00']) === false && $admin->isValidPartial(['albisthreshold' => '-1']) === false, 'Negative ALBIS money accepted');
check(DEEC_Filter::applyAll(['price' => '0'], ['price' => ['type' => 'decimal']])['price'] === null, 'Existing decimal zero behavior changed');
check(DEEC_Albis::eligiblePrice($context(), array_replace($item, ['clientid' => 8])) === null, 'Foreign-tenant item accepted by calculator');


// Exercise the actual public models and controller with tenant-scoped in-memory database rows.
class AlbisTestDb extends Zend_Db_Adapter_Pdo_Mysql {
	public array $rows = [];
	protected function _connect() {}
	protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
	public function describeTable($tableName, $schemaName = null) {
		$metadata = [];
		foreach (array_keys($this->rows[$tableName][0]) as $column) $metadata[$column] = ['COLUMN_NAME' => $column, 'DATA_TYPE' => 'varchar', 'PRIMARY' => $column === 'id', 'PRIMARY_POSITION' => 1, 'IDENTITY' => $column === 'id'];
		return $metadata;
	}
	public function query($sql, $bind = []) {
		if (!$sql instanceof Zend_Db_Table_Select || $bind) throw new RuntimeException('Unexpected database access');
		$sql->assemble(); $from = $sql->getPart(Zend_Db_Select::FROM); $table = reset($from)['tableName'];
		$rows = array_filter($this->rows[$table], static function(array $row) use ($sql): bool {
			foreach ($sql->getPart(Zend_Db_Select::WHERE) as $where) {
				if (!preg_match('/(?:`?([a-z]+)`?) = (.+)/', trim($where, '()'), $match)) throw new RuntimeException('Unknown fixture predicate');
				if ((string)($row[$match[1]] ?? '') !== trim($match[2], " '")) return false;
			}
			return true;
		});
		return new class(array_values($rows)) {
			public function __construct(private array $rows) {}
			public function fetchAll($mode) { return $this->rows; }
		};
	}
}
require_once $root . '/application/modules/shops/controllers/Action.php';
require_once $root . '/application/modules/shops/controllers/ItemController.php';
class AlbisTestController extends Shops_ItemController {
	public function init() {}
	public function setContext(DEEC_Site_Context $context): void { $this->_siteContext = $context; $this->_site = $context->getSite(); }
}
$db = new AlbisTestDb(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table_Abstract::setDefaultAdapter($db);
$db->rows['item'] = [$item];
$db->rows['category'] = [['id' => 51, 'clientid' => 7, 'shopid' => 41, 'activated' => 1, 'deleted' => 0, 'type' => 'shop']];
$cache = new AlbisTestCache(); $http = $setup();
$expected = (new DEEC_Albis($context(), $cache, $http, $credentials))->quote($item);
foreach (['CLIENT_ID' => 'client_id', 'CLIENT_SECRET' => 'client_secret', 'CACHE_KEY' => 'cache_key'] as $env => $field) putenv('DEWAWI_ALBIS_7_41_TEST_' . $env . '=' . $credentials[$field]);
Zend_Registry::set('Zend_Cache', $cache);
Zend_Layout::startMvc();
Zend_Controller_Front::getInstance()->addControllerDirectory($root . '/application/modules/shops/controllers', 'shops');
$run = static function(DEEC_Site_Context $ctx, string $method = 'GET', int $id = 12): array {
	Zend_Registry::set('SiteContext', $ctx);
	$_SERVER['REQUEST_METHOD'] = $method;
	$request = new Zend_Controller_Request_Http(); $request->setModuleName('shops')->setControllerName('item')->setActionName('leasing')->setParams(['id' => $id, 'kaufpreis' => 1, 'prodgrp' => 999]);
	$response = new Zend_Controller_Response_Http(); Zend_Controller_Front::getInstance()->setResponse($response);
	$controller = new AlbisTestController($request, $response); $controller->setContext($ctx); $controller->view = new AlbisTestView('de_DE');
	$controller->getHelper('json')->suppressExit = true;
	$controller->leasingAction();
	return [json_decode($response->getBody(), true), $response];
};
[$result, $responseObject] = $run($context());
check(!empty($result['html']) && str_contains($result['html'], '95'), 'Public endpoint did not use server-side cached price');
check(count($http->requests) === 5, 'Endpoint contacted API instead of using cache');
$headers = $responseObject->getHeaders();
check((bool)array_filter($headers, static fn($h) => strtolower($h['name']) === 'cache-control' && $h['value'] === 'private, no-store'), 'Endpoint response can be cached publicly');
foreach (['POST', 'HEAD', 'PUT'] as $method) check($run($context(), $method)[0]['html'] === '', 'Non-GET request obtained leasing quote');
check($run($context(), 'GET', 99)[0]['html'] === '', 'Unknown product obtained quote');
foreach ([['clientid' => 8], ['id' => 42], ['albisenabled' => 0]] as $changes) check($run($context($changes))[0]['html'] === '', 'Cross-tenant/shop or disabled endpoint leaked quote');
foreach (['shopenabled', 'albiseligible'] as $field) {
	$db->rows['item'][0][$field] = 0;
	check($run($context())[0]['html'] === '', 'Unpublished/ineligible item leaked quote');
	$db->rows['item'][0][$field] = 1;
}
foreach (['activated' => 0, 'deleted' => 1, 'shopid' => 42, 'clientid' => 8] as $field => $value) {
	$original = $db->rows['category'][0][$field]; $db->rows['category'][0][$field] = $value;
	check($run($context())[0]['html'] === '', 'Unavailable category leaked quote');
	$db->rows['category'][0][$field] = $original;
}
$router = new Zend_Controller_Router_Rewrite();
$register = new ReflectionMethod(DEEC_Site_Router::class, 'registerBaseRoutes'); $register->invoke(new DEEC_Site_Router(), $router, $context());
check($router->getRoute('product_leasing')->match('product/12/leasing')['id'] === '12', 'Leasing route does not resolve');
check($router->getRoute('product_leasing')->match('product/wrong/leasing') === false, 'Leasing route accepts nonnumeric IDs');
check(DEEC_Site_Router::isReservedPath('product/12/leasing'), 'Leasing endpoint can be shadowed by public slug');
echo "PASS: $checks ALBIS checks including public controller, route and tenant/category isolation\n";
