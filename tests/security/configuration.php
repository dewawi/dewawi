<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run the real entry point with isolated configuration and no database connection.
$root = dirname(__DIR__, 2);
if (isset($argv[1])) {
    define('BASE_PATH', $argv[1]);
    define('_CRONJOB_', true);
    putenv('APPLICATION_ENV');
    if ($argv[2] !== 'unset') putenv('APPLICATION_ENV=' . $argv[2]);
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['APPLICATION_ENV'] = 'development';
    $_GET['APPLICATION_ENV'] = 'development';
    require $root . '/index.php';
    $application->bootstrap('frontController');
    echo json_encode([
        'environment' => APPLICATION_ENV,
        'applicationEnvironment' => $application->getEnvironment(),
        'display_errors' => (bool)ini_get('display_errors'),
        'display_startup_errors' => (bool)ini_get('display_startup_errors'),
        'displayExceptions' => (bool)Zend_Controller_Front::getInstance()->getParam('displayExceptions'),
        'bootstrap' => $application->getOption('bootstrap'),
        'db' => $application->getOption('resources')['db'],
    ], JSON_THROW_ON_ERROR);
    exit;
}

$temp = sys_get_temp_dir() . '/dewawi-configuration-' . bin2hex(random_bytes(8));
mkdir($temp . '/configs', 0700, true);
copy($root . '/configs/application.ini', $temp . '/configs/application.ini');
$checks = 0;
try {
    foreach (['configs/database.sample.ini', 'install/config/database_template.ini'] as $template) {
        foreach ([false, true] as $override) {
            $database = file_get_contents($root . '/' . $template);
            if ($override) $database .= "\n[development : production]\nresources.db.params.dbname = development_fixture\n";
            file_put_contents($temp . '/configs/database.ini', $database);
            $production = parse_ini_string($database, true)['production'];
            foreach (['unset', '', 'production', 'development'] as $environment) {
                $pipes = [];
                $process = proc_open([PHP_BINARY, '-d', 'error_reporting=8191', '-d', 'display_errors=1', '-d', 'display_startup_errors=1', __FILE__, $temp, $environment], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $output = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                if (proc_close($process) !== 0) throw new RuntimeException('Entry point failed: ' . $errors . $output);
                $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
                $debug = $environment === 'development';
                $expected = $debug ? 'development' : 'production';
                if ($result['environment'] !== $expected || $result['applicationEnvironment'] !== $expected) throw new RuntimeException('Environment selected outside APPLICATION_ENV');
                foreach (['display_errors', 'display_startup_errors', 'displayExceptions'] as $setting) {
                    if ($result[$setting] !== $debug) throw new RuntimeException($expected . ': incorrect ' . $setting);
                }
                if ($result['bootstrap']['path'] !== $root . '/application/Bootstrap.php') throw new RuntimeException('Development did not inherit production bootstrap');
                $expectedDatabase = $debug && $override ? 'development_fixture' : $production['resources.db.params.dbname'];
                if ($result['db']['params']['dbname'] !== $expectedDatabase || $result['db']['params']['host'] !== $production['resources.db.params.host'] || $result['db']['adapter'] !== 'pdo_mysql') throw new RuntimeException('Database options or environment override lost');
                $checks++;
            }
        }
    }
    echo 'PASS: ' . $checks . " entry point cases (safe defaults, explicit development, inherited options, legacy database configurations and environment overrides)\n";
} finally {
    unlink($temp . '/configs/application.ini');
    unlink($temp . '/configs/database.ini');
    rmdir($temp . '/configs');
    rmdir($temp);
}
