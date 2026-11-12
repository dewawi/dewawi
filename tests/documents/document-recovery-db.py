from pathlib import Path
import shutil
import subprocess
import tempfile
import time
import importlib.util
import sys

# Only this disposable, networkless server is used by the database regression.
work = Path(tempfile.mkdtemp(prefix='dewawi-document-recovery-db-'))
server = None
try:
    init = subprocess.run(
        ['mariadb-install-db', '--no-defaults', '--datadir=' + str(work / 'data'),
         '--auth-root-authentication-method=normal', '--skip-test-db'],
        stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True,
    )
    if init.returncode:
        raise RuntimeError(init.stdout)
    socket = str(work / 'mysql.sock')
    server = subprocess.Popen(
        ['mariadbd', '--no-defaults', '--datadir=' + str(work / 'data'), '--socket=' + socket,
         '--skip-networking', '--pid-file=' + str(work / 'server.pid'), '--log-error=' + str(work / 'server.log'),
         '--innodb-buffer-pool-size=32M', '--innodb-log-file-size=8M'],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
    )
    for _ in range(100):
        if server.poll() is not None:
            raise RuntimeError((work / 'server.log').read_text())
        if Path(socket).exists():
            break
        time.sleep(0.1)
    result = subprocess.run(
        ['php', str(Path(__file__).with_suffix('.php')), socket],
        stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True,
    )
    print(result.stdout)
    if result.returncode == 0:
        sys.dont_write_bytecode = True
        spec = importlib.util.spec_from_file_location('dewawi_review_browser', Path(__file__).resolve().parents[1] / 'site/accessibility-http.py')
        browser_module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(browser_module)
        browser = browser_module.WebDriver()
        try:
            for locale in ['de_DE', 'en_US']:
                for width in [375, 1440]:
                    browser.emulate_viewport(width, 900)
                    browser.navigate((work / ('review-panel-' + locale + '.html')).as_uri())
                    panel = browser.execute("return {width: innerWidth, overflow: document.documentElement.scrollWidth > innerWidth, status: document.querySelector('[role=status]').dataset.documentStatus, links: document.querySelectorAll('a').length, text: document.body.textContent}")
                    assert panel['width'] == width and not panel['overflow'] and panel['status'] == 'historical_reconstruction_pending_original_comparison' and panel['links'] == 4 and 'DOCUMENTS_' not in panel['text'], panel
                    print('PASS: recovery review panel ' + locale + '/' + str(width) + '; two separate PDFs, translated status and no overflow')
        finally:
            browser.close()
    raise SystemExit(result.returncode)
finally:
    if server is not None:
        server.terminate()
        server.wait(timeout=20)
    shutil.rmtree(work)
