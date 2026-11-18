"""Exercise actual PHP pilot controls and global.js with isolated AJAX and real keyboard input."""
from pathlib import Path
import base64
import importlib.util
import json
import subprocess
import sys
import tempfile

sys.dont_write_bytecode = True
root = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('button_browser_driver', root / 'tests/site/accessibility-http.py')
browser = importlib.util.module_from_spec(spec)
spec.loader.exec_module(browser)

def check(value, message):
    global checks
    checks += 1
    if not value:
        raise AssertionError(message)

script = r'''
var language = document.documentElement.lang, module = 'contacts', controller = 'inquiry', action = 'edit';
var id = 17, baseUrl = '/fixture', cookiePath = '/fixture', dwMessages = {}, deleteConfirm = 'Confirm?';
var calls = [], inlineClicks = 0, navigations = [], submits = [];
function autosize() {}
$.fn.datepicker = function () { return this; };
$.ajax = function (options) {
    calls.push(options);
    var deferred = $.Deferred();
    var response = options.url && options.url.indexOf('/search') !== -1 ? {ok: true, content: fixture.row, toolbar: ''} : {ok: true, saved: true};
    if (options.success) options.success(response);
    deferred.resolve(response);
    if (options.complete) options.complete();
    return deferred.promise({abort: function () {}});
};
'''
setup = r'''
window.setLocation = function (url) { navigations.push(url); };
$('#native').on('submit', function (event) {
    event.preventDefault();
    var submitter = event.originalEvent.submitter;
    submits.push({name: submitter.name, value: submitter.value});
});
$('#pilot-form').on('submit', function (event) { event.preventDefault(); submits.push({name: 'pilot'}); });
'''
checks = 0
driver = browser.WebDriver()
try:
    with tempfile.TemporaryDirectory(prefix='dewawi-buttons-') as directory:
        for locale in ['de_DE', 'en_US']:
            rendered = subprocess.run(['php', str(root / 'tests/ui/buttons.php'), 'render', locale], capture_output=True, text=True, check=True)
            data = json.loads(rendered.stdout)
            html = '<!doctype html><html lang="' + locale.replace('_', '-') + '"><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
            html += '<link rel="stylesheet" href="' + (root / 'css/ui.css').as_uri() + '">'
            html += '<style>section{margin:16px 0}#controls{display:flex;flex-wrap:wrap;gap:8px}#long-label{max-width:180px}</style>'
            html += '<h1>DEWAWI B2</h1><section id="toolbar">' + data['toolbar'] + '</section><section id="content">' + data['row'] + '</section>'
            html += '<section class="edit"><form id="pilot-form">' + data['form'] + '</form><form data-autosave="false"><input name="isolated" value="0"></form></section>'
            html += '<section id="controls">' + data['controls'] + '</section><section class="add"><form id="native"><input name="required" required value="valid">' + data['submit'] + data['reset'] + '</form></section>'
            html += '<div id="messages"></div><script src="' + (root / 'library/jQuery/jquery-1.12.4.min.js').as_uri() + '"></script>'
            html += '<script src="' + (root / 'library/jQuery/jquery.cookie.js').as_uri() + '"></script>'
            html += '<script>var fixture=' + json.dumps(data).replace('<', '\\u003c') + ';' + script + '</script>'
            html += '<script src="' + (root / 'js/global.js').as_uri() + '"></script><script>' + setup + '</script></html>'
            path = Path(directory) / (locale + '.html')
            path.write_text(html)
            for width in [390, 768, 1280]:
                driver.emulate_viewport(width, 1100)
                driver.navigate(path.as_uri())
                driver.wait("return !!window.DewawiToolbar")
                metrics = driver.execute(r'''
                    const controls=[...document.querySelectorAll('.dw-btn--control')];
                    const compact=document.querySelector('#content button');
                    const standard=document.querySelector('#toolbar button');
                    const label=document.getElementById('long-label');
                    const icon=standard.querySelector('.dw-btn__icon');
                    return {
                        width: innerWidth, overflow: document.documentElement.scrollWidth>innerWidth,
                        compact: compact.getBoundingClientRect().width,
                        standard: standard.getBoundingClientRect().height,
                        touch: document.getElementById('touch').getBoundingClientRect().height,
                        icons: controls.every(c=>[...c.querySelectorAll('.dw-btn__icon')].every(i=>getComputedStyle(i).backgroundImage!=='none'&&i.getAttribute('aria-hidden')==='true')),
                        names: controls.every(c=>(c.getAttribute('aria-label')||c.textContent).trim().length>0),
                        noBackground: controls.every(c=>getComputedStyle(c).backgroundImage==='none'),
                        noIconPadding: getComputedStyle(document.getElementById('no-icon')).paddingLeft,
                        legacyPadding: getComputedStyle(document.getElementById('legacy-text')).paddingLeft,
                        longFits: label.scrollWidth<=label.clientWidth,
                        type: document.querySelector('#pilot-form button').type,
                        iconSlot: getComputedStyle(icon).width
                    };
                ''')
                check(metrics['width'] == width and not metrics['overflow'], f'{locale}/{width}: overflow or false viewport: {metrics}')
                check(metrics['compact'] == (44 if width < 768 else 32), f'Compact/touch size mismatch: {metrics}')
                check(metrics['standard'] >= (44 if width < 768 else 36) and metrics['touch'] >= 44, 'Control hitbox too small')
                check(metrics['icons'] and metrics['names'] and metrics['noBackground'], 'Missing icon, name or duplicate background')
                check(metrics['noIconPadding'] == '14px' and metrics['legacyPadding'] == '34px', 'New text padding or legacy CSS changed')
                check(metrics['longFits'] and metrics['type'] == 'submit', 'Long label or native form type failed')
                check(metrics['iconSlot'] == ('20px' if width < 768 else '16px'), 'Icon box size mismatch')

                # Existing toolbar code, native Space/Enter and actual AJAX result replacement.
                driver.click('#toolbar button[name="filter"]')
                check(driver.execute("return $('.dw-filter-panel').hasClass('is-open')"), 'Filter toggle no longer works')
                driver.keys('tab')
                focus = driver.execute("var c=document.activeElement,s=getComputedStyle(c);return {name:c.name,width:s.outlineWidth,style:s.outlineStyle,visible:c.matches(':focus-visible')}")
                check(focus['name'] == 'reset' and focus['width'] == '2px' and focus['style'] == 'solid' and focus['visible'], f'Keyboard focus invisible: {focus}')
                driver.execute('calls=[];window.oldRow=document.querySelector("#content button")')
                driver.keys('space')
                driver.wait('return calls.length===1')
                check(driver.execute('return calls[0].url==="/fixture/contacts/inquiry/search" && oldRow!==document.querySelector("#content button")'), 'Space failed or search did not replace rows')
                driver.execute('calls=[]')
                driver.keys('enter')
                driver.wait('return calls.length===1')
                check(driver.execute('return calls[0].data.context_action==="index"'), 'Enter changed search context or submitted the toolbar')
                driver.execute('calls=[];$("#toolbar input[name=keyword]").val("example")')
                driver.click('#toolbar button[name="clear"] .dw-btn__icon')
                driver.wait('return calls.length===1')
                check(driver.execute('return $("#toolbar input[name=keyword]").val()===""'), 'Clear lost the field target')
                driver.click('#content button[data-action="edit"] .dw-btn__icon')
                check(driver.execute('return navigations.length===1 && navigations[0]==="/fixture/contacts/inquiry/edit/id/17"'), 'Dynamic child click resolved the wrong row target or ran twice')

                # The new DOM must not change existing status auto-save or its opt-out.
                driver.execute('calls=[];$("#pilot-form select[name=status]").val("in_progress").trigger("change")')
                check(driver.execute('return calls.length===1 && calls[0].data.status==="in_progress" && calls[0].url.indexOf("/contacts/inquiry/edit/id/17")!==-1'), 'Status auto-save request changed')
                driver.execute('calls=[];$("[name=isolated]").val("1").trigger("change")')
                check(driver.execute('return calls.length===0'), 'Explicit auto-save opt-out was ignored')

                # Keep DOM nodes, request locks and pre-existing disabled state through loading.
                check(driver.execute(r'''
                    var b=$('#content button[data-action=edit]'), icon=b.find('.dw-btn__icon')[0], original=b.html();
                    var release=beginActionRequest('b2-fixture','contacts','inquiry',['17'],b);
                    var blocked=beginActionRequest('b2-fixture','contacts','inquiry',['17'],$('#touch'));
                    var busy=b.prop('disabled')&&b.attr('aria-busy')==='true'&&getComputedStyle(icon).display==='none';
                    release();
                    setButtonLoading(b,true,'Working');setButtonLoading(b,false);
                    return busy&&!blocked&&b.html()===original&&b.find('.dw-btn__icon')[0]===icon&&!b.prop('disabled')&&!b.is('[aria-busy]');
                '''), 'Loading lost content, state or overlapping-request lock')
                check(driver.execute("setButtonLoading($('#disabled'),true,'Working');setButtonLoading($('#disabled'),false);return $('#disabled').prop('disabled')"), 'Loading re-enabled a pre-disabled control')
                driver.execute('inlineClicks=0;document.getElementById("disabled-link").click();document.getElementById("disabled-link").firstChild.click()')
                check(driver.execute('return inlineClicks===0&&location.hash!=="#blocked"'), 'Disabled link ran inline handler or navigated')
                driver.click('#link')
                check(driver.execute('return inlineClicks===1&&location.hash==="#navigation"'), 'Native enabled link was intercepted')
                check(driver.execute("var b=$('#link');setButtonLoading(b,true,'Working');b[0].click();var ok=inlineClicks===1;setButtonLoading(b,false);return ok&&b.attr('href')==='#navigation'&&!b.is('[aria-busy]')"), 'Loading link activated or lost its href/state')

                loading = driver.execute(r'''
                    var b=$('#pilot-form button'), node=b.find('.dw-btn__icon')[0], original=b.html();
                    setButtonLoading(b,true);
                    var busy=b.prop('disabled') && b.attr('aria-busy')==='true' && getComputedStyle(node).display==='none' && getComputedStyle(b[0],'::after').content.indexOf('…')!==-1;
                    setButtonLoading(b,false);
                    var restored=b.html()===original && b.find('.dw-btn__icon')[0]===node;
                    setButtonLoading(b,true,'Saving status'); setButtonLoading(b,false);
                    var textRestored=b.html()===original && b.find('.dw-btn__icon')[0]===node;
                    var compact=$('#content button').first(), compactHtml=compact.html(), name=compact.attr('aria-label');
                    setButtonLoading(compact,true,'Loading label');
                    var compactLoading=getComputedStyle(compact[0]).fontSize==='0px' && compact.attr('aria-label')===name;
                    setButtonLoading(compact,false);
                    return busy && restored && textRestored && compactLoading && compact.html()===compactHtml;
                ''')
                check(loading, 'Modern loading lost icon nodes, labels or fixed icon-only geometry')
                driver.click('#pilot-form button .dw-btn__icon')
                check(driver.execute('return submits.length===1 && submits[0].name==="pilot"'), 'Pilot save no longer submits its native status form')
                driver.execute('submits=[]')

                # Native submitter and explicit submit preparation remain independent of icons.
                driver.click('#submit .dw-btn__icon')
                check(driver.execute('return submits.length===1&&submits[0].name==="operation"&&submits[0].value==="confirm"'), 'Submitter name/value lost on child click')
                driver.execute('document.getElementById("submit").focus()')
                driver.keys('enter')
                check(driver.execute('return submits.length===2&&submits[1].value==="confirm"'), 'Native keyboard submit failed')
                check(driver.execute("var f=$('#native'),b=$('#submit'),done=prepareFormSubmit(f,b,b[0]);var ok=b.prop('disabled')&&f.serialize().indexOf('operation=confirm')!==-1;done();return ok&&!b.prop('disabled')&&f.find('[type=hidden][name=operation]').length===0"), 'Loading submit lost or duplicated submitter data')
                driver.execute('$("#native input[name=required]").val("")')
                driver.click('#submit')
                check(driver.execute('return submits.length===2'), 'Icon button bypassed native required-field validation')
                driver.execute('$("#native input[name=required]").val("changed")')
                driver.click('#native-reset')
                check(driver.execute('return $("#native input[name=required]").val()==="valid"'), 'Native reset turned into another action')
                names = driver.command('POST', '/goog/cdp/execute', {'cmd': 'Accessibility.getFullAXTree', 'params': {}})
                button_names = {n.get('name', {}).get('value') for n in names['nodes'] if n.get('role', {}).get('value') == 'button'}
                expected = {'Anzeigen', 'Bearbeiten', 'Leeren'} if locale == 'de_DE' else {'View', 'Edit', 'Clear'}
                check(expected <= button_names, f'Accessible tree lost localized names: {expected-button_names}')
                if locale == 'de_DE' and width in [390, 1280]:
                    driver.execute("$('.dw-filter-panel').removeClass('is-open');window.scrollTo(0,0)")
                    screenshot = driver.command('GET', '/screenshot')
                    Path('/tmp/dewawi-b2-buttons-' + str(width) + '.png').write_bytes(base64.b64decode(screenshot))
                print(f'PASS: {locale} {width}px button layout, keyboard, AJAX, auto-save, loading, links and submit')
        driver.command('POST', '/goog/cdp/execute', {'cmd': 'Emulation.setEmulatedMedia', 'params': {'features': [{'name': 'forced-colors', 'value': 'active'}]}})
        driver.execute('document.getElementById("variant-default").focus()')
        driver.keys('tab')
        check(driver.execute('return getComputedStyle(document.activeElement).outlineStyle==="solid"'), 'Forced-colors keyboard focus missing')
        print(f'PASS: {checks} browser assertions including forced colors')
finally:
    driver.close()
