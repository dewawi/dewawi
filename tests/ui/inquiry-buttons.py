"""Exercise the production Inquiry views, layouts and assets with synthetic data, never the application DB."""
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
before = '--before' in sys.argv
spec = importlib.util.spec_from_file_location('inquiry_button_driver', root / 'tests/site/accessibility-http.py')
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
    for layout in ['layout', 'plain']:
        for action in ['index', 'edit']:
            for state in ['', 'readonly']:
                result = subprocess.run(['php', str(root / 'tests/ui/inquiry-buttons.php'), 'render', locale, action, layout, state], capture_output=True, text=True, check=True)
                pages[f'/fixture/{locale}/{action}/{layout}/{state}'] = json.loads(result.stdout)['html'].encode()
    result = subprocess.run(['php', str(root / 'tests/ui/inquiry-buttons.php'), 'render', locale, 'search'], capture_output=True, text=True, check=True)
    searches[locale.replace('_', '-')] = json.loads(result.stdout)

setup = ('var fixtureSearches=' + json.dumps(searches).replace('<', '\\u003c') + ';' + r'''
window.fixtureCalls=[];window.fixtureNavigations=[];window.fixtureSubmits=[];
$.ajax=function(options){
    fixtureCalls.push({url:options.url,data:options.data});
    var d=$.Deferred(), response=options.url.indexOf('/search')!==-1 ? fixtureSearches[document.documentElement.lang] : {ok:true,saved:true};
    if(options.success) options.success(response);
    d.resolve(response);
    if(options.complete) options.complete();
    return d.promise({abort:function(){}});
};
window.setLocation=function(url){fixtureNavigations.push(url);};
$(function(){
    $('form').on('submit',function(event){event.preventDefault();var b=event.originalEvent.submitter;fixtureSubmits.push({name:b.name,type:b.type,value:b.value});});
});
''').encode()

class InquiryPages(BaseHTTPRequestHandler):
    def do_GET(self):
        path = unquote(urlparse(self.path).path)
        if path in pages:
            payload, kind = pages[path], 'text/html; charset=utf-8'
        elif path == '/fixture/setup.js':
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

