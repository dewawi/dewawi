#!/usr/bin/env python3
"""Use only the database created by shop-checkout-db.py for HTTP and browser tests."""
import html
from html.parser import HTMLParser
import http.cookiejar
import json
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
work = Path(sys.argv[1]).resolve()
if not str(work).startswith('/tmp/dewawi-shop-checkout-db-'):
    raise SystemExit('Isolated fixture directory required')
fixtures = json.loads((work / 'order-fixtures.json').read_text())
checks = 0

def check(condition, message):
    global checks
    checks += 1
    if not condition:
        raise AssertionError(message)

class FormInputs(HTMLParser):
    def __init__(self, text):
        super().__init__()
        self.tokens = []
        self.feed(text)
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'input' and attrs.get('name') == 'csrf':
            self.tokens.append(attrs.get('value', ''))

with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0))
    port = sock.getsockname()[1]
origin = f'http://127.0.0.1:{port}'
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
env = dict(os.environ, DEWAWI_ORDER_TEST_SOCKET=str(work / 'mysql.sock'))

def request(path, data=None):
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    try:
        with client.open(urllib.request.Request(origin + path, data=body), timeout=15) as response:
            return response.status, response.read().decode(), response.headers
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode(), e.headers

log = (work / 'order-http.log').open('w')
server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(work), str(ROOT / 'tests/security/fixtures/shop-orders-http.php')], env=env, stdout=log, stderr=log)
try:
    for _ in range(100):
        try:
            status, text, headers = request('/view?contactquery=Changed')
            break
        except urllib.error.URLError:
            time.sleep(.05)
    else:
        raise AssertionError('HTTP fixture did not start')
    check(status == 200, 'Detail failed: ' + (work / 'order-http.log').read_text())
    check('Original buyer GmbH' in text and 'Original item title' in text and 'ORIGINAL-SKU' in text, 'Detail reconstructed current master data')
    check('SHOPS_ORDER_' not in text, 'Detail contains untranslated labels')
    check('no-store' in headers.get('Cache-Control', ''), 'Customer order response can be publicly cached')
    csrf = FormInputs(text).tokens[0]
    state_url = f"/shops/order/state/id/{fixtures['new']}"
    convert_url = f"/shops/order/createsalesorder/id/{fixtures['new']}"
    check(request(state_url)[0] == 405, 'GET changes order status')
    check(request(state_url, {'csrf': 'wrong', 'status': 'in_progress'})[0] == 403, 'Missing CSRF accepted')
    check(request(state_url, {'csrf': csrf, 'status': 'in_progress', 'total': '1'})[0] == 400, 'Snapshot mutation field accepted')
    check(request(state_url, {'csrf': csrf, 'status': 'in_progress'})[0] == 200, 'Valid status transition failed')
    check(request('/view?role=reader')[0] == 200, 'Reader cannot inspect order')
    check(request(state_url + '?role=reader', {'csrf': csrf, 'status': 'completed'})[0] == 403, 'Reader changes order')
    for role in ('guest', 'denied'):
        check(request('/view?role=' + role)[0] == 403, role + ' can inspect order')
        check(request('/index?role=' + role)[0] == 403, role + ' can list stored orders')
        check(request('/search?role=' + role)[0] == 403, role + ' can search stored orders')
        check(request(convert_url + '?role=' + role, {'csrf': csrf, 'contactid': fixtures['contact']})[0] == 403, role + ' can convert stored orders')
    check(request(convert_url + '?role=reader', {'csrf': csrf, 'contactid': fixtures['contact']})[0] == 403, 'Reader converts stored orders')
    check(request('/view?tenant=8')[0] == 404, 'Foreign tenant accesses order')
    check(request('/view?id=999999')[0] == 404, 'Missing order is not 404')
    check(request('/add')[0] == 404 and request('/delete')[0] == 404, 'Legacy write actions remain available')
    status, legacy, _ = request('/view?id=' + str(fixtures['legacy']))
    check(status == 200 and 'Historische Bestelldaten fehlen' in legacy and 'name="contactid"' not in legacy, 'Incomplete historical order exposes conversion')
    status, listing, _ = request('/index')
    check(status == 200 and 'Original buyer GmbH' in listing and 'SHOPS_ORDER_' not in listing, 'Internal order list failed: ' + (work / 'order-http.log').read_text())
    check(request('/index?status=cancelled')[0] == 200, 'Filtered list failed')
    # Render the actual detail view in Chromium at desktop and mobile widths.
    chrome = shutil.which('chromium') or shutil.which('google-chrome')
    if not chrome:
        raise AssertionError('Chromium unavailable')
    for width in (1280, 390):
        script = """const frame=document.querySelector('iframe');frame.onload=()=>setTimeout(()=>{const d=frame.contentDocument,w=frame.contentWindow;const failures=[];let checks=0;function check(v,m){checks++;if(!v)failures.push(m)}check(d.body.textContent.includes('Original buyer GmbH'),'Missing customer');check(!!d.querySelector('select[name="contactid"]'),'Missing customer selection');check(d.documentElement.scrollWidth<=w.innerWidth,'Horizontal page overflow');check(d.querySelectorAll('input[name="csrf"]').length===2,'Missing action tokens');check(d.querySelector('form[action*="createsalesorder"]').method==='post','Conversion is not explicit POST');check(!d.querySelector('[data-name="price"]'),'Historical price is editable');document.querySelector('#metrics').textContent=JSON.stringify({checks,failures});},300);"""
        page = f'<!doctype html><html><body><iframe src="{origin}/view?contactquery=Changed" style="width:{width}px;height:1400px;border:0"></iframe><pre id="metrics"></pre><script>{script}</script></body></html>'
        # The parent must share the fixture origin to inspect its iframe.
        (work / 'order-browser.html').write_text(page)
        with tempfile.TemporaryDirectory(prefix='dewawi-order-chromium-') as profile:
            result = subprocess.run([chrome, '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', f'--user-data-dir={profile}', f'--window-size={max(width,800)},1500', '--virtual-time-budget=3000', '--dump-dom', origin + '/order-browser.html'], capture_output=True, text=True, timeout=35)
        found = re.findall(r'<pre id="metrics">(.*?)</pre>', result.stdout, re.S)
        check(result.returncode == 0 and bool(found) and bool(found[-1]), 'Chromium did not return order metrics')
        metrics = json.loads(html.unescape(found[-1]))
        check(not metrics['failures'], f"{width}px: " + '; '.join(metrics['failures']))
        checks += metrics['checks']
    status, text, _ = request('/view?contactquery=Changed')
    csrf = FormInputs(text).tokens[0]
    check(request(convert_url, {'csrf': csrf, 'contactid': fixtures['foreign_contact']})[0] == 200, 'Foreign customer rejection did not return review page')
    status, linked, _ = request(convert_url, {'csrf': csrf, 'contactid': fixtures['contact']})
    check(status == 200 and '/sales/salesorder/' in linked and 'name="contactid"' not in linked, 'Conversion did not create a linked draft')
    check(request(convert_url, {'csrf': csrf, 'contactid': fixtures['contact']})[0] == 200, 'Conversion replay failed')
    print(f'PASS: {checks} order HTTP, CSRF, permission, legacy and responsive browser checks')
finally:
    server.terminate()
    server.wait(timeout=10)
    log.close()
    if sys.exc_info()[0]:
        print((work / 'order-http.log').read_text()[-9000:])
