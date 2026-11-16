<?php

require __DIR__ . '/bootstrap.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(?:css|js|library/jQuery|library/Datejs|library/Leaflet|images)/#', $path)) {
	$file = realpath($root . $path);
	if (!$file || !str_starts_with($file, $root . '/') || !preg_match('/\.(css|js|png|gif)$/', $file, $ext)) { http_response_code(404); exit; }
	header('Content-Type: ' . (['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'gif' => 'image/gif'][$ext[1]]));
	readfile($file); exit;
}
if ($path === '/browser.html') { header('Content-Type: text/html'); readfile($work . '/browser.html'); exit; }
calendarActor((int)($_GET['actor'] ?? $_COOKIE['actor'] ?? 1), (int)($_GET['tenant'] ?? $_COOKIE['tenant'] ?? 1));
if (isset($_GET['actor'])) setcookie('actor', (string)(int)$_GET['actor'], 0, '/');
if (isset($_GET['tenant'])) setcookie('tenant', (string)(int)$_GET['tenant'], 0, '/');
if (!empty($_GET['guest'])) Zend_Auth::getInstance()->clearIdentity();
if (!preg_match('#^/calendar(?:/index)?(?:/(index|get|view|add|edit|delete|reminders|participants|respond|contacts|cancel|send))?(?:/id/(\d+))?/?$#', $path, $parts)) { http_response_code(404); exit; }
$action = ($parts[1] ?? '') ?: 'index';
$request = new Zend_Controller_Request_Http();
$request->setModuleName('calendar')->setControllerName('index')->setActionName($action)->setParams(['module' => 'calendar', 'controller' => 'index', 'action' => $action]);
if (!empty($parts[2])) $request->setParam('id', $parts[2]);
$response = new Zend_Controller_Response_Http();
$front = Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
$front->setControllerDirectory(['default' => $root . '/application/controllers', 'calendar' => $root . '/application/modules/calendar/controllers']);
$front->getRouter()->addDefaultRoutes();
Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->setExit(false);
$view = new Zend_View(['scriptPath' => $root . '/application/views/scripts']);
$view->addScriptPath($root . '/application/modules/calendar/views/scripts');
$view->addHelperPath($root . '/application/views/helpers', 'Zend_View_Helper');
$locale = $_GET['locale'] ?? $_COOKIE['locale'] ?? 'de_DE';
if (!in_array($locale, ['de_DE', 'en_US'], true)) $locale = 'de_DE';
if (isset($_GET['locale'])) setcookie('locale', $locale, 0, '/');
$tr = new DEEC_Translate($locale);
$tr->loadDir('default', $root . '/languages/' . $locale . '/default');
$tr->loadDir('calendar', $root . '/languages/' . $locale . '/calendar');
Zend_Registry::set('DEEC_Translate', $tr);
Zend_Registry::set('Zend_Locale', new Zend_Locale($locale));
$view->language = $locale;
$renderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
$renderer->setView($view);
Zend_Layout::startMvc(['layoutPath' => $root . '/application/layouts/scripts', 'layout' => 'layout']);
try {
	$controller = new Calendar_IndexController($request, $response);
	$controller->{$action . 'Action'}();
	if (!$response->isRedirect() && !$renderer->getNoRender()) {
		$script = $renderer->getScriptAction() ?: $action;
		$html = $view->render('index/' . $script . '.phtml');
		$layout = Zend_Layout::getMvcInstance();
		$layout->content = $html;
		$view->addScriptPath($root . '/application/layouts/scripts');
		$response->setBody($view->render('layout.phtml'));
	}
} catch (Throwable $e) { error_log((string)$e); $response->setHttpResponseCode($e->getCode() === 403 ? 403 : 500)->setBody('Fixture request failed'); }
$response->sendResponse();
