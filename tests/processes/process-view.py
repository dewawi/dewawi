#!/usr/bin/env python3
"""Exercise the original Process MVC read view with synthetic data and forbidden writes."""

import base64
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('process_browser', ROOT / 'tests/site/accessibility-http.py')
browser = importlib.util.module_from_spec(spec)
spec.loader.exec_module(browser)
checks = 0


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def check(condition, message):
    global checks
    checks += 1
    if not condition:
        raise AssertionError(message)


with tempfile.TemporaryDirectory(prefix='dewawi-process-view-') as temporary:
    port = browser.free_port()
    base = f'http://127.0.0.1:{port}'
    log_path = Path(temporary) / 'php.log'
    log = log_path.open('w')
    server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', 'tests/ui/button-page-integration.php'], cwd=ROOT,
                              env={**os.environ, 'DEWAWI_ICON_TEST_SESSIONS': temporary}, stdout=log, stderr=log)
    driver = None

    def request(path='/processes/process/view/id/17', scenario='full', locale='de_DE', extra=None, cookie=None, ajax=False):
        params = {'fixture_process_view': scenario, 'fixture_locale': locale, **(extra or {})}
        headers = {'X-Requested-With': 'XMLHttpRequest'} if ajax else {}
        if cookie:
            headers['Cookie'] = 'tab=' + urllib.parse.quote(cookie)
        req = urllib.request.Request(base + path + '?' + urllib.parse.urlencode(params), headers=headers)
        try:
            response = urllib.request.build_opener(NoRedirect).open(req, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            body = response.read().decode()
            probe = response.headers.get('X-Process-View-Probe')
            return response.status, body, json.loads(base64.b64decode(probe)) if probe else None

    try:
        for _ in range(50):
            try:
                request()
                break
            except urllib.error.URLError:
                time.sleep(.1)
        else:
            raise RuntimeError('PHP fixture did not start')

        for locale in ['de_DE', 'en_US']:
            for scenario in ['full', 'empty', 'draft', 'no-contact', 'missing-contact', 'foreign-contact', 'deleted-contact', 'no-contact-rights', 'untitled', 'child-owner']:
                status, html, probe = request(scenario=scenario, locale=locale)
                check(status == 200 and probe is not None, f'{scenario}: complete MVC rendering')
                check('<!doctype html>' in html.lower() and '</html>' in html, f'{scenario}: complete layout')
                check(probe['unchanged'] and probe['mode'] == 'readonly' and probe['fileWrites'] is False, f'{scenario}: read only')
                positions = probe['positions']
                expected = [] if scenario == 'empty' else [100, 102, 101]
                check([row['id'] for row in positions] == expected, f'{scenario}: scoped stable position order')
                check(re.findall(r'<tr id="position(\d+)">', html) == [str(i) for i in expected], f'{scenario}: rendered rows')
                check(all(value in html for value in ['800.1234', '93.4567', '893.5801', 'Stored recipient snapshot']), f'{scenario}: stored totals and address')
                check(not any(value in html for value in ['FOREIGN POSITION', 'OTHER PROCESS POSITION', 'DELETED POSITION', 'OTHER OWNER POSITION', 'Foreign contact']), f'{scenario}: no data disclosure')
                for row in positions:
                    check(row['price'] == ('25.4321' if row['id'] == 102 else '12.3456') and row['quantity'] == ('3.0000' if row['id'] == 102 else '2.5000') and row['taxrate'] == ('7.0000' if row['id'] == 102 else '19.0000') and row['total'] == '99.9999', f'{scenario}: saved decimal values')
                contact_visible = scenario in ['full', 'empty', 'draft', 'untitled', 'child-owner']
                check(('/contacts/contact/edit/id/31' in html) == contact_visible, f'{scenario}: contact primary id / missing contact')
                check(bool(probe['contact']) == contact_visible, f'{scenario}: authorized contact')
                check(len(probe['contactQueries']) == (0 if scenario in ['no-contact', 'no-contact-rights'] else 1), f'{scenario}: contact query budget')
                if contact_visible:
                    check(probe['contact']['clientid'] == 70 and 'clientid = 70' in probe['contactQueries'][0].replace('`', ''), f'{scenario}: trusted contact module owner')
                check(len(probe['positionQueries']) == 1, f'{scenario}: one positions query')
                sql = probe['positionQueries'][0].replace('`', '')
                owner = 71 if scenario == 'child-owner' else 70
                check(f"clientid = {owner}" in sql and 'parentid = 17' in sql and 'deleted = 0' in sql and 'ordering ASC' in sql and 'id ASC' in sql, f'{scenario}: model scoping/order')
                check('id="emailmessages"' in html and '<form' not in html and 'data-autosave="true"' not in html, f'{scenario}: no composer/autosave form')
                if scenario == 'untitled':
                    check(bool(re.search(r'<title>[^<]*17', html)), 'primary id title fallback')

            for tab in ['#tabOverview', '#tabFiles', '#tabMessages', '#invalid']:
                _, html, probe = request(locale=locale, cookie=tab)
                selected = '#tabOverview' if tab == '#invalid' else tab
                check(probe['activeTab'] == selected, 'validated tab cookie')
                check(len(re.findall(r'<li class="active"><a href="#tab', html)) == 1, 'one active tab')
                check(f'id="{selected[1:]}" class="tab_content active"' in html, 'matching active panel')

            for path in ['/processes/process/view', '/processes/process/view/id/0', '/processes/process/view/id/-1', '/processes/process/view/id/abc', '/processes/process/view/id/999']:
                status, body, probe = request(path, locale=locale, ajax=True)
                check(status == 200 and json.loads(body) == {'ok': False, 'message': 'not_found'} and probe is None, 'invalid id denied by original controller')
                status, body, probe = request(path, locale=locale)
                check(status == 302 and 'Stored recipient snapshot' not in body and probe is None, 'invalid id redirects without rendering data')
            for extra in [{'fixture_tenant': 'foreign'}, {'fixture_tenant': 'shared'}]:
                _, body, _ = request(locale=locale, extra=extra, ajax=True)
                check(json.loads(body) == {'ok': False, 'message': 'not_found'}, 'no added process sharing / foreign access')
                status, body, _ = request(locale=locale, extra=extra)
                check(status == 302 and 'Stored recipient snapshot' not in body, 'regular foreign request redirects without data')
            _, body, _ = request(scenario='deleted-process', locale=locale, ajax=True)
            check(json.loads(body) == {'ok': False, 'message': 'not_found'}, 'deleted process denied')
            status, body, _ = request(locale=locale, extra={'fixture_role': 'denied'}, ajax=True)
            check(status == 403 and 'Stored recipient snapshot' not in body, 'ACL denial')
            status, _, probe = request(locale=locale, extra={'fixture_role': 'readonly'})
            check(status == 200 and probe['mode'] == 'readonly' and probe['unchanged'], 'view-only role')

        driver = browser.WebDriver()
        driver.command('POST', '/goog/cdp/execute', {'cmd': 'Page.addScriptToEvaluateOnNewDocument', 'params': {'source': """
window.processRequests = [];
const originalOpen = XMLHttpRequest.prototype.open;
XMLHttpRequest.prototype.open = function(method, url) { window.processRequests.push({method, url}); return originalOpen.apply(this, arguments); };
"""}})
        profiles = []
        for locale in ['de_DE', 'en_US']:
            for width in [320, 390, 768, 1280]:
                for scenario in ['full', 'empty']:
                    driver.emulate_viewport(width)
                    driver.navigate(base + '/processes/process/view/id/17?' + urllib.parse.urlencode({'fixture_process_view': scenario, 'fixture_locale': locale}))
                    result = driver.execute("""
return {rows: [...document.querySelectorAll('table#positions tbody tr')].map(row => row.id),
 totals: document.querySelector('#total').innerText,
 forms: document.querySelectorAll('form').length,
 writes: window.processRequests.filter(r => r.method.toUpperCase() !== 'GET'),
 active: document.querySelectorAll('.tab_content.active').length,
 missingIcons: [...document.querySelectorAll('svg use')].filter(use => !document.getElementById(use.getAttribute('href').slice(1))).length,
 contact: document.querySelector('#contactid').readOnly,
 composer: document.querySelectorAll('.dw-email-composer').length};
""")
                    check(result['rows'] == ([] if scenario == 'empty' else ['position100', 'position102', 'position101']), 'browser saved positions')
                    check(all(value in result['totals'] for value in ['800.1234', '93.4567', '893.5801']), 'browser saved totals')
                    check(result['forms'] == 0 and result['writes'] == [] and result['contact'] and result['composer'] == 0, 'opening view performs no autosave/lock/compose')
                    check(result['active'] == 1 and result['missingIcons'] == 0, 'browser complete layout')
                    # Preserve the native contact link and keyboard reachability; do not submit any edit action.
                    driver.execute("document.querySelector('a[href=\"/contacts/contact/edit/id/31\"]').focus()")
                    driver.shift_tab()
                    driver.keys('tab')
                    check(driver.execute('return document.activeElement.getAttribute("href")') == '/contacts/contact/edit/id/31', 'keyboard navigation remains native')
                    profiles.append({'locale': locale, 'width': width, 'scenario': scenario})

        log.flush()
        errors = re.findall(r'^.*(?:PHP (?:Warning|Fatal|Parse|Notice)|Uncaught|Database writes are forbidden|Only synthetic SELECTs).*$', log_path.read_text(), re.M)
        check(not errors, '\n'.join(errors))
        print(json.dumps({'checks': checks, 'browserProfiles': len(profiles), 'result': 'PASS', 'backend': 'original MVC, synthetic read-only adapter; no production access'}, indent=2))
    except Exception:
        log.flush()
        print('\n'.join(line for line in log_path.read_text().splitlines() if 'PHP ' in line or 'Uncaught' in line)[-6000:], file=sys.stderr)
        raise
    finally:
        if driver:
            driver.close()
        server.terminate()
        server.wait(timeout=10)
        log.close()
