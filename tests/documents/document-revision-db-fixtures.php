<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$revisionChecks = $checks;
$pdo->exec('UPDATE user SET admin = 1 WHERE id = 42');
$rights = ['userid' => 42, 'clientid' => 7];
foreach (DEEC_DocumentArchive::documents() as $module => $types) $rights[$module] = json_encode(array_fill_keys($types, ['view', 'edit', 'add']));
foreach (['sales', 'purchases'] as $module) $pdo->prepare('UPDATE permission SET `' . $module . '` = ? WHERE userid = 42')->execute([$rights[$module]]);
if (is_file($blockedDirectory) && file_get_contents($blockedDirectory) === 'directory collision canary') unlink($blockedDirectory);
$db = recoveryBoot(migrationConfig($work . '/root.ini', 'production') + ['unix_socket' => $socket], $base, 7, 42);
$service = new Sales_Service_DocumentRevisionService();
foreach (['quote', 'salesorder'] as $type) {
	$source = $pdo->query('SELECT * FROM `' . $type . '` WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
	$original = $archive->inspect($archive->path(7, 'sales', $type, 1), 'pdf');
	$key = hash('sha256', 'synthetic-revision-' . $type);
	$newId = $service->create(1, $type, 'Synthetic description revision', $key);
	integrityCheck($service->create(1, $type, 'Synthetic description revision', $key) === $newId, 'Revision retry created another draft');
	$draft = $pdo->query('SELECT * FROM `' . $type . '` WHERE id = ' . $newId)->fetch(PDO::FETCH_ASSOC);
	integrityCheck(!$draft[$type . 'id'] && !$draft['completed'] && !$draft['filename'] && $draft['title'] === $source['title'], 'Revision did not create a normal unnumbered draft');
	$positions = $pdo->query('SELECT * FROM `' . $type . 'pos` WHERE parentid = ' . $newId)->fetchAll(PDO::FETCH_ASSOC);
	integrityCheck(count($positions) === 1 && $positions[0]['price'] === '100.0000', 'Revision lost saved position amounts');
	$pdo->prepare('UPDATE `' . $type . 'pos` SET price = 200, quantity = 2, title = ? WHERE id = ?')->execute(['Synthetic revised position', $positions[0]['id']]);
	$before = $pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
	$failed = integrityIssue('sales', $type, $newId, 'pdf');
	integrityCheck(!$failed['ok'] && !$pdo->query('SELECT completed FROM `' . $type . '` WHERE id = ' . $newId)->fetchColumn() && $before === $pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'Failed revision PDF retained issued state or archive changes');
	$user = Zend_Registry::get('User'); Zend_Registry::set('User', array_replace($user, ['admin' => 0]));
	try { (new Sales_Service_DocumentFinalizeService())->finalize($draft, $type); throw new LogicException('Non-admin approved a revision'); }
	catch (RuntimeException $error) { integrityCheck($error->getMessage() === 'DOCUMENT_CHANGES_ADMIN_REQUIRED', 'Unexpected revision approval denial'); }
	Zend_Registry::set('User', $user);
	$workers = [integrityStart('sales', $type, $newId), integrityStart('sales', $type, $newId)];
	foreach ($workers as $worker) { $result = integrityFinish($worker); integrityCheck($result['ok'], 'Concurrent revision finalization failed: ' . json_encode($result)); }
	$issued = $pdo->query('SELECT * FROM `' . $type . '` WHERE id = ' . $newId)->fetch(PDO::FETCH_ASSOC);
	integrityCheck($issued[$type . 'id'] !== $source[$type . 'id'] && $issued['total'] === '476.0000', 'Revision number or recalculated new amounts incorrect');
	integrityCheck($pdo->query('SELECT * FROM `' . $type . '` WHERE id = 1')->fetch(PDO::FETCH_ASSOC) === $source && $archive->inspect($original['path'], 'pdf') === $original, 'Revision changed previous finalized document or PDF');
	$history = $service->history(1, $type);
	integrityCheck(count($history) === 1 && (int)$history[0]['sequence'] === 2 && $history[0]['reason'] === 'Synthetic description revision' && (int)$history[0]['documentid'] === $newId, 'Revision history missing explicit chain');
	$next = $service->create($newId, $type, 'Synthetic successive revision', hash('sha256', 'next-' . $type));
	$chain = $service->history($next, $type);
	integrityCheck(count($chain) === 2 && (int)$chain[1]['sequence'] === 3 && (int)$chain[1]['sourcedocumentid'] === $newId, 'Successive revision chain lost its base');
}
foreach ([['quote', 999999, 'valid'], ['quote', 1, ''], ['invoice', 1, 'invalid type']] as [$type, $id, $reason]) {
	try { $service->create($id, $type, $reason, bin2hex(random_bytes(32))); throw new LogicException('Invalid revision accepted'); }
	catch (RuntimeException $error) { integrityCheck(true, 'Invalid revision rejected'); }
}
integrityDraft('quote', 301);
integritySeed('quoteposset', ['id' => 777, 'clientid' => 7, 'parentid' => 301, 'title' => 'Synthetic saved section', 'ordering' => 1]);
$pdo->exec('UPDATE quotepos SET possetid = 777 WHERE id = 301');
integritySeed('quotepos', ['id' => 1301, 'clientid' => 7, 'parentid' => 301, 'masterid' => 301, 'possetid' => 777, 'title' => 'Synthetic child', 'quantity' => 1, 'price' => 10, 'taxrate' => 19, 'currency' => 'EUR', 'uom' => 'pcs']);
integritySeed('pricerulepos', ['clientid' => 7, 'module' => 'sales', 'controller' => 'quotepos', 'parentid' => 301, 'action' => 'bypercent', 'amount' => 5]);
integrityCheck(integrityIssue('sales', 'quote', 301)['ok'], 'Grouped source quote could not be finalized');
$grouped = $service->create(301, 'quote', 'Synthetic grouped revision', hash('sha256', 'grouped'));
$sets = $pdo->query('SELECT * FROM quoteposset WHERE parentid = ' . $grouped)->fetchAll(PDO::FETCH_ASSOC);
$lines = $pdo->query('SELECT * FROM quotepos WHERE parentid = ' . $grouped . ' ORDER BY masterid, id')->fetchAll(PDO::FETCH_ASSOC);
integrityCheck(count($sets) === 1 && $sets[0]['title'] === 'Synthetic saved section' && (int)$lines[1]['masterid'] === (int)$lines[0]['id'] && (int)$lines[0]['possetid'] === (int)$sets[0]['id'], 'Revision lost group text or child/master/set relationships');
integrityCheck((int)$pdo->query('SELECT COUNT(*) FROM pricerulepos WHERE parentid = ' . (int)$lines[0]['id'] . " AND controller = 'quotepos' AND clientid = 7")->fetchColumn() === 1, 'Revision lost saved price rules');
$oldUser = Zend_Registry::get('User'); $oldClient = Zend_Registry::get('Client');
Zend_Registry::set('User', array_replace($oldUser, ['clientid' => 8])); Zend_Registry::set('Client', ['id' => 8, 'modules' => ['sales' => 8, 'contacts' => 8]]);
try { $service->create(1, 'quote', 'Foreign revision', bin2hex(random_bytes(32))); throw new LogicException('Foreign tenant created a revision'); }
catch (RuntimeException $error) { integrityCheck(true, 'Foreign revision rejected'); }
Zend_Registry::set('User', $oldUser); Zend_Registry::set('Client', $oldClient);
Zend_Session::$_unitTestEnabled = true;
Zend_Controller_Front::getInstance()->getRouter()->addRoute('default', new Zend_Controller_Router_Route_Module(['module' => 'default', 'controller' => 'index', 'action' => 'index']));
foreach (['de_DE', 'en_US'] as $locale) {
	$translator = new DEEC_Translate($locale); $translator->loadDir('default', $root . '/languages/' . $locale . '/default'); Zend_Registry::set('DEEC_Translate', $translator);
	$view = new Zend_View(); $view->addHelperPath($root . '/application/views/helpers', 'Zend_View_Helper'); $view->addScriptPath($root . '/application/views/scripts');
	$html = $view->DocumentChanges('quote', ['id' => 1]);
	integrityCheck(str_contains($html, 'data-autosave="false"') && str_contains($html, 'required maxlength="2000"') && str_contains($html, 'name="csrf_token"') && !str_contains($html, 'DOCUMENT_CHANGES_'), 'Revision UI lacks explicit reason, CSRF or translations');
	file_put_contents($work . '/revision-panel-' . $locale . '.html', '<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="file://' . $root . '/css/ui.css"><body class="edit">' . $html . '<script src="file://' . $root . '/library/jQuery/jquery-1.12.4.min.js"></script><script src="file://' . $root . '/library/jQuery/jquery.cookie.js"></script><script>var language="en",module="sales",controller="quote",action="view",id=1,baseUrl="/fixture",cookiePath="/fixture"; var fixtureErrors=[]; window.addEventListener("error",function(e){fixtureErrors.push(e.message);}); $.fn.datepicker=function(){return this;}; function autosize(){}</script><script src="file://' . $root . '/js/global.js"></script></body></html>');
}
class RevisionRequestFixture extends Zend_Controller_Request_Http { public string $method = 'GET'; public function getMethod() { return $this->method; } }
class RevisionControllerFixture extends DEEC_Controller_DocumentAction {
	public function init() { $this->_user = Zend_Registry::get('User'); $this->view = new Zend_View(); $this->view->addHelperPath(dirname(__DIR__, 2) . '/application/views/helpers', 'Zend_View_Helper'); }
	protected function disableView(): void {}
}
Zend_Controller_Front::getInstance()->setControllerDirectory(['default' => $root . '/application/controllers', 'sales' => $root . '/application/modules/sales/controllers']);
foreach (['GET' => 405, 'POST' => 409] as $method => $status) {
	$request = new RevisionRequestFixture(); $request->method = $method; $request->setModuleName('sales')->setControllerName('quote')->setActionName('revise')->setParam('id', 1)->setPost(['csrf_token' => 'invalid', 'reason' => 'Invalid request', 'request_key' => bin2hex(random_bytes(32))]);
	$response = new Zend_Controller_Response_Http(); $response->headersSentThrowsException = false; $count = $pdo->query('SELECT COUNT(*) FROM documentrevision')->fetchColumn();
	(new RevisionControllerFixture($request, $response))->reviseAction();
	integrityCheck($response->getHttpResponseCode() === $status && $pdo->query('SELECT COUNT(*) FROM documentrevision')->fetchColumn() === $count, 'Revision accepted GET or invalid CSRF');
}
$db->closeConnection();
echo 'PASS: ' . ($checks - $revisionChecks) . " commercial revision checks; quote/order, new numbers, editable positions, immutable originals, failed writes, concurrent administrator approval and successive history\n";
