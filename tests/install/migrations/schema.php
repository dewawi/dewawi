<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!isset($argv[1], $argv[2]) || !preg_match('~^/tmp/dewawi-migrations-[^/]+/mysql\.sock$~D', $argv[1]) || !preg_match('/^[a-z_]+$/D', $argv[2])) exit(1);
$root = dirname(__DIR__, 3);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class): void {
    if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path;
});
require_once $root . '/tools/migrations/Migrator.php';
$db = Zend_Db::factory('Pdo_Mysql', ['host' => 'localhost', 'unix_socket' => $argv[1], 'username' => 'root', 'password' => '', 'dbname' => $argv[2], 'charset' => 'utf8mb4']);
echo json_encode(DewawiSqlMigrator::schema($db), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
