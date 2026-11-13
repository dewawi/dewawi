from pathlib import Path
import http.cookiejar
from html.parser import HTMLParser
import json
import os
import shutil
import socket as socket_module
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request


def check(condition, message):
    if not condition:
        raise AssertionError(message)


def check_http(work, socket):
    root = Path(__file__).resolve().parents[2]
    router = root / 'tests/security/fixtures/inquiry-pilot-http.php'
    env = dict(os.environ, DEWAWI_INQUIRY_TEST_SOCKET=socket)
    (work / 'sessions').mkdir()

    def state():
        return json.loads(subprocess.run(['php', str(router)], env=env, check=True, capture_output=True, text=True).stdout)

    before = state()
    with socket_module.socket() as port_socket:
        port_socket.bind(('127.0.0.1', 0))
        port = port_socket.getsockname()[1]
    address = 'http://127.0.0.1:' + str(port)
    client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(path='/', values=None):
        data = urllib.parse.urlencode(values).encode() if values is not None else None
        req = urllib.request.Request(address + path, data=data, headers={'X-Requested-With': 'XMLHttpRequest'} if data else {})
        try:
            with client.open(req, timeout=10) as response:
                return response.status, response.read().decode()
        except urllib.error.HTTPError as error:
            return error.code, error.read().decode()

    class Inputs(HTMLParser):
        def __init__(self, html):
            super().__init__()
            self.values = {}
            self.feed(html)

        def handle_starttag(self, tag, attributes):
            attributes = dict(attributes)
            if tag == 'input' and 'name' in attributes:
                self.values[attributes['name']] = attributes.get('value', '')

    log_path = work / 'http.log'
    with log_path.open('w') as log:
        http_server = subprocess.Popen(['php', '-S', '127.0.0.1:' + str(port), '-t', str(work), str(router)], env=env, stdout=log, stderr=log)
        try:
            for _ in range(100):
                try:
                    status, html = request()
                    break
                except urllib.error.URLError:
                    time.sleep(0.05)
            else:
                raise AssertionError('Isolated HTTP server did not start')
            check(status == 200 and 'inquiry-form' in html, 'Initial HTTP form failed: ' + html)
            for form_id in [866, 867]:
                check(request('/?id=' + str(form_id))[0] == 404, 'Foreign shop or tenant form accessible')
            status, html = request()
            inputs = Inputs(html).values
            tokens = {name: inputs[name] for name in ['csrf_token', 'inquiry_token']}

            def post(values):
                return request('/', dict(tokens, **values))

            check(post({'step': '3'})[0] == 400, 'HTTP allowed skipping prior steps')
            check(request('/', dict(tokens, csrf_token='invalid', step='1'))[0] == 400, 'HTTP accepted invalid CSRF')
            requirements = {'step': '1', 'einsatzbereich': '0', 'aufstellung': 'unbekannt', 'medium': '1', 'glykol_typ': '2', 'glykol_typ_sonstiges': 'Test fluid', 'glykol_anteil': '30,5', 'glykol_bezugsart': 'volumen', 'glykol_bezugstemperatur': '0', 'kuehlleistung': '12,5', 'vorlauf_temp': '0', 'umgebung_temp_max': '35', 'clientid': '200', 'shopid': '121', '_selection': 'forged approval'}
            status, body = post(requirements)
            check(status == 200 and json.loads(body)['status'] == 'ok' and 'data-current-step="2"' in body.replace('\\"', '"'), 'Requirements HTTP step failed: ' + body)
            equipment = {'step': '2', 'pumpe': 'unbekannt', 'tank': 'unbekannt', 'elektrische_versorgung': 'bekannt', 'netzspannung': '400', 'netzfrequenz': '50', 'freitext_optionen': 'Retained equipment wish'}
            status, body = post(dict(equipment, back='1'))
            check(status == 200 and json.loads(body)['status'] == 'ok', 'HTTP back navigation failed')
            status, body = post(requirements)
            restored = json.loads(body)['formHtml']
            check(Inputs(restored).values.get('netzspannung') == '400' and 'Retained equipment wish' in restored, 'Unchanged prior step lost advanced equipment values')
            post(dict(equipment, back='1'))
            status, body = post(dict(requirements, medium='0'))
            changed = json.loads(body)['formHtml']
            check(Inputs(changed).values.get('netzspannung', '') == '' and 'Retained equipment wish' not in changed, 'Changed earlier requirements retained a stale equipment draft')
            post(dict(equipment, back='1'))
            post(requirements)
            status, body = post(equipment)
            contact_html = json.loads(body)['formHtml']
            check(status == 200 and 'value="DE"' in contact_html and 'value="CH"' in contact_html and 'value="XX"' not in contact_html, 'Country source was not tenant-scoped')
            contact = {'step': '3', 'firma': 'HTTP Pilot GmbH', 'ansprechpartner': 'Test Person', 'adresse': 'Billing Street', 'plz': '12345', 'stadt': 'Billing City', 'country': 'DE', 'email': 'http-pilot@example.test', 'privacy': '1', 'lieferadresse_abweichend': 'ja', 'lieferempfaenger': 'Delivery GmbH', 'lieferstrasse': 'Delivery Street', 'lieferplz': '98765', 'lieferstadt': 'Delivery City', 'lieferland': 'CH'}
            status, body = post(dict(contact, country='XX'))
            check(status == 200 and json.loads(body)['status'] == 'error', 'HTTP accepted a foreign tenant country')
            status, body = post(dict(contact, lieferstrasse=''))
            check(status == 200 and json.loads(body)['status'] == 'error', 'HTTP accepted incomplete delivery address')
            status, body = post(contact)
            confirmation = json.loads(body)['formHtml']
            check(status == 200 and 'gespeichert' in confirmation and 'geprüft' in confirmation and 'Ihr Angebot ist fertig' not in confirmation, 'Manual confirmation failed: ' + body)
            after = state()
            check(after['quotes'] == before['quotes'] and len(after['inquiries']) == 1, 'Manual HTTP request created a quote or duplicate')
            check(after['scopes'] == [{'clientid': 100, 'shopid': 120}], 'Forged HTTP tenant or shop scope accepted')
            stored = after['inquiries'][0]
            check(stored['country'] == 'DE' and stored['lieferland'] == 'CH' and stored['lieferstrasse'] == 'Delivery Street', 'Stored HTTP delivery data mixed')
            check(float(stored['netzspannung']) == 400 and float(stored['glykol_bezugstemperatur']) == 0 and float(stored['glykol_anteil']) == 30.5 and stored['_selection']['eligible'] is False, 'Advanced or numeric HTTP values lost')
            post(contact)
            check(len(state()['inquiries']) == 1, 'HTTP replay duplicated the inquiry')
            print('PASS: isolated HTTP form, AJAX/back navigation, scoped countries, structured delivery, numeric values, manual persistence and replay')
            for form_id, selected_id, expected_sku, name in [(900, '1001', 'DUPLICATE', 'equipment'), (901, '1100', 'BOLT-12', 'trading')]:
                path = '/?id=' + str(form_id)
                status, html = request(path)
                inputs = Inputs(html).values
                catalog_tokens = {key: inputs[key] for key in ['csrf_token', 'inquiry_token']}

                def catalog_post(values):
                    return request(path, dict(catalog_tokens, **values))

                status, body = catalog_post({'step': 'requirements', 'demand': '12,5', 'material': '0', 'clientid': '200'})
                suggestions = json.loads(body)['formHtml']
                check(status == 200 and 'inquiry-catalog-item' in suggestions and 'Berechneter Wert' not in suggestions, 'Generic HTTP catalog suggestions failed')
                (work / ('catalog-' + name + '.html')).write_text(suggestions)
                contact = {'step': 'contact', 'itemid': '1010', 'email': 'catalog-http@example.test', 'privacy': '1', 'sku': 'FORGED', 'titel': 'Forged customer title'}
                check(catalog_post(contact)[0] == 400, 'Catalog accepted an inaccessible item ID')
                contact['itemid'] = selected_id
                catalog_post(dict(contact, back='1'))
                status, body = catalog_post({'step': 'requirements', 'demand': '12,5', 'material': '0'})
                check(Inputs(json.loads(body)['formHtml']).values.get('itemid') == selected_id, 'Catalog back navigation lost selected identity')
                status, body = catalog_post(contact)
                check(status == 200 and 'gespeichert' in json.loads(body)['formHtml'], 'Catalog inquiry was not saved')
                saved = state()['catalog_inquiries'][-1]
                check(saved['sku'] == expected_sku and saved['_selection']['selected']['itemid'] == int(selected_id) and saved['_selection']['eligible'] is False, 'Catalog trusted SKU/title or enabled quotes')
                check(state()['quotes'] == before['quotes'], 'Catalog created an automatic quote')
                previous_count = len(state()['catalog_inquiries'])
                catalog_post(contact)
                check(len(state()['catalog_inquiries']) == previous_count, 'Repeated catalog submission duplicated inquiry')
            path = '/?id=900'
            status, html = request(path)
            inputs = Inputs(html).values
            catalog_tokens = {key: inputs[key] for key in ['csrf_token', 'inquiry_token']}
            status, body = catalog_post({'step': 'requirements', 'demand': '1000', 'material': '0'})
            check('class="inquiry-catalog-item"' not in json.loads(body)['formHtml'], 'Nonmatching catalog returned candidates')
            status, body = catalog_post({'step': 'contact', 'itemid': '', 'email': 'catalog-http@example.test', 'privacy': '1'})
            saved = state()['catalog_inquiries'][-1]
            check(status == 200 and saved['_selection']['selected'] is None and saved['_selection']['eligible'] is False and state()['quotes'] == before['quotes'], 'No-match manual inquiry was lost or auto-offered')
            print('PASS: two catalog HTTP product groups, canonical IDs/SKUs, tamper rejection, back navigation, manual storage and replay')
            ui = subprocess.run(['python3', str(root / 'tests/ui/inquiry-catalog.py'), str(work)], capture_output=True, text=True)
            print(ui.stdout)
            check(ui.returncode == 0, 'Catalog Chromium regression failed: ' + ui.stderr)
        except Exception:
            print(log_path.read_text())
            raise
        finally:
            http_server.terminate()
            http_server.wait(timeout=10)

