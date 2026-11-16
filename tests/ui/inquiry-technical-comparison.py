"""Read-only candidate comparison through actual private controllers and views."""
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
fixture = json.loads((work / 'inquiry-comparison.json').read_text())
base = '/contacts/inquiry/select/id/918'
def query(candidates):
    values = {'compare': '1'}
    for i, candidate in enumerate(candidates):
        for key, value in candidate.items():
            values['candidates[' + str(i) + '][' + key + ']'] = value
    return '?' + urllib.parse.urlencode(values)
script = r'''
(async function() {
 const result = document.getElementById('result'), frame = document.getElementById('workflow');
 const doc = () => frame.contentDocument;
 function check(ok, text) { if(!ok) throw new Error(text); }
 function next(action) { return new Promise((resolve,reject) => {
   const timer=setTimeout(() => reject(new Error('Navigation timeout')),12000);
   frame.onload=() => {clearTimeout(timer); resolve();}; action();
 }); }
 async function search(text) {
   const form=doc().querySelector('#inquiry-comparison-search');check(form,'Search missing');
   form.querySelector('[name=q]').value=text;await next(() => form.querySelector('button').click());
 }
 function responsive() {
   check(doc().documentElement.scrollWidth<=frame.contentWindow.innerWidth,'Horizontal overflow');
   const cells=doc().querySelectorAll('.comparison-table td');
   check(cells.length>20 && Array.from(cells).every(c=>c.dataset.candidate),'Mobile candidate identity missing');
   check(!doc().querySelector('.dw-alert--success'),'Misleading success/fitness badge');
   check(!frame.contentWindow.injected,'Inquiry HTML executed');
 }
 try {
   const before=await(await fetch('/review-count')).json();
   await next(() => frame.src='/contacts/inquiry/view/id/918');
   const entry=Array.from(doc().querySelectorAll('a')).find(a=>a.href.includes('compare=1'));check(entry,'Comparison entry missing');
   await next(() => entry.click());
   check(!doc().querySelector('.comparison-table'),'Automatic candidate selection');
   await search('DEMO-D7B2A-R410A');
   check(doc().querySelectorAll('.comparison-add').length===1,'Search not scoped');
   await next(() => doc().querySelector('.comparison-add').click());
   check(doc().querySelectorAll('.comparison-open').length===1,'One candidate missing');
   const first=Array.from(doc().querySelectorAll('.comparison-revision')).find(a=>new URL(a.href).searchParams.get('candidates[0][datasetid]')===String(FIXTURE.candidates[0].datasetid));check(first,'Explicit revision missing');
   await next(() => first.click());
   check(doc().querySelector('[data-area=refrigerant_curve]').textContent.includes('synthetische'),'Synthetic curve boundary missing');
   check(doc().querySelector('[data-area=evaporator_maps]').textContent.includes('Nachweis fehlt'),'Absent machine map hidden');
   await search('DEMO-D7B2A-R454B');
   await next(() => doc().querySelector('.comparison-add').click());
   const second=Array.from(doc().querySelectorAll('.comparison-revision')).find(a=>new URL(a.href).searchParams.get('candidates[1][datasetid]')===String(FIXTURE.candidates[1].datasetid));check(second,'Second revision missing');
   await next(() => second.click());
   check(doc().querySelectorAll('.comparison-open').length===2,'Two candidates missing');
   const titles=Array.from(doc().querySelectorAll('.comparison-table thead th')).slice(1).map(c=>c.textContent);
   check(titles[0].includes('R410A')&&titles[1].includes('R454B'),'Manual order or refrigerants changed');
   check(doc().querySelector('[data-area="requirement:kuehlleistung"]').textContent.includes('31')&&doc().querySelector('[data-area="requirement:kuehlleistung"]').textContent.includes('kW'),'Original power units lost');
   responsive();
   await next(() => doc().querySelector('.comparison-remove').click());
   check(doc().querySelectorAll('.comparison-open').length===1 && doc().querySelector('.comparison-table thead').textContent.includes('R454B'),'Remove changed remaining candidate');
   await next(() => doc().querySelector('.comparison-open').click());
   check(doc().querySelector('select[name=calculation]') && doc().querySelector('#inquiry-technical-save'),'Existing D7A/D7B1 review/save unavailable');
   const choice=doc().querySelector('select[name=calculation]');choice.value='stage';await next(() => choice.form.querySelector('button').click());
   check(doc().querySelector('#inquiry-technical-calculate'),'Explicit calculation choice unavailable');
   check(!doc().querySelector('#inquiry-technical-result'),'Opening calculated automatically');
   await next(() => frame.src=HISTORICAL);
   const historic=doc().querySelector('[data-area=historical]');
   check(historic.textContent.includes('7.15') && historic.textContent.includes('kW') && historic.textContent.includes('nicht neu berechnet'),'Historical units or immutable result missing');
   check(historic.textContent.includes('SYNTHETISCHE TEST-/DEMOWERTE') && historic.textContent.includes('eligible=false'),'Demo/history release boundary lost');
   check(historic.textContent.includes('Heute geprüfter Datenstand') && doc().querySelectorAll('.comparison-history').length>1,'Current status or saved review choice missing');
   check(!doc().querySelector('form[method=post]'),'Comparison has a write form');
   const after=await(await fetch('/review-count')).json();check(before.count===after.count,'Read-only comparison saved history');
   responsive();
   doc().querySelector('.comparison-table').scrollIntoView();
   result.textContent='PASS: manual search/add/revision, two exact variants and refrigerants, coverage gaps, original units, remove/open D7B1, historical result/current status, no writes, responsive comparison, eligible=false';
 } catch(error) {result.textContent='FAIL: '+error.message;}
})();
'''
historical = base + query([fixture['historical'], fixture['candidates'][1]])
(work / 'browser-comparison.html').write_text('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0}iframe{width:100%;height:790px;border:0}pre{white-space:pre-wrap}</style><pre id="result">PENDING</pre><iframe id="workflow"></iframe><script>const FIXTURE=' + json.dumps(fixture) + ';const HISTORICAL=' + json.dumps(historical) + ';' + script + '</script>')
def request(path):
    try:
        with urllib.request.urlopen(address + path, timeout=10) as response:
            return response.status, response.read().decode()
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode()
def check(value, message):
    if not value:
        raise AssertionError(message)
