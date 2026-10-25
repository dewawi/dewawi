<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/documents/pdf.php (temporary files and in-memory database).
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class): void {
    if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path;
});

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

class DocumentPdfTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    public array $rows = [];
    protected function _connect() {}
    protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
    public function describeTable($tableName, $schemaName = null)
    {
        $metadata = [];
        foreach (array_keys($this->rows[$tableName][0]) as $column) {
            $metadata[$column] = ['COLUMN_NAME' => $column, 'DATA_TYPE' => 'varchar', 'PRIMARY' => $column === 'id', 'PRIMARY_POSITION' => 1, 'IDENTITY' => $column === 'id'];
        }
        return $metadata;
    }
    public function query($sql, $bind = [])
    {
        if (!$sql instanceof Zend_Db_Table_Select || $bind) throw new RuntimeException('Database writes are forbidden');
        $sql->assemble();
        $from = $sql->getPart(Zend_Db_Select::FROM);
        $table = reset($from)['tableName'];
        $rows = array_values(array_filter($this->rows[$table], static function ($row) use ($sql): bool {
            foreach ($sql->getPart(Zend_Db_Select::WHERE) as $where) {
                if (!preg_match('/^\(?([a-z]+) = \'?(\d+)\'?\)?$/', preg_replace('/^AND /', '', $where), $match)) {
                    throw new RuntimeException('Unsupported fixture predicate');
                }
                if ((string)$row[$match[1]] !== $match[2]) return false;
            }
            return true;
        }));
        return new class(array_slice($rows, 0, 1)) {
            public function __construct(private array $rows) {}
            public function fetchAll($mode) { return $this->rows; }
        };
    }
}

class DocumentPdfTestTable extends DEEC_Model_DbTable_Entity {}

class DocumentPdfTestGenerator extends DEEC_Pdf
{
    public int $renders = 0;
    public int $resolutions = 0;
    public int $bootstraps = 0;
    public string $text = 'PDF fixture';
    public bool $failResolution = false;
    public ?array $duringRender = null;
    public ?array $duringPublish = null;
    public array $lastOptions = [];
    protected function getDocumentDb($module, string $controller)
    {
        return new DocumentPdfTestTable(['name' => $controller]);
    }
    protected function bootstrapTcpdf(): void
    {
        global $root;
        $this->bootstraps++;
        require_once $root . '/library/Tcpdf/config/tcpdf_config.php';
        require_once $root . '/library/Tcpdf/tcpdf.php';
    }
    protected function resolveDocumentPayloadData(string $module, string $controller, array $document, array $options = []): array
    {
        $this->resolutions++;
        $this->lastOptions = $options;
        if ($this->failResolution) throw new RuntimeException('Template unavailable');
        return ['document' => $document, 'template' => [], 'settings' => []];
    }
    protected function renderPayload(TCPDF $pdf, array $payload): void
    {
        $this->renders++;
        $pdf->Write(0, $this->text);
        if ($this->duringRender) {
            [$path, $contents] = $this->duringRender;
            check(!is_file($path), 'Race fixture already exists');
            file_put_contents($path, $contents);
        }
    }
    protected function publishPdfFile(string $tempFile, array $file, array $payload, string $source): void
    {
        if ($this->duringPublish) {
            [$path, $contents] = $this->duringPublish;
            check(!is_file($path), 'Publish race fixture already exists');
            file_put_contents($path, $contents);
        }
        parent::publishPdfFile($tempFile, $file, $payload, $source);
    }
    public function outputForTest(TCPDF $pdf, array $request): array { return $this->outputPdf($pdf, $this->buildPayload($request)); }
}

require_once $root . '/application/modules/sales/models/DbTable/Invoice.php';

class DocumentPdfTestController extends DEEC_Controller_DocumentAction
{
    public function init() {}
    protected function getDb(): DEEC_Model_DbTable_Entity { return new DocumentPdfTestTable(['name' => 'invoice']); }
    protected function sendPdfResponse(array $result) { return $result; }
    public function ensureOriginal(): void { $this->beforeView($this->loadRow(1)); }
}

