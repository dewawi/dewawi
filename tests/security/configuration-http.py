#!/usr/bin/env python3
"""Exercise the repository's Apache rules in isolated root and subdirectory installations."""
import grp
import http.client
import json
import os
import re
from pathlib import Path
import pwd
import shutil
import socket
import subprocess
import tempfile
import time
from urllib.parse import urlencode

root = Path(__file__).resolve().parents[2]
apache = shutil.which("apache2")
if not apache:
    raise SystemExit("Apache is required for this HTTP regression test")

front = r'''<?php
set_include_path(ROOT_PATH . '/library' . PATH_SEPARATOR . get_include_path());
require_once 'Zend/Application.php';
$application = new Zend_Application('production');
$front = Zend_Controller_Front::getInstance();
$front->setControllerDirectory([
    'default' => ROOT_PATH . '/application/controllers',
    'contacts' => ROOT_PATH . '/application/modules/contacts/controllers',
]);
require_once ROOT_PATH . '/application/Bootstrap.php';
$bootstrap = (new ReflectionClass(Bootstrap::class))->newInstanceWithoutConstructor();
$routes = new ReflectionMethod(Bootstrap::class, '_initFileManagerRoutes');
$routes->setAccessible(true);
$routes->invoke($bootstrap);
$routes = new ReflectionMethod(Bootstrap::class, '_initAttachmentRoutes');
$routes->setAccessible(true);
$routes->invoke($bootstrap);
$request = new Zend_Controller_Request_Http();
$front->getRouter()->route($request);
// Publication is tested with real models in file-access.php and attachment-http.py.
class Contacts_Model_DbTable_Emailattachment {
    public function getPublishedFilePath($path) { return in_array($path, ['media/header/logo.png', 'media/images/public.png'], true) ? $path : null; }
    public function canReadStoredFile($path, $action = 'view') { return str_starts_with($path, 'files/contacts/7/0/7/1/0/10/'); }
}
if ($request->getModuleName() === 'contacts' && $request->getControllerName() === 'attachment' && $request->getActionName() === 'file') {
    define('BASE_PATH', __DIR__);
    Zend_Loader_Autoloader::getInstance()->registerNamespace('DEEC_');
    DEEC_Session::configure();
    Zend_Layout::startMvc();
    require_once ROOT_PATH . '/application/modules/contacts/controllers/AttachmentController.php';
    $response = new Zend_Controller_Response_Http();
    $front->setRequest($request)->setResponse($response);
    (new Contacts_AttachmentController($request, $response))->fileAction();
    $response->sendResponse();
    return;
}
if ($request->getModuleName() === 'default' && $request->getControllerName() === 'media' && $request->getActionName() === 'manager') {
    define('BASE_PATH', __DIR__);
    Zend_Loader_Autoloader::getInstance()->registerNamespace('DEEC_');
    DEEC_Session::configure();
    Zend_Layout::startMvc();
    $auth = Zend_Auth::getInstance();
    $auth->setStorage(new Zend_Auth_Storage_NonPersistent());
    $mode = $_SERVER['HTTP_X_MANAGER_FIXTURE'] ?? 'guest';
    if ($mode !== 'guest') $auth->getStorage()->write((object)['id' => 42]);
    $clientId = $mode === 'shared' ? 8 : 7;
    Zend_Registry::set('User', ['id' => 42, 'clientid' => $clientId, 'admin' => $mode === 'admin' ? 1 : 0]);
    Zend_Registry::set('Client', ['id' => $clientId, 'parentid' => $mode === 'shared' ? 7 : 0, 'modules' => ['contacts' => 7, 'items' => 7]]);
    class Application_Model_DbTable_Permission {
        public function getPermissions() {
            $rights = ($_SERVER['HTTP_X_MANAGER_FIXTURE'] ?? '') === 'readonly' ? ['view'] : ['view', 'edit'];
            return ['contacts' => json_encode(['contact' => $rights])];
        }
    }
    require_once ROOT_PATH . '/application/plugins/Acl.php';
    $response = new Zend_Controller_Response_Http();
    $front->setRequest($request)->setResponse($response);
    $acl = new Application_Plugin_Acl();
    $acl->setResponse($response);
    $acl->routeShutdown($request);
    if ($response->getHttpResponseCode() === 403) { $response->setBody('Access denied')->sendResponse(); return; }
    $session = new Zend_Session_Namespace($request->getParam('managerName'));
    if ($mode !== 'unprepared') {
        $session->context = ['userid' => 42, 'clientid' => $mode === 'switched' ? 8 : $clientId, 'ownerid' => $mode === 'foreign' ? 9 : 7, 'module' => $mode === 'admin' ? 'admin' : 'contacts', 'controller' => $mode === 'admin' ? 'media' : 'contact', 'action' => $mode === 'readonly' ? 'view' : 'edit'];
        $session->subfolder = $mode === 'admin' ? ($request->getParam('managerName') === 'MediaManager' ? '7/0/7' : 'uploads/7/0/7') : 'contacts/7/0/7/1/0/10';
        $session->writable = true;
        $session->csrf_token = str_repeat('a', 64);
    }
    require_once ROOT_PATH . '/application/controllers/MediaController.php';
    class ManagerUrlFixture extends DEEC_UrlDownload {
        protected function resolve(string $host): array { return ['8.8.8.8']; }
        protected function transfer(array $options): array {
            $options[CURLOPT_WRITEFUNCTION](null, "%PDF-1.4\nManager URL fixture\n%%EOF\n");
            return [200, '8.8.8.8'];
        }
        public function receive(string $url, int $limit = 10240000): array {
            $download = parent::receive($url, $limit);
            file_put_contents(BASE_PATH . '/.staged-url-path', $download['path']);
            return $download;
        }
    }
    class ManagerFixtureController extends MediaController {
        protected function getUrlDownload(): DEEC_UrlDownload {
            return isset($_SERVER['HTTP_X_SIMULATED_URL']) ? new ManagerUrlFixture() : parent::getUrlDownload();
        }
    }
    (new ManagerFixtureController($request, $response))->managerAction();
    $response->sendResponse();
    return;
}
header('Content-Type: application/json');
echo json_encode([
    'uri' => $request->getRequestUri(), 'method' => $request->getMethod(),
    'ajax' => $request->isXmlHttpRequest(), 'post' => $request->getPost(),
    'module' => $request->getModuleName(), 'controller' => $request->getControllerName(),
    'action' => $request->getActionName(), 'id' => $request->getParam('id'),
], JSON_THROW_ON_ERROR);
'''.replace("ROOT_PATH", "'" + str(root).replace("'", "\\'") + "'")

