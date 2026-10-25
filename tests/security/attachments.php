<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Reuse the real models, permissions and mail helper with the existing in-memory transport.
ob_start();
require __DIR__ . '/quote-email.php';
require_once $root . '/application/modules/contacts/controllers/AttachmentController.php';
$attachmentChecksStart = $checks;
$testPath = sys_get_temp_dir() . '/dewawi-attachment-test-' . bin2hex(random_bytes(8));
mkdir($testPath, 0700);
define('BASE_PATH', $testPath);

function removeAttachmentTestFiles(string $path): void
{
    if (is_link($path) || !is_dir($path)) { unlink($path); return; }
    foreach (new FilesystemIterator($path) as $entry) removeAttachmentTestFiles($entry->getPathname());
    rmdir($path);
}
register_shutdown_function(static function () use ($testPath): void { removeAttachmentTestFiles($testPath); });

class AttachmentTestAdapter extends QuoteEmailTestAdapter
{
    public function update($table, array $bind, $where = '')
    {
        $this->writes[] = [$table, $bind];
        foreach ($this->rows[$table] ?? [] as $index => $row) {
            foreach ((array)$where as $condition) {
                if (!emailWhereMatches($condition, $row)) continue 2;
            }
            $this->rows[$table][$index] = array_replace($row, $bind);
        }
        return 1;
    }
}
class AttachmentTestLayout extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'Layout'; }
    public function setLayout($layout) {}
    public function disableLayout() {}
}
class AttachmentTestRequest extends Zend_Controller_Request_Http
{
    public string $body = '';
    public function getRawBody() { return $this->body; }
}
class AttachmentTestUpload
{
    public array $files = [];
    public int $receives = 0;
    private string $destination;
    public function addValidator(...$args) {}
    public function setDestination($destination) { $this->destination = $destination; }
    public function getFileInfo() { return $this->files; }
    public function isUploaded($file) { return true; }
    public function isValid($file) { return true; }
    public function receive($file)
    {
        $this->receives++;
        return copy($this->files[$file]['tmp_name'], $this->destination . '/' . $this->files[$file]['name']);
    }
}
class AttachmentTestController extends Contacts_AttachmentController
{
    public static ?AttachmentTestUpload $upload = null;
    public function init()
    {
        parent::init();
        $this->view = new Zend_View();
    }
    protected function getUploadAdapter() { return self::$upload; }
    protected function sendAttachmentFile($stream): void
    {
        if ($this->getRequest()->getMethod() !== 'HEAD') $this->getResponse()->setBody(stream_get_contents($stream));
        fclose($stream);
    }
}

$adapter = new AttachmentTestAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Controller_Action_HelperBroker::addHelper(new AttachmentTestLayout());
Zend_Controller_Front::getInstance()->setControllerDirectory(['contacts' => $root . '/application/modules/contacts/controllers']);

function attachmentFile(array $overrides = []): array
{
    $row = array_replace(['id' => 600, 'documentid' => 10, 'module' => 'sales', 'controller' => 'quote', 'clientid' => 7, 'filename' => 'quote.pdf', 'deleted' => 0], $overrides);
    $row['location'] = DEEC_Emailattachment::getDirectory($row['module'], $row['controller'], $row['documentid'], $row['clientid']);
    if (!is_dir($row['location'])) mkdir($row['location'], 0775, true);
    $path = $row['location'] . '/' . $row['filename'];
    if (!is_file($path)) file_put_contents($path, "%PDF-1.4\nAttachment security fixture\n%%EOF\n");
    return $row + ['filetype' => 'application/pdf', 'filesize' => filesize($path), 'ordering' => 0];
}

