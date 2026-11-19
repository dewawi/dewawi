"""Exercise actual list models and delegated row actions; all business I/O stays in memory."""
from pathlib import Path
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from threading import Thread
from urllib.parse import unquote, urlparse
import base64
import importlib.util
import json
import mimetypes
import subprocess
import sys

sys.dont_write_bytecode = True
root = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('list_driver', root / 'tests/site/accessibility-http.py')
browser = importlib.util.module_from_spec(spec)
spec.loader.exec_module(browser)
checks = 0

def check(value, message):
    global checks
    checks += 1
    if not value:
        raise AssertionError(message)

groups = {'contacts': ['view', 'edit', 'copy'], 'items': ['edit', 'copy'], 'tasks': ['view', 'edit', 'copy']}
groups.update({'admin/' + c: ['copy'] for c in ['country', 'uom', 'deliverytime', 'manufacturer', 'warehouse', 'shippingmethod']})
pages, searches = {}, {}
for locale in ['de_DE', 'en_US']:
    searches[locale.replace('_', '-')] = {}
    for module in groups:
        for state in ['', 'readonly']:
            result = subprocess.run(['php', str(root / 'tests/ui/toolbar-buttons.php'), 'render', locale, module, 'index', 'layout', state, 'index', 'list-actions'], capture_output=True, text=True, check=True)
            pages[f'/fixture/{locale}/{module}/{state}'] = json.loads(result.stdout)['html'].encode()
        result = subprocess.run(['php', str(root / 'tests/ui/toolbar-buttons.php'), 'render', locale, module, 'search', 'layout', '', 'index', 'list-actions'], capture_output=True, text=True, check=True)
        searches[locale.replace('_', '-')][module] = json.loads(result.stdout)

setup = ('var fixtureSearches=' + json.dumps(searches).replace('<', '\\u003c') + ';' + r'''
window.calls=[];window.navigations=[];window.pendingCopy=null;window.confirmations=0;
window.confirm=function(){confirmations++;return false;};
window.setLocation=function(url){navigations.push(url);};
$.ajax=function(o){
    calls.push({url:o.url,data:o.data,type:o.type});
    var d=$.Deferred();
    if(o.url===baseUrl+'/'+module+'/'+controller+'/search'){
        var fixtures=fixtureSearches[document.documentElement.lang],r=fixtures[module+'/'+controller]||fixtures[module];
        if(o.success)o.success(r);d.resolve(r);if(o.complete)o.complete();
    }else if(o.url===baseUrl+'/'+module+'/'+controller+'/copy/id/18'){
        pendingCopy=function(ok){
            if(ok){var r={ok:true,saved:true,id:117};if(o.success)o.success(r);d.resolve(r);}
            else {var e={status:403,responseJSON:{ok:false,saved:false}};if(o.error)o.error(e);d.reject(e);}
            if(o.complete)o.complete();pendingCopy=null;
        };
    }else throw Error('Unexpected business request: '+o.url);
    return d.promise({abort:function(){}});
};
''').encode()

class ListPages(BaseHTTPRequestHandler):
    def do_GET(self):
        path = unquote(urlparse(self.path).path)
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
        self.send_header('Content-Security-Policy', "frame-ancestors 'self'")
        self.end_headers()
        self.wfile.write(payload)

    def log_message(self, *args):
        pass

