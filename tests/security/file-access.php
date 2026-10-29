<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

ob_start();
require __DIR__ . '/attachments.php';
ob_end_clean();
require_once $root . '/application/controllers/MediaController.php';
require_once $root . '/application/modules/contacts/controllers/DownloadController.php';
require_once $root . '/application/controllers/helpers/Directory.php';
Zend_Session::setOptions(['save_path' => BASE_PATH, 'use_cookies' => false, 'cache_limiter' => '']);
Zend_Session::start();
ini_set('error_log', BASE_PATH . '/file-access-test.log');
Zend_Controller_Front::getInstance()->addControllerDirectory($root . '/application/controllers', 'default');
$checks = 0;

class StoredFileAdapter extends AttachmentTestAdapter
{
    public bool $denyReads = false;

    public function query($sql, $bind = [])
    {
        if ($this->denyReads) throw new RuntimeException('Media publication must not query the database');
        if (!$sql instanceof Zend_Db_Table_Select || $bind) throw new RuntimeException('Unexpected database access');
        $sql->assemble();
        $from = $sql->getPart(Zend_Db_Select::FROM);
        $table = reset($from)['tableName'];
        $rows = array_values(array_filter($this->rows[$table] ?? [], static function ($row) use ($sql): bool {
            foreach ($sql->getPart(Zend_Db_Select::WHERE) as $where) {
                if (preg_match("/SUBSTRING_INDEX\(downloads, '\\|', -1\) = '(.*)'/", $where, $match)) {
                    if (substr(strrchr('|' . ($row['downloads'] ?? ''), '|'), 1) !== $match[1]) return false;
                } elseif (!emailWhereMatches($where, $row)) return false;
            }
            return true;
        }));
        if ($limit = $sql->getPart(Zend_Db_Select::LIMIT_COUNT)) $rows = array_slice($rows, 0, $limit);
        return new class($rows) {
            public function __construct(private array $rows) {}
            public function fetchAll($mode) { return $this->rows; }
        };
    }
}
class StoredFileMediaController extends MediaController
{
    public function init() { $this->_user = Zend_Registry::get('User'); }
    public function allowed(string $folder, string $path, string $action = 'view'): bool { return $this->isManagerPath(BASE_PATH . '/files/', $folder, $path, $action); }
    public function folder(string $module, string $controller, int $id, string $type): ?string { return $this->getMediaFolder($module, $controller, $id, $type); }
}
class StoredDownloadController extends Contacts_DownloadController
{
    public function init() { $this->_flashMessenger = new QuoteEmailTestFlashMessenger(); }
    protected function getUploadAdapter() { return AttachmentTestController::$upload; }
    public function receiveFixture(string $directory): ?array { return $this->receiveFile($directory); }
}
$adapter = new StoredFileAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);

