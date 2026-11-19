"""Cross-engine integration probes using existing memory-only PHP fixtures.

No business request reaches PHP/ERP. Firefox uses its installed BiDi endpoint;
Chromium reuses the existing driver. Neither engine uses a production session
or database; these probes do not constitute authenticated ERP or screenreader E2E.
"""
from pathlib import Path
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from threading import Thread
from urllib.parse import urlparse
import argparse
import asyncio
import base64
import importlib.util
import json
import subprocess
import sys
import tempfile
import time

sys.dont_write_bytecode = True
root = Path(__file__).resolve().parents[2]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--engine', choices=['chromium', 'firefox'], default='chromium')
parser.add_argument('--output', type=Path, default=Path('/tmp/dewawi-button-release-audit'))
parser.add_argument('--standard-actions', action='store_true', help='Probe standard Delete actions using memory-only complete documents')
parser.add_argument('--document-icons', action='store_true', help='Check original complete MVC documents and actual SVG pixels')
args = parser.parse_args()
args.output.mkdir(parents=True, exist_ok=True)
spec = importlib.util.spec_from_file_location('audit_browser', root / 'tests/site/accessibility-http.py')
browser = importlib.util.module_from_spec(spec)
spec.loader.exec_module(browser)


class Firefox:
    def __init__(self):
        import websockets
        self.profile = tempfile.TemporaryDirectory(prefix='dewawi-button-firefox-')
        # Disable background services in this disposable test profile.
        (Path(self.profile.name) / 'user.js').write_text('user_pref("datareporting.policy.dataSubmissionEnabled", false);\nuser_pref("app.normandy.enabled", false);\nuser_pref("browser.newtabpage.enabled", false);\n')
        self.log = (args.output / 'firefox.log').open('w')
        port = browser.free_port()
        self.process = subprocess.Popen(['firefox', '--headless', '--no-remote', '--profile', self.profile.name, '--remote-debugging-port', str(port), 'about:blank'], stdout=self.log, stderr=self.log)
        self.loop = asyncio.new_event_loop()
        asyncio.set_event_loop(self.loop)
        self.sequence = 0
        for _ in range(100):
            try:
                self.ws = self.loop.run_until_complete(websockets.connect(f'ws://127.0.0.1:{port}/session', open_timeout=1))
                break
            except OSError:
                time.sleep(.1)
        else:
            self.close()
            raise RuntimeError('Firefox BiDi unavailable; see firefox.log')
        self.version = self.call('session.new', {'capabilities': {}})['capabilities']['browserVersion']
        self.context = self.call('browsingContext.getTree', {})['contexts'][0]['context']

    def call(self, method, params):
        async def exchange():
            self.sequence += 1
            await self.ws.send(json.dumps({'id': self.sequence, 'method': method, 'params': params}))
            while True:
                response = json.loads(await asyncio.wait_for(self.ws.recv(), 20))
                if response.get('id') == self.sequence:
                    if response['type'] != 'success':
                        raise RuntimeError(response)
                    return response['result']
        return self.loop.run_until_complete(exchange())

    def execute(self, script):
        result = self.call('script.evaluate', {'expression': 'JSON.stringify((function(){' + script + '})())', 'target': {'context': self.context}, 'awaitPromise': True})
        if result['type'] != 'success':
            raise RuntimeError(result)
        if result['result']['type'] == 'undefined':
            return None
        return json.loads(result['result']['value'])

    def emulate_viewport(self, width, height):
        self.call('browsingContext.setViewport', {'context': self.context, 'viewport': {'width': width, 'height': height}})

    def navigate(self, url):
        self.call('browsingContext.navigate', {'context': self.context, 'url': url, 'wait': 'complete'})
        self.call('browsingContext.activate', {'context': self.context})
        # BiDi can leave browser chrome focused after navigation. Focus page content
        # with a real click in the empty page margin before testing keyboard input.
        self.call('input.performActions', {'context': self.context, 'actions': [{'type': 'pointer', 'id': 'mouse', 'parameters': {'pointerType': 'mouse'}, 'actions': [{'type': 'pointerMove', 'x': 1, 'y': 1}, {'type': 'pointerDown', 'button': 0}, {'type': 'pointerUp', 'button': 0}]}]})

    def keys(self, key):
        value = browser.KEYS[key]
        self.call('input.performActions', {'context': self.context, 'actions': [{'type': 'key', 'id': 'audit', 'actions': [{'type': 'keyDown', 'value': value}, {'type': 'keyUp', 'value': value}]}]})

    def shift_tab(self):
        actions = [{'type': t, 'value': browser.KEYS[k]} for t, k in [('keyDown', 'shift'), ('keyDown', 'tab'), ('keyUp', 'tab'), ('keyUp', 'shift')]]
        self.call('input.performActions', {'context': self.context, 'actions': [{'type': 'key', 'id': 'keyboard', 'actions': actions}]})

    def screenshot(self):
        return base64.b64decode(self.call('browsingContext.captureScreenshot', {'context': self.context})['data'])

    def close(self):
        if hasattr(self, 'ws'):
            self.loop.run_until_complete(self.ws.close())
        self.process.terminate()
        self.process.wait(timeout=10)
        self.loop.close()
        self.log.close()
        self.profile.cleanup()


