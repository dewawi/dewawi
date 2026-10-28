#!/usr/bin/env python3
"""Exercise the repository's Apache rules in isolated root and subdirectory installations."""
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
set_include_path(ROOT_PATH . '/library' . PATH_SEPARATOR . get_include_path());
require_once 'Zend/Application.php';
$application = new Zend_Application('production');
$front = Zend_Controller_Front::getInstance();
$front->setControllerDirectory([
    'default' => ROOT_PATH . '/application/controllers',
    'contacts' => ROOT_PATH . '/application/modules/contacts/controllers',
]);
$request = new Zend_Controller_Request_Http();
$front->getRouter()->route($request);
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
        "tests/security/fixture.php", "library/DEEC/Fixture.php", "library/Zend/Fixture.php",
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
        "media/header/logo.png", "files/images/public.png", "files/contacts/public.pdf",
        "cache/item/42.pdf", "cache/thumbs/media/public.png", "cache/thumbs/files/public.png",
        ".well-known/acme-challenge/fixture", "images/configs-logo.png",
    ]:
        public[relative] = b"Public HTTP resource fixture"
    for relative, contents in public.items():
        target = site / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(contents)
    (site / "media/linked.png").symlink_to(site / "media/header/logo.png")
    public["media/linked.png"] = public["media/header/logo.png"]
    (site / "index.php").write_text(front)
    for relative in [
        "index.php", "introduction.php", "requirements.php", "config/config.php",
        "templates/header.php", "templates/footer.php", "templates/introduction.php", "templates/requirements.php",
    ]:
        target = site / "install" / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(root / "install" / relative, target)
    private += ["install/config/config.php", "install/templates/header.php"]

    # Reproduce the old static-file bypass before applying the current rules.
    current = (root / ".htaccess").read_text()
    (site / ".htaccess").write_text("RewriteEngine On\n" + current[current.index("RewriteRule ^files/attachments"):])
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
            for path in private + [
                "configs", "configs/", "CONFIGS/missing.ini", "application/missing.php",
                "install/config", "install/templates/", "configs//database.ini", "%63onfigs/database.ini",
                "configs/database.ini/extra", "files/export%2esql", "media/error%2elog?download=1",
                ".git/config?raw=1", "media/../configs/database.ini",
            ]:
                for method in ["GET", "HEAD", "POST"]:
                    status, body = fetch(prefix + "/" + path, method)
                    assert status == 403 and marker not in body, f"Private path exposed: {method} {prefix}/{path} (HTTP {status})"
            for path, contents in public.items():
                assert fetch(prefix + "/" + path) == (200, contents), f"Public resource affected: {prefix}/{path}"
            assert fetch(prefix + "/media/")[0] == 403, "Directory listing enabled"
            for path in ["/files/export", "/media/error"]:
                status, body = fetch(prefix + path)
                assert status == 200 and json.loads(body)["uri"] == prefix + path, "MultiViews exposed a private file through an extensionless URL"
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
            status, body = fetch(prefix + "/install/index.php", "POST", "nextStep=requirements", {"Content-Type": "application/x-www-form-urlencoded"})
            assert status == 200 and b"PHP Extensions" in body, "Installer POST step unavailable"
        print(f"PASS: {checks} Apache HTTP cases (baseline exposure, private paths, GET/HEAD/POST, public assets, cache previews, AJAX/Zend routing, installer and subdirectory deployment)")
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
