"""Full-document pixel and behavior probes, invoked by button-release-audit.py --document-icons."""
import base64
import io
import json
import math
import os
import re
import subprocess
import tempfile
import time
import urllib.request

from PIL import Image


def run(root, browser, factory, engine, output):
    checks, measurements, limitations = 0, [], set()

    def check(value, message):
        nonlocal checks
        checks += 1
        if not value:
            raise AssertionError(message)

    def wait(script):
        deadline = time.monotonic() + 8
        while not driver.execute(script):
            if time.monotonic() > deadline:
                raise AssertionError('Browser condition timed out: ' + script)
            time.sleep(.05)

    def screenshot():
        return driver.screenshot() if engine == 'firefox' else base64.b64decode(driver.command('GET', '/screenshot'))

    def paint(selector, name):
        rect = driver.execute('var s=document.querySelector(' + json.dumps(selector) + ');s.scrollIntoView({block:"center",inline:"center"});var r=s.getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height,color:getComputedStyle(s).stroke,viewport:innerWidth}')
        png = screenshot()
        picture = Image.open(io.BytesIO(png)).convert('RGB')
        scale = picture.width / rect['viewport']
        cropped = picture.crop((math.ceil(rect['x'] * scale), math.ceil(rect['y'] * scale), math.floor((rect['x'] + rect['w']) * scale), math.floor((rect['y'] + rect['h']) * scale)))
        cropped.save(output / (name + '.png'))
        color = [int(v) for v in re.findall(r'\d+', rect['color'])[:3]]
        check(len(color) == 3, name + ': no computed stroke color')
        count = sum(max(abs(c - target) for c, target in zip(pixel, color)) < 40 for pixel in cropped.getdata())
        return count, [rect['w'], rect['h']]

    def click(selector):
        if engine == 'chromium':
            driver.click(selector)
            return
        point = driver.execute('var b=document.querySelector(' + json.dumps(selector) + ');b.scrollIntoView({block:"center",inline:"center"});var r=b.getBoundingClientRect();return {x:Math.floor(r.x+r.width/2),y:Math.floor(r.y+r.height/2)}')
        driver.call('input.performActions', {'context': driver.context, 'actions': [{'type': 'pointer', 'id': 'svg-pointer', 'parameters': {'pointerType': 'mouse'}, 'actions': [{'type': 'pointerMove', **point}, {'type': 'pointerDown', 'button': 0}, {'type': 'pointerUp', 'button': 0}]}]})

    profiles = [
        ('/contacts/contact', 'contacts', ['.dw-row-actions button[data-action=view] svg', '.dw-row-actions button[data-action=edit] svg', '.dw-toolbar button[name=add] svg']),
        ('/', 'dashboard', ['.dw-table--dashboard-contacts button[data-action=edit] svg']),
        ('/items/item/select', 'popup', ['button[name=apply] svg']),
    ]
    with tempfile.TemporaryDirectory(prefix='dewawi-svg-document-') as sessions:
        port = browser.free_port()
        log = (output / 'document-http.log').open('w')
        server = subprocess.Popen(['php', '-d', 'opcache.enable_cli=0', '-S', f'127.0.0.1:{port}', str(root / 'tests/ui/button-page-integration.php')], cwd=root, env=dict(os.environ, DEWAWI_ICON_TEST_SESSIONS=sessions), stdout=log, stderr=log)
        driver = None
        origin = f'http://127.0.0.1:{port}'
        try:
            for _ in range(100):
                try:
                    with urllib.request.urlopen(origin + '/css/ui.css', timeout=1):
                        break
                except OSError:
                    time.sleep(.05)
            else:
                raise RuntimeError('Read-only fixture server did not start')
            driver = factory()
            for locale in ['de_DE', 'en_US']:
                for path, page, selectors in profiles:
                    url = origin + path + '?fixture_locale=' + locale
                    with urllib.request.urlopen(url) as response:
                        html = response.read().decode()
                        check(response.status == 200 and response.headers.get('Content-Security-Policy') == "frame-ancestors 'self'", 'HTTP/CSP changed')
                    (output / (locale + '-' + page + '.html')).write_text(html)
                    shell = 'class="dw-popup-shell ' if page == 'popup' else 'class="dw-app-shell"'
                    check('<html ' in html and '<body' in html and html.count('<symbol ') == 14 and html.index('<symbol ') < html.index(shell), page + ': missing full-document definitions before shell')
                    for width in [320, 390, 768, 1280]:
                        name = f'{locale}-{page}-{width}'
                        driver.emulate_viewport(width, 1100)
                        driver.navigate(url)
                        wait('return !!window.DewawiToolbar&&!!window.jQuery')
                        driver.execute('window.svgErrors=[];addEventListener("error",function(e){svgErrors.push(e.message)},true);$.ajaxSetup({headers:{"X-Icon-Fixture-Locale":' + json.dumps(locale) + '}});return true')
                        dom = driver.execute(r'''
                            var bank=document.querySelector('.dw-icon-symbols'),uses=[...document.querySelectorAll('use')].filter(u=>(u.getAttribute('href')||'').startsWith('#dw-icon-'));
                            return {symbols:document.querySelectorAll('symbol[id^="dw-icon-"]').length,banks:document.querySelectorAll('.dw-icon-symbols').length,body:bank?.parentNode===document.body,
                            insideInner:document.querySelectorAll('.dw-app-shell__inner symbol,.dw-popup-shell symbol').length,unresolved:uses.filter(u=>!document.getElementById(u.getAttribute('href').slice(1))).length,
                            emptyDefinitions:[...document.querySelectorAll('symbol[id^="dw-icon-"]')].filter(s=>!s.querySelector('path,circle,rect,line,polygon,polyline')).length,focused:document.hasFocus()};
                        ''')
                        check(dom['symbols'] == 14 and dom['banks'] == 1 and dom['body'] and dom['insideInner'] == 0 and dom['unresolved'] == 0 and dom['emptyDefinitions'] == 0, name + ': full DOM symbol contract')
                        record = {'page': page, 'locale': locale, 'viewport': width, 'dom': dom, 'icons': []}
                        measurements.append(record)
                        for index, selector in enumerate(selectors):
                            positive, size = paint(selector, name + '-' + str(index) + '-present')
                            check(positive >= 3 and size == ([20, 20] if width < 768 else [16, 16]), name + ': icon not painted at expected size')
                            driver.execute('window.svgBank=document.querySelector(".dw-icon-symbols");window.savedButtons=[...document.querySelectorAll("button")].map(b=>b.outerHTML);svgBank.remove();return true')
                            blank, missing_size = paint(selector, name + '-' + str(index) + '-missing')
                            check(blank == 0 and missing_size == size and driver.execute('return document.querySelectorAll("symbol").length===0&&savedButtons.every((s,i)=>document.querySelectorAll("button")[i].outerHTML===s)'), name + ': missing bank was not a true blank-icon reproduction')
                            driver.execute('document.body.insertBefore(svgBank,document.body.firstChild);return true')
                            restored, restored_size = paint(selector, name + '-' + str(index) + '-restored')
                            check(restored >= 3 and restored_size == size and driver.execute('return savedButtons.every((s,i)=>document.querySelectorAll("button")[i].outerHTML===s)'), name + ': bank restoration did not restore pixels without changing buttons')
                            record['icons'].append({'selector': selector, 'size': size, 'present': positive, 'missing': blank, 'restored': restored})
                        if page in ['contacts', 'dashboard']:
                            selector = '.dw-row-actions button[data-action=edit]'
                            driver.execute('window.iconNav=[];window.setLocation=function(u){iconNav.push(u)};window.editButton=document.querySelector(' + json.dumps(selector) + ');window.editIcon=editButton.querySelector("svg");window.beforeEdit=editButton.outerHTML;window.expectedEdit=baseUrl+"/"+editButton.dataset.module+"/"+editButton.dataset.controller+"/edit/id/"+editButton.dataset.id;return true')
                            click(selector + ' svg')
                            check(driver.execute('return iconNav.length===1&&iconNav[0]===expectedEdit'), name + ': native SVG-child click routing')
                            native_keys = engine == 'chromium' or driver.execute('return document.hasFocus()')
                            if native_keys:
                                driver.execute('editButton.focus();return true')
                                driver.keys('tab')
                                driver.shift_tab()
                                check(driver.execute('return document.activeElement===editButton&&editButton.matches(":focus-visible")&&getComputedStyle(editButton).outlineWidth==="2px"'), name + ': native visible keyboard focus')
                                driver.keys('enter')
                                driver.keys('space')
                                check(driver.execute('return iconNav.length===3&&iconNav.every(u=>u===expectedEdit)'), name + ': native Enter/Space')
                            else:
                                limitations.add('Firefox headless document.hasFocus=false: native keyboard/focus NOT TESTED; native pointer and actual pixels tested')
                            driver.execute('window.navCount=iconNav.length;editButton.disabled=true;editIcon.querySelector("use").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}));editButton.disabled=false;setButtonLoading($(editButton),true,"Working");editIcon.querySelector("use").dispatchEvent(new MouseEvent("click",{bubbles:true,cancelable:true}));return true')
                            check(driver.execute('return iconNav.length===navCount&&editButton.disabled&&editButton.getAttribute("aria-busy")==="true"'), name + ': disabled/loading protection')
                            driver.execute('setButtonLoading($(editButton),false);editButton.click();return true')
                            check(driver.execute('return iconNav.length===navCount+1&&iconNav[iconNav.length-1]===expectedEdit&&editButton.outerHTML===beforeEdit&&editButton.querySelector("svg")===editIcon'), name + ': exact loading restore and continued action')
                        if page == 'contacts':
                            driver.execute('window.originalBank=document.querySelector(".dw-icon-symbols");$("input[name=sort][value=ASC]").prop("checked",true).trigger("change");return true')
                            wait('return !!document.querySelector("button[data-action=clear-filter]")')
                            pixels, _ = paint('button[data-action=clear-filter] svg', name + '-ajax-chip')
                            check(pixels >= 3, name + ': real AJAX chip did not paint')
                            click('button[data-action=clear-filter]')
                            wait('return !document.querySelector("button[data-action=clear-filter]")')
                            pixels, _ = paint('.dw-row-actions button[data-action=view] svg', name + '-ajax-row')
                            check(pixels >= 3 and driver.execute('return originalBank.isConnected&&document.querySelectorAll(".dw-icon-symbols").length===1'), name + ': real AJAX row/symbol retention')
                            driver.execute('$("input[data-select-id]").first().prop("checked",true);return true')
                            click('button[name=tags]')
                            wait('return document.getElementById("contactTags").getAttribute("aria-hidden")==="false"')
                            check(driver.execute('return modalWindow.current[0].id==="contactTags"&&modalWindow.current.attr("role")==="dialog"'), name + ': original dialog handler')
                            if engine == 'chromium' or driver.execute('return document.hasFocus()'):
                                driver.keys('escape')
                            else:
                                click('#contactTags > .close')
                            wait('return document.getElementById("contactTags").getAttribute("aria-hidden")==="true"')
                        check(driver.execute('return svgErrors.length===0'), name + ': browser runtime error')
                        print('PASS:', engine, name, 'full document, actual pixels, missing-bank reproduction and handlers', flush=True)
            print(f'PASS: {checks} {engine} full-document SVG assertions; version {getattr(driver, "version", "see chromedriver")}', flush=True)
        finally:
            (output / 'document-results.json').write_text(json.dumps({'checks': checks, 'measurements': measurements, 'limitations': sorted(limitations)}, indent=2))
            if driver:
                driver.close()
            server.terminate()
            server.wait(timeout=5)
            log.close()
