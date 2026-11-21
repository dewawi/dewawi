<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0'); ini_set('log_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED);
set_error_handler(static function(int $severity): bool {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException('worker_configuration');
});

try {
    $args = [];
    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--help' && $argc === 2) {
            echo "DEWAWI update worker (CLI only).\nphp tools/update-worker.php --config=/private/worker.json [--command=tick|publish|identity]\nDefault tick: consume one externally released approval or publish a locked M1 plan.\npublish never executes migrations. identity prints only the installation/database fingerprint.\nSee tools/updates/README.md for mandatory account, filesystem, backup and maintenance setup.\n";
            exit;
        }
        if (!preg_match('/^--(config|command)=(.+)$/sD', $argument, $match) || isset($args[$match[1]])) throw new RuntimeException('worker_configuration');
        $args[$match[1]] = $match[2];
    }
    $command = $args['command'] ?? 'tick';
    if (!isset($args['config']) || !in_array($command, ['tick', 'publish', 'identity'], true) || !function_exists('posix_geteuid') || posix_geteuid() === 0 || !function_exists('fsync')) throw new RuntimeException('worker_configuration');
    $root = dirname(__DIR__);
    $source = static function(string $file) use ($root): void {
        $stat = lstat($file);
        if (!$stat || ($stat['mode'] & 0170000) !== 0100000 || $stat['uid'] !== 0 || ($stat['mode'] & 0022) || realpath($file) !== $file || !str_starts_with($file, $root . '/')) throw new RuntimeException('worker_configuration');
        for ($path = dirname($file); $path !== '/'; $path = dirname($path)) {
            $stat = lstat($path);
            if (!$stat || ($stat['mode'] & 0170000) !== 0040000 || $stat['uid'] !== 0 || ($stat['mode'] & 0022) || realpath($path) !== $path) throw new RuntimeException('worker_configuration');
        }
    };
    foreach (['tools/update-worker.php', 'tools/updates/Files.php', 'tools/updates/Worker.php', 'tools/migrations/Inspector.php', 'tools/migrations/Migrator.php', 'tools/migrations/baseline-1.1.8.json', 'install/SqlSplitter.php', 'library/DEEC/Version.php', 'configs/version.php', 'install/config/migrations/registry.json'] as $file) $source($root . '/' . $file);
    foreach (glob($root . '/install/config/migrations/*/*.sql') as $file) $source($file);
    set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
    spl_autoload_register(static function(string $class) use ($source): void {
        if ($file = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { $source($file); require_once $file; }
    });
    require_once __DIR__ . '/updates/Worker.php';
    $private = static function(string $file): void {
        DewawiUpdateFiles::outside($file);
        $stat = lstat($file); $parent = lstat(dirname($file));
        if (!$stat || ($stat['mode'] & 0177777) !== 0100600 || $stat['uid'] !== posix_geteuid() || $stat['nlink'] !== 1
            || !$parent || ($parent['mode'] & 0177777) !== 0040700 || $parent['uid'] !== posix_geteuid() || realpath(dirname($file)) !== dirname($file)) throw new RuntimeException('worker_configuration');
        for ($path = dirname(dirname($file)); $path !== '/'; $path = dirname($path)) {
            $stat = lstat($path);
            if (!$stat || ($stat['mode'] & 0170000) !== 0040000 || $stat['uid'] !== 0 || ($stat['mode'] & 0022) || realpath($path) !== $path) throw new RuntimeException('worker_configuration');
        }
    };
    $private($args['config']);
    $config = json_decode(file_get_contents($args['config']), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($config) || !DewawiUpdateFiles::keys($config, ['format', 'queue', 'database_config', 'environment', 'operator_ids']) || $config['format'] !== 1
        || !is_string($config['queue']) || !is_string($config['database_config']) || !is_string($config['environment']) || !is_array($config['operator_ids'])) throw new RuntimeException('worker_configuration');
    $files = new DewawiUpdateFiles($config['queue'], 'worker');
    $private($config['database_config']);
    $database = (new Zend_Config_Ini($config['database_config'], $config['environment']))->resources->db->toArray();
    if (strtolower($database['adapter'] ?? '') !== 'pdo_mysql') throw new RuntimeException('worker_configuration');
    $params = array_intersect_key($database['params'] ?? [], array_flip(['host', 'port', 'unix_socket', 'username', 'password', 'dbname', 'charset']));
    if (empty($params['dbname']) || empty($params['username'])) throw new RuntimeException('worker_configuration');
    $params['charset'] = 'utf8mb4'; $params['driver_options'] = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_PERSISTENT => false];
    $worker = new DewawiUpdateWorker($files, new Zend_Db_Adapter_Pdo_Mysql($params), $config['operator_ids']);
    $result = $command === 'identity' ? ['database' => $worker->identity()] : $worker->tick($command === 'publish');
    // Detailed plans/results are in the protected queue, never in process logs.
    echo json_encode(['ok' => true] + ($command === 'identity' ? $result : ['issues' => $result['issues'], 'job' => $result['job']]), JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Update worker stopped; check private configuration, permissions and preserved queue evidence.\n");
    exit(1);
}
