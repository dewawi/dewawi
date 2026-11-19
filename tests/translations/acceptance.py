#!/usr/bin/env python3
"""T3: real HTTP dispatch and Chromium with temporary sessions and synthetic rows only."""
import argparse
import base64
from collections import Counter
import http.client
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import time
from urllib.parse import urlencode

ROOT = Path(__file__).resolve().parents[2]
sys.dont_write_bytecode = True
ROUTES = {'admin': '/admin/index', 'calendar': '/calendar/index', 'campaigns': '/campaigns/campaign',
          'contacts': '/contacts/contact', 'ebay': '/ebay/listing', 'items': '/items/item',
          'processes': '/processes/process', 'purchases': '/purchases/purchaseorder', 'sales': '/sales/quote',
          'shops': '/shops/index', 'statistics': '/statistics/reporting', 'tasks': '/tasks/task', 'users': '/users/user/edit'}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--report', type=Path, default=ROOT / 'tests/translations/report.json', help='Audit snapshot for expected module domains and effective texts')
    parser.add_argument('--extended', action='store_true', help='Also check reverse native switching, request count, client CSRF and password presentation')
    args = parser.parse_args()
    spec = importlib.util.spec_from_file_location('t3_browser', ROOT / 'tests/site/accessibility-http.py')
    browser = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(browser)
    results = []
    extended = []
    report = json.loads(args.report.read_text())
    keys = set()
    # Extract identifier candidates independently of their occurrence in source markup/scripts.
    for file in (ROOT / 'languages/en_US').rglob('*.php'):
        keys.update(re.findall(r"['\"]([A-Z][A-Z0-9_]+)['\"]\s*=>", file.read_text()))

    def record(name, ok, **evidence):
        results.append({'scenario': name, 'status': 'passed' if ok else 'failed', **evidence})

    with tempfile.TemporaryDirectory(prefix='dewawi-translation-acceptance-') as directory:
        work = Path(directory)
        base = work / 'base'
        for folder in ['session', 'cache', 'files', 'media', 'logs']:
            (base / folder).mkdir(parents=True, exist_ok=True)
        for folder in ['languages', 'library']:
            (base / folder).symlink_to(ROOT / folder, target_is_directory=True)
        password = subprocess.check_output(['php', '-r', "echo password_hash('isolated-test-only', PASSWORD_DEFAULT);"], text=True)
        user = dict(id=42, clientid=7, username='fixture', password=password, name='Synthetic User', email='fixture@example.invalid', admin=1, activated=1, deleted=0, language='de_DE', locked=0, lockedtime=None)
        user.update({name: '' for name in ['emailsender', 'emailsignature', 'smtphost', 'smtpuser', 'smtppass', 'smtpsecure', 'smtpauth']})
        controllers = {'default': ['index'], **{module: [path.split('/')[2]] for module, path in ROUTES.items()}}
        permissions = {'id': 1, 'userid': 42, 'clientid': 7, 'deleted': 0}
        permission_columns = ['default', 'calendar', 'contacts', 'items', 'processes', 'purchases', 'sales', 'statistics']
        permissions.update({module: json.dumps({controller: ['view', 'add', 'edit', 'delete'] for controller in names}) for module, names in controllers.items() if module in permission_columns})
        rows = {'user': [user], 'client': [dict(id=7, parentid=0, company='Synthetic Tenant', deleted=0, modules={module: 7 for module in ROUTES})],
                'config': [dict(id=1, clientid=7, language='de_DE', timezone='Europe/Berlin')], 'permission': [permissions],
                'language': [dict(id=i, clientid=7, deleted=0, ordering=i, code=locale, name=name) for i, (locale, name) in enumerate([('de_DE', 'Deutsch'), ('en_US', 'English')], 1)]}
        (work / 'fixture.json').write_text(json.dumps({'rows': rows}))
        env = dict(os.environ, DEWAWI_TRANSLATION_ACCEPTANCE_WORK=str(work))
        port = browser.free_port()
        log = (work / 'server.log').open('w+')
        server = subprocess.Popen(['php', '-d', 'opcache.enable_cli=0', '-S', f'127.0.0.1:{port}', str(ROOT / 'tests/translations/acceptance-router.php')], env=env, cwd=ROOT, stdout=log, stderr=log)
        sid = None
        driver = None

        def request(path, data=None, ajax=False):
            nonlocal sid
            connection = http.client.HTTPConnection('127.0.0.1', port, timeout=25)
            headers = {'User-Agent': 'DEWAWI isolated T3 fixture'}
            if sid:
                headers['Cookie'] = 'PHPSESSID=' + sid
            if ajax:
                headers['X-Requested-With'] = 'XMLHttpRequest'
            body = None if data is None else urlencode(data)
            if data is not None:
                headers['Content-Type'] = 'application/x-www-form-urlencoded'
            connection.request('GET' if data is None else 'POST', path, body=body, headers=headers)
            response = connection.getresponse()
            text = response.read().decode(errors='replace')
            for cookie in response.headers.get_all('Set-Cookie', []):
                match = re.search(r'PHPSESSID=([^;]+)', cookie)
                if match:
                    sid = match[1]
            meta = json.loads(base64.b64decode(response.headers['X-T3-Meta'])) if response.headers.get('X-T3-Meta') else None
            result = {'http_status': response.status, 'meta': meta, 'location': response.headers.get('Location'), 'text': text}
            connection.close()
            return result

        try:
            for _ in range(100):
                try:
                    request('/favicon.ico')
                    break
                except OSError:
                    time.sleep(.05)
            for locale in ['de_DE', 'en_US']:
                request('/acceptance/guest-locale?' + urlencode({'locale': locale}))
                page = request('/users/user/login')
                record('guest login form ' + locale, page['http_status'] == 200 and ('Benutzername' if locale == 'de_DE' else 'Username') in page['text'], http_status=page['http_status'], meta=page['meta'], error=page['text'][:500] if page['http_status'] != 200 else None)
                record('login HTML language ' + locale, f'lang="{locale[:2]}"' in page['text'], observed=re.findall(r'<html[^>]+>', page['text']))
                page = request('/users/user/login', {'username': 'fixture', 'password': 'wrong'})
                record('failed login message ' + locale, page['http_status'] == 200 and (locale == 'de_DE' or 'Benutzername und Passwort' not in page['text']), german_message_present='Benutzername und Passwort' in page['text'], http_status=page['http_status'])
            page = request('/users/user/login', {'username': 'fixture', 'password': 'isolated-test-only'})
            record('real login with synthetic account', page['http_status'] == 302 and bool(page['meta'] and page['meta']['authenticated']), **{k: v for k, v in page.items() if k != 'text'}, error=page['text'][:500] if page['http_status'] != 302 else None)
            pages = []
            page_contexts = {}
            for locale in ['de_DE', 'en_US', 'de_DE']:
                switch = request('/users/user/language', {'language': locale}, ajax=True)
                record('session locale switch ' + locale, switch['http_status'] == 200, http_status=switch['http_status'])
                for module, path in ROUTES.items():
                    page = request(path + ('?site_locale=' + locale if module == 'shops' else ''))
                    ok = page['http_status'] == 200 and page['meta'] and page['meta']['locale'] == locale and page['meta']['clientid'] == 7
                    # Full text node analysis is performed below in Chromium, not against raw HTML/JS.
                    record('HTTP module ' + module + ' ' + locale, bool(ok), http_status=page['http_status'], meta=page['meta'], error=page['text'][:600] if page['http_status'] != 200 else None)
                    expected = next(context for context in report['runtime']['observations']['request_contexts'] if context['locale'] == locale and context['module'] == module)
                    record('loaded domains and effective texts ' + module + ' ' + locale, bool(page['meta'] and page['meta']['domains'] == expected['domains'] and page['meta']['hash'] == expected['effective_sha256']), expected_domains=expected['domains'], observed_hash=page['meta']['hash'] if page['meta'] else None)
                    if ok and (module, locale, path) not in pages:
                        pages.append((module, locale, path))
                        page_contexts[(module, locale)] = page['meta']
                if locale == 'de_DE' and len(pages) > 13:
                    break
                for name, data in [('valid profile auto-save', {'name': 'Synthetic Updated'}), ('invalid profile validation', {'email': 'bad email'}), ('forbidden profile field', {'admin': '0'})]:
                    result = request('/users/user/edit', data, ajax=True)
                    record(name + ' ' + locale, result['http_status'] == (403 if 'forbidden' in name else 200), http_status=result['http_status'], response=result['text'][:1000], meta=result['meta'])
                    if name == 'invalid profile validation':
                        payload = json.loads(result['text'])
                        record('profile validation API contract ' + locale, payload.get('saved') is False and 'email' in payload.get('errors', {}), response=payload)
            invalid = request('/users/user/language', {'language': '../../xx'}, ajax=True)
            record('invalid locale rejected', invalid['http_status'] == 400, http_status=invalid['http_status'])
            driver = browser.WebDriver()
            origin = f'http://127.0.0.1:{port}'
            driver.navigate(origin + '/users/user/login')
            driver.add_cookie('PHPSESSID', sid)
            driver.command('POST', '/goog/cdp/execute', {'cmd': 'Network.enable', 'params': {}})
            driver.command('POST', '/goog/cdp/execute', {'cmd': 'Network.setBlockedURLs', 'params': {'urls': ['https://*', 'http://*.com/*', 'http://*.net/*']}})
            for locale in ['de_DE', 'en_US']:
                request('/users/user/language', {'language': locale}, ajax=True)
                for module, candidate_locale, path in pages:
                    if candidate_locale != locale:
                        continue
                    driver.navigate(origin + path + ('?site_locale=' + locale if module == 'shops' else ''))
                    visible = driver.execute('return document.body.innerText')
                    attributes = driver.execute("return Array.from(document.querySelectorAll('[title],[placeholder],[aria-label]')).flatMap(e=>['title','placeholder','aria-label'].map(a=>e.getAttribute(a)||'')).join('\\n')")
                    unresolved = sorted(set(re.findall(r'\b[A-Z][A-Z0-9]+(?:_[A-Z0-9]+)+\b', visible + '\n' + attributes)) & keys)
                    missing_identifiers = {issue['value'] for issue in page_contexts[(module, locale)]['issues'] if issue['type'] == 'missing_key' and issue['value'] != 'DEWAWI' and re.fullmatch('[A-Z][A-Z0-9_]*', issue['value'])}
                    route_labels = sorted(value for value in missing_identifiers if re.search(r'\b' + re.escape(value) + r'\b', visible))
                    record('browser module ' + module + ' ' + locale, not unresolved and not route_labels, unresolved_keys=unresolved, untranslated_route_labels=route_labels, title=driver.execute('return document.title'), html_lang=driver.execute('return document.documentElement.lang'), jquery_loaded=driver.execute('return typeof jQuery !== "undefined"'), text_excerpt=visible[:350])
                    if module == 'admin' and locale == 'en_US':
                        record('English admin descriptions', 'Stammdaten verwalten' not in visible, german_description_present='Stammdaten verwalten' in visible)
                    if module == 'users' and locale == 'en_US':
                        record('English navigation accessible labels', 'Menü schließen' not in attributes, german_label_present='Menü schließen' in attributes)
                        record('English password dialog trigger', 'Neues Passwort' not in visible, german_label_present='Neues Passwort' in visible)
                    if module == 'users':
                        notification = driver.command('POST', '/execute/async', {'script': "var done=arguments[arguments.length-1]; fetch('/users/user/edit',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:'email=invalid'}).then(r=>r.json()).then(data=>{showSaveError(data,false);done({text:document.querySelector('#messages').innerText,expected:Dewawi.message('MESSAGES_FORM_IS_INVALID')});});", 'args': []})
                        record('browser localized AJAX validation ' + locale, notification['expected'] in notification['text'] and 'Form is invalid' not in notification['text'], notification=notification)
                        element = driver.element('input[name=name]')
                        driver.command('POST', '/element/' + element + '/clear', {})
                        driver.command('POST', '/element/' + element + '/value', {'text': 'Synthetic Browser ' + locale})
                        driver.click('.dw-app-header__brand')
                        driver.navigate(origin + '/users/user/edit')
                        record('browser field auto-save and reload ' + locale, driver.execute('return document.querySelector("input[name=name]").value') == 'Synthetic Browser ' + locale)
            for locale in ['de_DE', 'en_US']:
                for action in ['success', 'error']:
                    page = request('/shops/contact/' + action + '?site_locale=' + locale)
                    record('public contact ' + action + ' ' + locale, page['http_status'] == 200, http_status=page['http_status'], meta=page['meta'])
                    driver.navigate(origin + '/shops/contact/' + action + '?site_locale=' + locale)
                    visible = driver.execute('return document.body.innerText')
                    record('public contact wording ' + action + ' ' + locale, locale == 'de_DE' or 'Es ist ein Fehler' not in visible, text_excerpt=visible[:650])
                page = request('/shops/page/index/id/999?site_locale=' + locale)
                record('public missing CMS page ' + locale, page['http_status'] == 404, http_status=page['http_status'], meta=page['meta'], text_excerpt=re.sub('<[^>]+>', ' ', page['text'])[-650:])
            # Opposing site/UI locale, followed by ERP navigation, must preserve the session.
            request('/users/user/language', {'language': 'en_US'}, ajax=True)
            site = request('/shops/index?site_locale=de_DE')
            erp = request('/contacts/contact')
            record('site language independent of English UI session', site['meta']['locale'] == 'de_DE' and erp['meta']['locale'] == 'en_US', site_locale=site['meta']['locale'], next_erp_locale=erp['meta']['locale'])
            driver.navigate(origin + '/users/user/edit')
            live = driver.command('POST', '/execute/async', {'script': "var done=arguments[arguments.length-1]; fetch('/users/user/language',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:'language=en_US'}).then(r=>done(r.status)).catch(e=>done(String(e)));", 'args': []})
            record('browser AJAX locale endpoint', live == 200, http_status=live)
            driver.navigate(origin + '/users/user/edit')
            record('browser locale persists after reload', 'Username' in driver.execute('return document.body.innerText'))
            driver.emulate_viewport(390, 844)
            record('mobile navigation rendered', driver.execute('return !!document.querySelector("nav")'))
            # Real click logout, followed by AJAX expiration through the real ACL forward.
            logout = request('/users/user/logout')
            expired = request('/contacts/contact', ajax=True)
            record('guest AJAX forward', expired['http_status'] == 401 and expired['meta'] and not expired['meta']['authenticated'], http_status=expired['http_status'], meta=expired['meta'], response=expired['text'][:500])
            for locale in ['de_DE', 'en_US']:
                public = request('/shops/index?site_locale=' + locale)
                record('anonymous public site ' + locale, public['http_status'] == 200 and public['meta']['locale'] == locale and not public['meta']['authenticated'] and public['meta']['clientid'] is None, http_status=public['http_status'], meta=public['meta'])
            driver.close()
            driver = browser.WebDriver()
            driver.navigate(origin + '/users/user/login')
            for field, value in [('username', 'fixture'), ('password', 'isolated-test-only')]:
                element = driver.element('[name=' + field + ']')
                driver.command('POST', '/element/' + element + '/value', {'text': value})
            driver.click('button[type=submit]')
            driver.wait('return !!document.querySelector(".dw-userbar")', timeout=5)
            record('browser login through actual form submit', driver.execute('return !!document.querySelector(".dw-userbar")'))
            element = driver.element('.dw-userbar #language')
            driver.click('.dw-userbar #language')
            driver.command('POST', '/element/' + element + '/value', {'text': 'English'})
            driver.keys('enter')
            try:
                driver.wait('return document.documentElement.lang === "en-US"', timeout=3)
            except AssertionError:
                pass
            record('browser actual language selector reload', driver.execute('return document.documentElement.lang === "en-US"'),
                   dom=driver.execute('return {lang:document.documentElement.lang,selected:document.querySelector("#language").value,legacyContainer:!!document.querySelector("#userinfo") }'))
            if args.extended:
                def extra(name, ok, **evidence):
                    extended.append({'scenario': name, 'status': 'passed' if ok else 'failed', **evidence})

                def browser_request(path, data=None):
                    return driver.command('POST', '/execute/async', {'script': "var path=arguments[0], data=arguments[1], done=arguments[2];fetch(path,{method:data===null?'GET':'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:data}).then(async r=>done({status:r.status,text:await r.text(),meta:r.headers.get('X-T3-Meta')?JSON.parse(atob(r.headers.get('X-T3-Meta'))):null}));", 'args': [path, None if data is None else urlencode(data)]})

                for target, text in [('de_DE', 'Deutsch'), ('en_US', 'English')]:
                    driver.execute("sessionStorage.setItem('t4-switch-count','0');jQuery(document).on('ajaxSend.t4probe',function(e,x,s){if(s.url.indexOf('/users/user/language')!==-1)sessionStorage.setItem('t4-switch-count',Number(sessionStorage.getItem('t4-switch-count'))+1);});")
                    element = driver.element('.dw-userbar__switchers #language')
                    driver.click('.dw-userbar__switchers #language')
                    driver.command('POST', '/element/' + element + '/value', {'text': text})
                    driver.keys('enter')
                    driver.wait('return document.documentElement.lang === ' + json.dumps(target.replace('_', '-')), timeout=5)
                    count = driver.execute("return Number(sessionStorage.getItem('t4-switch-count'))")
                    extra('native switch and exactly one request ' + target, count == 1, requests=count)
                    page = browser_request('/contacts/contact')
                    extra('native switch preserves auth and client ' + target, page['meta']['locale'] == target and page['meta']['authenticated'] and page['meta']['clientid'] == 7, meta=page['meta'])
                    invalid = browser_request('/users/user/language', {'language': 'fr_FR'})
                    extra('unsupported locale cannot change session ' + target, invalid['status'] == 400 and browser_request('/contacts/contact')['meta']['locale'] == target)
                    csrf = browser_request('/users/user/client', {'clientid': '99', 'client_csrf': 'invalid'})
                    extra('client CSRF remains enforced ' + target, csrf['status'] == 403 and browser_request('/contacts/contact')['meta']['clientid'] == 7, http_status=csrf['status'])
                driver.navigate(origin + '/users/user/password')
                password_text = driver.execute('return document.body.innerText')
                extra('English password advice and button', 'Use at least 8 characters' in password_text and 'Change password' in password_text and 'Kennwort' not in password_text, text=password_text[:800])
                fields = driver.execute('return Array.from(document.querySelectorAll("form#password input[type=password]")).map(e=>e.name)')
                extra('password fields rendered as inputs', set(fields) == {'passwordactual', 'passwordnew', 'passwordconfirm'} and 'Array' not in password_text, fields=fields)
                for field, value in [('passwordactual', 'wrong'), ('passwordnew', 'fixture-only-new'), ('passwordconfirm', 'fixture-only-new')]:
                    element = driver.element('input[name=' + field + ']')
                    driver.command('POST', '/element/' + element + '/value', {'text': value})
                driver.click('button.password')
                driver.wait('return !!document.querySelector("#messages") && document.querySelector("#messages").innerText.indexOf("Please check your entries.") !== -1')
                extra('password dialog rejects incorrect password in English', 'Please check your entries.' in driver.execute('return document.querySelector("#messages").innerText'))
                invalid_password = browser_request('/users/user/password', {'passwordactual': 'wrong', 'passwordnew': 'fixture-only-new', 'passwordconfirm': 'fixture-only-new'})
                extra('English invalid password response', invalid_password['status'] == 200 and 'Please check your entries.' in invalid_password['text'], response=invalid_password['text'])
        except Exception as error:
            record('fixture execution', False, error=str(error), driver_error=error.read().decode(errors='replace')[:1800] if hasattr(error, 'read') else None, browser_text=driver.execute('return document.body.innerText').strip()[:600] if driver else None)
        finally:
            if driver:
                driver.close()
            server.terminate()
            server.wait(timeout=10)
            log.seek(0)
            errors = [line.strip() for line in log if any(marker in line for marker in ['Fatal', 'Uncaught', 'fixture predicate', 'Exception:'])]
            log.close()
    output = {'scope': 'synthetic account/client 42/7; in-memory SQL; temp sessions; external browser hosts blocked',
              'guest_locale_note': 'EN guest presentation injected by fixture; no production anonymous language selector exists',
              'results': results, 'counts': dict(Counter(r['status'] for r in results)), 'server_errors': errors}
    if args.extended:
        output['extended_results'] = extended
        output['extended_counts'] = dict(Counter(r['status'] for r in extended))
    print(json.dumps(output, ensure_ascii=False, indent=2))
    return int(any(r['status'] == 'failed' for r in results + extended))


if __name__ == '__main__':
    sys.exit(main())
