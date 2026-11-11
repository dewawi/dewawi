<?php

// Run with: python3 tests/items/attribute-import-db.py (isolated MariaDB only).
if(PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if(!isset($argv[1]) || !str_starts_with($argv[1], '/tmp/dewawi-attribute-import-db-')) {
	echo "SKIP: start the isolated database with python3 tests/items/attribute-import-db.py\n";
	exit;
}

error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root.'/library'.PATH_SEPARATOR.get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
	if($path = stream_resolve_include_path(str_replace('_', '/', $class).'.php')) {
		require_once $path;
		return;
	}
	$parts = explode('_', $class);
	if(count($parts) < 3) return;
	$module = array_shift($parts);
	$base = $module === 'Application' ? '/application/' : '/application/modules/'.strtolower($module).'/';
	$path = $root.$base.strtolower(array_shift($parts)).'s/'.implode('/', $parts).'.php';
	if(is_file($path)) require_once $path;
});

class AttributeImportAuditAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
	public int $writes = 0;
	public function query($sql, $bind = [])
	{
		if(is_string($sql) && preg_match('/^\s*(INSERT|UPDATE|DELETE)\b/i', $sql)) $this->writes++;
		return parent::query($sql, $bind);
	}
}

function importAdapter(string $socket): Zend_Db_Adapter_Pdo_Mysql
{
	return new AttributeImportAuditAdapter(['host' => 'localhost', 'unix_socket' => $socket, 'username' => 'root', 'password' => '', 'dbname' => 'dewawi_attribute_import_test', 'charset' => 'utf8']);
}

function importText(Items_Model_DbTable_Itematr $model, string $csv, string $separator = ';', string $enclosure = '"'): array
{
	$stream = fopen('php://temp', 'w+');
	fwrite($stream, $csv);
	rewind($stream);
	try {
		return $model->importCsv($stream, $separator, $enclosure);
	} finally {
		fclose($stream);
	}
}

function importFixture(string $factor = '1,41', string $reference = '7'): string
{
	return "sku;title;value;description\nFORM_CALC;temperaturfaktor_20 / 30;$factor;\nFORM_CALC;referenzleistung;$reference;ZCE110\n";
}

class PausingAttributeImport extends Items_Model_DbTable_Itematr
{
	private bool $paused = false;
	public function create(array $data): int
	{
		$id = parent::create($data);
		if(!$this->paused) {
			$this->paused = true;
			echo "PAUSED\n";
			flush();
			if(trim((string)fgets(STDIN)) !== 'continue') throw new RuntimeException('Worker was not released');
		}
		return $id;
	}
}

$socket = $argv[1];
Zend_Registry::set('User', ['id' => 11]);
Zend_Registry::set('Client', ['id' => 7]);
if(isset($argv[2])) {
	$adapter = importAdapter($socket);
	Zend_Db_Table::setDefaultAdapter($adapter);
	$model = $argv[2] === 'pause' ? new PausingAttributeImport() : new Items_Model_DbTable_Itematr();
	if(isset($argv[3])) $model->setClientId((int)$argv[3]);
	echo "START\n";
	flush();
	importText($model, importFixture($argv[2] === 'pause' ? '1,50' : '1.41'));
	echo "DONE\n";
	exit;
}

