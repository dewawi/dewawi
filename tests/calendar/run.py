"""Run calendar integration/HTTP/browser checks using disposable local services only."""
from pathlib import Path
import os
import shutil
import socket
import subprocess
import tempfile
import time
import json
import threading
import socketserver
import uuid

root = Path(__file__).resolve().parents[2]
work = Path(tempfile.mkdtemp(prefix='dewawi-calendar-'))
server = web = None
smtp = None
class CalendarSMTP(socketserver.StreamRequestHandler):
    def handle(self):
        self.wfile.write(b'220 calendar fixture\r\n')
        recipient = ''
        while True:
            line = self.rfile.readline()
            if not line:
                return
            command = line.decode(errors='replace').strip()
            if command.upper().startswith('EHLO'):
                self.wfile.write(b'250-calendar fixture\r\n250 SIZE 1000000\r\n')
            elif command.upper().startswith('RCPT TO:'):
                recipient = command[8:]
                with (work/'smtp-commands.txt').open('a') as log:
                    log.write(recipient+'\n')
                self.wfile.write(b'451 test rejection\r\n' if 'failure@' in recipient else b'250 OK\r\n')
            elif command.upper() == 'DATA':
                self.wfile.write(b'354 End with dot\r\n')
                lines = []
                while True:
                    line = self.rfile.readline()
                    if line in [b'.\r\n', b'']:
                        break
                    lines.append(line[1:] if line.startswith(b'..') else line)
                (work/('smtp-'+uuid.uuid4().hex+'.eml')).write_bytes(b''.join(lines))
                if 'uncertain@' in recipient:
                    return
                self.wfile.write(b'250 Accepted\r\n')
            elif command.upper() == 'QUIT':
                self.wfile.write(b'221 Bye\r\n')
                return
            else:
                self.wfile.write(b'250 OK\r\n')
try:
    for name in ['library', 'languages']:
        (work/name).symlink_to(root/name, target_is_directory=True)
    smtp = socketserver.ThreadingTCPServer(('127.0.0.1', 0), CalendarSMTP)
    smtp.daemon_threads = True
    threading.Thread(target=smtp.serve_forever, daemon=True).start()
    (work/'smtp.json').write_text(json.dumps({'port':smtp.server_address[1]}))
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
    subprocess.run(['php', str(root/'tests/calendar/reminders.php')], check=True, env=env)
    subprocess.run(['php', str(root/'tests/calendar/participants.php')], check=True, env=env)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    address = f'http://127.0.0.1:{port}'
    with (work/'http.log').open('w') as log:
        web = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', str(root/'tests/calendar/router.php')], env=env, stdout=log, stderr=log)
        time.sleep(.3)
        for script in ['workflow.py', 'reminders-workflow.py', 'participants-workflow.py']:
            result = subprocess.run(['python3', str(root/'tests/calendar'/script), str(work), address])
            if result.returncode:
                print('\n'.join(line for line in (work/'http.log').read_text().splitlines() if not any(noise in line for noise in ['Accepted', 'Closing', 'Closed without sending'])))
                raise SystemExit(result.returncode)
finally:
    if smtp:
        smtp.shutdown()
        smtp.server_close()
    for service in [web, server]:
        if service:
            service.terminate()
            service.wait(timeout=20)
    shutil.rmtree(work)
