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
putenv('APPLICATION_ENV=production');
ini_set('error_log', $work . '/php-error.log');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '');
$db->set_charset('utf8mb4');
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
check($db->query('SELECT GROUP_CONCAT(title ORDER BY id) FROM uom WHERE `default` = 1 AND deleted = 0')->fetch_row()[0] === 'Stück', 'Fresh installation did not select Stück as the only default UOM');
check($db->query("SHOW COLUMNS FROM address LIKE 'street'")->fetch_assoc()['Type'] === 'text', 'Street type changed');
check($db->query("SHOW INDEX FROM address WHERE Column_name = 'street'")->num_rows === 0, 'Street index still exists');
check((int)$db->query("SELECT COUNT(*) FROM information_schema.STATISTICS s JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA = s.TABLE_SCHEMA AND c.TABLE_NAME = s.TABLE_NAME AND c.COLUMN_NAME = s.COLUMN_NAME WHERE s.TABLE_SCHEMA = DATABASE() AND c.DATA_TYPE IN ('tinytext', 'text', 'mediumtext', 'longtext', 'tinyblob', 'blob', 'mediumblob', 'longblob') AND s.SUB_PART IS NULL AND s.INDEX_TYPE <> 'FULLTEXT'")->fetch_row()[0] === 0, 'Unprefixed TEXT/BLOB index remains');

