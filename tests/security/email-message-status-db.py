from pathlib import Path
import shutil
import subprocess
import tempfile
import time

work = Path(tempfile.mkdtemp(prefix='dewawi-email-message-status-db-'))
server = None
try:
    init = subprocess.run(
        ['mariadb-install-db', '--no-defaults', '--datadir=' + str(work / 'data'), '--auth-root-authentication-method=normal', '--skip-test-db'],
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
    )
    if init.returncode:
        print(init.stdout)
        raise SystemExit(init.returncode)

    socket = str(work / 'mysql.sock')
    server = subprocess.Popen(
        [
            'mariadbd', '--no-defaults', '--datadir=' + str(work / 'data'), '--socket=' + socket,
            '--skip-networking', '--pid-file=' + str(work / 'server.pid'), '--log-error=' + str(work / 'server.log'),
            '--innodb-buffer-pool-size=32M', '--innodb-log-file-size=8M',
        ],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )
    for _ in range(100):
        if server.poll() is not None:
            print((work / 'server.log').read_text())
            raise SystemExit('Isolated MariaDB failed to start')
        if Path(socket).exists():
            break
        time.sleep(0.1)

    result = subprocess.run(
        ['php', str(Path(__file__).with_suffix('.php')), socket],
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
    )
    print(result.stdout)
    raise SystemExit(result.returncode)
finally:
    if server is not None:
        server.terminate()
        server.wait(timeout=20)
    shutil.rmtree(work)
