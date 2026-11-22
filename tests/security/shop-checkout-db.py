from pathlib import Path
import shutil
import subprocess
import tempfile
import time

work = Path(tempfile.mkdtemp(prefix='dewawi-shop-checkout-db-'))
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
            'mariadbd', '--no-defaults', '--user=root', '--datadir=' + str(work / 'data'), '--socket=' + socket,
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
            ready = subprocess.run(['mariadb-admin', '--no-defaults', '--socket=' + socket, '--user=root', 'ping'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            if ready.returncode == 0:
                break
        time.sleep(0.1)

    for script in [Path(__file__).with_suffix('.php'), Path(__file__).with_name('shop-cart-db.php'), Path(__file__).with_name('shop-orders-db.php')]:
        result = subprocess.run(
            ['php', str(script), socket], text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
        )
        print(result.stdout)
        if result.returncode:
            raise SystemExit(result.returncode)

    http_result = subprocess.run(['python3', str(Path(__file__).with_name('shop-orders-http.py')), str(work)], text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    print(http_result.stdout)
    if http_result.returncode:
        raise SystemExit(http_result.returncode)

    flow_result = subprocess.run(['python3', str(Path(__file__).with_name('shop-checkout-flow.py')), str(work)], text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    print(flow_result.stdout)
    if flow_result.returncode:
        raise SystemExit(flow_result.returncode)

    subprocess.run(['php', str(Path(__file__).with_name('shop-checkout-shipping-db.php')), socket], check=True)
    subprocess.run(['php', str(Path(__file__).with_name('shop-checkout-tax-db.php')), socket], check=True)

finally:
    if server is not None:
        server.terminate()
        server.wait(timeout=20)
    shutil.rmtree(work)