if args.standard_actions:
    spec = importlib.util.spec_from_file_location('delete_apply_browser', root / 'tests/ui/delete-apply-browser.py')
    action_probe = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(action_probe)
    action_probe.run(root, browser, Firefox if args.engine == 'firefox' else browser.WebDriver, args.engine, args.output)
    raise SystemExit(0)

if args.document_icons:
    spec = importlib.util.spec_from_file_location('svg_document_context', root / 'tests/ui/svg-document-context.py')
    document_probe = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(document_probe)
    document_probe.run(root, browser, Firefox if args.engine == 'firefox' else browser.WebDriver, args.engine, args.output)
    raise SystemExit(0)


def php(file, *options):
    return json.loads(subprocess.check_output(['php', str(root / 'tests/ui' / file), *options], text=True))


pages, replies = {}, {}
for locale in ['de_DE', 'en_US']:
    fixtures = php('filter-buttons.php', 'fixtures', locale, 'audit')
    for route, fixture in fixtures.items():
        if route in ['contacts/email', 'items/inventory', 'admin/paymentmethod']:
            continue
        profiles = fixture.get('audit', {'index': fixture['page']})
        for action, page in profiles.items():
            key = f'/{locale}/{route}/{action}'
            pages[key] = page['html'].encode()
            if action in ['index', 'select']:
                replies[key] = fixture['auditSearch'][action] if 'auditSearch' in fixture else fixture['search']['clear']
    for layout in ['layout', 'plain']:
        key = f'/{locale}/inquiry/index/{layout}'
        pages[key] = php('inquiry-buttons.php', 'render', locale, 'index', layout)['html'].encode()
    for layout in ['layout', 'plain']:
        key = f'/{locale}/inquiry/edit/{layout}'
        pages[key] = php('inquiry-buttons.php', 'render', locale, 'edit', layout)['html'].encode()

setup = r'''
window.auditCalls=[];window.auditErrors=[];window.auditSubmit=[];window.auditNavigations=[];
window.setLocation=function(url){auditNavigations.push(url)};
addEventListener('error',function(e){auditErrors.push(e.message||'resource error')},true);
$.ajax=function(o){
    if(o.url!==baseUrl+'/'+module+'/'+controller+'/search')throw Error('Unexpected business request: '+o.url);
    auditCalls.push({url:o.url,data:o.data});
    var d=$.Deferred();window.auditReply=function(r){if(o.success)o.success(r);d.resolve(r);if(o.complete)o.complete()};
    return d.promise({abort:function(){if(o.complete)o.complete()}});
};
$(document).on('submit','form',function(e){e.preventDefault();auditSubmit.push({type:e.originalEvent.submitter.type,name:e.originalEvent.submitter.name})});
'''.encode()


class Pages(BaseHTTPRequestHandler):
    def do_GET(self):
        path = urlparse(self.path).path
        if path in pages:
            payload, kind = pages[path], 'text/html; charset=utf-8'
        elif path in ['/fixture/setup.js', '/fixture/toolbar-setup.js']:
            payload, kind = setup, 'text/javascript'
        else:
            asset = (root / path.removeprefix('/fixture/')).resolve()
            if not path.startswith('/fixture/') or not asset.is_relative_to(root) or asset.suffix not in ['.js', '.css', '.png', '.gif', '.svg', '.ico'] or not asset.is_file():
                self.send_error(404)
                return
            payload = asset.read_bytes()
            kind = {'.js': 'text/javascript', '.css': 'text/css', '.svg': 'image/svg+xml'}.get(asset.suffix, 'application/octet-stream')
        self.send_response(200)
        self.send_header('Content-Type', kind)
        self.send_header('Content-Security-Policy', "frame-ancestors 'self'")
        self.end_headers()
        self.wfile.write(payload)

    def log_message(self, *args):
        pass


