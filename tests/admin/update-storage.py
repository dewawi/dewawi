"""M2F cases sharing the existing isolated M1/M2E environment."""
import concurrent.futures
import hashlib
import html
import json
import os
from pathlib import Path
import shutil
import re
import subprocess
import tempfile
import time


def run(c, h):
    check, sql, work, source = c['check'], c['sql'], c['work'], c['source']
    request, get, fields, post = c['request'], h['get'], h['fields'], h['post']
    start = c['checks']
    with tempfile.TemporaryDirectory(prefix='dewawi-storage-test-', dir=Path.home()) as private:
        private = Path(private)
        subprocess.run(['php',str(c['ROOT']/'tests/admin/update-storage.php'),str(private)],check=True)
        h['fresh']()
        reports = private/'runtime-reports'; reports.mkdir(mode=0o700)
        c['env']['DEWAWI_GOBD_REPORT_DIR'] = str(reports)
        # Source contains a complete real CLI copy, not a wrapper with a test bypass.
        (source/'tools/update-hosting.php').write_bytes((c['ROOT']/'tools/update-hosting.php').read_bytes())
        for file in [h['config'], source/'configs/operator.ini']:
            text='\n'.join(line for line in file.read_text().splitlines() if not line.startswith(('systemUpdates.statePath','systemUpdates.mode')))+'\n'
            file.write_text(text)
        h['restart'](disabled=True)
        locator = source/'configs/update-storage.json'; ready = source/'configs/update-storage.ready.json'
        state = reports/('dewawi-updates-'+hashlib.sha256(str(source).encode()).hexdigest()[:32])
        check('Ein geeigneter Ablageort' in get(), 'Automatic prerequisites not shown')
        check(not locator.exists() and not state.exists(), 'GET initialized storage')
        form = fields('initialize')
        for change in [{'password':'wrong'},{'csrf':'wrong'},{'confirm':'0'}]:
            data=dict(form,password=h['password'],confirm='1');data.update(change)
            check(request('/admin/updates/initialize',data)[0]==403,'Setup omitted authorization/confirmation')
            check(not locator.exists() and not state.exists(), 'Unauthorized initialization touched storage')
        # Two real browser sessions cannot assign two identities/directories.
        a,b=c['client'](),c['client']()
        forms=[fields('initialize',a),fields('initialize',b)]
        for data in forms:data.update(password=h['password'],confirm='1')
        chrome=shutil.which('chromium') or shutil.which('google-chrome')
        for locale in ['de_DE','en_US']:
            for width in [1280,390,320]:
                submit=locale=='en_US' and width==320
                script='''const f=document.querySelector('iframe');let sent=false;f.onload=()=>setTimeout(()=>{try{const d=f.contentDocument,w=f.contentWindow,failures=[];if(d.documentElement.scrollWidth>w.innerWidth)failures.push('overflow');if(d.body.textContent.includes('UPDATES_'))failures.push('translation');if(sent){if(!d.querySelector('form[action$="/maintenance"]'))failures.push('setup incomplete')}else{const form=d.querySelector('form[action$="/initialize"]');if(!form||form.dataset.autosave!=='false')throw Error('No native setup form');const checkbox=form.querySelector('[name=confirm]');checkbox.focus();if(d.activeElement!==checkbox||form.checkValidity())failures.push('confirmation');checkbox.checked=true;form.querySelector('[name=password]').value=PASSWORD;if(!form.checkValidity())failures.push('password');if(SUBMIT){sent=true;form.requestSubmit();return}}document.querySelector('#metrics').textContent=JSON.stringify(failures)}catch(e){document.querySelector('#metrics').textContent=JSON.stringify([String(e)])}},300);'''
                script=script.replace('PASSWORD',json.dumps(h['password'])).replace('SUBMIT','true' if submit else 'false')
                (work/'browser.html').write_text('<iframe style="width:'+str(width)+'px;height:2200px" src="/admin/updates/index?locale='+locale+'"></iframe><pre id="metrics"></pre><script>'+script+'</script>')
                with tempfile.TemporaryDirectory(prefix='dewawi-m2f-chrome-') as profile:
                    r=subprocess.run([chrome,'--headless','--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--user-data-dir='+profile,'--virtual-time-budget=8000','--dump-dom',c['origin']+'/browser.html'],capture_output=True,text=True,timeout=30)
                metrics=re.findall(r'<pre id="metrics">(.*?)</pre>',r.stdout,re.S)
                check(r.returncode==0 and metrics and json.loads(html.unescape(metrics[-1]))==[],'Setup browser '+locale+' '+str(width)+': '+str(metrics))
        with concurrent.futures.ThreadPoolExecutor() as pool:
            statuses=list(pool.map(lambda p:request('/admin/updates/initialize',p[1],p[0])[0],zip([a,b],forms)))
        check(all(x in [302,409] for x in statuses) and 302 in statuses,'Parallel browser setup failed: '+str(statuses))
        control=json.loads((state/'control.json').read_text())
        check(locator.exists() and ready.exists() and state.is_dir(),'Automatic setup not completed')
        check((state.stat().st_mode&0o777)==0o700 and all((p.stat().st_mode&0o777)==0o600 for p in state.iterdir()),'Private modes wrong')
        check(json.loads(locator.read_text())['control']==control,'Locator identity not bound')
        check('Installationskennung' in get() and 'name="password"' in get(),'Initialized UI missing')
        before={p.name:p.read_bytes() for p in state.iterdir()}
        init={'csrf':fields('maintenance')['csrf'],'password':h['password'],'confirm':'1'}
        post('initialize',init)
        check(before=={p.name:p.read_bytes() for p in state.iterdir()},'Repeated initialization replaced files')

        cli_env=dict(c['env']);cli_env.pop('DEWAWI_GOBD_REPORT_DIR',None);cli_env['TMPDIR']='/var/tmp'
        command=['php',str(source/'tools/update-hosting.php'),'--config='+str(h['config']),'--environment=production','--command=init']
        with concurrent.futures.ThreadPoolExecutor() as pool:
            results=list(pool.map(lambda _:subprocess.run(command,env=cli_env,capture_output=True,text=True),range(2)))
        check(all(r.returncode in [0,1] for r in results) and any(r.returncode==0 for r in results),'Concurrent CLI could not reuse browser setup')
        check(before=={p.name:p.read_bytes() for p in state.iterdir()},'CLI reassigned existing identity')

        def mirror(name):
            target=work/name;target.mkdir();(target/'configs').mkdir()
            shutil.copytree(source/'tools',target/'tools')
            (target/'install').mkdir();shutil.copyfile(source/'install/SqlSplitter.php',target/'install/SqlSplitter.php')
            shutil.copytree(source/'install/config',target/'install/config')
            (target/'library').symlink_to(source/'library',target_is_directory=True)
            config=target/'configs/database.ini'
            config.write_text(h['config'].read_text()+'systemUpdates.documentRoots[]="'+str(target)+'"\n');config.chmod(0o600)
            return target,config

        # Actual competing PHP processes exercise the first-claim race, not just retries.
        target,config=mirror('parallel-source')
        command=['php',str(target/'tools/update-hosting.php'),'--config='+str(config),'--environment=production','--command=init']
        with concurrent.futures.ThreadPoolExecutor() as pool:
            results=list(pool.map(lambda _:subprocess.run(command,env=c['env'],capture_output=True,text=True),range(2)))
        check(any(r.returncode==0 for r in results),'Parallel first CLI initialization failed')
        pinned=json.loads((target/'configs/update-storage.json').read_text())
        check((target/'configs/update-storage.ready.json').exists() and json.loads((Path(pinned['path'])/'control.json').read_text())==pinned['control'],'Parallel initializers published inconsistent identities')
        check(len(list(Path(pinned['path']).glob('control.json')))==1,'More than one control file generated')

        target,config=mirror('interrupted-setup-source')
        probe='''set_include_path($argv[1].'/library'.PATH_SEPARATOR.get_include_path());spl_autoload_register(function($c){if($p=stream_resolve_include_path(str_replace('_','/',$c).'.php'))require_once $p;});require $argv[1].'/tools/updates/Hosting.php';$config=(new Zend_Config_Ini($argv[2],'production'))->toArray();$db=Zend_Db::factory('Pdo_Mysql',$config['resources']['db']['params']);$selection=DEEC_UpdateMaintenance::storage($config['systemUpdates'],$argv[1],true);$id=str_repeat('e',32);$i=new DewawiMigrationInspector($db,$argv[1].'/install/config/migrations');DEEC_UpdateMaintenance::claim($selection,['format'=>1,'installation'=>$id,'database'=>$i->databaseIdentity($id),'topology'=>'single-local-host']);'''
        result=subprocess.run(['php','-r',probe,str(target),str(config)],env=c['env'],capture_output=True,text=True)
        check(result.returncode==0,'Could not simulate a durable pre-directory claim: '+result.stderr)
        partial=json.loads((target/'configs/update-storage.json').read_text());check(not Path(partial['path']).exists(),'Claim unexpectedly created directory')
        result=subprocess.run(['php',str(target/'tools/update-hosting.php'),'--config='+str(config),'--environment=production','--command=init'],env=cli_env,capture_output=True,text=True)
        check(result.returncode==0 and json.loads((Path(partial['path'])/'control.json').read_text())['installation']=='e'*32,'Interrupted initialization replaced the pinned identity')

        # A changed/missing provider environment cannot select another location.
        alternate=private/'other-reports';alternate.mkdir(mode=0o700)
        c['env']['DEWAWI_GOBD_REPORT_DIR']=str(alternate);h['restart']()
        get();check(not list(alternate.iterdir()) and json.loads((state/'control.json').read_text())==control,'Provider change moved state')
        post('maintenance')
        check(request('/ordinary-write')[0]==503,'Auto state did not fence ordinary writes')
        for entry in ['index.php','cron.php']:(source/entry).write_bytes((c['ROOT']/entry).read_bytes())
        result=subprocess.run(['php',str(source/'cron.php')],cwd=source,env=cli_env,capture_output=True,text=True)
        check(result.returncode!=0 and 'Maintenance unavailable' in result.stderr,'Early CLI fence used another location')
        initial=(state/'control.json').read_bytes();post('initialize',{'csrf':fields('prepare')['csrf'],'password':h['password'],'confirm':'1'})
        check((state/'control.json').read_bytes()==initial and (state/'maintenance.json').exists(),'Active maintenance was reset')
        post('finish')

        # Preserve begun/failed jobs and M1 rows; initialize is never repair.
        ident='d'*32
        job={'id':ident,'operator':42,'context':[],'created_at':int(time.time()),'expires':int(time.time())+600,'selected':'1.1.8-1.1.9/001-test.sql'}
        c['write'](state/(ident+'.request.json'),job,0o600);c['write'](state/(ident+'.accepted.json'),job,0o600)
        snapshot={p.name:p.read_bytes() for p in state.iterdir()}
        init={'csrf':fields('maintenance')['csrf'],'password':h['password'],'confirm':'1'}
        post('initialize',init);check(snapshot=={p.name:p.read_bytes() for p in state.iterdir()},'Interrupted job was erased')
        post('maintenance',expected=409)
        sql("UPDATE dewawi_schema_migration SET status='failed',completed_at=NULL",c['database'])
        history=sql('SELECT * FROM dewawi_schema_migration',c['database'])
        post('initialize',init);check(sql('SELECT * FROM dewawi_schema_migration',c['database'])==history,'Initialization repaired M1 history')

        # DB routing changes cannot create a new pin or overwrite this state.
        sql("UPDATE template.user SET password=MD5('test-password')")
        sql('GRANT SELECT ON template.* TO hosting@localhost')
        for file in [h['config'],source/'configs/operator.ini']:
            file.write_text(file.read_text().replace('dbname="'+c['database']+'"','dbname="template"'))
        check('Keine neue Ablage' in get(),'Database identity error not displayed')
        post('initialize',init,expected=409)
        check(snapshot=={p.name:p.read_bytes() for p in state.iterdir()},'DB switch reinitialized state')
        for file in [h['config'],source/'configs/operator.ini']:
            file.write_text(file.read_text().replace('dbname="template"','dbname="'+c['database']+'"'))

        # Restore the previous synthetic M1 data; the file identity must remain unchanged.
        sql("UPDATE dewawi_schema_migration SET status='completed',completed_at=started_at",c['database'])
        get();post('initialize',init)
        check((state/'control.json').read_bytes()==initial,'Database restore generated another installation identity')
        original=locator.read_bytes();locator.write_text('{}')
        check(request('/ordinary-write')[0]==503 and 'Betreiberprüfung' in get(),'Corrupt locator did not fail closed')
        post('initialize',init,expected=409);check(not list(alternate.iterdir()),'Corrupt locator fell back to another provider')
        locator.write_bytes(original)
        gate=state/'gate.lock';gate.rename(state/'retained-gate')
        check('Betreiberprüfung' in get(),'Missing existing lock was not blocked')
        post('initialize',init,expected=409);check(not gate.exists(),'Missing existing lock was automatically repaired')
        (state/'retained-gate').rename(gate)
        state.rename(reports/'retained-state')
        check('Betreiberprüfung' in get() and not state.exists(),'Lost initialized state was recreated by GET')
        post('initialize',init,expected=409)
        check(not state.exists(),'Lost initialized state was recreated by POST')
        check('hosting-fixture' not in get() and str(private) not in get(),'Internal path/credentials leaked')
        c['env'].pop('DEWAWI_GOBD_REPORT_DIR',None)
    print('PASS: '+str(c['checks']-start)+' M2F browser setup, concurrency, CLI, maintenance, identity/restore and retained-state checks')


if __name__=='__main__':
    raise SystemExit(subprocess.call(['python3','-B',str(Path(__file__).with_name('update-worker.py')),'--storage-only']))
