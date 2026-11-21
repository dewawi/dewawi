"""Variant A cases; run through update-worker.py's disposable MariaDB harness."""
import concurrent.futures
import fcntl
import hashlib
import html
from html.parser import HTMLParser
import json
import os
from pathlib import Path
import re
import socket
import shutil
import subprocess
import time
import urllib.parse


class Forms(HTMLParser):
    def __init__(self, body):
        super().__init__(); self.forms = {}; self.current = None; self.feed(body)

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'form':
            self.current = a['action'].rsplit('/', 1)[-1]; self.forms[self.current] = {}
        if tag == 'input' and self.current and a.get('name'):
            self.forms[self.current][a['name']] = a.get('value', '')

    def handle_endtag(self, tag):
        if tag == 'form': self.current = None


def run(c):
    check, sql, request, work, source = c['check'], c['sql'], c['request'], c['work'], c['source']
    start = c['checks']
    sql("CREATE USER hosting@localhost IDENTIFIED BY 'hosting-fixture'; CREATE USER hostingread@localhost IDENTIFIED BY 'hosting-fixture'")
    state_dir = work/'hosting'
    config = source/'configs/database.ini'
    password = 'test-password'
    query = ''
    restore_number = 0

    def write(p, value):
        c['write'](p, value, 0o600)

    def restart(limit=0):
        c['web'].terminate(); c['web'].wait(timeout=10)
        c['web'] = subprocess.Popen(['php', '-d', 'opcache.enable=0', '-d', 'max_execution_time='+str(limit), '-S', '127.0.0.1:'+str(c['port']), '-t', str(source), str(c['ROOT']/'tests/admin/updates-fixture.php')], env=c['env'], stdout=c['log'], stderr=c['log'])
        for _ in range(100):
            try: request(); return
            except Exception: time.sleep(.03)
        raise AssertionError('Hosting HTTP server not ready')

    def configure(user='hosting'):
        text = '[production]\nsystemUpdates.mode=shared\nsystemUpdates.statePath="'+str(state_dir)+'"\nresources.db.adapter=Pdo_Mysql\n'
        values = {'host':'localhost', 'unix_socket':str(work/'mysql.sock'), 'username':user, 'password':'hosting-fixture', 'dbname':c['database'], 'charset':'utf8mb4'}
        text += ''.join('resources.db.params.'+k+'="'+v+'"\n' for k,v in values.items())
        config.write_text(text); config.chmod(0o600); (source/'configs/operator.ini').write_text(text)

    def fresh(content='CREATE TABLE hosting_marker (id int); INSERT INTO hosting_marker VALUES (1);', full=False, baseline=True):
        nonlocal query
        query = ''
        c['browser'] = c['client']()
        # Worker publication is still tested on every case before switching to A.
        if config.exists(): config.unlink()
        (source/'configs/operator.ini').write_text('[production]\nsystemUpdates.mode=worker\n')
        c['new_case'](content, full=full, baseline=baseline)
        sql('SET GLOBAL read_only=0; GRANT ALL ON '+c['database']+'.* TO hosting@localhost; GRANT SELECT ON '+c['database']+'.* TO hostingread@localhost')
        sql("UPDATE user SET password='"+hashlib.md5(password.encode()).hexdigest()+"'", c['database'])
        if state_dir.exists(): state_dir.rename(work/('hosting-archive-'+str(c['case_number'])))
        state_dir.mkdir(mode=0o700)
        configure()
        init = subprocess.run(['php', str(c['ROOT']/'tools/update-hosting.php'), '--config='+str(config), '--environment=production', '--command=init'], capture_output=True, text=True)
        # The CLI uses its own repository catalog, identical to the real baseline catalog.
        check(init.returncode == 0, 'Hosting setup without root: '+init.stderr)
        check((state_dir/'gate.lock').stat().st_uid == os.geteuid(), 'Additional OS identity required')
        check(sql('SELECT @@GLOBAL.read_only') == '0', 'Hosting requires global fence')

    def get(agent=None):
        status, body, _ = request('/admin/updates/index'+query, agent=agent)
        check(status == 200, 'Hosting overview failed: '+str(status)+' '+body[-1000:])
        return body

    def fields(action, agent=None):
        forms = Forms(get(agent)).forms
        check(action in forms, 'No '+action+' form: '+get(agent)[-2500:])
        return forms[action]

    def post(action, data=None, expected=302, agent=None):
        data = fields(action, agent) if data is None else dict(data)
        if 'password' in data: data['password'] = password
        if 'confirm' in data: data['confirm'] = '1'
        status, body, _ = request('/admin/updates/'+action+query, data, agent)
        check(status == expected, action+' status '+str(status)+' expected '+str(expected)+': '+body[-1700:])
        return data

    def evidence(historical=True, seconds=1, real_backup=False):
        nonlocal restore_number
        m = json.loads((state_dir/'maintenance.json').read_text()); control = json.loads((state_dir/'control.json').read_text())
        registry = json.loads(c['registry'].read_text()); entry = registry['transitions'][0]['migrations'][0]['file']
        baseline = not sql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='"+c['database']+"' AND TABLE_NAME='dewawi_schema_migration'") == '1'
        selected = 'baseline/1.1.8' if baseline else '1.1.8-1.1.9/'+entry
        artifact = source/'tools/migrations/baseline-1.1.8.json' if baseline else c['registry'].parent/selected
        now = int(time.time())
        def report(name):
            p = work/name; p.write_text('External synthetic evidence for '+c['database']); p.chmod(0o600)
            return {'file':str(p), 'sha256':hashlib.sha256(p.read_bytes()).hexdigest()}
        value = {'format':1, 'maintenance':m['id'], 'installation':control['installation'], 'database':control['database'], 'expires_at':now+900, 'external_writers_stopped':True,
                 'writers':dict(report('hosting-writers.txt'), checked_at=now, confirmed_by='external-operator'),
                 'backup':dict(report('hosting-backup.sql'), taken_at=now), 'restore':dict(report('hosting-restore.txt'), tested_at=now, confirmed_by='external-dba'),
                 'historical':dict(report('hosting-historical.txt'), checked_at=now, confirmed_by='independent-auditor') if historical else None,
                 'execution':{'migration':selected, 'checksum':hashlib.sha256(artifact.read_bytes()).hexdigest(), 'http_seconds':seconds}}
        if real_backup:
            backup=work/'hosting-backup.sql'
            result=subprocess.run(['mariadb-dump','--no-defaults','--socket='+str(work/'mysql.sock'),'-uroot','--skip-lock-tables',c['database']],capture_output=True,check=True)
            backup.write_bytes(result.stdout); backup.chmod(0o600)
            value['backup']['sha256']=hashlib.sha256(result.stdout).hexdigest()
            restore_number+=1; restored='m2e_restore_'+str(restore_number)
            sql('CREATE DATABASE '+restored); sql(result.stdout.decode(),restored)
            check(sql('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()',restored)==sql('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()',c['database']),'Actual backup restore schema differs')
            check(sql('SELECT SUM(balance) FROM tenant_fixture',restored)=='300','Actual backup restore lost tenant data')
            report_path=Path(value['restore']['file'])
            report_path.write_text(json.dumps({'restored_database':restored,'schema_and_tenant_balance_verified':True,'backup_sha256':value['backup']['sha256']}))
            value['restore']['sha256']=hashlib.sha256(report_path.read_bytes()).hexdigest()
            value['restore']['tested_at']=int(time.time())
        write(state_dir/'evidence.json', value)
        return value

    def approved(**kwargs):
        post('maintenance'); evidence(**kwargs); post('prepare'); old = post('approve')
        forms = Forms(get()).forms; check('execute' in forms and 'approve' not in forms, 'Approval is not one-use')
        return fields('execute'), old

    def count():
        return int(sql('SELECT COUNT(*) FROM dewawi_schema_migration', c['database'])) if sql("SHOW TABLES LIKE 'dewawi_schema_migration'", c['database']) else 0

    fresh(full=True, baseline=False)
    # Hosting-panel configuration plus explicit HTTP initialization needs no CLI.
    (state_dir/'control.json').unlink(); (state_dir/'gate.lock').unlink()
    check('initialize' in Forms(get()).forms, 'No rootless browser setup')
    d=fields('initialize'); d['password']='wrong'
    check(request('/admin/updates/initialize',d)[0]==403, 'Initialization skipped password')
    post('initialize'); check((state_dir/'control.json').exists(), 'HTTP setup failed')
    check(count()==0, 'HTTP setup wrote schema/history')
    with (state_dir/'gate.lock').open('rb') as held:
        fcntl.flock(held,fcntl.LOCK_SH)
        post('maintenance',expected=409)
        check(not (state_dir/'maintenance.json').exists(),'Maintenance did not drain active readers/writers')
    query = '?role=tenantadmin&tenant=9'
    post('maintenance'); check(request('/ordinary-write')[0] == 503, 'Ordinary GET writer bypassed maintenance')
    probe = '''set_include_path($argv[1].'/library'.PATH_SEPARATOR.get_include_path());spl_autoload_register(function($c){if($p=stream_resolve_include_path(str_replace('_','/',$c).'.php'))require_once $p;});DEEC_UpdateMaintenance::enter(['mode'=>'shared','statePath'=>$argv[2]],true);$r=new Zend_Controller_Request_Simple('edit','item','items');$denied=!DEEC_UpdateMaintenance::dispatchAllowed($r);$r->setModuleName('admin')->setControllerName('updates')->setActionName('index');echo json_encode([$denied,DEEC_UpdateMaintenance::dispatchAllowed($r)]);'''
    result=subprocess.run(['php','-r',probe,str(source),str(state_dir)],capture_output=True,text=True)
    check(result.returncode==0 and json.loads(result.stdout)==[True,True],'Forwarding escaped maintenance dispatch guard')
    for entry in ['index.php','cron.php']:
        (source/entry).write_bytes((c['ROOT']/entry).read_bytes())
    for command in [['php',str(source/'cron.php')],['php',str(source/'tools/migrate.php'),'--config='+str(config),'--environment=production','--command=status'],
                    ['php',str(source/'application/cli/contact-geocode.php'),'--help'],['php',str(source/'application/cli/contact-geocoding-batch.php'),'--help']]:
        result=subprocess.run(command,cwd=source,capture_output=True,text=True)
        check(result.returncode!=0 and 'Maintenance unavailable' in result.stderr,'Unfenced cron/technical migration')
    check(not sql('SELECT id FROM tenant_fixture WHERE id=99', c['database']), 'Maintenance allowed DB write')
    post('prepare', expected=409); evidence(historical=False); post('prepare', expected=409)
    evidence(real_backup=True); post('prepare'); data = fields('approve')
    for change in [{'csrf':'wrong'}, {'nonce':'wrong'}, {'confirm':'0'}, {'password':'wrong'}]:
        d = dict(data, password=password, confirm='1'); d.update(change)
        status, _, _ = request('/admin/updates/approve'+query, d); check(status == 403, 'Forged approval accepted: '+str(change))
    before = count(); old = post('approve'); check(count() == before == 0, 'Approval performed SQL')
    status, _, _ = request('/admin/updates/approve'+query, dict(old, password=password)); check(status == 403, 'Approval replay')
    execution = fields('execute')
    second=c['client'](); alternate=fields('execute',second) if 'execute' in Forms(get(second)).forms else {'csrf':Forms(get(second)).forms['prepare']['csrf']}
    data=dict(execution,password=password,csrf=alternate['csrf'])
    status,body,_=request('/admin/updates/execute'+query,data,second)
    check(status==403 and not list(state_dir.glob('*.accepted.json')),'Another session used an approval: '+str(status))
    post('execute'); check(count() == 1, 'Baseline HTTP registration failed')
    archive=json.loads((state_dir/(execution['request']+'.evidence.json')).read_text())
    check(archive['source_sha256']==hashlib.sha256(archive['source'].encode()).hexdigest() and archive['source']==(state_dir/'evidence.json').read_text(),'Exact bound evidence bytes were not retained')
    check(sql('SELECT status,statement_number FROM dewawi_schema_migration',c['database']) == 'completed\t0', 'Baseline history malformed')
    check(not sql("SHOW TABLES LIKE 'shopshippingclass'",c['database']), 'Baseline applied shipping')
    check('001-shop-shipping.sql' in get(), 'Shipping not pending')
    post('execute', execution, expected=409)
    evidence(real_backup=True); post('prepare'); post('approve'); post('execute')
    check(count() == 2 and sql("SHOW TABLES LIKE 'shopshippingclass'",c['database']), 'Shipping HTTP migration failed')
    post('finish'); check(request('/ordinary-write')[0] == 200, 'Maintenance cannot end after success')
    for entry in ['contact-geocode.php','contact-geocoding-batch.php']:
        result=subprocess.run(['php',str(source/'application/cli'/entry),'--help'],capture_output=True,text=True)
        check(result.returncode==0 and not result.stderr,'CLI bootstrap/help broken outside maintenance: '+result.stderr)
    check(sql('SELECT SUM(balance) FROM tenant_fixture WHERE id IN (7,8)',c['database']) == '300', 'Tenant balances changed')

    fresh()
    registry=json.loads(c['registry'].read_text()); registry['transitions'][0]['migrations'].append({'file':'002-next.sql','depends':[]})
    write(c['registry'],registry); (c['registry'].parent/'1.1.8-1.1.9/002-next.sql').write_text('CREATE TABLE next_migration (id int);')
    approved(); post('execute')
    check(sql("SHOW TABLES LIKE 'hosting_marker'",c['database']) and not sql("SHOW TABLES LIKE 'next_migration'",c['database']),'Approval executed more than one migration')

    fresh(full=False,baseline=False); post('maintenance'); evidence(); post('prepare',expected=409)
    check(count()==0 and not sql("SHOW TABLES LIKE 'dewawi_schema_migration'",c['database']),'Incomplete baseline registered through A')

    fresh(); token=fields('maintenance')['csrf']
    for file in [config,source/'configs/operator.ini']: file.write_text(file.read_text()+'resources.db.params.persistent=1\n')
    post('prepare',{'csrf':token},expected=409)
    check(not list(state_dir.glob('*.accepted.json')),'Persistent PDO session settings could leak')

    for failure in ['rights','backup','restore','writers','identity','pin','history','catalog','evidence','expired','admin','inactive','deleted','no-maintenance']:
        fresh(); post('maintenance'); e = evidence()
        if failure == 'rights': configure('hostingread'); post('prepare', expected=409); check(count()==1,'DDL without rights'); continue
        if failure in ['backup','restore','writers','identity','pin']:
            if failure in ['backup','restore']: e[failure]['sha256']='0'*64
            if failure == 'writers': e['external_writers_stopped']=False
            if failure == 'identity': e['database']='0'*64
            if failure == 'pin':
                p=state_dir/'control.json'; control=json.loads(p.read_text()); control['database']='0'*64; write(p,control)
            write(state_dir/'evidence.json', e); post('prepare',expected=409); check(count()==1,'Invalid prerequisites mutated DB'); continue
        post('prepare'); post('approve'); execution = fields('execute')
        if failure == 'history': sql('UPDATE dewawi_schema_migration SET completed_at=DATE_ADD(completed_at, INTERVAL 1 SECOND)',c['database'])
        if failure == 'catalog':
            f=c['registry'].parent/'1.1.8-1.1.9/001-test.sql'; f.write_text(f.read_text()+'\n-- stale\n')
        if failure == 'evidence': e['expires_at']+=1; write(state_dir/'evidence.json',e)
        if failure == 'expired':
            f=next(state_dir.glob('*.request.json')); r=json.loads(f.read_text()); r['expires']=0; write(f,r)
        if failure in ['admin','inactive','deleted']:
            flag={'admin':'admin=0','inactive':'activated=0','deleted':'deleted=1'}[failure]; sql('UPDATE user SET '+flag+' WHERE id=42',c['database'])
        if failure == 'no-maintenance': (state_dir/'maintenance.json').unlink()
        post('execute', execution, expected=403 if failure in ['admin','inactive','deleted'] else 409)
        check(not sql("SHOW TABLES LIKE 'hosting_marker'",c['database']), 'Invalid execution: '+failure)

    fresh(); post('maintenance'); evidence(); post('prepare')
    a, b = c['client'](), c['client']()
    # Independent sessions review the same current plan.
    for agent in [a,b]: post('prepare',agent=agent)
    left,right=fields('approve',a),fields('approve',b)
    for d in [left,right]: d.update(password=password,confirm='1')
    with concurrent.futures.ThreadPoolExecutor() as pool:
        statuses=list(pool.map(lambda pair:request('/admin/updates/approve',pair[1],pair[0])[0],[(a,left),(b,right)]))
    check(sorted(statuses)==[302,409], 'Concurrent approvals duplicated')
    check(len(list(state_dir.glob('*.request.json')))==1,'Multiple approved jobs')

    fresh('CREATE TABLE first_ddl (id int); INSERT INTO absent_table VALUES (1);')
    execution,_=approved(); post('execute')
    check(sql("SHOW TABLES LIKE 'first_ddl'",c['database']) and sql("SELECT status FROM dewawi_schema_migration WHERE migration_id LIKE '%001-test.sql'",c['database'])=='failed','Partial DDL was hidden')
    post('execute',execution,expected=409); post('prepare',expected=409); post('finish',expected=409)
    check(request('/ordinary-write')[0]==503,'Failed DDL released maintenance')

    fresh(); execution,_=approved(seconds=11); post('execute',execution,expected=409)
    cli=subprocess.run(['php',str(c['ROOT']/'tools/update-hosting.php'),'--config='+str(config),'--environment=production','--command=execute','--request='+execution['request']],capture_output=True,text=True)
    # Use the mirrored catalog for a synthetic fixture; production CLI always uses its own code catalog.
    check(cli.returncode!=0 and not sql("SHOW TABLES LIKE 'hosting_marker'",c['database']),'CLI silently accepted a different code catalog')
    check('rejected' in next(state_dir.glob('*.result.json')).read_text(),'Stale CLI plan remained reusable')
    check(json.loads(next(state_dir.glob('*.result.json')).read_text())['code'] in ['plan_changed','evidence'],'Rejection reason was not retained')
    post('prepare'); post('approve'); execution=fields('execute')
    mirror=source/'tools/update-hosting.php'; mirror.write_bytes((c['ROOT']/'tools/update-hosting.php').read_bytes())
    cli=subprocess.run(['php',str(mirror),'--config='+str(config),'--environment=production','--command=execute','--request='+execution['request']],capture_output=True,text=True)
    check(cli.returncode==0 and sql("SHOW TABLES LIKE 'hosting_marker'",c['database']),'Same-account CLI alternative failed: '+cli.stderr)

    fresh(); execution,_=approved(); restart(1); post('execute',execution,expected=409); restart()
    check(not sql("SHOW TABLES LIKE 'hosting_marker'",c['database']),'Tight PHP runtime ignored')

    fresh('CREATE TABLE hosting_timeout (id int); INSERT INTO hosting_timeout VALUES (SLEEP(8)); CREATE TABLE after_timeout (id int);')
    execution,_=approved(); post('execute')
    check('manual_review' in next(state_dir.glob('*.result.json')).read_text() and not sql("SHOW TABLES LIKE 'after_timeout'",c['database']),'Statement timeout continued migration')
    check(sql("SELECT status FROM dewawi_schema_migration WHERE migration_id LIKE '%001-test.sql'",c['database'])=='failed','Timeout did not persist M1 failure')

    fresh('CREATE TABLE hosting_abort AS SELECT SLEEP(1) AS waited;')
    execution,_=approved(); data=dict(execution,password=password)
    jar=next(h.cookiejar for h in c['browser'].handlers if hasattr(h,'cookiejar'))
    payload=urllib.parse.urlencode(data).encode(); cookies='; '.join(x.name+'='+x.value for x in jar)
    sock=socket.create_connection(('127.0.0.1',c['port'])); sock.sendall(('POST /admin/updates/execute HTTP/1.1\r\nHost: localhost\r\nCookie: '+cookies+'\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: '+str(len(payload))+'\r\nConnection: close\r\n\r\n').encode()+payload); sock.close()
    time.sleep(1.5)
    check('completed' in next(state_dir.glob('*.result.json')).read_text(),'Browser abort lost completion')
    post('execute',execution,expected=409)

    fresh('CREATE TABLE hosting_killed AS SELECT SLEEP(12) AS waited;')
    execution,_=approved(); data=dict(execution,password=password)
    with concurrent.futures.ThreadPoolExecutor() as pool:
        running=pool.submit(request,'/admin/updates/execute',data)
        for _ in range(100):
            if sql("SELECT COUNT(*) FROM dewawi_schema_migration WHERE status='started'",c['database'])=='1': break
            time.sleep(.03)
        check(list(state_dir.glob('*.accepted.json')),'Acceptance not durable before DDL')
        c['web'].kill(); c['web'].wait(timeout=5)
        try: running.result(timeout=5)
        except Exception: pass
    restart(); check('manual_review' not in get() and 'manuelle Prüfung' in get(),'Interrupted request not shown')
    post('execute',execution,expected=409); post('prepare',expected=409); post('finish',expected=409)
    check(request('/ordinary-write')[0]==503,'Interrupted DDL released maintenance')

    fresh(); post('maintenance'); evidence(); post('prepare')
    chrome=shutil.which('chromium') or shutil.which('google-chrome')
    for locale in ['de_DE','en_US']:
        for width in [1280,390,320]:
            script="""const f=document.querySelector('iframe');let prepared=false;f.onload=()=>setTimeout(async()=>{const d=f.contentDocument,w=f.contentWindow;if(!prepared){const form=d.querySelector('form[action$="/prepare"]');await w.fetch(form.action,{method:'POST',body:new w.FormData(form)});prepared=true;w.location.reload();return}const failures=[];if(d.documentElement.scrollWidth>w.innerWidth)failures.push('overflow');if(d.body.textContent.includes('UPDATES_'))failures.push('translation');const forms=[...d.querySelectorAll('.dw-system-updates form')];if(!forms.length||forms.some(f=>f.dataset.autosave!=='false'))failures.push('autosave');const c=d.querySelector('[name=confirm]');c.focus();if(d.activeElement!==c||c.form.checkValidity())failures.push('confirmation');document.querySelector('#metrics').textContent=JSON.stringify(failures)},300)"""
            (work/'browser.html').write_text('<iframe style="width:'+str(width)+'px;height:2500px" src="/admin/updates/index?locale='+locale+'"></iframe><pre id="metrics"></pre><script>'+script+'</script>')
            result=subprocess.run([chrome,'--headless','--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--user-data-dir='+str(work/('host-chrome-'+locale+str(width))),'--virtual-time-budget=5000','--dump-dom',c['origin']+'/browser.html'],capture_output=True,text=True,timeout=30)
            metrics=re.findall('<pre id="metrics">(.*?)</pre>',result.stdout,re.S)
            check(result.returncode==0 and metrics and json.loads(html.unescape(metrics[-1]))==[],'Hosting browser '+locale+' '+str(width)+': '+str(metrics))
    body=get(); check(not any(s in body for s in ['hosting-fixture',password,str(work),'SELECT *','CREATE TABLE']),'Hosting response leaked secrets')
    print('PASS: '+str(c['checks']-start)+' M2E same-account setup, MariaDB, HTTP, evidence, maintenance, timeout/kill/replay and DE/EN Chromium checks')


if __name__ == '__main__':
    raise SystemExit(subprocess.call(['python3', '-B', str(Path(__file__).with_name('update-worker.py')), '--hosting-only']))
