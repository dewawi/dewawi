<?php

require __DIR__.'/map-bootstrap.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('~^/erp/(css|js|library|images)/~', $path)) {
	$file = realpath($root.substr($path, 4));
	if (!$file || !str_starts_with($file, $root.'/') || !preg_match('/\.(css|js|png|gif|svg|woff2?)$/', $file, $extension)) { http_response_code(404); exit; }
	header('Content-Type: '.(['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'woff' => 'font/woff', 'woff2' => 'font/woff2'][$extension[1]]));
	readfile($file); exit;
}
$tenant = (int)($_GET['tenant'] ?? $_COOKIE['tenant'] ?? 1);
if (!in_array($tenant, [1, 2, 3], true)) $tenant = 1;
mapActor($tenant, empty($_GET['denied']), !empty($_GET['guest']));
if (isset($_GET['tenant'])) setcookie('tenant', (string)$tenant, 0, '/');
$request = new Zend_Controller_Request_Http();
$request->setBaseUrl('/erp');
$response = new Zend_Controller_Response_Http();
$front = Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
$front->setControllerDirectory(['default' => $root.'/application/controllers', 'contacts' => $root.'/application/modules/contacts/controllers']);
$front->getRouter()->route($request);
$action = $request->getActionName();
if ($request->getModuleName() !== 'contacts' || $request->getControllerName() !== 'contact' || !in_array($action, ['index', 'search', 'select', 'map', 'mapdata', 'view'], true)) { http_response_code(404); exit; }
Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->setExit(false);
$acl = new Application_Plugin_Acl();
$acl->setRequest($request)->setResponse($response);
$acl->routeShutdown($request);
if ($response->isRedirect() || $request->getModuleName() !== 'contacts') {
	$response->setBody('{"ok":false}')->sendResponse(); exit;
}
$view = new Zend_View(['scriptPath' => $root.'/application/views/scripts']);
$view->addScriptPath($root.'/application/modules/contacts/views/scripts');
$view->addHelperPath($root.'/application/views/helpers', 'Zend_View_Helper');
$view->language = 'de_DE';
$renderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
$renderer->setView($view);
Zend_Layout::startMvc(['layoutPath' => $root.'/application/layouts/scripts', 'layout' => 'layout']);
try {
	$controller = new Contacts_ContactController($request, $response);
	$controller->{$action.'Action'}();
	if (!$renderer->getNoRender()) {
		$html = $view->render('contact/'.($renderer->getScriptAction() ?: $action).'.phtml');
		if ($action === 'search' || $action === 'select') $response->setBody($html);
		else {
			Zend_Layout::getMvcInstance()->content = $html;
			$view->addScriptPath($root.'/application/layouts/scripts');
			$html = $view->render('layout.phtml');
			// Keep browser tests fully local while exercising the actual Leaflet and application scripts.
			$stub = '<script>var fixtureMap;var mapFactory=L.map;L.map=function(){return fixtureMap=mapFactory.apply(this,arguments);};L.tileLayer=function(url,options){var layer=new L.GridLayer(options);layer.createTile=function(){return document.createElement("div");};return layer;};</script>';
			$response->setBody(str_replace('</head>', $stub.'</head>', $html));
		}
	}
	$plugin = new Application_Plugin_ListResponse();
	$plugin->setRequest($request)->setResponse($response);
	$plugin->postDispatch($request);
} catch (Throwable $e) { error_log((string)$e); $response->setHttpResponseCode(500)->setBody('Map fixture failed'); }
$response->sendResponse();
