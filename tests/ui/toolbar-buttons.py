"""Render the actual shared toolbar helpers, list footer and internal layouts without a database."""
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
tasks_mode = '--tasks' in sys.argv
modules = ['tasks'] if tasks_mode else ['contacts', 'items']
spec = importlib.util.spec_from_file_location('shared_toolbar_driver', root / 'tests/site/accessibility-http.py')
browser = importlib.util.module_from_spec(spec)
spec.loader.exec_module(browser)
checks = 0

def check(value, message):
    global checks
    checks += 1
    if not value:
        raise AssertionError(message)

pages, searches = {}, {}
profiles = [('index', 'layout'), ('select', 'plain'), ('edit', 'layout'), ('view', 'layout')]
for locale in ['de_DE', 'en_US']:
    searches[locale.replace('_', '-')] = {}
    for module in modules:
        for action, layout in profiles:
            for state in ['', 'readonly']:
                r = subprocess.run(['php', str(root / 'tests/ui/toolbar-buttons.php'), 'render', locale, module, action, layout, state], capture_output=True, text=True, check=True)
                pages[f'/fixture/{locale}/{module}/{action}/{layout}/{state}'] = json.loads(r.stdout)['html'].encode()
        searches[locale.replace('_', '-')][module] = {}
        for context in ['index', 'select']:
            r = subprocess.run(['php', str(root / 'tests/ui/toolbar-buttons.php'), 'render', locale, module, 'search', 'layout', '', context], capture_output=True, text=True, check=True)
            searches[locale.replace('_', '-')][module][context] = json.loads(r.stdout)

setup = ('var fixtureSearches=' + json.dumps(searches).replace('<', '\\u003c') + ';' + r'''
window.calls=[];window.navigations=[];window.holdCopy=false;window.finishCopy=null;
$.ajax=function(options){
    calls.push({url:options.url,data:options.data,type:options.type});
    var d=$.Deferred(), response=options.url.indexOf('/search')!==-1 ? fixtureSearches[document.documentElement.lang][module][options.data.context_action] : {ok:true,saved:true,id:117};
    function finish(){if(options.success)options.success(response);d.resolve(response);if(options.complete)options.complete();}
    if(holdCopy&&options.url.indexOf('/copy/')!==-1) finishCopy=finish;else finish();
    return d.promise({abort:function(){}});
};
window.setLocation=function(url){navigations.push(url);};
''').encode()

class ToolbarPages(BaseHTTPRequestHandler):
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