$bootstrap = new PDO('mysql:unix_socket='.$socket, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$bootstrap->exec('CREATE DATABASE dewawi_attribute_import_test');
$bootstrap = null;
$adapter = importAdapter($socket);
Zend_Db_Table::setDefaultAdapter($adapter);
$schema = file_get_contents($root.'/install/config/structure.sql');
foreach(['item', 'itematr', 'itematrset', 'category'] as $table) {
	if(!preg_match('/CREATE TABLE IF NOT EXISTS `'.$table.'` \(.*?\) ENGINE=InnoDB DEFAULT CHARSET=utf8;/s', $schema, $match)) throw new RuntimeException('Missing schema for '.$table);
	$adapter->query($match[0]);
}
$adapter->insert('item', ['id' => 10, 'catid' => 0, 'sku' => 'ZCE110', 'title' => 'ZCE 110', 'shopid' => 120, 'shopcatid' => 20, 'clientid' => 7]);
$adapter->insert('item', ['id' => 11, 'catid' => 0, 'sku' => 'FOREIGN', 'shopid' => 121, 'clientid' => 8]);
$adapter->insert('category', ['id' => 20, 'parentid' => 0, 'type' => 'shop', 'title' => 'Devices', 'shopid' => 120, 'activated' => 1, 'clientid' => 7]);
$model = new Items_Model_DbTable_Itematr();
$checks = 0;
function checkImport(bool $condition, string $message): void
{
	global $checks;
	$checks++;
	if(!$condition) throw new RuntimeException($message);
}
function importSnapshot(): array
{
	global $adapter;
	return [$adapter->fetchAll('SELECT * FROM itematr ORDER BY id'), $adapter->fetchAll('SELECT * FROM itematrset ORDER BY id')];
}
function invalidImport(string $csv, string $message): void
{
	global $model, $adapter;
	$before = importSnapshot();
	$writes = $adapter->writes;
	try {
		importText($model, $csv);
		throw new RuntimeException('Invalid input accepted: '.$message);
	} catch(InvalidArgumentException $e) {
		checkImport(importSnapshot() === $before, 'Invalid input changed data: '.$message);
		checkImport($adapter->writes === $writes, 'Invalid input attempted a write before validation: '.$message);
	}
}

checkImport(importText($model, importFixture()) === ['created' => 2], 'First import failed');
checkImport(count($model->getPositionsBySku('FORM_CALC')) === 2, 'First import row count differs');
checkImport($model->getPositionsBySku('FORM_CALC')[0]['parentid'] == 0, 'Historical parent ID changed');
importText($model, importFixture());
checkImport(count($model->getPositionsBySku('FORM_CALC')) === 2, 'Reimport duplicated rows');
importText($model, importFixture('0.69', '4,6000'));
$calc = $model->getPositionsBySku('FORM_CALC')->toArray();
checkImport(array_column($calc, 'value') === ['0.69', '4,6000'], 'Decimal values were rewritten');
checkImport(array_column($calc, 'title') === ['temperaturfaktor_20 / 30', 'referenzleistung'] && $calc[1]['description'] === 'ZCE110', 'Calculation keys or device mapping changed');

$adapter->insert('itematr', ['parentid' => 0, 'atrsetid' => 0, 'sku' => 'OTHER', 'title' => 'Keep', 'value' => 'old', 'ordering' => 99, 'clientid' => 7]);
$otherId = (int)$adapter->lastInsertId();
$adapter->insert('itematr', ['parentid' => 10, 'atrsetid' => 0, 'sku' => 'FORM_CALC', 'title' => 'Keep attached', 'ordering' => 18, 'clientid' => 7]);
$attachedId = (int)$adapter->lastInsertId();
$adapter->insert('itematr', ['parentid' => 0, 'atrsetid' => 0, 'sku' => 'FORM_CALC', 'title' => 'referenzleistung', 'value' => '999', 'description' => 'FOREIGN', 'clientid' => 8]);
$foreignBefore = $adapter->fetchAll('SELECT * FROM itematr WHERE clientid = 8');
$otherBefore = $adapter->fetchRow('SELECT * FROM itematr WHERE id = ?', [$otherId]);
$attachedBefore = $adapter->fetchRow('SELECT * FROM itematr WHERE id = ?', [$attachedId]);
importText($model, importFixture());
checkImport($otherBefore === $adapter->fetchRow('SELECT * FROM itematr WHERE id = ?', [$otherId]), 'Parentless unrelated attribute or ordering changed');
checkImport($attachedBefore === $adapter->fetchRow('SELECT * FROM itematr WHERE id = ?', [$attachedId]), 'Attached FORM_CALC was replaced');
checkImport($foreignBefore === $adapter->fetchAll('SELECT * FROM itematr WHERE clientid = 8'), 'Another tenant changed');

foreach([
	'' => 'empty file',
	"sku;title\n" => 'header only',
	"sku;value\nFORM_CALC;1\n" => 'missing title column',
	"sku;title;title\nFORM_CALC;one;two\n" => 'duplicate header',
	"sku;title;value\nFORM_CALC;key;1\n" => 'missing description column',
	"sku;title;value;description\nFORM_CALC;;1;\n" => 'missing title',
	"sku;title;value;description\nFORM_CALC;key;;\n" => 'missing value',
	importFixture('1.2.3') => 'invalid decimal',
	importFixture('1,2invalid') => 'decimal suffix',
	importFixture('1e309') => 'non-finite number',
	"sku;title;value;description\nFORM_CALC;referenzleistung;7;\n" => 'missing reference description',
	importFixture()."FORM_CALC;referenzleistung;8;ZCE110\n" => 'duplicate reference with different value',
	importFixture()."FORM_CALC;temperaturfaktor_20 / 30;9;\n" => 'duplicate factor',
	importFixture()."FORM_CALC;key;1\n" => 'short row',
	importFixture()."FORM_CALC;key;1;;extra\n" => 'long row',
	importFixture()."FORM_CALC;\"unterminated;1;\n" => 'unterminated quote',
	importFixture()."FORM_CALC;bad\"quote;1;\n" => 'unescaped quote',
	importFixture()."FOREIGN;title;value;description\n" => 'foreign item SKU',
	importFixture()."MISSING;title;value;description\n" => 'unknown item SKU',
	importFixture()."FORM_CALC;".str_repeat('x', 256).";1;\n" => 'title too long',
	importFixture()."FORM_CALC;key;1;".str_repeat('x', 65536)."\n" => 'description too long',
	importFixture()."FORM_CALC;key;1;invalid\xFF\n" => 'invalid encoding',
	importFixture()."FORM_CALC;key;1;unsupported \u{1F600}\n" => 'unsupported schema character',
] as $csv => $message) invalidImport($csv, $message);

$before = importSnapshot();
try {
	$model->addPosition(['parentid' => 0, 'sku' => 'FORM_CALC']);
	throw new RuntimeException('General parent check was relaxed');
} catch(InvalidArgumentException $e) {
	checkImport(importSnapshot() === $before, 'Parent check wrote data');
}

// Verify ordinary attributes, groups, placeholders, optional values and discount compatibility.
$adapter->insert('itematr', ['parentid' => 10, 'atrsetid' => 0, 'sku' => 'ZCE110', 'title' => 'old', 'description' => 'replacement', 'clientid' => 7]);
$mixed = "sku;title;value;description;set;price;dewawidiscount;quantity\nZCE110;Color;blue;#old#;Technical;12,50;10;0\nZCE110;Size;large;Size description;Dimensions;1.25;;2,5\nZCE110;Ungrouped;;;;;;\nFORM_CALC;temperaturfaktor_20 / 30;1,41;;;;;\nFORM_CALC;referenzleistung;7;ZCE110;;;;\n";
checkImport(importText($model, $mixed)['created'] === 5, 'Mixed import row count differs');
$normal = $model->getPositions(10)->toArray();
checkImport(count($normal) === 3 && $normal[0]['description'] === 'replacement', 'Ordinary attributes or placeholders changed');
checkImport($normal[0]['price'] === '11.2500' && $normal[0]['quantity'] === '0.0000' && $normal[1]['quantity'] === '2.5000', 'Ordinary decimals or discount changed');
$sets = (new Items_Model_DbTable_Itematrset())->getPositionSets(10)->toArray();
checkImport(array_column($sets, 'title') === ['Technical', 'Dimensions'] && (int)$normal[2]['atrsetid'] === 0, 'Attribute group mappings differ');
$repeatMixed = str_replace('#old#', 'replacement', $mixed);
importText($model, $repeatMixed);
checkImport(count($model->getPositions(10)) === 3 && count((new Items_Model_DbTable_Itematrset())->getPositionSets(10)) === 2, 'Mixed reimport duplicated groups or attributes');
invalidImport($repeatMixed."FORM_CALC;bad;invalid;;;;;\n", 'invalid mixed import');
invalidImport(str_replace('12,50', '0.12345', $repeatMixed), 'decimal precision loss');

$calcBefore = $adapter->fetchAll("SELECT * FROM itematr WHERE sku = 'FORM_CALC' ORDER BY id");
importText($model, "sku;title;description\nZCE110;Ordinary only;free text\n");
checkImport(count($model->getPositions(10)) === 1 && $model->getPositions(10)[0]['description'] === 'free text', 'Legacy ordinary-only CSV failed');
checkImport($calcBefore === $adapter->fetchAll("SELECT * FROM itematr WHERE sku = 'FORM_CALC' ORDER BY id"), 'Ordinary-only CSV replaced calculation fields');
importText($model, $repeatMixed);

// A database failure after set and ordinary attribute replacement must restore both tables.
$before = importSnapshot();
$adapter->query("CREATE TRIGGER fail_calc BEFORE INSERT ON itematr FOR EACH ROW BEGIN IF NEW.sku = 'FORM_CALC' AND NEW.title = 'referenzleistung' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'injected insert failure'; END IF; END");
try {
	importText($model, str_replace('blue', 'red', $repeatMixed));
	throw new RuntimeException('Injected database failure did not run');
} catch(Zend_Db_Exception $e) {
	checkImport(str_contains($e->getMessage(), 'injected insert failure'), 'Unexpected database error');
	checkImport(importSnapshot() === $before, 'Insert failure did not fully roll back mixed import');
	checkImport(!$adapter->getConnection()->inTransaction(), 'Failed import left a transaction open');
} finally {
	$adapter->query('DROP TRIGGER fail_calc');
}
importText($model, $repeatMixed);
checkImport(count($model->getPositions(10)) === 3, 'Import after rollback failed or lock was leaked');

// The opposite row order must also restore FORM_CALC when an ordinary insert fails.
$before = importSnapshot();
$adapter->query("CREATE TRIGGER fail_normal BEFORE INSERT ON itematr FOR EACH ROW BEGIN IF NEW.sku = 'ZCE110' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'injected ordinary failure'; END IF; END");
try {
	importText($model, importFixture()."ZCE110;New attribute;new;description\n");
	throw new RuntimeException('Ordinary insert failure did not run');
} catch(Zend_Db_Exception $e) {
	checkImport(str_contains($e->getMessage(), 'injected ordinary failure') && importSnapshot() === $before, 'Ordinary insertion failed to restore FORM_CALC and groups');
} finally {
	$adapter->query('DROP TRIGGER fail_normal');
}
$adapter->query("CREATE TRIGGER fail_set BEFORE INSERT ON itematrset FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'injected group failure'");
try {
	importText($model, $repeatMixed);
	throw new RuntimeException('Group insert failure did not run');
} catch(Zend_Db_Exception $e) {
	checkImport(str_contains($e->getMessage(), 'injected group failure') && importSnapshot() === $before, 'Group insertion failed to restore attributes and groups');
} finally {
	$adapter->query('DROP TRIGGER fail_set');
}

// Fail closed for installations whose existing tables are not transactional.
$adapter->query('ALTER TABLE itematrset ENGINE=MyISAM');
$before = importSnapshot();
try {
	importText($model, importFixture());
	throw new RuntimeException('Nontransactional table accepted');
} catch(RuntimeException $e) {
	checkImport(str_contains($e->getMessage(), 'requires transactional'), 'Unexpected storage-engine error');
	checkImport(importSnapshot() === $before, 'Storage-engine rejection changed data');
} finally {
	$adapter->query('ALTER TABLE itematrset ENGINE=InnoDB');
}

// Exercise the real public model and unchanged calculation controller with imported data.
require_once $root.'/application/modules/shops/controllers/InquiryController.php';
class ImportedInquiryController extends Shops_InquiryController
{
	public function init() {}
	public function suggestions(): array
	{
		return $this->calculateSuggestions([['name' => 'temperaturfaktor', 'calculate' => ['target' => 'vorlauf_temp / umgebung_temp_max', 'reference' => 'referenzleistung', 'tolerance' => '0,1']]], ['vorlauf_temp' => '20', 'umgebung_temp_max' => '30', 'kuehlleistung' => '9,8']);
	}
}
Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 120, 'clientid' => 7]));
$controller = new ImportedInquiryController(new Zend_Controller_Request_Simple(), new Zend_Controller_Response_Http());
$suggestions = $controller->suggestions();
checkImport(count($suggestions) === 1 && $suggestions[0]['description'] === 'ZCE110' && $suggestions[0]['value'] === 9.87, 'Imported data broke public device selection');
$adapter->update('category', ['activated' => 0], 'id = 20');
checkImport($controller->suggestions() === [], 'Public category access was bypassed');
$adapter->update('category', ['activated' => 1], 'id = 20');

