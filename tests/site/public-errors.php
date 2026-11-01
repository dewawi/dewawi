<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/site/public-errors.php (no database required).

error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path;
});

require_once $root . '/application/modules/shops/controllers/Action.php';

Zend_Controller_Front::getInstance()->setControllerDirectory(['shops' => $root . '/application/modules/shops/controllers']);
Zend_Controller_Action_HelperBroker::addPath($root . '/library/Zend/Layout/Controller/Action/Helper', 'Zend_Layout_Controller_Action_Helper');

$checks = 0;

function checkPublicError(bool $condition, string $message): void
{
	global $checks;
	$checks++;
	if (!$condition) throw new RuntimeException($message);
}

class PublicErrorTestController extends Shops_Controller_Action
{
	public function init()
	{
		$this->view = new Zend_View();
	}

	protected function initSiteLayout(): void
	{
	}

	public function showError(int $status): void
	{
		$this->renderSiteError($status);
	}

	public function renderScript($script, $name = null)
	{
		$this->getResponse()->appendBody('rendered-public-error');
	}
}

function publicErrorController(string $method = 'GET', ?string $accept = null): array
{
	$request = new Zend_Controller_Request_HttpTestCase();
	$request->setModuleName('shops')->setControllerName('page')->setActionName('index')->setMethod($method);
	if ($accept !== null) $request->setHeader('Accept', $accept);
	$response = new Zend_Controller_Response_HttpTestCase();

	return [new PublicErrorTestController($request, $response), $response];
}

[$controller, $response] = publicErrorController();
$controller->view->headLink(['rel' => 'canonical', 'href' => 'https://example.test/missing']);
$controller->showError(404);
checkPublicError($response->getHttpResponseCode() === 404, 'HTML missing page did not retain HTTP 404');
checkPublicError($response->getBody() === 'rendered-public-error', 'HTML public error was not rendered');
checkPublicError(!str_contains((string)$controller->view->headLink(), 'canonical'), 'Public 404 retained a canonical URL');
checkPublicError(str_contains((string)$controller->view->headMeta(), 'noindex, follow'), 'Public 404 is missing noindex metadata');

[$controller, $response] = publicErrorController('HEAD');
$controller->showError(410);
checkPublicError($response->getHttpResponseCode() === 410, 'HEAD removed path did not return HTTP 410');
checkPublicError($response->getBody() === '', 'HEAD public error returned a response body');

[$controller, $response] = publicErrorController('GET', 'application/json');
$controller->showError(410);
$json = json_decode($response->getBody(), true);
$contentType = array_values(array_filter($response->getHeaders(), static fn(array $header): bool => $header['name'] === 'Content-Type'));
checkPublicError($response->getHttpResponseCode() === 410, 'JSON removed path did not return HTTP 410');
checkPublicError($contentType && str_starts_with((string)$contentType[0]['value'], 'application/json'), 'JSON public error has the wrong content type');
checkPublicError(($json['status'] ?? '') === 'error' && is_string($json['message'] ?? null), 'JSON public error has an unsafe or incomplete body');

try {
	$controller->showError(500);
	checkPublicError(false, 'Public renderer accepted an internal HTTP 500');
} catch (InvalidArgumentException $e) {
	checkPublicError(true, '');
}

echo 'PASS: ' . $checks . " public HTML, JSON, canonical and HEAD error response cases\n";
