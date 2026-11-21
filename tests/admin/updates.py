#!/usr/bin/env python3
"""M2A: real M1 history, SELECT-only web connection and disposable MariaDB/HTTP."""
from pathlib import Path
import hashlib, html, json, os, re, shutil, socket, subprocess, sys, tempfile, time
import urllib.error, urllib.request

ROOT = Path(__file__).resolve().parents[2]
checks = 0


def check(value, message):
    global checks
    checks += 1
    if not value:
        raise AssertionError(message)


with tempfile.TemporaryDirectory(prefix='dewawi-updates-') as directory:
    work = Path(directory)
    db_server = web = None
    try:
        source = work/'source'
        for relative in ['application', 'library/DEEC', 'languages', 'tools/migrations', 'tools/updates', 'install/config/migrations']:
            shutil.copytree(ROOT/relative, source/relative)
        for relative in ['tools/migrate.php', 'install/helper.php', 'install/SqlSplitter.php', 'install/config/config.php', 'configs/application.ini', 'configs/version.php']:
            target = source/relative
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(ROOT/relative, target)
        for entry in (ROOT/'library').iterdir():
            if entry.name != 'DEEC':
                (source/'library'/entry.name).symlink_to(entry)
        for name in ['css', 'js', 'images']:
            (source/name).symlink_to(ROOT/name)
        (source/'configs/operator.ini').write_text('[production]\n')
        (work/'sessions').mkdir()
        states = {name: 'updates_'+name for name in ['missing', 'empty', 'baseline', 'completed', 'started', 'failed', 'checksum', 'definition', 'baselinechecksum', 'unknown', 'gap', 'invalidstatus', 'ambiguous', 'invaliddate', 'badtable']}
        (work/'states.json').write_text(json.dumps(states))
        env = dict(os.environ, DEWAWI_UPDATES_TEST_WORK=str(work))
        env.pop('DEWAWI_GOBD_REPORT_DIR', None)
        subprocess.run(['mariadb-install-db', '--no-defaults', '--datadir='+str(work/'data'), '--auth-root-authentication-method=normal', '--skip-test-db'], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        db_socket = str(work/'mysql.sock')
        db_server = subprocess.Popen(['mariadbd', '--no-defaults', '--datadir='+str(work/'data'), '--socket='+db_socket, '--skip-networking', '--pid-file='+str(work/'pid'), '--log-error='+str(work/'db.log'), '--innodb-buffer-pool-size=32M', '--innodb-log-file-size=8M'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

        def sql(query, database=None):
            return subprocess.run(['mariadb', '--no-defaults', '--socket='+db_socket, '-uroot', '--batch', '--skip-column-names']+([database] if database else []), input=query, capture_output=True, text=True, check=True).stdout

        for _ in range(100):
            try:
                sql('SELECT 1')
                break
            except subprocess.CalledProcessError:
                time.sleep(.1)
        else:
            raise RuntimeError('Isolated MariaDB not ready')
        sql(';'.join('CREATE DATABASE '+name for name in states.values()))
        release = subprocess.check_output(['git', 'show', '8ab77ce4:install/config/structure.sql'], cwd=ROOT, text=True)
        sql(release, states['completed'])
        sql((ROOT/'install/config/structure.sql').read_text(), states['missing'])
        private = work/'cli.ini'
        private.write_text('[test]\nresources.db.adapter=Pdo_Mysql\nresources.db.params.host=localhost\nresources.db.params.unix_socket="'+db_socket+'"\nresources.db.params.username=root\nresources.db.params.password=""\nresources.db.params.dbname='+states['completed']+'\n')
        private.chmod(0o600)
        cli = ['php', str(source/'tools/migrate.php'), '--config='+str(private), '--environment=test']
        subprocess.run(cli+['--command=baseline'], check=True, capture_output=True)
        subprocess.run(cli+['--command=apply'], check=True, capture_output=True)
        history = 'dewawi_schema_migration'
        migration = '1.1.8-1.1.9/001-shop-shipping.sql'
        for state, database in states.items():
            if state not in ['missing', 'completed']:
                sql('CREATE TABLE permission LIKE '+states['completed']+'.permission', database)
            if state in ['missing', 'completed']:
                continue
            sql('CREATE TABLE '+history+' LIKE '+states['completed']+'.'+history, database)
            if state != 'empty':
                sql('INSERT INTO '+history+' SELECT * FROM '+states['completed']+'.'+history, database)
            if state == 'baseline':
                sql("DELETE FROM "+history+" WHERE migration_id <> 'baseline/1.1.8'", database)
            changes = {
                'started': "status='started', completed_at=NULL",
                'failed': "status='failed', completed_at=NULL, error_code='sqlstate_42000'",
                'checksum': "checksum=REPEAT('0',64)",
                'definition': "definition_checksum=REPEAT('0',64)",
                'invalidstatus': "status='forged'",
                'ambiguous': "statement_number=9999",
            }
            if state in changes:
                sql('UPDATE '+history+' SET '+changes[state]+" WHERE migration_id='"+migration+"'", database)
            if state == 'baselinechecksum':
                sql("UPDATE "+history+" SET checksum=REPEAT('0',64) WHERE migration_id='baseline/1.1.8'", database)
            if state == 'invaliddate':
                sql("SET sql_mode=''; UPDATE "+history+" SET started_at='0000-00-00 00:00:00', completed_at='0000-00-00 00:00:00' WHERE migration_id='"+migration+"'", database)
            if state == 'unknown':
                sql("INSERT INTO "+history+" SELECT 'PRIVATE_<script>_SELECT_password', checksum, definition_checksum, status, started_at, completed_at, statement_number, error_code FROM "+history+" LIMIT 1", database)
            if state == 'gap':
                sql("DELETE FROM "+history+" WHERE migration_id='baseline/1.1.8'", database)
            if state == 'badtable':
                sql('ALTER TABLE '+history+' ADD forged int', database)
        sql("CREATE USER overview@localhost IDENTIFIED BY 'fixture-secret'")
        for database in states.values():
            sql('GRANT SELECT ON '+database+'.* TO overview@localhost')

        def fixture(argument):
            result = subprocess.run(['php', str(ROOT/'tests/admin/updates-fixture.php'), argument], env=env, capture_output=True, text=True, check=True)
            check(not result.stderr, 'Unexpected fixture errors: '+result.stderr)
            return json.loads(result.stdout)

        def dump():
            data = subprocess.check_output(['mariadb-dump', '--no-defaults', '--socket='+db_socket, '-uroot', '--skip-comments', '--compact', '--order-by-primary', '--databases']+list(states.values()))
            return hashlib.sha256(data).hexdigest()

        before = dump()
        reports = {state: fixture(state) for state in states}
        check(reports['missing']['baseline'] == 'not_registered' and 'history_missing' in reports['missing']['issues'], 'Missing history falsely completed')
        check(reports['missing']['pending'] == 1 and sql("SHOW TABLES LIKE 'shopshippingclass'", states['missing']).strip(), 'Existing development shipping tables must not certify an applied migration')
        check(reports['empty']['baseline'] == 'not_registered' and 'baseline_missing' in reports['empty']['issues'], 'Empty history falsely completed')
        check(reports['baseline']['baseline'] == 'completed' and reports['baseline']['pending'] == 1 and reports['baseline']['last'] is None, 'Registered baseline and pending shipping migration')
        check(reports['completed']['pending'] == 0 and reports['completed']['last']['id'] == migration, 'Completed migration was not recognized')
        for state in ['started', 'failed', 'checksum', 'definition', 'baselinechecksum', 'unknown', 'gap', 'invalidstatus', 'ambiguous', 'invaliddate', 'badtable']:
            check(reports[state]['issues'] and reports[state]['pending'] is None and reports[state]['last'] is None, state+' must block and withhold success summaries')
        for state in ['started', 'failed']:
            check(reports[state]['migrations'][1]['status'] == state, state+' status lost')
        for state in ['checksum', 'definition']:
            check(reports[state]['migrations'][1]['checksum'] == 'mismatch', 'Checksum/definition mismatch not shown')
        check(reports['baselinechecksum']['baseline'] == 'unknown', 'Changed baseline checksum falsely accepted')
        check(reports['unknown']['issues'] == ['invalid_history'] and 'PRIVATE_' not in json.dumps(reports['unknown']), 'Unknown history content leaked')
        registry = source/'install/config/migrations/registry.json'
        catalog = registry.read_bytes()
        shipping_catalog = registry.parent/'1.1.8-1.1.9'
        shipping_catalog.rename(work/'held-transition')
        registry.write_text('{"transitions":[]}')
        check(fixture('baseline')['pending'] == 0, 'Baseline-only catalog')
        registry.write_bytes(catalog)
        (work/'held-transition').rename(shipping_catalog)

        version_file = source/'configs/version.php'
        version = version_file.read_bytes()
        check(fixture('version') == {'version': '1.1.8', 'installer': '1.1.8'}, 'Shared released version and installer')
        for content in [None, b'<?php return ["wrong"];', b'<?php syntax error', b'PRIVATE_secret', b'<?php echo "PRIVATE_secret"; return "1.1.8";']:
            if content is None:
                version_file.unlink()
            else:
                version_file.write_bytes(content)
            result = fixture('version')
            check(result['version'] is None and '1.1.8' not in result['installer'] and 'PRIVATE_' not in json.dumps(result), 'Invalid version accepted or leaked')
        version_file.write_bytes(version)

        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0)); port = sock.getsockname()[1]
        origin = 'http://127.0.0.1:'+str(port)
        log = (work/'http.log').open('w')
        web = subprocess.Popen(['php', '-d', 'opcache.enable=0', '-S', '127.0.0.1:'+str(port), '-t', str(work), str(ROOT/'tests/admin/updates-fixture.php')], env=env, stdout=log, stderr=log)

        def request(path='/admin/updates/index', method='GET'):
            try:
                with urllib.request.urlopen(urllib.request.Request(origin+path, method=method), timeout=15) as response:
                    return response.status, response.read().decode(), response.headers
            except urllib.error.HTTPError as error:
                return error.code, error.read().decode(), error.headers

        for _ in range(100):
            try:
                status, body, headers = request()
                break
            except urllib.error.URLError:
                time.sleep(.05)
        check(status == 200 and 'dw-app-shell' in body and 'Version 1.1.8' in body, 'ERP overview or central footer failed: '+body[:500])
        check('no-store' in headers.get('Cache-Control', ''), 'Overview may be cached')
        for state in states:
            for locale in ['de_DE', 'en_US']:
                status, body, _ = request('/admin/updates/index?state='+state+'&locale='+locale)
                check(status == 200 and 'UPDATES_' not in body and 'APP_VERSION_' not in body, 'Broken or untranslated overview: '+state+' '+locale)
                check(not any(secret in body for secret in ['fixture-secret', 'PRIVATE_', 'CREATE TABLE', 'SELECT *', str(work), 'sqlstate_42000']), 'SQL, secret or private path leaked')
                check('Prüfung erforderlich' in body if locale == 'de_DE' else 'Verification required' in body, 'Verification limitation not shown')
        for role in ['reader', 'guest', 'inactive', 'deleted', 'mismatch']:
            for direct in ['', '&direct=1']:
                for tenant in [7, 8, 9]:
                    status, body, _ = request('/admin/updates/index?role='+role+'&tenant='+str(tenant)+direct)
                    check(status in [302, 403] and history not in body and migration not in body, 'Unauthorized direct/global access: '+role)
            check('/admin/updates' not in request('/fixture-menu?role='+role)[1], 'Unauthorized update menu: '+role)
        for role in ['operator', 'tenantadmin']:
            for tenant in [7, 8, 9]:
                query = '?role='+role+'&tenant='+str(tenant)
                check(request('/admin/updates/index?state=completed&role='+role+'&tenant='+str(tenant))[0] == 200, 'Active administrator denied in selected tenant')
                menu = request('/fixture-menu'+query)[1]
                dashboard = request('/fixture-dashboard'+query)[1]
                check('/admin/updates' in menu and 'UPDATES_' not in menu, 'Administrator menu entry missing')
                check(dashboard.count('href="/admin/updates"') == 2, 'Sidebar and System dashboard entry disagree')
        for role in ['reader', 'guest', 'inactive', 'deleted', 'mismatch']:
            check('/admin/updates' not in request('/fixture-dashboard?role='+role)[1], 'Dashboard exposed updates to an unauthorized account')
        for method in ['POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS']:
            status, _, headers = request(method=method)
            check(status == 405 and headers.get('Allow') == 'GET, HEAD', 'Mutation method available: '+method)
        check(request(method='HEAD')[0] == 200, 'Read-only HEAD blocked')
        for action in ['baseline', 'apply', 'bundle', 'sql', 'save', 'edit', 'state', 'checksum', 'export', 'search', 'add', 'delete']:
            for method in ['GET', 'POST']:
                check(request('/admin/updates/'+action, method)[0] == 404, 'Inherited or migration action is executable: '+action)
        for content in [None, b'<?php return "bad";']:
            if content is None: version_file.unlink()
            else: version_file.write_bytes(content)
            status, body, _ = request()
            check(status == 200 and 'Version 1.1.8' not in body and 'zentrale Anwendungsversion fehlt' in body, 'Missing/invalid version not safely shown in ERP: '+str(status)+' '+body)
        version_file.write_bytes(version)
        for content in [b'broken', b'{"transitions":[{"from":"1.1.8","to":"1.1.9","migrations":[{"file":"001-shop-shipping.sql","depends":[["PRIVATE_"]]}]}]}']:
            registry.write_bytes(content)
            status, body, _ = request()
            check(status == 200 and 'Katalog oder die Basisreferenz' in body and 'PRIVATE_' not in body and 'Warning' not in body, 'Invalid catalog leaked or failed unsafely')
        registry.write_bytes(catalog)

        chrome = shutil.which('chromium') or shutil.which('google-chrome')
        check(bool(chrome), 'Chromium unavailable')
        for locale in ['de_DE', 'en_US']:
            for width in [1280, 390, 320]:
                script = r'''
const f=document.querySelector('#overview'),dashboard=document.querySelector('#dashboard');
Promise.all([f,dashboard].map(frame=>new Promise(resolve=>frame.onload=resolve))).then(()=>{setTimeout(()=>{
 const d=f.contentDocument,w=f.contentWindow,failures=[];let checks=0;
 const check=(v,m)=>{checks++;if(!v)failures.push(m)};
 check(d.documentElement.scrollWidth<=w.innerWidth,'Horizontal overflow');
 check(!d.body.textContent.includes('UPDATES_'),'Missing translations');
 check(d.querySelector('.dw-app-shell'),'ERP layout missing');
 check(d.querySelector('[role="alert"]'),'Blockers not accessible');
 const alert=d.querySelector('[role="alert"]');alert.focus();check(d.activeElement===alert,'Blocker cannot receive keyboard focus');
 const link=d.querySelector('a[href="#updates-migrations"]');link.focus();check(d.activeElement===link,'Migration navigation cannot receive focus');link.click();
 check(w.location.hash==='#updates-migrations','Native anchor navigation failed');
 check(!d.querySelector('.dw-system-updates form,.dw-system-updates button'),'Execution control present');
 check(d.querySelectorAll('.dw-system-updates__migrations > li').length===2,'Baseline or shipping catalog missing');
 const dd=dashboard.contentDocument,dw=dashboard.contentWindow;
 check(dd.documentElement.scrollWidth<=dw.innerWidth,'Dashboard horizontal overflow');
 check(!dd.body.textContent.includes('UPDATES_'),'Dashboard translation missing');
 const entry=dd.querySelector('.dw-dashboard a[href="/admin/updates"]');
 check(!!entry&&dd.querySelectorAll('a[href="/admin/updates"]').length===2,'Dashboard and menu disagree');
 if(entry){entry.focus();check(dd.activeElement===entry,'Dashboard update link cannot receive keyboard focus');check(entry.closest('.dw-dashboard-card').querySelector('h3').textContent.trim()==='System','Updates outside System card')}
 document.querySelector('#metrics').textContent=JSON.stringify({checks,failures});
},250)});
'''
                (work/'browser.html').write_text('<!doctype html><iframe id="overview" src="'+origin+'/admin/updates/index?role=tenantadmin&locale='+locale+'" style="width:'+str(width)+'px;height:2200px;border:0"></iframe><iframe id="dashboard" src="'+origin+'/fixture-dashboard?role=tenantadmin&locale='+locale+'" style="width:'+str(width)+'px;height:2200px;border:0"></iframe><pre id="metrics"></pre><script>'+script+'</script>')
                with tempfile.TemporaryDirectory(prefix='dewawi-updates-chromium-') as profile:
                    result = subprocess.run([chrome, '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--user-data-dir='+profile, '--window-size=1400,2400', '--virtual-time-budget=5000', '--dump-dom', origin+'/browser.html'], capture_output=True, text=True, timeout=30)
                metrics = re.findall(r'<pre id="metrics">(.*?)</pre>', result.stdout, re.S)
                check(result.returncode == 0 and metrics and metrics[-1], 'Browser did not complete')
                data = json.loads(html.unescape(metrics[-1]))
                check(not data['failures'], str(width)+' '+locale+': '+str(data['failures']))
                checks += data['checks']
        check(dump() == before, 'Read-only inspection changed database schema or history')
        log.flush()
        check('Fatal error' not in (work/'http.log').read_text(), 'Unexpected HTTP fatal error')
        print(f'PASS: {checks} M2A version, history, authorization, HTTP, SELECT-only and desktop/mobile Chromium checks')
    finally:
        if web:
            web.terminate(); web.wait(timeout=10)
            if sys.exc_info()[0]:
                log.flush()
                print((work/'http.log').read_text()[-6000:])
        if db_server:
            db_server.terminate(); db_server.wait(timeout=20)
