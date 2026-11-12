<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$socket = $argv[1] ?? '';
$work = realpath(dirname($socket));
if (!$work || !preg_match('~^/tmp/dewawi-gobd-export-db-[a-zA-Z0-9_]+$~D', $work) || $socket !== $work . '/mysql.sock') throw new RuntimeException('Only an isolated GoBD test socket is accepted');
require __DIR__ . '/document-recovery.php';
umask(0077);
$root = dirname(__DIR__, 2);
if (($argv[2] ?? '') === '--large-export') {
	$db = recoveryBoot(migrationConfig($work . '/database.ini', 'production') + ['unix_socket' => $socket], $work . '/source', 7, 42);
	$name = (new DEEC_Export_Gobd($db, 7, 42))->export(BASE_PATH . '/files/export/7/0/7/', '2030-01-01', '2030-12-31', true);
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
$published = glob($destination . '*.zip');
foreach ([['from' => '2020-02-30', 'to' => '2021-01-01'], ['from' => '2022-01-01', 'to' => '2021-01-01'], ['from' => ['bad'], 'to' => '2021-01-01'], ['from' => '2020-01-01', 'to' => '2021-01-01', 'include_documents' => ['bad']]] as $parameters) {
	$request = new Zend_Controller_Request_Http(); $request->setParams($parameters + ['type' => 'gobd']);
	$invalid = new GobdControllerFixture($request, new Zend_Controller_Response_Http());
	exportCheck($invalid->exportAction() === 'index' && glob($destination . '*.zip') === $published, 'Controller accepted invalid dates or checkbox values');
}
$pdo->exec("UPDATE permission SET purchases = '{}' WHERE userid = 42");
$request = new Zend_Controller_Request_Http(); $request->setParams(['type' => 'gobd', 'from' => '2019-01-01', 'to' => '2021-12-31', 'include_documents' => '1']);
$denied = new GobdControllerFixture($request, new Zend_Controller_Response_Http()); $denied->exportAction();
exportCheck($denied->getResponse()->getHttpResponseCode() === 403 && glob($destination . '*.zip') === $published, 'Controller created images without document permissions');
$pdo->prepare('UPDATE permission SET purchases = ? WHERE userid = 42')->execute([$rights['purchases']]);
mkdir($destination . '.gobd-private', 0700); file_put_contents($destination . '.gobd-private/unpublished.zip', 'private');
exportCheck(!in_array('.gobd-private', array_column($controller->listing($destination), 'name'), true), 'Export list exposed staging directories');
unlink($destination . '.gobd-private/unpublished.zip'); rmdir($destination . '.gobd-private');
foreach ($before as $table => $rows) exportCheck($pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $rows, 'Export changed business/archive rows: ' . $table);
foreach ($fileBefore as $path => $state) exportCheck([filesize($path), hash_file('sha256', $path), filemtime($path)] === $state, 'Export changed an original PDF');
$zip->close(); $legacy->close();
exportDocument('sales', 'invoice', 50, ['invoicedate' => '2030-01-01'], false);
$largePath = $archive->path(7, 'sales', 'invoice', 50);
$out = fopen($largePath, 'xb'); fwrite($out, "%PDF-1.4\n");
for ($i = 0; $i < 32; $i++) fwrite($out, random_bytes(1024 * 1024));
fwrite($out, "\n%%EOF\n"); fclose($out);
exportSeed('documentarchive', ['clientid' => 7, 'module' => 'sales', 'controller' => 'invoice', 'documentid' => 50, 'format' => 'pdf', 'version' => 1, 'filename' => 'invoice-50.pdf', 'path' => substr($largePath, strlen($base) + 1), 'filesize' => filesize($largePath), 'sha256' => hash_file('sha256', $largePath)]);
$process = proc_open([PHP_BINARY, '-d', 'memory_limit=24M', __FILE__, $socket, '--large-export'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$result = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
exportCheck(proc_close($process) === 0, 'Large streaming PDF export failed: ' . $error . $result);
$largeResult = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
exportCheck(is_file($destination . $largeResult['name']) && $largeResult['peak'] < 24 * 1024 * 1024, 'Large PDF was loaded into PHP memory');
echo 'PASS: ' . $checks . " isolated GoBD checks; eight types, inclusive periods, manifests, permissions, original protection and 32 MB PDF under 24 MB PHP limit\n";