function storedFixture(string $relative, string $contents = 'Private stored file fixture'): string
{
    $path = BASE_PATH . '/' . $relative;
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
    file_put_contents($path, $contents);
    return $path;
}
function storedHeader($controller, string $name): ?string
{
    foreach ($controller->getResponse()->getHeaders() as $header) if (strcasecmp($header['name'], $name) === 0) return $header['value'];
    return null;
}
function storedResponse(string $relative, string $method = 'GET', string $base = ''): array
{
    return attachmentAction('file', [], $method, null, $base . '/' . $relative);
}
function setupStoredFiles(bool $shared = false): void
{
    global $adapter;
    setupAttachments($shared);
    $adapter->rows['quote'][0] += ['filename' => 'quote.pdf'];
    $adapter->rows['quote'][0]['contactid'] = 100;
    $adapter->rows['invoice'][0]['filename'] = 'invoice.pdf';
    $adapter->rows['invoice'][0]['contactid'] = 100;
    $adapter->rows['permission'][0]['items'] = '{"item":["view","edit"]}';
    $adapter->rows['item'] = [['id' => 501, 'clientid' => 7, 'shopid' => 1, 'deleted' => 0, 'downloads' => 'de|legacy.pdf'], ['id' => 502, 'clientid' => 8, 'shopid' => 2, 'deleted' => 0, 'downloads' => 'de|foreign.pdf']];
    $adapter->rows['itemlist'] = [['id' => 503, 'clientid' => 7, 'deleted' => 0, 'image' => 'internal.png']];
    $adapter->rows['download'] = [['id' => 700, 'contactid' => 100, 'clientid' => $shared ? 70 : 7, 'filename' => 'customer.pdf', 'deleted' => 0]];
    $adapter->rows['category'] = [['id' => 800, 'shopid' => 1, 'clientid' => 7, 'deleted' => 0, 'activated' => 1], ['id' => 801, 'shopid' => 1, 'clientid' => 7, 'deleted' => 0, 'activated' => 0]];
    $adapter->rows['slide'] = [['id' => 900, 'shopid' => 1, 'clientid' => 7, 'deleted' => 0, 'activated' => 1]];
    $adapter->rows['media'] = [
        ['id' => 1, 'parentid' => 501, 'module' => 'items', 'controller' => 'item', 'clientid' => 7, 'deleted' => 0, 'type' => 'image', 'url' => 'product.png'],
        ['id' => 2, 'parentid' => 800, 'module' => 'shops', 'controller' => 'category', 'clientid' => 7, 'deleted' => 0, 'type' => 'image', 'url' => 'category.png'],
        ['id' => 3, 'parentid' => 900, 'module' => 'shops', 'controller' => 'slide', 'clientid' => 7, 'deleted' => 0, 'type' => 'image', 'url' => 'slide.png'],
        ['id' => 4, 'parentid' => 800, 'module' => 'shops', 'controller' => 'category', 'clientid' => 7, 'deleted' => 0, 'type' => 'download', 'url' => 'data.pdf'],
        ['id' => 5, 'parentid' => 801, 'module' => 'shops', 'controller' => 'category', 'clientid' => 7, 'deleted' => 0, 'type' => 'image', 'url' => 'unpublished.png'],
        ['id' => 6, 'parentid' => 502, 'module' => 'items', 'controller' => 'item', 'clientid' => 8, 'deleted' => 0, 'type' => 'image', 'url' => 'foreign.png'],
    ];
    Zend_Registry::getInstance()->offsetUnset('SiteContext');
}

