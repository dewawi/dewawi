"""M2D cases using the disposable environment in update-worker.py."""
import concurrent.futures
import hashlib
import json
import time


def run(c):
    check, new_case, driver = c['check'], c['new_case'], c['driver']
    approve, release, request, write = c['approve'], c['release_request'], c['request'], c['write']
    work = c['work']
    shipping = '1.1.8-1.1.9/001-shop-shipping.sql'

    def sql(query):
        return c['sql'](query, c['database'])

    def rows():
        return int(sql('SELECT COUNT(*) FROM dewawi_schema_migration')) if sql("SHOW TABLES LIKE 'dewawi_schema_migration'") else 0

    def fresh():
        state = new_case(full=True, baseline=False)
        check(state['plan']['operation'] == 'baseline' and state['plan']['pending'] == ['baseline/1.1.8'], 'Registration mixed with feature migrations')
        check(len(state['plan']['schema']) == 64 and not state['plan']['history_table'], 'Unverified baseline snapshot')
        check(not sql("SHOW TABLES LIKE 'dewawi_schema_migration'"), 'Read-only worker publication created history')
        return state

    def historical(req, change=None):
        plan = json.loads((work/('queue/private/'+req['id']+'.plan.json')).read_text())
        captured = work/('queue/private/'+req['id']+'.request.json')
        proof = {'format': 1, 'request': req['id'], 'request_sha256': hashlib.sha256(captured.read_bytes()).hexdigest(),
                 'plan': req['digest'], 'installation': c['policy']['installation'], 'database': c['policy']['database'],
                 'operator': req['operator'], 'scope': 'historical-data-through-1.1.8', 'baseline_sha256': plan['baseline'],
                 'reference': 'historical-upgrade-audit-001', 'report_sha256': 'c'*64, 'confirmed_by': 'independent-data-auditor',
                 'checked_at': int(time.time())}
        if change:
            change(proof)
        file = work/('queue/evidence/'+req['id']+'.historical.json')
        write(file, proof)
        return file

    state = fresh()
    status, body, _ = request()
    check(status == 200 and 'Datenbankbasis prüfen und registrieren' in body and 'root-kontrollierten Nachweis' in body, 'Registration/evidence explanation missing')
    req, old_form = approve(query='?role=tenantadmin&tenant=9')
    check(req['operator'] == 43, 'Cross-tenant administrator could not confirm registration')
    check(rows() == 0 and not sql("SHOW TABLES LIKE 'dewawi_schema_migration'"), 'HTTP registered baseline')
    check(driver()['job']['code'] == 'evidence_required' and rows() == 0, 'Registration without backup release')
    release(req)
    waiting = driver()
    check(waiting['job']['code'] == 'historical_required' and waiting['job']['operation'] == 'baseline', 'Historical data was inferred from schema')
    check(not sql("SHOW TABLES LIKE 'dewawi_schema_migration'"), 'Missing historical evidence created history')
    historical(req)
    result = driver()
    check(result['job']['status'] == 'completed' and result['job']['code'] == 'baseline_registered' and result['job']['operation'] == 'baseline', 'Baseline result ambiguous')
    check(sql('SELECT SUM(balance) FROM tenant_fixture') == '300', 'Baseline registration changed tenant data')
    check(rows() == 1 and sql('SELECT migration_id,status,statement_number FROM dewawi_schema_migration') == 'baseline/1.1.8\tcompleted\t0', 'M1 baseline registration incorrect')
    check(result['plan']['pending'] == [shipping] and result['plan'].get('operation', 'migrate') == 'migrate', 'Shipping is not the next separate step')
    check(not sql("SHOW TABLES LIKE 'shopshippingclass'"), 'Registration executed shipping SQL')
    check('historical' in json.loads((work/('queue/private/'+req['id']+'.release.json')).read_text()), 'Historical audit evidence was not retained')
    original = sql('SELECT * FROM dewawi_schema_migration')
    driver(); driver()
    check(sql('SELECT * FROM dewawi_schema_migration') == original, 'Repeated worker changed completed registration')
    check(request('/admin/updates/approve', old_form)[0] == 403, 'Consumed registration confirmation replayed')
    status, body, _ = request()
    check(status == 200 and 'Vollständige 1.1.8-Basis in M1 registriert' in body and shipping in body, 'Registered status or pending shipping missing')
    next_req, _ = approve()
    check(driver()['job']['code'] == 'evidence_required' and rows() == 1, 'Baseline release reused for shipping')
    release(next_req)
    check(driver()['job']['status'] == 'completed' and rows() == 2 and sql("SHOW TABLES LIKE 'shopshippingclass'"), 'Separate shipping approval failed')

    for ddl in ['DROP TABLE country', 'ALTER TABLE address DROP COLUMN geoupdated', 'ALTER TABLE user MODIFY admin tinyint NOT NULL DEFAULT 1']:
        fresh(); sql(ddl)
        state = driver('publish')
        check(state['plan'] is None and state['issues'] == ['baseline_incomplete'] and rows() == 0, 'Incomplete schema certified: '+ddl)
        check('name="csrf"' not in request()[1], 'Incomplete schema offered approval')

    for mutation in [lambda p:p.update(scope='unknown'), lambda p:p.update(baseline_sha256='0'*64),
                     lambda p:p.update(report_sha256=''), lambda p:p.update(confirmed_by=''),
                     lambda p:p.update(checked_at=0), lambda p:p.update(database='0'*64)]:
        fresh(); req, _ = approve(); driver(); release(req); historical(req, mutation)
        result = driver()
        check(result['job']['status'] == 'rejected' and result['job']['code'] == 'historical_invalid' and rows() == 0, 'Invalid historical audit accepted')

    fresh(); req, _ = approve(); driver(); release(req); evidence = historical(req)
    evidence.rename(work/'historical-saved.json'); evidence.symlink_to(work/'historical-saved.json')
    check(driver()['job']['status'] == 'rejected' and rows() == 0, 'Symlink historical evidence accepted')
    fresh(); req, _ = approve(); driver(); release(req); historical(req).chmod(0o660)
    check(driver()['job']['status'] == 'rejected' and rows() == 0, 'Writable historical evidence accepted')

    for change in ['schema', 'history', 'catalog', 'identity', 'expired']:
        fresh(); req, _ = approve(); driver(); release(req); historical(req)
        if change == 'schema': sql('ALTER TABLE user ADD baseline_extra int')
        if change == 'history': sql('CREATE TABLE dewawi_schema_migration LIKE template.dewawi_schema_migration')
        if change == 'catalog':
            file = c['registry'].parent/'1.1.8-1.1.9/001-shop-shipping.sql'
            file.write_bytes(file.read_bytes()+b'\n-- changed after baseline approval\n')
        if change == 'identity':
            c['policy']['database'] = 'd'*64; write(work/'queue/control.json', c['policy'], 0o644)
        if change == 'expired':
            state = json.loads((work/'queue/published/current.json').read_text()); state['expires_at'] = int(time.time())-1
            write(work/'queue/published/current.json', state)
        check(driver()['job']['status'] == 'rejected' and rows() == 0, 'Stale/mismatched baseline approval executed: '+change)

    for permission in ['admin=0', 'activated=0', 'deleted=1']:
        fresh(); req, _ = approve(); driver(); release(req); historical(req); sql('UPDATE user SET '+permission+' WHERE id=42')
        check(driver()['job']['code'] == 'operator_denied' and rows() == 0, 'Baseline ignored revoked administrator: '+permission)

    new_case(full=True)
    state = driver('publish')
    check(state['plan'].get('operation', 'migrate') == 'migrate' and state['plan']['pending'] == [shipping], 'Registered baseline offered re-registration')
    check('id="updates-baseline-registration"' not in request()[1], 'Registered baseline UI offers another registration')

    fresh(); first, second = c['client'](), c['client']()
    a, b = c['form'](first), c['form'](second)
    with concurrent.futures.ThreadPoolExecutor() as pool:
        results = list(pool.map(lambda pair:request('/admin/updates/approve', pair[1], pair[0])[0], [(first, a), (second, b)]))
    check(sorted(results) == [302, 409], 'Concurrent baseline confirmations were both accepted')
    req = json.loads((work/'queue/inbox/request.json').read_text())
    driver(); release(req); historical(req)
    # Pause the actual process after history-table creation but before M1's start INSERT.
    signal = work/'before-start.signal'
    if signal.exists(): signal.unlink()
    process = driver(asynchronous=True, paused=True)
    for _ in range(200):
        if signal.exists(): break
        time.sleep(.03)
    check(signal.exists(), 'Baseline worker did not reach durable acceptance')
    check(driver(success=False).returncode != 0, 'Parallel baseline worker acquired queue')
    process.kill(); process.wait(timeout=5)
    check(driver()['job']['status'] == 'manual_review' and rows() == 0 and sql("SHOW TABLES LIKE 'dewawi_schema_migration'"), 'Interrupted empty-history registration resumed')

    fresh(); req, _ = approve(); driver(); release(req); historical(req)
    process = driver(asynchronous=True, before_complete=True)
    signal = work/'before-complete.signal'
    for _ in range(200):
        if signal.exists(): break
        time.sleep(.03)
    check(signal.exists() and sql('SELECT status FROM dewawi_schema_migration') == 'started', 'Baseline start marker was not durable')
    process.kill(); process.wait(timeout=5)
    result = driver()
    check(result['job']['status'] == 'manual_review' and result['job']['operation'] == 'baseline' and sql('SELECT status FROM dewawi_schema_migration') == 'started', 'Interrupted baseline was silently completed')
    check(driver()['plan'] is None, 'Unresolved registration offered further execution')


if __name__ == '__main__':
    raise SystemExit('Run python3 -B tests/admin/update-worker.py (isolated M2B/M2C/M2D environment)')