function setupAttachments(bool $shared = false): void
{
    global $adapter;
    setupQuoteEmail($shared);
    $adapter->rows['permission'][0]['contacts'] = '{"contact":["view","add","edit","delete"]}';
    $adapter->rows['permission'][0]['sales'] = '{"quote":["view","add","edit","delete"],"invoice":["view","add","delete"]}';
    $adapter->rows['invoice'] = $adapter->rows['quote'];
    $adapter->rows['emailmessage'][0]['attachment'] = 'quote.pdf';
    $adapter->rows['emailattachment'] = [
        attachmentFile(),
        attachmentFile(['id' => 601, 'documentid' => 11, 'clientid' => 8, 'filename' => 'foreign.pdf']),
        attachmentFile(['id' => 602, 'deleted' => 1, 'filename' => 'deleted.pdf']),
        attachmentFile(['id' => 603, 'documentid' => 13, 'filename' => 'deleted-parent.pdf']),
        attachmentFile(['id' => 604, 'module' => 'contacts', 'controller' => 'contact', 'documentid' => 100, 'clientid' => $shared ? 70 : 7, 'filename' => 'contact.pdf']),
        attachmentFile(['id' => 605, 'controller' => 'invoice', 'filename' => 'invoice.pdf']),
    ];
    $adapter->writes = [];
    AttachmentTestController::$upload = new AttachmentTestUpload();
}

function attachmentAction(string $action, array $params = [], string $method = 'GET', ?array $body = null, ?string $uri = null): array
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = [];
    $_GET = [];
    $request = new AttachmentTestRequest();
    $request->setModuleName('contacts')->setControllerName('attachment')->setActionName($action)->setParams($params);
    if ($body !== null) $request->body = json_encode($body);
    if ($uri !== null) $request->setRequestUri($uri);
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
    $controller = new AttachmentTestController($request, $response);
    $controller->{$action . 'Action'}();
    return [$response->getHttpResponseCode(), $response->getBody(), $controller];
}

function uploadFixture(string $filename): array
{
    $path = tempnam(BASE_PATH, 'incoming-');
    file_put_contents($path, "%PDF-1.4\nUploaded attachment security fixture\n%%EOF\n");
    return ['name' => $filename, 'tmp_name' => $path, 'size' => filesize($path)];
}

