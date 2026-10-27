#!/usr/bin/env python3
"""Exercise list footers through the real front controller and configured read-only database."""

from pathlib import Path
import http.client
import json
import os
import re
import signal
import socket
import subprocess
import time
from urllib.parse import urlencode


root = Path(__file__).resolve().parents[2]
php = os.environ.get("PHP_BINARY", "php")

session_script = r'''<?php
define('BASE_PATH', ROOT_PATH);
define('APPLICATION_PATH', BASE_PATH . '/application');
set_include_path(BASE_PATH . '/library' . PATH_SEPARATOR . get_include_path());
require_once 'Zend/Application.php';
$application = new Zend_Application('production', [
    'config' => [BASE_PATH . '/configs/application.ini', BASE_PATH . '/configs/database.ini'],
]);
$bootstrap = $application->getBootstrap();
$bootstrap->bootstrap('db');
$db = $bootstrap->getResource('db');
$user = $db->fetchRow(
    'SELECT u.id, u.clientid FROM user u '
    . 'INNER JOIN client c ON c.id = u.clientid AND c.deleted = 0 '
    . 'WHERE u.admin = 1 AND u.activated = 1 AND u.deleted = 0 '
    . 'ORDER BY u.id ASC LIMIT 1'
);
if (!$user) {
    fwrite(STDERR, "No active administrator is available for the HTTP integration test.\n");
    exit(77);
}
$bootstrap->bootstrap('DeecSession');
Zend_Session::start();
$sessionId = Zend_Session::getId();
$auth = Zend_Auth::getInstance();
$auth->setStorage(new Zend_Auth_Storage_Session());
$auth->getStorage()->write((object)[
    'id' => (int)$user['id'],
    'clientid' => (int)$user['clientid'],
    'language' => 'de_DE',
]);
$_SESSION[DEEC_Session::REMEMBER_KEY] = false;
session_write_close();
echo json_encode([
    'session_id' => $sessionId,
    'inventory_table' => (bool)$db->fetchOne(
        "SELECT COUNT(*) FROM information_schema.TABLES "
        . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory'"
    ),
]);
'''.replace("ROOT_PATH", "'" + str(root).replace("'", "\\'") + "'")


def verify(condition, message):
    if not condition:
        raise RuntimeError(message)


session = subprocess.run(
    [php, "-r", session_script.removeprefix("<?php\n")],
    cwd=root,
    text=True,
    capture_output=True,
    timeout=60,
)
if session.returncode == 77:
    raise SystemExit("SKIP: " + session.stderr.strip())
verify(session.returncode == 0, session.stderr or "Unable to create the integration session")
session_data = json.loads(session.stdout)
session_id = session_data.get("session_id", "")
verify(session_id, "The integration session has no ID")

with socket.socket() as listener:
    listener.bind(("127.0.0.1", 0))
    port = listener.getsockname()[1]

server = subprocess.Popen(
    [php, "-d", "opcache.enable_cli=0", "-S", f"127.0.0.1:{port}", "index.php"],
    cwd=root,
    stdout=subprocess.PIPE,
    stderr=subprocess.PIPE,
    text=True,
    start_new_session=True,
)


def request(path, data=None, ajax=False):
    headers = {"Cookie": "PHPSESSID=" + session_id}
    body = None
    method = "GET"
    if data is not None:
        method = "POST"
        body = urlencode(data)
        headers["Content-Type"] = "application/x-www-form-urlencoded"
    if ajax:
        headers["X-Requested-With"] = "XMLHttpRequest"
    connection = http.client.HTTPConnection("127.0.0.1", port, timeout=60)
    connection.request(method, path, body=body, headers=headers)
    response = connection.getresponse()
    content = response.read().decode("utf-8", "replace")
    status = response.status
    connection.close()
    error = re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", content)).strip()
    verify(status == 200, f"{path} returned HTTP {status}: {error[:2000]}")
    return content