function removePdfTestFiles(string $path): void
{
    if (!is_dir($path)) { unlink($path); return; }
    foreach (new FilesystemIterator($path) as $entry) removePdfTestFiles($entry->getPathname());
    rmdir($path);
}

$testPath = sys_get_temp_dir() . '/dewawi-document-pdf-' . bin2hex(random_bytes(8));
mkdir($testPath);
define('BASE_PATH', $testPath);
register_shutdown_function(static function () use ($testPath): void { removePdfTestFiles($testPath); });
ini_set('error_log', $testPath . '/pdf.log');
$adapter = new DocumentPdfTestAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('Client', ['id' => 7]);
Zend_Registry::set('User', ['id' => 42]);
Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer')->view = new Zend_View();

$document = [
    'id' => 1, 'clientid' => 7, 'contactid' => 12, 'deleted' => 0, 'filename' => 'original.pdf',
    'state' => 105, 'completed' => 1, 'completeddate' => '2026-09-09 12:00:00', 'completedby' => 42,
    'modified' => '2026-09-10 12:00:00', 'modifiedby' => 42,
];
$originalDir = BASE_PATH . '/files/contacts/7/0/7/1/2/12';
mkdir($originalDir, 0775, true);
$original = $originalDir . '/original.pdf';
$originalBytes = "%PDF-1.4\nOriginal issued document\n%%EOF\n";

// Existing files must work even when the current template or TCPDF is unavailable.
foreach (['sales' => ['quote', 'invoice', 'salesorder', 'deliveryorder', 'creditnote', 'reminder'], 'purchases' => ['quoterequest', 'purchaseorder']] as $module => $controllers) {
    foreach ($controllers as $controller) {
        $adapter->rows[$controller] = [$document + [$controller . 'id' => 1001]];
        file_put_contents($original, $originalBytes);
        touch($original, 1000000000);
        $generator = new DocumentPdfTestGenerator();
        $generator->failResolution = true;
        foreach (['download', 'inline', 'file'] as $output) {
            $result = $generator->generate(['module' => $module, 'controller' => $controller, 'documentId' => 1, 'output' => $output, 'storage' => 'cache', 'overwrite' => true, 'templateid' => 999]);
            check($result['path'] === $original && $result['storage'] === 'contact' && $result['output'] === $output && $result['exists'], 'Original file was not selected');
            check($result['url'] === '/files/contacts/7/0/7/1/2/12/original.pdf', 'Original URL changed');
            check(file_get_contents($original) === $originalBytes && filemtime($original) === 1000000000, 'Original content or timestamp changed');
        }
        check($generator->bootstraps === 0 && $generator->resolutions === 0 && $generator->renders === 0, 'Existing original triggered generation');
    }
}

$request = ['module' => 'sales', 'controller' => 'invoice', 'documentId' => 1, 'output' => 'download', 'storage' => 'cache', 'overwrite' => true, 'templateid' => 999];
$issued = $document + ['invoiceid' => 1001];

// A number protects legacy and cancelled documents even with missing lifecycle data.
foreach ([100, 105, 106] as $state) {
    $legacy = array_replace($issued, ['state' => $state, 'completed' => 0, 'completeddate' => null, 'completedby' => 0]);
    $adapter->rows['invoice'] = [$legacy];
    check((new DocumentPdfTestGenerator())->generate($request)['path'] === $original, 'Legacy or cancelled original was regenerated');
    check($adapter->rows['invoice'] === [$legacy], 'Legacy completion data was changed');
}

// Keep the real tenant/deleted predicates ahead of any access to stored files.
foreach ([array_replace($issued, ['clientid' => 8]), array_replace($issued, ['deleted' => 1])] as $denied) {
    $adapter->rows['invoice'] = [$denied];
    $generator = new DocumentPdfTestGenerator();
    try {
        $generator->generate($request + ['clientid' => 8]);
        throw new LogicException('Inaccessible document was returned');
    } catch (RuntimeException $expected) {
        check($expected->getMessage() === 'Document not found', 'Unexpected access error');
    }
    check($generator->renders === 0 && $generator->resolutions === 0, 'Inaccessible document was rendered');
    check(file_get_contents($original) === $originalBytes, 'Access failure changed original');
}

