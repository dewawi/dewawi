"""Test diagnosis and the manual geocoding CLI against a disposable database only."""
from pathlib import Path
import os
import subprocess
import tempfile
import time


root = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='dewawi-contact-map-') as directory:
    work = Path(directory)
    server = None
    try:
        subprocess.run(['mariadb-install-db', '--no-defaults', '--datadir=' + str(work / 'data'), '--auth-root-authentication-method=normal', '--skip-test-db'], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
        server = subprocess.Popen(['mariadbd', '--no-defaults', '--datadir=' + str(work / 'data'), '--socket=' + str(work / 'mysql.sock'), '--skip-networking', '--pid-file=' + str(work / 'server.pid'), '--log-error=' + str(work / 'database.log'), '--innodb-buffer-pool-size=32M', '--innodb-log-file-size=8M'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        for _ in range(100):
            if server.poll() is not None:
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
        subprocess.run(['php', str(Path(__file__).with_suffix('.php'))], check=True, env=env)
        subprocess.run(['php', str(root / 'tests/contacts/geocoding-manual-cli.php')], check=True, env=env)
        subprocess.run(['php', '-d', 'allow_url_fopen=0', str(root / 'tests/contacts/geocoding-batch.php')], check=True, env=env)
        subprocess.run(['php', '-d', 'allow_url_fopen=0', str(root / 'tests/contacts/geocoding-selection.php')], check=True, env=env)
    finally:
        if server is not None:
            server.terminate()
            server.wait(timeout=20)
