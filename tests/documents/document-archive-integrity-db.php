<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$socket = $argv[1] ?? '';
$work = realpath(dirname($socket));
if (!$work || !preg_match('~^/tmp/dewawi-document-integrity-db-[a-zA-Z0-9_]+$~D', $work) || $socket !== $work . '/mysql.sock') throw new RuntimeException('Only the disposable integrity database is accepted');
require __DIR__ . '/document-recovery.php';
$root = dirname(__DIR__, 2);
$base = $work . '/source';
umask(0077);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class): void { if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path; });

class IntegrityFixtureAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
	public string $failure = '';
	public function commit()
	{
		if (in_array($this->failure, ['commit-before', 'xml-commit-before'], true)) throw new RuntimeException('Fixture commit acknowledgement failure');
		$result = parent::commit();
		if (in_array($this->failure, ['commit-after', 'xml-commit-after'], true)) throw new RuntimeException('Fixture committed acknowledgement failure');
		return $result;
	}
}

if (($argv[2] ?? '') === 'worker') {
	try {
		$db = recoveryBoot(migrationConfig($work . '/root.ini', 'production') + ['unix_socket' => $socket], $base, 7, 42);
		$db->closeConnection();
		$db = new IntegrityFixtureAdapter(migrationConfig($work . '/root.ini', 'production') + ['unix_socket' => $socket, 'charset' => 'utf8mb4']);
		$db->failure = $argv[6] ?? '';
		Zend_Db_Table::setDefaultAdapter($db);
		$module = $argv[3]; $type = $argv[4]; $id = (int)$argv[5];
		require_once $root . '/application/modules/' . $module . '/controllers/helpers/Positions.php';
		$translator = new DEEC_Translate('de_DE');
		$translator->loadDir('default', $root . '/languages/de_DE/default');
		$translator->loadDir('documents', $root . '/languages/de_DE/' . $module);
		Zend_Registry::set('DEEC_Translate', $translator);
		if (str_starts_with($db->failure, 'xml')) {
			$document = (new Sales_Model_DbTable_Invoice())->getById($id);
			$result = (new Application_Model_DbTable_Documentarchive())->archiveBytes($document, 'sales', 'invoice', 'xml', 'invoice.xml', $db->failure === 'xml-invalid' ? '<bad' : '<Invoice><ID>' . $id . '</ID><Marker>' . $db->failure . '</Marker></Invoice>');
		} else {
			class IntegrityFixtureController extends DEEC_Controller_DocumentAction {
				public function init() { $this->_user = Zend_Registry::get('User'); $this->view = new Zend_View(); Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer')->view = $this->view; }
				public function issue(int $id): array { return $this->generatePdfDocument($id, ['finalize' => true]); }
				protected function generatePdf(array $row, array $options, bool $createOriginal): array {
					if ($createOriginal && $this->getDb()->getAdapter()->failure === 'pdf') throw new RuntimeException('Fixture PDF failure');
					return parent::generatePdf($row, $options, $createOriginal);
				}
			}
			Zend_Controller_Front::getInstance()->setControllerDirectory(['default' => $root . '/application/controllers']);
			$request = new Zend_Controller_Request_Http(); $request->setModuleName($module)->setControllerName($type)->setParam('id', $id);
			$result = (new IntegrityFixtureController($request, new Zend_Controller_Response_Http()))->issue($id);
		}
		echo json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR);
	} catch (Throwable $error) { echo json_encode(['ok' => false, 'error' => $error->getMessage()], JSON_THROW_ON_ERROR); }
	exit;
}