// CSV formats and ownership columns from legacy exports remain safe.
importText($model, "\xEF\xBB\xBF\"sku\",\"title\",\"value\",\"description\",\"clientid\",\"parentid\",\"id\"\r\n\"FORM_CALC\",\"custom_key -5 / 30\",\"0,69000\",\"\",\"8\",\"11\",\"1\"\r\n", ',');
$row = $model->getPositionsBySku('FORM_CALC')[0];
checkImport($row['value'] === '0,69000' && $row['title'] === 'custom_key -5 / 30' && (int)$row['clientid'] === 7 && (int)$row['parentid'] === 0, 'Legacy export keys or ownership changed');
importText($model, "sku;title;value;description\n'FORM_CALC';'quoted_key';'0.69';'device; with ''quote''\nline'\n", ';', "'");
checkImport($model->getPositionsBySku('FORM_CALC')[0]['description'] === "device; with 'quote'\nline", 'Quoted multiline description changed');
importText($model, importFixture());

function startImportWorker(string $mode, int $clientId = 7): array
{
	global $socket;
	$process = proc_open([PHP_BINARY, __FILE__, $socket, $mode, (string)$clientId], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
	if(!is_resource($process)) throw new RuntimeException('Worker could not start');
	stream_set_timeout($pipes[1], 15);
	checkImport(trim((string)fgets($pipes[1])) === 'START', 'Worker did not start');
	return [$process, $pipes];
}
function finishImportWorker(array $worker): void
{
	[$process, $pipes] = $worker;
	fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]);
	$error = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	checkImport(proc_close($process) === 0 && str_contains($output, 'DONE'), 'Worker failed: '.$output.$error);
}