// Do not guess storage information or allow a filename to escape its contact folder.
foreach (['', '../original.pdf', '..\\original.pdf', "original\0.pdf"] as $filename) {
    $adapter->rows['invoice'] = [array_replace($issued, ['filename' => $filename])];
    try {
        (new DocumentPdfTestGenerator())->generate($request);
        throw new LogicException('Invalid filename was accepted');
    } catch (RuntimeException $expected) {
        check(in_array($expected->getMessage(), ['Invalid PDF filename', 'Issued document storage is incomplete'], true), 'Unexpected filename error');
    }
}

// Recover an existing attachment before considering the current template.
$adapter->rows['invoice'] = [$issued];
unlink($original);
$attachmentDir = BASE_PATH . '/files/attachments/sales/invoice/7/0/7/1/0/1';
mkdir($attachmentDir, 0775, true);
$attachment = $attachmentDir . '/original.pdf';
file_put_contents($attachment, $originalBytes);
$generator = new DocumentPdfTestGenerator();
$generator->failResolution = true;
check($generator->generate($request)['path'] === $original && file_get_contents($original) === $originalBytes, 'Original attachment was not recovered');
check($generator->bootstraps === 0 && $generator->resolutions === 0, 'Attachment recovery loaded a template');
check(file_get_contents($attachment) === $originalBytes && $adapter->rows['invoice'] === [$issued], 'Attachment recovery changed source or metadata');
check(str_contains(file_get_contents($testPath . '/pdf.log'), 'source=attachment'), 'Attachment recovery was not logged');
unlink($attachment);
unlink($original);

// Issued invoices and credit notes cannot be reconstructed from mutable current data.
foreach (['invoice', 'creditnote'] as $controller) {
    $saved = $document + [$controller . 'id' => 1001];
    $adapter->rows[$controller] = [$saved];
    $generator = new DocumentPdfTestGenerator();
    try {
        $generator->generate(array_replace($request, ['controller' => $controller]));
        throw new LogicException('Missing original was silently reconstructed');
    } catch (RuntimeException $expected) {
        check($expected->getMessage() === 'Original PDF is unavailable', 'Unexpected missing-original error');
    }
    check($generator->bootstraps === 0 && $generator->renders === 0 && !is_file($original), 'Missing original used current template or data');
    check($adapter->rows[$controller] === [$saved], 'Missing original changed completion data');

    $archiveDir = BASE_PATH . '/files/attachments/sales/' . $controller . '/7/0/7/1/0/1';
    if (!is_dir($archiveDir)) mkdir($archiveDir, 0775, true);
    file_put_contents($archiveDir . '/original.pdf', $originalBytes);
    check($generator->generate(array_replace($request, ['controller' => $controller]))['path'] === $original, 'Archived original was not restored');
    check(file_get_contents($original) === $originalBytes && $generator->renders === 0, 'Recovery changed original bytes');
    $adapter->rows[$controller][0]['notes'] = 'Changed internal note';
    $generator->generate(array_replace($request, ['controller' => $controller]));
    check(file_get_contents($original) === $originalBytes && $generator->renders === 0, 'Note change or repeated download regenerated the original');
    unlink($archiveDir . '/original.pdf');
    unlink($original);
}
$adapter->rows['invoice'] = [$issued];

// Initial finalization can publish the original once in the canonical location.
$initialRequest = $request + ['createOriginal' => true];
$generator = new DocumentPdfTestGenerator();
$restored = $generator->generate($initialRequest);
check($generator->renders === 1 && $generator->lastOptions === ['templateid' => null], 'Restore accepted a template override');
check($restored['path'] === $original && str_starts_with(file_get_contents($original), '%PDF-'), 'Missing original was not restored as a PDF');
check((fileperms($original) & 0777) === (0666 & ~umask()), 'Restored file permissions changed');
$hash = hash_file('sha256', $original);
$generator->failResolution = true;
check($generator->generate($request)['path'] === $original && hash_file('sha256', $original) === $hash && $generator->renders === 1, 'Restored original was regenerated');
check($adapter->rows['invoice'] === [$issued], 'Restore changed document metadata');
check(str_contains(file_get_contents($testPath . '/pdf.log'), 'source=render'), 'Missing original generation was not logged');