foreach ([false, true] as $shared) {
    setupAttachments($shared);
    $db = new Contacts_Model_DbTable_Emailattachment();
    $client = Zend_Registry::get('Client');
    check($db->getEmailattachment(600)['documentid'] === 10, 'Allowed attachment missing');
    check(array_column($db->getEmailattachments(10, 'sales', 'quote'), 'id') === [600], 'Document attachment list mixed tenants');
    check($db->getEmailattachment(601) === null && $db->getEmailattachment(602) === null && $db->getEmailattachment(603) === null, 'Foreign, deleted or orphaned attachment visible');
    check(attachmentAction('download', ['id' => 600])[1] === file_get_contents(DEEC_Emailattachment::getPath($adapter->rows['emailattachment'][0])), 'Allowed download failed');
    check(attachmentAction('download', ['id' => 604])[0] === 200, 'Shared contact attachment unavailable');
    check(attachmentAction('download', ['id' => 605])[0] === 200, 'Invoice attachment unavailable');
    check(attachmentAction('download', ['messageid' => 400, 'filename' => 'quote.pdf'])[0] === 200, 'Existing email attachment download failed');
    check(attachmentAction('download', ['messageid' => 401, 'filename' => 'quote.pdf'])[0] === 404, 'Foreign email attachment download allowed');
    check(attachmentAction('download', ['messageid' => 400, 'filename' => 'unlisted.pdf'])[0] === 404, 'Unlisted message filename allowed');
    foreach ([601, 602, 603, 999, -1, [], '600 OR 1=1', '600x'] as $id) {
        [$status, $body] = attachmentAction('download', ['id' => $id]);
        check($status === 404 && $body === '', 'Invalid attachment download exposed content');
    }
    $row = $adapter->rows['emailattachment'][0];
    $relative = 'sales/quote/' . (new DEEC_Directory())->getUrl('10', '7') . '/quote.pdf';
    check(attachmentAction('download', ['attachmentPath' => $relative], 'GET', null, '/files/attachments/' . $relative)[0] === 200, 'Legacy attachment link failed');
    check(attachmentAction('download', ['attachmentPath' => $relative], 'GET', null, '/files/attachments/sales/quote/8/0/8/1/1/11/foreign.pdf')[0] === 404, 'Legacy path exposed a foreign document');
    check(attachmentAction('download', ['attachmentPath' => $relative], 'GET', null, '/files/attachments/sales/quote/7/0/7/1/0/10/../quote.pdf')[0] === 404, 'Traversal path accepted');
    $adapter->rows['permission'][0]['sales'] = '{"quote":["add","delete"]}';
    check(attachmentAction('download', ['id' => 600])[0] === 404, 'Download without read permission accepted');
    check(attachmentAction('delete', [], 'POST', ['id' => [600]])[0] === 404 && is_file(DEEC_Emailattachment::getPath($row)), 'Delete without read permission accepted');
    $adapter->rows['permission'][0]['sales'] = '{"quote":["view"]}';
    check(attachmentAction('upload', ['cmodule' => 'sales', 'ccontroller' => 'quote', 'id' => 10], 'POST')[0] === 404, 'Upload without add permission accepted');
    check(AttachmentTestController::$upload->receives === 0 && $adapter->writes === [], 'Denied upload performed storage');
    check(attachmentAction('delete', [], 'POST', ['id' => [600]])[0] === 404 && $adapter->writes === [], 'Delete without delete permission accepted');
    check(Zend_Registry::get('Client') === $client, 'Attachment access changed the global client');

    setupAttachments($shared);
    $uploadName = 'uploaded-' . ($shared ? 'shared' : 'own') . '.pdf';
    AttachmentTestController::$upload->files = ['file' => uploadFixture($uploadName)];
    $target = DEEC_Emailattachment::getDirectory('sales', 'quote', 10, 7) . '/' . $uploadName;
    check(attachmentAction('upload', ['cmodule' => 'sales', 'ccontroller' => 'quote', 'id' => 10], 'POST')[0] === 200 && is_file($target), 'Allowed upload failed');
    $uploaded = end($adapter->rows['emailattachment']);
    check($uploaded['clientid'] === 7 && $uploaded['documentid'] === 10, 'Document upload used the shared contact tenant or visible quote number');
    check(attachmentAction('upload', ['cmodule' => 'sales', 'ccontroller' => 'quote', 'id' => 10], 'POST')[0] === 409, 'Upload overwrote an existing file');
    check(attachmentAction('delete', [], 'POST', ['id' => [$uploaded['id']]])[0] === 200 && !is_file($target), 'Allowed deletion failed');
    check(attachmentAction('delete', [], 'POST', ['id' => [$uploaded['id']]])[0] === 404, 'Repeated deletion accepted');

    setupAttachments($shared);
    $contactUpload = 'contact-upload-' . ($shared ? 'shared' : 'own') . '.pdf';
    AttachmentTestController::$upload->files = ['file' => uploadFixture($contactUpload)];
    check(attachmentAction('upload', ['cmodule' => 'contacts', 'ccontroller' => 'contact', 'id' => 100], 'POST')[0] === 200, 'Contact upload failed');
    check(end($adapter->rows['emailattachment'])['clientid'] === ($shared ? 70 : 7), 'Contact upload lost sharing');
    $adapter->writes = [];
    foreach ([['id' => 11], ['id' => 1001], ['id' => '10x'], ['id' => []], ['cmodule' => '../sales'], ['cmodule' => []], ['ccontroller' => 'other'], ['ccontroller' => []]] as $override) {
        check(attachmentAction('upload', array_replace(['cmodule' => 'sales', 'ccontroller' => 'quote', 'id' => 10], $override), 'POST')[0] === 404, 'Invalid or foreign upload context accepted');
    }
    check($adapter->writes === [], 'Denied contexts caused database writes');
    foreach ([601, 602, 603, '600x', [], -1] as $id) {
        check(attachmentAction('delete', [], 'POST', ['id' => [$id]])[0] === 404, 'Foreign or malformed deletion accepted');
    }
    check(attachmentAction('delete', [], 'POST', ['id' => [600, 601]])[0] === 404 && $adapter->writes === [], 'Mixed deletion batch partially deleted an allowed file');

    setupAttachments($shared);
    if ($shared) {
        $legacy = attachmentFile(['id' => 606, 'clientid' => 70, 'filename' => 'legacy-shared.pdf']);
        $adapter->rows['emailattachment'][] = $legacy;
        $foreignShared = attachmentFile(['id' => 611, 'clientid' => 70, 'documentid' => 11, 'filename' => 'foreign-shared.pdf']);
        $adapter->rows['emailattachment'][] = $foreignShared;
        $adapter->rows['emailmessage'][1]['attachment'] = 'foreign-shared.pdf';
        check(attachmentAction('download', ['id' => 606])[0] === 200, 'Legacy shared document attachment unavailable');
        check(count((new Contacts_Model_DbTable_Emailattachment())->getEmailattachments(10, 'sales', 'quote')) === 2, 'Legacy shared attachment missing from list');
        check(attachmentAction('download', ['id' => 611])[0] === 404, 'Shared storage exposed a sibling sales document');
        check(attachmentAction('download', ['messageid' => 401, 'filename' => 'foreign-shared.pdf'])[0] === 404, 'Shared email exposed a sibling document attachment');
        check(attachmentAction('delete', [], 'POST', ['id' => [611]])[0] === 404 && $adapter->writes === [], 'Shared storage allowed deleting a sibling attachment');
        check(is_file(DEEC_Emailattachment::getPath($foreignShared)), 'Denied deletion removed the shared foreign fixture');
    }
    foreach (['../escape.pdf', 'nested/name.pdf', 'back\\slash.pdf', '.htaccess', "bad\r\n.pdf", 'comma,name.pdf', 'script.php', 'wrong.png'] as $filename) {
        AttachmentTestController::$upload->files = ['file' => uploadFixture($filename)];
        check(attachmentAction('upload', ['cmodule' => 'sales', 'ccontroller' => 'quote', 'id' => 10], 'POST')[0] === 400, 'Invalid filename, extension or MIME accepted');
    }
    $empty = uploadFixture('empty.pdf');
    file_put_contents($empty['tmp_name'], '');
    AttachmentTestController::$upload->files = ['file' => $empty];
    check(attachmentAction('upload', ['cmodule' => 'sales', 'ccontroller' => 'quote', 'id' => 10], 'POST')[0] === 400, 'Empty upload accepted');
    $large = uploadFixture('large.pdf');
    $stream = fopen($large['tmp_name'], 'ab');
    ftruncate($stream, 10240001);
    fclose($stream);
    AttachmentTestController::$upload->files = ['file' => $large];
    check(attachmentAction('upload', ['cmodule' => 'sales', 'ccontroller' => 'quote', 'id' => 10], 'POST')[0] === 400, 'Oversized upload accepted');
    check($adapter->writes === [], 'Invalid uploads persisted metadata');

    $row = $adapter->rows['emailattachment'][0];
    foreach ([['location' => BASE_PATH], ['filename' => '../escape.pdf'], ['location' => $row['location'] . '/../10']] as $override) {
        $adapter->rows['emailattachment'][0] = array_replace($row, $override);
        check(attachmentAction('download', ['id' => 600])[0] === 404, 'Manipulated metadata path downloaded');
        check(attachmentAction('delete', [], 'POST', ['id' => [600]])[0] === 404 && $adapter->writes === [], 'Manipulated metadata path deleted');
    }
    $adapter->rows['emailattachment'][0] = $row;
    $path = DEEC_Emailattachment::getPath($row);
    unlink($path);
    check(attachmentAction('download', ['id' => 600])[0] === 404, 'Missing file downloaded');
    $outside = BASE_PATH . '/outside.pdf';
    file_put_contents($outside, 'Do not delete this fixture');
    symlink($outside, $path);
    check(attachmentAction('download', ['id' => 600])[0] === 404, 'Symlink downloaded');
    check(attachmentAction('delete', [], 'POST', ['id' => [600]])[0] === 404 && is_file($outside), 'Symlink target deleted');
    unlink($path);
    file_put_contents($path, "%PDF-1.4\nRestored fixture\n%%EOF\n");

    // Existing attachment selection must be authorized before any message is written or sent.
    $adapter->writes = [];
    DEEC_Email::$sent = [];
    check(sendQuoteEmail(['files' => [600]]), 'Allowed PDF attachment send failed');
    check(DEEC_Email::$sent[0]['attachments'] === [$path], 'Mail helper selected a different file');
    check((end($adapter->rows['emailmessage'])['attachments'] ?? []) === ['quote.pdf'], 'Mail log lost the selected filename');
    foreach ([601, 602, 603, 604, 605, 999, '600x', []] as $id) {
        try {
            sendQuoteEmail(['files' => [$id]]);
            throw new RuntimeException('Unauthorized attachment accepted');
        } catch (RuntimeException $e) {
            check($e->getMessage() === 'Email attachment is not available', 'Unexpected attachment send failure');
            check($adapter->writes === [] && DEEC_Email::$sent === [], 'Denied attachment reached the mail transport or log');
        }
    }
    $adapter->rows['emailattachment'][0]['location'] = BASE_PATH;
    try {
        sendQuoteEmail(['files' => [600]]);
        throw new RuntimeException('Manipulated attachment sent');
    } catch (InvalidArgumentException $e) {
        check($adapter->writes === [] && DEEC_Email::$sent === [], 'Manipulated file reached the mail transport');
    }
}

