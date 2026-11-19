"""Test original ERP controllers over HTTP with a read-only synthetic adapter, never production config."""
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urlencode
import argparse
import base64
import importlib.util
import io
import json
import os
import re
import subprocess
import sys
import tempfile
import time
import urllib.request
from PIL import Image

sys.dont_write_bytecode = True
root = Path(__file__).resolve().parents[2]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--layouts', type=Path, help='Optional old layout snapshots for a red regression run')
parser.add_argument('--compare-layouts', type=Path, help='Compare every button byte-for-byte with these prior layouts')
parser.add_argument('--output', type=Path, default=Path('/tmp/dewawi-b14'))
parser.add_argument('--inspect-html', type=Path, help='Inspect an explicitly supplied HTML file without HTTP or database access')
args = parser.parse_args()
png_keys = set(re.findall(r'\.dw-btn\.([a-z-]+),\s*\.dw-btn__icon--', (root / 'css/ui.css').read_text()))


class Inventory(HTMLParser):
    def __init__(self, html):
        super().__init__()
        self.buttons, self.symbols, self.references, self.svg_count = [], [], [], 0
        self.button = None
        self.feed(html)

    def handle_starttag(self, tag, attributes):
        attributes = dict(attributes)
        if tag == 'button':
            action = attributes.get('data-action', attributes.get('name', 'other'))
            if 'data-contact-tag-operation' in attributes:
                action = 'contact-tag-' + attributes['data-contact-tag-operation']
            elif 'data-contact-export-profile' in attributes:
                action = 'contact-export-' + attributes['data-contact-export-profile']
            elif attributes.get('onclick', '').startswith('pin('):
                action = 'pin'
            self.button = {'classes': attributes.get('class', '').split(), 'action': action, 'svg': False}
            self.buttons.append(self.button)
        if tag == 'svg':
            self.svg_count += 1
            if self.button is not None:
                self.button['svg'] = True
        if tag == 'symbol':
            self.symbols.append(attributes.get('id'))
        if tag == 'use' and attributes.get('href', '').startswith('#dw-icon-'):
            self.references.append(attributes['href'][1:])

    def handle_endtag(self, tag):
        if tag == 'button':
            self.button = None

    def result(self):
        legacy = {}
        for button in self.buttons:
            if 'dw-btn--control' in button['classes'] or button['svg']:
                continue
            mechanism = 'png-class' if png_keys.intersection(button['classes']) else 'no-action-icon-class'
            key = button['action'] + ' / ' + mechanism
            legacy[key] = legacy.get(key, 0) + 1
        return {'buttons': len(self.buttons), 'svg_elements': self.svg_count, 'svg_buttons': sum(b['svg'] for b in self.buttons), 'modern_buttons': sum('dw-btn--control' in b['classes'] for b in self.buttons), 'symbols': len(self.symbols), 'references': len(self.references), 'unresolved': sorted(set(self.references) - set(self.symbols)), 'legacy_groups': legacy}


if args.inspect_html:
    result = Inventory(args.inspect_html.read_text()).result()
    print(json.dumps(result, indent=2))
    raise SystemExit(1 if result['unresolved'] else 0)

args.output.mkdir(parents=True, exist_ok=True)
spec = importlib.util.spec_from_file_location('page_icon_driver', root / 'tests/site/accessibility-http.py')
browser = importlib.util.module_from_spec(spec)
spec.loader.exec_module(browser)
checks, results = 0, []


def check(value, message):
    global checks
    checks += 1
    if not value:
        raise AssertionError(message)


def paint(driver, selector, name):
    driver.execute('document.querySelector(' + json.dumps(selector) + ').scrollIntoView({block:"center",inline:"center"})')
    element = driver.element(selector)
    png = base64.b64decode(driver.command('GET', f'/element/{element}/screenshot'))
    (args.output / (name + '.png')).write_bytes(png)
    foreground = driver.execute('return getComputedStyle(document.querySelector(' + json.dumps(selector) + ')).stroke')
    rgb = [int(v) for v in re.findall(r'\d+', foreground)[:3]]
    count = sum(max(abs(c - wanted) for c, wanted in zip(pixel[:3], rgb)) < 40 for pixel in Image.open(io.BytesIO(png)).convert('RGB').getdata())
    return count


