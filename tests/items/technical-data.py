from pathlib import Path
import subprocess
import tempfile
import time
import shutil

root = Path(__file__).resolve().parents[2]
work = Path(tempfile.mkdtemp(prefix='dewawi-technical-test-'))
server = None
try:
    # The upgrade starts from the repository's real tagged 1.1.7 schema.
    historical = subprocess.run(['git', 'show', '1.1.7:install/config/structure.sql'], cwd=root, check=True, capture_output=True)
    (work / 'structure-1.1.7.sql').write_bytes(historical.stdout)
    result = subprocess.run(['mariadb-install-db', '--no-defaults', '--datadir='+str(work/'data'), '--auth-root-authentication-method=normal', '--skip-test-db'], capture_output=True, text=True)
    if result.returncode:
        raise RuntimeError(result.stdout + result.stderr)
    socket = str(work / 'mysql.sock')
    server = subprocess.Popen(['mariadbd', '--no-defaults', '--datadir='+str(work/'data'), '--socket='+socket, '--skip-networking', '--pid-file='+str(work/'server.pid'), '--log-error='+str(work/'server.log'), '--innodb-buffer-pool-size=32M', '--innodb-log-file-size=8M'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    for _ in range(100):
        if server.poll() is not None:
            raise RuntimeError((work / 'server.log').read_text())
        if Path(socket).exists():
            break
        time.sleep(0.1)
    result = subprocess.run(['php', str(Path(__file__).with_suffix('.php')), socket, str(work)], cwd=root, capture_output=True, text=True)
    print(result.stdout, end='')
    if result.stderr:
        print(result.stderr, end='')
    if result.returncode:
        raise SystemExit(result.returncode)
finally:
    if server is not None:
        server.terminate()
        server.wait(timeout=20)
    shutil.rmtree(work)