foreach ([false, true] as $shared) {
    setupStoredFiles($shared);
    $owner = $shared ? 70 : 7;
    $contact = 'files/contacts/' . (new DEEC_Directory())->getUrl(100, $owner) . '/';
    $paths = [$contact . 'notes.txt', 'files/contacts/7/0/7/1/0/100/quote.pdf', 'files/contacts/7/0/7/1/0/100/invoice.pdf', 'cache/document/sales/quote/7/10/quote.pdf', 'cache/item/7/501/preview.pdf', 'cache/itemlist/7/503/preview.pdf', 'files/items/7/0/7/5/0/501/specification.pdf', 'files/downloads/' . (new DEEC_Directory())->getShortUrl($owner) . '/customer.pdf'];
    foreach ($paths as $relative) {
        storedFixture($relative);
        [$status, $body, $controller] = storedResponse($relative);
        check($status === 200 && $body === 'Private stored file fixture', 'Authorized file failed: ' . $relative);
        check(storedHeader($controller, 'Cache-Control') === 'private, no-store' && storedHeader($controller, 'X-Content-Type-Options') === 'nosniff', 'Private file headers missing');
        check(storedHeader($controller, 'Content-Type') === 'application/octet-stream' && str_starts_with(storedHeader($controller, 'Content-Disposition'), 'attachment;'), 'Unknown MIME rendered inline');
        check(storedResponse($relative, 'HEAD')[1] === '', 'HEAD exposed a body');
        check(storedResponse($relative, 'POST')[0] === 405, 'Unsafe file method accepted');
    }
    foreach (['files/contacts/8/0/8/1/0/102/foreign.pdf', 'files/contacts/contacts.pdf', 'files/contacts/100/notes.txt', 'files/uploads/unmapped.pdf', 'files/import/data.csv', 'cache/item/501.pdf', 'cache/document/sales/quote/quote.pdf', 'cache/document/sales/quote/8/10/quote.pdf', 'files/images/unmapped.png'] as $relative) {
        storedFixture($relative);
        check(storedResponse($relative)[0] === 404, 'Foreign or unmapped file accepted: ' . $relative);
    }
    $db = new Contacts_Model_DbTable_Emailattachment();
    $adapter->rows['permission'][0]['sales'] = '{"quote":["view"]}';
    check(storedResponse('files/contacts/7/0/7/1/0/100/invoice.pdf')[0] === 404, 'Contact view permission exposed an invoice');
    storedFixture('files/contacts/7/0/7/1/0/100/copy/invoice.pdf');
    check(storedResponse('files/contacts/7/0/7/1/0/100/copy/invoice.pdf')[0] === 404, 'Copied document bypassed its original permission');
    $media = new StoredFileMediaController(new Zend_Controller_Request_Http(), new Zend_Controller_Response_Http());
    check(!$media->allowed('contacts/7/0/7/1/0/100', 'contacts/7/0/7/1/0/100/invoice.pdf'), 'Manager bypassed invoice permissions');
    check($media->allowed('contacts/7/0/7/1/0/100', 'contacts/7/0/7/1/0/100/quote.pdf'), 'Manager denied authorized document read');
    check(!$media->allowed('contacts/7/0/7/1/0/100', 'contacts/7/0/7/1/0/100/quote.pdf', 'edit'), 'Manager bypassed document write permission');
    check(storedResponse('files/contacts/7/0/7/1/0/100/quote.pdf')[0] === 200, 'Quote permission lost');
    $adapter->rows['invoice'][0]['deleted'] = 1;
    check(storedResponse('files/contacts/7/0/7/1/0/100/invoice.pdf')[0] === 404, 'Deleted invoice released as an ordinary contact file');
    $adapter->rows['invoice'][0]['filename'] = 'quote.pdf';
    check(storedResponse('files/contacts/7/0/7/1/0/100/quote.pdf')[0] === 404, 'Ambiguous document filename released');
    $adapter->rows['permission'][0]['contacts'] = '{}';
    check(storedResponse($contact . 'notes.txt')[0] === 404, 'Missing contact permission accepted');
    Zend_Registry::getInstance()->offsetUnset('User');
    Zend_Registry::getInstance()->offsetUnset('Client');
    foreach ($paths as $relative) check(storedResponse($relative)[0] === 404, 'Anonymous file exposed: ' . $relative);
}

setupStoredFiles();
$user = Zend_Registry::get('User');
Zend_Registry::set('User', $user + ['admin' => 1]);
$media = new StoredFileMediaController(new Zend_Controller_Request_Http(), new Zend_Controller_Response_Http());
check($media->folder('shops', 'category', 800, 'image') === 'category' && $media->folder('shops', 'category', 800, 'download') === 'download', 'Authorized media context lost');
check($media->folder('shops', 'category', 999, 'image') === null && $media->folder('shops', 'invoice', 800, 'image') === null, 'Unknown media parent accepted');
Zend_Registry::set('User', $user);
$media = new StoredFileMediaController(new Zend_Controller_Request_Http(), new Zend_Controller_Response_Http());
check($media->folder('shops', 'category', 800, 'image') === null && $media->folder('items', 'item', 501, 'image') === 'images', 'Media permission check failed');
check($media->folder('items', 'item', 502, 'image') === null, 'Cross-tenant media parent accepted');

