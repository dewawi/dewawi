"""Run calendar integration/HTTP/browser checks using disposable local services only."""
from pathlib import Path
import os
import shutil
import socket
import subprocess
import tempfile
import time

root = Path(__file__).resolve().parents[2]
work = Path(tempfile.mkdtemp(prefix='dewawi-calendar-'))
server = web = None
try:
    subprocess.run(['mariadb-install-db', '--no-defaults', '--datadir='+str(work/'data'), '--auth-root-authentication-method=normal', '--skip-test-db'], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    server = subprocess.Popen(['mariadbd', '--no-defaults', '--datadir='+str(work/'data'), '--socket='+str(work/'mysql.sock'), '--skip-networking', '--pid-file='+str(work/'server.pid'), '--log-error='+str(work/'server.log'), '--innodb-buffer-pool-size=32M', '--innodb-log-file-size=8M'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    for _ in range(100):
        if server.poll() is not None:
            raise RuntimeError((work/'server.log').read_text())
        ready = subprocess.run(['mariadb', '--no-defaults', '--socket='+str(work/'mysql.sock'), '-uroot', '-e', 'SELECT 1'], capture_output=True)
        if ready.returncode == 0:
            break
        time.sleep(.1)
    subprocess.run(['mariadb', '--no-defaults', '--socket='+str(work/'mysql.sock'), '-uroot', '-e', 'CREATE DATABASE calendar_test'], check=True)
    env = dict(os.environ, DEWAWI_CALENDAR_TEST=str(work))
    subprocess.run(['php', str(root/'tests/calendar/integration.php')], check=True, env=env)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    address = f'http://127.0.0.1:{port}'
    with (work/'http.log').open('w') as log:
        web = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', str(root/'tests/calendar/router.php')], env=env, stdout=log, stderr=log)
        time.sleep(.3)
        result = subprocess.run(['python3', str(root/'tests/calendar/workflow.py'), str(work), address])
        if result.returncode:
            print((work/'http.log').read_text())
            raise SystemExit(result.returncode)
finally:
    for service in [web, server]:
        if service:
            service.terminate()
            service.wait(timeout=20)
    shutil.rmtree(work)
