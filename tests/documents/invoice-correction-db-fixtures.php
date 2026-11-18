<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$correctionStart = $checks;
$schema = file_get_contents($root . '/install/config/structure.sql');
$migration = file_get_contents($root . '/install/config/update-1.1.7-1.1.8.sql');
$pdo->exec('CREATE DATABASE integrity_changes_migration'); $pdo->exec('USE integrity_changes_migration');
$pdo->exec('CREATE TABLE invoice LIKE integrity_fixture.invoice');
preg_match('/CREATE TABLE IF NOT EXISTS `emailmessageattachment` .*?ENGINE=InnoDB DEFAULT CHARSET=utf8;/s', $schema, $mailSchema);
$oldMailSchema = str_replace(["  `invoicecorrectionid` int(11) DEFAULT NULL,\n", ",\n  KEY `invoicecorrectionid` (`invoicecorrectionid`)"], '', $mailSchema[0]);
$pdo->exec($oldMailSchema);
$pdo->exec(substr($migration, strpos($migration, '-- Explicit commercial revision links;')));
foreach (['documentrevision', 'invoicecorrection', 'emailmessageattachment'] as $table) {
	$freshDefinition = $pdo->query('SHOW CREATE TABLE integrity_fixture.`' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
	$migratedDefinition = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
	integrityCheck(preg_replace('/ AUTO_INCREMENT=\d+/', '', $freshDefinition) === preg_replace('/ AUTO_INCREMENT=\d+/', '', $migratedDefinition), 'Applied migration differs from fresh schema: ' . $table);
}
$pdo->exec('USE integrity_fixture');
$pdo->exec("UPDATE permission SET contacts = '{\"contact\":[\"view\",\"add\"]}' WHERE userid = 42");
function correctionReject(callable $callback, string $expected): void
{
	try { $callback(); } catch (RuntimeException $error) { integrityCheck($error->getMessage() === $expected, 'Unexpected correction refusal: ' . $error->getMessage()); return; }
	throw new RuntimeException('Correction did not reject ' . $expected);
}
function correctionWorker(array $row, array $approval, string $failure = ''): array
{
	global $socket, $root;
	$process = proc_open([PHP_BINARY, '-d', 'error_reporting=8191', $root . '/tests/documents/invoice-correction-worker.php', $socket, (string)$row['invoiceid'], (string)$row['id'], (string)$row['revision'], $row['record_sha256'], json_encode($approval, JSON_THROW_ON_ERROR), $failure], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	return [$process, $pipes];
}
function correctionFinish(array $worker): array
{
	[$process, $pipes] = $worker; $out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
	integrityCheck(proc_close($process) === 0 && $error === '', 'Correction worker failed: ' . $out . $error);
	return json_decode($out, true, 512, JSON_THROW_ON_ERROR);
}
function correctionText(string $bytes): array
{
	global $work;
	$path = $work . '/correction-text.pdf'; file_put_contents($path, $bytes);
	$process = proc_open(['pdftotext', '-raw', $path, '-'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); integrityCheck(proc_close($process) === 0, 'Correction PDF unreadable: ' . $error);
	return array_values(array_filter(explode("\f", $out), static fn(string $page): bool => trim($page) !== ''));
}

$pdo->exec("UPDATE client SET company = 'Synthetic correction issuer', address = 'Synthetic issuer street', postcode = '10115', city = 'Berlin', country = 'DE' WHERE id = 7");
integrityDraft('invoice', 500);
$pdo->exec("UPDATE invoice SET billingpostcode = '10115', billingname1 = 'Synthetic recipent', subject = 'Synthetic subject' WHERE id = 500");
integrityCheck(integrityIssue('sales', 'invoice', 500)['ok'], 'Correction source could not be finalized');
$db = recoveryBoot(migrationConfig($work . '/root.ini', 'production') + ['unix_socket' => $socket], $base, 7, 42);
$service = new Sales_Service_InvoiceCorrectionService();
$sourceInvoice = $pdo->query('SELECT * FROM invoice WHERE id = 500')->fetch(PDO::FETCH_ASSOC); $sourcePositions = $pdo->query('SELECT * FROM invoicepos WHERE parentid = 500')->fetchAll(PDO::FETCH_ASSOC);
$sourceCounter = $pdo->query('SELECT * FROM increment WHERE clientid = 7')->fetch(PDO::FETCH_ASSOC);
$sourceRegistrations = $pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$originalPath = $archive->path(7, 'sales', 'invoice', 500); $originalState = [hash_file('sha256', $originalPath), filemtime($originalPath), filesize($originalPath)];
$approval = ['format_scope' => 'transition_2026', 'format_reason' => 'Synthetic reviewed transition and no public procurement obligation', 'original_reference' => 'Synthetic independent issued paper comparison', 'original_confirmed' => '1', 'identity_confirmed' => '1', 'nonfinancial_confirmed' => '1', 'structured_not_required' => '1', 'recipient_consent' => '1'];
$id = $service->create(500, 'Synthetic recipient typo', hash('sha256', 'first-correction'));
integrityCheck($service->create(500, 'Synthetic recipient typo', hash('sha256', 'first-correction')) === $id, 'Correction retry created a duplicate');
$draft = $service->get(500, $id);
correctionReject(fn() => $service->approve(500, $id, (int)$draft['revision'], $draft['record_sha256'], $approval), 'CORRECTION_NO_CHANGES');
$service->save(500, $id, (int)$draft['revision'], $draft['record_sha256'], 'Synthetic recipient typo', ['document.billingname1' => 'Synthetic recipient']);
correctionReject(fn() => $service->save(500, $id, (int)$draft['revision'], $draft['record_sha256'], 'Stale draft', ['document.subject' => 'Stale']), 'DOCUMENT_CHANGES_STALE');
$draft = $service->get(500, $id);
correctionReject(fn() => $service->approve(500, $id, (int)$draft['revision'], $draft['record_sha256'], array_replace($approval, ['original_confirmed' => '0'])), 'CORRECTION_ORIGIN_REQUIRED');
foreach (['document.total', 'document.taxes', 'document.currency', 'document.invoicedate', 'document.billingcountry', 'position.500.price', 'position.500.quantity', 'position.500.taxrate', 'position.99999.title'] as $key) correctionReject(fn() => $service->save(500, $id, (int)$draft['revision'], $draft['record_sha256'], 'Forbidden change', [$key => '1']), 'CORRECTION_FINANCIAL_BLOCKED');
foreach (['structured_required', 'unresolved', 'outside_domestic_scope'] as $scope) correctionReject(fn() => $service->approve(500, $id, (int)$draft['revision'], $draft['record_sha256'], array_replace($approval, ['format_scope' => $scope])), 'CORRECTION_FORMAT_BLOCKED');
$draftPages = correctionText((new DEEC_Pdf())->correction($draft));
integrityCheck(str_contains(implode('', $draftPages), 'MUSTER') && str_contains(implode('', $draftPages), 'Synthetic recipent') && str_contains(implode('', $draftPages), 'Synthetic recipient'), 'Draft correction lost marking or before/after values');
$user = Zend_Registry::get('User'); Zend_Registry::set('User', array_replace($user, ['admin' => 0]));
correctionReject(fn() => $service->approve(500, $id, (int)$draft['revision'], $draft['record_sha256'], $approval), 'DOCUMENT_CHANGES_ADMIN_REQUIRED'); Zend_Registry::set('User', $user);
$failure = correctionFinish(correctionWorker($draft, $approval, 'pdf-failure'));
integrityCheck(!$failure['ok'] && $service->get(500, $id)['status'] === 'draft', 'Failed PDF approved a correction');
$pdo->exec("CREATE TRIGGER fail_correction BEFORE UPDATE ON invoicecorrection FOR EACH ROW BEGIN IF NEW.status = 'approved' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Synthetic correction publication failure'; END IF; END");
$failure = correctionFinish(correctionWorker($draft, $approval)); $pdo->exec('DROP TRIGGER fail_correction');
integrityCheck(!$failure['ok'] && $service->get(500, $id)['status'] === 'draft', 'Failed correction update retained approval');
$target = dirname($originalPath) . '/corrections/500/' . $id . '.pdf';
integrityCheck(!file_exists($target), 'Rollback retained an uncommitted correction PDF');
$canary = $archive->storeAt((new DEEC_Pdf())->correction($draft), $target, 'pdf');
correctionReject(fn() => $service->approve(500, $id, (int)$draft['revision'], $draft['record_sha256'], $approval), 'Archive target collision');
integrityCheck(hash_file('sha256', $target) === $canary['sha256'] && $service->get(500, $id)['status'] === 'draft', 'Occupied correction path was adopted or replaced');
$archive->discardUncommitted($canary);
$pdo->exec('UPDATE invoice SET total = 120 WHERE id = 500');
correctionReject(fn() => $service->approve(500, $id, (int)$draft['revision'], $draft['record_sha256'], $approval), 'DOCUMENT_CHANGES_STALE');
$pdo->prepare('UPDATE invoice SET total = ? WHERE id = 500')->execute([$sourceInvoice['total']]);
$originalRegistration = $service->original(500);
$pdo->prepare('UPDATE documentarchive SET parameters = ? WHERE id = ?')->execute([json_encode(['recovery' => ['selection' => ['origin' => ['kind' => 'reconstructed']]]]), $originalRegistration['id']]);
correctionReject(fn() => $service->create(500, 'Synthetic reconstruction refused', hash('sha256', 'reconstruction-refused')), 'CORRECTION_ORIGINAL_REQUIRED');
$pdo->prepare('UPDATE documentarchive SET parameters = ? WHERE id = ?')->execute([null, $originalRegistration['id']]);
$workers = [correctionWorker($draft, $approval), correctionWorker($draft, $approval)];
$results = array_map('correctionFinish', $workers);
integrityCheck($results[0]['ok'] && $results[1]['ok'] && $results[0]['sha256'] === $results[1]['sha256'], 'Concurrent correction approval was not idempotent');
$approved = $service->get(500, $id); $path = $service->path($approved); $bytes = file_get_contents($path);
integrityCheck($approved['identity'] === 'RB-' . $sourceInvoice['invoiceid'] . '-1' && substr($approved['approved'], 0, 10) === date('Y-m-d') && (fileperms($path) & 0777) === 0600, 'Correction identity, current date or private publication failed');
$pages = correctionText($bytes); $text = implode('', $pages);
integrityCheck(!str_contains($text, 'MUSTER') && str_contains($text, $approved['identity']) && str_contains($text, $sourceInvoice['invoiceid']) && str_contains($text, '119.0000') && str_contains($text, $originalState[0]), 'Final correction lost original identity, unchanged amounts or SHA evidence');
correctionReject(fn() => $service->save(500, $id, (int)$approved['revision'], $approved['record_sha256'], 'Immutable', ['document.subject' => 'No']), 'DOCUMENT_CHANGES_STALE');
correctionReject(fn() => $service->attachment(500, $id, 'invalid'), 'DOCUMENT_CHANGES_ACCESS_DENIED');
$attachment = $service->attachment(500, $id, Sales_Service_DocumentRevisionService::token('invoice', 500));
integrityCheck($attachment['path'] === $path && $attachment['invoicecorrectionid'] === $id, 'Correction email source identity lost');

$second = $service->create(500, 'Synthetic position wording', hash('sha256', 'second-correction')); $secondDraft = $service->get(500, $second);
$service->save(500, $second, (int)$secondDraft['revision'], $secondDraft['record_sha256'], 'Synthetic position wording', ['position.500.title' => 'Synthetic corrected position']); $secondDraft = $service->get(500, $second);
$secondApproved = $service->approve(500, $second, (int)$secondDraft['revision'], $secondDraft['record_sha256'], array_replace($approval, ['recipient_consent' => '0']));
integrityCheck((int)$secondApproved['previousid'] === $id && $secondApproved['snapshot']['base_fields']['document.billingname1'] === 'Synthetic recipient' && count($service->history(500)) === 2, 'Successive correction lost previous corrected values');
$secondText = preg_replace('/\s+/u', ' ', implode('', correctionText(file_get_contents($service->path($secondApproved)))));
integrityCheck(str_contains($secondText, 'Synthetic recipient') && str_contains($secondText, 'Synthetic corrected position') && str_contains($secondText, $approved['identity']), 'Cumulative correction PDF lost previous values or reference');
correctionReject(fn() => $service->attachment(500, $second, Sales_Service_DocumentRevisionService::token('invoice', 500)), 'CORRECTION_CONSENT_REQUIRED');
$composer = (new Sales_Service_AttachmentService())->sync($sourceInvoice, (new Contacts_Model_DbTable_Contact())->getContactWithID(12), 'invoice');
$correctionChoices = array_values(array_filter($composer['attachments'], static fn(array $row): bool => str_starts_with((string)$row['id'], 'correction:')));
integrityCheck(count($correctionChoices) === 1 && $correctionChoices[0]['selected'] === false && str_starts_with((string)$composer['attachments'][0]['id'], 'archive:'), 'Corrections were preselected, unconsented or replaced the original email attachment');

$staleId = $service->create(500, 'Synthetic parallel draft', hash('sha256', 'stale-correction')); $stale = $service->get(500, $staleId);
$service->save(500, $staleId, (int)$stale['revision'], $stale['record_sha256'], 'Synthetic parallel draft', ['document.subject' => 'Synthetic stale subject']); $stale = $service->get(500, $staleId);
$nextId = $service->create(500, 'Synthetic current draft', hash('sha256', 'next-correction')); $next = $service->get(500, $nextId);
$service->save(500, $nextId, (int)$next['revision'], $next['record_sha256'], 'Synthetic current draft', ['document.reference' => 'Synthetic reference']); $next = $service->get(500, $nextId);
$service->approve(500, $nextId, (int)$next['revision'], $next['record_sha256'], $approval);
correctionReject(fn() => $service->approve(500, $staleId, (int)$stale['revision'], $stale['record_sha256'], $approval), 'DOCUMENT_CHANGES_STALE');

$xmlId = $service->create(1, 'Synthetic structured correction', hash('sha256', 'xml-correction')); $xml = $service->get(1, $xmlId);
$service->save(1, $xmlId, (int)$xml['revision'], $xml['record_sha256'], 'Synthetic structured correction', ['document.subject' => 'Synthetic XML correction']); $xml = $service->get(1, $xmlId);
correctionReject(fn() => $service->approve(1, $xmlId, (int)$xml['revision'], $xml['record_sha256'], $approval), 'CORRECTION_STRUCTURED_BLOCKED');
integrityDraft('invoice', 501); $pdo->exec("UPDATE invoice SET invoiceid = 990501, completed = 1, state = 105, filename = 'missing.pdf', subtotal = 100, taxes = 19, total = 119 WHERE id = 501");
correctionReject(fn() => $service->create(501, 'Synthetic missing original', hash('sha256', 'missing-correction')), 'CORRECTION_ORIGINAL_REQUIRED');
$manifest = $service->manifest(500);
integrityCheck(count($manifest['corrections']) === 3 && !$manifest['original_invoice_values_replaced'] && !$manifest['gobd_original_delivery_modified'] && $manifest['corrections'][0]['kind'] === 'editorial_invoice_correction', 'Correction export mixed drafts or original deliveries');
integrityCheck(!(new Contacts_Model_DbTable_Emailattachment())->canReadStoredFile(substr($path, strlen(BASE_PATH) + 1)), 'Correction PDF entered a direct file route');
$forged = $approved; $forged['clientid'] = 8; $forged['record_sha256'] = Sales_Service_InvoiceCorrectionService::fingerprint($forged);
correctionReject(fn() => $service->path($forged), 'DOCUMENT_CHANGES_ACCESS_DENIED');
file_put_contents($path, 'Synthetic invalid correction bytes');
correctionReject(fn() => $service->path($approved), 'CORRECTION_INTEGRITY_FAILED'); file_put_contents($path, $bytes); chmod($path, 0600);
unlink($path); correctionReject(fn() => $service->path($approved), 'CORRECTION_INTEGRITY_FAILED'); file_put_contents($path, $bytes); chmod($path, 0600);
$pdo->prepare('UPDATE invoicecorrection SET reason = ? WHERE id = ?')->execute(['Synthetic tampered record', $id]);
correctionReject(fn() => $service->get(500, $id), 'CORRECTION_INTEGRITY_FAILED'); $pdo->prepare('UPDATE invoicecorrection SET reason = ? WHERE id = ?')->execute([$approved['reason'], $id]);

integritySeed('user', ['id' => 46, 'clientid' => 7, 'activated' => 1]); integritySeed('permission', ['userid' => 46, 'clientid' => 7, 'sales' => '{"invoice":["view"]}']);
integritySeed('user', ['id' => 45, 'clientid' => 7, 'activated' => 1]); integritySeed('permission', ['userid' => 45, 'clientid' => 7, 'sales' => '{"invoice":["view","add"]}', 'contacts' => '{"contact":["view","add"]}']);
$ownerUser = Zend_Registry::get('User'); $ownerClient = Zend_Registry::get('Client');
Zend_Registry::set('User', array_replace($ownerUser, ['id' => 46, 'admin' => 0]));
integrityCheck($service->get(500, $id)['identity'] === $approved['identity'] && $service->path($service->get(500, $id)) === $path, 'Read-only user could not view a correction');
correctionReject(fn() => $service->create(500, 'No create right', hash('sha256', 'no-create')), 'DOCUMENT_CHANGES_ACCESS_DENIED');
correctionReject(fn() => $service->approve(500, $id, (int)$approved['revision'], $approved['record_sha256'], $approval), 'DOCUMENT_CHANGES_ACCESS_DENIED');
correctionReject(fn() => $service->manifest(500), 'DOCUMENT_CHANGES_ADMIN_REQUIRED');
correctionReject(fn() => $service->attachment(500, $id, Sales_Service_DocumentRevisionService::token('invoice', 500)), 'DOCUMENT_CHANGES_ACCESS_DENIED');
Zend_Registry::set('User', array_replace($ownerUser, ['id' => 45, 'admin' => 0]));
integrityCheck($service->attachment(500, $id, Sales_Service_DocumentRevisionService::token('invoice', 500))['invoicecorrectionid'] === $id, 'Separate send permission incorrectly required editing or approval rights');
correctionReject(fn() => $service->save(500, $staleId, (int)$stale['revision'], $stale['record_sha256'], 'No edit right', ['document.subject' => 'No']), 'DOCUMENT_CHANGES_ACCESS_DENIED');
Zend_Registry::set('User', array_replace($ownerUser, ['id' => 43, 'admin' => 0, 'clientid' => 8])); Zend_Registry::set('Client', ['id' => 8, 'modules' => ['sales' => 8, 'contacts' => 8]]);
correctionReject(fn() => $service->get(500, $id), 'DOCUMENT_CHANGES_ACCESS_DENIED');
Zend_Registry::set('User', $ownerUser); Zend_Registry::set('Client', $ownerClient);

require_once $root . '/application/modules/sales/controllers/InvoiceController.php';
class CorrectionControllerFixture extends Sales_InvoiceController {
	public function init() { $this->_user = Zend_Registry::get('User'); $this->view = new Zend_View(); $this->view->addHelperPath(dirname(__DIR__, 2) . '/application/views/helpers', 'Zend_View_Helper'); }
	protected function disableView(): void {}
}
function correctionRequest(int $invoiceId, int $correctionId, string $action, string $method = 'GET', array $post = []): Zend_Controller_Response_Http
{
	$request = new RevisionRequestFixture(); $request->method = $method; $request->setModuleName('sales')->setControllerName('invoice')->setActionName($action)->setParam('id', $invoiceId)->setParam('correctionid', $correctionId)->setPost($post);
	$response = new Zend_Controller_Response_Http(); $response->headersSentThrowsException = false; (new CorrectionControllerFixture($request, $response))->{$action . 'Action'}(); return $response;
}
$response = correctionRequest(500, $id, 'correctionpdf');
integrityCheck($response->getHttpResponseCode() === 200 && $response->getBody() === $bytes, 'Correction download changed finalized bytes');
integrityCheck(correctionRequest(500, $id, 'correctionpdf', 'HEAD')->getBody() === '' && correctionRequest(500, $id, 'correctionpdf', 'POST')->getHttpResponseCode() === 405, 'Correction download method handling failed');
integrityCheck(correctionRequest(500, $id, 'correction', 'POST', ['operation' => 'approve', 'csrf_token' => 'invalid'])->getHttpResponseCode() === 403, 'Correction accepted invalid CSRF');
integrityCheck(correctionRequest(500, $id, 'correction', 'POST', ['operation' => 'save', 'csrf_token' => Sales_Service_DocumentRevisionService::token('invoice', 500), 'total' => '1'])->getHttpResponseCode() === 409, 'Correction HTTP endpoint accepted financial fields');
$export = correctionRequest(500, $id, 'correctionexport');
integrityCheck($export->getHttpResponseCode() === 200 && count(json_decode($export->getBody(), true, 512, JSON_THROW_ON_ERROR)['corrections']) === 3, 'Private correction export failed');
Zend_Registry::set('User', []); integrityCheck(correctionRequest(500, $id, 'correctionpdf')->getHttpResponseCode() === 403, 'Unauthenticated correction download succeeded'); Zend_Registry::set('User', $ownerUser);

require_once $root . '/application/controllers/helpers/Email.php';
class CorrectionEmailFixture extends Application_Controller_Action_Helper_Email {
	public function getEmailConfig($module): array { return [['host' => '127.0.0.1', 'port' => (int)getenv('DEWAWI_CORRECTION_TEST_SMTP_PORT'), 'auth' => false, 'secure' => '', 'timeout' => 5], 'sender@fixture.invalid', 'Synthetic sender']; }
}
integritySeed('email', ['id' => 88, 'clientid' => 7, 'parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'email' => 'recipient@fixture.invalid']);
Zend_Registry::set('User', $ownerUser + ['emailsignature' => '']);
$emailRequest = new RevisionRequestFixture(); $emailRequest->method = 'POST'; $emailRequest->setModuleName('contacts')->setControllerName('email')->setActionName('send')->setParam('documentid', 500)->setParam('contactid', 12)->setPost(['recipient' => '88', 'subject' => 'Synthetic correction delivery', 'body' => 'Synthetic message', 'module' => 'sales', 'controller' => 'invoice', 'correction_csrf_token' => Sales_Service_DocumentRevisionService::token('invoice', 500), 'files' => ['archive:' . $approved['snapshot']['original']['id'], 'correction:' . $id]]);
$emailController = new RevisionControllerFixture($emailRequest, new Zend_Controller_Response_Http());
$emailHelper = new CorrectionEmailFixture(); $emailHelper->setActionController($emailController);
$emailBefore = (int)$pdo->query('SELECT COUNT(*) FROM emailmessage')->fetchColumn();
$emailResult = $emailHelper->sendEmail('contacts', 'contact');
integrityCheck($emailResult['ok'] && (int)$pdo->query('SELECT COUNT(*) FROM emailmessage')->fetchColumn() === $emailBefore + 1, 'Explicit correction send failed or sent automatically');
$mail = $pdo->query('SELECT * FROM emailmessage ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$mailCopies = $pdo->query('SELECT * FROM emailmessageattachment WHERE emailmessageid = ' . $mail['id'] . ' ORDER BY ordering')->fetchAll(PDO::FETCH_ASSOC);
integrityCheck($mail['deliverystatus'] === 'accepted' && count($mailCopies) === 2 && $mailCopies[0]['sha256'] === $originalState[0] && $mailCopies[1]['sha256'] === $approved['sha256'] && (int)$mailCopies[1]['invoicecorrectionid'] === $id, 'Correction send lost stored original/correction copies or references');
file_put_contents($work . '/correction-email-check.json', json_encode([$approved['snapshot']['original']['filename'] => $originalState[0], $approved['identity'] . '.pdf' => $approved['sha256']], JSON_THROW_ON_ERROR));
$reviewDb = new mysqli('localhost', 'root', '', 'integrity_fixture', 3306, $socket);
$review = reviewLoadDocument($reviewDb, $archive, ['clientid' => 7, 'module' => 'sales', 'controller' => 'invoice', 'id' => 500], 7); $reviewDb->close();
$correctionSources = array_values(array_filter($review['sources'], static fn(array $row): bool => $row['source'] === 'emailmessageattachment' && !empty($row['evidence']['invoicecorrectionid'])));
integrityCheck(count($correctionSources) === 1 && $correctionSources[0]['status'] === 'correction_not_original', 'Correction dispatch copy entered historical-original recovery candidates');
Zend_Registry::set('User', $ownerUser);

foreach (['de_DE', 'en_US'] as $locale) {
	$translator = new DEEC_Translate($locale); $translator->loadDir('default', $root . '/languages/' . $locale . '/default'); Zend_Registry::set('DEEC_Translate', $translator);
	$response = correctionRequest(500, $staleId, 'correction');
	$view = new Zend_View(); $view->addHelperPath($root . '/application/views/helpers', 'Zend_View_Helper'); $view->addScriptPath($root . '/application/modules/sales/views/scripts');
	$view->invoice = $sourceInvoice; $view->correction = $service->get(500, $staleId); $view->canEditCorrection = true; $view->canApproveCorrection = true;
	$html = $view->render('invoice/correction.phtml');
	integrityCheck(!str_contains($html, 'CORRECTION_') && !str_contains($html, 'name="total"') && str_contains($html, 'name="original_reference"') && substr_count($html, 'data-autosave="false"') === 2, 'Correction UI lost localization, original review or form isolation');
	file_put_contents($work . '/correction-panel-' . $locale . '.html', '<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="file://' . $root . '/css/ui.css"><body class="edit">' . $html . '<script src="file://' . $root . '/library/jQuery/jquery-1.12.4.min.js"></script><script src="file://' . $root . '/library/jQuery/jquery.cookie.js"></script><script>var language="en",module="sales",controller="invoice",action="correction",id=500,baseUrl="/fixture",cookiePath="/fixture"; var fixtureErrors=[]; window.addEventListener("error",function(e){fixtureErrors.push(e.message);}); $.fn.datepicker=function(){return this;}; function autosize(){}</script><script src="file://' . $root . '/js/global.js"></script></body></html>');
}
integrityCheck($pdo->query('SELECT * FROM invoice WHERE id = 500')->fetch(PDO::FETCH_ASSOC) === $sourceInvoice && $pdo->query('SELECT * FROM invoicepos WHERE parentid = 500')->fetchAll(PDO::FETCH_ASSOC) === $sourcePositions && $pdo->query('SELECT * FROM increment WHERE clientid = 7')->fetch(PDO::FETCH_ASSOC) === $sourceCounter, 'Correction changed historical invoice, positions or numbering');
integrityCheck($pdo->query('SELECT * FROM documentarchive ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $sourceRegistrations && [hash_file('sha256', $originalPath), filemtime($originalPath), filesize($originalPath)] === $originalState, 'Correction changed original archive rows or bytes');
$exportOriginal = (new Application_Model_DbTable_Documentarchive())->getForExport(500, 'sales', 'invoice');
integrityCheck($exportOriginal['sha256'] === $originalState[0] && BASE_PATH . '/' . $exportOriginal['path'] === $originalPath, 'Correction entered original export selection');

integritySeed('client', ['id' => 19, 'parentid' => 7, 'activated' => 1, 'company' => 'Synthetic child issuer', 'address' => 'Child street', 'postcode' => '10115', 'city' => 'Berlin', 'country' => 'DE']);
$childInvoice = array_replace($sourceInvoice, ['id' => 901, 'clientid' => 19, 'invoiceid' => 990901, 'filename' => 'child-invoice.pdf', 'language' => 'en_US']);
integritySeed('invoice', $childInvoice);
integritySeed('invoicepos', array_replace($sourcePositions[0], ['id' => 901, 'parentid' => 901, 'clientid' => 19]));
$db = recoveryBoot(migrationConfig($work . '/root.ini', 'production') + ['unix_socket' => $socket], $base, 19, 42);
$childPdf = new TCPDF(); $childPdf->AddPage(); $childPdf->SetFont('dejavusans', '', 10); $childPdf->Write(5, 'Synthetic issued child invoice 990901');
$archive->store($childPdf->Output('', 'S'), 19, 'sales', 'invoice', 901, 'pdf');
(new Application_Model_DbTable_Documentarchive())->registerOriginal($childInvoice, 'sales', 'invoice');
$parentContact = $pdo->query('SELECT * FROM contact WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
$childId = $service->create(901, 'Synthetic child wording correction', hash('sha256', 'child-correction'));
$child = $service->get(901, $childId);
$service->save(901, $childId, (int)$child['revision'], $child['record_sha256'], 'Synthetic child wording correction', ['position.901.description' => str_repeat('Synthetic corrected description with unchanged supply. ', 120)]);
$child = $service->get(901, $childId); $childPages = correctionText((new DEEC_Pdf())->correction($child));
integrityCheck(count($childPages) >= 3 && count(array_filter($childPages, static fn(string $page): bool => str_contains($page, 'SAMPLE'))) === count($childPages), 'Multipage English correction lost draft markings');
$childApproved = $service->approve(901, $childId, (int)$child['revision'], $child['record_sha256'], $approval);
integrityCheck((int)$childApproved['clientid'] === 19 && $service->path($childApproved) === dirname($archive->path(19, 'sales', 'invoice', 901)) . '/corrections/901/' . $childId . '.pdf' && $pdo->query('SELECT * FROM contact WHERE id = 1')->fetch(PDO::FETCH_ASSOC) === $parentContact, 'Authorized child correction lost tenant ownership or changed shared parent contact');
$childFinalPages = correctionText(file_get_contents($service->path($childApproved)));
integrityCheck(!str_contains(implode('', $childFinalPages), 'SAMPLE') && count($childFinalPages) >= 3, 'Finalized multipage child correction retained draft marks');
Zend_Registry::set('User', $ownerUser); Zend_Registry::set('Client', $ownerClient);

echo 'PASS: ' . ($checks - $correctionStart) . " invoice correction checks; immutable amounts, snapshots, explicit consent, structured blocks, failed writes, concurrent publication and successive corrections\n";