// Generated document attachments retain their module and selected owner under contact sharing.
foreach (['sales' => 'quote', 'purchases' => 'purchaseorder', 'processes' => 'process'] as $module => $controller) {
    setupAttachments(true);
    $adapter->rows['permission'][0][$module] = json_encode([$controller => ['view', 'add', 'delete']]);
    $document = $adapter->rows['quote'][0];
    $document['id'] = 30;
    $document['filename'] = $module . '-original.pdf';
    $adapter->rows[$controller][] = $document;
    $sourceDir = BASE_PATH . '/files/contacts/' . (new DEEC_Directory())->getUrl('500', '7');
    if (!is_dir($sourceDir)) mkdir($sourceDir, 0775, true);
    $source = $sourceDir . '/' . $document['filename'];
    $original = "%PDF-1.4\nStored original document fixture\n%%EOF\n";
    file_put_contents($source, $original);
    $class = ucfirst($module) . '_Service_AttachmentService';
    $service = new $class();
    $result = $service->sync($document, $adapter->rows['contact'][0], $controller);
    check(count($result['attachments']) === 1, 'Stored PDF was not registered: ' . $module);
    $attachment = $result['attachments'][0];
    check($attachment['clientid'] === 7 && $attachment['documentid'] === 30 && $attachment['module'] === $module, 'Stored PDF used an incorrect context');
    check(file_get_contents(DEEC_Emailattachment::getPath($attachment)) === $original, 'Stored PDF bytes changed');
    check(count($service->sync($document, [], $controller)['attachments']) === 1, 'Repeated synchronization duplicated metadata');
    check(attachmentAction('download', ['id' => $attachment['id']])[0] === 200, 'Stored PDF unavailable for download');
    check(attachmentAction('delete', [], 'POST', ['id' => [$attachment['id']]])[0] === 200, 'Stored PDF could not be deleted');
    check(file_get_contents($source) === $original, 'Deleting an attachment modified the original document');
}