$adapter->rows['permission'][0]['sales'] = '{"quote":["view"]}';
storedFixture('files/contacts/7/0/7/1/0/100/copy/invoice.pdf');
$managerSession = new Zend_Session_Namespace('FileManager');
$managerSession->context = ['userid' => 42, 'clientid' => 7, 'ownerid' => 7, 'module' => 'contacts', 'controller' => 'contact', 'action' => 'edit', 'id' => 100];
$managerSession->subfolder = 'contacts/7/0/7/1/0/100';
$managerSession->writable = true;
$managerSession->csrf_token = str_repeat('a', 64);
Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
Zend_Auth::getInstance()->getStorage()->write((object)['id' => 42]);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['path' => 'contacts/7/0/7/1/0/100/copy', 'csrf_token' => str_repeat('a', 64)];
$_GET = ['action' => 'delete_folder'];
$request = (new Zend_Controller_Request_Http())->setModuleName('default')->setControllerName('media')->setActionName('manager')->setParams(['managerName' => 'FileManager', 'managerScript' => 'execute']);
$response = new Zend_Controller_Response_Http();
(new StoredFileMediaController($request, $response))->managerAction();
check($response->getHttpResponseCode() === 403 && is_file(BASE_PATH . '/files/contacts/7/0/7/1/0/100/copy/invoice.pdf'), 'Recursive manager operation bypassed document permission');
$managerSession->context = array_replace($managerSession->context, ['module' => 'users', 'controller' => 'user', 'id' => 42]);
$managerSession->subfolder = 'uploads/7/0/7/users/42';
storedFixture('files/uploads/7/0/7/users/42/own.txt');
storedFixture('files/uploads/7/0/7/users/43/other.txt');
check(storedResponse('files/uploads/7/0/7/users/42/own.txt')[0] === 200, 'Prepared own user upload inaccessible');
check(storedResponse('files/uploads/7/0/7/users/43/other.txt')[0] === 404, 'Sibling user upload exposed');
$managerSession->subfolder = 'uploads/7/0/7';
check(storedResponse('files/uploads/7/0/7/users/43/other.txt')[0] === 404, 'Legacy common upload folder granted a user blanket access');

$scope = 'files/contacts/7/0/7/1/0/100/';
storedFixture($scope . 'safe.pdf', "%PDF-1.4\nfixture\n");
symlink(BASE_PATH . '/' . $scope . 'safe.pdf', BASE_PATH . '/' . $scope . 'linked.pdf');
symlink(BASE_PATH . '/' . $scope, BASE_PATH . '/files/contacts/alias');
foreach ([$scope . 'linked.pdf', 'files/contacts/alias/safe.pdf', $scope . '../100/safe.pdf', $scope . '%2e%2e/100/safe.pdf', $scope . '%252e%252e/safe.pdf', $scope . 'safe.pdf%00', $scope . 'safe.pdf/extra', $scope . '\\safe.pdf'] as $relative) {
    check(storedResponse($relative)[0] === 404, 'Traversal or symlink accepted: ' . $relative);
}
[$status, $body, $controller] = storedResponse($scope . 'safe.pdf');
check($status === 200 && storedHeader($controller, 'Content-Type') === 'application/pdf' && str_starts_with(storedHeader($controller, 'Content-Disposition'), 'inline;') && str_starts_with(storedHeader($controller, 'Content-Security-Policy'), 'sandbox;'), 'Protected PDF preview failed');

