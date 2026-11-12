<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$socket = $argv[1] ?? '';
$work = realpath(dirname($socket));
if (!$work || !preg_match('~^/tmp/dewawi-gobd-export-db-[a-zA-Z0-9_]+$~D', $work) || $socket !== $work . '/mysql.sock') throw new RuntimeException('Only an isolated GoBD test socket is accepted');
require __DIR__ . '/document-recovery.php';
umask(0077);
$root = dirname(__DIR__, 2);
if (in_array($argv[2] ?? '', ['--large-export', '--large-review'], true)) {
	$db = recoveryBoot(migrationConfig($work . '/database.ini', 'production') + ['unix_socket' => $socket], $work . '/source', 7, 42);
	$name = (new DEEC_Export_Gobd($db, 7, 42))->export(BASE_PATH . '/files/export/7/0/7/', '2030-01-01', '2030-12-31', true, $argv[2] === '--large-review');
	echo json_encode(['name' => $name, 'peak' => memory_get_peak_usage(true)], JSON_THROW_ON_ERROR);
	exit;
}
$pdo = new PDO('mysql:unix_socket=' . $socket . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE gobd_fixture');
$pdo->exec('USE gobd_fixture');
$pdo->exec(file_get_contents($root . '/install/config/structure.sql'));
$checks = 0;
function exportCheck(bool $condition, string $message): void { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; }
function exportSeed(string $table, array $values): void
{
	global $pdo;
	foreach ($pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) as $column) {
		if ($column['Field'] === 'id' || $column['Null'] === 'YES' || $column['Default'] !== null || array_key_exists($column['Field'], $values)) continue;
		$values[$column['Field']] = preg_match('/int|decimal|float|double/', $column['Type']) ? 0 : '';
	}
	$pdo->prepare('INSERT INTO `' . $table . '` (`' . implode('`,`', array_keys($values)) . '`) VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')')->execute(array_values($values));
}
function exportReject(callable $operation, string $message): void
{
	try { $operation(); } catch (RuntimeException | InvalidArgumentException $expected) { exportCheck(true, $message); return; }
	throw new RuntimeException($message);
}
function exportCsv(ZipArchive $zip, string $file): array
{
	$stream = $zip->getStream($file);
	$fields = fgetcsv($stream, 0, ';', '"', '');
	$rows = [];
	while (($row = fgetcsv($stream, 0, ';', '"', '')) !== false) $rows[] = array_combine($fields, $row);
	fclose($stream);
	return $rows;
}
exportSeed('client', ['id' => 70, 'company' => 'Parent fixture', 'activated' => 1]);
exportSeed('client', ['id' => 7, 'parentid' => 70, 'company' => 'Export fixture', 'activated' => 1]);
exportSeed('client', ['id' => 8, 'company' => 'Foreign fixture', 'activated' => 1]);
exportSeed('user', ['id' => 42, 'clientid' => 7, 'name' => 'Export admin', 'admin' => 1, 'activated' => 1]);
$rights = ['userid' => 42, 'clientid' => 7, 'contacts' => '{"contact":["view"]}'];
foreach (DEEC_DocumentArchive::documents() as $module => $controllers) $rights[$module] = json_encode(array_fill_keys($controllers, ['view']));
exportSeed('permission', $rights);
exportSeed('contact', ['id' => 500, 'clientid' => 70, 'contactid' => 12, 'name1' => 'Shared parent contact']);
$base = $work . '/source';
mkdir($base, 0700);
$destination = $base . '/files/export/7/0/7/';
mkdir($destination, 0700, true);
$pdo->exec("CREATE USER 'gobd_reader'@'localhost' IDENTIFIED BY 'fixture-only'");
$pdo->exec("GRANT SELECT ON gobd_fixture.* TO 'gobd_reader'@'localhost'");
file_put_contents($work . '/database.ini', "[production]\nresources.db.params.host = 'localhost'\nresources.db.params.username = 'gobd_reader'\nresources.db.params.password = 'fixture-only'\nresources.db.params.dbname = 'gobd_fixture'\n");
$db = recoveryBoot(migrationConfig($work . '/database.ini', 'production') + ['unix_socket' => $socket], $base, 7, 42);
$archive = new DEEC_DocumentArchive($base);
function exportDocument(string $module, string $controller, int $id, array $changes = [], bool $registered = true): array
{
	global $archive, $base;
	$document = array_replace(['id' => $id, 'clientid' => 7, 'contactid' => 12, $controller . 'id' => 10000 + $id, $controller . 'date' => '2020-02-29', 'filename' => $controller . '-' . $id . '.pdf', 'completed' => 1, 'state' => 105], $changes);
	exportSeed($controller, $document);
	if ($registered) {
		$file = $archive->store("%PDF-1.4\nOriginal $module/$controller/$id\n%%EOF\n", $document['clientid'], $module, $controller, $id, 'pdf');
		exportSeed('documentarchive', ['clientid' => $document['clientid'], 'module' => $module, 'controller' => $controller, 'documentid' => $id, 'format' => 'pdf', 'version' => 1, 'filename' => $document['filename'], 'path' => substr($file['path'], strlen($base) + 1), 'filesize' => $file['size'], 'sha256' => $file['sha256'], 'archived' => '2026-11-12 12:00:00', 'archivedby' => 42]);
	}
	return $document;
}
$tables = ['documentarchive', 'contact'];
foreach (DEEC_DocumentArchive::documents() as $module => $controllers) foreach ($controllers as $controller) {
	$tables[] = $controller;
	$tables[] = $controller . 'pos';
	exportDocument($module, $controller, 11, [$controller . 'date' => $controller === 'quote' ? '2019-01-01' : ($controller === 'purchaseorder' ? '2021-12-31' : '2020-02-29'), 'deleted' => $controller === 'quote' ? 1 : 0]);
	exportDocument($module, $controller, 12, [$controller . 'date' => '2022-01-01']);
	exportSeed($controller . 'pos', ['id' => 11, 'parentid' => 11, 'clientid' => 7, 'price' => 100, 'quantity' => 1, 'taxrate' => 19, 'total' => 119]);
}
$pdo->exec("UPDATE invoice SET header = 'Stored quote and slash \\\\ item' WHERE id = 11");
exportDocument('sales', 'invoice', 13, ['filename' => null], false);
exportDocument('sales', 'invoice', 90, ['clientid' => 8]);
exportDocument('sales', 'invoice', 14, [], false);
$archive->store("%PDF-1.4\nunregistered file must not be used\n%%EOF\n", 7, 'sales', 'invoice', 14, 'pdf');
foreach ([16, 17, 18, 19, 20, 21, 22, 23, 24] as $id) exportDocument('sales', 'invoice', $id, in_array($id, [23, 24], true) ? ['invoiceid' => null, 'state' => 100, 'completed' => 0, 'filename' => $id === 24 ? null : 'draft.pdf'] : [], $id !== 24);
$pdo->exec("UPDATE documentarchive SET sha256 = REPEAT('0', 64) WHERE clientid = 7 AND controller = 'invoice' AND documentid = 16");
$pdo->exec("UPDATE documentarchive SET filesize = filesize + 1 WHERE clientid = 7 AND controller = 'invoice' AND documentid = 17");
$pdo->exec("UPDATE documentarchive SET path = 'files/documents/8/0/8/sales/invoice/0/18.pdf' WHERE clientid = 7 AND controller = 'invoice' AND documentid = 18");
$pdo->exec("UPDATE documentarchive SET version = 2 WHERE clientid = 7 AND controller = 'invoice' AND documentid = 19");
$pdo->exec("UPDATE invoice SET cancelled = 1, completed = 0, state = 106 WHERE id = 22 AND clientid = 7");
foreach ([20, 21] as $id) {
	$paths = $archive->paths(['id' => $id, 'clientid' => 7, 'module' => 'sales', 'controller' => 'invoice', 'contactid' => 12, 'contact_record_id' => 500, 'filename' => 'invoice-' . $id . '.pdf']);
	foreach (['current', 'legacy'] as $name) { if (!is_dir(dirname($paths[$name]))) mkdir(dirname($paths[$name]), 0700, true); file_put_contents($paths[$name], "%PDF-1.4\nHistorical $name $id\n%%EOF\n"); }
}
$approved = $pdo->query("SELECT * FROM documentarchive WHERE clientid = 7 AND controller = 'invoice' AND documentid = 21")->fetch(PDO::FETCH_ASSOC);
$proof = ['recovery' => ['approval' => ['manual' => true, 'approved_by' => 42, 'approved_at' => '2026-11-12T12:00:00Z', 'identity' => ['clientid' => 7, 'module' => 'sales', 'controller' => 'invoice', 'id' => 21], 'sha256' => $approved['sha256'], 'size' => (int)$approved['filesize']], 'historical_conflicts' => ['historical_versions_differ'], 'reconstruction' => ['generated_at' => '2026-11-12T11:00:00Z'], 'fiscal_correction' => false]];
$pdo->prepare('UPDATE documentarchive SET parameters = ? WHERE id = ?')->execute([json_encode($proof), $approved['id']]);
$before = [];
foreach ($tables as $table) $before[$table] = $pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$fileBefore = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base . '/files/documents', FilesystemIterator::SKIP_DOTS)) as $file) $fileBefore[$file->getPathname()] = [filesize($file->getPathname()), hash_file('sha256', $file->getPathname()), filemtime($file->getPathname())];
$export = new DEEC_Export_Gobd($db, 7, 42);
class GobdFilenameFixture extends DEEC_Export_Gobd
{
	public function name($number, int $id): string { return $this->documentZipPath('documents', 'sales', 'invoice', ['id' => $id, 'invoiceid' => $number]); }
}
$filenames = new GobdFilenameFixture($db, 7, 42);
exportCheck($filenames->name('110395', 55893) === 'documents/7/sales/invoice/110395.pdf', 'Visible invoice number was replaced with its internal ID');
exportCheck($filenames->name('110395', 55894) === 'documents/7/sales/invoice/110395--id-55894.pdf', 'Duplicate visible number overwrote an entry');
foreach ([null, '', '../110395', 'a/b', 'a\\b', "x\n.pdf", 'CON', '..', str_repeat('a', 200), 'Ä/23'] as $i => $number) {
	$path = $filenames->name($number, 100 + $i);
	exportCheck(dirname($path) === 'documents/7/sales/invoice' && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.pdf$/D', basename($path)) && str_contains($path, '--id-' . (100 + $i)), 'Unsafe or missing number did not receive a safe ID suffix');
}
exportCheck(strtolower($filenames->name('invoice', 201)) !== strtolower($filenames->name('INVOICE', 202)), 'Case-insensitive extraction collided');
$legacyName = $export->export($destination, '2019-01-01', '2021-12-31');
$legacy = new ZipArchive(); $legacy->open($destination . $legacyName, ZipArchive::CHECKCONS);
exportCheck($legacy->locateName('documents/') === false && $legacy->locateName('document_manifest.csv') === false && !isset(json_decode($legacy->getFromName('metadata.json'), true)['documents']), 'Default export unexpectedly requires document images');
$name = $export->export($destination, '2019-01-01', '2021-12-31', true);
$zip = new ZipArchive(); exportCheck($zip->open($destination . $name, ZipArchive::CHECKCONS) === true, 'Document ZIP is invalid');
for ($i = 0; $i < $legacy->numFiles; $i++) {
	$file = $legacy->getNameIndex($i);
	if (str_ends_with($file, '.csv')) exportCheck($legacy->getFromName($file) === $zip->getFromName($file), 'Optional images changed an existing CSV dataset: ' . $file);
}
$manifest = exportCsv($zip, 'document_manifest.csv');
$missing = exportCsv($zip, 'missing_documents.csv');
$index = exportCsv($zip, 'document_index.csv');
exportCheck(count($manifest) === 10 && count($missing) === 8, 'Included/missing image counts differ: ' . count($manifest) . '/' . count($missing));
$types = array_unique(array_column($manifest, 'controller'));
exportCheck(count($types) === 8, 'Optional images omitted a document type');
foreach ($manifest as $row) {
	exportCheck(basename($row['zip_path']) === $row['document_number'] . '.pdf', 'ZIP uses an internal ID instead of the saved visible number');
	exportCheck($row['clientid'] === '7' && $row['document_date'] >= '2019-01-01' && $row['document_date'] <= '2021-12-31', 'Document period or tenant isolation failed');
	exportCheck(hash('sha256', $zip->getFromName($row['zip_path'])) === $row['sha256'] && strlen($zip->getFromName($row['zip_path'])) === (int)$row['filesize'], 'ZIP bytes differ from archive manifest');
	$references = exportCsv($zip, $row['csv_file']);
	exportCheck(in_array($row['csv_key_value'], array_column($references, $row['csv_key_column']), true), 'Document CSV reference is unresolved');
}
exportCheck(count(array_filter($manifest, static fn(array $row) => $row['controller'] === 'quote' && $row['deleted'] === '1')) === 1, 'Deleted issued original was omitted');
exportCheck(count(exportCsv($zip, 'excluded_documents.csv')) === 2, 'Deleted or excluded document inventory is missing');
exportCheck(in_array('historical_versions_differ', array_column($missing, 'reason'), true), 'Unapproved historical conflict was silently exported');
$recovered = array_values(array_filter($manifest, static fn(array $row) => $row['controller'] === 'invoice' && $row['documentid'] === '21'))[0];
exportCheck(str_contains($recovered['archive_parameters'], 'generated_at') && str_contains($recovered['historical_conflicts'], 'historical_versions_differ'), 'Approved recovery provenance or conflict evidence was lost');
$metadata = json_decode($zip->getFromName('metadata.json'), true);
exportCheck($metadata['documents']['summary']['complete'] === false && str_contains($zip->getFromName('readme.txt'), 'INCOMPLETE'), 'Missing images were presented as complete');
foreach (explode("\n", trim($zip->getFromName('checksums.sha256'))) as $line) { [$sha, $file] = explode('  ', $line, 2); exportCheck(hash('sha256', $zip->getFromName($file)) === $sha, 'ZIP checksum manifest differs'); }
$empty = $export->export($destination, '2040-01-01', '2040-12-31', true);
$emptyZip = new ZipArchive(); $emptyZip->open($destination . $empty);
exportCheck(exportCsv($emptyZip, 'document_manifest.csv') === [] && json_decode($emptyZip->getFromName('metadata.json'), true)['documents']['summary']['complete'], 'Empty dated-document scope is invalid'); $emptyZip->close();
foreach ([['2019-02-30', '2021-12-31'], ['2022-01-01', '2021-12-31'], ['2019-1-01', '2021-12-31'], [null, '2021-12-31']] as [$from, $to]) exportReject(static fn() => $export->export($destination, $from, $to, true), 'Invalid export dates were accepted');
$user = Zend_Registry::get('User'); Zend_Registry::set('User', array_replace($user, ['admin' => 0]));
exportReject(static fn() => $export->export($destination, '2019-01-01', '2021-12-31', true), 'Non-admin exported document images'); Zend_Registry::set('User', $user);
exportReject(static fn() => (new DEEC_Export_Gobd($db, 8, 42))->export($destination, '2019-01-01', '2021-12-31', true), 'Foreign tenant exported document images');
$pdo->exec("UPDATE permission SET purchases = '{}' WHERE userid = 42");
exportReject(static fn() => $export->export($destination, '2019-01-01', '2021-12-31', true), 'Missing document permission was ignored');
$pdo->prepare('UPDATE permission SET purchases = ? WHERE userid = 42')->execute([$rights['purchases']]);
class BrokenGobdPackage extends DEEC_Export_Gobd
{
	protected function verifyZip(string $path, array $expected): void { parent::verifyZip($path, $expected); throw new RuntimeException('Fixture post-write failure'); }
}
class BrokenGobdReference extends DEEC_Export_Gobd
{
	protected function createZip(string $filePath, array $files, string $zipFileName): void
	{
		$stream = fopen($filePath . 'document_manifest.csv', 'r'); $rows = [];
		while (($row = fgetcsv($stream, 0, ';', '"', '')) !== false) $rows[] = $row;
		fclose($stream);
		$rows[1][array_search('csv_key_value', $rows[0], true)] = 'missing-csv-key';
		$stream = fopen($filePath . 'document_manifest.csv', 'w');
		foreach ($rows as $row) fputcsv($stream, $row, ';', '"', '');
		fclose($stream);
		parent::createZip($filePath, $files, $zipFileName);
	}
}
class GobdRollbackFixture
{
	public function __construct(private $db) {}
	public function __call($method, $arguments)
	{
		if ($method === 'rollBack') {
			if ($this->db->getConnection()->inTransaction()) $this->db->rollBack();
			throw new RuntimeException('Fixture rollback failure');
		}
		return $this->db->$method(...$arguments);
	}
}
$published = glob($destination . '*.zip');
exportReject(static fn() => (new BrokenGobdPackage($db, 7, 42))->export($destination, '2019-01-01', '2021-12-31', true), 'Incomplete ZIP was published');
exportReject(static fn() => (new BrokenGobdReference($db, 7, 42))->export($destination, '2019-01-01', '2021-12-31', true), 'Unresolved CSV reference was published');
exportReject(static fn() => (new DEEC_Export_Gobd(new GobdRollbackFixture($db), 7, 42))->export($destination, '2019-01-01', '2021-12-31', true), 'Rollback failure published a package');
exportCheck(glob($destination . '*.zip') === $published && glob($destination . '.gobd-*') === [], 'Failed export left public partial files or staging');
require $root . '/application/modules/admin/controllers/ExportController.php';
symlink($root . '/library', $base . '/library');
class GobdFlashFixture extends Zend_Controller_Action_Helper_Abstract
{
	public array $messages = [];
	public function getName() { return 'FlashMessenger'; }
	public function addMessage($message) { $this->messages[] = $message; }
}
class GobdRedirectFixture extends Zend_Controller_Action_Helper_Abstract
{
	public function getName() { return 'Redirector'; }
	public function gotoSimple($action) { return $action; }
}
class GobdLayoutFixture extends Zend_Controller_Action_Helper_Abstract
{
	public function getName() { return 'Layout'; }
	public function disableLayout() {}
}
Zend_Controller_Action_HelperBroker::addHelper(new GobdFlashFixture());
Zend_Controller_Action_HelperBroker::addHelper(new GobdRedirectFixture());
Zend_Controller_Action_HelperBroker::addHelper(new GobdLayoutFixture());
Zend_Controller_Front::getInstance()->setControllerDirectory(['default' => $root . '/application/controllers', 'admin' => $root . '/application/modules/admin/controllers']);
class GobdControllerFixture extends Admin_ExportController
{
	public function init() { $this->_user = Zend_Registry::get('User'); $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger'); $this->view = new class extends Zend_View { public function translate($key) { return $key; } }; }
	public function database(string $path, string $type): void { $this->exportDatabase($path, $type); }
	public function listing(string $path): array { return $this->getExportedFiles('7/0/7', $path); }
}
$controller = new GobdControllerFixture(new Zend_Controller_Request_Http(), new Zend_Controller_Response_Http());
$controller->database($destination, 'csv'); $controller->database($destination, 'sql');
exportCheck(count(glob($destination . 'export-*-csv.zip')) === 1 && count(glob($destination . 'export-*-sql.zip')) === 1, 'Legacy CSV/SQL exports regressed');
$flash = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
$translator = new DEEC_Translate('de_DE');
$translator->loadDir('default', $root . '/languages/de_DE/default');
Zend_Registry::set('DEEC_Translate', $translator);
foreach ([['type' => 'csv'], ['type' => 'sql'], ['type' => 'list', 'target' => 'contacts'], ['type' => 'list', 'target' => 'items'], ['type' => 'list', 'target' => 'stocks'], ['type' => 'gobd', 'from' => '2019-01-01', 'to' => '2021-12-31'], ['type' => 'gobd', 'from' => '2019-01-01', 'to' => '2021-12-31', 'include_documents' => '1'], ['type' => 'gobd', 'from' => '2019-01-01', 'to' => '2021-12-31', 'review_export' => '1']] as $parameters) {
	$flash->messages = [];
	$request = new Zend_Controller_Request_Http(); $request->setParams($parameters);
	$request->setModuleName('admin')->setControllerName('export')->setActionName('export');
	Zend_Controller_Front::getInstance()->setRequest($request);
	$successful = new GobdControllerFixture($request, new Zend_Controller_Response_Http());
	exportCheck($successful->exportAction() === 'index', 'Export success lost the index redirect: ' . json_encode($parameters));
	exportCheck($flash->messages === [['type' => 'success', 'message' => 'ADMIN_EXPORT_CREATED']], 'Export success is untyped or untranslated: ' . json_encode($parameters));
}
foreach (['contacts-companies-*.csv', 'items-*.csv', 'stock-*.csv'] as $pattern) exportCheck(count(array_filter(glob($destination . $pattern), static fn($path) => filesize($path) > 0)) > 0, 'List export did not create its CSV: ' . $pattern);
$flash->messages = [];
$request = new Zend_Controller_Request_Http(); $request->setParam('type', 'unknown');
exportCheck((new GobdControllerFixture($request, new Zend_Controller_Response_Http()))->exportAction() === 'index' && $flash->messages === ['ADMIN_EXPORT_INVALID_TYPE'], 'Invalid export type lost its localized error/redirect');
class GobdFailedExportAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
	protected function _connect() { throw new RuntimeException('Fixture export read failure'); }
}
$flash->messages = [];
$published = glob($destination . '*.zip');
Zend_Db_Table::setDefaultAdapter(new GobdFailedExportAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']));
$request = new Zend_Controller_Request_Http(); $request->setParams(['type' => 'gobd', 'from' => '2019-01-01', 'to' => '2021-12-31']);
try {
	exportCheck((new GobdControllerFixture($request, new Zend_Controller_Response_Http()))->exportAction() === 'index' && $flash->messages === ['ADMIN_EXPORT_FAILED'], 'Failed GoBD export lost its error/redirect or reported success');
} finally { Zend_Db_Table::setDefaultAdapter($db); }
exportCheck(glob($destination . '*.zip') === $published && glob($destination . '.gobd-*') === [], 'Controller export failure left a partial package or staging files');
$published = glob($destination . '*.zip');
foreach ([['from' => '2020-02-30', 'to' => '2021-01-01'], ['from' => '2022-01-01', 'to' => '2021-01-01'], ['from' => ['bad'], 'to' => '2021-01-01'], ['from' => '2020-01-01', 'to' => '2021-01-01', 'include_documents' => ['bad']], ['from' => '2020-01-01', 'to' => '2021-01-01', 'review_export' => ['bad']], ['from' => '2020-01-01', 'to' => '2021-01-01', 'review_export' => '2']] as $parameters) {
	$request = new Zend_Controller_Request_Http(); $request->setParams($parameters + ['type' => 'gobd']);
	$invalid = new GobdControllerFixture($request, new Zend_Controller_Response_Http());
	$flash->messages = [];
	exportCheck($invalid->exportAction() === 'index' && glob($destination . '*.zip') === $published, 'Controller accepted invalid dates or checkbox values');
	exportCheck($flash->messages === ['ADMIN_EXPORT_INVALID_PERIOD'], 'Invalid GoBD input lost its localized error or reported success');
}
$pdo->exec("UPDATE permission SET purchases = '{}' WHERE userid = 42");
$request = new Zend_Controller_Request_Http(); $request->setParams(['type' => 'gobd', 'from' => '2019-01-01', 'to' => '2021-12-31', 'include_documents' => '1']);
$denied = new GobdControllerFixture($request, new Zend_Controller_Response_Http()); $denied->exportAction();
exportCheck($denied->getResponse()->getHttpResponseCode() === 403 && glob($destination . '*.zip') === $published, 'Controller created images without document permissions');
$request = new Zend_Controller_Request_Http(); $request->setParams(['type' => 'gobd', 'from' => '2019-01-01', 'to' => '2021-12-31', 'review_export' => '1']);
$denied = new GobdControllerFixture($request, new Zend_Controller_Response_Http()); $denied->exportAction();
exportCheck($denied->getResponse()->getHttpResponseCode() === 403 && glob($destination . '*.zip') === $published, 'Review button bypassed server-side document permissions');
$pdo->prepare('UPDATE permission SET purchases = ? WHERE userid = 42')->execute([$rights['purchases']]);
mkdir($destination . '.gobd-private', 0700); file_put_contents($destination . '.gobd-private/unpublished.zip', 'private');
exportCheck(!in_array('.gobd-private', array_column($controller->listing($destination), 'name'), true), 'Export list exposed staging directories');
unlink($destination . '.gobd-private/unpublished.zip'); rmdir($destination . '.gobd-private');
foreach ($before as $table => $rows) exportCheck($pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $rows, 'Export changed business/archive rows: ' . $table);
foreach ($fileBefore as $path => $state) exportCheck([filesize($path), hash_file('sha256', $path), filemtime($path)] === $state, 'Export changed an original PDF');
$zip->close(); $legacy->close();

// Published review fixtures use the same private records and validators as the recovery CLI.
$reviewRoot = $work . '/review';
reviewDirectory($reviewRoot);
reviewWrite($reviewRoot . '/review.json', reviewJson(['schema_version' => 1, 'clientid' => 7, 'contacts_clientid' => 70, 'base' => $base]));
recoveryBindReviewable($reviewRoot, 7);
function exportReviewCandidate(string $module, string $controller, int $id, string $version, bool $published = true): array
{
	global $reviewRoot, $db;
	$document = $db->fetchRow('SELECT * FROM `' . $controller . '` WHERE id = ? AND clientid = 7', [$id]);
	$identity = ['module' => $module, 'controller' => $controller, 'id' => $id, 'clientid' => 7, 'document_number' => (string)$document[$controller . 'id']];
	$directory = $reviewRoot . '/' . $module . '-' . $controller . '-' . $id;
	reviewDirectory($directory . '/candidates'); reviewDirectory($directory . '/reconstructions');
	$bytes = "%PDF-1.4\nPublished review fixture $module/$controller/$id/$version\n%%EOF\n";
	$sha = hash('sha256', $bytes);
	reviewWrite($directory . '/candidates/' . $sha . '.pdf', $bytes);
	$snapshot = DEEC_DocumentReview::snapshot($db, $identity);
	$proof = ['schema_version' => 1, 'identity' => $identity, 'generated_at' => gmdate('c'), 'generated_by' => 42, 'size' => strlen($bytes), 'sha256' => $sha, 'snapshot_sha256' => $snapshot['sha256'], 'fiscal_correction' => false, 'manual_approval_required' => true,
		'validation' => ['schema_version' => 1, 'original_comparison_required' => true, 'warnings' => ['fixture historical warning'], 'displayed' => ['header' => ['subtotal' => 100, 'taxes' => 19, 'total' => 119], 'positions' => [$id => ['price' => 100, 'quantity' => 1, 'total' => 100]]]]];
	reviewWrite($directory . '/reconstructions/' . $sha . '.json', reviewJson($proof));
	$candidate = DEEC_DocumentReview::candidate($reviewRoot, $directory, $identity, $snapshot, $sha);
	if ($published) {
		$receipt = ['schema_version' => 1, 'status' => DEEC_DocumentReview::STATUS, 'identity' => $identity, 'directory' => basename($directory), 'sha256' => $sha, 'size' => strlen($bytes), 'snapshot_sha256' => $snapshot['sha256'], 'proof_sha256' => $candidate['proof_sha256'],
			'database_confirmation' => ['database_values_verified' => true, 'original_comparison_performed' => false, 'confirmed_by' => 42, 'confirmed_at' => gmdate('c'), 'scope_sha256' => hash('sha256', reviewJson([$identity])), 'run_sha256' => hash('sha256', 'isolated fixture run')]];
		DEEC_DocumentReview::receipt($receipt, $identity, $candidate, $snapshot);
		reviewDirectory(DEEC_DocumentReview::publicationDirectory($reviewRoot, $identity));
		reviewWrite(DEEC_DocumentReview::publicationDirectory($reviewRoot, $identity) . '/' . $sha . '.json', reviewJson($receipt));
	}
	return $candidate;
}
foreach (DEEC_DocumentArchive::documents() as $module => $controllers) foreach ($controllers as $controller) {
	exportDocument($module, $controller, 31, [$controller . 'id' => 110395, 'subtotal' => 100, 'taxes' => 19, 'total' => 119], false);
	exportSeed($controller . 'pos', ['id' => 31, 'parentid' => 31, 'clientid' => 7, 'price' => 100, 'quantity' => 1, 'taxrate' => 19, 'total' => 119]);
	exportReviewCandidate($module, $controller, 31, 'first');
}
exportReviewCandidate('sales', 'invoice', 31, 'second');
foreach ([32, 33, 34, 35, 36, 37] as $id) {
	exportDocument('sales', 'invoice', $id, ['invoiceid' => $id === 32 ? 110395 : 110000 + $id, 'subtotal' => 100, 'taxes' => 19, 'total' => 119], false);
	exportSeed('invoicepos', ['id' => $id, 'parentid' => $id, 'clientid' => 7, 'price' => 100, 'quantity' => 1, 'taxrate' => 19, 'total' => 119]);
	if ($id !== 36) $candidates[$id] = exportReviewCandidate('sales', 'invoice', $id, 'first', $id !== 33);
}
$pdo->exec('UPDATE invoicepos SET price = 101 WHERE id = 34');
file_put_contents($candidates[35]['file']['path'], "%PDF-1.4\nChanged private review PDF\n%%EOF\n");
$pdo->exec('UPDATE invoice SET deleted = 1 WHERE id = 37');
exportDocument('sales', 'invoice', 38, ['invoiceid' => 10011]);
exportDocument('sales', 'invoice', 39, ['invoiceid' => null]);
$reviewBefore = [];
foreach ($tables as $table) $reviewBefore[$table] = $pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$reviewFileBefore = [];
foreach ([$base . '/files/documents', $reviewRoot] as $directory) foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) $reviewFileBefore[$file->getPathname()] = [filesize($file->getPathname()), hash_file('sha256', $file->getPathname()), filemtime($file->getPathname())];
$reviewName = $export->export($destination, '2019-01-01', '2021-12-31', false, true);
$reviewZip = new ZipArchive(); exportCheck($reviewZip->open($destination . $reviewName, ZipArchive::CHECKCONS) === true && str_starts_with($reviewName, 'document-review-'), 'Review package was not published separately');
foreach ([['2019-02-30', '2021-12-31'], ['2022-01-01', '2021-12-31'], [null, '2021-12-31']] as [$from, $to]) exportReject(static fn() => $export->export($destination, $from, $to, false, true), 'Invalid review dates were accepted');
exportReject(static fn() => (new DEEC_Export_Gobd($db, 8, 42))->export($destination, '2019-01-01', '2021-12-31', false, true), 'Foreign tenant exported private review PDFs');
Zend_Registry::set('User', array_replace($user, ['admin' => 0]));
exportReject(static fn() => $export->export($destination, '2019-01-01', '2021-12-31', false, true), 'Non-admin exported private review PDFs'); Zend_Registry::set('User', $user);
$pdo->exec("UPDATE permission SET purchases = '{}' WHERE userid = 42");
exportReject(static fn() => $export->export($destination, '2019-01-01', '2021-12-31', false, true), 'Review export ignored document permissions');
$pdo->prepare('UPDATE permission SET purchases = ? WHERE userid = 42')->execute([$rights['purchases']]);
$reviewRows = exportCsv($reviewZip, 'document_manifest.csv');
$reviewMetadata = json_decode($reviewZip->getFromName('metadata.json'), true);
exportCheck($reviewMetadata['exportType'] === 'Internal document review' && $reviewMetadata['export_kind'] === 'internal_document_review' && str_starts_with($reviewZip->getFromName('readme.txt'), 'INTERNAL DOCUMENT REVIEW ONLY'), 'Review package incorrectly claims a GoBD original export');
exportCheck($reviewZip->locateName('invoices.csv') === false, 'Review export unexpectedly contains fiscal CSV datasets');
foreach (['archived_original', DEEC_DocumentReview::STATUS, 'missing', 'invalid', 'excluded'] as $status) exportCheck(in_array($status, array_column($reviewRows, 'status'), true), 'Review manifest lost status ' . $status);
$pending = array_values(array_filter($reviewRows, static fn(array $row): bool => $row['status'] === DEEC_DocumentReview::STATUS));
exportCheck(count($pending) === 10 && count(array_unique(array_column($pending, 'controller'))) === 8, 'Published review PDFs, multiple versions or duplicate numbers were omitted');
foreach ($reviewRows as $row) {
	exportCheck($row['clientid'] === '7' && $row['document_date'] >= '2019-01-01' && $row['document_date'] <= '2021-12-31', 'Review export crossed tenant or date scope');
	if ($row['zip_path'] !== '') exportCheck(hash('sha256', $reviewZip->getFromName($row['zip_path'])) === $row['sha256'] && strlen($reviewZip->getFromName($row['zip_path'])) === (int)$row['filesize'] && str_starts_with($row['zip_path'], ($row['status'] === 'archived_original' ? 'originals' : 'review') . '/7/'), 'Review PDF role, SHA or size differs');
}
foreach ([33 => 'missing', 34 => 'invalid', 35 => 'invalid', 36 => 'missing', 37 => 'excluded'] as $id => $status) exportCheck(count(array_filter($reviewRows, static fn(array $row): bool => $row['controller'] === 'invoice' && $row['documentid'] === (string)$id && $row['status'] === $status && $row['zip_path'] === '')) === 1, 'Unpublished, stale, corrupt, missing or deleted PDF was included');
foreach (['originals/7/sales/invoice/10011.pdf', 'originals/7/sales/invoice/10011--id-38.pdf', 'originals/7/sales/invoice/document--id-39.pdf'] as $path) exportCheck($reviewZip->locateName($path) !== false, 'Duplicate or missing number strategy lost an original');
$pendingPaths = array_column($pending, 'zip_path');
exportCheck(count($pendingPaths) === count(array_unique($pendingPaths)) && count(array_filter($pendingPaths, static fn(string $path): bool => str_contains($path, '110395--'))) === 10, 'Review version names do not prefer visible numbers or collide');
$afterGobd = $export->export($destination, '2019-01-01', '2021-12-31', true);
$afterZip = new ZipArchive(); $afterZip->open($destination . $afterGobd);
foreach (exportCsv($afterZip, 'document_manifest.csv') as $row) exportCheck(!in_array($row['documentid'], ['31', '32', '33', '34', '35', '36', '37'], true), 'Pending reviews entered the GoBD original population');
$afterZip->close();
foreach (explode("\n", trim($reviewZip->getFromName('checksums.sha256'))) as $line) { [$sha, $file] = explode('  ', $line, 2); exportCheck(hash('sha256', $reviewZip->getFromName($file)) === $sha, 'Review checksum manifest differs'); }
$emptyReview = $export->export($destination, '2040-01-01', '2040-12-31', false, true);
$emptyReviewZip = new ZipArchive(); $emptyReviewZip->open($destination . $emptyReview);
exportCheck(exportCsv($emptyReviewZip, 'document_manifest.csv') === [] && json_decode($emptyReviewZip->getFromName('metadata.json'), true)['documents']['summary']['complete'], 'Empty review package is inconsistent'); $emptyReviewZip->close();
$publishedBeforeFailure = glob($destination . '*.zip');
exportReject(static fn() => (new BrokenGobdPackage($db, 7, 42))->export($destination, '2019-01-01', '2021-12-31', false, true), 'Broken review ZIP was published');
exportReject(static fn() => (new BrokenGobdReference($db, 7, 42))->export($destination, '2019-01-01', '2021-12-31', false, true), 'Broken review CSV reference was published');
exportCheck(glob($destination . '*.zip') === $publishedBeforeFailure && glob($destination . '.gobd-*') === [], 'Review failure left a public partial package');
foreach ($reviewBefore as $table => $rows) exportCheck($pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $rows, 'Review export changed business/archive rows: ' . $table);
foreach ($reviewFileBefore as $path => $state) exportCheck([filesize($path), hash_file('sha256', $path), filemtime($path)] === $state, 'Review export changed original, candidate or evidence bytes');
$reviewZip->close();
exportDocument('sales', 'invoice', 50, ['invoicedate' => '2030-01-01'], false);
$largePath = $archive->path(7, 'sales', 'invoice', 50);
$out = fopen($largePath, 'xb'); fwrite($out, "%PDF-1.4\n");
for ($i = 0; $i < 32; $i++) fwrite($out, random_bytes(1024 * 1024));
fwrite($out, "\n%%EOF\n"); fclose($out);
exportSeed('documentarchive', ['clientid' => 7, 'module' => 'sales', 'controller' => 'invoice', 'documentid' => 50, 'format' => 'pdf', 'version' => 1, 'filename' => 'invoice-50.pdf', 'path' => substr($largePath, strlen($base) + 1), 'filesize' => filesize($largePath), 'sha256' => hash_file('sha256', $largePath)]);
foreach (['--large-export', '--large-review'] as $mode) {
	$process = proc_open([PHP_BINARY, '-d', 'memory_limit=24M', __FILE__, $socket, $mode], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$result = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
	exportCheck(proc_close($process) === 0, 'Large streaming PDF export failed: ' . $error . $result);
	$largeResult = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
	exportCheck(is_file($destination . $largeResult['name']) && $largeResult['peak'] < 24 * 1024 * 1024, 'Large PDF was loaded into PHP memory');
}
echo 'PASS: ' . $checks . " isolated GoBD checks; eight types, inclusive periods, manifests, permissions, original protection and 32 MB PDF under 24 MB PHP limit\n";