def verify_footer(content, route, monetary):
    verify(content.count('class="dw-list-footer"') == 1, f"{route} does not contain exactly one list footer")
    verify('class="dw-list-footer__metric"' in content, f"{route} has no record-count metric")
    amount_count = content.count('class="dw-list-footer__amount"')
    verify((amount_count > 0) == monetary, f"{route} has an unexpected monetary summary state")
    for key in ["LIST_RECORDS", "LIST_TOTAL_VALUE", "LIST_FILTERED_VALUE", "LIST_PAGE_VALUE"]:
        verify(f">{key}<" not in content, f"{route} exposes the raw translation key {key}")


checks = 0
try:
    for _ in range(100):
        if server.poll() is not None:
            _, error = server.communicate()
            raise RuntimeError(error or "The local PHP server stopped during startup")
        try:
            with socket.create_connection(("127.0.0.1", port), timeout=0.1):
                break
        except OSError:
            time.sleep(0.05)
    else:
        raise RuntimeError("The local PHP server did not start")

    monetary_routes = {
        "/items/stock",
        "/processes/process",
        "/purchases/quoterequest",
        "/purchases/purchaseorder",
        "/sales/quote",
        "/sales/salesorder",
        "/sales/deliveryorder",
        "/sales/invoice",
        "/sales/creditnote",
        "/sales/reminder",
    }
    regular_routes = [
        "/admin/category",
        "/admin/client",
        "/admin/country",
        "/admin/currency",
        "/admin/deliverytime",
        "/admin/export",
        "/admin/footer",
        "/admin/manufacturer",
        "/admin/menu",
        "/admin/module",
        "/admin/page",
        "/admin/paymentmethod",
        "/admin/shippingmethod",
        "/admin/shop",
        "/admin/slide",
        "/admin/tag",
        "/admin/taxrate",
        "/admin/template",
        "/admin/uom",
        "/admin/user",
        "/admin/warehouse",
        "/campaigns/campaign",
        "/contacts/contact",
        "/items/item",
        "/items/itemlist",
        "/items/ledger",
        "/items/pricerule",
        "/items/stock",
        "/processes/process",
        "/purchases/quoterequest",
        "/purchases/purchaseorder",
        "/sales/quote",
        "/sales/salesorder",
        "/sales/deliveryorder",
        "/sales/invoice",
        "/sales/creditnote",
        "/sales/reminder",
        "/tasks/task",
    ]
    if session_data.get("inventory_table"):
        regular_routes.append("/items/inventory")
    else:
        print("SKIP: /items/inventory (the configured database has no inventory table)")

    for route in regular_routes:
        verify_footer(request(route), route, route in monetary_routes)
        checks += 1

    for route in [
        "/items/stock",
        "/processes/process",
        "/purchases/quoterequest",
        "/purchases/purchaseorder",
    ]:
        response = json.loads(request(
            route + "/search",
            {
                "keyword": "dewawi-footer-no-match",
                "page": 1,
                "limit": 1,
                "context_action": "index",
            },
            True,
        ))
        verify(isinstance(response.get("toolbar"), str), f"{route} AJAX response has no toolbar")
        ajax_content = response.get("content", "")
        verify_footer(ajax_content, route + "/search", True)
        verify("0,00" in ajax_content, f"{route} no-match search has no zero-value summary")
        checks += 1

    verify_footer(
        request("/items/stock?stockdate=2026-02-31"),
        "/items/stock invalid stock date",
        True,
    )
    checks += 1

    historical = request("/items/stock?stockdate=2026-10-01")
    verify('class="dw-list-footer__amount"' not in historical, "Historical stock exposes a current stock value")
    checks += 1
finally:
    if server.poll() is None:
        os.killpg(server.pid, signal.SIGTERM)
    try:
        server.communicate(timeout=10)
    except subprocess.TimeoutExpired:
        os.killpg(server.pid, signal.SIGKILL)
        server.communicate()
    session_file = root / "session" / ("sess_" + session_id)
    session_file.unlink(missing_ok=True)

print(f"PASS: {checks} real HTTP list footer, AJAX, empty-result and stock-date cases")
