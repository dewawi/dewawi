#!/usr/bin/env python3
"""Real CLI, isolated MariaDB and synthetic SQL only; never opens an existing DB."""
from pathlib import Path
import hashlib
import http.client
import json
import os
import shutil
import socket
import subprocess
import tempfile
import time

ROOT = Path(__file__).resolve().parents[2]
CHECKS = 0


def check(condition, message):
    global CHECKS
    if not condition:
        raise AssertionError(message)
    CHECKS += 1


with tempfile.TemporaryDirectory(prefix='dewawi-migrations-') as directory:
    work = Path(directory)
    server = web = None
    children = []
    try:
        subprocess.run(['mariadb-install-db', '--no-defaults', '--datadir='+str(work/'data'), '--auth-root-authentication-method=normal', '--skip-test-db'], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        db_socket = str(work/'mysql.sock')
        server = subprocess.Popen(['mariadbd', '--no-defaults', '--datadir='+str(work/'data'), '--socket='+db_socket, '--skip-networking', '--pid-file='+str(work/'server.pid'), '--log-error='+str(work/'server.log'), '--innodb-buffer-pool-size=32M', '--innodb-log-file-size=8M'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

        def sql(query, database=None, success=True):
            command = ['mariadb', '--no-defaults', '--socket='+db_socket, '-uroot', '--batch', '--skip-column-names']
            if database:
                command.append(database)
            result = subprocess.run(command, input=query, text=True, capture_output=True)
            if success and result.returncode:
                raise AssertionError(result.stderr)
            return result

        for attempt in range(100):
            if server.poll() is not None:
                raise RuntimeError((work/'server.log').read_text())
            if sql('SELECT 1', success=False).returncode == 0:
                break
            time.sleep(.1)
        else:
            raise RuntimeError('Isolated server not ready')

        mirror = work/'source'
        for relative in ['tools/migrations', 'install/config/migrations']:
            (mirror/relative).mkdir(parents=True)
        for relative in ['tools/migrate.php', 'tools/migrations/Migrator.php', 'tools/migrations/baseline-1.1.8.json']:
            shutil.copyfile(ROOT/relative, mirror/relative)
        (mirror/'library').symlink_to(ROOT/'library', target_is_directory=True)
        (mirror/'install/helper.php').symlink_to(ROOT/'install/helper.php')
        registry_root = mirror/'install/config/migrations'
        baseline_reference = json.loads((ROOT/'tools/migrations/baseline-1.1.8.json').read_text())
        original_tables = list(baseline_reference['tables'])
        check(len(original_tables) == 124, 'Frozen release table count')
        for filename, field in [('structure.sql', 'source_structure_sha256'), ('update-1.1.7-1.1.8.sql', 'source_update_sha256')]:
            check(hashlib.sha256((ROOT/'install/config'/filename).read_bytes()).hexdigest() == baseline_reference[field], 'Released SQL changed: '+filename)

        def catalog(transitions=None):
            for entry in registry_root.iterdir():
                if entry.is_dir():
                    shutil.rmtree(entry)
                else:
                    entry.unlink()
            registry = []
            for from_version, to_version, migrations in transitions or []:
                transition = from_version+'-'+to_version
                (registry_root/transition).mkdir()
                entries = []
                for filename, content, depends in migrations:
                    (registry_root/transition/filename).write_text(content)
                    entries.append({'file': filename, 'depends': depends})
                registry.append({'from': from_version, 'to': to_version, 'migrations': entries})
            (registry_root/'registry.json').write_text(json.dumps({'transitions': registry}))

        def create(database, historical=False):
            sql('CREATE DATABASE `'+database+'`')
            if historical:
                structure = subprocess.run(['git', 'show', '1.1.7:install/config/structure.sql'], cwd=ROOT, check=True, capture_output=True, text=True).stdout
                sql(structure, database)
                sql((ROOT/'install/config/update-1.1.7-1.1.8.sql').read_text(), database)
            else:
                sql((ROOT/'install/config/structure.sql').read_text(), database)
                sql((ROOT/'install/config/data.sql').read_text(), database)
            config = work/(database+'.ini')
            config.write_text('[test]\nresources.db.adapter = Pdo_Mysql\nresources.db.params.host = localhost\nresources.db.params.unix_socket = "'+db_socket+'"\nresources.db.params.username = root\nresources.db.params.password = ""\nresources.db.params.dbname = '+database+'\n')
            config.chmod(0o600)
            return config

        def cli(database=None, command=None, extra=(), success=True, asynchronous=False):
            args = ['php', str(mirror/'tools/migrate.php')]
            if database:
                args += ['--config='+str(work/(database+'.ini')), '--environment=test']
            if command:
                args.append('--command='+command)
            args += list(extra)
            if asynchronous:
                child = subprocess.Popen(args, text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                children.append(child)
                return child
            result = subprocess.run(args, text=True, capture_output=True)
            check((result.returncode == 0) == success, 'CLI outcome: '+str(args[2:])+'\n'+result.stdout+result.stderr)
            return result

        def no_history(database):
            return sql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dewawi_schema_migration'", database).stdout.strip() == '0'

        def count_history(database):
            return int(sql('SELECT COUNT(*) FROM dewawi_schema_migration', database).stdout.strip())

        def wait_for(query, database, expected):
            for _ in range(100):
                if sql(query, database).stdout.strip() == expected:
                    return
                time.sleep(.05)
            raise AssertionError('Timed out waiting for '+expected)

        catalog()
        create('fresh')
        snapshot = subprocess.run(['php', str(ROOT/'tests/install/migrations/schema.php'), db_socket, 'fresh'], check=True, capture_output=True, text=True)
        check(json.loads(snapshot.stdout) == baseline_reference['tables'], 'Frozen reference matches full fresh schema')
        check(json.loads(cli('fresh').stdout)['baseline'] == 'verified_not_registered', 'Default is read-only status')
        cli('fresh', 'plan')
        check(no_history('fresh'), 'Read-only status/plan never creates history')
        sql("CREATE USER 'm1_reader'@'localhost' IDENTIFIED BY 'synthetic_reader'; GRANT SELECT ON fresh.* TO 'm1_reader'@'localhost'")
        (work/'reader.ini').write_text((work/'fresh.ini').read_text().replace('username = root', 'username = m1_reader').replace('password = ""', 'password = synthetic_reader'))
        cli('reader')
        cli('reader', 'plan')
        cli('reader', 'baseline', success=False)
        check(no_history('fresh'), 'SELECT-only account can plan but cannot register baseline')
        check('explicit_baseline_registration_required' in cli('fresh', 'apply', success=False).stderr, 'Apply requires explicit baseline')
        cli('fresh', 'baseline')
        cli('fresh', 'baseline')
        check(count_history('fresh') == 1, 'Explicit baseline is registered exactly once')
        create('upgraded', historical=True)
        check(sql("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='address' AND INDEX_NAME='street'", 'upgraded').stdout.strip() == '1', 'Historical additional index exists')
        cli('upgraded', 'baseline')
        print('PASS: frozen baseline, fresh install, full historical upgrade and inherited extra index', flush=True)

        create('partial')
        sql('CREATE DATABASE m1_external; CREATE TABLE m1_external.calendarevent LIKE partial.calendarevent')
        partial_cases = [
            ('ALTER TABLE shop DROP COLUMN albisfeenet', 'ALTER TABLE shop ADD albisfeenet decimal(12,2) DEFAULT NULL'),
            ('ALTER TABLE shoporder DROP INDEX clientid_status_shopid_deleted', 'ALTER TABLE shoporder ADD INDEX clientid_status_shopid_deleted (clientid,status,shopid(64),deleted)'),
            ('ALTER TABLE calendarparticipant DROP FOREIGN KEY calendarparticipant_event', 'ALTER TABLE calendarparticipant ADD CONSTRAINT calendarparticipant_event FOREIGN KEY(eventid) REFERENCES calendarevent(id) ON DELETE RESTRICT ON UPDATE RESTRICT'),
            ('ALTER TABLE calendarparticipant DROP FOREIGN KEY calendarparticipant_event; ALTER TABLE calendarparticipant ADD CONSTRAINT calendarparticipant_event FOREIGN KEY(eventid) REFERENCES m1_external.calendarevent(id) ON DELETE RESTRICT ON UPDATE RESTRICT', 'ALTER TABLE calendarparticipant DROP FOREIGN KEY calendarparticipant_event; ALTER TABLE calendarparticipant ADD CONSTRAINT calendarparticipant_event FOREIGN KEY(eventid) REFERENCES calendarevent(id) ON DELETE RESTRICT ON UPDATE RESTRICT'),
            ('ALTER TABLE calendarevent ALTER visibility SET DEFAULT \'public\'', 'ALTER TABLE calendarevent ALTER visibility SET DEFAULT \'private\''),
            ('ALTER TABLE shop MODIFY albisthreshold decimal(10,2) NOT NULL DEFAULT 5000.00', 'ALTER TABLE shop MODIFY albisthreshold decimal(12,2) NOT NULL DEFAULT 5000.00'),
            ('RENAME TABLE shoporderhistory TO hidden_history', 'RENAME TABLE hidden_history TO shoporderhistory'),
        ]
        for change, restore in partial_cases:
            sql(change, 'partial')
            check('incomplete_1.1.8_schema' in cli('partial', 'baseline', success=False).stderr, 'Missing or modified required schema is rejected')
            check(no_history('partial'), 'Invalid baseline never creates history')
            sql(restore, 'partial')
        cli('partial', 'baseline')
        print('PASS: partial schema, missing table/column/index/FK, changed default/type rejected', flush=True)

        first = ('001-first.sql', 'CREATE TABLE m1_first (id int PRIMARY KEY, note varchar(80));\nINSERT INTO m1_first VALUES (1, \'semi;colon -- text # still literal\');\n', [])
        second = ('002-second.sql', 'ALTER TABLE m1_first ADD marker int NOT NULL DEFAULT 7;\nCREATE TABLE m1_second (id int PRIMARY KEY);\n', [])
        catalog([('1.1.8', '1.1.9', [first, second])])
        sql('CREATE TABLE m1_tenant_fixture (id int PRIMARY KEY, clientid int, balance decimal(12,2)); INSERT INTO m1_tenant_fixture VALUES (1,101,12.34),(2,202,56.78)', 'fresh')
        def tenant_dump():
            result = subprocess.run(['mariadb-dump', '--no-defaults', '--socket='+db_socket, '-uroot', '--no-create-info', '--skip-comments', '--compact', '--order-by-primary', 'fresh']+original_tables+['m1_tenant_fixture'], check=True, capture_output=True)
            return hashlib.sha256(result.stdout).hexdigest()
        before_data = tenant_dump()
        pending = json.loads(cli('fresh', 'plan').stdout)['pending']
        check(pending == ['1.1.8-1.1.9/001-first.sql', '1.1.8-1.1.9/002-second.sql'], 'Deterministic numeric order')
        check(count_history('fresh') == 1 and sql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='m1_first'", 'fresh').stdout.strip() == '0', 'Plan never writes history or executes migration SQL')
        cli('fresh', 'apply')
        history_before = sql('SELECT * FROM dewawi_schema_migration ORDER BY migration_id', 'fresh').stdout
        cli('fresh', 'apply')
        check(sql('SELECT * FROM dewawi_schema_migration ORDER BY migration_id', 'fresh').stdout == history_before, 'Repeated apply never updates completed history')
        check(count_history('fresh') == 3 and sql('SELECT marker FROM m1_first', 'fresh').stdout.strip() == '7', 'Multiple DDL/DML migrations completed once')
        check(sql('SELECT note FROM m1_first', 'fresh').stdout.strip() == 'semi;colon -- text # still literal', 'Splitter preserves SQL strings')
        check(tenant_dump() == before_data, 'All existing tables and two synthetic tenants unchanged')
        print('PASS: ordered DDL/DML, repeated apply, literal splitting and tenant data preservation', flush=True)

        first_path = registry_root/'1.1.8-1.1.9/001-first.sql'
        first_path.write_text(first[1]+'\n-- changed\n')
        check('migration_checksum_mismatch' in cli('fresh', 'apply', success=False).stderr, 'Changed executed SQL rejected')
        first_path.write_text(first[1])
        first_path.unlink()
        check('missing_migration' in cli('fresh', 'plan', success=False).stderr, 'Missing file rejected')
        first_path.write_text(first[1])
        catalog([('1.1.8', '1.1.9', [first])])
        check('missing_historical_migration' in cli('fresh', 'apply', success=False).stderr, 'Removing executed migration from registry rejected')
        catalog([('1.1.8', '1.1.9', [first, (second[0], second[1], ['nonexistent'])])])
        check('missing_or_forward_dependency' in cli('fresh', 'apply', success=False).stderr, 'Missing dependency rejected')
        catalog([('1.1.8', '1.1.9', [first, (second[0], second[1], ['baseline/1.1.8'])])])
        check('migration_checksum_mismatch' in cli('fresh', 'apply', success=False).stderr, 'Changed dependency definition rejected')
        catalog([('1.1.8', '1.1.9', [first, ('003-gap.sql', second[1], [])])])
        check('invalid_migration_sequence' in cli('fresh', 'plan', success=False).stderr, 'Number gap rejected')
        catalog([('1.1.9', '1.1.10', [first])])
        check('invalid_transition_chain' in cli('fresh', 'plan', success=False).stderr, 'Missing version transition rejected')
        print('PASS: checksums, missing files/history/dependencies, changed definitions and sequence gaps', flush=True)

        third = ('001-third.sql', 'CREATE TABLE m1_third (id int PRIMARY KEY);', [])
        catalog([('1.1.8', '1.1.9', [first, second]), ('1.1.9', '1.1.10', [third])])
        bundle = work/'update-1.1.8-1.1.9.sql'
        cli(command='bundle', extra=['--transition=1.1.8-1.1.9', '--output='+str(bundle)])
        cli(command='bundle', extra=['--transition=1.1.8-1.1.9', '--output='+str(bundle)], success=False)
        cli('fresh', 'apply', ['--bundle='+str(bundle)])
        check(count_history('fresh') == 3, 'Bundle after individual migrations skips same history')
        cli('fresh', 'apply')
        check(count_history('fresh') == 4, 'Cross-transition successor executes after previous release')
        create('bundled')
        cli('bundled', 'baseline')
        raw = sql(bundle.read_text(), 'bundled', success=False)
        check(raw.returncode != 0 and 'DEWAWI: use tools/migrate.php' in raw.stderr, 'Direct SQL import stops at guard')
        check(count_history('bundled') == 1, 'Direct SQL import never creates migration history')
        cli('bundled', 'apply', ['--bundle='+str(bundle)])
        check(count_history('bundled') == 3, 'Bundle executes only selected release')
        cli('bundled', 'apply')
        check(count_history('bundled') == 4, 'Individual apply after bundle shares history')
        bundle.write_text(bundle.read_text()+'\n-- tampered\n')
        check('bundle_does_not_match_catalog' in cli('bundled', 'apply', ['--bundle='+str(bundle)], success=False).stderr, 'Tampered consolidated SQL rejected')
        later_bundle = work/'update-1.1.9-1.1.10.sql'
        cli(command='bundle', extra=['--transition=1.1.9-1.1.10', '--output='+str(later_bundle)])
        check('bundle_predecessor_not_applied' in cli('partial', 'apply', ['--bundle='+str(later_bundle)], success=False).stderr, 'Bundle requires earlier transitions')
        print('PASS: cross-transition ordering, guarded consolidated updates and shared history in both directions', flush=True)

        failing = ('001-failure.sql', 'CREATE TABLE m1_partial (id int PRIMARY KEY);\nINSERT INTO m1_partial VALUES (1);\nALTER TABLE m1_missing ADD field int;\nCREATE TABLE m1_never (id int);', [])
        catalog([('1.1.8', '1.1.9', [failing])])
        create('failed')
        cli('failed', 'baseline')
        error = cli('failed', 'apply', success=False)
        check('statement 3 sqlstate_' in error.stderr, 'SQL failure contains migration and statement, no raw SQL')
        check(sql('SELECT status,statement_number,completed_at,error_code FROM dewawi_schema_migration WHERE migration_id LIKE \'1.1.8-%\'', 'failed').stdout.strip().startswith('failed\t3\tNULL\tsqlstate_'), 'Durable failed status with statement number')
        check(sql('SELECT COUNT(*) FROM m1_partial', 'failed').stdout.strip() == '1', 'Earlier DDL and DML remain after failure')
        check(sql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='m1_never'", 'failed').stdout.strip() == '0', 'Failure prevents later SQL')
        check('unresolved_migration' in cli('failed', 'apply', success=False).stderr, 'Failed migration never retried')
        check('unresolved_migration' in cli('failed', 'baseline', success=False).stderr, 'Baseline cannot hide failed history')
        print('PASS: partial SQL persists, failed state blocks retry and baseline cannot repair it', flush=True)

        slow = ('001-slow.sql', 'CREATE TABLE m1_before_interrupt (id int PRIMARY KEY);\nINSERT INTO m1_before_interrupt SELECT SLEEP(20);\nCREATE TABLE m1_after_interrupt (id int);', [])
        catalog([('1.1.8', '1.1.9', [slow])])
        create('concurrent')
        cli('concurrent', 'baseline')
        running = cli('concurrent', 'apply', asynchronous=True)
        wait_for("SELECT CONCAT(status,':',statement_number) FROM dewawi_schema_migration WHERE migration_id LIKE '1.1.8-%'", 'concurrent', 'started:2')
        check('migration_lock_busy' in cli('concurrent', 'apply', success=False).stderr, 'Second writer cannot acquire database lock')
        check('migration_lock_busy' in cli('concurrent', 'baseline', success=False).stderr, 'Baseline shares updater lock')
        running.kill()
        running.communicate(timeout=5)
        # Kill the server-side statement too: a dead client may leave SLEEP running until SQL returns.
        process_id = sql("SELECT ID FROM information_schema.PROCESSLIST WHERE DB='concurrent' AND INFO LIKE 'INSERT INTO m1_before_interrupt%'", 'concurrent').stdout.strip()
        if process_id:
            sql('KILL CONNECTION '+process_id)
        wait_for("SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB='concurrent' AND INFO LIKE 'INSERT INTO m1_before_interrupt%'", 'concurrent', '0')
        check('unresolved_migration' in cli('concurrent', 'apply', success=False).stderr, 'Interrupted migration blocks rerun after lock release')
        check(sql("SELECT status,statement_number FROM dewawi_schema_migration WHERE migration_id LIKE '1.1.8-%'", 'concurrent').stdout.strip() == 'started\t2', 'Interrupted state stays started')
        check(sql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='m1_before_interrupt'", 'concurrent').stdout.strip() == '1', 'DDL before interruption remains committed')
        check(sql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='m1_after_interrupt'", 'concurrent').stdout.strip() == '0', 'DDL after interruption never ran')
        print('PASS: concurrent writer/baseline locks, killed process and interrupted DDL never repeated', flush=True)

        catalog()
        create('broken_history')
        cli('broken_history', 'baseline')
        sql('ALTER TABLE dewawi_schema_migration DROP PRIMARY KEY', 'broken_history')
        check('invalid_history_primary_key' in cli('broken_history', 'apply', success=False).stderr, 'Malformed history is rejected')
        create('order_gap')
        cli('order_gap', 'baseline')
        catalog([('1.1.8', '1.1.9', [first, second])])
        cli('order_gap', 'apply')
        sql("UPDATE dewawi_schema_migration SET statement_number=0 WHERE migration_id LIKE '%001-first.sql'", 'order_gap')
        check('ambiguous_completed_history' in cli('order_gap', 'apply', success=False).stderr, 'Completed history with wrong statement count is rejected')
        sql("UPDATE dewawi_schema_migration SET statement_number=2 WHERE migration_id LIKE '%001-first.sql'", 'order_gap')
        sql("DELETE FROM dewawi_schema_migration WHERE migration_id LIKE '%001-first.sql'", 'order_gap')
        check('history_order_gap' in cli('order_gap', 'apply', success=False).stderr, 'Applied successor without predecessor is rejected')
        catalog([('1.1.8', '1.1.9', [('001-unsafe.sql', 'SET autocommit=0; CREATE TABLE m1_unsafe(id int);', [])])])
        check('unsupported_migration_statement' in cli('partial', 'apply', success=False).stderr, 'Session manipulation rejected before execution')
        catalog([('1.1.8', '1.1.9', [('001-unsafe.sql', '/*!50000 CREATE TABLE m1_unsafe(id int) */;', [])])])
        check('unsupported_sql_directive' in cli('partial', 'apply', success=False).stderr, 'Executable comments rejected')
        catalog([('1.1.8', '1.1.9', [('001-unsafe.sql', '/*M!100100 CREATE TABLE m1_unsafe(id int) */;', [])])])
        check('unsupported_sql_directive' in cli('partial', 'apply', success=False).stderr, 'MariaDB executable comments rejected')
        catalog()
        secret = 'm1_secret_password_never_print'
        bad_config = work/'bad.ini'
        bad_config.write_text('[test]\nresources.db.adapter=Pdo_Mysql\nresources.db.params.unix_socket="'+db_socket+'"\nresources.db.params.host=localhost\nresources.db.params.username=nonexistent\nresources.db.params.password='+secret+'\nresources.db.params.dbname=fresh\n')
        bad = cli(extra=['--config='+str(bad_config), '--environment=test'], success=False)
        check(secret not in bad.stderr+bad.stdout and 'nonexistent' not in bad.stderr+bad.stdout, 'Connection failure does not expose credentials')
        print('PASS: invalid history, unsafe SQL, order gaps and credential-safe errors', flush=True)

        # Built-in HTTP server bypasses Apache rules and proves the PHP guards independently.
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        web_log = open(work/'http.log', 'w')
        web = subprocess.Popen(['php', '-S', '127.0.0.1:'+str(port), '-t', str(mirror)], stdout=web_log, stderr=web_log)
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.2):
                    break
            except OSError:
                time.sleep(.05)
        for method in ['GET', 'POST']:
            for path in ['/tools/migrate.php?command=apply', '/tools/migrations/Migrator.php']:
                connection = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
                connection.request(method, path)
                response = connection.getresponse()
                body = response.read()
                check(response.status == 404 and body == b'', 'Direct HTTP execution is blocked: '+method+' '+path)
                connection.close()
        check(tenant_dump() == before_data, 'Tenant data unchanged after entire test run')
        print('PASS: HTTP guards; '+str(CHECKS)+' checks on '+sql('SELECT VERSION()').stdout.strip(), flush=True)
    finally:
        for child in children:
            if child.poll() is None:
                child.kill()
                child.communicate(timeout=5)
        if web is not None:
            web.terminate()
            web.wait(timeout=10)
        if server is not None:
            server.terminate()
            server.wait(timeout=20)
