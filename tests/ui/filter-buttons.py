"""Exercise shared filter chips/disclosure with real PHP output and captured search I/O."""
from pathlib import Path
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from threading import Thread
from urllib.parse import unquote, urlparse, parse_qs
import base64
import importlib.util
import json
import mimetypes
import subprocess
import sys

sys.dont_write_bytecode = True
root = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('filter_driver', root / 'tests/site/accessibility-http.py')
browser = importlib.util.module_from_spec(spec)
spec.loader.exec_module(browser)
checks = 0

def check(value, message):
    global checks
    checks += 1
    if not value:
        raise AssertionError(message)

groups = ['contacts/inquiry', 'contacts/contact', 'items/item', 'tasks/task']
groups += ['admin/' + c for c in ['country', 'uom', 'deliverytime', 'manufacturer', 'warehouse', 'shippingmethod']]
groups += ['statistics/' + c for c in ['customer', 'quote', 'turnover']]
case = next((arg.split('=', 1)[1] for arg in sys.argv if arg.startswith('--case=')), None)
if case:
    if case not in groups:
        raise ValueError('Unknown filter test case')
    groups = [case]
pages, searches = {}, {}
for locale in ['de_DE', 'en_US']:
    fixtures = json.loads(subprocess.check_output(['php', str(root / 'tests/ui/filter-buttons.php'), 'fixtures', locale], text=True))
    searches[locale.replace('_', '-')] = {route: value['search'] for route, value in fixtures.items()}
    for route, value in fixtures.items():
        pages[f'/fixture/{locale}/{route}/'] = value['page']['html'].encode()
        pages[f'/long/{locale}/{route}/'] = value['long']['html'].encode()
        if route == 'tasks/task':
            for state in ['multi', 'date']:
                pages[f'/{state}/{locale}/{route}/'] = value[state]['html'].encode()
        if route in ['contacts/email', 'admin/paymentmethod']:
            pages[f'/legacy-select/{locale}/{route}/'] = value['legacy-select']['html'].encode()
    for route in ['contacts/contact', 'items/item', 'tasks/task', 'admin/country']:
        value = json.loads(subprocess.check_output(['php', str(root / 'tests/ui/filter-buttons.php'), 'render', locale, route, 'select', 'plain', 'index', 'active'], text=True))
        pages[f'/select/{locale}/{route}/'] = value['html'].encode()
        searches[locale.replace('_', '-')][route]['select-clear'] = json.loads(subprocess.check_output(['php', str(root / 'tests/ui/filter-buttons.php'), 'render', locale, route, 'search', 'plain', 'select', 'clear'], text=True))

setup = ('var fixtureSearches=' + json.dumps(searches).replace('<', '\\u003c') + ';' + r'''
window.calls=[];window.pending=null;window.replyActive=false;
$.ajax=function(o){
    if(o.url!==baseUrl+'/'+module+'/'+controller+'/search')throw Error('Unexpected business request: '+o.url);
    calls.push({url:o.url,type:o.type,data:o.data});
    var d=$.Deferred();window.pending=function(ok){
        if(ok){var f=fixtureSearches[document.documentElement.lang][module+'/'+controller],r=f[o.data.context_action==='select'?'select-clear':(replyActive?'active':'clear')];if(o.success)o.success(r);d.resolve(r);}
        else {var e={status:500};if(o.error)o.error(e,'error');d.reject(e);}
        if(o.complete)o.complete();pending=null;
    };
    return d.promise({abort:function(){if(o.complete)o.complete();}});
};
''').encode()

