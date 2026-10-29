#!/usr/bin/env python3
"""Exercise real ACL, DEEC actions and trash authorization through isolated Apache requests."""
import grp
import http.client
import json
import os
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
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
require ROOT_PATH . '/tests/security/fixtures/tenant-access.php';
Zend_Session::setOptions(['save_path' => __DIR__ . '/session', 'use_cookies' => false, 'cache_limiter' => '']);
$mode = $_SERVER['HTTP_X_FIXTURE_ROLE'] ?? 'guest';
$adapter = tenantAccessFixture($mode, (int)($_SERVER['HTTP_X_FIXTURE_CLIENT'] ?? 7));
$before = serialize($adapter->rows);
$front = Zend_Controller_Front::getInstance();
$front->setControllerDirectory(['default' => ROOT_PATH . '/application/controllers', 'contacts' => ROOT_PATH . '/application/modules/contacts/controllers']);
$request = new Zend_Controller_Request_Http();
$response = new Zend_Controller_Response_Http();
$front->setRequest($request)->setResponse($response);
$front->getRouter()->route($request);
ob_start();
try { tenantAccessDispatch($request, $response); }
catch (Throwable $exception) { error_log((string)$exception); $response->setHttpResponseCode(500)->setBody(json_encode(['ok' => false, 'message' => 'Internal error'])); }
ob_end_clean();
$response->setHeader('X-Fixture-Changed', serialize($adapter->rows) === $before ? '0' : '1');
$response->setHeader('Content-Type', 'application/json', true)->sendResponse();
'''.replace("ROOT_PATH", "'" + str(root).replace("'", "\\'") + "'")

with tempfile.TemporaryDirectory(prefix="dewawi-tenant-http-") as temp:
    temp = Path(temp)
    site = temp / "site"
    site.mkdir()
    (site / "session").mkdir()
    (site / "index.php").write_text(front)
    (site / ".htaccess").write_text((root / ".htaccess").read_text())
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

        checks = 0

        def fetch(prefix, route, role="full", fields=None, selected=7):
            connection = http.client.HTTPConnection("127.0.0.1", port, timeout=5)
            headers = {"X-Fixture-Role": role, "X-Fixture-Client": str(selected), "X-Requested-With": "XMLHttpRequest"}
            body = None
            if fields is not None:
                if route == "/default/trash/add":
                    body = json.dumps(fields)
                    headers["Content-Type"] = "application/json"
                else:
                    body = urlencode(fields)
                    headers["Content-Type"] = "application/x-www-form-urlencoded"
            connection.request("POST" if fields is not None else "GET", prefix + route, body, headers)
            response = connection.getresponse()
            payload = response.read()
            status, changed = response.status, response.getheader("X-Fixture-Changed")
            connection.close()
            assert status != 500, f"Fixture failed: {route}: {payload!r}; log: {(temp / 'error.log').read_text()[-2500:]}"
            for secret in [b"Foreign secret", b"Invalid parent secret", b"Stack trace", b"SQLSTATE", str(root).encode()]:
                assert secret not in payload, f"Sensitive information returned: {route}"
            return status, json.loads(payload), changed

        for prefix in ["", "/dewawi"]:
            if prefix:
                sub = site / "dewawi"
                sub.mkdir()
                (sub / "session").mkdir()
                (sub / "index.php").write_text(front)
                (sub / ".htaccess").write_text((root / ".htaccess").read_text())
            status, result, changed = fetch(prefix, "/contacts/contactperson/get/id/11")
            assert status == 200 and result["ok"] and result["item"]["clientid"] == 1 and changed == "0"
            checks += 1
            for action in ["get", "edit", "copy"]:
                for object_id in [21, 22, 999]:
                    status, result, changed = fetch(prefix, f"/contacts/contactperson/{action}/id/{object_id}", fields={"firstname": "tampered"} if action == "edit" else None)
                    assert status == 200 and not result["ok"] and changed == "0", f"IDOR write/read accepted: {action}/{object_id}"
                    checks += 1
            status, result, changed = fetch(prefix, "/contacts/contactperson/edit/id/11", fields={"firstname": "Saved", "clientid": 9, "parentid": 20, "module": "sales", "controller": "quote"})
            assert status == 200 and result["ok"] and result["id"] == 11 and result["values"]["firstname"] == "Saved" and changed == "1"
            checks += 1
            status, result, changed = fetch(prefix, "/contacts/contactperson/copy/id/11")
            assert status == 200 and result["ok"] and changed == "1"
            checks += 1
            for role in ["readonly", "denied", "guest"]:
                for action in ["edit", "copy"]:
                    status, result, changed = fetch(prefix, f"/contacts/contactperson/{action}/id/11", role, {"firstname": "tampered"})
                    assert status == 403 and not result["ok"] and changed == "0", f"ACL bypass: {role}/{action}"
                    checks += 1
            for role, object_id, expected_change in [("full", 11, "1"), ("full", 21, "0"), ("full", 22, "0"), ("readonly", 11, "0"), ("guest", 11, "0")]:
                status, result, changed = fetch(prefix, "/default/trash/add", role, {"id": [object_id], "module": "contacts", "controller": "contactperson", "clientid": 9})
                assert changed == expected_change, f"Trash target permission failed: {role}/{object_id}"
                if expected_change == "1":
                    assert result["ok"] and result["deletedIds"] == [11]
                checks += 1
            status, result, changed = fetch(prefix, "/contacts/contactperson/get/id/11", selected=8)
            assert status == 200 and result["ok"] and result["item"]["clientid"] == 1, "Authorized child lost shared parent access"
            checks += 1
        print(f"PASS: {checks} Apache object/ACL/AJAX/trash cases in root and subdirectory installations")
    finally:
        server.terminate()
        try:
            server.communicate(timeout=5)
        except subprocess.TimeoutExpired:
            server.kill()
            server.communicate()
