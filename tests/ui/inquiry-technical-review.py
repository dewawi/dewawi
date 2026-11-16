"""Exercise immutable review persistence through the existing isolated HTTP fixture."""
from pathlib import Path
import html
import json
import os
import re
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request

work = Path(sys.argv[1]).resolve()
address = sys.argv[2]
if not str(work).startswith('/tmp/dewawi-technical-test-') or not re.fullmatch(r'http://127\.0\.0\.1:[0-9]+', address):
    raise RuntimeError('Only the disposable local technical fixture is accepted')
fixture = json.loads((work / 'inquiry-technical.json').read_text())
saved_id = json.loads((work / 'inquiry-review.json').read_text())['id']
identity = fixture['machine']
params = {'itemid': fixture['itemid'], 'datasetid': identity['id'], 'revision': identity['revision'], 'hash': identity['sha256'], 'calculation': 'mode:cooling:full'}
base = '/contacts/inquiry/select/id/918'
query = '?' + urllib.parse.urlencode(params)
script = r'''
(async function() {
 const result = document.getElementById('result');
 const frame = document.getElementById('workflow');
 function doc() { return frame.contentDocument; }
 function check(ok, message) { if(!ok) throw new Error(message); }
 function next(action) { return new Promise((resolve,reject) => {
   const timer = setTimeout(() => reject(new Error('Navigation timeout')),12000);
   frame.onload = () => { clearTimeout(timer);resolve(); };action();
 }); }
 try {
   await next(() => frame.src = START);
   const form = doc().querySelector('#inquiry-technical-calculate');
   form.querySelector('[name=cold_inlet]').value='20';form.querySelector('[name=hot_inlet]').value='30';form.querySelector('[name=reference]').checked=true;
   await next(() => form.querySelector('button').click());
   check(doc().querySelector('#inquiry-technical-result').textContent.includes('7.15'),'Calculation not shown before save');
   const save = doc().querySelector('#inquiry-technical-save');
   check(save,'Explicit save form missing');
   const action=save.action;const body=new URLSearchParams(new FormData(save));
   const before=await (await fetch('/review-count')).json();
   await next(() => save.querySelector('button').click());
   const open=doc().querySelector('#inquiry-technical-open-saved');
   check(open,'Save failed');const historicalUrl=open.href;const id=new URL(historicalUrl).searchParams.get('reviewid');
   const repeat=await fetch(action,{method:'POST',body});check(repeat.status===200,'Repeated POST failed');
   const after=await (await fetch('/review-count')).json();check(after.count===before.count+1,'Repeated form duplicated a review');
   await next(() => open.click());
   let historical=doc().querySelector('#inquiry-technical-historical');
   check(historical && historical.textContent.includes('7.15') && historical.textContent.includes('eligible=false'),'Reopened historical result missing');
   check(historical.textContent.includes('nicht neu berechnet') && historical.textContent.includes('SYNTHETISCHE TEST-/DEMOWERTE'),'Historical/demo boundary lost');
   check(doc().querySelectorAll('#inquiry-technical-history-list a').length>1,'History missing');
   await next(() => doc().querySelector('#inquiry-technical-reuse').click());
   check(doc().querySelector('[name=cold_inlet]').value==='20' && doc().querySelector('[name=hot_inlet]').value==='30','Explicit reuse did not restore conditions');
   check(!doc().querySelector('#inquiry-technical-result'),'Reuse recalculated automatically');
   const blocked=doc().querySelector('#inquiry-technical-save');check(blocked.textContent.includes('ohne Berechnung'),'Incomplete review not distinguishable');
   await next(() => blocked.querySelector('button').click());
   await next(() => doc().querySelector('#inquiry-technical-open-saved').click());
   historical=doc().querySelector('#inquiry-technical-historical');
   check(historical.textContent.includes('Keine Berechnung ausgeführt') && historical.textContent.includes('Technisch unvollständig'),'Incomplete stored status lost');
   check(historical.textContent.includes('Vorheriger Prüfstand: #'+id),'Predecessor identity lost');
   await next(() => frame.src=historicalUrl);
   historical=doc().querySelector('#inquiry-technical-historical');check(historical.textContent.includes('7.15'),'New version overwrote historical result');
   check(!doc().querySelector('.dw-alert--success') && doc().documentElement.scrollWidth<=frame.contentWindow.innerWidth,'False release or responsive overflow');
   historical.scrollIntoView();
   result.textContent='PASS: explicit save/reopen, bounded historical values, idempotent POST, history, reuse without recalculation, blocked successor, unchanged predecessor and eligible=false';
 } catch(error) { result.textContent='FAIL: '+error.message; }
})();
'''
(work / 'browser-review.html').write_text('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0}iframe{width:100%;height:790px;border:0}pre{white-space:pre-wrap}</style><pre id="result">PENDING</pre><iframe id="workflow"></iframe><script>const START=' + json.dumps(base + query) + ';' + script + '</script>')
def request(path, values=None):
    data = urllib.parse.urlencode(values).encode() if values is not None else None
    try:
        with urllib.request.urlopen(urllib.request.Request(address + path, data=data), timeout=10) as response:
            return response.status, response.read().decode()
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode()
def check(value, message):
    if not value:
        raise AssertionError(message)
check(request(base + '?reviewid=' + str(saved_id) + '&guest=1')[0] == 403, 'Guest knows review ID')
check(request('/contacts/inquiry/select/id/919?reviewid=' + str(saved_id))[0] == 403, 'Foreign inquiry exposes history')
check(request('/contacts/inquiry/select/id/920?reviewid=' + str(saved_id))[0] == 403, 'Review ID bypasses inquiry scope')
check(request('/contacts/inquiry/save/id/918', {'review_token': 'f' * 64})[0] == 403, 'HTTP save CSRF bypass')
for width in [390, 1280]:
    artifacts = os.environ.get('DEWAWI_INQUIRY_TEST_ARTIFACT_DIR')
    screenshot = []
    if artifacts:
        if not artifacts.startswith('/tmp/dewawi-'):
            raise ValueError('Artifacts must stay in /tmp/dewawi-*')
        Path(artifacts).mkdir(exist_ok=True)
        screenshot = ['--screenshot=' + str(Path(artifacts) / ('review-' + str(width) + '.png'))]
    result = subprocess.run(['chromium', '--headless', '--no-sandbox', '--disable-gpu', '--disable-background-networking', '--no-first-run', '--user-data-dir=' + str(work / ('chromium-review-' + str(width))), '--window-size=' + str(width) + ',900', '--virtual-time-budget=30000', '--dump-dom', *screenshot, address + '/browser-review'], capture_output=True, text=True, timeout=90)
    marker = html.unescape(result.stdout.split('<pre id="result">', 1)[-1].split('</pre>', 1)[0])
    print('reviews ' + str(width) + 'px: ' + marker)
    check(result.returncode == 0 and marker.startswith('PASS:'), 'Review browser workflow failed: ' + marker)
print('PASS: persisted review IDs remain private and scoped over HTTP; saving requires CSRF')