$structure = 'CREATE TABLE import_before (id INT);';
$data = 'INSERT INTO import_before VALUES (1);';
foreach (['structure', 'data'] as $file) {
    $invalid = ' INVALID SQL; CREATE TABLE import_after (id INT);';
    [$errors, $next, $html] = importFixture('install_failure_' . $file, $structure . ($file === 'structure' ? $invalid : ''), $data . ($file === 'data' ? $invalid : ''));
    check(count($errors) === 1 && str_contains($errors[0], 'Import failed.'), 'SQL failure did not produce a safe public message');
    check(!str_contains($html, 'SQL Error') && !str_contains($html, '1064') && !str_contains($html, 'INVALID SQL') && !str_contains($html, 'Stack trace') && !str_contains($html, $work), 'Installer exposed technical details');
    $log = file_get_contents($work . '/php-error.log');
    check(str_contains($log, $file . '.sql - Statement 2 - SQL Error 1064') && str_contains($log, 'mysqli_sql_exception') && str_contains($log, 'Stack trace:'), 'SQL error context or trace missing from internal log');
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

// Version 1.1.8 maps explicit contact roles and normalizes inquiry tokens before adding indexes.
$db->query('CREATE DATABASE contact_role_upgrade');
$db->select_db('contact_role_upgrade');
$db->query('CREATE TABLE contactperson (id INT PRIMARY KEY, name1 VARCHAR(255) NULL, name2 VARCHAR(255) NULL) ENGINE=InnoDB');
$db->query('CREATE TABLE contact (id INT PRIMARY KEY, type VARCHAR(255), clientid INT NOT NULL, created DATETIME NULL, createdby INT NOT NULL DEFAULT 0, modified DATETIME NULL, modifiedby INT NOT NULL DEFAULT 0, deleted TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB');
$db->query('CREATE TABLE shopinquiryform (id INT PRIMARY KEY, shopid INT NOT NULL, clientid INT NOT NULL, deleted TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB');
$db->query('CREATE TABLE shopinquirydata (id INT PRIMARY KEY, formid INT NOT NULL, shopid INT NOT NULL, token VARCHAR(255) NOT NULL, clientid INT NOT NULL, deleted TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB');
$db->query('CREATE TABLE uom LIKE install_success.uom');
$db->query("INSERT INTO uom (id, clientid, ordering, `default`, deleted) VALUES (20, 7, 0, 1, 1), (21, 7, 2, 0, 0), (23, 7, 1, 0, 0), (25, 7, 1, 0, 0), (30, 8, 0, 0, 0), (31, 8, 9, 1, 0), (40, 9, 2, 1, 0), (41, 9, 1, 1, 0), (42, 9, 1, 1, 0), (43, 9, 0, 1, 1), (50, 10, 0, 1, 1), (60, 12, -1, 0, 0), (61, 12, 0, 2, 0)");
$db->query("INSERT INTO contact (id, type, clientid, deleted) VALUES (1, ' customer ', 7, 0), (2, 'supplier', 7, 0), (3, 'customer,supplier', 7, 0), (4, '', 7, 0), (5, 'partner', 7, 1), (6, 'Prospect', 7, 0)");
$db->query("INSERT INTO shopinquirydata (id, formid, shopid, token, clientid, deleted) VALUES (1, 11, 41, 'duplicate-token', 7, 0), (2, 11, 41, 'duplicate-token', 7, 0), (3, 11, 42, 'duplicate-token', 7, 0), (4, 11, 41, 'duplicate-token', 8, 0)");
$roleQueries = [];
PMA_splitSqlFile($roleQueries, file_get_contents($root . '/install/config/update-1.1.7-1.1.8.sql'));
foreach ($roleQueries as $query) $db->query($query['query']);
check($db->query('SELECT GROUP_CONCAT(id ORDER BY clientid) FROM uom WHERE `default` = 1')->fetch_row()[0] === '23,31,41,60', 'UOM upgrade did not preserve and normalize tenant defaults by ordering and ID');
check((int)$db->query('SELECT COUNT(*) FROM uom WHERE deleted != 0 AND `default` != 0')->fetch_row()[0] === 0, 'UOM upgrade retained a deleted default');
$uoms = $db->query('SELECT * FROM uom ORDER BY id')->fetch_all(MYSQLI_ASSOC);
foreach ($roleQueries as $query) {
    if (str_contains($query['query'], 'UPDATE `uom` AS u')) $db->query($query['query']);
}
check($db->query('SELECT * FROM uom ORDER BY id')->fetch_all(MYSQLI_ASSOC) === $uoms, 'Repeated UOM upgrade changed normalized data');
check((int)$db->query('SELECT COUNT(*) FROM contactrole')->fetch_row()[0] === 4, 'Contact role upgrade mapped ambiguous or empty legacy values');
check($db->query('SELECT GROUP_CONCAT(role ORDER BY role) FROM contactrole')->fetch_row()[0] === 'customer,partner,prospect,supplier', 'Contact role upgrade lost an explicit role code');
check($db->query('SELECT type FROM contact WHERE id = 1')->fetch_row()[0] === ' customer ', 'Contact role upgrade changed contact.type');
check((int)$db->query('SELECT COUNT(*) FROM shopinquirydata')->fetch_row()[0] === 4, 'Inquiry migration deleted an existing duplicate record');
check((int)$db->query('SELECT COUNT(DISTINCT token) FROM shopinquirydata WHERE clientid = 7 AND shopid = 41')->fetch_row()[0] === 2, 'Inquiry migration did not normalize duplicate tokens within one tenant and shop');
check($db->query('SELECT GROUP_CONCAT(token ORDER BY id) FROM shopinquirydata WHERE id IN (3, 4)')->fetch_row()[0] === 'duplicate-token,duplicate-token', 'Inquiry migration changed equal tokens from different shops or tenants');
check((int)$db->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'shopinquirydata' AND index_name = 'clientid_shopid_token' AND non_unique = 0")->fetch_row()[0] === 3, 'Inquiry token unique index is missing or has the wrong columns');
check((int)$db->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'shopinquirydata' AND index_name = 'clientid_shopid_formid_deleted'")->fetch_row()[0] === 4, 'Inquiry data lookup index is missing or has the wrong columns');
check($db->query("SHOW COLUMNS FROM shopinquirydata LIKE 'status'")->fetch_assoc()['Default'] === 'new', 'Inquiry status column is missing or has the wrong default');
check((int)$db->query("SELECT COUNT(*) FROM shopinquirydata WHERE status = 'new'")->fetch_row()[0] === 4, 'Existing inquiries did not receive the initial processing status');
check((int)$db->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'shopinquirydata' AND index_name = 'clientid_status_shopid_formid_deleted'")->fetch_row()[0] === 5, 'Inquiry management index is missing or has the wrong columns');
check((int)$db->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'shopinquiryform' AND index_name = 'clientid_shopid_deleted_id'")->fetch_row()[0] === 4, 'Inquiry form lookup index is missing or has the wrong columns');
foreach ($roleQueries as $query) {
    if (str_starts_with(trim($query['query']), 'INSERT IGNORE INTO `contactrole`')) $db->query($query['query']);
}
check((int)$db->query('SELECT COUNT(*) FROM contactrole')->fetch_row()[0] === 4, 'Repeated contact role upgrade created duplicates');

echo "PASS: full installation, schema indexes, upgrade migrations and failure handling on " . $db->server_info . "\n";
$db->close();