$upload = storedFixture('incoming-pdf', "%PDF-1.4\nfixture\n");
check(DEEC_Directory::validStoredUpload('document.pdf', $upload), 'Valid manager document refused');
foreach (['.htaccess', '.user.ini', 'evil.php', 'evil.php.png', 'evil.phtml.pdf', 'evil.phar', 'evil.exe', '../evil.pdf', "bad\n.pdf", 'file.pdf.'] as $name) check(!DEEC_Directory::validStoredUpload($name, $upload), 'Unsafe upload name accepted: ' . $name);
check(!DEEC_Directory::validStoredUpload('fake.png', $upload), 'Spoofed image type accepted');
$script = storedFixture('incoming-script', '<?php echo "secret";');
check(!DEEC_Directory::validStoredUpload('innocent.txt', $script), 'Script content accepted');
$large = fopen(BASE_PATH . '/incoming-large', 'w');
ftruncate($large, 10240001);
fclose($large);
check(!DEEC_Directory::validStoredUpload('large.pdf', BASE_PATH . '/incoming-large'), 'Oversized manager upload accepted');

setupStoredFiles();
$download = new StoredDownloadController(new Zend_Controller_Request_Http(), new Zend_Controller_Response_Http());
$fixture = uploadFixture('catalogue.pdf');
AttachmentTestController::$upload->files = ['file_0_' => $fixture];
check($download->receiveFixture(BASE_PATH . '/files/downloads/7/0/7')['name'] === 'catalogue.pdf', 'Valid download catalogue upload failed');
$original = file_get_contents(BASE_PATH . '/files/downloads/7/0/7/catalogue.pdf');
check($download->receiveFixture(BASE_PATH . '/files/downloads/7/0/7') === null && file_get_contents(BASE_PATH . '/files/downloads/7/0/7/catalogue.pdf') === $original, 'Catalogue upload overwrote an existing file');
foreach (['evil.php', '../outside.pdf', 'fake.png'] as $name) {
    AttachmentTestController::$upload->files = ['file_0_' => array_replace($fixture, ['name' => $name])];
    $received = AttachmentTestController::$upload->receives;
    check($download->receiveFixture(BASE_PATH . '/files/downloads/7/0/7') === null && AttachmentTestController::$upload->receives === $received, 'Invalid catalogue upload reached storage');
}
$model = new Contacts_Model_DbTable_Download();
check($model->getDownload(700)['clientid'] === 7, 'Tenant-scoped download metadata unavailable');
$adapter->rows['download'][0]['clientid'] = 8;
check($model->getDownload(700) === false, 'Foreign download metadata readable');
$snapshot = $adapter->rows['download'];
$model->updateDownload(700, ['title' => 'tampered']);
check($adapter->rows['download'] === $snapshot, 'Foreign download metadata written');
check(str_contains(file_get_contents(BASE_PATH . '/file-access-test.log'), 'Invalid download file') && str_contains(file_get_contents(BASE_PATH . '/file-access-test.log'), 'Stack trace:'), 'Download upload failure lost its internal technical log');

storedFixture('files/images/internal.png');
check(storedResponse('files/images/internal.png')[0] === 200, 'Metadata-mapped internal preview unavailable');
$helper = new Application_Controller_Action_Helper_Directory();
check($helper->getUrl(100) === '7/0/7/1/0/100', 'Directory helper changed valid paths');
foreach (['../outside', '10/../../outside', -1, 'bad'] as $id) {
    try { $helper->getUrl($id); throw new LogicException('Invalid directory ID accepted'); }
    catch (InvalidArgumentException $exception) { $checks++; }
    try { (new DEEC_Directory())->getUrl($id, 7); throw new LogicException('Invalid DEEC directory ID accepted'); }
    catch (InvalidArgumentException $exception) { $checks++; }
}