setupAttachments();
$special = attachmentFile(['id' => 609, 'filename' => 'offer + #ä.pdf']);
$adapter->rows['emailattachment'][] = $special;
$relative = 'sales/quote/' . (new DEEC_Directory())->getUrl('10', '7') . '/' . rawurlencode($special['filename']);
check(attachmentAction('download', ['attachmentPath' => $relative], 'GET', null, '/files/attachments/' . $relative)[0] === 200, 'Encoded legacy filename unavailable');
[$status, $body, $download] = attachmentAction('download', ['id' => 609], 'HEAD');
check($status === 200 && $body === '', 'HEAD download returned bytes');
$headers = array_column($download->getResponse()->getHeaders(), 'value', 'name');
check(($headers['X-Content-Type-Options'] ?? '') === 'nosniff' && ($headers['Cache-Control'] ?? '') === 'private, no-store', 'Download security headers missing');
check(str_contains($headers['Content-Disposition'], "filename*=UTF-8''" . rawurlencode($special['filename'])), 'Download filename was not safely encoded');
check(attachmentAction('download', ['id' => 609], 'POST')[0] === 405, 'Download accepted a write request');

setupAttachments();
$row = $adapter->rows['emailattachment'][0];
$path = DEEC_Emailattachment::getPath($row);
$adapter->rows['emailattachment'][] = array_replace($row, ['id' => 607, 'location' => $row['location'] . '/']);
check(attachmentAction('delete', [], 'POST', ['id' => [600]])[0] === 200 && is_file($path), 'Deletion removed a file referenced by another active attachment');
check(attachmentAction('download', ['id' => 607])[0] === 200, 'Shared physical file became unavailable');
check(attachmentAction('delete', [], 'POST', ['id' => [607]])[0] === 200 && !is_file($path), 'Last reference did not release the file');

