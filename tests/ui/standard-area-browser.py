"""B18: original MVC/HTTP documents, actual application JS, synthetic write replies only."""
import base64
import io
import json
import math
import os
import re
import subprocess
import tempfile
import time
from pathlib import Path
import urllib.error
import urllib.request
from urllib.parse import urlencode

from PIL import Image


def run(root, browser, factory, engine, output):
    checks, measurements = 0, []

    def check(value, message):
        nonlocal checks
        checks += 1
        if not value:
            raise AssertionError(message)

    def wait(script):
        deadline = time.monotonic() + 10
        while not driver.execute(script):
            if time.monotonic() > deadline:
                raise AssertionError('Timed out: ' + script)
            time.sleep(.04)

    def click(selector):
        if engine == 'chromium':
            driver.click(selector)
        else:
            point = driver.execute('var b=document.querySelector(' + json.dumps(selector) + ');b.scrollIntoView({block:"center",inline:"center"});var r=b.getBoundingClientRect();return {x:Math.floor(r.x+r.width/2),y:Math.floor(r.y+r.height/2)}')
            driver.call('input.performActions', {'context': driver.context, 'actions': [{'type': 'pointer', 'id': 'mouse', 'parameters': {'pointerType': 'mouse'}, 'actions': [{'type': 'pointerMove', **point}, {'type': 'pointerDown', 'button': 0}, {'type': 'pointerUp', 'button': 0}]}]})

    def paint(selector):
        rect = driver.execute('var s=document.querySelector(' + json.dumps(selector) + ');s.scrollIntoView({block:"center",inline:"center"});var r=s.getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height,color:getComputedStyle(s).stroke,viewport:innerWidth}')
        png = driver.screenshot() if engine == 'firefox' else base64.b64decode(driver.command('GET', '/screenshot'))
        picture = Image.open(io.BytesIO(png)).convert('RGB')
        scale = picture.width / rect['viewport']
        crop = picture.crop((math.ceil(rect['x'] * scale), math.ceil(rect['y'] * scale), math.floor((rect['x'] + rect['w']) * scale), math.floor((rect['y'] + rect['h']) * scale)))
        rgb = [int(v) for v in re.findall(r'\d+', rect['color'])[:3]]
        count = sum(max(abs(c - target) for c, target in zip(pixel, rgb)) < 40 for pixel in crop.getdata())
        if count <= 8:
            (output / 'paint-failure.png').write_bytes(png)
            crop.save(output / 'paint-failure-crop.png')
            (output / 'paint-failure.json').write_text(json.dumps({'rect': rect, 'size': picture.size, 'rgb': rgb, 'count': count}))
        return count

    setup = r'''
        window.calls=[];window.navigations=[];window.confirmations=[];window.confirmResult=false;window.fixtureRows='';window.pending=null;
        window.confirm=function(message){confirmations.push(message);return confirmResult;};
        window.setLocation=function(url){navigations.push(url);};
        window.originalAjax=$.ajax;
        $.ajax=function(o){
            if(o.url.endsWith('/search'))o.data.fixture_rows=fixtureRows;
            calls.push({url:o.url,type:o.type,data:o.data,contentType:o.contentType});
            if(o.url.endsWith('/search'))return originalAjax(o);
            if(o.url!==baseUrl+'/trash/add/'&&!o.url.includes('/copy/id/'))throw Error('Unexpected write request: '+o.url);
            var d=$.Deferred();
            pending=function(result,error){
                if(error){var e={status:403,responseJSON:{ok:false}};if(o.error)o.error(e);d.reject(e);}
                else {if(o.success)o.success(result);d.resolve(result);}
                if(o.complete)o.complete();pending=null;
            };
            return d.promise({abort:function(){if(o.complete)o.complete();}});
        };
    '''
    routes = ['processes/process', 'items/pricerule', 'admin/shop']
    with tempfile.TemporaryDirectory(prefix='dewawi-b18-sessions-') as sessions:
        forms = Path(sessions) / 'forms'
        for module in ['processes', 'items', 'admin']:
            for name in ['Toolbar.php', 'ToolbarInline.php']:
                relative = f'application/modules/{module}/forms/{name}'
                target = forms / relative
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_bytes(subprocess.check_output(['git', 'show', '7ceb5e6b:' + relative], cwd=root))
        port = browser.free_port()
        log = (output / 'http.log').open('w')
        server = subprocess.Popen(['php', '-d', 'opcache.enable_cli=0', '-S', f'127.0.0.1:{port}', str(root / 'tests/ui/button-page-integration.php')], cwd=root, env=dict(os.environ, DEWAWI_ICON_TEST_SESSIONS=sessions, DEWAWI_ICON_TEST_AREA_FORMS=str(forms), DEWAWI_ICON_TEST_COMPARE_AREAS='1'), stdout=log, stderr=log)
        driver = None
        origin = f'http://127.0.0.1:{port}'
        try:
            for _ in range(100):
                try:
                    urllib.request.urlopen(origin + '/css/ui.css', timeout=1).close()
                    break
                except OSError:
                    time.sleep(.05)
            driver = factory()
            for locale in ['de_DE', 'en_US']:
                for route in routes:
                    for tenant in ['foreign', 'shared']:
                        if route == 'admin/shop' and tenant == 'shared':
                            continue
                        request = urllib.request.Request(origin + '/' + route + '/edit/id/17?' + urlencode({'fixture_locale': locale, 'fixture_tenant': tenant}), headers={'X-Requested-With': 'XMLHttpRequest'})
                        with urllib.request.urlopen(request) as response:
                            content = response.read().decode()
                        if tenant == 'foreign':
                            check(json.loads(content).get('ok') is False, route + ': foreign tenant could load record')
                        else:
                            legacy_request = urllib.request.Request(request.full_url + '&fixture_legacy=1', headers={'X-Requested-With': 'XMLHttpRequest'})
                            with urllib.request.urlopen(legacy_request) as response: previous = response.read().decode()
                            check(content == previous and json.loads(content).get('ok') is False, route + ': existing strict edit scope changed by presentation')
                    for action in ['index', 'edit/id/17', 'select']:
                        path = '/' + route + '/' + action
                        url = origin + path + '?' + urlencode({'fixture_locale': locale})
                        with urllib.request.urlopen(url) as response:
                            html = response.read().decode()
                            check(response.status == 200 and html.count('<symbol ') == 14 and '<html ' in html and '<body' in html, path + ': incomplete MVC document')
                        (output / (locale + '-' + route.replace('/', '-') + '-' + action.replace('/', '-') + '.html')).write_text(html)
                        for width in [320, 390, 768, 1280]:
                            context = f'{locale}/{route}/{action}/{width}'
                            driver.emulate_viewport(width, 1100)
                            driver.navigate(url + '&fixture_legacy=1')
                            wait('return !!window.DewawiToolbar&&!!window.jQuery')
                            legacy = driver.execute('return {width:document.documentElement.scrollWidth,tables:[...document.querySelectorAll(".dw-table-wrap")].map(e=>({w:e.clientWidth,scroll:e.scrollWidth,overflow:getComputedStyle(e).overflowX})),buttons:[...document.querySelectorAll(".dw-toolbar button,.dw-row-actions button")].map(b=>({attributes:Object.fromEntries([...b.attributes].map(a=>[a.name,a.value])),text:b.textContent.trim()}))}')
                            driver.navigate(url)
                            wait('return !!window.DewawiToolbar&&!!window.jQuery')
                            driver.execute(setup + ';$.ajaxSetup({headers:{"X-Icon-Fixture-Locale":' + json.dumps(locale) + '}});return true')
                            metric = driver.execute(r'''
                                var controls=[...document.querySelectorAll('.dw-toolbar .dw-btn--control,.dw-row-actions .dw-btn--control')];
                                return {symbols:document.querySelectorAll('symbol[id^="dw-icon-"]').length,banks:document.querySelectorAll('.dw-icon-symbols').length,
                                    unresolved:[...document.querySelectorAll('use[href^="#dw-icon-"]')].filter(u=>!document.getElementById(u.getAttribute('href').slice(1))).length,
                                    controls:controls.map(b=>{var i=b.querySelector('svg'),r=b.getBoundingClientRect();return {name:b.name,type:b.type,w:r.width,h:r.height,label:b.getAttribute('aria-label')||b.textContent.trim(),stroke:getComputedStyle(i).stroke,color:getComputedStyle(b).color,bg:getComputedStyle(b).backgroundImage};}),
                                    rows:[...document.querySelectorAll('tbody .dw-row')].map(e=>e.getBoundingClientRect().height),width:document.documentElement.scrollWidth,
                                    tables:[...document.querySelectorAll('.dw-table-wrap')].map(e=>({w:e.clientWidth,scroll:e.scrollWidth,overflow:getComputedStyle(e).overflowX})),
                                    contained:[...document.querySelectorAll('.dw-table-wrap')].every(e=>e.scrollWidth<=e.clientWidth+1||['auto','scroll'].includes(getComputedStyle(e).overflowX))};
                            ''')
                            check(metric['symbols'] == 14 and metric['banks'] == 1 and not metric['unresolved'], context + ': symbol resolution')
                            check(metric['controls'] and all(b['type'] == 'button' and b['label'] and b['stroke'] == b['color'] and b['bg'] == 'none' and b['h'] >= (44 if width < 768 else 32) for b in metric['controls']), context + ': native/name/color/touch contract')
                            check(metric['width'] <= legacy['width'] + 1 and len(metric['tables']) == len(legacy['tables']) and all(t['scroll'] <= old['scroll'] + 1 and t['overflow'] == old['overflow'] for t, old in zip(metric['tables'], legacy['tables'])), context + ': layout worsened relative to original forms')
                            metric['legacyLayout'] = legacy
                            current_buttons = driver.execute('return [...document.querySelectorAll(".dw-toolbar button,.dw-row-actions button")].map(b=>({attributes:Object.fromEntries([...b.attributes].map(a=>[a.name,a.value])),text:b.textContent.trim()}))')
                            check(len(current_buttons) == len(legacy['buttons']), context + ': action count changed')
                            for new_button, old_button in zip(current_buttons, legacy['buttons']):
                                a, b = dict(new_button['attributes']), dict(old_button['attributes'])
                                new_classes, old_classes = set(a.pop('class', '').split()), set(b.pop('class', '').split())
                                if a.get('name') == 'filter':
                                    for key in ['aria-controls', 'aria-expanded']: a.pop(key, None); b.pop(key, None)
                                check(a == b and old_classes <= new_classes and new_button['text'] == old_button['text'], context + ': native attributes, names, labels or hooks changed')
                            metric['pixels'] = paint('.dw-toolbar .dw-btn--control svg')
                            check(metric['pixels'] >= 3, context + ': SVG draws no pixels')
                            if action == 'edit/id/17':
                                click('.dw-toolbar button[name=copy] svg')
                                check(driver.execute('return calls.length===1&&calls[0].url===baseUrl+"/"+module+"/"+controller+"/copy/id/17"'), context + ': detail copy target')
                                driver.execute('pending(null,true)')
                                check(driver.execute('return !document.querySelector(".dw-toolbar button[name=copy]").disabled'), context + ': detail copy error recovery')
                                driver.execute('confirmResult=true')
                                click('.dw-toolbar button[name=delete] svg')
                                check(driver.execute('return JSON.stringify(JSON.parse(calls[calls.length-1].data).id)===JSON.stringify(["17"])'), context + ': detail delete target')
                                driver.execute('pending({ok:true,deletedIds:[17]})')
                                check(driver.execute('return navigations[0]===baseUrl+"/"+module+"/"+controller'), context + ': detail redirect')
                            else:
                                if action == 'index':
                                    check(driver.execute('var b=document.querySelector(".dw-toolbar button[name=filter]");return !!b&&b.getAttribute("aria-controls")&&!!document.getElementById(b.getAttribute("aria-controls"))'), context + ': disclosure linkage')
                                    click('.dw-toolbar button[name=filter]')
                                    check(driver.execute('return document.querySelector(".dw-toolbar button[name=filter]").getAttribute("aria-expanded")==="true"&&$(".dw-filter-panel").hasClass("is-open")'), context + ': disclosure open')
                                driver.execute('window.previousBody=document.querySelector("tbody");$("input[name=sort][value=ASC]").prop("checked",true).trigger("change")')
                                wait('return previousBody!==document.querySelector("tbody")&&!!document.querySelector("button[data-action=clear-filter]")')
                                check(driver.execute('return calls[calls.length-1].data.sort==="ASC"&&calls[calls.length-1].data.context_action===action&&document.querySelectorAll(".dw-icon-symbols").length===1'), context + ': real HTTP filter/selection context')
                                check(paint('button[data-action=clear-filter] svg') >= 3, context + ': refreshed chip draws no pixels')
                                click('button[data-action=clear-filter] svg')
                                wait('return !document.querySelector("button[data-action=clear-filter]")')
                                driver.execute('$("input[name=keyword]").val("fixture").trigger("input")')
                                wait('return calls[calls.length-1].data.keyword==="fixture"&&$("#content").attr("aria-busy")!=="true"')
                                click('.dw-toolbar button[name=clear] svg')
                                wait('return calls[calls.length-1].data.keyword===""&&$("#content").attr("aria-busy")!=="true"')
                                if action == 'index':
                                    click('.dw-toolbar button[name=reset] svg')
                                    wait('return $("#content").attr("aria-busy")!=="true"&&document.querySelector(".dw-toolbar button[name=filter]").getAttribute("aria-expanded")==="true"')
                                    click('.dw-toolbar button[name=filter] svg')
                                    driver.execute('document.querySelector(".dw-toolbar button[name=filter]").focus()')
                                    driver.keys('tab'); driver.shift_tab(); driver.keys('enter')
                                    metric['keyboard'] = driver.execute('var b=document.querySelector(".dw-toolbar button[name=filter]");return b.getAttribute("aria-expanded")==="true"&&b.matches(":focus-visible")&&getComputedStyle(b).outlineWidth==="2px"')
                                    check(metric['keyboard'] or engine == 'firefox', context + ': native keyboard/focus')
                                if action == 'index':
                                    if route != 'admin/shop':
                                        check(paint('.dw-row-actions .dw-btn--control svg') >= 3, context + ': inline SVG draws no pixels')
                                    driver.execute('calls=[];navigations=[];$("input[data-select-id=\\"18\\"]").prop("checked",true)')
                                    if route != 'admin/shop':
                                        click('.dw-row-actions button[name=edit][data-id="17"] svg')
                                        check(driver.execute('return navigations[0]===baseUrl+"/"+module+"/"+controller+"/edit/id/17"&&!calls.length&&$("input[data-select-id]:checked").attr("data-select-id")==="18"'), context + ': row navigation used unrelated checkbox selection')
                                        driver.execute('navigations=[]')
                                    click('.dw-toolbar button[name=edit] svg')
                                    check(driver.execute('return navigations[0]===baseUrl+"/"+module+"/"+controller+"/edit/id/18"&&!calls.length'), context + ': selected navigation target')
                                    selector = '.dw-row-actions button[name=copy][data-id="18"]'
                                    driver.execute('window.copyButton=document.querySelector(' + json.dumps(selector) + ');window.copyHTML=copyButton.outerHTML')
                                    click(selector + ' svg')
                                    check(driver.execute('return calls.length===1&&calls[0].url.endsWith("/copy/id/18")&&copyButton.disabled'), context + ': row copy used unrelated target/loading')
                                    driver.execute('document.querySelector(".dw-toolbar--bottom button[name=copy]").click();pending(null,true)')
                                    check(driver.execute('return calls.length===1&&copyButton.outerHTML===copyHTML&&$("input[data-select-id]:checked").length===1'), context + ': copy lock/error/selection')
                                    click(selector + ' svg')
                                    driver.execute('window.previousBody=document.querySelector("tbody");pending({ok:true,id:118})')
                                    wait('return previousBody!==document.querySelector("tbody")')
                                    driver.execute('calls=[];confirmations=[];$("input[data-select-id]").prop("checked",true)')
                                    click('.dw-toolbar button[name=delete] svg')
                                    check(driver.execute('return !calls.length&&confirmations.length===1&&confirmations[0]===deleteConfirm'), context + ': cancel sent a request')
                                    driver.execute('confirmResult=true;window.deleteButton=document.querySelector(".dw-toolbar button[name=delete]");window.deleteHTML=deleteButton.outerHTML')
                                    click('.dw-toolbar button[name=delete] svg')
                                    check(driver.execute('var c=calls[0],d=JSON.parse(c.data);return calls.length===1&&c.url===baseUrl+"/trash/add/"&&c.type==="POST"&&c.contentType==="application/json"&&d.module===module&&d.controller===controller&&JSON.stringify(d.id)===JSON.stringify(["17","18"])&&deleteButton.disabled&&deleteButton.getAttribute("aria-busy")==="true"&&getComputedStyle(deleteButton.querySelector("svg")).display==="none"'), context + ': delete payload/loading')
                                    driver.execute('document.querySelector(".dw-toolbar--bottom button[name=delete]").click();pending(null,true)')
                                    check(driver.execute('return calls.length===1&&deleteButton.outerHTML===deleteHTML&&!Object.keys(Dewawi.actionRequests).length&&$("input[data-select-id]:checked").length===2'), context + ': delete overlap/error/retry state')
                                    click('.dw-toolbar--bottom button[name=delete] svg')
                                    driver.execute('fixtureRows="remaining";window.previousBody=document.querySelector("tbody");pending({ok:false,deletedIds:[17],blockedIds:[18]})')
                                    wait('return previousBody!==document.querySelector("tbody")')
                                    check(driver.execute('return !document.querySelector("input[data-select-id=\\"17\\"]")&&!!document.querySelector("input[data-select-id=\\"18\\"]")&&!Object.keys(Dewawi.actionRequests).length&&document.querySelectorAll(".dw-icon-symbols").length===1'), context + ': partial delete/actual refresh')
                                    check(paint('.dw-toolbar--bottom button[name=delete] svg') >= 3, context + ': dynamic footer Delete draws no pixels')
                                    if route == 'admin/shop':
                                        check(driver.execute('return [...document.querySelectorAll(".dw-row-actions button[name=delete]")].every(b=>!b.querySelector("svg"))'), context + ': media-delete migrated')
                                    if route == 'items/pricerule':
                                        check(driver.execute('return [...document.querySelectorAll("button[name=apply]")].every(b=>!b.querySelector("svg"))'), context + ': Index Apply migrated')
                                    if route == 'processes/process':
                                        check(driver.execute('return [...document.querySelectorAll(".dw-row-actions button[name=pdf]")].every(b=>!b.querySelector("svg"))'), context + ': PDF migrated')
                                    driver.execute('$("input[data-select-id]").prop("checked",true)')
                                    click('.dw-toolbar button[name=delete] svg')
                                    driver.execute('fixtureRows="empty";pending({ok:true,deletedIds:[18]})')
                                    wait('return !document.querySelector("input[data-select-id]")')
                                    driver.execute('calls=[];confirmations=[]')
                                    click('.dw-toolbar button[name=delete] svg')
                                    check(driver.execute('return !calls.length&&!confirmations.length'), context + ': empty selection triggered action')
                            measurements.append({'context': context, **metric})
                            print('PASS:', context, flush=True)
                    for role in ['denied', 'readonly']:
                        if route == 'admin/shop' and role == 'readonly':
                            continue
                        url = origin + '/' + route + '?' + urlencode({'fixture_locale': locale, 'fixture_role': role})
                        if role == 'denied':
                            try:
                                urllib.request.urlopen(url)
                                check(False, route + ': denied role received 200')
                            except urllib.error.HTTPError as error:
                                check(error.code == 403, route + ': denied status')
                        else:
                            driver.navigate(url)
                            wait('return !!window.DewawiToolbar')
                            check(driver.execute('return !document.querySelector("button[name=edit],button[name=copy],button[name=delete],button[name=add]")'), route + ': readonly rights bypass')
            result = {'engine': engine, 'checks': checks, 'measurements': measurements, 'limitations': ['Synthetic identity/read adapter, not a production login or full SQL evaluator.', 'Write replies and window.confirm are controlled; search uses real local HTTP.', 'Firefox native keyboard/focus is NOT TESTED wherever keyboard=false.', 'Native dialogs, WebKit, screenreader and third-party widgets are not tested.']}
            (output / 'standard-areas.json').write_text(json.dumps(result, indent=2))
            print(f'PASS: {checks} {engine} B18 full MVC/HTTP display and function assertions; {len(measurements)} profiles')
            missing_keyboard = sum(m.get('keyboard') is False for m in measurements)
            if missing_keyboard: print(f'NOT TESTED: native keyboard/focus in {missing_keyboard} headless Firefox Index profiles.')
        finally:
            if driver:
                driver.close()
            server.terminate(); server.wait(timeout=10); log.close()
