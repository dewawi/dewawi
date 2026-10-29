#!/usr/bin/env python3
"""Exercise Apache rewrites and real file authorization using isolated files and in-memory data."""
import grp
import base64
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
        if (in_array($table, ['media', 'item'], true)) throw new RuntimeException('Media publication queried metadata');
        $rows = array_values(array_filter($this->rows[$table] ?? [], static function ($row) use ($sql): bool {
            foreach ($sql->getPart(Zend_Db_Select::WHERE) as $where) {
                $where = trim(preg_replace('/^AND /', '', $where), '() ');
                if (str_starts_with($where, 'SUBSTRING_INDEX(')) {
                    preg_match("/= '(.*)'/", $where, $match);
                    if (substr(strrchr('|' . ($row['downloads'] ?? ''), '|'), 1) !== $match[1]) return false;
                    continue;
                }
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
$adapter->rows = [
    'shop' => [
        ['id' => 1, 'clientid' => 7, 'activated' => 1, 'url' => 'http://localhost'],
        ['id' => 120, 'clientid' => 100, 'activated' => 1, 'url' => 'https://www.renocold.com', 'logo' => 'renocold.png'],
        ['id' => 2, 'clientid' => 8, 'activated' => 1, 'url' => 'https://foreign.test'],
    ],
];
Zend_Session::setOptions(['save_path' => BASE_PATH . '/session', 'use_cookies' => false, 'cache_limiter' => '']);
if (isset($_SERVER['HTTP_X_ATTACHMENT_TEST_USER'])) {
    $mode = $_SERVER['HTTP_X_ATTACHMENT_TEST_USER'];
    $clientId = $mode === 'foreign' ? 8 : 7;
    Zend_Registry::set('User', ['id' => 42, 'clientid' => $clientId]);
    Zend_Registry::set('Client', ['id' => $clientId, 'parentid' => 0]);
    $rights = $mode === 'allowed' ? ['quote' => ['view']] : [];
    $adapter->rows += [
        'permission' => [['id' => 1, 'userid' => 42, 'deleted' => 0, 'sales' => json_encode($rights), 'contacts' => json_encode(['contact' => ['view']])]],
        'quote' => [['id' => 10, 'contactid' => 100, 'filename' => 'quote.pdf', 'clientid' => 7, 'deleted' => 0]],
        'invoice' => [['id' => 20, 'contactid' => 100, 'filename' => 'invoice.pdf', 'clientid' => 7, 'deleted' => 0]],
        'contact' => [['id' => 100, 'clientid' => 7, 'deleted' => 0]],
        'emailattachment' => [['id' => 600, 'documentid' => 10, 'clientid' => 7, 'module' => 'sales', 'controller' => 'quote', 'filename' => 'fixture.pdf', 'location' => BASE_PATH . '/files/attachments/sales/quote/7/0/7/1/0/10', 'deleted' => 0]],
    ];
}
Zend_Db_Table::setDefaultAdapter($adapter);
$site = (new DEEC_Site_Resolver())->resolveByHost($_SERVER['HTTP_HOST'] ?? '');
if ($site) Zend_Registry::set('SiteContext', $site);
header_register_callback(static function (): void {
    header('X-Test-Session-Started: ' . (Zend_Session::isStarted() ? 'yes' : 'no'));
});
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
if ($request->getModuleName() !== 'contacts' || $request->getControllerName() !== 'attachment' || !in_array($request->getActionName(), ['download', 'file'], true)) {
    $response->setHttpResponseCode(404);
} else {
    $controller = new Contacts_AttachmentController($request, $response);
    $controller->{$request->getActionName() . 'Action'}();
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
    public = site / "media/7/0/7/images/product.png"
    public.parent.mkdir(parents=True)
    public.write_bytes(b"Public media fixture")
    (site / "session").mkdir()
    stored_files = ["files/contacts/7/0/7/1/0/100/notes.txt", "files/contacts/7/0/7/1/0/100/quote.pdf", "files/contacts/7/0/7/1/0/100/invoice.pdf", "cache/document/sales/quote/7/10/quote.pdf", "files/uploads/unmapped.pdf", "cache/item/501.pdf", "cache/internal.pdf", "cache/thumbs/files/unmapped.png"]
    for relative in stored_files:
        target = site / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(contents)
    (site / "files/contacts/7/0/7/1/0/100/linked.pdf").symlink_to(fixture)
    image = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jXioAAAAASUVORK5CYII=')
    media_files = ['header/kaeltemaschine.jpg', 'category/kaltwassererzeuger.jpg', 'header/renocold.png', 'header/another.png', 'custom/nested/banner.png', 'root.png']
    for shard in ['1/0/100', '8/0/8']:
        for relative in media_files:
            for area in ['media', 'cache/thumbs/media']:
                target = site / area / shard / relative
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_bytes(image)
    unsafe_media = {
        'header/evil.php': b'<?php echo "executed";', 'header/evil.PHP.png': b'fixture',
        'header/script.sh': b'#!/bin/sh\necho secret\n', 'header/config.inc': b'fixture',
        'header/disguised.png': b'<?php echo "secret";', 'header/active.html': b'<!DOCTYPE html><html><script>alert(1)</script></html>',
        '.secret': b'fixture', '.internal/file.txt': b'fixture', 'header/database.ini.bak': b'fixture',
    }
    for relative, data in unsafe_media.items():
        target = site / 'media/1/0/100' / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(data)
    for area in ['media', 'cache/thumbs/media']:
        (site / area / '1/0/100/linked.png').symlink_to(site / area / '1/0/100/header/renocold.png')
        (site / area / '1/0/100/foreign').symlink_to(site / area / '8/0/8/header', target_is_directory=True)
    (site / 'media/1/0/100/business').symlink_to(site / 'files', target_is_directory=True)
    (site / 'cache/thumbs/media/1/0/100/orphan.png').write_bytes(image)
    (site / 'cache/thumbs/media/1/0/100/header/another.png').write_bytes(b'<?php echo "secret";')
    (site / "index.php").write_text(front)
    current = (root / ".htaccess").read_text()
    (site / ".htaccess").write_text(current.replace("RewriteRule ^files/attachments(?:/|$) index.php [NC,L]\n", "").replace("RewriteRule ^(?:files|media|cache)(?:/|$) index.php [NC,L]\n", ""))
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

        last_headers = {}

        def fetch(path, user=None, method="GET", host="localhost"):
            try:
                headers = {"Host": host}
                if user:
                    headers['X-Attachment-Test-User'] = user
                request = urllib.request.Request(f"http://127.0.0.1:{port}{path}", headers=headers, method=method)
                with urllib.request.urlopen(request, timeout=5) as response:
                    last_headers.clear()
                    last_headers.update(response.headers)
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
        for relative in stored_files:
            status, body = fetch('/' + relative)
            assert status == 404 and contents not in body, f"Anonymous stored file exposed: {relative}"
        for relative in stored_files[:2] + [stored_files[3]]:
            assert fetch('/' + relative, 'allowed') == (200, contents), f"Authorized file failed: {relative}"
            assert last_headers['Cache-Control'] == 'private, no-store' and last_headers['X-Content-Type-Options'] == 'nosniff'
            assert fetch('/' + relative, 'allowed', 'HEAD') == (200, b'')
            assert fetch('/' + relative, 'foreign')[0] == 404, 'Foreign tenant downloaded a file'
            assert fetch('/' + relative, 'allowed', 'POST')[0] == 405, 'File accepted a write request'
        assert fetch('/' + stored_files[1], 'denied')[0] == 404, 'Missing quote permission accepted'
        assert fetch('/' + stored_files[2], 'allowed')[0] == 404, 'Contact rights bypassed invoice permission'
        for requested in ['files/contacts/7/0/7/1/0/100/linked.pdf', 'files/contacts/7/0/7/1/0/100/%2e%2e/100/quote.pdf', 'files/contacts/7/0/7/1/0/100/%252e%252e/quote.pdf', 'files/contacts/7/0/7/1/0/100/quote.pdf%00']:
            assert fetch('/' + requested, 'denied')[0] in (400, 403, 404), f'Traversal or symlink accepted: {requested}'
        for requested in ['/media/images/product.png', '/media/7/0/7/images/product.png']:
            assert fetch(requested) == (200, b"Public media fixture"), 'Public product media affected'
            assert last_headers['Cache-Control'] == 'public, max-age=3600'
        shutil.copytree(site, temp / 'subdirectory', symlinks=True)
        (temp / 'subdirectory').rename(site / 'dewawi')
        for relative in stored_files[:2] + [stored_files[3]]:
            assert fetch('/dewawi/' + relative)[0] == 404, 'Subdirectory guest file access allowed'
            assert fetch('/dewawi/' + relative, 'allowed') == (200, contents), 'Subdirectory authorized download failed'
            assert fetch('/dewawi/' + relative, 'foreign')[0] == 404, 'Subdirectory tenant bypass'
        assert fetch('/dewawi/media/images/product.png') == (200, b'Public media fixture'), 'Subdirectory publication failed'
        for prefix in ['', '/dewawi']:
            for relative in media_files:
                for path in ['/media/' + relative, '/media/1/0/100/' + relative]:
                    assert fetch(prefix + path, host='www.renocold.com') == (200, image), f'Tenant media failed: {prefix}{path}'
                    assert last_headers['Cache-Control'] == 'public, max-age=3600' and last_headers['X-Content-Type-Options'] == 'nosniff'
                    assert last_headers['Content-Security-Policy'].startswith('sandbox;')
                    assert last_headers['X-Test-Session-Started'] == 'no' and 'Set-Cookie' not in last_headers, 'Public media required a session'
                    assert fetch(prefix + path, method='HEAD', host='www.renocold.com') == (200, b'')
                    assert fetch(prefix + path, method='POST', host='www.renocold.com')[0] == 405
                if relative != 'header/another.png':
                    for path in ['/cache/thumbs/media/' + relative, '/cache/thumbs/media/1/0/100/' + relative]:
                        assert fetch(prefix + path, host='www.renocold.com') == (200, image), f'Public thumbnail failed: {path}'
            for area in ['media', 'cache/thumbs/media']:
                for path in ['8/0/8/header/renocold.png', '0/0/100/header/renocold.png', '01/0/100/header/renocold.png', 'linked.png', 'foreign/renocold.png', 'business/contacts/7/0/7/1/0/100/quote.pdf', 'header/%2e%2e/root.png', 'header/%252e%252e/root.png', 'header//renocold.png', 'header/renocold.png%00', 'missing.png', '', 'header/'] + list(unsafe_media):
                    assert fetch(prefix + '/' + area + '/' + path, host='www.renocold.com')[0] in (400, 403, 404), f'Unsafe media request accepted: {area}/{path}'
            for path in ['/cache/thumbs/media/1/0/100/orphan.png', '/cache/thumbs/media/1/0/100/header/another.png', '/media/1/0/100/header/renocold.png']:
                assert fetch(prefix + path, host='foreign.test')[0] == 404, 'Foreign shop served tenant media'
            assert fetch(prefix + '/cache/thumbs/media/orphan.png', host='www.renocold.com')[0] == 404, 'Orphan thumbnail exposed'
            assert fetch(prefix + '/cache/thumbs/media/header/another.png', host='www.renocold.com')[0] == 404, 'Executable thumbnail exposed'
            assert fetch(prefix + '/media/8/0/8/header/renocold.png', host='foreign.test') == (200, image), 'Foreign shop lost its own media'
            assert fetch(prefix + '/media/1/0/100/header/renocold.png', host='unknown.test')[0] == 404, 'Unknown domain published media'
            for relative in stored_files:
                assert fetch(prefix + '/' + relative, host='www.renocold.com')[0] == 404, 'Public shop exposed private files or cache'
        print("PASS: Apache static bypass reproduced; attachments and stored files protected; tenant, object, method, traversal, symlink and cache checks pass; published media accessible")
    finally:
        server.terminate()
        try:
            server.communicate(timeout=5)
        except subprocess.TimeoutExpired:
            server.kill()
            server.communicate()