checks = 0
with tempfile.TemporaryDirectory(prefix="dewawi-configuration-http-") as temp:
    temp = Path(temp)
    site = temp / "site"
    site.mkdir()
    private = [
        "configs/application.ini", "configs/database.ini", "configs/magento.ini",
        "application/Bootstrap.php", "languages/de_DE/default/default.php",
        "logs/cron.log", "logs/extensionless", "session/sess_fixture", "templates/private.phtml",
        "tests/security/fixture.php", "tests/security/file-access.php", "tests/security/url-download.php", "library/DEEC/UrlDownload.php", "library/DEEC/Fixture.php", "library/Zend/Fixture.php",
        ".git/config", ".aws/credentials", ".codex/config.toml", ".agents/private.txt",
        ".env", ".htaccess", "media/.private", "media/database.INI.bak",
        "files/export.sql", "files/export.SQL.gz", "media/error.log", "media/error.log.1",
        "cache/zend_cache---fixture", "cache/zend_cache---internal-metadatas---fixture",
        "cache/zend_cache--a/zend_cache---fixture", "install/config/data.sql",
    ]
    marker = b"Private HTTP security fixture"
    for relative in private:
        if relative == ".htaccess":
            continue
        target = site / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(marker)

    public = {}
    for relative in [
        "css/ui.css", "js/global.js", "images/saved.png", "favicon.ico",
        "library/jQuery/jquery-1.12.4.min.js", "library/Leaflet/leaflet.css",
        "library/MediaManager/plugin.min.js", "library/FileManager/plugin.min.js",
        "install/css/blueprint/screen.css", "install/img/logo.png",
    ]:
        public[relative] = (root / relative).read_bytes()
    for relative in [
        "media/header/logo.png", "media/images/public.png",
        ".well-known/acme-challenge/fixture", "images/configs-logo.png",
    ]:
        public[relative] = b"Public HTTP resource fixture"
    for relative, contents in public.items():
        target = site / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(contents)
    (site / "media/linked.png").symlink_to(site / "media/header/logo.png")
    (site / "index.php").write_text(front)
    for relative in [
        "index.php", "helper.php", "introduction.php", "requirements.php", "config/config.php",
        "templates/header.php", "templates/footer.php", "templates/introduction.php", "templates/requirements.php",
    ]:
        target = site / "install" / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(root / "install" / relative, target)
    private += ["install/config/config.php", "install/templates/header.php"]

    # Every non-entry PHP/include file gets a harmless fixture; vendor code is never executed by the inventory probe.
    scripts = subprocess.run(["git", "ls-files", "-z"], cwd=root, check=True, capture_output=True).stdout.decode().split("\0")
    entries = {"index.php", "install/index.php"}
    entries.update(f"library/{manager}/{name}.php" for manager in ["FileManager", "MediaManager"] for name in ["dialog", "upload", "execute", "ajax_calls", "force_download"])
    inventory = sorted(path for path in scripts if re.search(r"\.(?:php\d*|phtml|pht|phar|inc)(?:[^a-z0-9]|$)", path, re.I))
    blocked = [path for path in inventory if path not in entries]
    for path in blocked:
        target = site / path
        if not target.exists():
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(marker)
    for manager in ["FileManager", "MediaManager"]:
        for original in (root / "library" / manager).rglob("*.php"):
            target = site / original.relative_to(root)
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(original, target)
    (site / "js/filemanager.js").write_bytes((root / "js/filemanager.js").read_bytes())
    (site / "browser-test.html").write_text('''<!DOCTYPE html><html><body>
<form id="fileupload" method="post"></form><form id="download" method="post"></form><pre id="result">WAIT</pre>
<script src="library/jQuery/jquery-1.12.4.min.js"></script>
<script>jQuery(function () { jQuery('#fileupload').on('fileuploadsubmit', function (event, data) { data.formData = data.asArray ? [{name: 'fldr', value: 'uploads/7/0/7/'}] : { fldr: 'uploads/7/0/7/' }; }); });
window.dewawiFileManagerToken = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';</script>
<script src="js/filemanager.js"></script><script>
jQuery(function () {
    var data = {};
    jQuery('#fileupload').trigger('fileuploadsubmit', [data]);
    if (data.formData.csrf_token !== window.dewawiFileManagerToken || data.formData.fldr !== 'uploads/7/0/7/' || jQuery('form input[name=csrf_token]').length !== 2) {
        jQuery('#result').text('FAIL: form or iframe upload token missing'); return;
    }
    var $upload = jQuery('#fileupload');
    var arrayData = {asArray: true};
    $upload.trigger('fileuploadsubmit', [arrayData]);
    if (arrayData.formData.length !== 2 || arrayData.formData[0].value !== 'uploads/7/0/7/' || arrayData.formData[1].name !== 'csrf_token' || arrayData.formData[1].value !== window.dewawiFileManagerToken) {
        jQuery('#result').text('FAIL: array upload token or directory lost'); return;
    }
    $upload.trigger('fileuploadstart').trigger('fileuploadstart');
    if ($upload.attr('aria-busy') !== 'true' || jQuery('#download').attr('aria-busy')) {
        jQuery('#result').text('FAIL: upload busy state missing or blocks independent form'); return;
    }
    $upload.trigger('fileuploadstop');
    if ($upload.attr('aria-busy') !== undefined) { jQuery('#result').text('FAIL: upload stop left busy state'); return; }
    $upload.attr('aria-busy', 'false').trigger('fileuploadstart').trigger('fileuploadstop');
    if ($upload.attr('aria-busy') !== 'false') { jQuery('#result').text('FAIL: original upload ARIA state lost'); return; }
    jQuery.ajax({ type: 'POST', url: 'library/FileManager/ajax_calls.php?action=clear_clipboard', headers: { 'X-Manager-Fixture': 'admin' } })
        .done(function () { jQuery('#result').text('PASS: manager AJAX, forms and upload CSRF tokens'); })
        .fail(function () { jQuery('#result').text('FAIL: AJAX CSRF header missing'); });
});</script></body></html>''')
    for base in ["files", "media"]:
        for folder in ["uploads/7/0/7", "7/0/7", "contacts/7/0/7/1/0/10", "uploads/9/0/9"]:
            target = site / base / folder / "fixture.pdf"
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(b"%PDF-1.4\nManager HTTP fixture\n%%EOF\n")
        (site / base / "uploads/7/0/7/foreign").symlink_to(site / base / "uploads/9/0/9", target_is_directory=True)
        (site / base / "7/0/7/foreign").symlink_to(site / base / "uploads/9/0/9", target_is_directory=True)
        for folder in ["uploads/7/0/7", "7/0/7"]:
            (site / base / folder / "linked.pdf").symlink_to(site / base / "uploads/9/0/9/fixture.pdf")

    # Reproduce the old static-file bypass before applying the current rules.
    current = (root / ".htaccess").read_text()
    baseline = current[current.index("RewriteRule ^files/attachments"):].replace('RewriteRule ^(?:files|media|cache)(?:/|$) index.php [NC,L]\n', '').replace('RewriteRule ^files/import(?:/|$) - [F,NC]\n', '')
    (site / ".htaccess").write_text("RewriteEngine On\n" + baseline)
    with socket.socket() as listener:
        listener.bind(("127.0.0.1", 0))
        port = listener.getsockname()[1]
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
LoadModule dir_module "{modules}/mod_dir.so"
LoadModule autoindex_module "{modules}/mod_autoindex.so"
LoadModule mime_module "{modules}/mod_mime.so"
LoadModule negotiation_module "{modules}/mod_negotiation.so"
LoadModule php_module "{modules}/libphp8.3.so"
DocumentRoot "{site}"
DirectoryIndex index.php
TypesConfig /etc/mime.types
AddType text/plain .ini .sql .log
<Directory "{site}">
    AllowOverride All
    Options Indexes FollowSymLinks MultiViews
    Require all granted
