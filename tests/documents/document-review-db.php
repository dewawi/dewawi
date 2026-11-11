<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Run with python3 tests/documents/document-review-db.py; never use an existing socket.
$socket = $argv[1] ?? '';
$work = realpath(dirname($socket));
if (!$work || !preg_match('~^/tmp/dewawi-document-review-db-[a-zA-Z0-9_]+$~D', $work) || $socket !== $work . '/mysql.sock') throw new RuntimeException('Only the isolated test socket is accepted');
require __DIR__ . '/document-review.php';
umask(0077);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '', '', 3306, $socket);
$db->query('CREATE DATABASE review_fixture');
$db->select_db('review_fixture');
$checks = 0;
function reviewDbCheck(bool $condition, string $message): void
{
	global $checks;
	if (!$condition) throw new RuntimeException($message);
	$checks++;
}
function reviewDbInsert(mysqli $db, string $table, array $row): void
{
	$sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', array_keys($row)) . '`) VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')';
	$stmt = $db->prepare($sql);
	$values = array_values($row);
	$stmt->bind_param(str_repeat('s', count($values)), ...$values);
	$stmt->execute();
	$stmt->close();
}
function reviewDbFile(string $path, string $label): string
{
	if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
	$bytes = "%PDF-1.4\n" . $label . "\n%%EOF\n";
	file_put_contents($path, $bytes);
	return hash('sha256', $bytes);
}
foreach (DEEC_DocumentArchive::documents() as $controllers) foreach ($controllers as $controller) {
	$db->query("CREATE TABLE `$controller` (id INT PRIMARY KEY, clientid INT, contactid INT, filename VARCHAR(255), `{$controller}id` INT, `{$controller}date` DATE, state INT DEFAULT 105, completed INT DEFAULT 1, cancelled INT DEFAULT 0, deleted INT DEFAULT 0)");
}
$db->query('CREATE TABLE contact (id INT PRIMARY KEY, clientid INT, contactid INT, deleted INT DEFAULT 0)');
$db->query('CREATE TABLE emailattachment (id INT PRIMARY KEY, clientid INT, documentid INT, module VARCHAR(64), controller VARCHAR(64), filename VARCHAR(255), location VARCHAR(512), filesize INT, deleted INT DEFAULT 0)');
$db->query('CREATE TABLE documentarchive (id INT PRIMARY KEY, clientid INT, documentid INT, module VARCHAR(64), controller VARCHAR(64), filename VARCHAR(255), format VARCHAR(16), path VARCHAR(512), filesize INT, sha256 CHAR(64), version INT DEFAULT 1, correctionarchiveid INT DEFAULT NULL)');
$db->query('CREATE TABLE emailmessage (id INT PRIMARY KEY, clientid INT, documentid INT, module VARCHAR(64), controller VARCHAR(64), messagesent DATETIME, deliverystatus VARCHAR(32))');
$db->query('CREATE TABLE emailmessageattachment (id INT PRIMARY KEY, clientid INT, emailmessageid INT, documentarchiveid INT, emailattachmentid INT, filename VARCHAR(255), path VARCHAR(512), filesize INT, sha256 CHAR(64), ordering INT)');
foreach ([[800, 100, 500], [900, 100, 600], [901, 100, 600], [980, 101, 500], [990, 200, 700]] as [$id, $client, $number]) reviewDbInsert($db, 'contact', ['id' => $id, 'clientid' => $client, 'contactid' => $number]);
$base = $work . '/source';
mkdir($base, 0700);
$archive = new DEEC_DocumentArchive($base);
$plans = [];
$documents = [];
foreach ([['invoice', 17, 500], ['quote', 18, 500], ['invoice', 19, 500], ['invoice', 20, 600], ['invoice', 22, 500], ['quote', 23, 500], ['invoice', 777, 500], ['invoice', 24, 700]] as [$controller, $id, $contact]) {
	$filename = in_array($id, [22, 23], true) ? 'collision.pdf' : $controller . '-' . $id . '.pdf';
	$owner = $id === 777 ? 101 : 100;
	$row = ['id' => $id, 'clientid' => $owner, 'contactid' => $contact, 'filename' => $filename, $controller . 'id' => 190000 + $id, $controller . 'date' => '2019-03-01'];
	reviewDbInsert($db, $controller, $row);
	$documents[$id] = ['module' => 'sales', 'controller' => $controller, 'id' => $id, 'clientid' => $owner, 'contactid' => $contact, 'filename' => $filename, 'contact_record_id' => $contact === 600 ? 900 : 800];
	if (in_array($id, [23, 24], true)) continue;
	$plans[] = ['module' => 'sales', 'controller' => $controller, 'id' => $id, 'clientid' => 100, 'document_number' => (string)(190000 + $id), 'format' => 'pdf', 'status' => 'blocked', 'reason' => $id === 20 ? 'contact_mapping_ambiguous' : 'original_missing', 'files' => ['current' => ['path' => '/forged/foreign.pdf']]];
}
$paths = $archive->paths($documents[17]);
foreach (['canonical' => 'canonical version', 'attachment' => 'same version', 'current' => 'same version', 'legacy' => 'other version'] as $name => $label) reviewDbFile($paths[$name], $label);
$legacy = ['id' => 1, 'clientid' => 100, 'documentid' => 17, 'module' => 'sales', 'controller' => 'invoice', 'filename' => $documents[17]['filename'], 'location' => dirname($paths['attachment']), 'filesize' => filesize($paths['attachment'])];
reviewDbInsert($db, 'emailattachment', $legacy);
$older = array_replace($legacy, ['id' => 2, 'filename' => 'invoice-previous.pdf']);
$olderPath = $archive->paths(array_replace($documents[17], ['filename' => $older['filename']]))['attachment'];
reviewDbFile($olderPath, 'older attached version');
reviewDbInsert($db, 'emailattachment', $older);
$historicalContact = $archive->paths(array_replace($documents[17], ['filename' => $older['filename']]))['current'];
$historicalCollision = reviewDbFile($historicalContact, 'ambiguous historical filename canary');
reviewDbInsert($db, 'emailattachment', array_replace($older, ['id' => 5, 'module' => 'sales', 'controller' => 'quote', 'documentid' => 23]));
$foreignPath = $archive->paths(array_replace($documents[17], ['clientid' => 101]))['attachment'];
$forbidden = [$historicalCollision, reviewDbFile($foreignPath, 'foreign tenant canary')];
reviewDbInsert($db, 'emailattachment', array_replace($legacy, ['id' => 3, 'location' => dirname($foreignPath)]));
reviewDbInsert($db, 'emailattachment', array_replace($legacy, ['id' => 4, 'clientid' => 101, 'location' => dirname($foreignPath)]));
foreach ([1 => 100, 2 => 101] as $id => $owner) reviewDbInsert($db, 'emailmessage', ['id' => $id, 'clientid' => $owner, 'documentid' => 17, 'module' => 'sales', 'controller' => 'invoice', 'deliverystatus' => 'accepted']);
foreach ([1 => 1, 2 => 0, 3 => 1] as $id => $reference) {
	$relative = 'files/attachments/emailmessage/1/0/100/0/1/' . $id . '.pdf';
	$hash = reviewDbFile($base . '/' . $relative, $id === 1 ? 'stored mail version' : 'unverified mail canary');
	if ($id > 1) $forbidden[] = $hash;
	reviewDbInsert($db, 'emailmessageattachment', ['id' => $id, 'clientid' => 100, 'emailmessageid' => $id === 3 ? 2 : 1, 'emailattachmentid' => $reference, 'filename' => $documents[17]['filename'], 'path' => $relative, 'filesize' => filesize($base . '/' . $relative), 'sha256' => $hash, 'ordering' => $id]);
}
$forbidden[] = reviewDbFile($archive->paths($documents[20])['legacy'], 'ambiguous contact canary');
reviewDbFile($archive->paths($documents[20])['attachment'], 'safe document-specific copy');
$forbidden[] = reviewDbFile($archive->paths($documents[22])['current'], 'ambiguous filename canary');
reviewDbFile($archive->paths($documents[18])['current'], 'quote candidate');
$before = [];
foreach (['documentarchive', 'emailattachment', 'emailmessage', 'emailmessageattachment', 'invoice', 'quote'] as $table) $before[$table] = $db->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetch_all(MYSQLI_ASSOC);
$db->query("CREATE USER 'review_reader'@'localhost' IDENTIFIED BY 'fixture-only'");
$db->query("GRANT SELECT ON review_fixture.* TO 'review_reader'@'localhost'");
$reader = new mysqli('localhost', 'review_reader', 'fixture-only', 'review_fixture', 3306, $socket);
$reader->query('SET TRANSACTION READ ONLY');
$reader->begin_transaction();
$shared = reviewLoadDocument($reader, $archive, ['module' => 'sales', 'controller' => 'invoice', 'id' => 24, 'clientid' => 100], 200);
reviewDbCheck($shared['document']['contact_record_id'] === 990 && (int)$shared['document']['clientid'] === 100, 'Explicit parent contact owner replaced document ownership');
$reader->rollback();
$reader->close();
$report = '';
foreach ($plans as $plan) {
	$plan['files']['current']['path'] = $foreignPath;
	$report .= json_encode(['kind' => 'document', 'plan' => $plan]) . "\n";
}
$report .= json_encode(['kind' => 'summary', 'summary' => ['mode' => 'dry-run', 'clientid' => 100, 'contacts_clientid' => 100, 'documents' => count($plans), 'unassigned_scan' => 'complete']]) . "\n";
file_put_contents($work . '/report.jsonl', $report);
file_put_contents($work . '/audit.tsv', "sales/invoice\t17\t190017\t2019-03-01\nsales/quote\t18\t190018\t2019-03-01\n");
file_put_contents($work . '/database.ini', "[production]\nresources.db.params.host = 'localhost'\nresources.db.params.username = 'review_reader'\nresources.db.params.password = 'fixture-only'\nresources.db.params.dbname = 'review_fixture'\n");
$output = $work . '/review/client-100';
$command = [PHP_BINARY, '-d', 'mysqli.default_socket=' . $socket, __DIR__ . '/document-review.php', '--config=' . $work . '/database.ini', '--base=' . $base, '--output=' . $output, '--client=100', '--jsonl=' . $work . '/report.jsonl', '--audit-tsv=' . $work . '/audit.tsv', '--select=both'];
for ($attempt = 0; $attempt < 2; $attempt++) {
	$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]); fclose($pipes[2]);
	$exit = proc_close($process);
	reviewDbCheck($exit === 1 && $stderr === '', 'CLI failed instead of reporting documented source issues: ' . $stderr);
	$result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
	reviewDbCheck($result['counts']['selected_unique'] === 6 && $result['counts']['audit'] === 2 && $result['counts']['blocked_invoices_in_report'] === 5, 'Real CLI selection counts differ');
	$run = json_decode(file_get_contents($result['run_directory'] . '/run.json'), true, 512, JSON_THROW_ON_ERROR);
	$manifests = [];
	foreach ($run['documents'] as $row) $manifests[$row['key']] = json_decode(file_get_contents($output . '/' . $row['manifest']), true, 512, JSON_THROW_ON_ERROR);
	reviewDbCheck(count($manifests['sales/invoice/17']['candidates']) === 5, 'Canonical, distinct historical or verified mail versions were lost');
	reviewDbCheck($manifests['sales/invoice/19']['candidates'] === [], 'Missing original received invented bytes');
	reviewDbCheck(count($manifests['sales/invoice/20']['candidates']) === 1, 'Ambiguous contact was used instead of document-specific source');
	reviewDbCheck($manifests['sales/invoice/22']['candidates'] === [], 'Shared contact filename was arbitrarily assigned');
	reviewDbCheck($manifests['sales/invoice/777']['current_reason'] === 'document_unavailable' && $manifests['sales/invoice/777']['candidates'] === [], 'Foreign tenant row was read');
	foreach ($manifests as $manifest) foreach ($manifest['candidates'] as $candidate) reviewDbCheck(!in_array($candidate['sha256'], $forbidden, true), 'Unverified or foreign file was collected');
}
foreach ($before as $table => $rows) reviewDbCheck($db->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetch_all(MYSQLI_ASSOC) === $rows, 'Collection changed database records');
$db->close();
echo 'PASS: ' . $checks . " isolated MariaDB/CLI document review checks; SELECT-only account, tenant and parent scopes, source verification, reruns and unchanged database\n";