class FilterPages(BaseHTTPRequestHandler):
    def do_GET(self):
        path = unquote(urlparse(self.path).path)
        query = parse_qs(urlparse(self.path).query)
        if 'fixture_locale' in query:
            route = '/'.join(path.removeprefix('/fixture/').split('/')[:2])
            path = '/' + query['fixture_mode'][0] + '/' + query['fixture_locale'][0] + '/' + route + '/'
        if path in pages:
            payload, kind = pages[path], 'text/html; charset=utf-8'
        elif path == '/fixture/toolbar-setup.js':
            payload, kind = setup, 'text/javascript'
        elif path.startswith(('/fixture/css/', '/fixture/js/', '/fixture/library/', '/fixture/images/')) or path == '/fixture/favicon.ico':
            asset = (root / path.removeprefix('/fixture/')).resolve()
            if not asset.is_relative_to(root) or asset.suffix.lower() not in ['.js', '.css', '.png', '.gif', '.svg', '.ico', '.jpg', '.woff', '.woff2'] or not asset.is_file():
                self.send_error(404)
                return
            payload, kind = asset.read_bytes(), mimetypes.guess_type(asset)[0] or 'application/octet-stream'
        else:
            self.send_error(404)
            return
        self.send_response(200)
        self.send_header('Content-Type', kind)
        self.end_headers()
        self.wfile.write(payload)

    def log_message(self, *args):
        pass

server = ThreadingHTTPServer(('127.0.0.1', 0), FilterPages)
Thread(target=server.serve_forever, daemon=True).start()
driver = None

def navigate_fixture(locale, route, mode='fixture'):
    driver.command('DELETE', '/cookie')
    action = 'select' if mode in ['select', 'legacy-select'] else 'index'
    params = '&parent=processes%7Cprocess%7C501&setid=7' if mode == 'select' else ''
    driver.navigate(f'http://127.0.0.1:{server.server_port}/fixture/{route}/{action}?fixture_locale={locale}&fixture_mode={mode}' + params)