server = ThreadingHTTPServer(('127.0.0.1', 0), ListPages)
Thread(target=server.serve_forever, daemon=True).start()
driver = None
try:
    driver = browser.WebDriver()
    driver.command('POST', '/goog/cdp/execute', {'cmd': 'Page.addScriptToEvaluateOnNewDocument', 'params': {'source': 'window.fixtureErrors=[];addEventListener("error",function(e){fixtureErrors.push(e.message||e.target.src||e.target.href||"resource error")},true)'}})
    for locale in ['de_DE', 'en_US']:
        for module, actions in groups.items():
            route = '/fixture/' + (module if '/' in module else module + '/' + {'contacts': 'contact', 'items': 'item', 'tasks': 'task'}[module])
            for width in [390, 768, 1280]:
                context = f'{locale}/{module}/{width}'
                driver.emulate_viewport(width, 1100)
                driver.navigate(f'http://127.0.0.1:{server.server_port}/fixture/{locale}/{module}/')
                driver.wait('return !!window.DewawiToolbar&&!!window.calls')
                metrics = driver.execute(r'''
                    return {width:document.documentElement.scrollWidth,errors:fixtureErrors,symbols:document.querySelectorAll('.dw-icon-symbols').length,
                        tables:[...document.querySelectorAll('.dw-table-wrap')].map(e=>({width:e.clientWidth,scroll:e.scrollWidth,overflow:getComputedStyle(e).overflowX})),
                        rows:[...document.querySelectorAll('tbody .dw-row')].map(e=>e.getBoundingClientRect().height),
                        buttons:[...document.querySelectorAll('.dw-row-actions .dw-btn--control')].map(b=>{
                            var i=b.querySelector('svg'),u=i.querySelector('use'),r=b.getBoundingClientRect(),ir=i.getBoundingClientRect(),s=getComputedStyle(b);
                            return {name:b.name,id:b.dataset.id,label:b.getAttribute('aria-label'),title:b.title,type:b.type,w:r.width,h:r.height,center:Math.abs(r.y+r.height/2-ir.y-ir.height/2),
                                icon:u.getAttribute('href'),geometry:u.getBBox().width,stroke:getComputedStyle(i).stroke,color:s.color,bg:s.backgroundImage,decorative:i.getAttribute('aria-hidden')==='true'&&i.getAttribute('focusable')==='false'};
                        })};
                ''')
                check(not metrics['errors'] and metrics['symbols'] == 1, f'{context}: scripts/symbols: {metrics}')
                check([b['name'] for b in metrics['buttons']] == actions * 2 and [b['id'] for b in metrics['buttons']] == ['17'] * len(actions) + ['18'] * len(actions), f'{context}: action/row scope')
                check(metrics['width'] <= width and all(t['scroll'] <= t['width'] + 1 or t['overflow'] in ['auto', 'scroll'] for t in metrics['tables']), f'{context}: uncontained table overflow: {metrics}')
                check(all(32 <= h <= 250 for h in metrics['rows']), f'{context}: unexpected row heights: {metrics["rows"]}')
                for b in metrics['buttons']:
                    check(b['type'] == 'button' and b['label'] == b['title'] and b['label'] and not b['label'].startswith('TOOLBAR_'), f'{context}: native semantics/name/tooltip: {b}')
                    check(b['icon'] == '#dw-icon-' + b['name'] and b['geometry'] > 0 and b['decorative'] and b['stroke'] == b['color'] and b['bg'] == 'none', f'{context}: icon/PNG overlap: {b}')
                    check(b['w'] >= (44 if width < 768 else 32) and b['h'] >= (44 if width < 768 else 32) and b['center'] < 1, f'{context}: target size/alignment: {b}')
                ax = driver.command('POST', '/goog/cdp/execute', {'cmd': 'Accessibility.getFullAXTree', 'params': {}})
                names = {n.get('name', {}).get('value') for n in ax['nodes'] if n.get('role', {}).get('value') == 'button'}
                check({b['label'] for b in metrics['buttons']} <= names, f'{context}: AX names')
                check(driver.execute('return [...document.querySelectorAll(".dw-row-actions button[name=delete]")].every(b=>!b.querySelector("svg")&&!b.classList.contains("dw-btn--control"))&&!document.querySelector(".dw-row-actions button[name=pdf]")'), f'{context}: delete/PDF migrated')
                check(driver.execute('return [...document.querySelectorAll("tbody a")].some(a=>a.getAttribute("href").endsWith(arguments[0]+"/edit/id/18"))', [route]), f'{context}: native link target')
                selector = '.dw-row-actions button[name=copy][data-id="18"]'
                driver.execute('document.querySelector(arguments[0]).focus()', [selector])
                driver.keys('tab')
                driver.shift_tab()
                check(driver.execute('return document.activeElement.matches(arguments[0])&&document.activeElement.matches(":focus-visible")&&getComputedStyle(document.activeElement).outlineWidth==="2px"', [selector]), f'{context}: keyboard focus')
                driver.execute('window.hoverButton=document.querySelector(arguments[0]);hoverButton.scrollIntoView({block:"center",inline:"center"})', [selector])
                driver.command('POST', '/actions', {'actions': [{'type': 'pointer', 'id': 'mouse', 'parameters': {'pointerType': 'mouse'}, 'actions': [{'type': 'pointerMove', 'duration': 0, 'origin': {'element-6066-11e4-a52e-4f735466cecf': driver.element(selector)}, 'x': 0, 'y': 0}]}]})
                driver.execute('var p=document.createElement("span");p.style.color=getComputedStyle(hoverButton).getPropertyValue("--dw-btn-hover-background");document.body.append(p);window.hoverColor=getComputedStyle(p).color;p.remove()')
                driver.wait('return hoverButton.matches(":hover")&&getComputedStyle(hoverButton).backgroundColor===hoverColor')
                check(driver.execute('return hoverButton.matches(":hover")'), f'{context}: hover')
                driver.execute('$("input[data-select-id]").first().prop("checked",true);navigations=[];calls=[]')
                for name in [a for a in actions if a != 'copy']:
                    driver.execute('document.querySelector(".dw-row-actions button[name="+arguments[0]+"][data-id=\\"18\\"]").focus()', [name])
                    driver.keys('enter' if name == 'view' else 'space')
                    check(driver.execute('return navigations.pop()===arguments[0]+"/"+arguments[1]+"/id/18"&&!calls.length&&document.querySelector("input[data-select-id]:checked").dataset.selectId==="17"', [route, name]), f'{context}: row navigation used checkbox selection')
                check(driver.execute(r'''
                    var b=$(arguments[0]),i=b.find('svg')[0],html=b.html();b.prop('disabled',true);i.querySelector('use').dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true}));
                    var blocked=!calls.length&&!navigations.length&&parseFloat(getComputedStyle(b[0]).opacity)<1;
                    setButtonLoading(b,true,'Working');setButtonLoading(b,false);var restored=b.prop('disabled')&&b.html()===html&&b.find('svg')[0]===i;b.prop('disabled',false);
                    b.attr('aria-disabled','true');b[0].click();var ariaBlocked=!calls.length;b.removeAttr('aria-disabled');return blocked&&restored&&ariaBlocked;
                ''', [selector]), f'{context}: disabled/loading guard')
                driver.execute('window.copyButton=document.querySelector(arguments[0]);window.copyIcon=copyButton.querySelector("svg");copyButton.focus()', [selector])
                driver.keys('enter')
                driver.execute('copyIcon.querySelector("use").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}));$("input[data-select-id]").prop("checked",false).last().prop("checked",true);document.querySelector(".dw-toolbar--bottom button[name=copy]").click()')
                check(driver.execute('return calls.length===1&&calls[0].url===arguments[0]+"/copy/id/18"&&calls[0].type==="POST"&&copyButton.disabled&&copyButton.getAttribute("aria-busy")==="true"&&getComputedStyle(copyIcon).display==="none"', [route]), f'{context}: exact copy target/shared request lock')
                driver.execute('pendingCopy(false)')
                check(driver.execute('return calls.length===1&&!copyButton.disabled&&copyButton.querySelector("svg")===copyIcon&&document.querySelector("input[data-select-id]:checked").dataset.selectId==="18"&&!confirmations'), f'{context}: failed copy lost state or introduced confirmation')
                driver.execute('copyButton.focus()')
                driver.keys('space')
                check(driver.execute('return calls.length===2&&copyButton.disabled'), f'{context}: retry failed')
                driver.execute('window.oldRows=document.querySelector("tbody");window.oldFooter=document.querySelector(".dw-toolbar--bottom");pendingCopy(true)')
                driver.wait('return calls.length===3')
                check(driver.execute('return calls[2].url===arguments[0]+"/search"&&calls[2].data.context_action==="index"&&oldRows!==document.querySelector("tbody")&&oldFooter!==document.querySelector(".dw-toolbar--bottom")&&document.querySelectorAll(".dw-icon-symbols").length===1&&!document.querySelector("input[data-select-id]:checked")', [route]), f'{context}: successful copy AJAX/selection contract')
                check(driver.execute('return document.querySelectorAll(".dw-row-actions .dw-btn--control use").length===arguments[0]', [2 * len(actions)]), f'{context}: dynamic SVG missing')
                driver.execute('window.dynamic=document.querySelector(arguments[0]);dynamic.querySelector("use").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}))', [selector])
                check(driver.execute('return calls.length===4&&calls[3].url===arguments[0]+"/copy/id/18"&&dynamic.disabled', [route]), f'{context}: delegated dynamic SVG click')
                driver.execute('pendingCopy(false)')
                check(driver.execute('return !dynamic.disabled&&!fixtureErrors.length&&!confirmations'), f'{context}: dynamic restore/script errors')
                if locale == 'de_DE' and module in ['contacts', 'items', 'tasks', 'admin/uom']:
                    driver.execute('document.querySelector(arguments[0]).focus()', [selector])
                    shot = driver.command('GET', '/screenshot')
                    Path(f'/tmp/dewawi-b10-{module.replace("/", "-")}-{width}.png').write_bytes(base64.b64decode(shot))
                print(f'PASS: {context}; row heights={metrics["rows"]}; table overflow contained', flush=True)
            driver.navigate(f'http://127.0.0.1:{server.server_port}/fixture/{locale}/{module}/readonly')
            driver.wait('return !!window.calls')
            check(driver.execute('var bs=[...document.querySelectorAll(".dw-row-actions .dw-btn--control")];bs.forEach(b=>b.querySelector("use").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true})));return bs.length===arguments[0]&&bs.every(b=>b.disabled)&&!calls.length&&!navigations.length', [2 * len(actions)]), f'{locale}/{module}: readonly list')
    print(f'PASS: {checks} list action browser assertions')
finally:
    if driver:
        driver.close()
    server.shutdown()
    server.server_close()