$workerA = startImportWorker('pause');
checkImport(trim((string)fgets($workerA[1][1])) === 'PAUSED', 'First import did not pause after insert');
$reader = importAdapter($socket);
$publicReader = new Shops_Model_DbTable_Itematr(['db' => $reader]);
checkImport(count($publicReader->getPositionsBySku('FORM_CALC')) === 2 && $publicReader->getPositionsBySku('FORM_CALC')[0]['value'] === '1,41', 'Reader observed uncommitted deletion or partial insertion');
$reader->beginTransaction();
checkImport($publicReader->getPositionsBySkuAndTitle('FORM_CALC', 'temperaturfaktor_20 / 30')[0]['value'] === '1,41', 'Reader initial snapshot differs');
$workerB = startImportWorker('replace');
$readPipes = [$workerB[1][1]];
$writePipes = $exceptPipes = [];
checkImport(stream_select($readPipes, $writePipes, $exceptPipes, 0, 300000) === 0 && proc_get_status($workerB[0])['running'], 'Concurrent import bypassed tenant lock');
fwrite($workerA[1][0], "continue\n");
finishImportWorker($workerA);
finishImportWorker($workerB);
checkImport($publicReader->getPositionsBySkuAndTitle('FORM_CALC', 'referenzleistung')[0]['value'] === '7', 'Repeatable-read Inquiry snapshot changed');
checkImport($publicReader->getPositionsBySkuAndTitle('FORM_CALC', 'temperaturfaktor_20 / 30')[0]['value'] === '1,41', 'Reader lost its earlier generation');
$reader->commit();
checkImport(count($publicReader->getPositionsBySku('FORM_CALC')) === 2 && $publicReader->getPositionsBySku('FORM_CALC')[0]['value'] === '1.41', 'Concurrent replacement produced mixed rows or duplicates');
checkImport(count($controller->suggestions()) === 1, 'Inquiry failed after concurrent replacement');

