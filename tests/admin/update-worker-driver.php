<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$work = getenv('DEWAWI_UPDATES_TEST_WORK');
if (!is_string($work) || !preg_match('~^/tmp/dewawi-updates-[^/]+$~D', $work) || !is_file($work . '/states.json')) exit(1);
error_reporting(E_ALL & ~E_DEPRECATED); ini_set('display_errors', '0');
set_error_handler(static function(int $severity): bool { if (!(error_reporting() & $severity)) return false; throw new ErrorException('test_io'); });
$root = $work . '/source';
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class): void { if ($file = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $file; });
require __DIR__ . '/update-worker-support.php';
require $root . '/tools/updates/Worker.php';
$states = json_decode(file_get_contents($work . '/states.json'), true, 32, JSON_THROW_ON_ERROR);
$db = new class(['host' => 'localhost', 'unix_socket' => $work . '/mysql.sock', 'username' => 'executor', 'password' => 'private-worker-secret', 'dbname' => $states['missing'], 'charset' => 'utf8mb4']) extends Zend_Db_Adapter_Pdo_Mysql {
    public function insert($table, array $bind)
    {
        // Deterministically pause the real worker after acceptance, before M1's start INSERT.
        if (getenv('DEWAWI_TEST_BEFORE_START') === '1' && $table === 'dewawi_schema_migration' && ($bind['status'] ?? null) === 'started') {
            file_put_contents(getenv('DEWAWI_UPDATES_TEST_WORK') . '/before-start.signal', 'paused');
            sleep(30);
        }
        return parent::insert($table, $bind);
    }
};
$files = new UpdatesProtocolFiles($work . '/queue', 'worker');
$worker = new DewawiUpdateWorker($files, $db);
try {
    if (($argv[1] ?? '') === 'production-policy') { new DewawiUpdateFiles($work . '/queue', 'worker'); throw new LogicException('Production accepted emulated ownership'); }
    if (($argv[1] ?? '') === 'identity') $result = ['database' => $worker->identity()];
    else $result = $worker->tick(($argv[1] ?? '') === 'publish');
    echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (Throwable $error) { fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . "\n"); exit(1); }
