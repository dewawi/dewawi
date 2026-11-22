"""Actual admin shop edit and readiness tab on the disposable shipping fixture."""
from pathlib import Path
import html
import json
import re
import shutil
import subprocess
import sys
import tempfile
import urllib.error
import urllib.request

origin = sys.argv[1]
work = Path(sys.argv[2]).resolve()
if not origin.startswith('http://127.0.0.1:') or not str(work).startswith('/tmp/dewawi-migrations-shipping-'):
    raise SystemExit('Isolated shipping HTTP fixture required')
checks = 0

def check(value, message):
    global checks
    checks += 1
    if not value:
        raise AssertionError(message)

def request(path):
    try:
        with urllib.request.urlopen(origin + path, timeout=15) as response:
            return response.status, response.read().decode()
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode()

edit = '/admin/shop/edit?id=51'
for role in ['guest', 'reader', 'editor', 'denied']:
    check(request(edit + '&role=' + role)[0] == 403, 'Unauthorized readiness read: ' + role)
check('shop-readiness' not in request(edit + '&tenant=8')[1], 'Foreign shop readiness exposed')
status, body = request(edit)
check(status == 200 and 'id="shop-readiness"' in body, 'Actual shop edit failed: ' + body[:500])
check('fixture-secret-never-render' not in body and 'smtp.example.invalid' not in body, 'SMTP credentials exposed')
check('ADMIN_GOLIVE' not in body and 'data-readiness-status="warning"' in body, 'Readiness not translated or manual acceptance missing')
section = body.split('<section id="shop-readiness"', 1)[1].split('</section>', 1)[0]
check('<input' not in section and '<button' not in section and '<form' not in section, 'Readiness has mutation controls')

def sql(query):
    subprocess.run(['mariadb', '--no-defaults', '--socket=' + str(work/'mysql.sock'), '-uroot', 'shipping_upgrade'], input=query, text=True, capture_output=True, check=True)

tables = ['shopshippingclass', 'shopshippingzone', 'shopshippingtariff', 'shopitemshipping', 'shopshippingcombination']
sql('RENAME TABLE ' + ', '.join(table + ' TO ' + table + '_absent' for table in tables))
try:
    status, body = request(edit)
    check(status == 200 and 'data-check="schema" data-status="blocked"' in body, 'Unmigrated shop edit did not report schema blocker')
    check('Fatal error' not in body and 'Warning:' not in body, 'Missing schema crashed admin edit')
    for table in tables:
        check(table in body, 'Missing table not identified: ' + table)
finally:
    sql('RENAME TABLE ' + ', '.join(table + '_absent TO ' + table for table in tables))

chrome = shutil.which('chromium') or shutil.which('google-chrome')
check(bool(chrome), 'Chromium unavailable')
for locale in ['de_DE', 'en_US']:
    for width in [1280, 390, 320]:
        script = r'''
const frame=document.querySelector('iframe');frame.onload=()=>{frame.onload=null;setTimeout(()=>{
 const d=frame.contentDocument,w=frame.contentWindow;let checks=0;const failures=[];
 const check=(v,m)=>{checks++;if(!v)throw Error(m)};
 try {
  const tab=d.querySelector('a[href="#tabgolive"]');check(tab,'Go-live tab missing');tab.click();
  check(d.querySelector('#tabgolive').classList.contains('is-active'),'Existing tab interaction failed');
  const section=d.querySelector('#shop-readiness');check(section&&section.getBoundingClientRect().height>0,'Readiness is hidden');
  section.querySelector('summary').click();check(section.querySelector('details').open,'Check details cannot be opened');
  check(!section.textContent.includes('ADMIN_GOLIVE'),'Untranslated readiness labels');
  check(section.querySelector('[data-check="freight"][data-status="ready"]'),'Configured freight is not ready');
  check(section.querySelector('[data-check="legal"][data-status="warning"]'),'Legal acceptance certified');
  check(section.querySelector('[data-readiness-status="warning"]'),'Synthetic fixture released');
  check(!section.querySelector('input,select,button,form'),'Readiness can mutate data');
  check(d.documentElement.scrollWidth<=w.innerWidth,'Admin page horizontal overflow');
  check(section.scrollWidth<=section.clientWidth,'Readiness horizontal overflow');
  check(!d.body.textContent.includes('fixture-secret-never-render'),'Credentials exposed');
 }catch(e){failures.push(e.message)}
 document.querySelector('#metrics').textContent=JSON.stringify({checks,failures});
},500)};
'''
        source = origin + edit + '&locale=' + locale
        (work/'shipping-browser.html').write_text('<!doctype html><iframe src="' + source + '" style="width:' + str(width) + 'px;height:2000px;border:0"></iframe><pre id="metrics"></pre><script>' + script + '</script>')
        with tempfile.TemporaryDirectory(prefix='dewawi-readiness-chromium-') as profile:
            run = subprocess.run([chrome, '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--user-data-dir=' + profile, '--window-size=1400,2200', '--virtual-time-budget=7000', '--dump-dom', origin + '/shipping-browser.html'], capture_output=True, text=True, timeout=45)
        metrics = re.findall(r'<pre id="metrics">(.*?)</pre>', run.stdout, re.S)
        check(run.returncode == 0 and metrics and metrics[-1], 'Readiness browser did not complete')
        result = json.loads(html.unescape(metrics[-1]))
        check(not result['failures'], str(width) + ' ' + locale + ': ' + '; '.join(result['failures']))
        checks += result['checks']
print(f'PASS: {checks} go-live admin integration, missing schema, authorization, DE/EN and desktop/mobile Chromium checks')