// The tenant's media directory is public without metadata, a user or a client session.
setupStoredFiles();
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 1, 'clientid' => 7, 'logo' => 'logo.png']));
Zend_Registry::getInstance()->offsetUnset('User');
Zend_Registry::getInstance()->offsetUnset('Client');
$adapter->denyReads = true;
$public = ['images/product.png', 'category/category.png', 'category/unpublished.png', 'slide/slide.png', 'download/data.pdf', 'downloads/legacy.pdf', 'header/logo.png', 'header/kaeltemaschine.jpg', 'header/another.png', 'custom/nested/banner.png', 'root.txt', 'images/unmapped.png', 'downloads/unmapped.pdf'];
foreach ($public as $relative) {
    $contents = str_ends_with($relative, '.pdf') ? "%PDF-1.4\nPublic fixture\n" : base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jXioAAAAASUVORK5CYII=');
    storedFixture('media/7/0/7/' . $relative, $contents);
    foreach (['media/' . $relative, 'media/7/0/7/' . $relative] as $url) {
        [$status, $body, $controller] = storedResponse($url);
        check($status === 200 && $body === $contents && storedHeader($controller, 'Cache-Control') === 'public, max-age=3600', 'Published media unavailable: ' . $url);
        check(storedHeader($controller, 'X-Content-Type-Options') === 'nosniff' && str_starts_with(storedHeader($controller, 'Content-Security-Policy'), 'sandbox;'), 'Public file protection headers missing');
        check(storedResponse($url, 'HEAD')[1] === '' && storedResponse($url, 'POST')[0] === 405, 'Public file method handling failed');
    }
}
check(storedResponse('media/downloads/data.pdf')[0] === 200, 'Legacy plural category URL did not resolve to its stored download folder');
check(storedResponse('files/images/internal.png')[0] === 404, 'Internal preview became public');
foreach (['media/8/0/8/images/foreign.png', 'media/0/7/7/header/logo.png', 'media/07/0/7/header/logo.png', 'media/images/global.png', 'files/images/logo.png', 'cache/internal.txt', 'cache/thumbs/files/private.png'] as $relative) {
    storedFixture($relative);
    check(storedResponse($relative)[0] === 404, 'Foreign or private file exposed: ' . $relative);
}
foreach (['media/header/missing.png', 'media/', 'media/header/', 'media/header/../root.txt', 'media/header/%2e%2e/root.txt', 'media/header/%252e%252e/root.txt', 'media/header//logo.png', 'media/header/logo.png%00', 'media/header/..\\root.txt'] as $relative) check(storedResponse($relative)[0] === 404, 'Invalid media path accepted: ' . $relative);
foreach (['hidden/.secret', '.internal/file.txt', 'header/evil.php', 'header/evil.PHP.png', 'header/script.sh', 'header/script.py', 'header/config.inc', 'header/database.ini.bak', 'header/export.sql.gz', 'header/error.log.1'] as $relative) {
    storedFixture('media/7/0/7/' . $relative);
    check(storedResponse('media/' . $relative)[0] === 404, 'Unsafe media name accepted: ' . $relative);
}
foreach (['php.txt' => '<?php echo "secret";', 'shell.txt' => "#!/bin/sh\necho secret\n", 'active.html' => '<!DOCTYPE html><html><script>alert(1)</script></html>'] as $name => $contents) {
    storedFixture('media/7/0/7/header/' . $name, $contents);
    check(storedResponse('media/header/' . $name)[0] === 404, 'Unsafe media content exposed: ' . $name);
}
storedFixture('media/7/0/7/custom/empty.txt', '');
check(storedResponse('media/custom/empty.txt')[0] === 200, 'Empty regular media file unavailable');
storedFixture('media/7/0/7/custom/large.txt', str_repeat('a', 10240001));
check(storedResponse('media/custom/large.txt')[0] === 200, 'Upload size limit incorrectly applied to stored media');
storedFixture('media/7/0/7/custom/active.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
[$status, $body, $controller] = storedResponse('media/custom/active.svg');
check($status === 200 && storedHeader($controller, 'Content-Type') === 'image/svg+xml' && str_starts_with(storedHeader($controller, 'Content-Security-Policy'), 'sandbox;'), 'SVG media lost its sandbox');
symlink(BASE_PATH . '/media/7/0/7/header/logo.png', BASE_PATH . '/media/7/0/7/header/linked.png');
symlink(BASE_PATH . '/media/8/0/8/images', BASE_PATH . '/media/7/0/7/foreign');
symlink(BASE_PATH . '/files', BASE_PATH . '/media/7/0/7/business');
foreach (['header/linked.png', 'foreign/foreign.png', 'business/images/internal.png'] as $relative) check(storedResponse('media/' . $relative)[0] === 404, 'Media symlink accepted: ' . $relative);
storedFixture('cache/thumbs/media/7/0/7/images/product.png');
check(storedResponse('cache/thumbs/media/7/0/7/images/product.png')[0] === 200, 'Public thumbnail unavailable');
check(storedResponse('cache/thumbs/media/images/product.png')[0] === 200, 'Flat public thumbnail unavailable');
storedFixture('cache/thumbs/media/7/0/7/custom/orphan.png');
check(storedResponse('cache/thumbs/media/custom/orphan.png')[0] === 404, 'Thumbnail without a source became public');
storedFixture('cache/thumbs/media/7/0/7/header/php.txt');
check(storedResponse('cache/thumbs/media/header/php.txt')[0] === 404, 'Thumbnail of unsafe source became public');
storedFixture('cache/thumbs/media/7/0/7/header/logo.png', '<?php echo "secret";');
check(storedResponse('cache/thumbs/media/header/logo.png')[0] === 404, 'Executable thumbnail became public');
symlink(BASE_PATH . '/media/7/0/7/header', BASE_PATH . '/cache/thumbs/media/7/0/7/linked');
storedFixture('media/7/0/7/linked/logo.png');
check(storedResponse('cache/thumbs/media/linked/logo.png')[0] === 404, 'Thumbnail directory symlink accepted');
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 2, 'clientid' => 8]));
check(storedResponse('media/7/0/7/images/product.png')[0] === 404, 'Wrong shop served a published image from another tenant');
check(storedResponse('cache/thumbs/media/7/0/7/images/product.png')[0] === 404, 'Wrong shop served a thumbnail from another tenant');
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 120, 'clientid' => 100, 'logo' => 'renocold.png']));
foreach (['header/kaeltemaschine.jpg', 'category/kaltwassererzeuger.jpg', 'header/renocold.png', 'header/extra.png', 'other/nested/banner.png'] as $relative) {
    storedFixture('media/1/0/100/' . $relative);
    check(storedResponse('media/' . $relative)[0] === 200 && storedResponse('media/1/0/100/' . $relative)[0] === 200, 'Shop 120 media mapping failed: ' . $relative);
}
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 1, 'clientid' => 0]));
check(storedResponse('media/header/logo.png')[0] === 404, 'Invalid tenant media mapping accepted');
Zend_Registry::getInstance()->offsetUnset('SiteContext');
check(storedResponse('media/7/0/7/header/logo.png')[0] === 404, 'Media published without a site context');
$adapter->denyReads = false;

// The shop quote grant is bound to this session, site, tenant, object and exact file.
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 1, 'clientid' => 7]));
$session = new Zend_Session_Namespace('MultiStepForm');
$relative = 'files/contacts/7/0/7/1/0/100/quote.pdf';
$session->downloadFile = ['siteid' => 1, 'clientid' => 7, 'quoteid' => 10, 'relative' => $relative];
check(storedResponse($relative)[0] === 200, 'Guest own inquiry quote unavailable');
check(storedResponse(str_replace('quote.pdf', 'invoice.pdf', $relative))[0] === 404, 'Inquiry grant exposed another document');
$session->downloadFile = array_replace($session->downloadFile, ['siteid' => 2]);
check(storedResponse($relative)[0] === 404, 'Inquiry grant crossed shops');
unset($session->downloadFile);
check(storedResponse($relative)[0] === 404, 'Guest quote remained accessible after grant removal');
check($adapter->writes === [], 'File reads wrote database data');

echo "PASS: $checks stored file, tenant, publication, manager, upload and header checks\n";
