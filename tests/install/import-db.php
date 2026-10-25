<?php

// Run with: python3 tests/install/import-db.py (isolated MariaDB, no existing databases).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!isset($argv[1], $argv[2])) { echo "SKIP: run python3 tests/install/import-db.py\n"; exit; }
$work = realpath($argv[2]);
if (!$work || !str_starts_with($work, '/tmp/dewawi-install-db-') || $argv[1] !== $work . '/mysql.sock') {
    throw new RuntimeException('Only isolated test sockets are accepted');
}
$root = dirname(__DIR__, 2);
ini_set('mysqli.default_socket', $argv[1]);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '');
mkdir($work . '/install/config', 0700, true);
mkdir($work . '/install/templates', 0700);
foreach (['importSQL.php', 'helper.php', 'templates/importSQL.php'] as $file) {
    copy($root . '/install/' . $file, $work . '/install/' . $file);
}

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

function importFixture(string $database, string $structure, string $data): array {
    global $db, $work;
    $db->query('CREATE DATABASE `' . $database . '`');
    $db->select_db($database);
    file_put_contents($work . '/install/config/structure.sql', $structure);
    file_put_contents($work . '/install/config/data.sql', $data);
    $_SESSION = ['db_host' => 'localhost', 'db_user' => 'root', 'db_pass' => '', 'db_name' => $database];
    ob_start();
    include $work . '/install/importSQL.php';
    return [$errors, $goToNextStep, ob_get_clean()];
}

[$errors, $next, $html] = importFixture('install_success', file_get_contents($root . '/install/config/structure.sql'), file_get_contents($root . '/install/config/data.sql'));
check(!$errors && $next && str_contains($html, 'Data import succeeded!'), 'Full installation failed: ' . implode('; ', $errors));
check((int)$db->query('SELECT COUNT(*) FROM client')->fetch_row()[0] === 1, 'Installation data was not imported');
check($db->query("SHOW COLUMNS FROM address LIKE 'street'")->fetch_assoc()['Type'] === 'text', 'Street type changed');
check($db->query("SHOW INDEX FROM address WHERE Column_name = 'street'")->num_rows === 0, 'Street index still exists');
check((int)$db->query("SELECT COUNT(*) FROM information_schema.STATISTICS s JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA = s.TABLE_SCHEMA AND c.TABLE_NAME = s.TABLE_NAME AND c.COLUMN_NAME = s.COLUMN_NAME WHERE s.TABLE_SCHEMA = DATABASE() AND c.DATA_TYPE IN ('tinytext', 'text', 'mediumtext', 'longtext', 'tinyblob', 'blob', 'mediumblob', 'longblob') AND s.SUB_PART IS NULL AND s.INDEX_TYPE <> 'FULLTEXT'")->fetch_row()[0] === 0, 'Unprefixed TEXT/BLOB index remains');

$structure = 'CREATE TABLE import_before (id INT);';
$data = 'INSERT INTO import_before VALUES (1);';
foreach (['structure', 'data'] as $file) {
    $invalid = ' INVALID SQL; CREATE TABLE import_after (id INT);';
    [$errors, $next, $html] = importFixture('install_failure_' . $file, $structure . ($file === 'structure' ? $invalid : ''), $data . ($file === 'data' ? $invalid : ''));
    check(count($errors) === 1 && str_contains($errors[0], $file . '.sql - Statement 2 - SQL Error 1064'), 'SQL failure did not identify the file and statement');
    check(!$next && !str_contains($html, 'Data import succeeded!') && !str_contains($html, 'value="done"') && str_contains($html, 'Retry'), 'Failed import reported success or allowed the next step');
    check($db->query("SHOW TABLES LIKE 'import_after'")->num_rows === 0, 'Import continued after failure');
    check((int)$db->query('SELECT COUNT(*) FROM import_before')->fetch_row()[0] === ($file === 'data' ? 1 : 0), 'Data import ran after a structure failure');
}

// Execute the historical address upgrade on a schema without the later indexes.
$db->query('CREATE DATABASE install_upgrade');
$db->select_db('install_upgrade');
$db->query('CREATE TABLE address (id INT PRIMARY KEY, type VARCHAR(255), department VARCHAR(255), street TEXT, postcode VARCHAR(12), city VARCHAR(255), country VARCHAR(255)) ENGINE=InnoDB');
$db->query("INSERT INTO address VALUES (1, 'billing', NULL, 'First line\nSecond line', '12345', 'City', 'DE')");
$queries = [];
PMA_splitSqlFile($queries, file_get_contents($root . '/install/config/update-1.1.0-1.1.1.sql'));
$upgrades = 0;
foreach ($queries as $query) {
    if (!str_starts_with(trim($query['query']), 'ALTER TABLE `address`')) continue;
    $db->query($query['query']);
    $upgrades++;
}
check($upgrades === 5, 'Historical address upgrade statements were not executed');
check($db->query("SHOW INDEX FROM address WHERE Column_name = 'street'")->num_rows === 0, 'Historical upgrade recreated street index');
check($db->query('SELECT street FROM address WHERE id = 1')->fetch_row()[0] === "First line\nSecond line", 'Historical upgrade changed address data');

// Existing MariaDB installations may have an automatically prefixed street index.
$db->query('ALTER TABLE address ADD INDEX street (street(255))');
check($db->query('SELECT street FROM address WHERE id = 1')->fetch_row()[0] === "First line\nSecond line", 'Existing installation with a street index lost data');
echo "PASS: full installation, schema indexes, structure/data failure handling and historical address upgrade on " . $db->server_info . "\n";
$db->close();
