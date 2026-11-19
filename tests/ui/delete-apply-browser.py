"""B17 standard Delete probes. Complete layouts, actual JS, memory-only business replies."""
import base64
import io
import json
import math
import mimetypes
import re
import subprocess
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from threading import Thread
from urllib.parse import urlparse

from PIL import Image


def run(root, browser, factory, engine, output):
    checks, measurements = 0, []

    def check(value, message):
        nonlocal checks
        checks += 1
        if not value:
            raise AssertionError(message)

    def wait(script):
        deadline = time.monotonic() + 8
        while not driver.execute(script):
            if time.monotonic() > deadline:
                raise AssertionError('Timed out: ' + script + ' state=' + json.dumps(driver.execute('var b=document.querySelector(".dw-toolbar button[name=delete]");var s=getComputedStyle(b),r=b.getBoundingClientRect();return {hover:b.matches(":hover"),bg:s.backgroundColor,wanted:s.getPropertyValue("--dw-btn-hover-background"),rect:{x:r.x,y:r.y,w:r.width,h:r.height},target:document.elementFromPoint(r.x+r.width/2,r.y+r.height/2)?.outerHTML}')))
            time.sleep(.03)

    pages, replies = {}, {}
    for locale in ['de_DE', 'en_US']:
        fixtures = json.loads(subprocess.check_output(['php', str(root / 'tests/ui/delete-apply-buttons.php'), 'fixtures', locale], text=True))
        for route, contexts in fixtures.items():
            for action, page in contexts.items():
                key = f'/fixture/{locale}/{route}/{action}'
                if 'html' in page:
                    pages[key] = page['html'].encode()
                else:
                    replies[key] = page
    setup = ('window.fixtureReplies=' + json.dumps(replies).replace('<', '\\u003c') + ';' + r'''
window.calls=[];window.navigations=[];window.confirmations=[];window.confirmResult=false;window.fixtureErrors=[];
window.nextRows='remaining';window.pendingDelete=null;
addEventListener('error',function(e){fixtureErrors.push(e.message||'resource error')},true);
window.confirm=function(message){confirmations.push(message);return confirmResult;};
window.setLocation=function(url){navigations.push(url);};
$.ajax=function(o){
    calls.push({url:o.url,type:o.type,data:o.data,contentType:o.contentType});
    var d=$.Deferred();
    if(o.url===baseUrl+'/trash/add/'){
        pendingDelete=function(response,error){
            if(error){var e={status:403,responseJSON:{ok:false}};if(o.error)o.error(e);d.reject(e);}
            else {if(o.success)o.success(response);d.resolve(response);}
            if(o.complete)o.complete();pendingDelete=null;
        };
    }else if(o.url===baseUrl+'/'+module+'/'+controller+'/search'){
        var r=fixtureReplies['/fixture/'+document.documentElement.lang.replace('-','_')+'/'+module+'/'+controller+'/'+nextRows];
        if(!r)throw Error('Missing search fixture');
        if(o.success)o.success(r);d.resolve(r);if(o.complete)o.complete();
    }else throw Error('Unexpected business request: '+o.url);
    return d.promise({abort:function(){if(o.complete)o.complete();}});
};
''').encode()

    class Pages(BaseHTTPRequestHandler):
        def do_GET(self):
            path = urlparse(self.path).path
            if path in pages:
                payload, kind = pages[path], 'text/html; charset=utf-8'
            elif path == '/fixture/toolbar-setup.js':
                payload, kind = setup, 'text/javascript'
            else:
                asset = (root / path.removeprefix('/fixture/')).resolve()
                if not path.startswith('/fixture/') or not asset.is_relative_to(root) or asset.suffix not in ['.js', '.css', '.svg', '.png', '.gif', '.ico', '.woff', '.woff2'] or not asset.is_file():
                    self.send_error(404)
                    return
                payload, kind = asset.read_bytes(), mimetypes.guess_type(asset)[0] or 'application/octet-stream'
            self.send_response(200)
            self.send_header('Content-Type', kind)
            self.send_header('Content-Security-Policy', "frame-ancestors 'self'")
            self.end_headers()
            self.wfile.write(payload)

        def log_message(self, *args):
            pass

    def click(selector):
        if engine == 'chromium':
            driver.click(selector)
        else:
            point = driver.execute('var b=document.querySelector(' + json.dumps(selector) + ');b.scrollIntoView({block:"center",inline:"center"});var r=b.getBoundingClientRect();return {x:Math.floor(r.x+r.width/2),y:Math.floor(r.y+r.height/2)}')
            driver.call('input.performActions', {'context': driver.context, 'actions': [{'type': 'pointer', 'id': 'mouse', 'parameters': {'pointerType': 'mouse'}, 'actions': [{'type': 'pointerMove', **point}, {'type': 'pointerDown', 'button': 0}, {'type': 'pointerUp', 'button': 0}]}]})

    def pixels(selector):
        rect = driver.execute('var s=document.querySelector(' + json.dumps(selector) + ');s.scrollIntoView({block:"center",inline:"center"});var r=s.getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height,color:getComputedStyle(s).stroke,viewport:innerWidth}')
        png = driver.screenshot() if engine == 'firefox' else base64.b64decode(driver.command('GET', '/screenshot'))
        picture = Image.open(io.BytesIO(png)).convert('RGB')
        scale = picture.width / rect['viewport']
        crop = picture.crop((math.ceil(rect['x'] * scale), math.ceil(rect['y'] * scale), math.floor((rect['x'] + rect['w']) * scale), math.floor((rect['y'] + rect['h']) * scale)))
        color = [int(v) for v in re.findall(r'\d+', rect['color'])[:3]]
        count = sum(max(abs(c - target) for c, target in zip(pixel, color)) < 40 for pixel in crop.getdata())
        return count

    server = ThreadingHTTPServer(('127.0.0.1', 0), Pages)
    Thread(target=server.serve_forever, daemon=True).start()
    driver = None
    try:
        driver = factory()
        for locale in ['de_DE', 'en_US']:
            routes = [key.removeprefix(f'/fixture/{locale}/').removesuffix('/index') for key in pages if key.startswith(f'/fixture/{locale}/') and key.endswith('/index')]
            for route in routes:
                for width in [320, 390, 768, 1280]:
                    context = f'{locale}/{route}/{width}'
                    driver.emulate_viewport(width, 1100)
                    driver.navigate(f'http://127.0.0.1:{server.server_port}/fixture/{locale}/{route}/index')
                    wait('return !!window.DewawiToolbar&&!!window.calls')
                    top = '.dw-toolbar button[name=delete]'
                    bottom = '.dw-toolbar--bottom button[name=delete]'
                    metric = driver.execute(r'''
                        var b=document.querySelector('.dw-toolbar button[name=delete]'),r=b.getBoundingClientRect(),i=b.querySelector('svg'),ir=i.getBoundingClientRect();
                        return {symbols:document.querySelectorAll('symbol[id^="dw-icon-"]').length,banks:document.querySelectorAll('.dw-icon-symbols').length,
                            unresolved:[...document.querySelectorAll('use[href^="#dw-icon-"]')].filter(u=>!document.getElementById(u.getAttribute('href').slice(1))).length,
                            label:b.textContent.trim(),name:b.getAttribute('aria-label'),type:b.type,w:r.width,h:r.height,center:Math.abs(r.y+r.height/2-ir.y-ir.height/2),
                            rows:[...document.querySelectorAll('tbody .dw-row')].map(e=>e.getBoundingClientRect().height),
                            tableOverflow:[...document.querySelectorAll('.dw-table-wrap')].every(e=>e.scrollWidth<=e.clientWidth+1||['auto','scroll'].includes(getComputedStyle(e).overflowX))};
                    ''')
                    check(metric['symbols'] == 14 and metric['banks'] == 1 and not metric['unresolved'], context + ': unresolved symbols')
                    check(metric['label'] == ('Löschen' if locale == 'de_DE' else 'Delete') and metric['type'] == 'button' and metric['center'] < 1 and metric['h'] >= (44 if width < 768 else 32), context + ': label/type/alignment/touch target')
                    check(metric['tableOverflow'], context + ': uncontained table overflow')
                    if route in ['contacts/contact', 'tasks/task'] and width < 768:
                        check(all(h <= 116 for h in metric['rows']), context + ': mobile row regression')
                    metric['pixels'] = pixels(top + ' svg')
                    check(metric['pixels'] > 8, context + ': Delete SVG paints no pixels')
                    if not route.startswith('admin/'):
                        check(pixels('.dw-row-actions button[name=delete] svg') > 8, context + ': inline Delete paints no pixels')
                    else:
                        check(driver.execute('return [...document.querySelectorAll(".dw-row-actions button[name=delete]")].every(b=>!b.querySelector("svg"))'), context + ': media-delete migrated')
                    click(top)
                    wait('var b=document.querySelector(".dw-toolbar button[name=delete]");var e=document.createElement("span");e.style.color=getComputedStyle(b).getPropertyValue("--dw-btn-hover-background");document.body.append(e);var color=getComputedStyle(e).color;e.remove();return b.matches(":hover")&&getComputedStyle(b).backgroundColor===color')
                    check(driver.execute('return !calls.length&&!confirmations.length'), context + ': empty selection triggered Delete')
                    driver.execute('$("input[data-select-id]").prop("checked",true);confirmResult=false')
                    click(top + ' svg')
                    check(driver.execute('return !calls.length&&confirmations.length===1&&confirmations[0]===deleteConfirm&&$("input[data-select-id]:checked").length===2'), context + ': confirmation cancel changed selection/request')
                    driver.execute('confirmResult=true;window.originalButton=document.querySelector(".dw-toolbar button[name=delete]");window.originalHTML=originalButton.outerHTML;originalButton.focus()')
                    driver.keys('enter')
                    # Some headless Firefox sessions do not give native keys to page content.
                    keyboard = driver.execute('return calls.length===1')
                    if not keyboard:
                        check(engine == 'firefox', context + ': Enter did not activate Delete')
                        click(top)
                    check(driver.execute('var c=calls[0],d=JSON.parse(c.data);return calls.length===1&&c.url==="/fixture/trash/add/"&&c.type==="POST"&&c.contentType==="application/json"&&d.module===module&&d.controller===controller&&JSON.stringify(d.id)===JSON.stringify(["17","18"])&&originalButton.disabled&&originalButton.getAttribute("aria-busy")==="true"&&originalButton.classList.contains("is-loading")&&getComputedStyle(originalButton.querySelector("svg")).display==="none"'), context + ': bulk payload/loading')
                    driver.execute('document.querySelector(".dw-toolbar--bottom button[name=delete] use").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}))')
                    check(driver.execute('return calls.length===1&&confirmations.length===2'), context + ': overlap/duplicate protection')
                    driver.execute('pendingDelete(null,true)')
                    check(driver.execute('return originalButton.outerHTML===originalHTML&&$("input[data-select-id]:checked").length===2&&!Object.keys(Dewawi.actionRequests).length&&document.querySelector("#messages").textContent.trim().length>0'), context + ': failure did not restore original button/selection/error')
                    click(bottom)
                    check(driver.execute('return calls.length===2&&confirmations.length===3'), context + ': footer retry failed')
                    driver.execute('pendingDelete({ok:false,deletedIds:[17],blockedIds:[18],blockedReasons:{"18":"MESSAGES_CONTACT_DELETE_BLOCKED_OPERATIONS"}})')
                    wait('return calls.length===3')
                    check(driver.execute('return calls.length===3&&calls[2].url===baseUrl+"/"+module+"/"+controller+"/search"&&!document.querySelector("input[data-select-id=\\"17\\"]")&&!!document.querySelector("input[data-select-id=\\"18\\"]")&&!Object.keys(Dewawi.actionRequests).length&&!fixtureErrors.length&&document.querySelectorAll(".dw-icon-symbols").length===1'), context + ': partial success/AJAX refresh ' + json.dumps(driver.execute('return {calls:calls,errors:fixtureErrors,locks:Object.keys(Dewawi.actionRequests),ids:[...document.querySelectorAll("input[data-select-id]")].map(e=>e.dataset.selectId)}')))
                    if not route.startswith('admin/'):
                        check(pixels('.dw-row-actions button[name=delete] svg') > 8, context + ': refreshed Delete paints no pixels')
                        click('.dw-row-actions button[name=delete] svg')
                    else:
                        driver.execute('$("input[data-select-id]").prop("checked",true)')
                        click(top)
                    check(driver.execute('return JSON.stringify(JSON.parse(calls[calls.length-1].data).id)===JSON.stringify(["18"])'), context + ': refreshed target changed')
                    driver.execute('nextRows="empty";pendingDelete({ok:true,deletedIds:[18]})')
                    wait('return !document.querySelector("input[data-select-id]")')
                    check(driver.execute('return !document.querySelector("input[data-select-id]")&&!Object.keys(Dewawi.actionRequests).length&&!fixtureErrors.length'), context + ': successful Delete did not refresh empty list')
                    driver.execute('document.querySelector(".dw-toolbar button[name=delete]").focus()')
                    driver.keys('tab'); driver.shift_tab()
                    metric['nativeFocusVisible'] = driver.execute('var b=document.querySelector(".dw-toolbar button[name=delete]");return document.activeElement===b&&b.matches(":focus-visible")&&getComputedStyle(b).outlineWidth==="2px"')
                    check(metric['nativeFocusVisible'] or engine == 'firefox', context + ': visible keyboard focus missing')
                    measurements.append({'context': context, **metric, 'nativeEnter': keyboard})
                    print(context, 'PASS', flush=True)
                # Detail Delete targets the current record, not a selected row.
                driver.navigate(f'http://127.0.0.1:{server.server_port}/fixture/{locale}/{route}/edit')
                wait('return !!window.calls')
                driver.execute('confirmResult=true')
                click(top)
                check(driver.execute('return calls.length===1&&JSON.stringify(JSON.parse(calls[0].data).id)===JSON.stringify(["17"])'), route + ': detail ID changed')
                driver.execute('pendingDelete({ok:true,deletedIds:[17]})')
                check(driver.execute('return navigations[0]===baseUrl+"/"+module+"/"+controller&&!Object.keys(Dewawi.actionRequests).length'), route + ': detail redirect changed')
                driver.navigate(f'http://127.0.0.1:{server.server_port}/fixture/{locale}/{route}/readonly')
                wait('return !!window.calls')
                click(top)
                check(driver.execute('return !calls.length&&!confirmations.length&&[...document.querySelectorAll(".dw-btn--control")].every(b=>b.disabled)'), route + ': readonly action executed')
                if not route.startswith('admin/'):
                    driver.navigate(f'http://127.0.0.1:{server.server_port}/fixture/{locale}/{route}/index')
                    wait('return !!window.calls')
                    driver.execute('$("input[data-select-id=\\"17\\"]").prop("checked",true);confirmResult=true')
                    click('.dw-row-actions button[name=delete][data-id="18"] svg')
                    check(driver.execute('return calls.length===1&&JSON.stringify(JSON.parse(calls[0].data).id)===JSON.stringify(["18"])&&$("input[data-select-id]:checked").first().attr("data-select-id")==="17"'), route + ': inline target used unrelated selection')
                    driver.execute('pendingDelete({ok:true,deletedIds:["invalid"]})')
                    check(driver.execute('return calls.length===1&&!Object.keys(Dewawi.actionRequests).length&&$("input[data-select-id]").length===2'), route + ': invalid server result refreshed or kept request locked')
                    click('.dw-row-actions button[name=delete][data-id="18"] svg')
                    driver.execute('pendingDelete({ok:false,deletedIds:[],blockedIds:[18]})')
                    check(driver.execute('return calls.length===2&&!Object.keys(Dewawi.actionRequests).length&&$("input[data-select-id]").length===2'), route + ': blocked record changed selection/list or retry state')
        (output / 'standard-actions.json').write_text(json.dumps({'engine': engine, 'checks': checks, 'measurements': measurements, 'limitations': ['Memory-only replies, no authenticated database workflow.', 'Confirmation contract is exercised with controlled window.confirm, not native dialog UI.', 'Screenreader and WebKit not tested.', 'Native Firefox keyboard/focus results are recorded per profile; false means NOT TESTED, never PASS.']}, indent=2))
        print(f'PASS: {checks} {engine} standard Delete display and functional assertions; {len(measurements)} responsive DE/EN profiles')
        missed = sum(not m['nativeEnter'] for m in measurements)
        if missed:
            print(f'NOT TESTED: native Enter activation in {missed} headless Firefox profiles; native pointer and application dispatch passed.')
        missed_focus = sum(not m['nativeFocusVisible'] for m in measurements)
        if missed_focus:
            print(f'NOT TESTED: native keyboard focus in {missed_focus} headless Firefox profiles.')
    finally:
        if driver:
            driver.close()
        server.shutdown()
        server.server_close()
