"""Exercise actual Apply controls inside a real iframe/modal; all business I/O is captured."""
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
spec = importlib.util.spec_from_file_location('apply_driver', root / 'tests/site/accessibility-http.py')
browser = importlib.util.module_from_spec(spec)
spec.loader.exec_module(browser)
checks = 0

def check(value, message):
    global checks
    checks += 1
    if not value:
        raise AssertionError(message)

pages, searches = {}, {}
for locale in ['de_DE', 'en_US']:
    searches[locale.replace('_', '-')] = {}
    for module in ['contacts', 'items']:
        for state in ['', 'readonly']:
            result = subprocess.run(['php', str(root / 'tests/ui/toolbar-buttons.php'), 'render', locale, module, 'select', 'plain', state, 'index', 'selection-actions'], capture_output=True, text=True, check=True)
            pages[f'/fixture/{locale}/{module}/{state}'] = json.loads(result.stdout)['html'].encode()
        result = subprocess.run(['php', str(root / 'tests/ui/toolbar-buttons.php'), 'render', locale, module, 'search', 'plain', '', 'select', 'selection-actions'], capture_output=True, text=True, check=True)
        searches[locale.replace('_', '-')][module] = json.loads(result.stdout)

setup = ('var fixtureSearches=' + json.dumps(searches).replace('<', '\\u003c') + ';' + r'''
window.calls=[];window.navigations=[];
$.ajax=function(o){
    calls.push({url:o.url,data:o.data,type:o.type});
    if(o.url.indexOf('/position/add/')!==-1)return parent.capturePosition(o,$);
    if(o.url!==baseUrl+'/'+module+'/'+controller+'/search')throw Error('Unexpected child request: '+o.url);
    var d=$.Deferred(),r=fixtureSearches[document.documentElement.lang][module];
    if(o.success)o.success(r);d.resolve(r);if(o.complete)o.complete();return d.promise({abort:function(){}});
};
window.setLocation=function(url){navigations.push(url);};
''').encode()

parent_template = r'''<!doctype html><html lang="__LANG__"><meta charset="utf-8"><meta name="viewport" content="width=device-width">
<link rel="stylesheet" href="/fixture/css/ui.css">
<button type="button" id="open" onclick="modalWindow('picker',7,this)">__OPEN__</button>
<div class="edit"><form id="parent-fields"><input name="customerid"><input name="billingname1"><input name="vatin"><input name="taxfree"></form></div>
<div id="messages"></div><div id="phones"></div><div id="emails"></div><div id="internets"></div><div id="tabfiles"></div>
<div id="picker" class="popup_block"><iframe id="picker-frame" title="__OPEN__" width="100%" height="100%"></iframe></div>
<script src="/fixture/library/jQuery/jquery-1.12.4.min.js"></script><script src="/fixture/library/jQuery/jquery.cookie.js"></script>
<script>var module='processes',controller='process',id=501,action='edit',baseUrl='/fixture',cookiePath='/fixture',language='__LANG__',dwMessages=__MESSAGES__,deleteConfirm='';
function autosize(){} $.fn.datepicker=function(){return this;};
</script><script src="/fixture/js/global.js"></script><script src="/fixture/js/contacts.js"></script>
<script>
window.parentCalls=[];window.positionCalls=[];window.pending=[];window.calculations=[];window.refreshes=[];window.failRead=false;window.failSave=false;window.reenter=false;window.confirmations=0;window.loadingSeen=false;
window.confirm=function(){confirmations++;return true;};
window.applyCalculation=function(data){calculations.push(data);};
window.getPositions=function(p,t,y){refreshes.push({parent:p,type:t,scroll:y});};
window.capturePosition=function(o,jq){positionCalls.push({url:o.url,type:o.type});pending.push(o);return jq.Deferred().promise();};
window.finishPosition=function(ok){var o=pending.shift();if(!o)throw Error('No pending position');o.success({ok:ok,calculation:{marker:positionCalls.length}});};
$.ajax=function(o){
    parentCalls.push({url:o.url,data:o.data,type:o.type,async:o.async});
    var d=$.Deferred(),r={ok:true,saved:true};
    if(o.url.indexOf('/contact/get/')!==-1){
        var selected=Number(o.url.split('/id/')[1]);
        r=failRead?{ok:false}:{ok:true,item:{id:selected,contactid:100+selected,name1:'Fixture contact',vatin:'DE-fixture',taxfree:1,country:'DE'}};
        var w=document.querySelector('iframe').contentWindow,b=w.document.querySelector('button[name=apply].is-loading');
        loadingSeen=!!b&&b.disabled&&b.getAttribute('aria-busy')==='true'&&w.getComputedStyle(b.querySelector('svg')).display==='none';
        if(reenter){reenter=false;
            w.document.querySelector('.dw-toolbar--bottom button[name=apply] use').dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true}));}
    }else if(o.url.indexOf('/address/get/')!==-1)r={ok:true,items:[]};
    else if(o.url==='/fixture/processes/process/edit/id/501'){
        if(failSave)r={ok:false,saved:false,errors:{customerid:['Fixture validation']}};
    }else throw Error('Unexpected parent request: '+o.url);
    if(o.success)o.success(r);d.resolve(r);if(o.complete)o.complete();return d.promise({abort:function(){}});
};
document.querySelector('iframe').src='__FRAME__?parent=__PARENT__&setid=7';
</script></html>'''
for locale in ['de_DE', 'en_US']:
    for module in ['contacts', 'items']:
        child = pages[f'/fixture/{locale}/{module}/'].decode()
        messages = json.JSONDecoder().raw_decode(child.split('var dwMessages = ', 1)[1])[0]
        parent = parent_template.replace('__LANG__', locale.replace('_', '-')).replace('__OPEN__', 'Auswahl öffnen' if locale == 'de_DE' else 'Open selection').replace('__MESSAGES__', json.dumps(messages).replace('<', '\\u003c')).replace('__FRAME__', f'/fixture/{locale}/{module}/').replace('__PARENT__', 'processes%7Cprocess' + ('%7C501' if module == 'items' else ''))
        pages[f'/parent/{locale}/{module}'] = parent.encode()