setupAttachments();
$adapter->rows['quote'][] = array_replace($adapter->rows['quote'][0], ['id' => 14]);
$targetDir = BASE_PATH . '/files/attachments/sales/quote/' . (new DEEC_Directory())->getUrl('14', '7');
if (!is_dir(dirname($targetDir))) mkdir(dirname($targetDir), 0775, true);
$outsideDir = BASE_PATH . '/outside-directory';
mkdir($outsideDir);
file_put_contents($outsideDir . '/target.pdf', 'Preserve this fixture');
symlink($outsideDir, $targetDir);
$adapter->rows['emailattachment'][] = array_replace($adapter->rows['emailattachment'][0], ['id' => 608, 'documentid' => 14, 'filename' => 'target.pdf', 'location' => $targetDir]);
AttachmentTestController::$upload->files = ['file' => uploadFixture('new.pdf')];
check(attachmentAction('upload', ['cmodule' => 'sales', 'ccontroller' => 'quote', 'id' => 14], 'POST')[0] === 400, 'Symlink directory accepted an upload');
check(attachmentAction('download', ['id' => 608])[0] === 404, 'Symlink directory downloaded a file');
check(attachmentAction('delete', [], 'POST', ['id' => [608]])[0] === 404, 'Symlink directory deleted a file');
check(file_get_contents($outsideDir . '/target.pdf') === 'Preserve this fixture' && !is_file($outsideDir . '/new.pdf') && $adapter->writes === [], 'Symlink directory caused writes');
unlink($targetDir);

setupAttachments();
AttachmentTestController::$upload->files = ['a' => uploadFixture('duplicate.pdf'), 'b' => uploadFixture('duplicate.pdf')];
check(attachmentAction('upload', ['cmodule' => 'sales', 'ccontroller' => 'quote', 'id' => 10], 'POST')[0] === 409 && $adapter->writes === [], 'Duplicate upload filenames were partially stored');
$row = $adapter->rows['emailattachment'][0];
unlink(DEEC_Emailattachment::getPath($row));
try {
    sendQuoteEmail(['files' => [600]]);
    throw new RuntimeException('Missing attachment sent');
} catch (RuntimeException $e) {
    check($e->getMessage() === 'Attachment file is not available' && $adapter->writes === [] && DEEC_Email::$sent === [], 'Missing attachment reached the mail transport');
}

setupAttachments(true);
Zend_Registry::getInstance()->offsetUnset('User');
check(attachmentAction('download', ['id' => 600])[0] === 404, 'Guest downloaded an attachment');
check(attachmentAction('upload', ['cmodule' => 'sales', 'ccontroller' => 'quote', 'id' => 10], 'POST')[0] === 404, 'Guest uploaded an attachment');
check(attachmentAction('delete', [], 'POST', ['id' => [600]])[0] === 404 && $adapter->writes === [], 'Guest deleted an attachment');

echo 'PASS: ' . ($checks - $attachmentChecksStart) . " attachment upload, download, deletion, path and mail-selection checks\n";
ob_end_flush();