</Directory>
<FilesMatch "\\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>
php_admin_value session.save_path "{temp}"
''')
    subprocess.run([apache, "-t", "-f", str(config)], check=True, capture_output=True)
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

        def fetch(path, method="GET", body=None, headers=None):
            global checks
            connection = http.client.HTTPConnection("127.0.0.1", port, timeout=5)
            connection.request(method, path, body, headers or {})
            response = connection.getresponse()
            result = response.status, response.read()
            connection.close()
            checks += 1
            return result

        for path in ["configs/database.ini", "files/export.sql", "media/error.log", "files/export", "media/error"]:
            assert fetch("/" + path) == (200, marker), f"Baseline exposure not reproduced: {path}"
        (site / ".htaccess").write_text(current)
        shutil.copytree(site, temp / "subdirectory", symlinks=True)
        (temp / "subdirectory").rename(site / "dewawi")
        for prefix in ["", "/dewawi"]:
            for path in blocked:
                status, body = fetch(prefix + "/" + path)
                assert status == 403 and marker not in body, f"Direct PHP/include access allowed: {prefix}/{path} (HTTP {status})"
            for path in private + [
                "configs", "configs/", "CONFIGS/missing.ini", "application/missing.php",
                "install/config", "install/templates/", "configs//database.ini", "%63onfigs/database.ini",
                "configs/database.ini/extra", "files/export%2esql", "media/error%2elog?download=1",
                ".git/config?raw=1", "media/../configs/database.ini",
                "install/database.php", "install/importSQL.php", "install/helper.php", "install/requirements.php",
            ]:
                for method in ["GET", "HEAD", "POST"]:
                    status, body = fetch(prefix + "/" + path, method)
                    assert status == 403 and marker not in body, f"Private path exposed: {method} {prefix}/{path} (HTTP {status})"
            for path, contents in public.items():
                assert fetch(prefix + "/" + path) == (200, contents), f"Public resource affected: {prefix}/{path}"
            assert fetch(prefix + "/media/")[0] in (403, 404), "Directory listing enabled"
            for path in ['files/contacts/unmapped.pdf', 'files/uploads/unmapped.pdf', 'cache/item/42.pdf', 'cache/thumbs/files/unmapped.png', 'media/linked.png']:
                assert fetch(prefix + '/' + path)[0] == 404, f'Unmapped file exposed: {path}'
            for manager in ["FileManager", "MediaManager"]:
                endpoint = prefix + "/library/" + manager + "/"
                folder = "uploads/7/0/7" if manager == "FileManager" else "7/0/7"
                for script in ["dialog", "upload", "execute", "ajax_calls", "force_download"]:
                    for method in ["GET", "POST"]:
                        assert fetch(endpoint + script + ".php", method)[0] == 403, f"Guest manager access: {manager}/{script}"
                headers = {"X-Manager-Fixture": "admin", "X-CSRF-Token": "a" * 64, "Content-Type": "application/x-www-form-urlencoded"}
                status, body = fetch(endpoint + "dialog.php", headers=headers)
                assert status == 200 and b"window.dewawiFileManagerToken" in body and b"/js/filemanager.js" in body, (manager, status, body[:1000])
                for mode in ["unprepared", "switched", "foreign"]:
                    assert fetch(endpoint + "dialog.php", headers={"X-Manager-Fixture": mode})[0] == 403, f"Invalid manager context allowed: {mode}"
                assert fetch(endpoint + "execute.php?action=delete_file", "POST", "path=uploads/7/0/7/fixture.pdf", {"X-Manager-Fixture": "admin"})[0] == 403, "Missing CSRF token accepted"
                assert fetch(endpoint + "execute.php?action=delete_file", "GET", headers=headers)[0] == 405, "GET mutation accepted"
                for url in ['http://127.0.0.1/file.pdf', 'http://169.254.169.254/file.pdf', 'http://10.0.0.1/file.pdf', 'http://[::1]/file.pdf', 'http://[fc00::1]/file.pdf', 'file:///etc/passwd', 'http://2130706433/file.pdf']:
                    status, body = fetch(endpoint + 'upload.php', 'POST', urlencode({'fldr': folder + '/', 'url': url}), headers)
                    assert status == 400 and json.loads(body).get('error') == 'The file could not be downloaded.', f'Unsafe URL upload accepted: {url}'
                    assert b'/var/' not in body and b'Stack trace' not in body, 'URL upload leaked technical details'
                status, body = fetch(endpoint + 'upload.php', 'POST', urlencode({'fldr': folder + '/', 'url': 'https://safe.test/url-upload.pdf'}), dict(headers, **{'X-Simulated-URL': '1'}))
                assert status == 200 and 'error' not in json.loads(body)['files'][0], ('URL upload failed', manager, status, body[:1000])
                installation = site / prefix.lstrip('/')
                uploaded_url = installation / ('files' if manager == 'FileManager' else 'media') / folder / 'url-upload.pdf'
                assert uploaded_url.read_bytes() == b'%PDF-1.4\nManager URL fixture\n%%EOF\n', 'URL upload did not preserve downloaded bytes'
                assert not Path((installation / '.staged-url-path').read_text()).exists(), 'Manager left its URL staging file'
                for path in ["uploads/9/0/9/", folder + "/../9/", folder + "/foreign/", folder + "/%2e%2e/"]:
                    assert fetch(endpoint + "force_download.php", "POST", urlencode({"path": path, "name": "fixture.pdf"}), headers)[0] == 403, f"Foreign manager path accepted: {path}"
                assert fetch(endpoint + "force_download.php", "POST", urlencode({"path": folder + "/", "name": "linked.pdf"}), headers)[0] == 403, "File symlink escaped the manager scope"
                assert fetch(endpoint + "ajax_calls.php?action=save_img", "POST", urlencode({"path": folder + "/", "name": "linked.pdf", "url": "data:image/png;base64,fixture"}), headers)[0] == 403, "Image write followed a foreign file symlink"
                pdf = b"%PDF-1.4\nManager HTTP fixture\n%%EOF\n"
                assert fetch(endpoint + "force_download.php", "POST", urlencode({"path": folder + "/", "name": "fixture.pdf"}), headers) == (200, pdf), "Authorized manager download affected"
                create = urlencode({"path": folder + "/", "name": "created.txt", "new_content": "Created through authorized manager"})
                assert fetch(endpoint + "execute.php?action=create_file", "POST", create, headers)[0] == 200, "Authorized manager write affected"
                assert (site / prefix.lstrip("/") / ("files" if manager == "FileManager" else "media") / folder / "created.txt").read_text() == "Created through authorized manager"
                boundary = "dewawi-manager-upload"
                payload = (f"--{boundary}\r\nContent-Disposition: form-data; name=\"fldr\"\r\n\r\n{folder}/\r\n"
                    f"--{boundary}\r\nContent-Disposition: form-data; name=\"csrf_token\"\r\n\r\n{'a' * 64}\r\n"
                    f"--{boundary}\r\nContent-Disposition: form-data; name=\"files[]\"; filename=\"uploaded.pdf\"\r\nContent-Type: application/pdf\r\n\r\n").encode() + pdf + f"\r\n--{boundary}--\r\n".encode()
                status, body = fetch(endpoint + "upload.php", "POST", payload, {"X-Manager-Fixture": "admin", "Content-Type": "multipart/form-data; boundary=" + boundary})
                assert status == 200 and "error" not in json.loads(body)["files"][0], (manager, status, body[:1000])
                assert (site / prefix.lstrip("/") / ("files" if manager == "FileManager" else "media") / folder / "uploaded.pdf").read_bytes() == pdf, "Authorized multipart upload affected"
                unsafe_payload = payload.replace(b'filename="uploaded.pdf"', b'filename="evil.php.png"')
                assert fetch(endpoint + 'upload.php', 'POST', unsafe_payload, {'X-Manager-Fixture': 'admin', 'Content-Type': 'multipart/form-data; boundary=' + boundary})[0] == 403, 'Executable multipart filename accepted'
                if manager == "FileManager":
                    for mode in ["readonly", "shared"]:
                        scoped_headers = dict(headers, **{"X-Manager-Fixture": mode})
                        assert fetch(endpoint + "dialog.php", headers=scoped_headers)[0] == 200, f"Valid shared/read context denied: {mode}"
                        assert fetch(endpoint + "force_download.php", "POST", "path=contacts/7/0/7/1/0/10/&name=fixture.pdf", scoped_headers) == (200, pdf)
                    assert fetch(endpoint + "execute.php?action=delete_file", "POST", "path=contacts/7/0/7/1/0/10/fixture.pdf", dict(headers, **{"X-Manager-Fixture": "readonly"}))[0] == 403, "Read-only manager allowed writes"
                    assert fetch(endpoint + "ajax_calls.php?action=save_img", "POST", "path=contacts/7/0/7/1/0/10/", dict(headers, **{"X-Manager-Fixture": "readonly"}))[0] == 403, "Read-only image write accepted"
                    assert fetch(endpoint + 'dialog.php', 'POST', urlencode({'url': 'http://127.0.0.1/file.pdf'}), dict(headers, **{'X-Manager-Fixture': 'readonly'}))[0] == 403, 'Read-only dialog initiated a URL upload'
            for path in ["/files/export", "/media/error"]:
                status, body = fetch(prefix + path)
                assert status == 404 and marker not in body, "MultiViews exposed a private file through an extensionless URL"
            for path in ["/", "/index.php", "/contacts/contact/index?format=json", "/contacts/contact/save/id/42"]:
                headers = {"X-Requested-With": "XMLHttpRequest", "Content-Type": "application/x-www-form-urlencoded"}
                status, body = fetch(prefix + path, "POST", urlencode({"name": "AJAX fixture"}), headers)
                assert status == 200, f"Front controller affected: {prefix}{path} (HTTP {status})"
                result = json.loads(body)
                assert result["uri"] == prefix + path and result["method"] == "POST" and result["ajax"] and result["post"] == {"name": "AJAX fixture"}, result
                if "/contacts/" in path:
                    assert result["module"] == "contacts" and result["controller"] == "contact", result
                    assert result["action"] == ("save" if "/save/" in path else "index"), result
                    if "/save/" in path:
                        assert result["id"] == "42", result

            # Exercise real installer entry points without a database or any write step.
            installation = site / prefix.lstrip("/")
            (installation / "configs/database.ini").unlink()
            for path in ["/install/", "/install/index.php"]:
                status, body = fetch(prefix + path)
                assert status == 200 and b"DEWAWI Setup Wizard" in body, "Installer introduction unavailable"
            connection = http.client.HTTPConnection("127.0.0.1", port, timeout=5)
            connection.request("GET", prefix + "/install/index.php")
            response = connection.getresponse()
            cookie = response.getheader("Set-Cookie").split(";", 1)[0]
            token = re.search(rb'name="csrf_token" value="([a-f0-9]{64})"', response.read()).group(1).decode()
            connection.close()
            assert fetch(prefix + "/install/index.php", "POST", "nextStep=done", {"Cookie": cookie, "Content-Type": "application/x-www-form-urlencoded"})[0] == 403, "Installer CSRF accepted"
            status, body = fetch(prefix + "/install/index.php", "POST", urlencode({"nextStep": "requirements", "csrf_token": token}), {"Cookie": cookie, "Content-Type": "application/x-www-form-urlencoded"})
            assert status == 200 and b"PHP Extensions" in body, "Installer POST step unavailable"
        chromium = shutil.which("chromium")
        if not chromium:
            raise RuntimeError("Chromium is required for the manager CSRF and upload ARIA browser regression")
        browser = subprocess.run([chromium, "--headless", "--disable-gpu", "--disable-background-networking", "--no-first-run", "--no-default-browser-check", "--user-data-dir=" + str(temp / "browser-profile"), "--virtual-time-budget=5000", "--dump-dom", f"http://127.0.0.1:{port}/browser-test.html"], capture_output=True, text=True, timeout=30)
        assert browser.returncode == 0 and '<pre id="result">PASS:' in browser.stdout, browser.stdout[-3000:] + browser.stderr[-2000:]
        print(f"PASS: {checks} Apache HTTP cases; {len(inventory)} inventoried PHP/include files; manager CSRF and upload ARIA browser regression")
    except Exception:
        print((temp / "error.log").read_text())
        raise
    finally:
        server.terminate()
        try:
            server.communicate(timeout=5)
        except subprocess.TimeoutExpired:
            server.kill()
            server.communicate()