class ApplyPages(BaseHTTPRequestHandler):
    def do_GET(self):
        path = unquote(urlparse(self.path).path)
        if path in pages:
            payload, kind = pages[path], 'text/html; charset=utf-8'
        elif path == '/fixture/toolbar-setup.js':
            payload, kind = setup, 'text/javascript'
        elif path.startswith(('/fixture/css/', '/fixture/js/', '/fixture/library/', '/fixture/images/')) or path == '/fixture/favicon.ico':
            asset = (root / path.removeprefix('/fixture/')).resolve()
            if not asset.is_relative_to(root) or not asset.is_file() or asset.suffix.lower() not in ['.js', '.css', '.png', '.svg', '.gif', '.jpg', '.ico', '.woff', '.woff2']:
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

server = ThreadingHTTPServer(('127.0.0.1', 0), ApplyPages)
worker = Thread(target=server.serve_forever, daemon=True)
worker.start()
driver = None
try:
    driver = browser.WebDriver()
    driver.command('POST', '/goog/cdp/execute', {'cmd': 'Page.addScriptToEvaluateOnNewDocument', 'params': {'source': 'window.fixtureErrors=[];addEventListener("error",function(e){fixtureErrors.push(e.message||e.target.src||e.target.href||"resource error")},true)'}})

    def enter_frame():
        driver.command('POST', '/frame', {'id': {'element-6066-11e4-a52e-4f735466cecf': driver.element('#picker-frame')}})
        driver.wait('return !!window.DewawiToolbar&&!!window.calls')

    def leave_frame():
        driver.command('POST', '/frame', {'id': None})

    for locale in ['de_DE', 'en_US']:
        for module in ['contacts', 'items']:
            for width in [320, 390, 768, 1280]:
                context = f'{locale}/{module}/{width}'
                parent_context = 'processes|process' + ('|501' if module == 'items' else '')
                driver.emulate_viewport(width, 1100)
                driver.navigate(f'http://127.0.0.1:{server.server_port}/parent/{locale}/{module}')
                driver.wait('return !!window.modalWindow&&document.querySelector("iframe").contentDocument.readyState==="complete"')
                driver.click('#open')
                enter_frame()
                metrics = driver.execute(r'''
                    var b=[...document.querySelectorAll('.dw-toolbar button[name=apply]')];return {width:innerWidth,symbols:document.querySelectorAll('.dw-icon-symbols').length,overflow:document.documentElement.scrollWidth>innerWidth,
                    buttons:b.map(e=>{var i=e.querySelector('svg'),r=e.getBoundingClientRect(),ir=i.getBoundingClientRect();return {label:e.textContent.trim(),type:e.type,height:r.height,gap:e.querySelector('.dw-btn__label').getBoundingClientRect().left-ir.right,center:Math.abs(ir.top+ir.height/2-r.top-r.height/2),geometry:i.querySelector('use').getBBox().width,icon:i.getAttribute('aria-hidden')}})};
                ''')
                check(metrics['symbols'] == 1 and not metrics['overflow'] and len(metrics['buttons']) == 2, f'{context}: iframe layout/symbol scope')
                for b in metrics['buttons']:
                    check(b['label'] == ('Übernehmen' if locale == 'de_DE' else 'Apply') and b['type'] == 'button' and b['icon'] == 'true', f'{context}: translated name/native type')
                    check(b['height'] >= (44 if metrics['width'] < 768 else 36) and abs(b['gap']-8) < 1 and b['center'] < 1 and b['geometry'] > 0, f'{context}: size/alignment/geometry')
                check(driver.execute('var b=[...document.querySelectorAll("button[name=apply][data-id]")];return b.length===2&&b.map(e=>e.dataset.id).join(",")==="17,18"&&b.every(e=>e.type==="button"&&e.getAttribute("aria-label")===arguments[0]&&!e.textContent.trim()&&e.querySelector("svg[aria-hidden=true] use").getBBox().width>0&&e.getBoundingClientRect().height===(innerWidth<768?44:32))', ['Übernehmen' if locale == 'de_DE' else 'Apply']), f'{context}: row Apply target/name/size')
                driver.execute('window.hoverButton=document.querySelector("button[name=apply]");hoverButton.scrollIntoView({block:"center"})')
                driver.command('POST', '/actions', {'actions': [{'type': 'pointer', 'id': 'mouse', 'parameters': {'pointerType': 'mouse'}, 'actions': [{'type': 'pointerMove', 'duration': 0, 'origin': {'element-6066-11e4-a52e-4f735466cecf': driver.element('button[name=apply]')}, 'x': 0, 'y': 0}]}]})
                driver.execute('var p=document.createElement("span");p.style.color=getComputedStyle(hoverButton).getPropertyValue("--dw-btn-hover-background");document.body.append(p);window.hoverColor=getComputedStyle(p).color;p.remove()')
                driver.wait('return hoverButton.matches(":hover")&&getComputedStyle(hoverButton).backgroundColor===hoverColor')
                check(driver.execute('return hoverButton.matches(":hover")'), f'{context}: Apply hover')
                driver.execute('document.querySelector("button[name=apply]").focus()')
                driver.keys('tab')
                driver.shift_tab()
                check(driver.execute('return document.activeElement.name==="apply"&&document.activeElement.matches(":focus-visible")&&getComputedStyle(document.activeElement).outlineWidth==="2px"'), f'{context}: visible keyboard focus')
                driver.keys('enter')
                check(driver.execute('return !parent.parentCalls.length&&!parent.positionCalls.length'), f'{context}: empty selection applied')
                check(driver.execute(r'''
                    var b=$('button[name=apply]').first(),i=b.find('svg')[0],old=b.html();b.prop('disabled',true);i.querySelector('use').dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true}));
                    var disabled=!parent.parentCalls.length&&!parent.positionCalls.length&&parseFloat(getComputedStyle(b[0]).opacity)<1;b.prop('disabled',false);
                    setButtonLoading(b,true,'Working');var busy=b.prop('disabled')&&b.attr('aria-busy')==='true';setButtonLoading(b,false);
                    return disabled&&busy&&b.html()===old&&b.find('svg')[0]===i&&!b.prop('disabled');
                '''), f'{context}: disabled/loading restore')
                driver.execute('window.oldFooter=document.querySelector(".dw-toolbar--bottom");window.headIcon=document.querySelector("button[name=apply] svg");$("input[data-select-id]").prop("checked",true);$("input[name=keyword]").val("fixture").trigger("input")')
                driver.wait('return calls.length===1')
                check(driver.execute('return calls[0].data.context_action==="select"&&calls[0].data.parent===arguments[0]&&String(calls[0].data.setid)==="7"&&oldFooter!==document.querySelector(".dw-toolbar--bottom")&&headIcon===document.querySelector("button[name=apply] svg")&&document.querySelectorAll("button[name=apply] use").length===4&&!document.querySelector("input[data-select-id]:checked")', [parent_context]), f'{context}: Search lost context/selection contract or dynamic SVG')
                if width == 1280:
                    driver.execute(r'history.replaceState(null,"",location.pathname.replace(/\/$/,"")+"/parent/"+encodeURIComponent(arguments[0])+"/setid/7")', [parent_context])
                    check(driver.execute('var c=DewawiToolbar.getSelectContext();return c.parent==="process"&&c.setid===7&&(module!=="items"||c.parentid==="501")'), f'{context}: path parameter context')
                driver.execute('$("input[data-select-id]").prop("checked",true);parent.reenter=true;document.querySelector("button[name=apply]").focus()')
                driver.keys('space')
                if module == 'contacts':
                    check(driver.execute('var p=parent,reads=p.parentCalls.filter(c=>c.url.includes("/contact/get/")),writes=p.parentCalls.filter(c=>c.url.includes("/edit/"));return p.loadingSeen&&reads.length===1&&reads[0].url==="/fixture/contacts/contact/get/id/17"&&writes.length===1&&writes[0].url==="/fixture/processes/process/edit/id/501"&&writes[0].data.customerid===117&&!("contactid" in writes[0].data)&&writes[0].data.billingname1==="Fixture contact"&&!p.modalWindow.current'), f'{context}: first contact/parent save/loading/reentrant lock changed')
                else:
                    check(driver.execute('return parent.positionCalls.length===1&&parent.positionCalls[0].url==="/fixture/processes/position/add/setid/7/parent/process/type/pos/parentid/501/itemid/17"&&parent.modalWindow.current.data("dw-modal-pending")===true'), f'{context}: item route/initial lock')
                    driver.execute('document.querySelector(".dw-toolbar--bottom button[name=apply] use").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}))')
                    driver.keys('escape')
                    check(driver.execute('return parent.positionCalls.length===1&&!!parent.modalWindow.current'), f'{context}: duplicate Apply or premature modal close')
                    driver.execute('parent.finishPosition(true)')
                    check(driver.execute('return parent.positionCalls.length===2&&parent.positionCalls[1].url.endsWith("/itemid/18")&&!parent.calculations.length&&!parent.refreshes.length'), f'{context}: sequential multi-selection changed')
                    driver.execute('parent.finishPosition(true)')
                    check(driver.execute('return parent.calculations.length===1&&parent.calculations[0].marker===2&&parent.refreshes.length===1&&parent.refreshes[0].parent==="process"&&parent.refreshes[0].type==="pos"&&!parent.modalWindow.current'), f'{context}: calculation/refresh/close changed')
                leave_frame()
                check(driver.execute('return document.activeElement.id==="open"&&!confirmations&&!fixtureErrors.length'), f'{context}: opener focus, new confirmation or parent script error')
                driver.click('#open')
                enter_frame()
                check(driver.execute('return document.querySelectorAll("input[data-select-id]:checked").length===2'), f'{context}: reopening lost selection')
                driver.execute('parent.parentCalls=[];parent.positionCalls=[];parent.pending=[];parent.failSave=true;window.footer=document.querySelector(".dw-toolbar--bottom button[name=apply]");window.footerIcon=footer.querySelector("svg");footerIcon.querySelector("use").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}))')
                if module == 'items':
                    if width == 1280:
                        driver.execute('parent.finishPosition(true)')
                    driver.execute('parent.finishPosition(false)')
                    check(driver.execute('return parent.positionCalls.length===arguments[0]', [2 if width == 1280 else 1]), f'{context}: partial batch failure changed request order')
                check(driver.execute('return !!parent.modalWindow.current&&!parent.modalWindow.current.data("dw-modal-pending")&&!footer.disabled&&footer.querySelector("svg")===footerIcon&&document.querySelectorAll("input[data-select-id]:checked").length===2'), f'{context}: failed action lost dialog/selection/button state')
                driver.execute('parent.failSave=false;parent.parentCalls=[];parent.positionCalls=[];parent.pending=[];footer.focus()')
                driver.keys('enter')
                if module == 'items':
                    driver.execute('parent.finishPosition(true);parent.finishPosition(true)')
                check(driver.execute('return !parent.modalWindow.current&&!fixtureErrors.length'), f'{context}: retry/dynamic footer behavior')
                leave_frame()
                check(driver.execute('return document.activeElement.id==="open"&&!document.querySelector("#fade")'), f'{context}: retry focus/overlay cleanup')
                driver.click('#open')
                enter_frame()
                driver.execute('parent.parentCalls=[];parent.positionCalls=[];parent.pending=[];$("input[data-select-id]").prop("checked",false).first().prop("checked",true);window.rowApply=document.querySelector("button[name=apply][data-id=\\"18\\"]");window.rowIcon=rowApply.querySelector("svg");rowApply.focus()')
                check(driver.execute('return rowIcon.querySelector("use").getBBox().width>0&&rowApply.matches(":focus-visible")'), f'{context}: dynamic row geometry/focus')
                if width == 1280:
                    driver.execute('rowIcon.querySelector("use").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}))')
                else:
                    driver.keys('enter')
                if module == 'contacts':
                    check(driver.execute('var r=parent.parentCalls.filter(c=>c.url.includes("/contact/get/")),w=parent.parentCalls.filter(c=>c.url.includes("/edit/"));return parent.loadingSeen&&r.length===1&&r[0].url.endsWith("/id/18")&&w.length===1&&w[0].data.customerid===118'), f'{context}: row contact target was replaced by checkbox selection')
                else:
                    check(driver.execute('return parent.positionCalls.length===1&&parent.positionCalls[0].url.endsWith("/itemid/18")'), f'{context}: row item target was replaced by checkbox selection')
                    driver.execute('parent.finishPosition(true)')
                    check(driver.execute('return parent.positionCalls.length===1'), f'{context}: row Apply adopted bulk selection')
                check(driver.execute('return !parent.modalWindow.current&&!rowApply.disabled&&rowApply.querySelector("svg")===rowIcon&&!fixtureErrors.length'), f'{context}: row Apply restore/close')
                leave_frame()
                driver.execute('parentCalls=[];$("#parent-fields [name=billingname1]").val("Autosave fixture").trigger("change")')
                check(driver.execute('return parentCalls.length===1&&parentCalls[0].url==="/fixture/processes/process/edit/id/501"&&parentCalls[0].data.billingname1==="Autosave fixture"'), f'{context}: parent auto-save changed')
                driver.execute('parentCalls=[];$("#parent-fields").attr("data-autosave","false");$("#parent-fields [name=billingname1]").val("Opt out").trigger("change")')
                check(driver.execute('return !parentCalls.length'), f'{context}: parent auto-save opt-out changed')
                if locale == 'de_DE':
                    driver.click('#open')
                    Path(f'/tmp/dewawi-b9-{module}-{width}.png').write_bytes(base64.b64decode(driver.command('GET', '/screenshot')))
                print(f'PASS: {context} real iframe/modal, selection, callbacks, locks, retry, keyboard and focus')
            for state in ['readonly']:
                driver.navigate(f'http://127.0.0.1:{server.server_port}/fixture/{locale}/{module}/{state}')
                driver.wait('return !!window.DewawiToolbar')
                check(driver.execute('return [...document.querySelectorAll("button[name=apply]")].length===4&&[...document.querySelectorAll("button[name=apply]")].every(b=>b.disabled)'), f'{locale}/{module}: readonly Apply enabled')
    print(f'PASS: {checks} Apply browser assertions')
finally:
    if driver:
        driver.close()
    server.shutdown()
    server.server_close()
    worker.join()