with tempfile.TemporaryDirectory(prefix='dewawi-button-pages-') as directory:
    env = dict(os.environ, DEWAWI_ICON_TEST_SESSIONS=directory)
    if args.layouts:
        env['DEWAWI_ICON_TEST_LAYOUTS'] = str(args.layouts.resolve())
    port = browser.free_port()
    log = (args.output / 'http-server.log').open('w')
    server = subprocess.Popen(['php', '-d', 'opcache.enable_cli=0', '-S', f'127.0.0.1:{port}', str(root / 'tests/ui/button-page-integration.php')], cwd=root, env=env, stdout=log, stderr=log)
    driver = None
    origin = f'http://127.0.0.1:{port}'
    try:
        for _ in range(100):
            try:
                with urllib.request.urlopen(origin + '/css/ui.css', timeout=1) as response:
                    if response.status == 200:
                        break
            except OSError:
                time.sleep(.05)
        else:
            raise RuntimeError('Isolated PHP HTTP server did not start')
        driver = browser.WebDriver()
        driver.command('POST', '/goog/cdp/execute', {'cmd': 'Network.enable', 'params': {}})
        paths = ['/contacts/contact?fixture_contextless=1', '/items/item/select?fixture_contextless=1', '/contacts/contact', '/items/item', '/', '/contacts/contact/select', '/items/item/select', '/erp/contacts/contact', '/erp/items/item/select', '/tasks/task', '/sales/quote']
        for locale in ['de_DE', 'en_US']:
            driver.command('POST', '/goog/cdp/execute', {'cmd': 'Network.setExtraHTTPHeaders', 'params': {'headers': {'X-Icon-Fixture-Locale': locale}}})
            for index, path in enumerate(paths):
                url = origin + path + ('&' if '?' in path else '?') + urlencode({'fixture_locale': locale})
                with urllib.request.urlopen(url) as response:
                    html = response.read().decode()
                    check(response.headers.get('Content-Security-Policy') == "frame-ancestors 'self'", 'Original internal CSP changed')
                    check(int(response.headers.get('X-Button-Fixture-Queries', '0')) > 0, 'Controller did not execute synthetic queries')
                inventory = Inventory(html).result()
                if args.compare_layouts:
                    previous = subprocess.check_output(['php', str(root / 'tests/ui/button-page-integration.php'), path + ('&' if '?' in path else '?') + urlencode({'fixture_locale': locale})], env=dict(env, DEWAWI_ICON_TEST_LAYOUTS=str(args.compare_layouts.resolve())), text=True)
                    check(re.findall(r'<button\b.*?</button>', previous, re.S) == re.findall(r'<button\b.*?</button>', html, re.S), f'{locale}/{path}: button markup/attributes/labels changed')
                (args.output / f'{locale}-page-{index}.html').write_text(html)
                for width in [320, 390, 768, 1280]:
                    name = f'{locale}-{index}-{width}'
                    driver.emulate_viewport(width, 1100)
                    driver.navigate(url)
                    driver.wait('return !!window.DewawiToolbar')
                    metrics = driver.execute(r'''
                        var icons=[...document.querySelectorAll('.dw-btn--control svg')];return {buttons:document.querySelectorAll('button').length,svgButtons:document.querySelectorAll('button svg').length,symbols:document.querySelectorAll('symbol').length,blocks:document.querySelectorAll('.dw-icon-symbols').length,
                        icons:icons.length,empty:icons.filter(i=>i.querySelector('use').getBBox().width===0).length,
                        invalid:icons.filter(i=>{var r=i.getBoundingClientRect(),s=getComputedStyle(i),b=i.closest('button'),c=getComputedStyle(b);return !r.width||!r.height||s.display==='none'||s.visibility==='hidden'||s.stroke!==c.color||s.fill!=='none'||c.backgroundImage!=='none'}).length,
                        legacy:[...document.querySelectorAll('button:not(.dw-btn--control)')].filter(b=>!b.querySelector('svg')).map(b=>({action:b.dataset.action||b.name||'other',background:getComputedStyle(b).backgroundImage}))};
                    ''')
                    painted = paint(driver, '.dw-btn--control svg', name) if metrics['icons'] else None
                    results.append({'locale': locale, 'path': path, 'width': width, 'html': inventory, 'browser': metrics, 'painted_pixels': painted})
                    check(inventory['symbols'] == 14 and not inventory['unresolved'], f'{name}: missing HTML definitions: {inventory}')
                    check(metrics['symbols'] == 14 and metrics['blocks'] == 1 and not metrics['empty'] and not metrics['invalid'], f'{name}: unresolved/invisible browser SVG: {metrics}')
                    if metrics['icons']:
                        check(painted >= 3, f'{name}: no actual currentColor icon pixels: {painted}')
                    if path == '/':
                        check(inventory['modern_buttons'] == 0 and inventory['svg_buttons'] == 1, f'{name}: dashboard migrated')
                        check(any(v['action'] == 'pdf' and '/images/pdf.png' in v['background'] for v in metrics['legacy']), f'{name}: dashboard PDF PNG missing')
                    if path == '/sales/quote':
                        check(inventory['modern_buttons'] == 0 and inventory['references'] == 0, f'{name}: document actions migrated')
                        check(any(v['action'] == 'pdf' and '/images/pdf.png' in v['background'] for v in metrics['legacy']), f'{name}: document PDF PNG missing')
                    if path in ['/contacts/contact', '/items/item']:
                        for action in ['delete', 'apply', 'export']:
                            check(driver.execute('var b=document.querySelector("button[name=' + action + ']");return !!b&&!b.querySelector("svg")&&!b.classList.contains("dw-btn--control")'), f'{name}: excluded {action} migrated')
                    urls = sorted({re.search(r'url\(["\']?(.*?)["\']?\)', v['background']).group(1) for v in metrics['legacy'] if v['background'].startswith('url(')})
                    driver.execute('window.fixtureImages=' + json.dumps(urls) + '.map(u=>{var i=new Image();i.src=u;return i});return true')
                    driver.wait('return fixtureImages.every(i=>i.complete)')
                    check(driver.execute('return fixtureImages.every(i=>i.naturalWidth>0)'), f'{name}: legacy PNG asset unavailable')
                    if metrics['icons'] and 'fixture_contextless' not in path:
                        check(driver.execute('var b=document.querySelector(".dw-btn--control"),i=b.querySelector("svg"),html=b.innerHTML;b.disabled=true;i.querySelector("use").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}));setButtonLoading($(b),true,"Working");var busy=b.getAttribute("aria-busy")==="true";setButtonLoading($(b),false);var ok=b.disabled&&b.querySelector("svg")===i&&b.innerHTML===html;b.disabled=false;b.focus();return busy&&ok'), f'{name}: loading/disabled restore')
                        driver.keys('tab')
                        driver.shift_tab()
                        check(driver.execute('var b=document.querySelector(".dw-btn--control");return document.activeElement===b&&b.matches(":focus-visible")&&getComputedStyle(b).outlineWidth==="2px"'), f'{name}: native keyboard focus')
                    if path in ['/contacts/contact', '/items/item', '/contacts/contact/select', '/items/item/select', '/erp/contacts/contact', '/erp/items/item/select']:
                        driver.execute('window.originalSymbols=document.querySelector(".dw-icon-symbols");$("input[name=sort][value=ASC]").prop("checked",true).trigger("change");return true')
                        driver.wait('return !!document.querySelector("button[data-action=clear-filter]")')
                        check(driver.execute('return document.querySelector("button[data-action=clear-filter]").getAttribute("aria-label").startsWith(' + json.dumps('Filter entfernen: ' if locale == 'de_DE' else 'Remove filter: ') + ')'), f'{name}: actual AJAX changed chip language')
                        check(paint(driver, 'button[data-action=clear-filter] svg', name + '-chip') >= 3, f'{name}: dynamic chip did not paint')
                        driver.click('button[data-action=clear-filter]')
                        driver.wait('return !document.querySelector("button[data-action=clear-filter]")')
                        check(driver.execute('return originalSymbols.isConnected&&document.querySelectorAll(".dw-icon-symbols").length===1&&[...document.querySelectorAll(".dw-btn--control use")].every(u=>u.getBBox().width>0)'), f'{name}: actual HTTP AJAX lost symbols')
                        check(paint(driver, '.dw-row-actions .dw-btn--control svg', name + '-ajax') >= 3, f'{name}: actual AJAX row icon did not paint')
                    if path in ['/contacts/contact', '/items/item'] and width in [390, 1280]:
                        driver.execute(r'''
                            window.fixtureNavigations=[];window.originalNavigation=setLocation;window.setLocation=function(url){fixtureNavigations.push(url)};
                            $('input[data-select-id]').prop('checked',false).last().prop('checked',true);
                            window.rowEdit=document.querySelector('.dw-row-actions button[data-action=edit]');rowEdit.focus();
                        ''')
                        driver.keys('enter')
                        check(driver.execute('return fixtureNavigations.length===1&&fixtureNavigations[0]===baseUrl+"/"+rowEdit.dataset.module+"/"+rowEdit.dataset.controller+"/edit/id/"+rowEdit.dataset.id'), f'{name}: actual row edit handler targeted another selection')
                        driver.execute(r'''
                            window.copyButton=document.querySelector('.dw-row-actions button[data-action=copy]');window.copyIcon=copyButton.querySelector('svg');window.copyCalls=[];window.originalAjax=$.ajax;
                            $.ajax=function(o){
                                if(o.url!==baseUrl+'/'+module+'/'+controller+'/copy/id/'+copyButton.dataset.id)return originalAjax.apply($,arguments);
                                copyCalls.push({url:o.url,type:o.type});var d=$.Deferred();window.pendingCopy=function(ok){pendingCopy=null;if(ok)d.resolve({ok:true,saved:true,id:9001});else d.reject({status:403,responseJSON:{ok:false,saved:false}})};
                                return d.promise({abort:function(){}});
                            };
                            copyButton.focus();
                        ''')
                        driver.keys('enter')
                        driver.execute('copyIcon.querySelector("use").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}));$("input[data-select-id]").prop("checked",false).filter(function(){return this.dataset.selectId===copyButton.dataset.id}).prop("checked",true);document.querySelector(".dw-toolbar--bottom button[name=copy]").click();return true')
                        check(driver.execute('return copyCalls.length===1&&copyCalls[0].type==="POST"&&copyButton.disabled&&copyButton.getAttribute("aria-busy")==="true"&&getComputedStyle(copyIcon).display==="none"'), f'{name}: copy execution/row-footer request lock/loading')
                        driver.execute('pendingCopy(false);return true')
                        check(driver.execute('return !copyButton.disabled&&copyButton.querySelector("svg")===copyIcon&&!!document.querySelector("input[data-select-id]:checked")'), f'{name}: copy error lost SVG/selection/retry state')
                        driver.execute('copyButton.focus();return true')
                        driver.keys('space')
                        check(driver.execute('return copyCalls.length===2&&copyButton.disabled'), f'{name}: native copy retry failed')
                        driver.execute('window.previousBody=document.querySelector("tbody");pendingCopy(true);return true')
                        driver.wait('return previousBody!==document.querySelector("tbody")')
                        check(driver.execute('return originalSymbols.isConnected&&document.querySelectorAll(".dw-icon-symbols").length===1&&!document.querySelector("input[data-select-id]:checked")'), f'{name}: copy success lost actual AJAX refresh/symbols/selection reset')
                        check(paint(driver, '.dw-row-actions button[data-action=copy] svg', name + '-copy') >= 3, f'{name}: copy result SVG did not paint')
                        driver.execute('$.ajax=originalAjax;window.setLocation=originalNavigation;return true')
                    if path == '/contacts/contact' and width == 390:
                        driver.execute('$("input[data-select-id]").first().prop("checked",true);return true')
                        for action_name, dialog_id in [('tags', 'contactTags'), ('export', 'contactExport')]:
                            driver.execute('window.dialogOpener=document.querySelector("button[name=' + action_name + ']");return true')
                            driver.click('button[name=' + action_name + ']')
                            driver.wait('return document.getElementById(' + json.dumps(dialog_id) + ').getAttribute("aria-hidden")==="false"')
                            check(driver.execute('return modalWindow.current[0].id===' + json.dumps(dialog_id) + '&&modalWindow.current.attr("role")==="dialog"&&document.querySelectorAll(".dw-icon-symbols").length===1'), f'{name}: original {action_name} dialog handler')
                            driver.keys('escape')
                            check(driver.execute('return document.getElementById(' + json.dumps(dialog_id) + ').getAttribute("aria-hidden")==="true"&&document.activeElement===dialogOpener'), f'{name}: dialog close/opener focus')
                    print(f'PASS: {locale} {path} {width}px original MVC/HTTP, symbols, paint and legacy', flush=True)
            for width in [390, 1280]:
                driver.emulate_viewport(width, 1100)
                driver.navigate(origin + '/contacts/contact?' + urlencode({'fixture_locale': locale}))
                driver.execute('var frame=document.createElement("iframe");frame.id="icon-select-frame";frame.src="/items/item/select?parent=processes%7Cprocess%7C501&setid=7";frame.style.width="100%";frame.style.height="600px";document.body.prepend(frame);return true')
                driver.command('POST', '/frame', {'id': {'element-6066-11e4-a52e-4f735466cecf': driver.element('#icon-select-frame')}})
                driver.wait('return !!window.DewawiToolbar&&!!document.querySelector("button[name=apply]")')
                check(driver.execute('return document.querySelectorAll("symbol").length===14&&document.querySelectorAll(".dw-icon-symbols").length===1&&[...document.querySelectorAll(".dw-btn--control use")].every(u=>document.getElementById(u.getAttribute("href").slice(1))&&u.getBBox().width>0)'), f'{locale}/{width}: iframe relies on parent symbols')
                check(paint(driver, 'button[name=apply] svg', f'{locale}-iframe-{width}') >= 3, f'{locale}/{width}: iframe SVG not painted')
                driver.command('POST', '/frame', {'id': None})
        print(f'PASS: {checks} complete ERP page assertions', flush=True)
    finally:
        (args.output / 'page-results.json').write_text(json.dumps(results, indent=2))
        if driver:
            driver.close()
        server.terminate()
        server.wait(timeout=5)
        log.close()
