"""Exercise real private D7A controller requests against the disposable technical database."""
from pathlib import Path
import html
import http.cookiejar
import json
import os
import socket
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
import time

root = Path(__file__).resolve().parents[2]
work = Path(sys.argv[1]).resolve()
if not str(work).startswith('/tmp/dewawi-technical-test-') or sys.argv[2] != str(work / 'mysql.sock'):
    raise RuntimeError('Only disposable technical test databases are accepted')
fixture = json.loads((work / 'inquiry-technical.json').read_text())
identity = fixture['machine']
parameters = {'itemid': fixture['itemid'], 'datasetid': identity['id'], 'revision': identity['revision'], 'hash': identity['sha256'], 'calculation': 'mode:cooling:full'}
base = '/contacts/inquiry/select/id/' + str(fixture['inquiryid'])
script = r'''
(async function() {
  const result = document.getElementById('result');
  const frame = document.getElementById('workflow');
  function check(value, message) { if(!value) throw new Error(message); }
  function doc() { return frame.contentDocument; }
  function next(action) { return new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error('Navigation timeout')), 12000);
    frame.onload = () => { clearTimeout(timer); resolve(); };
    action();
  }); }
  try {
    await next(() => frame.src = '/contacts/inquiry/view/id/918');
    check(doc().body.textContent.includes('31') && doc().body.textContent.includes('kW'), 'Original inquiry or unit missing');
    await next(() => doc().querySelector('a.dw-btn').click());
    const search = doc().querySelector('[name=q]');
    search.value = 'DEMO-D7A-machine';
    await next(() => search.form.querySelector('button').click());
    const item = Array.from(doc().querySelectorAll('a')).find(a => a.textContent.includes('DEMO-D7A-machine'));
    check(item, 'Manual article search failed');
    await next(() => item.click());
    const revision = Array.from(doc().querySelectorAll('a')).find(a => a.textContent.includes('fixture:d7a:machine:1 @1'));
    check(revision, 'Machine revision missing');
    await next(() => revision.click());
    check(doc().body.textContent.includes('DEMO-D7A-compressor-b') && doc().body.textContent.includes('2603'), 'Component or variant missing');
    const select = doc().querySelector('select[name=calculation]'); select.value = 'mode:cooling:full';
    await next(() => select.form.querySelector('button').click());
    check(doc().querySelector('[name=cold_inlet]').value === '' && doc().querySelector('[name=hot_inlet]').value === '', 'Inlets guessed');
    const calculate = doc().querySelector('#inquiry-technical-calculate');
    check(!calculate.checkValidity(), 'Missing boundary accepted by browser');
    calculate.querySelector('[name=cold_inlet]').value = '20'; calculate.querySelector('[name=hot_inlet]').value = '30';
    calculate.querySelector('[name=reference]').click();
    await next(() => calculate.querySelector('button').click());
    const output = doc().querySelector('#inquiry-technical-result');
    check(output && output.textContent.includes('SYNTHETISCHE TEST-/DEMOWERTE'), 'Demo label missing');
    check(output.textContent.includes('converged') && output.textContent.includes('eligible=false'), 'Math and release statuses missing');
    check(output.textContent.includes('7.15') && output.textContent.includes('real_machine_release_missing'), 'Computed power or OEM blockers missing');
    check(!doc().querySelector('.alert-success, .dw-alert--success') && !window.injected && !frame.contentWindow.injected, 'False success or XSS');
    check(doc().documentElement.scrollWidth <= frame.contentWindow.innerWidth, 'Horizontal overflow');
    output.scrollIntoView();
    result.textContent = 'PASS: actual inquiry entry, manual article and revision selection, components and variant, blank typed inputs, synthetic POST calculation, statuses, OEM blockers, escaping and responsive layout';
  } catch(error) { result.textContent = 'FAIL: ' + error.message; }
})();
'''
(work / 'browser.html').write_text('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0}iframe{width:100%;height:790px;border:0}pre{white-space:pre-wrap}</style><pre id="result">PENDING</pre><iframe id="workflow"></iframe><script>' + script + '</script>')
with socket.socket() as listener:
    listener.bind(('127.0.0.1', 0))
    port = listener.getsockname()[1]
address = 'http://127.0.0.1:' + str(port)
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(path, values=None):
    data = urllib.parse.urlencode(values).encode() if values is not None else None
    req = urllib.request.Request(address + path, data=data, headers={'X-Requested-With': 'XMLHttpRequest'} if values is not None else {})
    try:
        with client.open(req, timeout=30) as response:
            return response.status, response.read().decode()
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode()
def check(value, message):
    if not value:
        raise AssertionError(message)
with (work / 'd7a-http.log').open('w') as log:
    server = subprocess.Popen(['php', '-S', '127.0.0.1:' + str(port), '-t', str(work), str(root / 'tests/security/fixtures/inquiry-technical-http.php')], env=dict(os.environ, DEWAWI_TECHNICAL_TEST_SOCKET=sys.argv[2]), stdout=log, stderr=log)
    try:
        for _ in range(100):
            try:
                status, before = request('/state')
                break
            except urllib.error.URLError:
                time.sleep(.05)
        check(status == 200, 'HTTP fixture failed to start')
        query = '?' + urllib.parse.urlencode(parameters)
        check(request(base + query + '&guest=1')[0] == 403, 'Guest direct URL accepted')
        check(request('/contacts/inquiry/select/id/919' + query)[0] == 403, 'Foreign inquiry direct URL accepted')
        check(request(base, parameters)[0] == 403, 'AJAX without CSRF accepted')
        bad = dict(parameters, hash='0' * 64)
        check(request(base + '?' + urllib.parse.urlencode(bad))[0] == 409, 'Stale hash accepted over HTTP')
        status, page = request(base + query)
        check(status == 200 and 'Flüssigkeitseintritt' in page, 'GET form or private display failed')
        for width in [390, 1280]:
            artifacts = os.environ.get('DEWAWI_INQUIRY_TEST_ARTIFACT_DIR')
            screenshot = []
            if artifacts:
                if not artifacts.startswith('/tmp/dewawi-'):
                    raise ValueError('UI artifacts must stay under /tmp/dewawi-*')
                Path(artifacts).mkdir(exist_ok=True)
                screenshot = ['--screenshot=' + str(Path(artifacts) / ('technical-' + str(width) + '.png'))]
            result = subprocess.run(['chromium', '--headless', '--no-sandbox', '--disable-gpu', '--disable-background-networking', '--no-first-run', '--user-data-dir=' + str(work / ('chromium-d7a-' + str(width))), '--window-size=' + str(width) + ',900', '--virtual-time-budget=25000', '--dump-dom', *screenshot, address + '/browser'], capture_output=True, text=True, timeout=90)
            marker = html.unescape(result.stdout.split('<pre id="result">', 1)[-1].split('</pre>', 1)[0])
            print(str(width) + 'px: ' + marker)
            check(result.returncode == 0 and marker.startswith('PASS:'), 'D7A browser workflow failed: ' + marker)
        check(request('/state')[1] == before, 'HTTP/browser workflow changed business or technical state')
        check('PHP Warning' not in (work / 'd7a-http.log').read_text() and 'PHP Fatal' not in (work / 'd7a-http.log').read_text(), 'PHP warnings during browser workflow')
        print('PASS: private HTTP GET/POST/AJAX denial, CSRF, revision conflicts and unchanged inquiry/sales state')
    except Exception:
        print((work / 'd7a-http.log').read_text())
        raise
    finally:
        server.terminate()
        server.wait(timeout=10)