try:
    driver = browser.WebDriver()
    driver.command('POST', '/goog/cdp/execute', {'cmd': 'Page.addScriptToEvaluateOnNewDocument', 'params': {'source': 'window.fixtureErrors=[];addEventListener("error",function(e){fixtureErrors.push(e.message||e.target.src||e.target.href||"resource error")},true)'}})
    for locale in ['de_DE', 'en_US']:
        for route in groups:
            for width in [320, 390, 768, 1280]:
                context = f'{locale}/{route}/{width}'
                driver.emulate_viewport(width, 1100)
                navigate_fixture(locale, route)
                driver.wait('return !!window.calls&&!!window.DewawiToolbar')
                metrics = driver.execute(r'''
                    window.chip=document.querySelector('button[data-action="clear-filter"]');window.icon=chip.querySelector('svg');window.field=chip.dataset.filter;
                    var r=chip.getBoundingClientRect(),i=icon.getBoundingClientRect(),p=chip.parentElement.getBoundingClientRect();return {label:chip.getAttribute('aria-label'),text:chip.textContent.trim(),type:chip.type,name:chip.getAttribute('name'),value:chip.getAttribute('value'),height:r.height,gap:chip.querySelector('.dw-btn__label').getBoundingClientRect().left-i.right,center:Math.abs(r.y+r.height/2-i.y-i.height/2),fits:r.x>=0&&r.right<=innerWidth&&r.right<=p.right+1,decorative:icon.getAttribute('aria-hidden'),geometry:icon.querySelector('use').getBBox().width,bg:getComputedStyle(chip).backgroundImage};
                ''')
                check(metrics['type'] == 'button' and metrics['name'] is None and metrics['value'] is None, f'{context}: native contract')
                check(metrics['label'] == ('Filter entfernen: ' if locale == 'de_DE' else 'Remove filter: ') + metrics['text'] and 'TOOLBAR_' not in metrics['label'], f'{context}: translated accessible name')
                check(metrics['height'] >= (44 if width < 768 else 32) and abs(metrics['gap']-8) < 1 and metrics['center'] < 1 and metrics['fits'], f'{context}: target/spacing/overflow: {metrics}')
                check(metrics['decorative'] == 'true' and metrics['geometry'] > 0 and metrics['bg'] == 'none', f'{context}: SVG/PNG overlap')
                ax = driver.command('POST', '/goog/cdp/execute', {'cmd': 'Accessibility.getFullAXTree', 'params': {}})
                check(any(n.get('role', {}).get('value') == 'button' and n.get('name', {}).get('value') == metrics['label'] for n in ax['nodes']), f'{context}: AX name')
                driver.execute('window.filterToggle=document.querySelector("button[data-action=filter]");window.panel=document.getElementById(filterToggle.getAttribute("aria-controls"));filterToggle.focus()')
                driver.keys('enter')
                check(driver.execute('return filterToggle.getAttribute("aria-expanded")==="true"&&panel.classList.contains("is-open")&&!calls.length'), f'{context}: disclosure open')
                driver.keys('space')
                check(driver.execute('return filterToggle.getAttribute("aria-expanded")==="false"&&!panel.classList.contains("is-open")&&!calls.length'), f'{context}: disclosure close')
                driver.execute('chip.scrollIntoView({block:"center"});chip.focus()')
                driver.keys('tab')
                driver.shift_tab()
                check(driver.execute('return document.activeElement===chip&&chip.matches(":focus-visible")&&getComputedStyle(chip).outlineWidth==="2px"'), f'{context}: keyboard focus')
                driver.command('POST', '/actions', {'actions': [{'type': 'pointer', 'id': 'mouse', 'parameters': {'pointerType': 'mouse'}, 'actions': [{'type': 'pointerMove', 'duration': 0, 'origin': {'element-6066-11e4-a52e-4f735466cecf': driver.element('button[data-action=clear-filter]')}, 'x': 0, 'y': 0}]}]})
                check(driver.execute('return chip.matches(":hover")'), f'{context}: hover')
                check(driver.execute(r'''
                    var html=chip.innerHTML;chip.disabled=true;icon.querySelector('use').dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true}));var blocked=!calls.length;
                    setButtonLoading($(chip),true);var busy=chip.getAttribute('aria-busy')==='true'&&getComputedStyle(icon).display==='none';setButtonLoading($(chip),false);
                    setButtonLoading($(chip),true,'Working');var detached=!icon.isConnected;setButtonLoading($(chip),false);
                    var restored=chip.disabled&&chip.innerHTML===html&&chip.querySelector('svg')===icon;chip.disabled=false;chip.setAttribute('aria-disabled','true');chip.click();chip.removeAttribute('aria-disabled');return blocked&&busy&&detached&&restored&&!calls.length;
                '''), f'{context}: disabled/loading restore')
                if locale == 'de_DE' and route in ['contacts/contact', 'tasks/task', 'admin/country', 'statistics/customer'] and width in [320, 390, 1280]:
                    driver.execute('chip.focus()')
                    Path(f'/tmp/dewawi-b12-{route.replace("/", "-")}-{width}.png').write_bytes(base64.b64decode(driver.command('GET', '/screenshot')))
                driver.execute('filterToggle.click();window.originalFilterToggle=filterToggle;$("input[data-select-id]").first().prop("checked",true);chip.focus()')
                driver.keys('space')
                driver.wait('return calls.length===1&&!!pending')
                check(driver.execute('return calls[0].type==="POST"&&calls[0].url===arguments[0]&&calls[0].data.context_action==="index"&&String(calls[0].data[field])===(field==="sort"?"DESC":"0")&&$("#content").attr("aria-busy")==="true"', ['/fixture/' + route + '/search']), f'{context}: exact filter reset/search request')
                driver.execute('pending(false)')
                check(driver.execute('return chip.isConnected&&filterToggle.getAttribute("aria-expanded")==="true"&&panel.classList.contains("is-open")&&!$("#content").attr("aria-busy")'), f'{context}: failed search lost panel/chip state')
                driver.execute('chip.focus()')
                driver.keys('enter')
                driver.wait('return calls.length===2&&!!pending')
                driver.execute('pending(true)')
                check(driver.execute('return !document.querySelector("button[data-action=clear-filter]")&&filterToggle===originalFilterToggle&&filterToggle.getAttribute("aria-expanded")==="true"&&document.getElementById(filterToggle.getAttribute("aria-controls"))===panel&&document.querySelectorAll(".dw-icon-symbols").length===1&&!document.querySelector("input[data-select-id]:checked")'), f'{context}: successful AJAX replacement/disclosure/selection')
                driver.execute('if(field==="sort")$("[name=sort][value=ASC]").prop("checked",true);else $("[name=country]").val("DE");replyActive=true;search()')
                driver.wait('return calls.length===3&&!!pending')
                driver.execute('pending(true);replyActive=false;window.dynamic=document.querySelector("button[data-action=clear-filter]");dynamic.querySelector("use").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}))')
                driver.wait('return calls.length===4&&!!pending')
                driver.execute('pending(true)')
                check(driver.execute('return !document.querySelector("button[data-action=clear-filter]")&&!fixtureErrors.length&&String($.cookie(field))===(field==="sort"?"DESC":"0")'), f'{context}: dynamic use click/cookie reset/errors')
                print(f'PASS: {context} chips/disclosure/search/error/retry', flush=True)
            driver.emulate_viewport(320, 1100)
            navigate_fixture(locale, route, 'long')
            driver.wait('return !!window.calls')
            check(driver.execute('var b=document.querySelector("button[data-action=clear-filter]"),r=b.getBoundingClientRect();return r.right<=innerWidth&&b.scrollWidth<=b.clientWidth+1&&b.textContent.includes("<unsafe>")&&!b.querySelector("unsafe")'), f'{locale}/{route}: long escaped chip containment')
        for route in ['contacts/contact', 'items/item', 'tasks/task', 'admin/country']:
            for width in [320, 390, 768, 1280]:
                driver.emulate_viewport(width, 1100)
                navigate_fixture(locale, route, 'select')
                driver.wait('return !!window.calls')
                driver.execute('document.querySelector("button[data-action=clear-filter]").focus()')
                driver.keys('enter')
                driver.wait('return calls.length===1&&!!pending')
                check(driver.execute('return calls[0].data.context_action==="select"&&calls[0].data.parent==="processes|process|501"&&String(calls[0].data.setid)==="7"'), f'{locale}/{route}/{width}: Select parent/context lost')
                driver.execute('pending(true)')
                refreshed = driver.execute('return {chips:document.querySelectorAll("button[data-action=clear-filter]").length,errors:fixtureErrors}')
                check(refreshed['chips'] == 0 and not refreshed['errors'], f'{locale}/{route}/{width}: Select refresh: {refreshed}')
        for route in ['contacts/email', 'items/inventory', 'admin/paymentmethod']:
            navigate_fixture(locale, route)
            driver.wait('return !!window.calls')
            check(driver.execute('var c=document.querySelector("button[data-action=clear-filter]"),b=document.querySelector("button[data-action=filter]");b.click();return c&&!c.classList.contains("dw-btn--control")&&!c.querySelector("svg")&&!b.hasAttribute("aria-controls")&&!b.hasAttribute("aria-expanded")&&$(".dw-filter-panel").hasClass("is-open")'), f'{locale}/{route}: excluded helper context changed')
        for state in ['multi', 'date']:
            navigate_fixture(locale, 'tasks/task', state)
            driver.wait('return !!window.calls')
            driver.execute('window.before=collectToolbarData();document.querySelector("button[data-action=clear-filter]").click()')
            driver.wait('return calls.length===1&&!!pending')
            check(driver.execute('return arguments[0]==="multi"?JSON.stringify(calls[0].data.states)===JSON.stringify(["100","101","102","103","104"]):calls[0].data.daterange==="all"&&calls[0].data.from===before.from&&calls[0].data.to===before.to', [state]), f'{locale}/{state}: array/custom-date reset changed')
            driver.execute('pending(true)')
            check(driver.execute('return !document.querySelector("button[data-action=clear-filter]")&&!fixtureErrors.length'), f'{locale}/{state}: reset fragment/errors')
        for route in ['contacts/email', 'admin/paymentmethod']:
            navigate_fixture(locale, route, 'legacy-select')
            driver.wait('return !!window.calls')
            check(driver.execute('var c=document.querySelector("button[data-action=clear-filter]");DewawiToolbar.refresh("<div class=dw-toolbar></div>");return c.isConnected&&!document.querySelector(".dw-filter-panel[id]")&&!c.querySelector("svg")&&!calls.length'), f'{locale}/{route}: legacy popup refresh changed')
    print(f'PASS: {checks} shared filter button browser assertions')
finally:
    if driver:
        driver.close()
    server.shutdown()
    server.server_close()
