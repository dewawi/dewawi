from pathlib import Path
import shutil
import subprocess
import tempfile
import time
import importlib.util
import sys
import socketserver
import threading
import os
import json
import hashlib
from email.parser import BytesParser
from email import policy

messages = []
class FixtureSmtp(socketserver.StreamRequestHandler):
    def handle(self):
        self.wfile.write(b'220 fixture.invalid\r\n')
        while line := self.rfile.readline():
            command = line.split(b' ', 1)[0].strip().upper()
            if command == b'DATA':
                self.wfile.write(b'354 message follows\r\n')
                body = []
                while (line := self.rfile.readline()) not in [b'.\r\n', b'']:
                    body.append(line[1:] if line.startswith(b'..') else line)
                messages.append(b''.join(body))
                self.wfile.write(b'250 accepted\r\n')
            elif command == b'QUIT':
                self.wfile.write(b'221 bye\r\n')
                return
            else:
                self.wfile.write(b'250 fixture.invalid\r\n')

work = Path(tempfile.mkdtemp(prefix='dewawi-document-integrity-db-'))
server = None
smtp = socketserver.ThreadingTCPServer(('127.0.0.1', 0), FixtureSmtp)
threading.Thread(target=smtp.serve_forever, daemon=True).start()
try:
    init = subprocess.run(['mariadb-install-db', '--no-defaults', '--datadir=' + str(work / 'data'), '--auth-root-authentication-method=normal', '--skip-test-db'], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
    if init.returncode:
        raise RuntimeError(init.stdout)
    socket = str(work / 'mysql.sock')
    server = subprocess.Popen(['mariadbd', '--no-defaults', '--datadir=' + str(work / 'data'), '--socket=' + socket, '--skip-networking', '--pid-file=' + str(work / 'server.pid'), '--log-error=' + str(work / 'server.log'), '--innodb-buffer-pool-size=32M', '--innodb-log-file-size=8M'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    for _ in range(100):
        if server.poll() is not None:
            raise RuntimeError((work / 'server.log').read_text())
        if Path(socket).exists():
            break
        time.sleep(0.1)
    result = subprocess.run(['php', '-d', 'error_reporting=8191', str(Path(__file__).with_suffix('.php')), socket], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, env={**os.environ, 'DEWAWI_CORRECTION_TEST_SMTP_PORT': str(smtp.server_address[1])})
    print(result.stdout)
    if result.returncode == 0:
        expected = json.loads((work / 'correction-email-check.json').read_text())
        assert len(messages) == 1, len(messages)
        message = BytesParser(policy=policy.default).parsebytes(messages[0])
        actual = {part.get_filename(): hashlib.sha256(part.get_payload(decode=True)).hexdigest() for part in message.iter_attachments()}
        assert actual == expected, (actual, expected)
        print('PASS: explicit correction SMTP delivery; exact original and correction bytes, no default or automatic send')
        sys.dont_write_bytecode = True
        spec = importlib.util.spec_from_file_location('dewawi_changes_browser', Path(__file__).resolve().parents[1] / 'site/accessibility-http.py')
        browser_module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(browser_module)
        browser = browser_module.WebDriver()
        try:
            for width in [375, 1440]:
                browser.emulate_viewport(width, 1000)
                for locale in ['de_DE', 'en_US']:
                    browser.navigate((work / ('revision-panel-' + locale + '.html')).as_uri())
                    check = browser.execute("document.querySelector('details').open=true; let calls=0; $.ajax=function(){calls++; return $.Deferred().resolve({}).promise();}; $('textarea').val('Synthetic revision reason').trigger('change'); return {overflow: document.documentElement.scrollWidth > innerWidth, calls:calls, dirty:Dewawi.isDirty, errors:fixtureErrors, reason:document.querySelector('textarea').required, token:document.querySelector('[name=csrf_token]').value.length}")
                    assert not check['overflow'] and not check['calls'] and not check['dirty'] and not check['errors'] and check['reason'] and check['token'] == 64, check
                    print('PASS: revision panel ' + locale + '/' + str(width) + '; reason, CSRF, native form and no autosave or overflow')
                    browser.navigate((work / ('correction-panel-' + locale + '.html')).as_uri())
                    check = browser.execute("let calls=0; $.ajax=function(){calls++; return $.Deferred().resolve({}).promise();}; let select=document.querySelector('[data-correction-select]'); select.value='document.billingname1'; $(select).trigger('change'); let before=document.querySelector('[data-correction-before]').textContent; $('textarea').first().val('Synthetic correction edit').trigger('change'); $('input[type=checkbox]').prop('checked',true).trigger('change'); return {overflow:document.documentElement.scrollWidth>innerWidth,calls:calls,dirty:Dewawi.isDirty,errors:fixtureErrors,before:before,forms:document.forms.length,finance:!!document.querySelector('[name=total]'),confirm:document.querySelector('[name=original_confirmed]').required}")
                    assert not check['overflow'] and not check['calls'] and not check['dirty'] and not check['errors'] and check['before'] == 'Synthetic recipient' and check['forms'] == 2 and not check['finance'] and check['confirm'], check
                    print('PASS: correction panel ' + locale + '/' + str(width) + '; before/after, explicit approval and no autosave or overflow')
        finally:
            browser.close()
    raise SystemExit(result.returncode)
finally:
    smtp.shutdown()
    smtp.server_close()
    if server is not None:
        server.terminate()
        server.wait(timeout=20)
    shutil.rmtree(work)