work = Path(tempfile.mkdtemp(prefix='dewawi-shop-inquiry-quote-'))
server = None
try:
    init = subprocess.run(
        ['mariadb-install-db', '--no-defaults', '--datadir=' + str(work / 'data'), '--auth-root-authentication-method=normal', '--skip-test-db'],
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
    )
    if init.returncode:
        print(init.stdout)
        raise SystemExit(init.returncode)

    socket = str(work / 'mysql.sock')
    server = subprocess.Popen(
        [
            'mariadbd', '--no-defaults', '--user=root', '--datadir=' + str(work / 'data'), '--socket=' + socket,
            '--skip-networking', '--pid-file=' + str(work / 'server.pid'), '--log-error=' + str(work / 'server.log'),
            '--innodb-buffer-pool-size=32M', '--innodb-log-file-size=8M',
        ],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )
    for _ in range(100):
        if server.poll() is not None:
            print((work / 'server.log').read_text())
            raise SystemExit('Isolated MariaDB failed to start')
        if Path(socket).exists():
            break
        time.sleep(0.1)

    result = subprocess.run(
        ['php', str(Path(__file__).with_suffix('.php')), socket],
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
    )
    print(result.stdout)
    if result.returncode:
        raise SystemExit(result.returncode)
    check_http(work, socket)
finally:
    if server is not None:
        server.terminate()
        server.wait(timeout=20)
    shutil.rmtree(work)
