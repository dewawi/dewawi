#!/usr/bin/env python3
"""Exercise the real public checkout against a temporary database, sessions and mail fixture."""
from html.parser import HTMLParser
import http.cookiejar
import json
import os
from pathlib import Path
import re
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
work = Path(sys.argv[1]).resolve()
if not str(work).startswith('/tmp/dewawi-shop-checkout-db-') or not (work / 'mysql.sock').exists():
    raise SystemExit('Isolated fixture directory and socket required')
fixture = ROOT / 'tests/security/fixtures/shop-checkout-flow.php'
checks = 0

def check(value, message):
    global checks
    checks += 1
    if not value:
        raise AssertionError(message)

def cli(action='state', *values):
    result = subprocess.check_output(['php', str(fixture), str(work / 'mysql.sock'), action, *map(str, values)], text=True)
    return json.loads(result) if action == 'state' else None

class Inputs(HTMLParser):
    def __init__(self, content):
        super().__init__()
        self.values = {}
        self.feed(content)
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'input' and attrs.get('name'):
            self.values[attrs['name']] = attrs.get('value', '')

class Client:
    def __init__(self, origin):
        self.origin = origin
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(self, path, values=None, xhr=False, headers=None, timeout=10):
        headers = dict(headers or {})
        if xhr:
            headers['X-Requested-With'] = 'XMLHttpRequest'
        request = urllib.request.Request(self.origin + path, data=urllib.parse.urlencode(values).encode() if values is not None else None, headers=headers)
        try:
            with self.opener.open(request, timeout=timeout) as response:
                return response.status, response.read().decode(), response.geturl()
        except urllib.error.HTTPError as error:
            return error.code, error.read().decode(), error.geturl()
    def checkout(self, item=103):
        status, content, _ = self.request('/a/cart')
        check(status == 200, 'Cart GET failed: ' + content[-1500:])
        config = json.loads(re.search(r'data-config="([^"]+)"', content)[1].replace('&quot;', '"').replace('&amp;', '&'))
        status, body, _ = self.request('/a/cart/add', {'id': item, 'quantity': '2.5', 'csrf_token': config['token'], 'revision': config['revision']}, True)
        check(status == 200 and json.loads(body)['success'], 'Cart setup failed: ' + body)
        status, content, _ = self.request('/a/checkout')
        check(status == 200 and 'name="cart_revision"' in content, 'Checkout form missing: ' + content[-2000:])
        values = Inputs(content).values
        values.update({'billingname': 'Alice <script>alert(1)</script>', 'billingcompany': 'Example GmbH', 'billingdepartment': '', 'billingstreet': 'Testweg 1', 'billingpostcode': '10115', 'billingcity': 'Berlin', 'billingcountry': 'DE', 'billingphone': '+49 30 111', 'email': 'alice@example.com', 'message': 'Bitte vorher anrufen', 'differentshippingaddress': '0', 'privacy': '1', 'checkout_stage': 'preview'})
        return values, content
    def preview(self, values):
        status, body, _ = self.request('/a/checkout/send', values, True)
        check(status == 200 and json.loads(body).get('success'), 'Preview failed: ' + body)
        response = json.loads(body)
        html = response['reviewHtml']
        values = dict(values, csrf_token=response['csrfToken'])
        values = dict(values, checkout_stage='confirm', review_token=Inputs(html).values['review_token'])
        return values, html

cli('init')
with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0))
    port = sock.getsockname()[1]
