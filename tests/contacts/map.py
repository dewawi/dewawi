"""Run contact map SQL, HTTP and Chromium regressions using disposable services only."""
from pathlib import Path
import json
import os
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request


root = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='dewawi-contact-map-') as directory:
    work = Path(directory)
    database = web = None
    try:
        subprocess.run(['mariadb-install-db', '--no-defaults', '--datadir=' + str(work / 'data'), '--auth-root-authentication-method=normal', '--skip-test-db'], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
        database = subprocess.Popen(['mariadbd', '--no-defaults', '--datadir=' + str(work / 'data'), '--socket=' + str(work / 'mysql.sock'), '--skip-networking', '--pid-file=' + str(work / 'server.pid'), '--log-error=' + str(work / 'database.log'), '--innodb-buffer-pool-size=32M', '--innodb-log-file-size=8M'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        for _ in range(100):
            if database.poll() is not None:
                raise RuntimeError((work / 'database.log').read_text())
            ready = subprocess.run(['mariadb', '--no-defaults', '--socket=' + str(work / 'mysql.sock'), '-uroot', '-e', 'SELECT 1'], capture_output=True)
            if ready.returncode == 0:
                break
            time.sleep(.1)
        else:
            raise RuntimeError('Disposable MariaDB did not start')
        subprocess.run(['mariadb', '--no-defaults', '--socket=' + str(work / 'mysql.sock'), '-uroot', '-e', 'CREATE DATABASE contact_map_test'], check=True)
        env = dict(os.environ, DEWAWI_CONTACT_MAP_TEST=str(work))
        (work / 'cache/contact').mkdir(parents=True)
        subprocess.run(['php', str(root / 'tests/contacts/map.php')], check=True, env=env)
        with socket.socket() as listener:
            listener.bind(('127.0.0.1', 0))
            port = listener.getsockname()[1]
        address = 'http://127.0.0.1:' + str(port)
        with (work / 'http.log').open('w') as log:
            web = subprocess.Popen(['php', '-S', '127.0.0.1:' + str(port), str(root / 'tests/contacts/map-router.php')], env=env, stdout=log, stderr=log)
            time.sleep(.3)
            checks = 0

            def get(params=None, path='/mapdata', expected=200, post=False):
                global checks
                url = address + '/erp/contacts/contact' + path
                if params:
                    url += '?' + urllib.parse.urlencode(params)
                request = urllib.request.Request(url, data=b'' if post else None)
                try:
                    response = urllib.request.urlopen(request)
                except urllib.error.HTTPError as error:
                    response = error
                body = response.read().decode()
                assert response.status == expected, (url, response.status, body, (work / 'http.log').read_text())
                checks += 1
                return body, response.headers

            body, headers = get()
            assert json.loads(body)['total'] == 248 and 'no-store' in headers['Cache-Control']
            markers = json.loads(get(dict(mode='markers', south=-90, north=90, west=-180, east=180, zoom=5, limit=25, page=20))[0])
            assert markers['visible'] == 243 and sum(row['count'] for row in markers['groups']) == 243
            single = json.loads(get(dict(mode='contacts', south=-90, north=90, west=-180, east=180, keyword='Chosen Berlin'))[0])['contacts'][0]
            assert single['url'] == '/erp/contacts/contact/view/id/1' and '<img' in single['name1']
            card = get(path='/view/id/1')[0]
            assert 'Chosen Berlin' in card and 'readonly' in card and 'data-action="multi-add"' not in card and 'data-action="delete"' not in card
            assert 'window.mapXss' in card and '<img src=x onerror=' not in card
            shared_card = get(dict(tenant=2), path='/view/id/1')[0]
            assert 'Chosen Berlin' in shared_card and 'Foreign address' not in shared_card
            assert not any(secret in body + json.dumps(markers) + json.dumps(single) for secret in ['PRIVATE_TAX', 'PRIVATE_NOTES', 'PRIVATE_STREET', 'FOREIGN_PRIVATE'])
            assert json.loads(get(dict(tenant=2))[0])['total'] == 248
            assert json.loads(get(dict(tenant=3))[0])['total'] == 1
            get(dict(denied=1), expected=403)
            get(dict(denied=1), path='/map', expected=403)
            get(dict(denied=1), path='/view/id/1', expected=403)
            get(dict(guest=1), expected=404)  # Guest redirects to the fixture's absent login page.
            get(post=True, expected=405)
            for params in [dict(role='fachpartner'), dict(catid=99), dict(tagid=99), {'keyword[]': 'bad'}, dict(mode='markers', south=91, north=90, west=-180, east=180, zoom=5), dict(mode='unknown')]:
                get(params, expected=400)
            page = get(dict(role='partner', tagid=2), path='/map')[0]
            assert 'data-contact-map' in page and 'contacts-map.js' in page and 'value="partner" selected' in page
            assert 'data-map-approximate' in page and 'Ortsebene' in page and 'href="https://www.geoapify.com/"' in page
            listing = get(path='/index')[0]
            assert 'data-contact-map-open' in listing and 'id="contactsmap"' not in listing and 'function contactsMap' not in listing
            print('PASS:', checks, 'map controller/HTTP, routes, ACL, input and projection checks')
            subprocess.run(['python3', str(root / 'tests/contacts/map-browser.py'), str(work), address], check=True)
            subprocess.run(['php', str(root / 'tests/contacts/map-stress.php')], check=True, env=env)
        print('EXPLAIN:', json.dumps({name: [{key: row[key] for key in ['table', 'type', 'key', 'rows']} for row in rows] for name, rows in json.loads((work / 'map-explain.json').read_text()).items()}))
    except Exception:
        if (work / 'http.log').exists():
            print('\n'.join(line for line in (work / 'http.log').read_text().splitlines() if ' Accepted' not in line and ' Closing' not in line))
        raise
    finally:
        for process in [web, database]:
            if process is not None:
                process.terminate()
                process.wait(timeout=20)
