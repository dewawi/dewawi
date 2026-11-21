"""C4A.2: frozen-release upgrade, development fresh schema and shipping fixtures only."""
from pathlib import Path
import json, os, socket, subprocess, tempfile, time
ROOT = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='dewawi-migrations-shipping-') as directory:
    work = Path(directory)
    server = None
    try:
        subprocess.run(['mariadb-install-db', '--no-defaults', '--datadir='+str(work/'data'), '--auth-root-authentication-method=normal', '--skip-test-db'], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        db_socket = str(work/'mysql.sock')
        server = subprocess.Popen(['mariadbd', '--no-defaults', '--datadir='+str(work/'data'), '--socket='+db_socket, '--skip-networking', '--pid-file='+str(work/'pid'), '--log-error='+str(work/'db.log'), '--innodb-buffer-pool-size=32M', '--innodb-log-file-size=8M'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        def sql(query, db=None):
            return subprocess.run(['mariadb', '--no-defaults', '--socket='+db_socket, '-uroot']+([db] if db else []), input=query, text=True, capture_output=True, check=True).stdout
        for _ in range(100):
            try:
                sql('SELECT 1'); break
            except subprocess.CalledProcessError:
                time.sleep(.1)
        release = subprocess.check_output(['git', 'show', '8ab77ce4:install/config/structure.sql'], cwd=ROOT, text=True)
        sql('CREATE DATABASE shipping_upgrade; CREATE DATABASE shipping_fresh;')
        sql(release, 'shipping_upgrade')
        sql((ROOT/'install/config/structure.sql').read_text(), 'shipping_fresh')
        sql((ROOT/'install/config/data.sql').read_text(), 'shipping_fresh')
        private = work/'db.ini'
        private.write_text('[test]\nresources.db.adapter=Pdo_Mysql\nresources.db.params.host=localhost\nresources.db.params.unix_socket="'+db_socket+'"\nresources.db.params.username=root\nresources.db.params.password=""\nresources.db.params.dbname=shipping_upgrade\n')
        private.chmod(0o600)
        def migrate(command):
            return json.loads(subprocess.check_output(['php', str(ROOT/'tools/migrate.php'), '--config='+str(private), '--environment=test', '--command='+command], text=True))
        migrate('baseline'); migrate('apply')
        schema = lambda db: json.loads(subprocess.check_output(['php', str(ROOT/'tests/install/migrations/schema.php'), db_socket, db], text=True))
        upgraded = schema('shipping_upgrade'); upgraded.pop('dewawi_schema_migration', None)
        assert upgraded == schema('shipping_fresh'), 'Development fresh and M1 upgraded schemas differ'
        migrate('apply')
        assert not migrate('plan')['pending'], 'Shipping migration ran twice or is still pending'
        print('PASS: shipping fresh/verified 1.1.8 upgrade schema equality, completed migration and repeated apply', flush=True)
        for script in ['tests/security/shop-shipping-db.php']:
            subprocess.run(['php', str(ROOT/script), db_socket], check=True)
        subprocess.run(['python3', str(ROOT/'tests/security/shop-shipping-http.py'), str(work)], check=True)
    finally:
        if server:
            server.terminate(); server.wait(timeout=20)