$pdo = new PDO('mysql:unix_socket=' . $socket . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE integrity_fixture'); $pdo->exec('USE integrity_fixture');
$pdo->exec(file_get_contents($root . '/install/config/structure.sql'));
$checks = 0;
function integrityCheck(bool $condition, string $message): void { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; }
function integritySeed(string $table, array $values): void
{
	global $pdo;
	foreach ($pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) as $column) {
		if ($column['Field'] === 'id' || $column['Null'] === 'YES' || $column['Default'] !== null || array_key_exists($column['Field'], $values)) continue;
		$values[$column['Field']] = preg_match('/int|decimal|float|double/', $column['Type']) ? 0 : '';
	}
	$pdo->prepare('INSERT INTO `' . $table . '` (`' . implode('`,`', array_keys($values)) . '`) VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')')->execute(array_values($values));
}
function integrityDraft(string $type, int $id): void
{
	integritySeed($type, ['id' => $id, 'clientid' => 7, 'contactid' => 12, 'state' => 100, 'completed' => 0, 'cancelled' => 0, $type . 'id' => null, $type . 'date' => '2026-01-02', 'language' => 'de_DE', 'currency' => 'EUR', 'taxfree' => 0, 'templateid' => 1, 'billingname1' => 'Fixture buyer', 'billingstreet' => 'Fixture street', 'billingcity' => 'Berlin', 'billingcountry' => 'DE', 'created' => '2026-01-01 00:00:00']);
	integritySeed($type . 'pos', ['id' => $id, 'parentid' => $id, 'clientid' => 7, 'title' => 'Fixture position', 'sku' => 'TEST', 'price' => 100, 'quantity' => 1, 'taxrate' => 19, 'total' => 0, 'currency' => 'EUR', 'uom' => 'pcs', 'masterid' => 0]);
}
function integrityStart(string $module, string $type, int $id, string $failure = ''): array
{
	global $socket;
	$process = proc_open([PHP_BINARY, '-d', 'error_reporting=8191', __FILE__, $socket, 'worker', $module, $type, (string)$id, $failure], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	return [$process, $pipes];
}
function integrityFinish(array $worker): array
{
	[$process, $pipes] = $worker; $out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
	integrityCheck(proc_close($process) === 0, 'Worker failed: ' . $error);
	try { return json_decode($out, true, 512, JSON_THROW_ON_ERROR); }
	catch (Throwable $failure) { throw new RuntimeException('Invalid worker result: ' . $out . $error); }
}
function integrityIssue(string $module, string $type, int $id, string $failure = ''): array { return integrityFinish(integrityStart($module, $type, $id, $failure)); }

mkdir($base, 0700); symlink($root . '/library', $base . '/library'); symlink($root . '/languages', $base . '/languages');
mkdir($work . '/reports', 0700);
foreach (['root' => ['root', ''], 'reader' => ['integrity_reader', 'fixture-only']] as $name => [$user, $password]) file_put_contents($work . '/' . $name . '.ini', "[production]\nresources.db.params.host='localhost'\nresources.db.params.username='$user'\nresources.db.params.password='$password'\nresources.db.params.dbname='integrity_fixture'\n");
integritySeed('client', ['id' => 7, 'activated' => 1]); integritySeed('client', ['id' => 8, 'activated' => 1]);
integritySeed('user', ['id' => 42, 'clientid' => 7, 'activated' => 1]); integritySeed('user', ['id' => 43, 'clientid' => 8, 'activated' => 1]);
$permissions = ['userid' => 42, 'clientid' => 7];
foreach (DEEC_DocumentArchive::documents() as $module => $controllers) $permissions[$module] = json_encode(array_fill_keys($controllers, ['view', 'edit']));
integritySeed('permission', $permissions);
integritySeed('contact', ['id' => 1, 'clientid' => 7, 'contactid' => 12, 'name1' => 'Fixture buyer']);
integritySeed('template', ['id' => 1, 'clientid' => 7, 'activated' => 1, 'default' => 1]);
integritySeed('currency', ['id' => 1, 'clientid' => 7, 'code' => 'EUR', 'ordering' => 1]);
integritySeed('language', ['id' => 1, 'clientid' => 7, 'code' => 'de_DE', 'ordering' => 1]);
integritySeed('taxrate', ['id' => 1, 'clientid' => 7, 'rate' => 19]); integritySeed('uom', ['id' => 1, 'clientid' => 7, 'title' => 'pcs']);
$increment = ['clientid' => 7]; $filenames = ['clientid' => 7, 'language' => 'de_DE'];
foreach (DEEC_DocumentArchive::documents() as $module => $controllers) foreach ($controllers as $type) { $increment[$type . 'id'] = 1000; $filenames[$type] = $type . '-%NUMBER%.pdf'; integrityDraft($type, 1); }
integritySeed('increment', $increment); integritySeed('filename', $filenames);
$archive = new DEEC_DocumentArchive($base);
foreach (DEEC_DocumentArchive::documents() as $module => $controllers) foreach ($controllers as $type) {
	$result = integrityIssue($module, $type, 1);
	integrityCheck($result['ok'], 'Real finalization failed for ' . $type . ': ' . json_encode($result));
	$registration = $pdo->query("SELECT * FROM documentarchive WHERE controller = '$type' AND format = 'pdf'")->fetch(PDO::FETCH_ASSOC);
	$path = $archive->path(7, $module, $type, 1);
	integrityCheck($registration && hash_file('sha256', $path) === $registration['sha256'] && filesize($path) === (int)$registration['filesize'], 'Finalization committed without a matching original');
	$before = [hash_file('sha256', $path), filemtime($path), $pdo->query("SELECT * FROM `$type` WHERE id = 1")->fetch(PDO::FETCH_ASSOC)];
	integrityCheck(integrityIssue($module, $type, 1)['ok'] && $before === [hash_file('sha256', $path), filemtime($path), $pdo->query("SELECT * FROM `$type` WHERE id = 1")->fetch(PDO::FETCH_ASSOC)], 'Repeated finalization changed the original or document');
}
foreach (['pdf' => 2, 'registration' => 3, 'metadata' => 4] as $failure => $id) {
	integrityDraft('quote', $id);
	$counter = $pdo->query('SELECT quoteid FROM increment WHERE clientid = 7')->fetchColumn();
	if ($failure === 'registration') $pdo->exec("CREATE TRIGGER fail_registration BEFORE INSERT ON documentarchive FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture registration failure'");
	if ($failure === 'metadata') $pdo->exec("CREATE TRIGGER alter_registration BEFORE INSERT ON documentarchive FOR EACH ROW SET NEW.sha256 = REPEAT('0', 64)");
	$result = integrityIssue('sales', 'quote', $id, $failure);
	$pdo->exec('DROP TRIGGER IF EXISTS fail_registration'); $pdo->exec('DROP TRIGGER IF EXISTS alter_registration');
	$row = $pdo->query('SELECT * FROM quote WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
	integrityCheck(!$result['ok'] && empty($row['quoteid']) && empty($row['completed']) && !is_file($archive->path(7, 'sales', 'quote', $id)) && $pdo->query('SELECT quoteid FROM increment WHERE clientid = 7')->fetchColumn() === $counter, 'Failure retained issued state, consumed a number or left an original: ' . $failure);
	integrityCheck(integrityIssue('sales', 'quote', $id)['ok'], 'Rollback could not be retried: ' . $failure);
}
integrityDraft('quote', 5); $occupied = $archive->store("%PDF-1.4\nExisting canary\n%%EOF\n", 7, 'sales', 'quote', 5, 'pdf');
integrityCheck(!integrityIssue('sales', 'quote', 5)['ok'] && hash_file('sha256', $occupied['path']) === $occupied['sha256'] && !$pdo->query('SELECT quoteid FROM quote WHERE id = 5')->fetchColumn(), 'Existing original was overwritten or registered by finalization');
integrityDraft('quote', 6);
$workers = [integrityStart('sales', 'quote', 6), integrityStart('sales', 'quote', 6)];
foreach ($workers as $worker) integrityCheck(integrityFinish($worker)['ok'], 'Concurrent finalization failed');
integrityCheck((int)$pdo->query("SELECT COUNT(*) FROM documentarchive WHERE controller = 'quote' AND documentid = 6")->fetchColumn() === 1, 'Concurrent finalization registered multiple originals');
foreach (['commit-before' => 7, 'commit-after' => 8] as $failure => $id) {
	integrityDraft('quote', $id); $result = integrityIssue('sales', 'quote', $id, $failure);
	integrityCheck(!$result['ok'] && is_file($archive->path(7, 'sales', 'quote', $id)), 'Uncertain commit removed potentially committed original bytes');
	$count = (int)$pdo->query("SELECT COUNT(*) FROM documentarchive WHERE controller = 'quote' AND documentid = $id")->fetchColumn();
	integrityCheck($count === ($failure === 'commit-after' ? 1 : 0), 'Commit failure fixture did not exercise both outcomes');
}
integrityCheck(!integrityIssue('sales', 'invoice', 1, 'xml-invalid')['ok'] && !is_file($archive->path(7, 'sales', 'invoice', 1, 'xml')), 'Invalid XML was archived');
$pdo->exec("CREATE TRIGGER fail_xml BEFORE INSERT ON documentarchive FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture XML registration failure'");
integrityCheck(!integrityIssue('sales', 'invoice', 1, 'xml')['ok'] && !is_file($archive->path(7, 'sales', 'invoice', 1, 'xml')), 'XML registration failure left an orphan file');
$pdo->exec('DROP TRIGGER fail_xml');
$workers = [integrityStart('sales', 'invoice', 1, 'xml-a'), integrityStart('sales', 'invoice', 1, 'xml-b')];
$xmlResults = array_map('integrityFinish', $workers);
integrityCheck($xmlResults[0]['ok'] && $xmlResults[1]['ok'] && $xmlResults[0]['result']['sha256'] === $xmlResults[1]['result']['sha256'], 'Concurrent XML archiving was not idempotent');
$xmlHash = hash_file('sha256', $archive->path(7, 'sales', 'invoice', 1, 'xml'));
integrityCheck(integrityIssue('sales', 'invoice', 1, 'xml-other')['ok'] && hash_file('sha256', $archive->path(7, 'sales', 'invoice', 1, 'xml')) === $xmlHash, 'XML retry overwrote the issued XML');
foreach (['xml-commit-before' => 2, 'xml-commit-after' => 3] as $failure => $id) {
	integrityDraft('invoice', $id); integrityCheck(integrityIssue('sales', 'invoice', $id)['ok'], 'XML commit fixture invoice could not be finalized');
	$result = integrityIssue('sales', 'invoice', $id, $failure);
	$path = $archive->path(7, 'sales', 'invoice', $id, 'xml');
	integrityCheck(!$result['ok'] && is_file($path), 'Uncertain XML commit removed original bytes');
	$hash = hash_file('sha256', $path); $retry = integrityIssue('sales', 'invoice', $id, 'xml-retry');
	integrityCheck($retry['ok'] === ($failure === 'xml-commit-after') && hash_file('sha256', $path) === $hash, 'XML retry adopted an unregistered file or replaced a committed original');
}
integrityDraft('quote', 6000);
$blockedDirectory = dirname($archive->path(7, 'sales', 'quote', 6000)); file_put_contents($blockedDirectory, 'directory collision canary');
integrityCheck(!integrityIssue('sales', 'quote', 6000)['ok'] && file_get_contents($blockedDirectory) === 'directory collision canary' && !$pdo->query('SELECT quoteid FROM quote WHERE id = 6000')->fetchColumn(), 'Filesystem write failure committed a document or removed an existing directory collision');

require __DIR__ . '/document-archive-integrity-fixtures.php';
echo 'PASS: ' . $checks . " isolated original archive checks; eight real PDF flows, rollback, retries, concurrent finalization/XML, commit ambiguity and read-only integrity CLI\n";
require __DIR__ . '/document-recovery-assessment-db-fixtures.php';
require __DIR__ . '/document-revision-db-fixtures.php';
require __DIR__ . '/invoice-correction-db-fixtures.php';
require __DIR__ . '/document-historical-recovery-db-fixtures.php';