// Autocommit gives a snapshot per query, not per multi-query calculation.
$targetBeforeCommit = $publicReader->getPositionsBySkuAndTitle('FORM_CALC', 'temperaturfaktor_20 / 30')[0]['value'];
importText($model, importFixture('0.69', '14.3'));
$referenceAfterCommit = $publicReader->getPositionsBySkuAndTitle('FORM_CALC', 'referenzleistung')[0]['value'];
checkImport($targetBeforeCommit === '1.41' && $referenceAfterCommit === '14.3', 'Autocommit snapshot boundary differs');
checkImport(count($publicReader->getPositionsBySku('FORM_CALC')) === 2, 'Autocommit reader observed an incomplete committed dataset');

// Serialize concurrent first imports as well, without existing rows to lock.
$workerA = startImportWorker('pause', 9);
checkImport(trim((string)fgets($workerA[1][1])) === 'PAUSED', 'First-import worker did not pause');
$workerB = startImportWorker('replace', 9);
fwrite($workerA[1][0], "continue\n");
finishImportWorker($workerA);
finishImportWorker($workerB);
checkImport((int)$adapter->fetchOne("SELECT COUNT(*) FROM itematr WHERE clientid = 9 AND sku = 'FORM_CALC'") === 2, 'Concurrent first imports duplicated data');
checkImport($foreignBefore === $adapter->fetchAll('SELECT * FROM itematr WHERE clientid = 8'), 'Foreign tenant changed during regression suite');

