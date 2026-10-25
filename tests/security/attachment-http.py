#!/usr/bin/env python3
"""Exercise Apache rewrites using isolated test files and the real guest download action."""
import grp
import os
from pathlib import Path
import pwd
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

root = Path(__file__).resolve().parents[2]
apache = shutil.which("apache2")
if not apache:
    raise SystemExit("Apache is required for this HTTP regression test")

front = r'''<?php
$root = ROOT_PATH;
define('BASE_PATH', __DIR__);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function ($class) use ($root): void {
    if ($file = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { require_once $file; return; }
    $parts = explode('_', $class);
    if (count($parts) >= 3) {
        $module = array_shift($parts);
        $base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
        $file = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
        if (is_file($file)) require_once $file;
    }
});
class HttpAttachmentAdapter extends Zend_Db_Adapter_Pdo_Mysql {
    public array $rows = [];
    protected function _connect() {}
    protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
    public function describeTable($tableName, $schemaName = null) {
        $metadata = [];
        foreach (array_keys(($this->rows[$tableName][0] ?? []) + ['id' => 0]) as $field) {
            $metadata[$field] = ['COLUMN_NAME' => $field, 'DATA_TYPE' => 'varchar', 'PRIMARY' => $field === 'id', 'PRIMARY_POSITION' => 1, 'IDENTITY' => $field === 'id'];
        }
        return $metadata;
    }
    public function query($sql, $bind = []) {
        if (!$sql instanceof Zend_Db_Table_Select || $bind) throw new RuntimeException('Unexpected database access');
        $sql->assemble();
        $from = $sql->getPart(Zend_Db_Select::FROM);
        $table = reset($from)['tableName'];
        $rows = array_values(array_filter($this->rows[$table] ?? [], static function ($row) use ($sql): bool {
            foreach ($sql->getPart(Zend_Db_Select::WHERE) as $where) {
                $where = trim(preg_replace('/^AND /', '', $where), '() ');
                if (!preg_match('/^(\w+) = (.*)$/', $where, $match)) throw new RuntimeException('Unexpected fixture predicate');
                if ((string)($row[$match[1]] ?? '') !== trim($match[2], " '\"")) return false;
            }
            return true;
        }));
        return new class($rows) {
            public function __construct(private array $rows) {}
            public function fetchAll($mode) { return $this->rows; }
        };
    }
}
class HttpAttachmentLayout extends Zend_Controller_Action_Helper_Abstract {
    public function getName() { return 'Layout'; }
    public function setLayout($name) {}
    public function disableLayout() {}
}
$adapter = new HttpAttachmentAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
if (isset($_SERVER['HTTP_X_ATTACHMENT_TEST_USER'])) {
    Zend_Registry::set('User', ['id' => 42, 'clientid' => 7]);
    Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0]);
    $rights = $_SERVER['HTTP_X_ATTACHMENT_TEST_USER'] === 'allowed' ? ['quote' => ['view']] : [];
    $adapter->rows = [
        'permission' => [['id' => 1, 'userid' => 42, 'deleted' => 0, 'sales' => json_encode($rights)]],
        'quote' => [['id' => 10, 'clientid' => 7, 'deleted' => 0]],
        'emailattachment' => [['id' => 600, 'documentid' => 10, 'clientid' => 7, 'module' => 'sales', 'controller' => 'quote', 'filename' => 'fixture.pdf', 'location' => BASE_PATH . '/files/attachments/sales/quote/7/0/7/1/0/10', 'deleted' => 0]],
    ];
}
Zend_Db_Table::setDefaultAdapter($adapter);
require $root . '/application/Bootstrap.php';
require $root . '/application/modules/contacts/controllers/AttachmentController.php';
$front = Zend_Controller_Front::getInstance();
$front->setControllerDirectory(['contacts' => $root . '/application/modules/contacts/controllers']);
Zend_Controller_Action_HelperBroker::addHelper(new HttpAttachmentLayout());
$bootstrap = (new ReflectionClass(Bootstrap::class))->newInstanceWithoutConstructor();
$method = new ReflectionMethod(Bootstrap::class, '_initAttachmentRoutes');
$method->setAccessible(true);
$method->invoke($bootstrap);
$request = new Zend_Controller_Request_Http();
$response = new Zend_Controller_Response_Http();
$front->setRequest($request)->setResponse($response);
$front->getRouter()->route($request);
if ($request->getModuleName() !== 'contacts' || $request->getControllerName() !== 'attachment' || $request->getActionName() !== 'download') {
    $response->setHttpResponseCode(404);
} else {
    (new Contacts_AttachmentController($request, $response))->downloadAction();
}
$response->sendResponse();
'''.replace("ROOT_PATH", "'" + str(root).replace("'", "\\'") + "'")

