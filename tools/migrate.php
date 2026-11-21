<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/library/DEEC/UpdateMaintenance.php';
DEEC_UpdateMaintenance::protectInstallation(dirname(__DIR__));
ini_set('display_errors', '0');
ini_set('log_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED);

try {
    $args = [];
    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--help' && $argc === 2) {
            echo <<<'HELP'
DEWAWI SQL migrations (application version stays unchanged).
Default command: status. All database commands require an explicit private config.

php tools/migrate.php --config=/private/database.ini --environment=production
  [--command=status|plan|baseline|apply] [--bundle=/private/update-from-to.sql]
php tools/migrate.php --command=bundle --transition=1.1.8-1.1.9 --output=/private/update.sql

status/plan: read schema and history, list pending migrations; no persistent writes.
baseline: explicitly verify and register the complete frozen 1.1.8 schema.
apply: execute pending migrations; requires registered baseline and clean history.
bundle: offline generation; no database access; output must not already exist.
--bundle is accepted only with plan/apply and must exactly match the local catalog.

Config: existing Zend INI resources.db.adapter = Pdo_Mysql and resources.db.params.
No default application config, HTTP route, cron hook or automatic repair is used.
See tools/migrations/README.md for baseline limits and recovery instructions.

HELP;
            exit(0);
        }
        if (!preg_match('/^--(command|config|environment|bundle|transition|output)=(.+)$/sD', $argument, $match) || isset($args[$match[1]])) throw new InvalidArgumentException();
        $args[$match[1]] = $match[2];
    }
    $command = $args['command'] ?? 'status';
    if (!in_array($command, ['status', 'plan', 'baseline', 'apply', 'bundle'], true)) throw new InvalidArgumentException();
    $allowed = $command === 'bundle' ? ['command', 'transition', 'output'] : ['command', 'config', 'environment'];
    if (in_array($command, ['plan', 'apply'], true)) $allowed[] = 'bundle';
    if (array_diff(array_keys($args), $allowed)) throw new InvalidArgumentException();
    $root = dirname(__DIR__);
    set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
    spl_autoload_register(static function(string $class): void {
        if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path;
    });
    require_once $root . '/install/helper.php';
    restore_exception_handler();
    ini_set('display_errors', '0');
    ini_set('log_errors', '0');
    set_error_handler(static function(int $severity): bool {
        if (!(error_reporting() & $severity)) return false;
        throw new ErrorException('migration_runtime_error');
    });
    require_once __DIR__ . '/migrations/Migrator.php';
    if ($command === 'bundle') {
        if (!isset($args['transition'], $args['output'])) throw new InvalidArgumentException();
        $migrator = new DewawiSqlMigrator(null, $root . '/install/config/migrations');
        $content = $migrator->bundle($args['transition']);
        $file = fopen($args['output'], 'x');
        try {
            if (fwrite($file, $content) !== strlen($content)) throw new RuntimeException('bundle_write_failed');
        } finally { fclose($file); }
        echo json_encode(['bundle_sha256' => hash('sha256', $content)], JSON_THROW_ON_ERROR) . "\n";
        exit(0);
    }
    if (!isset($args['config'], $args['environment']) || !is_file($args['config'])) throw new InvalidArgumentException();
    $config = (new Zend_Config_Ini($args['config'], $args['environment']))->resources->db->toArray();
    if (strtolower($config['adapter'] ?? '') !== 'pdo_mysql') throw new InvalidArgumentException();
    $params = array_intersect_key($config['params'] ?? [], array_flip(['host', 'port', 'unix_socket', 'username', 'password', 'dbname', 'charset']));
    if (empty($params['dbname']) || empty($params['username'])) throw new InvalidArgumentException();
    $params['charset'] = 'utf8mb4';
    $params['driver_options'] = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_PERSISTENT => false];
    $db = Zend_Db::factory('Pdo_Mysql', $params);
    $migrator = new DewawiSqlMigrator($db, $root . '/install/config/migrations');
    $result = $migrator->locked(static function() use ($migrator, $command, $args): array {
        if ($command === 'baseline') return $migrator->baseline();
        if ($command === 'apply') return $migrator->apply($args['bundle'] ?? null);
        return $migrator->plan($args['bundle'] ?? null);
    });
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    $message = $error instanceof DewawiMigrationException ? $error->getMessage() : 'configuration_or_database_error (check private config, permissions and arguments; --help)';
    fwrite(STDERR, 'Migration stopped: ' . $message . "\n");
    exit(1);
}
