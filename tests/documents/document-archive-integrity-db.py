from pathlib import Path
import shutil
import subprocess
import tempfile
import time
import importlib.util
import sys

work = Path(tempfile.mkdtemp(prefix='dewawi-document-integrity-db-'))
server = None
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
    result = subprocess.run(['php', '-d', 'error_reporting=8191', str(Path(__file__).with_suffix('.php')), socket], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
    print(result.stdout)
    if result.returncode == 0:
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
        finally:
            browser.close()
    raise SystemExit(result.returncode)
finally:
    if server is not None:
        server.terminate()
        server.wait(timeout=20)
    shutil.rmtree(work)
