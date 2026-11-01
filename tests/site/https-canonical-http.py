#!/usr/bin/env python3
"""Exercise the documented canonical HTTPS virtual-host strategy in isolation."""
import grp
import http.client
import os
from pathlib import Path
import pwd
import shutil
import socket
import ssl
import subprocess
import tempfile
import time


apache = shutil.which("apache2")
openssl = shutil.which("openssl")
if not apache or not openssl:
    raise SystemExit("Apache and OpenSSL are required for this HTTP regression test")

checks = 0
with tempfile.TemporaryDirectory(prefix="dewawi-https-http-") as directory:
    temp = Path(directory)
    site = temp / "site"
    site.mkdir()
    (site / ".well-known/acme-challenge").mkdir(parents=True)
    (site / ".well-known/acme-challenge/fixture").write_text("acme fixture")
    (site / "index.php").write_text("<?php header('Content-Type: text/plain'); echo 'canonical fixture';")

    certificate = temp / "certificate.pem"
    key = temp / "certificate.key"
    subprocess.run([
        openssl, "req", "-x509", "-newkey", "rsa:2048", "-nodes",
        "-keyout", str(key), "-out", str(certificate), "-days", "1",
        "-subj", "/CN=canonical.example.test",
        "-addext", "subjectAltName=DNS:canonical.example.test,DNS:alternate.example.test",
    ], check=True, capture_output=True)

    def free_port():
        with socket.socket() as listener:
            listener.bind(("127.0.0.1", 0))
            return listener.getsockname()[1]

    http_port = free_port()
    https_port = free_port()
    modules = Path("/usr/lib/apache2/modules")
    config = temp / "apache.conf"
    config.write_text(f'''
ServerRoot "{temp}"
DefaultRuntimeDir "{temp}"
PidFile "{temp}/apache.pid"
Listen 127.0.0.1:{http_port}
Listen 127.0.0.1:{https_port} https
ServerName localhost
User {pwd.getpwuid(os.getuid()).pw_name}
Group {grp.getgrgid(os.getgid()).gr_name}
ErrorLog "{temp}/error.log"
LogLevel warn
LoadModule mpm_prefork_module "{modules}/mod_mpm_prefork.so"
LoadModule authz_core_module "{modules}/mod_authz_core.so"
LoadModule rewrite_module "{modules}/mod_rewrite.so"
LoadModule headers_module "{modules}/mod_headers.so"
LoadModule ssl_module "{modules}/mod_ssl.so"
LoadModule socache_shmcb_module "{modules}/mod_socache_shmcb.so"
LoadModule mime_module "{modules}/mod_mime.so"
LoadModule dir_module "{modules}/mod_dir.so"
LoadModule php_module "{modules}/libphp8.3.so"
TypesConfig /etc/mime.types
DirectoryIndex index.php
<FilesMatch "\\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>
<Directory "{site}">
    AllowOverride None
    Require all granted
</Directory>

<VirtualHost 127.0.0.1:{http_port}>
    ServerName unknown.invalid
    RewriteEngine On
    RewriteRule ^ - [R=421,L]
</VirtualHost>

<VirtualHost 127.0.0.1:{http_port}>
    ServerName canonical.example.test
    ServerAlias alternate.example.test
    DocumentRoot "{site}"
    RewriteEngine On
    RewriteRule ^/?\\.well-known/acme-challenge/ - [END]
    RewriteCond %{{REQUEST_METHOD}} =GET [OR]
    RewriteCond %{{REQUEST_METHOD}} =HEAD
    RewriteRule ^ https://canonical.example.test:{https_port}%{{REQUEST_URI}} [R=301,END,NE]
    RewriteRule ^ https://canonical.example.test:{https_port}%{{REQUEST_URI}} [R=308,END,NE]
</VirtualHost>

<VirtualHost 127.0.0.1:{https_port}>
    ServerName unknown.invalid
    SSLEngine On
    SSLCertificateFile "{certificate}"
    SSLCertificateKeyFile "{key}"
    RewriteEngine On
    RewriteRule ^ - [R=421,L]
</VirtualHost>

<VirtualHost 127.0.0.1:{https_port}>
    ServerName canonical.example.test
    ServerAlias alternate.example.test
    DocumentRoot "{site}"
    SSLEngine On
    SSLCertificateFile "{certificate}"
    SSLCertificateKeyFile "{key}"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
    RewriteEngine On
    RewriteCond %{{HTTP_HOST}} !^canonical\\.example\\.test(?::[0-9]+)?$ [NC]
    RewriteCond %{{REQUEST_METHOD}} =GET [OR]
    RewriteCond %{{REQUEST_METHOD}} =HEAD
    RewriteRule ^ https://canonical.example.test:{https_port}%{{REQUEST_URI}} [R=301,END,NE]
    RewriteCond %{{HTTP_HOST}} !^canonical\\.example\\.test(?::[0-9]+)?$ [NC]
    RewriteRule ^ https://canonical.example.test:{https_port}%{{REQUEST_URI}} [R=308,END,NE]
    RewriteRule ^ index.php [END]
</VirtualHost>
''')
    subprocess.run([apache, "-t", "-f", str(config)], check=True, capture_output=True)
    server = subprocess.Popen(
        [apache, "-f", str(config), "-DFOREGROUND"],
        stdout=subprocess.PIPE, stderr=subprocess.PIPE, start_new_session=True,
    )
    try:
        for _ in range(100):
            if server.poll() is not None:
                raise RuntimeError(server.communicate()[1].decode())
            try:
                with socket.create_connection(("127.0.0.1", http_port), timeout=0.1):
                    break
            except OSError:
                time.sleep(0.05)
        else:
            raise RuntimeError("Isolated Apache did not start")

        insecure = ssl.create_default_context()
        insecure.check_hostname = False
        insecure.verify_mode = ssl.CERT_NONE

        def fetch(secure, host, path, method="GET", headers=None):
            global checks
            request_headers = {"Host": host}
            request_headers.update(headers or {})
            connection_class = http.client.HTTPSConnection if secure else http.client.HTTPConnection
            port = https_port if secure else http_port
            options = {"context": insecure} if secure else {}
            connection = connection_class("127.0.0.1", port, timeout=5, **options)
            connection.request(method, path, "fixture" if method == "POST" else None, request_headers)
            response = connection.getresponse()
            result = response.status, dict(response.getheaders()), response.read()
            connection.close()
            checks += 1
            return result

        canonical_location = f"https://canonical.example.test:{https_port}"
        for host in ["canonical.example.test", "alternate.example.test"]:
            for path in ["/", "/subdirectory/page?x=1", "/old-slug", "/media/image.webp", "/files/download.pdf", "/robots.txt", "/sitemap.xml", "/cookie-consent"]:
                status, headers, _ = fetch(False, host, path)
                assert status == 301 and headers["Location"] == canonical_location + path, (host, path, status, headers)
            status, headers, _ = fetch(False, host, "/contact/send", "HEAD")
            assert status == 301 and headers["Location"] == canonical_location + "/contact/send"
            status, headers, _ = fetch(False, host, "/contact/send", "POST")
            assert status == 308 and headers["Location"] == canonical_location + "/contact/send"

        status, headers, body = fetch(False, "canonical.example.test", "/.well-known/acme-challenge/fixture")
        assert status == 200 and body == b"acme fixture" and "Location" not in headers
        status, headers, _ = fetch(False, "canonical.example.test", "/", headers={"X-Forwarded-Proto": "https", "Forwarded": "proto=https"})
        assert status == 301 and headers["Location"] == canonical_location + "/", "Untrusted proxy headers bypassed HTTPS enforcement"

        for path in ["/", "/subdirectory/page", "/old-slug", "/media/image.webp", "/files/download.pdf", "/robots.txt", "/sitemap.xml", "/cookie-consent"]:
            status, headers, body = fetch(True, f"canonical.example.test:{https_port}", path)
            assert status == 200 and body == b"canonical fixture" and "Location" not in headers, (path, status, headers)
            assert headers["X-Content-Type-Options"] == "nosniff"
            assert headers["Referrer-Policy"] == "strict-origin-when-cross-origin"
            assert headers["Permissions-Policy"] == "camera=(), microphone=(), geolocation=()"
            assert "Strict-Transport-Security" not in headers

        status, headers, _ = fetch(True, f"alternate.example.test:{https_port}", "/old-slug?x=1")
        assert status == 301 and headers["Location"] == canonical_location + "/old-slug?x=1"
        status, headers, _ = fetch(True, f"alternate.example.test:{https_port}", "/contact/send", "POST")
        assert status == 308 and headers["Location"] == canonical_location + "/contact/send"
        status, headers, body = fetch(True, f"canonical.example.test:{https_port}", "/", headers={"X-Forwarded-Proto": "http", "Forwarded": "proto=http"})
        assert status == 200 and body == b"canonical fixture" and "Location" not in headers, "Untrusted proxy headers forced a redirect"

        assert fetch(False, "unknown.example.test", "/")[0] == 421
        assert fetch(True, f"unknown.example.test:{https_port}", "/")[0] == 421
        print(f"PASS: {checks} isolated canonical HTTP/HTTPS, host, method, ACME, proxy-header and security-header cases")
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