server = ThreadingHTTPServer(('127.0.0.1', 0), ToolbarPages)
worker = Thread(target=server.serve_forever, daemon=True)
worker.start()
driver = None
try:
    driver = browser.WebDriver()
    driver.command('POST', '/goog/cdp/execute', {'cmd': 'Page.addScriptToEvaluateOnNewDocument', 'params': {'source': 'window.fixtureErrors=[];addEventListener("error",function(e){fixtureErrors.push(e.message||e.target.src||e.target.href||"resource error")},true)'}})
    for locale in ['de_DE', 'en_US']:
        for module in modules:
            controller = {'contacts': 'contact', 'items': 'item', 'tasks': 'task'}[module]
            route = f'/fixture/{module}/{controller}'
            for action, layout in profiles:
                for width in [390, 768, 1280]:
                    context = f'{locale}/{module}/{action}/{layout}/{width}'
                    driver.emulate_viewport(width, 1100)
                    driver.navigate(f'http://127.0.0.1:{server.server_port}/fixture/{locale}/{module}/{action}/{layout}/')
                    driver.wait('return !!window.DewawiToolbar&&!!window.calls')
                    metrics = driver.execute(r'''
                        var controls=[...document.querySelectorAll('button.dw-btn--control')];
                        return {errors:fixtureErrors,overflow:document.documentElement.scrollWidth>innerWidth,symbols:document.querySelectorAll('.dw-icon-symbols').length,
                            controls:controls.map(b=>{var i=b.querySelector('svg'),r=b.getBoundingClientRect(),ir=i.getBoundingClientRect(),l=b.querySelector('.dw-btn__label');return {
                                name:b.name,label:b.getAttribute('aria-label')||b.textContent.trim(),title:b.title,type:b.type,height:r.height,
                                geometry:i.querySelector('use').getBBox().width>0,stroke:getComputedStyle(i).stroke,color:getComputedStyle(b).color,
                                decorative:i.getAttribute('aria-hidden')==='true'&&i.getAttribute('focusable')==='false',
                                center:Math.abs(ir.top+ir.height/2-r.top-r.height/2),gap:l?l.getBoundingClientRect().left-ir.right:8
                            }})};
                    ''')
                    check(not metrics['errors'] and not metrics['overflow'] and metrics['symbols'] == 1, f'{context}: asset/layout/symbol failure: {metrics}')
                    check(len(metrics['controls']) == (9 if action == 'index' else 1), f'{context}: wrong scope')
                    for b in metrics['controls']:
                        check(b['type'] == 'button' and b['geometry'] and b['stroke'] == b['color'] and b['decorative'], f'{context}: icon/type failure: {b}')
                        check(b['label'] and not b['label'].startswith(('TOOLBAR_', 'CONTACT_', 'CLEAR')) and not b['title'].startswith('TOOLBAR_'), f'{context}: technical accessible name: {b}')
                        check(b['height'] >= (44 if width < 768 else 36) and b['center'] < 1 and abs(b['gap']-8) < 1, f'{context}: size/alignment: {b}')
                    ax = driver.command('POST', '/goog/cdp/execute', {'cmd': 'Accessibility.getFullAXTree', 'params': {}})
                    names = {n.get('name', {}).get('value') for n in ax['nodes'] if n.get('role', {}).get('value') == 'button'}
                    check({b['label'] for b in metrics['controls']} <= names, f'{context}: AX name missing')
                    selector = '.dw-toolbar button[name=add]' if action == 'index' else ('.dw-toolbar button[name=clear]' if action == 'select' else '.dw-toolbar button[name=copy]')
                    driver.execute('document.querySelector(arguments[0]).focus()', [selector])
                    driver.keys('tab')
                    driver.shift_tab()
                    check(driver.execute('var b=document.activeElement;return b.matches(arguments[0])&&b.matches(":focus-visible")&&getComputedStyle(b).outlineWidth==="2px"', [selector]), f'{context}: focus absent')
                    driver.execute('window.hoverButton=document.querySelector(arguments[0]);hoverButton.scrollIntoView({block:"center"})', [selector])
                    point = driver.execute('var r=hoverButton.getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2}')
                    driver.command('POST', '/actions', {'actions': [{'type': 'pointer', 'id': 'mouse', 'parameters': {'pointerType': 'mouse'}, 'actions': [{'type': 'pointerMove', 'duration': 0, 'x': int(point['x']), 'y': int(point['y'])}]}]})
                    driver.execute('var p=document.createElement("span");p.style.color=getComputedStyle(hoverButton).getPropertyValue("--dw-btn-hover-background");document.body.append(p);window.hoverColor=getComputedStyle(p).color;p.remove()')
                    driver.wait('return hoverButton.matches(":hover")&&getComputedStyle(hoverButton).backgroundColor===hoverColor')
                    check(driver.execute('return hoverButton.matches(":hover")'), f'{context}: hover absent')
                    check(driver.execute(r'''
                        var b=$(arguments[0]),i=b.find('svg')[0],html=b.html();setButtonLoading(b,true);
                        var busy=b.prop('disabled')&&b.attr('aria-busy')==='true'&&getComputedStyle(i).display==='none';setButtonLoading(b,false);
                        setButtonLoading(b,true,'Loading');setButtonLoading(b,false);var restored=b.html()===html&&b.find('svg')[0]===i;
                        b.prop('disabled',true);calls=[];navigations=[];i.querySelector('use').dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true}));
                        var blocked=!calls.length&&!navigations.length&&parseFloat(getComputedStyle(b[0]).opacity)<1;
                        setButtonLoading(b,true);setButtonLoading(b,false);var stillDisabled=b.prop('disabled');b.prop('disabled',false);
                        return busy&&restored&&blocked&&stillDisabled;
                    ''', [selector]), f'{context}: disabled/loading content or guard failed')
                    if action == 'index':
                        driver.execute('document.querySelector(".dw-toolbar button[name=add]").focus()')
                        driver.keys('enter')
                        check(driver.execute('return navigations.length===1&&navigations[0].indexOf(arguments[0]+"/add")===0', [route]), f'{context}: add route changed')
                        driver.execute('navigations=[];document.querySelector(".dw-toolbar button[name=edit]").click()')
                        check(driver.execute('return navigations.length===0'), f'{context}: edit ignored empty selection')
                        driver.execute('$("input[data-select-id]").first().prop("checked",true);document.querySelector(".dw-toolbar button[name=edit] use").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}))')
                        check(driver.execute('return navigations.length===1&&navigations[0]===arguments[0]+"/edit/id/17"', [route]), f'{context}: wrong selected edit target')
                        driver.execute('calls=[];holdCopy=true;window.copyButton=document.querySelector(".dw-toolbar--bottom button[name=copy]");window.copyIcon=copyButton.querySelector("svg");copyIcon.dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}));copyIcon.dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}));document.querySelector(".dw-toolbar button[name=copy] use").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}))')
                        check(driver.execute('return calls.length===1&&calls[0].url===arguments[0]+"/copy/id/17"&&copyButton.disabled&&copyButton.getAttribute("aria-busy")==="true"', [route]), f'{context}: copy target/lock failed')
                        driver.execute('holdCopy=false;finishCopy()')
                        driver.wait('return calls.some(c=>c.url.endsWith("/search"))')
                        check(driver.execute('return !copyButton.disabled&&copyButton.querySelector("svg")===copyIcon&&document.querySelectorAll(".dw-toolbar--bottom button[name=copy] svg use").length===1'), f'{context}: copy restore or dynamic footer failed')
                        driver.execute('calls=[];document.querySelector("button[name=filter]").focus()')
                        driver.keys('space')
                        check(driver.execute('return $(".dw-filter-panel").hasClass("is-open")'), f'{context}: filter keyboard failed')
                        if tasks_mode:
                            check(driver.execute('return JSON.stringify(collectToolbarData().states)===JSON.stringify(["100","101","102","103","104"])'), f'{context}: Task state defaults changed')
                            driver.execute('calls=[];$("input[name^=states][value=100]").prop("checked",false).trigger("change")')
                            driver.wait('return calls.length===1')
                            check(driver.execute('return JSON.stringify(calls[0].data.states)===JSON.stringify(["101","102","103","104"])'), f'{context}: Task state filter changed')
                            driver.execute('calls=[];$("input[name=daterange][value=custom]").prop("checked",true).trigger("change")')
                            driver.wait('return calls.length===1')
                            check(driver.execute('return calls[0].data.daterange==="custom"&&!$(".dw-filter-card.daterange").hasClass("is-hidden")'), f'{context}: custom date range changed')
                            driver.execute('calls=[];$("input[name=from]").val("2026-02-03").trigger("change")')
                            driver.wait('return calls.length===1')
                            check(driver.execute('return calls[0].data.from==="2026-02-03"&&calls[0].data.daterange==="custom"'), f'{context}: date field request changed')
                            driver.execute('calls=[]')
                        driver.execute('window.oldFooter=document.querySelector(".dw-toolbar--bottom");document.querySelector("button[name=reset]").focus()')
                        driver.keys('enter')
                        driver.wait('return calls.length===1')
                        check(driver.execute('return oldFooter!==document.querySelector(".dw-toolbar--bottom")&&calls[0].data.context_action==="index"'), f'{context}: reset/AJAX footer changed')
                        if tasks_mode:
                            check(driver.execute('return calls[0].data.daterange==="all"&&JSON.stringify(calls[0].data.states)===JSON.stringify(["100","101","102","103","104"])'), f'{context}: Task reset lost state/date defaults')
                    if action in ['index', 'select']:
                        driver.execute('calls=[];$("input[name=keyword]").val("query").trigger("input")')
                        driver.wait('return calls.length===1')
                        check(driver.execute('return calls[0].data.keyword==="query"&&calls[0].url===arguments[0]+"/search"', [route]), f'{context}: keyword search changed')
                        driver.execute('calls=[];$("input[name=keyword]").val("example");document.querySelector("button[name=clear]").focus()')
                        driver.keys('space')
                        driver.wait('return calls.length===1')
                        check(driver.execute('return $("input[name=keyword]").val()===""&&calls[0].data.context_action===arguments[0]', [action]), f'{context}: clear/context changed')
                    else:
                        driver.execute('calls=[];navigations=[];document.querySelector(".dw-toolbar button[name=copy]").focus()')
                        driver.keys('enter')
                        check(driver.execute('return calls.length===1&&calls[0].url===arguments[0]+"/copy/id/17"&&navigations[0]===arguments[0]+"/edit/id/117"', [route]), f'{context}: detail copy route changed')
                        if tasks_mode and action == 'edit':
                            driver.execute('calls=[];$(".dw-toolbar select[name=state]").val("101").trigger("change")')
                            check(driver.execute('return calls.length===1&&calls[0].data.state==="101"&&calls[0].url===arguments[0]+"/edit/id/17"', [route]), f'{context}: toolbar status save changed')
                            driver.execute('calls=[];$("#task-fields select[name=state]").val("102").trigger("change")')
                            check(driver.execute('return calls.length===1&&calls[0].data.state==="102"&&calls[0].url===arguments[0]+"/edit/id/17"', [route]), f'{context}: form status auto-save changed')
                            driver.execute('calls=[];$("#task-fields select[name=responsible]").val("43").trigger("change")')
                            check(driver.execute('return calls.length===1&&calls[0].data.responsible==="43"&&calls[0].url===arguments[0]+"/edit/id/17"', [route]), f'{context}: assignment auto-save changed')
                            driver.execute('calls=[];$("#task-fields").attr("data-autosave","false");$("#task-fields select[name=responsible]").val("42").trigger("change")')
                            check(driver.execute('return calls.length===0'), f'{context}: auto-save opt-out ignored')
                        if tasks_mode and action == 'view':
                            check(driver.execute('return [...document.querySelectorAll("#task-fields select")].every(s=>s.disabled)'), f'{context}: readonly Task fields enabled')
                    check(driver.execute('return document.querySelectorAll(".dw-icon-symbols").length===1&&document.querySelectorAll("button.dw-btn--control").length===arguments[0]&&[...document.querySelectorAll(".dw-btn--control use")].every(i=>i.getBBox().width>0)&&fixtureErrors.length===0', [9 if action == 'index' else 1]), f'{context}: dynamic scope/geometry, duplicate symbols or script errors')
                    if locale == 'de_DE' and width in [390, 1280]:
                        driver.execute('window.scrollTo(0,0);$(".dw-filter-panel").removeClass("is-open")')
                        shot = driver.command('GET', '/screenshot')
                        Path(f'/tmp/dewawi-{"b6" if tasks_mode else "b5"}-{module}-{action}-{width}.png').write_bytes(base64.b64decode(shot))
                    print(f'PASS: {context} shared toolbar layout, keyboard, selection, requests and states')
            driver.navigate(f'http://127.0.0.1:{server.server_port}/fixture/{locale}/{module}/index/layout/readonly')
            driver.wait('return !!window.calls')
            check(driver.execute('return [...document.querySelectorAll(".dw-btn--control")].every(b=>b.disabled&&parseFloat(getComputedStyle(b).opacity)<1)'), f'{locale}/{module}: readonly enabled')
    print(f'PASS: {checks} shared toolbar browser assertions')
finally:
    if driver:
        driver.close()
    server.shutdown()
    server.server_close()
    worker.join()
