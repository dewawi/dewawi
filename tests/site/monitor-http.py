#!/usr/bin/env python3
"""Exercise the availability monitor with isolated HTTP and trusted TLS fixtures."""

from __future__ import annotations

import json
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
import socket
import ssl
import subprocess
import sys
import tempfile
import threading
import time

import monitor


OPENSSL = "openssl"
ROOT = Path(__file__).resolve().parents[2]
checks = 0
methods = []


def check(condition: bool, message: str) -> None:
    global checks
    checks += 1
    if not condition:
        raise AssertionError(message)


class FixtureHandler(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    def log_message(self, format, *args):  # noqa: A002, ANN001
        pass

    def do_HEAD(self):
        methods.append("HEAD")
        self.respond(head=True)

    def do_GET(self):
        methods.append("GET")
        self.respond(head=False)

    def do_POST(self):
        methods.append("POST")
        self.send_response(405)
        self.send_header("Content-Length", "0")
        self.end_headers()

    def respond(self, head: bool) -> None:
        path = self.path.split("?", 1)[0]
        if path == "/redirect301":
            self.redirect(301, "/ok")
            return
        if path == "/redirect308":
            self.redirect(308, "/ok")
            return
        if path == "/loop-a":
            self.redirect(301, "/loop-b")
            return
        if path == "/loop-b":
            self.redirect(301, "/loop-a")
            return
        if path == "/external":
            self.redirect(301, "https://unexpected.invalid/")
            return
        if path == "/slow":
            time.sleep(0.4)
        status = 500 if path == "/error" else 404 if path in {"/missing", "/noindex/sitemap.xml"} else 200
        body = b"DEWAWI monitoring fixture"
        self.send_response(status)
        self.send_header("Content-Type", "text/plain; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        if not head:
            try:
                self.wfile.write(body)
            except (BrokenPipeError, ConnectionResetError):
                pass

    def redirect(self, status: int, location: str) -> None:
        self.send_response(status)
        self.send_header("Location", location)
        self.send_header("Content-Length", "0")
        self.end_headers()


def run(command):
    return subprocess.run(command, check=True, capture_output=True, text=True)


def create_certificates(directory: Path) -> dict[str, Path]:
    ca_key = directory / "ca.key"
    ca_cert = directory / "ca.pem"
    run([
        OPENSSL, "req", "-x509", "-newkey", "rsa:2048", "-nodes",
        "-keyout", str(ca_key), "-out", str(ca_cert), "-days", "3650",
        "-subj", "/CN=DEWAWI Monitoring Test CA",
        "-addext", "basicConstraints=critical,CA:TRUE",
        "-addext", "keyUsage=critical,keyCertSign,cRLSign",
    ])
    (directory / "index.txt").write_text("")
    (directory / "serial").write_text("1000\n")
    (directory / "newcerts").mkdir()
    config = directory / "openssl.cnf"
    config.write_text(f"""
[ca]
default_ca = test_ca
[test_ca]
database = {directory}/index.txt
new_certs_dir = {directory}/newcerts
certificate = {ca_cert}
private_key = {ca_key}
serial = {directory}/serial
default_md = sha256
policy = test_policy
copy_extensions = copy
unique_subject = no
[test_policy]
commonName = supplied
[server_cert]
basicConstraints = critical,CA:FALSE
keyUsage = critical,digitalSignature,keyEncipherment
extendedKeyUsage = serverAuth
""")

    certificates = {"ca": ca_cert}
    for name, common_name, days, expired in [
        ("valid", "localhost", "40", False),
        ("warning", "localhost", "5", False),
        ("wrong", "wrong.example.test", "40", False),
        ("expired", "localhost", "40", True),
    ]:
        key = directory / f"{name}.key"
        request = directory / f"{name}.csr"
        certificate = directory / f"{name}.pem"
        run([
            OPENSSL, "req", "-new", "-newkey", "rsa:2048", "-nodes",
            "-keyout", str(key), "-out", str(request),
            "-subj", f"/CN={common_name}", "-addext", f"subjectAltName=DNS:{common_name}",
        ])
        command = [
            OPENSSL, "ca", "-batch", "-notext", "-config", str(config),
            "-extensions", "server_cert", "-in", str(request), "-out", str(certificate),
        ]
        if expired:
            command += ["-startdate", "20200101000000Z", "-enddate", "20210101000000Z"]
        else:
            command += ["-days", days]
        run(command)
        certificates[name] = certificate
        certificates[name + "_key"] = key
    return certificates


def start_server(certificate: Path | None = None, key: Path | None = None):
    server = ThreadingHTTPServer(("127.0.0.1", 0), FixtureHandler)
    if certificate is not None:
        context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
        context.load_cert_chain(certificate, key)
        server.socket = context.wrap_socket(server.socket, server_side=True)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    return server, thread


def target(name, url, status=200, **options):
    return {"name": name, "url": url, "expected_status": status, **options}


def invoke(config_path: Path, ca_file: Path, output_format: str):
    return subprocess.run(
        [sys.executable, str(ROOT / "tests/site/monitor.py"), "--config", str(config_path), "--ca-file", str(ca_file), "--format", output_format],
        capture_output=True, text=True, timeout=20,
    )


with tempfile.TemporaryDirectory(prefix="dewawi-monitor-http-") as work:
    work = Path(work)
    certificates = create_certificates(work)
    http_server, http_thread = start_server()
    valid_server, valid_thread = start_server(certificates["valid"], certificates["valid_key"])
    warning_server, warning_thread = start_server(certificates["warning"], certificates["warning_key"])
    wrong_server, wrong_thread = start_server(certificates["wrong"], certificates["wrong_key"])
    expired_server, expired_thread = start_server(certificates["expired"], certificates["expired_key"])
    servers = [http_server, valid_server, warning_server, wrong_server, expired_server]
    try:
        http = f"http://localhost:{http_server.server_port}"
        valid = f"https://localhost:{valid_server.server_port}"
        warning = f"https://localhost:{warning_server.server_port}"
        wrong = f"https://127.0.0.1:{wrong_server.server_port}"
        expired = f"https://localhost:{expired_server.server_port}"

        check(monitor.certificate_threshold(31, [30, 14, 7]) is None, "Certificate above all thresholds warned")
        check(monitor.certificate_threshold(30, [30, 14, 7]) == 30, "30-day threshold failed")
        check(monitor.certificate_threshold(14, [30, 14, 7]) == 14, "14-day threshold failed")
        check(monitor.certificate_threshold(7, [30, 14, 7]) == 7, "7-day threshold failed")

        def slow_resolver(*args, **kwargs):
            time.sleep(0.2)
            return []

        _, dns_error, _ = monitor.resolve_host("fixture.invalid", 80, 0.05, slow_resolver)
        check(dns_error == "dns_timeout", "DNS timeout was not bounded")

        original_open_tcp = monitor.open_tcp
        try:
            monitor.open_tcp = lambda addresses, timeout: (None, "connection_timeout", timeout)
            timeout_config = monitor.validate_config({"targets": [target("TCP timeout", "http://localhost/")]})
            timeout_report = monitor.Monitor(timeout_config, str(certificates["ca"])).run()
            check(timeout_report["targets"][0]["issues"][0]["code"] == "connection_timeout", "TCP connection timeout was not classified")
        finally:
            monitor.open_tcp = original_open_tcp

        ok_config = monitor.validate_config({
            "timeout": 1,
            "certificate_warning_days": [30, 14, 7],
            "targets": [
                target("Homepage", http + "/ok?token=do-not-report&q=visible"),
                target("CMS subdirectory", http + "/subdirectory/cms"),
                target("Product", http + "/product"),
                target("Category", http + "/category"),
                target("Search", http + "/search?q=fixture"),
                target("Robots", http + "/robots.txt", method="HEAD"),
                target("Indexable sitemap", http + "/sitemap.xml"),
                target("Non-indexable sitemap", http + "/noindex/sitemap.xml", 404),
                target("Permanent redirect", http + "/redirect301", 301, expected_location=http + "/ok"),
                target("Method-preserving redirect", http + "/redirect308", 308, expected_location=http + "/ok"),
                target("Follow canonical redirect", http + "/redirect301", 200, follow_redirects=True, expected_final_url=http + "/ok"),
                target("Trusted HTTPS", valid + "/ok"),
            ],
        })
        ok_report = monitor.Monitor(ok_config, str(certificates["ca"])).run()
        check(ok_report["state"] == "OK" and ok_report["exitCode"] == 0, f"Healthy endpoints failed: {ok_report}")
        check(all(item["http"]["status"] in {200, 301, 308, 404} for item in ok_report["targets"]), "Expected HTTP statuses were lost")
        check(ok_report["targets"][-1]["tls"]["daysRemaining"] > 30, "Valid certificate lifetime was not reported")
        serialized = json.dumps(ok_report)
        check("do-not-report" not in serialized and "%3Credacted%3E" in serialized, "Sensitive query parameter was reported")

        ok_path = work / "ok.json"
        ok_path.write_text(json.dumps({
            "timeout": 1,
            "targets": [target("Homepage", http + "/ok"), target("HTTPS", valid + "/ok")],
        }))
        cli_ok = invoke(ok_path, certificates["ca"], "json")
        check(cli_ok.returncode == 0 and json.loads(cli_ok.stdout)["state"] == "OK", "JSON OK exit contract failed")
        cli_text = invoke(ok_path, certificates["ca"], "text")
        check(cli_text.returncode == 0 and "OK - DEWAWI availability check" in cli_text.stdout and "HTTP 200" in cli_text.stdout, "Text output is incomplete")

        warning_path = work / "warning.json"
        warning_path.write_text(json.dumps({
            "timeout": 1,
            "certificate_warning_days": [30, 14, 7],
            "targets": [
                target("Expiring HTTPS", warning + "/ok"),
                target("Slow response", http + "/slow", response_warning_ms=1),
            ],
        }))
        cli_warning = invoke(warning_path, certificates["ca"], "json")
        warning_report = json.loads(cli_warning.stdout)
        check(cli_warning.returncode == 1 and warning_report["state"] == "WARNING", "Certificate warning exit contract failed")
        check(warning_report["targets"][0]["issues"][0]["code"] == "certificate_expiry_7", "Certificate warning threshold was not identified")
        check(warning_report["targets"][1]["issues"][0]["code"] == "response_slow", "Slow response warning was not identified")

        with socket.socket() as unavailable:
            unavailable.bind(("127.0.0.1", 0))
            unavailable_port = unavailable.getsockname()[1]
        critical_config = {
            "timeout": 0.15,
            "targets": [
                target("Server error", http + "/error"),
                target("Unexpected missing page", http + "/missing"),
                target("Timeout", http + "/slow"),
                target("Redirect loop", http + "/loop-a", follow_redirects=True),
                target("External redirect", http + "/external", follow_redirects=True),
                target("Unavailable port", f"http://127.0.0.1:{unavailable_port}/"),
                target("Unknown host", "http://unreachable.invalid/"),
                target("Wrong certificate host", wrong + "/ok"),
                target("Expired certificate", expired + "/ok"),
            ],
        }
        critical_path = work / "critical.json"
        critical_path.write_text(json.dumps(critical_config))
        cli_critical = invoke(critical_path, certificates["ca"], "json")
        critical_report = json.loads(cli_critical.stdout)
        codes = {issue["code"] for item in critical_report["targets"] for issue in item["issues"]}
        check(cli_critical.returncode == 2 and critical_report["state"] == "CRITICAL", "Critical exit contract failed")
        for code in [
            "http_5xx", "http_status_unexpected", "http_timeout", "redirect_loop",
            "redirect_host_unexpected", "connection_failed", "dns_failed",
            "certificate_hostname_mismatch", "certificate_expired",
        ]:
            check(code in codes, f"Monitoring did not classify {code}: {codes}")

        invalid_path = work / "invalid.json"
        invalid_path.write_text(json.dumps({"targets": [target("Unsafe", http + "/ok", method="POST")]}))
        cli_unknown = invoke(invalid_path, certificates["ca"], "json")
        unknown_report = json.loads(cli_unknown.stdout)
        check(cli_unknown.returncode == 3 and unknown_report["state"] == "UNKNOWN", "Invalid configuration exit contract failed")
        check(unknown_report["issues"][0]["code"] == "configuration_invalid", "Invalid configuration was not classified")
        check("POST" not in methods and set(methods) == {"GET", "HEAD"}, f"Monitor issued an unsafe method: {methods}")
    finally:
        for server in servers:
            server.shutdown()
            server.server_close()

print(f"PASS: {checks} availability, HTTP, TLS, output, exit-code and read-only monitoring checks")
