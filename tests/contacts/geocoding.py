"""Exercise geocoding with a disposable MariaDB and mocked HTTP responses only."""
from pathlib import Path
import subprocess
import tempfile
import time


with tempfile.TemporaryDirectory(prefix='dewawi-geocoding-') as directory:
    work = Path(directory)
    server = None
    try:
        subprocess.run([
            'mariadb-install-db', '--no-defaults', '--datadir=' + str(work / 'data'),
            '--auth-root-authentication-method=normal', '--skip-test-db',
        ], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
        socket = work / 'mysql.sock'
        server = subprocess.Popen([
            'mariadbd', '--no-defaults', '--datadir=' + str(work / 'data'),
            '--socket=' + str(socket), '--skip-networking',
            '--pid-file=' + str(work / 'server.pid'), '--log-error=' + str(work / 'server.log'),
            '--innodb-buffer-pool-size=32M', '--innodb-log-file-size=8M',
        ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        for _ in range(100):
            if server.poll() is not None:
                raise RuntimeError((work / 'server.log').read_text())
            ready = subprocess.run([
                'mariadb', '--no-defaults', '--socket=' + str(socket), '-uroot', '-e', 'SELECT 1',
            ], capture_output=True)
            if ready.returncode == 0:
                break
            time.sleep(0.1)
        else:
            raise RuntimeError('Disposable MariaDB did not become ready')
        subprocess.run(['php', str(Path(__file__).with_suffix('.php')), str(socket)], check=True)
    finally:
        if server is not None:
            server.terminate()
            server.wait(timeout=20)