// A competing request that finishes during rendering wins without being overwritten.
unlink($original);
$generator = new DocumentPdfTestGenerator();
$generator->duringRender = [$original, $originalBytes];
$generator->generate($initialRequest);
check(file_get_contents($original) === $originalBytes, 'Concurrent original was overwritten');
check(glob($originalDir . '/.pdf-*') === [], 'Temporary PDFs were left behind');

// Also cover the race between the last existence check and atomic publication.
unlink($original);
$generator = new DocumentPdfTestGenerator();
$generator->duringPublish = [$original, $originalBytes];
$generator->generate($initialRequest);
check(file_get_contents($original) === $originalBytes, 'Original created immediately before publication was overwritten');
check(glob($originalDir . '/.pdf-*') === [], 'Publish race left temporary PDFs behind');

// A failed restore must not publish an empty original or change completion data.
unlink($original);
$generator = new DocumentPdfTestGenerator();
$generator->failResolution = true;
try {
    $generator->generate($initialRequest);
    throw new LogicException('Missing template did not fail');
} catch (RuntimeException $expected) {
    check($expected->getMessage() === 'Template unavailable', 'Unexpected template failure');
}
check(!is_file($original) && $adapter->rows['invoice'] === [$issued], 'Failed restore changed original or metadata');

class DocumentPdfFailedOutput extends TCPDF
{
    public function Output($name = 'doc.pdf', $dest = 'I')
    {
        file_put_contents($name, 'Incomplete output');
        throw new RuntimeException('Output failed');
    }
}
try {
    (new DocumentPdfTestGenerator())->outputForTest(new DocumentPdfFailedOutput(), $request);
    throw new LogicException('Output error was ignored');
} catch (RuntimeException $expected) {
    check($expected->getMessage() === 'Output failed', 'Unexpected output error');
}
check(!is_file($original) && glob($originalDir . '/.pdf-*') === [], 'Failed output published an incomplete PDF or left temporary files');

// Draft previews remain replaceable even if a copied draft carries an old filename.
file_put_contents($original, $originalBytes);
$adapter->rows['invoice'] = [array_replace($issued, ['invoiceid' => null, 'state' => 100, 'completed' => 0])];
$generator = new DocumentPdfTestGenerator();
$draft = $generator->generate($request);
check($draft['storage'] === 'cache' && $draft['path'] !== $original && $generator->lastOptions === ['templateid' => 999], 'Draft preview behavior changed');
$firstHash = hash_file('sha256', $draft['path']);
$generator->text = 'Changed draft';
$generator->generate($request);
check($generator->renders === 2 && hash_file('sha256', $draft['path']) !== $firstHash, 'Draft preview was frozen');
check(file_get_contents($original) === $originalBytes, 'Draft preview overwrote an original');

// Exercise the real controller download, preview and view preparation paths.
$adapter->rows['invoice'] = [$issued];
$controllerRequest = new Zend_Controller_Request_Http();
$controllerRequest->setModuleName('sales')->setControllerName('invoice')->setActionName('download')->setParam('id', 1);
Zend_Controller_Front::getInstance()->setControllerDirectory([
    'default' => $root . '/application/controllers',
    'sales' => $root . '/application/modules/sales/controllers',
]);
$controller = new DocumentPdfTestController($controllerRequest, new Zend_Controller_Response_Http());
check($controller->downloadAction()['path'] === $original, 'Download controller did not return original');
$controllerRequest->setActionName('preview')->setParam('templateid', 999);
check($controller->previewAction()['path'] === $original, 'Preview controller did not return original');
$controller->ensureOriginal();
check(file_get_contents($original) === $originalBytes && $adapter->rows['invoice'] === [$issued], 'View preparation changed original or metadata');

echo 'PASS: ' . $checks . " PDF checks; originals preserved, missing files restored, tenant isolation retained\n";