with tempfile.TemporaryDirectory(prefix="dewawi-attachment-http-") as temp:
    temp = Path(temp)
    site = temp / "site"
    site.mkdir()
    fixture = site / "files/attachments/sales/quote/7/0/7/1/0/10/fixture.pdf"
    fixture.parent.mkdir(parents=True)
    contents = b"%PDF-1.4\nHTTP attachment security fixture\n%%EOF\n"
    fixture.write_bytes(contents)
    public = site / "media/public.txt"
    public.parent.mkdir()
    public.write_bytes(b"Public media fixture")
    (site / "index.php").write_text(front)
    current = (root / ".htaccess").read_text()
    (site / ".htaccess").write_text(current.replace("RewriteRule ^files/attachments(?:/|$) index.php [NC,L]\n", ""))
    with socket.socket() as port_socket:
        port_socket.bind(("127.0.0.1", 0))
        port = port_socket.getsockname()[1]
    modules = Path("/usr/lib/apache2/modules")
    config = temp / "apache.conf"
    config.write_text(f'''
ServerRoot "{temp}"
DefaultRuntimeDir "{temp}"
PidFile "{temp}/apache.pid"
Listen 127.0.0.1:{port}
ServerName localhost
User {pwd.getpwuid(os.getuid()).pw_name}
Group {grp.getgrgid(os.getgid()).gr_name}
ErrorLog "{temp}/error.log"
LogLevel warn
LoadModule mpm_prefork_module "{modules}/mod_mpm_prefork.so"
LoadModule authz_core_module "{modules}/mod_authz_core.so"
LoadModule rewrite_module "{modules}/mod_rewrite.so"
LoadModule php_module "{modules}/libphp8.3.so"
DocumentRoot "{site}"
<Directory "{site}">
    AllowOverride All
    Options FollowSymLinks
    Require all granted
</Directory>
<FilesMatch "\\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>
''')
    server = subprocess.Popen([apache, "-f", str(config), "-DFOREGROUND"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, start_new_session=True)
    try:
        for _ in range(100):
            if server.poll() is not None:
                raise RuntimeError(server.communicate()[1].decode())
            try:
                with socket.create_connection(("127.0.0.1", port), timeout=0.1):
                    break
            except OSError:
                time.sleep(0.05)
        else:
            raise RuntimeError("Isolated Apache did not start")

        def fetch(path, user=None, method="GET"):
            try:
                request = urllib.request.Request(f"http://127.0.0.1:{port}{path}", headers={"X-Attachment-Test-User": user} if user else {}, method=method)
                with urllib.request.urlopen(request, timeout=5) as response:
                    return response.status, response.read()
            except urllib.error.HTTPError as error:
                return error.code, error.read()

        path = "/files/attachments/sales/quote/7/0/7/1/0/10/fixture.pdf"
        assert fetch(path) == (200, contents), "Baseline did not reproduce the anonymous static-file bypass"
        (site / ".htaccess").write_text(current)
        for requested in [path, path + "?id=600", path.replace("files/attachments", "files//attachments"), path.replace("attachments/", "attachments//"), path.replace("files/attachments", "files%2fattachments"), "/files/attachments/sales/quote/7/0/7/1/0/10/%66ixture.pdf", "/files/attachments/sales/quote/7/0/7/1/0/10/fixture.pdf/extra"]:
            status, body = fetch(requested)
            assert status == 404 and contents not in body, f"Anonymous attachment exposed: {requested} (HTTP {status})"
        allowed = fetch(path, "allowed")
        assert allowed == (200, contents), f"Authorized legacy URL did not stream the original file (HTTP {allowed[0]}, response {allowed[1][:500]!r})"
        assert fetch("/contacts/attachment/download/id/600", "allowed") == (200, contents), "Authorized attachment ID did not stream the original file"
        assert fetch(path, "allowed", "HEAD") == (200, b""), "HEAD request returned file content"
        assert fetch(path, "denied")[0] == 404, "Missing document permission allowed a download"
        assert fetch("/contacts/attachment/download/id/601", "allowed")[0] == 404, "Missing attachment ID allowed a download"
        assert fetch("/media/public.txt") == (200, b"Public media fixture"), "Public media affected"
        print("PASS: Apache baseline bypass reproduced; guests and missing rights denied; authorized legacy/ID downloads and HEAD work; public media unaffected")
    finally:
        server.terminate()
        try:
            server.communicate(timeout=5)
        except subprocess.TimeoutExpired:
            server.kill()
            server.communicate()