// Exercise the actual import form and escaped result presentation without an HTTP upload.
require_once $root.'/application/modules/items/controllers/AttributeController.php';
class ImportPresentationController extends Items_AttributeController
{
	public function init()
	{
		$this->_flashMessenger = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
		$this->view = new Zend_View();
	}
}
$request = new Zend_Controller_Request_HttpTestCase();
$request->setMethod('POST')->setPost(['separator' => 'invalid', 'delimiter' => 'double']);
$presentation = new ImportPresentationController($request, new Zend_Controller_Response_HttpTestCase());
$before = importSnapshot();
$presentation->importAction();
checkImport($before === importSnapshot() && !$presentation->view->importResult, 'Invalid upload reported success or modified data');
checkImport($presentation->view->form->file->getValidator('Count')->getMax() === 1, 'Import accepts multiple files');
checkImport((array)$presentation->view->form->file->getValidator('Extension')->getExtension() === ['csv'], 'Import accepts non-CSV files');
Zend_Controller_Front::getInstance()->setRequest(new Zend_Controller_Request_Http());
Zend_Controller_Front::getInstance()->getRouter()->addDefaultRoutes();
$presentation->view->addHelperPath($root.'/application/views/helpers', 'Zend_View_Helper');
$presentation->view->setScriptPath($root.'/application/modules/items/views/scripts');
foreach($presentation->view->form->getElements() as $element) $element->setView($presentation->view);
$presentation->view->importError = '<script>invalid CSV</script>';
$html = $presentation->view->render('attribute/import.phtml');
checkImport(str_contains($html, 'role="alert"') && str_contains($html, '&lt;script&gt;invalid CSV&lt;/script&gt;') && !str_contains($html, 'rows imported successfully'), 'Import error is unsafe or accompanied by success');
$presentation->view->importError = null;
$presentation->view->importResult = ['created' => 2];
checkImport(str_contains($presentation->view->render('attribute/import.phtml'), '2 rows imported successfully.'), 'Committed import result is missing');

echo "PASS: $checks attribute import, rollback, tenant, decimal, public Inquiry and concurrency checks\n";