server = ThreadingHTTPServer(('127.0.0.1', 0), Pages)
Thread(target=server.serve_forever, daemon=True).start()
driver = None
checks, measurements, limitations = 0, [], set()


def check(value, message):
    global checks
    checks += 1
    if not value:
        raise AssertionError(message)


try:
    driver = {'chromium': browser.WebDriver, 'firefox': Firefox}[args.engine]()
    for key in pages:
        # Existing Chromium baselines cover the other widths; 320px closes their gap.
        for width in ([320] if args.engine == 'chromium' else [320, 390, 768, 1280]):
            driver.emulate_viewport(width, 1100)
            driver.navigate(f'http://127.0.0.1:{server.server_port}{key}')
            deadline = time.monotonic() + 5
            while not driver.execute('return document.readyState==="complete"&&!!window.DewawiToolbar&&!!window.auditCalls'):
                if time.monotonic() > deadline:
                    raise TimeoutError(key)
                time.sleep(.02)
            m = driver.execute(r'''
                var bs=[...document.querySelectorAll('.dw-btn--control')].filter(b=>b.getClientRects().length), ids=[...document.querySelectorAll('[id]')].map(b=>b.id);
                return {width:innerWidth,document:document.documentElement.scrollWidth,header:document.querySelector('.dw-app-header__meta')?.getBoundingClientRect().right||0,
                count:bs.length,rowControls:document.querySelectorAll('.dw-row-actions .dw-btn--control').length,unique:ids.length===new Set(ids).size,symbols:document.querySelectorAll('.dw-icon-symbols').length,errors:auditErrors,
                bad:bs.filter(b=>{var r=b.getBoundingClientRect(),s=getComputedStyle(b),i=b.querySelector('svg'),u=i?.querySelector('use'),l=b.querySelector('.dw-btn__label'),name=b.getAttribute('aria-label')||b.textContent.trim();
                    return !name||/^(TOOLBAR_|CLEAR$|CONTACT_INQUIRIES_)/.test(name)||b.type!=='button'&&b.type!=='submit'||r.height<(innerWidth<768?44:32)-.5||!i||!u||u.getBBox().width<=0||!document.querySelector(u.getAttribute('href'))||i.getAttribute('aria-hidden')!=='true'||i.getAttribute('focusable')!=='false'||getComputedStyle(i).stroke!==s.color||s.backgroundImage!=='none'||l&&Math.abs(l.getBoundingClientRect().left-i.getBoundingClientRect().right-8)>1}).map(b=>b.outerHTML),
                rows:[...document.querySelectorAll('.dw-table--contacts-contact tbody tr,.dw-table--tasks-task tbody tr')].slice(0,2).map(r=>r.getBoundingClientRect().height)};
            ''')
            measurements.append({'page': key, 'viewport': width, **m})
            check(m['width'] == width, f'{key}: wrong viewport {m}')
            check(m['count'] > 0 and m['symbols'] == 1 and m['unique'], f'{key}/{width}: missing controls/symbols or duplicate IDs')
            check(not m['bad'] and not m['errors'], f'{key}/{width}: invalid SVG/labels/semantics/geometry: {m}')
            if any('/' + route + '/index' in key for route in ['contacts/contact', 'tasks/task']) and width < 768:
                check(m['rows'] and max(m['rows']) <= 116, f'{key}/{width}: mobile row height {m}')
            check(driver.execute(r'''
                window.b=document.querySelector('.dw-btn--control:not(:disabled)');window.i=b.querySelector('svg');var html=b.innerHTML;b.disabled=true;
                i.querySelector('use').dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true}));var blocked=!auditCalls.length;
                setButtonLoading($(b),true,'Working');var busy=b.getAttribute('aria-busy')==='true';setButtonLoading($(b),false);
                var restored=b.disabled&&b.querySelector('svg')===i&&b.innerHTML===html;b.disabled=false;b.setAttribute('aria-disabled','true');b.click();b.removeAttribute('aria-disabled');
                return blocked&&busy&&restored&&!auditCalls.length;
            '''), f'{key}/{width}: disabled/loading/DOM restore')
            native_input = args.engine == 'chromium' or driver.execute('return document.hasFocus()')
            if native_input:
                driver.execute('b.focus();return true')
                driver.keys('tab')
                driver.shift_tab()
                focus = driver.execute('return {active:document.activeElement===b,documentFocus:document.hasFocus(),visible:b.matches(":focus-visible"),outline:getComputedStyle(b).outlineWidth}')
                check(focus['active'] and focus['visible'] and focus['outline'] == '2px', f'{key}/{width}: visible keyboard focus {focus}')
            else:
                # The same limitation occurs in a plain HTML button without ERP CSS/JS.
                limitations.add('Firefox headless document.hasFocus=false: native keyboard/visible focus NOT TESTED; rendering and scripted interaction only')
                focus = {'documentFocus': False, 'status': 'NOT TESTED'}
            measurements[-1]['focus'] = focus
            if key.endswith('/index'):
                driver.execute('window.openerButton=document.querySelector("button[data-action=filter]");openerButton.focus();return true')
                driver.keys('space') if native_input else driver.execute('openerButton.click();return true')
                check(driver.execute('return openerButton.getAttribute("aria-expanded")==="true"&&document.getElementById(openerButton.getAttribute("aria-controls")).classList.contains("is-open")&&!auditCalls.length'), f'{key}/{width}: disclosure open')
                driver.keys('space') if native_input else driver.execute('openerButton.click();return true')
                check(driver.execute('return openerButton.getAttribute("aria-expanded")==="false"&&!document.getElementById(openerButton.getAttribute("aria-controls")).classList.contains("is-open")'), f'{key}/{width}: disclosure close')
            if key in replies:
                driver.execute('window.chip=document.querySelector("button[data-action=clear-filter]");chip.focus();return true')
                driver.keys('enter') if native_input else driver.execute('chip.click();return true')
                deadline = time.monotonic() + 5
                while not driver.execute('return auditCalls.length===1&&!!window.auditReply'):
                    if time.monotonic() > deadline:
                        raise TimeoutError('Captured search missing: ' + key)
                    time.sleep(.02)
                driver.execute('auditReply(' + json.dumps(replies[key]) + ');return true')
                check(driver.execute('return !document.querySelector("button[data-action=clear-filter]")&&document.querySelectorAll(".dw-icon-symbols").length===1&&!auditErrors.length'), f'{key}/{width}: AJAX chip refresh')
                check(driver.execute('return document.querySelectorAll(".dw-row-actions .dw-btn--control").length===' + str(m['rowControls']) + '&&[...document.querySelectorAll(".dw-row-actions .dw-btn--control use")].every(u=>u.getBBox().width>0)'), f'{key}/{width}: AJAX lost row controls/SVG geometry')
                check(driver.execute('var row=document.querySelector(".dw-row-actions button.dw-btn--control[data-action=view],.dw-row-actions button.dw-btn--control[data-action=edit]");if(!row)return true;row.querySelector("use").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}));return auditNavigations.length===1&&auditNavigations[0]===baseUrl+"/"+row.dataset.module+"/"+row.dataset.controller+"/"+row.dataset.action+"/id/"+row.dataset.id'), f'{key}/{width}: dynamic SVG use navigation')
            if args.engine == 'firefox' and (key, width) in [('/de_DE/contacts/contact/index', 390), ('/de_DE/tasks/task/index', 320), ('/en_US/inquiry/edit/layout', 1280)]:
                driver.execute('var row=document.querySelector(".dw-row-actions button.dw-btn--control");if(row){row.scrollIntoView({block:"center"});row.focus()}else{b.scrollIntoView({block:"center"});b.focus()}return true')
                (args.output / ('firefox-' + key.strip('/').replace('/', '-') + '-' + str(width) + '.png')).write_bytes(driver.screenshot())
            print(f'PASS: {args.engine} {key} {width}px', flush=True)
    print(f'PASS: {checks} {args.engine} integration assertions; version {getattr(driver, "version", "see chromedriver")}', flush=True)
    for limitation in sorted(limitations):
        print('NOT TESTED: ' + limitation, flush=True)
finally:
    (args.output / (args.engine + '-measurements.json')).write_text(json.dumps(measurements, indent=2))
    (args.output / (args.engine + '-limitations.json')).write_text(json.dumps(sorted(limitations), indent=2))
    if driver:
        driver.close()
    server.shutdown()
