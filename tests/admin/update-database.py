"""M2G: real MariaDB state, controller, maintenance and M1, without private files."""
import concurrent.futures
import html
import hashlib
import json
import re
import runpy
import shutil
import subprocess
import sys
import tempfile
import time
from pathlib import Path


def run(c):
    check, sql, request, source, work = [c[k] for k in ['check', 'sql', 'request', 'source', 'work']]
    Forms = runpy.run_path(str(c['ROOT']/'tests/admin/update-hosting.py'))['Forms']
    start = c['checks']
    config = source/'configs/database.ini'
    shutil.copyfile(c['ROOT']/'tools/update-hosting.php', source/'tools/update-hosting.php')
    sql("CREATE USER dbupdates@localhost IDENTIFIED BY 'db-test'; CREATE USER dbread@localhost IDENTIFIED BY 'db-test'; CREATE USER dbcreate@localhost IDENTIFIED BY 'db-test'")

    def configure(user='dbupdates', extra=''):
        text = '[production]\nsystemUpdates.mode=database\nresources.db.adapter=Pdo_Mysql\n'
        values = {'host':'localhost', 'unix_socket':str(work/'mysql.sock'), 'username':user, 'password':'db-test', 'dbname':c['database'], 'charset':'utf8mb4'}
        text += ''.join('resources.db.params.'+k+'="'+v+'"\n' for k,v in values.items()) + extra
        for p in [config, source/'configs/operator.ini']: p.write_text(text); p.chmod(0o600)

    def fresh(content='CREATE TABLE db_marker (id int PRIMARY KEY);', full=False, baseline=True):
        if config.exists(): config.unlink()
        (source/'configs/operator.ini').write_text('[production]\nsystemUpdates.mode=worker\n')
        c['new_case'](content, full=full, baseline=baseline)
        sql('SET GLOBAL read_only=0; GRANT ALL ON '+c['database']+'.* TO dbupdates@localhost; GRANT SELECT ON '+c['database']+'.* TO dbread@localhost; GRANT SELECT,CREATE ON '+c['database']+'.* TO dbcreate@localhost')
        configure(); c['browser'] = c['client']()

    def get(query='', agent=None):
        status, body, _ = request('/admin/updates/index'+query, agent=agent)
        check(status == 200 and 'UPDATES_' not in body, 'Database overview failed: '+str(status)+' '+body[-1200:])
        return body

    def fields(action, query='', agent=None):
        body = get(query, agent); forms = Forms(body).forms
        check(action in forms, 'Missing '+action+' form: '+body[-2000:])
        return forms[action]

    def post(action, data=None, expected=302, query='', agent=None):
        if data is None: data = fields(action, query, agent)
        status, body, _ = request('/admin/updates/'+action+query, data, agent)
        check(status == expected, action+' returned '+str(status)+' instead of '+str(expected)+': '+body[-1800:])
        return data

    def evidence():
        data = fields('run'); data.update(confirm='1', writers='1', backup='backup 2026-11-22 verified', restore='restore drill 2026-11-22', historical='1', historical_reference='independent operating record 118')
        return data

    mode=subprocess.check_output(['php','-r','require $argv[1]."/library/DEEC/UpdateMaintenance.php"; echo DEEC_UpdateMaintenance::mode([]);',str(source)],text=True)
    check(mode=='database','Unconfigured installation does not select database storage')
    orphan_base=work/'orphan-reports'; orphan_base.mkdir()
    orphan=(orphan_base/('dewawi-updates-'+hashlib.sha256(str(source).encode()).hexdigest()[:32])); orphan.mkdir()
    probe='require $argv[1]."/library/DEEC/UpdateMaintenance.php";putenv("DEWAWI_GOBD_REPORT_DIR=".$argv[2]);try{DEEC_UpdateMaintenance::enter(["mode"=>"database"],false,$argv[1]);}catch(Throwable $e){echo $e->getMessage();}'
    check(subprocess.check_output(['php','-r',probe,str(source),str(orphan_base)],text=True)=='storage_conflict','Orphaned legacy state bypasses early fence')
    fresh()
    for _ in range(2): get()
    check(sql("SHOW TABLES LIKE 'dewawi_system_update'", c['database']) == '', 'GET creates database state')
    check('password' not in get().lower(), 'Password requested')
    for role in ['reader','guest','inactive','deleted','mismatch']:
        for action in ['index','check','run','cancel']:
            status = request('/admin/updates/'+action+'?role='+role+'&direct=1', None if action=='index' else {})[0]
            check(status == 403, 'Unauthorized database action '+role+' '+action)
    for tenant in [7,8,9]: get('?tenant='+str(tenant))
    invalid = fields('check'); invalid['csrf']='forged'; post('check', invalid, 403)
    check(sql("SHOW TABLES LIKE 'dewawi_system_update'", c['database']) == '', 'Invalid CSRF creates state')
    post('check'); data=evidence()
    check(request('/ordinary-write')[0] == 503, 'Early maintenance permits ordinary writer')
    missing = dict(data,backup=''); post('run',missing,409)
    check(not sql("SHOW TABLES LIKE 'db_marker'", c['database']), 'Missing backup permits SQL')
    other = c['client'](); otherdata=fields('check',agent=other); post('run',dict(data,csrf=otherdata['csrf']),403,agent=other)
    saved = fields('cancel'); post('run',data)
    check(sql("SHOW TABLES LIKE 'db_marker'",c['database'])=='db_marker','Catalog SQL not executed')
    check(sql("SELECT status FROM dewawi_schema_migration WHERE migration_id LIKE '%001-test.sql'",c['database'])=='completed','M1 completion absent')
    post('run',data,403)
    check(request('/ordinary-write')[0]==200,'Successful migration leaves maintenance')
    identity=sql("SELECT payload FROM dewawi_system_update WHERE id='control'",c['database'])
    post('check'); check(sql("SELECT payload FROM dewawi_system_update WHERE id='control'",c['database'])==identity,'Check resets identity')
    check(not (source/'configs/update-storage.json').exists(), 'Database mode writes private locator')

    fresh(full=True,baseline=False); get(); post('check')
    check(not sql("SHOW TABLES LIKE 'dewawi_schema_migration'",c['database']),'Inspection registers schema')
    data=evidence(); post('run',dict(data,historical='0'),409); post('run',data)
    check(sql('SELECT migration_id FROM dewawi_schema_migration',c['database'])=='baseline/1.1.8','Registration ran feature SQL')
    post('check'); check('shop-shipping.sql' in get(),'Shipping is not next after registration'); post('cancel')

    # A current fresh schema can contain unrecorded feature objects. Never infer completion.
    fresh(baseline=False); sql('DROP TABLE user,permission,tenant_fixture',c['database'])
    sql((c['ROOT']/'install/config/structure.sql').read_text(),c['database'])
    sql("INSERT INTO user (id,password,clientid,admin,activated,deleted) VALUES (42,'fixture',7,1,1,0)",c['database'])
    c['catalog'](None)
    post('check'); post('run',evidence()); post('check',expected=409)
    check(sql('SELECT migration_id FROM dewawi_schema_migration',c['database'])=='baseline/1.1.8','Fresh schema inferred an executed feature from existing tables')

    fresh('ALTER TABLE tenant_fixture ADD COLUMN extra_field int;'); sql('ALTER TABLE tenant_fixture ADD COLUMN extra_field int',c['database'])
    post('check',expected=409)
    check(sql('SELECT COUNT(*) FROM dewawi_schema_migration',c['database'])=='1','Existing column inferred feature completion')

    fresh(full=True,baseline=False); sql('ALTER TABLE shop DROP COLUMN timezone',c['database'])
    post('check',expected=409)
    check(not sql("SHOW TABLES LIKE 'dewawi_schema_migration'",c['database']),'Incomplete schema registered')

    fresh(); configure('dbread'); post('check',expected=409)
    check(not sql("SHOW TABLES LIKE 'dewawi_system_update'",c['database']),'Read-only account creates state')
    configure('dbcreate'); post('check',expected=409)
    check(not sql("SHOW TABLES LIKE 'dewawi_system_update'",c['database']),'CREATE without metadata write privileges creates partial state')
    configure(); post('check'); data=evidence(); sql('UPDATE user SET admin=0 WHERE id=42',c['database']); post('run',data,403)
    check(not sql("SHOW TABLES LIKE 'db_marker'",c['database']),'Revoked admin executes')

    fresh(); csrf=fields('check'); configure(extra='resources.db.params.persistent=1\n'); post('check',csrf,409)
    check(not sql("SHOW TABLES LIKE 'dewawi_system_update'",c['database']),'Persistent execution connection creates state')

    fresh(); post('check'); data=evidence(); sql("UPDATE dewawi_system_update SET payload=JSON_SET(payload,'$.expires',1) WHERE id LIKE 'job:%'",c['database']); post('run',data,403)
    fresh(); post('check'); data=evidence(); sql("UPDATE dewawi_system_update SET payload=JSON_SET(payload,'$.database','bad') WHERE id='control'",c['database']); post('run',data,409)
    check(not sql("SHOW TABLES LIKE 'db_marker'",c['database']),'Identity mismatch executes')

    fresh(); post('check'); data=evidence()
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        statuses=list(pool.map(lambda _: request('/admin/updates/run',data)[0],range(2)))
    check(sorted(statuses)==[302,403],'Parallel requests execute twice: '+str(statuses))
    check(sql("SELECT COUNT(*) FROM dewawi_system_update WHERE id LIKE 'accepted:%'",c['database'])=='1','Duplicate accepted requests')

    fresh('CREATE TABLE sql_failure (id impossible_type);'); post('check'); data=evidence(); post('run',data,409)
    check(sql("SELECT status FROM dewawi_schema_migration WHERE migration_id LIKE '%001-test.sql'",c['database'])=='failed','SQL failure not recorded')
    check(request('/ordinary-write')[0]==503,'Failed SQL releases maintenance'); post('check',{'csrf':data['csrf']},409)

    fresh(); configure(extra='systemUpdates.statePath="'+str(work/'lost-old-state')+'"\n'); get()
    check('name="csrf"' not in get(),'Existing legacy configuration ignored')

    fresh(); post('check'); data=evidence(); configure(extra='systemUpdates.statePath="'+str(work/'mixed-state')+'"\n')
    post('run',data,409); check(request('/ordinary-write')[0]==503,'Mixed update state bypassed maintenance')
    config.write_text(config.read_text().replace('systemUpdates.mode=database','systemUpdates.mode=shared'))
    mixed=subprocess.run(['php',str(source/'tools/update-hosting.php'),'--config='+str(config),'--environment=production','--command=init'],capture_output=True,text=True)
    check(mixed.returncode!=0 and not (work/'mixed-state').exists(),'Legacy CLI ignored database state')

    fresh()
    legacy_path=work/'legacy-cli-state'
    config.write_text(config.read_text().replace('systemUpdates.mode=database','systemUpdates.mode=shared')+'systemUpdates.statePath="'+str(legacy_path)+'"\n')
    legacy=subprocess.run(['php',str(source/'tools/update-hosting.php'),'--config='+str(config),'--environment=production','--command=init'],capture_output=True,text=True)
    check(legacy.returncode==0 and (legacy_path/'control.json').exists(),'Legacy CLI without database state no longer initializes: '+legacy.stderr)

    fresh(); post('check'); data=evidence()
    sql("UPDATE dewawi_system_update SET payload='{\"format\":1}' WHERE id='control'",c['database'])
    post('run',data,409); check(request('/ordinary-write')[0]==503,'Corrupt control bypassed maintenance')

    fresh(); post('check'); data=evidence()
    sql('REVOKE CREATE ON '+c['database']+'.* FROM dbupdates@localhost')
    post('run',data,409)
    check(request('/ordinary-write')[0]==503,'Missing DDL rights releases maintenance')
    check(sql("SELECT COUNT(*) FROM dewawi_schema_migration WHERE migration_id LIKE '%001-test.sql'",c['database'])=='0','Missing rights started a migration')

    fresh('CREATE TABLE alternative_marker (id int); INSERT INTO alternative_marker VALUES (1);'); post('check'); data=evidence(); post('run',data,409)
    cli=['php',str(source/'tools/update-hosting.php'),'--config='+str(config),'--environment=production','--command=execute','--request='+data['job']]
    result=subprocess.run(cli,capture_output=True,text=True)
    check(result.returncode==0,'Approved technical alternative failed: '+result.stderr)
    check(sql('SELECT id FROM alternative_marker',c['database'])=='1','Technical alternative did not use M1')

    fresh('CREATE TABLE before_kill (id int); INSERT INTO before_kill SELECT SLEEP(20); CREATE TABLE after_kill (id int);'); post('check'); data=evidence(); post('run',data,409)
    cli=['php',str(source/'tools/update-hosting.php'),'--config='+str(config),'--environment=production','--command=execute','--request='+data['job']]
    running=subprocess.Popen(cli,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
    try:
        for _ in range(150):
            if sql("SELECT statement_number FROM dewawi_schema_migration WHERE status='started'",c['database'])=='2': break
            time.sleep(.03)
        else: raise AssertionError('No started marker before interruption')
        check(subprocess.run(cli,capture_output=True,timeout=8).returncode!=0,'Parallel technical executor acquired gate')
    finally:
        running.kill(); running.wait(timeout=10)
        for row in sql('SHOW PROCESSLIST').splitlines():
            cells=row.split('\t')
            if len(cells)>7 and cells[1]=='dbupdates' and cells[7].startswith('INSERT INTO before_kill'): sql('KILL '+cells[0])
    post('run',data,409)
    check(sql("SELECT status FROM dewawi_schema_migration WHERE migration_id LIKE '%001-test.sql'",c['database'])=='started','Interrupted history reset')
    check(not sql("SHOW TABLES LIKE 'after_kill'",c['database']),'Interrupted SQL resumed')
    check(request('/ordinary-write')[0]==503,'Interrupted process releases maintenance')

    # A separate connection holds a shared request lease; maintenance cannot drain it.
    fresh(); post('check'); post('cancel')
    php='set_include_path($argv[1]."/library".PATH_SEPARATOR.get_include_path());spl_autoload_register(function($c){if($p=stream_resolve_include_path(str_replace("_","/",$c).".php"))require_once $p;});$c=(new Zend_Config_Ini($argv[1]."/configs/database.ini","production"))->toArray();require $argv[1]."/library/DEEC/UpdateDatabase.php";DEEC_UpdateDatabase::enter($c["resources"]["db"],false);echo "leased\\n";flush();sleep(8);'
    lease=subprocess.Popen(['php','-r',php,str(source)],stdout=subprocess.PIPE,text=True)
    try:
        check(lease.stdout.readline().strip()=='leased','Shared DB lease not held'); post('check',expected=409)
    finally: lease.terminate(); lease.wait(timeout=10)
    post('check'); post('cancel')

    # A persisted accepted record without a result represents process interruption.
    fresh(); post('check'); data=evidence()
    sql("INSERT INTO dewawi_system_update VALUES ('accepted:"+data['job']+"','{\"at\":1}')",c['database'])
    post('run',data,409); check(request('/ordinary-write')[0]==503,'Interrupted job ignored')

    chrome=shutil.which('chromium') or shutil.which('google-chrome'); check(bool(chrome),'Chromium missing')
    for locale in ['de_DE','en_US']:
        for width in [1280,390,320]:
            fresh()
            script='''const f=document.querySelector('iframe');let stage=0;const e=[];f.onload=()=>setTimeout(()=>{try{const d=f.contentDocument,w=f.contentWindow;if(d.documentElement.scrollWidth>w.innerWidth)e.push('overflow '+stage);if(d.body.textContent.includes('UPDATES_'))e.push('translation');if(d.querySelector('[type=password]'))e.push('password');if([...d.querySelectorAll('details')].some(x=>x.open))e.push('details');if([...d.querySelectorAll('form')].some(x=>x.dataset.autosave!=='false'))e.push('autosave');if(stage===0){const form=d.querySelector('form[action$="/check"]');if(!form)throw Error('check');stage++;form.requestSubmit();return}if(stage===1){const form=d.querySelector('form[action$="/run"]');if(!form)throw Error('approval');if(form.checkValidity())e.push('missing operational confirmation');for(const name of ['backup','restore'])form.querySelector('[name='+name+']').value='verified operating record 2026';for(const name of ['writers','confirm'])form.querySelector('[name='+name+']').checked=true;const checkbox=form.querySelector('[name=confirm]');checkbox.focus();if(d.activeElement!==checkbox||!form.checkValidity())e.push('confirmation');stage++;form.requestSubmit();return}if(!d.querySelector('form[action$="/check"]')||d.querySelector('form[action$="/run"]'))e.push('execution incomplete');document.querySelector('#metrics').textContent=JSON.stringify(e)}catch(error){document.querySelector('#metrics').textContent=JSON.stringify([String(error)])}},300)'''
            (work/'browser.html').write_text('<iframe style="width:'+str(width)+'px;height:2000px;border:0" src="/admin/updates/index?locale='+locale+'"></iframe><pre id="metrics"></pre><script>'+script+'</script>')
            with tempfile.TemporaryDirectory(prefix='dewawi-m2g-chrome-') as profile:
                result=subprocess.run([chrome,'--headless','--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--user-data-dir='+profile,'--virtual-time-budget=8000','--dump-dom',c['origin']+'/browser.html'],capture_output=True,text=True,timeout=30)
            metrics=re.findall(r'<pre id="metrics">(.*?)</pre>',result.stdout,re.S)
            check(result.returncode==0 and metrics and json.loads(html.unescape(metrics[-1]))==[],'Browser '+locale+' '+str(width)+': '+str(metrics))
            check(sql('SELECT status FROM dewawi_schema_migration WHERE migration_id LIKE "%001-test.sql"',c['database'])=='completed','Native browser did not execute catalog migration')
    print('PASS: '+str(c['checks']-start)+' M2G database setup, approval, M1, maintenance, rights, legacy and browser checks')


if __name__ == '__main__':
    raise SystemExit(subprocess.call([sys.executable,'-B',str(Path(__file__).with_name('update-worker.py')),'--database-only']))
