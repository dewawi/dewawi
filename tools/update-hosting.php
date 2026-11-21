<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0'); ini_set('log_errors', '0');
try {
    $args = getopt('', ['config:', 'environment:', 'command:', 'request:']);
    if (!isset($args['config'], $args['environment']) || !in_array($args['command'] ?? '', ['init', 'execute'], true) || array_diff(array_keys($args), ['config', 'environment', 'command', 'request'])) throw new RuntimeException();
    $root = dirname(__DIR__); set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
    spl_autoload_register(static function(string $class): void { if ($p = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $p; });
    $config = (new Zend_Config_Ini($args['config'], $args['environment']))->toArray();
    $options = $config['systemUpdates'] ?? [];
    require_once __DIR__ . '/updates/Hosting.php';
    $selection = DEEC_UpdateMaintenance::storage($options, $root, $args['command'] === 'init');
    if (!$selection) throw new RuntimeException();
    $settings = $config['resources']['db'];
    if (strtolower($settings['adapter']) !== 'pdo_mysql') throw new RuntimeException();
    $params = array_intersect_key($settings['params'], array_flip(['host', 'port', 'unix_socket', 'username', 'password', 'dbname', 'charset']));
    $params['driver_options'] = [PDO::ATTR_PERSISTENT => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    $db = Zend_Db::factory('Pdo_Mysql', $params);
    if ($args['command'] === 'init') {
        (new DewawiHostingUpdates($selection['path'], $db, $root . '/install/config/migrations', true, $selection))->initialize();
        echo "Hosting update state initialized; no migration executed\n";
    } else {
        if ($selection['record'] && !$selection['ready']) throw new RuntimeException();
        if ((new DewawiHostingUpdates($selection['path'], $db, $root . '/install/config/migrations', false, $selection))->execute($args['request'] ?? '', true) !== 'completed') throw new RuntimeException();
        echo "Execution finished; inspect retained result and M1 history\n";
    }
} catch (Throwable $e) { fwrite(STDERR, "Hosting update stopped; inspect prerequisites and retained state\n"); exit(1); }