origin = f'http://127.0.0.1:{port}'
env = dict(os.environ, DEWAWI_CHECKOUT_TEST_SOCKET=str(work / 'mysql.sock'))
log_path = work / 'checkout-flow-http.log'
with log_path.open('w') as log:
    server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(work), str(fixture)], env=env, stdout=log, stderr=log)
    try:
        client = Client(origin)
        for _ in range(100):
            try:
                status, content, _ = client.request('/a/checkout/success')
                break
            except urllib.error.URLError:
                time.sleep(.05)
        check(status == 404 and 'Wir haben Ihre Bestellung erhalten' not in content, 'Direct success URL claims a saved order')
        values, page = client.checkout()
        check('Versandkosten werden in diesem Checkout nicht berechnet' in page and '297,50' in page and '47,50' in page, 'Initial ERP gross/tax or shipping notice missing')
        check('<img src=x' not in page and '&lt;img src=x' in page, 'Initial article title is not escaped')
        check('for="checkout-billingstreet"' in page and 'autocomplete="billing street-address"' in page, 'Visible field labels or autocomplete missing')
        check(cli()['orders'] == [], 'GET checkout created an order')
        status, body, _ = client.request('/a/checkout/send', dict(values, csrf_token='invalid'), True)
        check(status == 403 and 'csrf_token' in json.loads(body)['errors'], 'Invalid CSRF accepted')
        values['csrf_token'] = Inputs(client.request('/a/checkout')[1]).values['csrf_token']
        status, body, _ = client.request('/a/checkout/send', dict(values, billingcity='', email='invalid', billingcountry='XX'), True)
        errors = json.loads(body)['errors']
        check(status == 422 and all(key in errors for key in ['billingcity', 'email', 'billingcountry']), 'Field errors not returned: ' + body)
        values['csrf_token'] = json.loads(body)['csrfToken']
        status, html, _ = client.request('/a/checkout/send', dict(values, billingcity=''))
        check(status == 422 and 'aria-invalid="true"' in html and 'Testweg 1' in html and 'dw-field__error' in html, 'Plain POST did not retain form and inline errors')
        # A rendered form rotates the CSRF hash, just as a browser opening the page does.
        values['csrf_token'] = Inputs(html).values['csrf_token']
        status, body, _ = client.request('/a/checkout/send', dict(values, differentshippingaddress='1'), True)
        check(status == 422 and 'shippingname' in json.loads(body)['errors'], 'Different address fields are not required')
        values['csrf_token'] = json.loads(body)['csrfToken']
        values['shippingcountry'] = 'XX'
        status, body, _ = client.request('/a/checkout/send', dict(values, checkout_stage='confirm'), True)
        check(status == 409 and json.loads(body)['code'] == 'SHOPS_CHECKOUT_REVIEW_CHANGED' and not cli()['orders'], 'Final submission bypassed the mandatory review')
        values['csrf_token'] = json.loads(body)['csrfToken']
        values, review = client.preview(values)
        check(review.count('Testweg 1') == 2 and '&lt;script&gt;' in review and '<script>' not in review, 'Same-address normalization or escaping failed')
        check('297,50' in review and '47,50' in review and 'keine Zahlung' in review and 'Zahlungspflichtig bestellen' in review, 'Review amounts, payment or existing order semantics missing')
        check(cli()['orders'] == [] and cli()['mails'] == 0, 'Preview persisted or emailed an order')
        status, body, _ = client.request('/a/checkout/send', dict(values, billingstreet='Changed after preview'), True)
        check(status == 409 and json.loads(body)['code'] == 'SHOPS_CHECKOUT_REVIEW_CHANGED', 'Unreviewed address change accepted')
        values['csrf_token'] = json.loads(body)['csrfToken']
        cli('tax-change', 20)
        status, body, _ = client.request('/a/checkout/send', values, True)
        check(status == 409 and json.loads(body)['code'] == 'SHOPS_CHECKOUT_REVIEW_CHANGED', 'Unreviewed tax change accepted')
        values['csrf_token'] = json.loads(body)['csrfToken']
        cli('tax-change', 19)
        cli('price-change', 110)
        status, body, _ = client.request('/a/checkout/send', values, True)
        check(status == 409 and json.loads(body)['code'] == 'SHOPS_CART_CHANGED', 'Price revalidation bypassed')
        values['csrf_token'] = json.loads(body)['csrfToken']
        cli('price-change', 100)
        # Plain POST preview and final submission retain the existing redirect route.
        status, html, _ = client.request('/a/checkout/send', dict(values, checkout_stage='preview'))
        check(status == 200 and 'name="review_token"' in html and 'name="checkout_stage" value="confirm"' in html, 'Plain POST review missing')
        values.update({key: val for key, val in Inputs(html).values.items() if key in ['csrf_token', 'review_token', 'checkout_stage', 'checkout_token', 'cart_revision']})
        before = cli()
        status, receipt, location = client.request('/a/checkout/send', values, headers={'X-Fixture-Mail-Fail': 'exception'})
        after = cli()
        check(status == 200 and location.endswith('/a/checkout/success') and len(after['orders']) == len(before['orders']) + 1, 'Final submission did not store then redirect: ' + str(status) + ' ' + receipt[-2000:])
        number = after['orders'][-1]['orderid']
        check(str(number) in receipt and 'Ihre Bestellung wurde gespeichert' in receipt and 'keine Zahlung' in receipt, 'Stored order number, notification failure or no-payment message missing')
        check(after['mails'] == before['mails'] + 1 and after['positions'] == 1 and after['history'] == 1, 'Mail, positions or initial history duplicated')
        check(float(after['orders'][-1]['total']) == 250 and float(after['orders'][-1]['taxes']) == 47.5 and float(after['orders'][-1]['grosstotal']) == 297.5, 'Stored totals differ from review')
        status, legacy_receipt, _ = client.request('/a/checkout/success', headers={'X-Fixture-Legacy-Receipt': '1'})
        check(status == 200 and str(number) in legacy_receipt and 'Ihre Bestellung wurde gespeichert' in legacy_receipt, 'Existing C1 session receipt was lost')
        status, repeated, _ = client.request('/a/checkout/send', values)
        check(status == 200 and cli() == after and str(number) in repeated, 'Repeated POST duplicated order or notification')
        check(client.request('/a/checkout/success')[0] == 200 and cli() == after, 'Confirmation reload changed storage')
        stranger = Client(origin)
        status, body, _ = stranger.request('/a/checkout/send', values, True)
        check(status == 403 and not json.loads(body)['success'] and cli() == after, 'Another session reused checkout capabilities')
        status, foreign, _ = stranger.request('/a/checkout/success?orderid=' + str(number) + '&checkout_token=' + values['checkout_token'])
        check(status == 404 and 'Testweg 1' not in foreign, 'URL parameters exposed another session order')
        for prefix in ['/b', '/c']:
            status, foreign, _ = client.request(prefix + '/checkout/success')
            check(status == 404 and 'Testweg 1' not in foreign, 'Shop or tenant switch exposed receipt')
        status, body, _ = client.request('/a/checkout/send', dict(values, status_only='1'), True)
        check(status == 403 and not json.loads(body)['success'], 'Old checkout token survived a context switch')
        # Known missing tax remains unknown, while explicit zero tax is displayed as zero.
        for item, known in [(105, False), (106, True)]:
            tax_client = Client(origin)
            tax_values, tax_page = tax_client.checkout(item)
            tax_values, tax_review = tax_client.preview(tax_values)
            check(('Nicht vollständig bekannt' in tax_review) != known, 'Unknown tax was invented or explicit zero lost')
        # A transaction failure leaves no positions, history or receipt for that attempt.
        failed = Client(origin)
        failed_values, _ = failed.checkout(104)
        failed_values, _ = failed.preview(failed_values)
        before = cli()
        status, body, _ = failed.request('/a/checkout/send', failed_values, True)
        check(status == 503 and json.loads(body)['code'] == 'SHOPS_CHECKOUT_SAVE_UNCERTAIN' and cli() == before, 'Failed transaction claims success or leaves partial data')
        status, body, _ = failed.request('/a/checkout/send', dict(failed_values, status_only='1'), True)
        check(status == 200 and json.loads(body)['stored'] is False, 'Recovery invented a failed order')
        check(failed.request('/a/checkout/success')[0] == 404, 'Failed order has a success receipt')
        # Losing the response after commit must recover without creating or emailing again.
        interrupted = Client(origin)
        interrupted_values, _ = interrupted.checkout()
        interrupted_values, _ = interrupted.preview(interrupted_values)
        before = cli()
        try:
            interrupted.request('/a/checkout/send', interrupted_values, True, {'X-Fixture-Mail-Delay': '1'}, timeout=.1)
            raise AssertionError('Timeout fixture did not interrupt response')
        except (TimeoutError, socket.timeout):
            pass
        status, body, _ = interrupted.request('/a/checkout/send', dict(interrupted_values, status_only='1'), True)
        recovery = json.loads(body)
        after = cli()
        check(status == 200 and recovery['stored'] and recovery['redirect'] == '/a/checkout/success', 'Lost response did not recover committed order')
        check(len(after['orders']) == len(before['orders']) + 1 and after['mails'] == before['mails'] + 1, 'Recovery duplicated order or email')
        status, receipt, _ = interrupted.request(recovery['redirect'])
        check(status == 200 and str(after['orders'][-1]['orderid']) in receipt, 'Recovery receipt shows wrong order number')
        # A replay of the completed checkout must preserve a newly started cart.
        new_values, _ = interrupted.checkout()
        cart_before = interrupted.request('/a/cart/count', xhr=True)[1]
        interrupted.request('/a/checkout/send', dict(interrupted_values, status_only='1'), True)
        check(interrupted.request('/a/cart/count', xhr=True)[1] == cart_before, 'Receipt recovery cleared a newer cart')
        status, body, _ = interrupted.request('/a/checkout/send', dict(interrupted_values, checkout_token='a' * 32, status_only='1'), True)
        check(status == 403 and not json.loads(body)['success'], 'Recovery accepted unbound token')
        malformed = Client(origin)
        status, html, _ = malformed.request('/a/checkout/send', {'billingname[invalid]': 'nested'})
        check(status == 413 and 'Fatal error' not in html and 'Bestellformular' in html, 'Malformed plain POST did not recover safely')
        print(f'PASS: {checks} public checkout review, totals, field errors, mail failure, replay, timeout recovery and receipt isolation checks')
        # Browser tests reuse this isolated HTTP server and database.
        browser = subprocess.run(['python3', str(ROOT / 'tests/site/checkout-http.py'), origin], text=True, capture_output=True)
        print(browser.stdout)
        if browser.returncode:
            raise AssertionError(browser.stderr)
    except Exception:
        print(log_path.read_text()[-10000:], file=sys.stderr)
        raise
    finally:
        server.terminate()
        server.wait(timeout=10)