for suffix, code in [('&guest=1',403), ('&candidates[0][itemid]=2807',403), ('&candidates=bad',422), ('&q[]=bad',422)]:
    check(request(base + '?compare=1' + suffix)[0] == code, 'HTTP comparison input/authorization bypass: ' + suffix)
check(request(base + query([fixture['candidates'][0]] * 4))[0] == 422, 'HTTP candidate limit bypass')
check(request('/contacts/inquiry/select/id/919' + query([fixture['historical']]))[0] == 403, 'Foreign inquiry comparison')
for width in [390,1280]:
    artifacts = os.environ.get('DEWAWI_INQUIRY_TEST_ARTIFACT_DIR')
    screenshot = []
    if artifacts:
        if not artifacts.startswith('/tmp/dewawi-'):
            raise ValueError('Artifacts must stay in /tmp/dewawi-*')
        Path(artifacts).mkdir(exist_ok=True)
        screenshot = ['--screenshot=' + str(Path(artifacts) / ('comparison-' + str(width) + '.png'))]
    result = subprocess.run(['chromium','--headless','--no-sandbox','--disable-gpu','--disable-background-networking','--no-first-run','--user-data-dir=' + str(work / ('chromium-comparison-' + str(width))), '--window-size=' + str(width) + ',900','--virtual-time-budget=45000','--dump-dom', *screenshot,address + '/browser-comparison'],capture_output=True,text=True,timeout=90)
    marker = html.unescape(result.stdout.split('<pre id="result">',1)[-1].split('</pre>',1)[0])
    print('comparison ' + str(width) + 'px: ' + marker)
    check(result.returncode == 0 and marker.startswith('PASS:'), 'Comparison browser failed: ' + marker)
print('PASS: comparison HTTP tenant, authentication, malformed input and candidate limits')