server = ThreadingHTTPServer(('127.0.0.1', 0), InquiryPages)
worker = Thread(target=server.serve_forever, daemon=True)
worker.start()
driver = None
try:
    driver = browser.WebDriver()
    driver.command('POST', '/goog/cdp/execute', {'cmd': 'Page.addScriptToEvaluateOnNewDocument', 'params': {'source': 'window.fixtureErrors=[];addEventListener("error",function(e){fixtureErrors.push(e.message||e.target.src||e.target.href||"resource error")},true)'}})
    for locale in ['de_DE', 'en_US']:
        for layout in ['layout', 'plain']:
            for action in ['index', 'edit']:
                for width in [390, 768, 1280]:
                    context = f'{locale}/{layout}/{action}/{width}'
                    driver.emulate_viewport(width, 1100)
                    driver.navigate(f'http://127.0.0.1:{server.server_port}/fixture/{locale}/{action}/{layout}/')
                    driver.wait('return !!window.DewawiToolbar && !!window.fixtureCalls')
                    metrics = driver.execute(r'''
                        var controls=[...document.querySelectorAll('button.dw-btn--control')];
                        return {errors:fixtureErrors,overflow:document.documentElement.scrollWidth>innerWidth,
                            symbols:document.querySelectorAll('.dw-icon-symbols').length,
                            buttons:controls.map(b=>{var i=b.querySelector('.dw-btn__icon'),r=b.getBoundingClientRect(),ir=i.getBoundingClientRect(),label=b.querySelector('.dw-btn__label'),cell=b.closest('td'),cr=cell&&cell.getBoundingClientRect();return {
                                name:b.name,type:b.type,width:r.width,height:r.height,icon:i.tagName,
                                background:getComputedStyle(i).backgroundImage,stroke:getComputedStyle(i).stroke,color:getComputedStyle(b).color,
                                geometry:i.tagName==='svg'&&i.querySelector('use').getBBox().width>0,
                                center:Math.abs((ir.top+ir.height/2)-(r.top+r.height/2)),
                                fitsCell:!cr||(r.left>=cr.left&&r.right<=cr.right&&r.top>=cr.top&&r.bottom<=cr.bottom),
                                gap:label?label.getBoundingClientRect().left-ir.right:null,
                                label: b.getAttribute('aria-label')||b.textContent.trim(),title:b.title,
                                decorative:i.getAttribute('aria-hidden')==='true'&&(!i.hasAttribute('focusable')||i.getAttribute('focusable')==='false')
                            }})};
                    ''')
                    check(not metrics['errors'], f'{context}: asset or script error: {metrics["errors"]}')
                    check(not metrics['overflow'], f'{context}: document overflow')
                    check(len(metrics['buttons']) == (7 if action == 'index' else 1), f'{context}: wrong pilot scope')
                    check(metrics['symbols'] == (0 if before else 1), f'{context}: missing/duplicate document symbols')
                    for b in metrics['buttons']:
                        check(b['decorative'] and b['label'] and not b['label'].startswith(('TOOLBAR_', 'CONTACT_', 'CLEAR')) and not b['title'].startswith(('TOOLBAR_', 'CONTACT_')), f'{context}: bad accessible name: {b}')
                        check(b['height'] >= (44 if width < 768 else (32 if b['name'] in ['view', 'edit'] else 36)), f'{context}: small hitbox: {b}')
                        if not before and b['name'] == 'save':
                            check(b['height'] <= 44, f'{context}: status button still wraps inside one grid column: {b}')
                        check(b['fitsCell'] and b['center'] <= 1 and (b['gap'] is None or abs(b['gap']-8) <= 1), f'{context}: clipped control or icon alignment/gap: {b}')
                        check(b['background'] != 'none' if before else b['icon'] == 'svg' and b['geometry'] and b['stroke'] == b['color'] and b['background'] == 'none', f'{context}: missing icon: {b}')
                    accessibility = driver.command('POST', '/goog/cdp/execute', {'cmd': 'Accessibility.getFullAXTree', 'params': {}})
                    names = {node.get('name', {}).get('value') for node in accessibility['nodes'] if node.get('role', {}).get('value') == 'button'}
                    check({b['label'] for b in metrics['buttons']} <= names, f'{context}: pilot name missing in accessibility tree')

                    selector = 'button[name=filter]' if action == 'index' else 'button[name=save]'
                    driver.execute('document.querySelector(arguments[0]).focus()', [selector])
                    driver.keys('tab')
                    driver.shift_tab()
                    check(driver.execute('var b=document.activeElement;return b.matches(arguments[0])&&getComputedStyle(b).outlineWidth==="2px"&&getComputedStyle(b).outlineStyle==="solid"&&b.matches(":focus-visible")', [selector]), f'{context}: keyboard focus missing')
                    # Real pointer hover and native disabled/loading states on the same rendered control.
                    rect = driver.execute('var r=document.querySelector(arguments[0]).getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2}', [selector])
                    driver.command('POST', '/actions', {'actions': [{'type': 'pointer', 'id': 'mouse', 'parameters': {'pointerType': 'mouse'}, 'actions': [{'type': 'pointerMove', 'duration': 0, 'x': int(rect['x']), 'y': int(rect['y'])}]}]})
                    driver.execute('window.hoveredButton=document.querySelector(arguments[0]);var p=document.createElement("span");p.style.color=getComputedStyle(hoveredButton).getPropertyValue("--dw-btn-hover-background");document.body.append(p);window.hoverExpectedColor=getComputedStyle(p).color;p.remove()', [selector])
                    driver.wait('return hoveredButton.matches(":hover")&&getComputedStyle(hoveredButton).backgroundColor===hoverExpectedColor')
                    check(driver.execute('return hoveredButton.matches(":hover")'), f'{context}: hover absent')
                    check(driver.execute(r'''
                        var b=$(arguments[0]),i=b.find('.dw-btn__icon')[0],html=b.html(),name=b.attr('aria-label');
                        setButtonLoading(b,true);var busy=b.prop('disabled')&&b.attr('aria-busy')==='true'&&getComputedStyle(i).display==='none';
                        setButtonLoading(b,false);setButtonLoading(b,true,'Loading');setButtonLoading(b,false);
                        var restored=b.html()===html&&b.find('.dw-btn__icon')[0]===i&&b.attr('aria-label')===name;
                        b.prop('disabled',true);var dimmed=parseFloat(getComputedStyle(b[0]).opacity)<1;
                        fixtureCalls=[];fixtureSubmits=[];i.dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true}));
                        var locked=!fixtureCalls.length&&!fixtureSubmits.length;
                        setButtonLoading(b,true);setButtonLoading(b,false);var stillDisabled=b.prop('disabled');b.prop('disabled',false);
                        return busy&&restored&&dimmed&&locked&&stillDisabled;
                    ''', [selector]), f'{context}: loading/disabled changed content or behavior')

                    if action == 'index':
                        driver.execute('document.querySelector("button[name=filter]").focus()')
                        driver.keys('space')
                        check(driver.execute('return $(".dw-filter-panel").hasClass("is-open")'), f'{context}: Space did not open filters')
                        driver.execute('window.originalRow=document.querySelector(".dw-row-actions button");window.originalFilter=document.querySelector("button[name=filter]");fixtureCalls=[];document.querySelector("button[name=reset]").focus()')
                        driver.keys('enter')
                        driver.wait('return fixtureCalls.length===1&&originalRow!==document.querySelector(".dw-row-actions button")')
                        check(driver.execute('return fixtureCalls[0].url==="/fixture/contacts/inquiry/search"&&fixtureCalls[0].data.context_action==="index"&&originalFilter===document.querySelector("button[name=filter]")'), f'{context}: AJAX changed the route or replaced the persistent toolbar')
                        driver.execute('fixtureCalls=[];$("input[name=keyword]").val("example");document.querySelector("button[name=clear] .dw-btn__icon").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}))')
                        driver.wait('return fixtureCalls.length===1')
                        check(driver.execute('return $("input[name=keyword]").val()===""'), f'{context}: clear lost field target')
                        driver.execute('fixtureCalls=[];$("input[name=keyword]").val("example");document.querySelector("button[name=clear]").focus()')
                        driver.keys('space')
                        driver.wait('return fixtureCalls.length===1')
                        check(driver.execute('return $("input[name=keyword]").val()===""'), f'{context}: icon-only clear failed with Space')
                        driver.execute('document.querySelector(".dw-row-actions button[data-action=edit] .dw-btn__icon").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}))')
                        check(driver.execute('return fixtureNavigations.length===1&&fixtureNavigations[0]==="/fixture/contacts/inquiry/edit/id/17"'), f'{context}: dynamic row target changed')
                        driver.execute('document.querySelector(".dw-row-actions button[data-action=view]").focus()')
                        driver.keys('enter')
                        check(driver.execute('return fixtureNavigations.length===2&&fixtureNavigations[1]==="/fixture/contacts/inquiry/view/id/17"'), f'{context}: icon-only view failed with Enter')
                        if not before:
                            check(driver.execute('return document.querySelectorAll(".dw-icon-symbols").length===1&&[...document.querySelectorAll(".dw-btn--control use")].every(i=>i.getBBox().width>0)'), f'{context}: AJAX lost geometry/duplicated symbols')
                    else:
                        driver.execute('fixtureCalls=[];$("select[name=status]").val("in_progress").trigger("change")')
                        check(driver.execute('return fixtureCalls.length===0' if layout == 'plain' else 'return fixtureCalls.length===1&&fixtureCalls[0].data.status==="in_progress"&&fixtureCalls[0].url==="/fixture/contacts/inquiry/edit/id/17"'), f'{context}: existing autosave binding changed')
                        driver.execute('fixtureCalls=[];$("select[name=status]").closest("form").attr("data-autosave","false");$("select[name=status]").val("completed").trigger("change")')
                        check(driver.execute('return fixtureCalls.length===0'), f'{context}: autosave opt-out ignored')
                        driver.execute('document.querySelector("button[name=save]").focus()')
                        driver.keys('enter')
                        check(driver.execute('return fixtureSubmits.length===1&&fixtureSubmits[0].name==="save"&&fixtureSubmits[0].type==="submit"'), f'{context}: native keyboard submit changed')
                    check(driver.execute('return fixtureErrors.length===0'), f'{context}: runtime errors')
                    if width in [390, 1280]:
                        if action == 'index':
                            shot = driver.command('GET', '/element/' + driver.element('.dw-row-actions') + '/screenshot')
                            Path(f'/tmp/dewawi-b4-{"before" if before else "after"}-{locale}-{layout}-rows-{width}.png').write_bytes(base64.b64decode(shot))
                        driver.execute('window.scrollTo(0,0);$(".dw-filter-panel").removeClass("is-open")')
                        shot = driver.command('GET', '/screenshot')
                        Path(f'/tmp/dewawi-b4-{"before" if before else "after"}-{locale}-{layout}-{action}-{width}.png').write_bytes(base64.b64decode(shot))
                    print(f'PASS: {context} production templates/assets, states, keyboard and behavior')
    for locale in ['de_DE', 'en_US']:
        for layout in ['layout', 'plain']:
            for action in ['index', 'edit']:
                for width in [390, 1280]:
                    context = f'{locale}/{layout}/{action}/{width}/readonly'
                    driver.emulate_viewport(width, 1100)
                    driver.navigate(f'http://127.0.0.1:{server.server_port}/fixture/{locale}/{action}/{layout}/readonly')
                    driver.wait('return !!window.DewawiToolbar && !!window.fixtureCalls')
                    check(driver.execute('return [...document.querySelectorAll("button.dw-btn--control")].every(b=>b.disabled&&parseFloat(getComputedStyle(b).opacity)<1)'), f'{context}: readonly control was enabled or invisible disabled state')
                    if not before:
                        check(driver.execute('return [...document.querySelectorAll(".dw-btn--control use")].every(i=>i.getBBox().width>0)'), f'{context}: readonly SVG missing')
                    driver.execute('fixtureCalls=[];fixtureSubmits=[];document.querySelectorAll("button.dw-btn--control").forEach(b=>{b.click();b.querySelector(".dw-btn__icon").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}))})')
                    check(driver.execute('return !fixtureCalls.length&&!fixtureSubmits.length&&!fixtureNavigations.length'), f'{context}: disabled child click bypassed guard')
                    check(driver.execute('return fixtureErrors.length===0'), f'{context}: script or asset error')
    print(f'PASS: {checks} real-render Inquiry browser assertions ({"PNG before" if before else "SVG after"})')
finally:
    if driver:
        driver.close()
    server.shutdown()
    server.server_close()
    worker.join()
