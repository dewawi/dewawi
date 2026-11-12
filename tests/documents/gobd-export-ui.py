"""Exercise the real export view, flash rendering and filter persistence in Chromium."""
from pathlib import Path
import json
import subprocess
import importlib.util
import sys
import tempfile

root = Path(__file__).resolve().parents[2]
php = r'''<?php
$root = $argv[1];
$locale = $argv[2];
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function($class) { if ($file = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $file; });
require $root . '/application/views/helpers/Messages.php';
class GobdViewFixture extends Zend_View {
    public array $texts = [];
    public function translate($key) { return $this->texts[$key] ?? $key; }
    public function headTitle($title = null) { return ''; }
    public function Messages() { (new Zend_View_Helper_Messages())->setView($this)->Messages(); }
    public function Pagination() { return ''; }
    public function baseUrl() { return $this->fixtureBase; }
    public function url($options = [], $name = null, $reset = false, $encode = true) { return $this->baseUrl() . '/admin/export/' . ($options['action'] ?? 'index') . '?' . http_build_query(array_intersect_key($options, array_flip(['type', 'target']))); }
}
$view = new GobdViewFixture();
foreach (array_merge(glob($root . '/languages/' . $locale . '/default/*.php'), glob($root . '/languages/' . $locale . '/admin/*.php')) as $file) $view->texts = array_merge($view->texts, require $file);
$view->user = ['clientid' => (int)($argv[4] ?? 7), 'id' => (int)($argv[5] ?? 42)];
$view->fixtureBase = $argv[6] ?? '/fixture';
$view->messages = match ($argv[3] ?? '') {
    'success' => [['type' => 'success', 'message' => 'ADMIN_EXPORT_CREATED']],
    'invalid' => ['ADMIN_EXPORT_INVALID_PERIOD'],
    'failure' => ['ADMIN_EXPORT_FAILED'],
    default => [],
};
$view->exports = new class { public function render() { return '<table><tr><td>Fixture export list</td></tr></table>'; } };
$view->setScriptPath($root . '/application/modules/admin/views/scripts');
echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="file://' . $root . '/css/ui.css"></head><body>';
echo '<div id="content">';
echo $view->render('export/index.phtml');
echo '</div><script src="file://' . $root . '/library/jQuery/jquery-1.12.4.min.js"></script><script src="file://' . $root . '/library/jQuery/jquery.cookie.js"></script>';
echo '<script>var language = "en", module = "admin", controller = "export", action = "index", baseUrl = "/fixture", id = 0, cookiePath = "/fixture"; var fixtureErrors = []; window.addEventListener("error", function(e) { fixtureErrors.push(e.message); }); $.fn.datepicker = function() { return this; }; function autosize() {}</script>';
echo '<script src="file://' . $root . '/js/global.js"></script>';
'''
script = r'''
<pre id="result"></pre><script>
try {
  const check = (ok, text) => { if (!ok) throw new Error(text); };
  check(innerWidth === Number(new URLSearchParams(location.search).get('width')), 'Unexpected viewport width: ' + innerWidth);
  const type = document.querySelector('input[name="type"][value="gobd"]');
  const form = type.form, box = form.querySelector('input[name="include_documents"]');
  const review = form.querySelector('button[name="review_export"]');
  check(review && review.value === '1' && !review.textContent.includes('ADMIN_'), 'Separate review export button or translation is missing');
  check(box && !box.checked && box.value === '1', 'Checkbox must default to off');
  check(!new FormData(form).has('include_documents'), 'Default form sends PDF option');
  check(document.querySelectorAll('input[name="include_documents"]').length === 1, 'Other exports received the PDF option');
  check(!box.parentElement.textContent.includes('ADMIN_'), 'Checkbox translation is missing');
  check(form.querySelector('input[name="from"]').required && form.querySelector('input[name="to"]').required, 'Date fields lost validation');
  form.elements.from.value = '2019-01-01'; form.elements.to.value = '2021-12-31'; box.click();
  let data = new URLSearchParams(new FormData(form));
  check(data.get('include_documents') === '1' && data.get('from') === '2019-01-01' && data.get('to') === '2021-12-31', 'Historical form submission is incorrect');
  form.elements.from.value = '2033-03-02'; form.elements.to.value = '2033-05-19';
  data = new URLSearchParams(new FormData(form));
  check(data.get('from') === '2033-03-02' && data.get('to') === '2033-05-19' && form.checkValidity(), 'Dates are restricted to a fixed audit period');
  const reviewData = new URLSearchParams(new FormData(form, review));
  check(reviewData.get('review_export') === '1' && reviewData.get('from') === '2033-03-02' && reviewData.get('to') === '2033-05-19', 'Review export lost its separate mode or shared dates');
  check(!data.has('review_export'), 'Normal GoBD export acquired review mode');
  check(document.documentElement.scrollWidth <= innerWidth + 1, 'Export view overflows the viewport');
  document.getElementById('result').textContent = 'PASS: real GoBD view; default off, translated checkbox, free dates and responsive form';
} catch (error) { document.getElementById('result').textContent = 'FAIL: ' + error.message; }
</script></body></html>
'''

sys.dont_write_bytecode = True
spec = importlib.util.spec_from_file_location('dewawi_browser_fixture', root / 'tests/site/accessibility-http.py')
browser_module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(browser_module)

with tempfile.TemporaryDirectory(prefix='dewawi-gobd-ui-') as directory:
    directory = Path(directory)
    renderer = directory / 'render.php'
    renderer.write_text(php)
    browser = browser_module.WebDriver()
    try:
        def render(locale, state='', client=7, user=42, base='/fixture'):
            rendered = subprocess.run(['php', str(renderer), str(root), locale, state, str(client), str(user), base], capture_output=True, text=True, check=True)
            if rendered.stderr:
                raise RuntimeError(rendered.stderr)
            page = directory / (locale + '-' + state + '-' + str(client) + '-' + str(user) + '-' + base.replace('/', '_') + '.html')
            page.write_text(rendered.stdout + '</body></html>')
            return page.as_uri()

        def check_js(script):
            browser.execute("const check = (ok, message) => { if (!ok) throw new Error(message); }; const g = document.querySelector('input[value=gobd]').form, l = document.querySelector('input[value=list]').form; " + script)
            errors = browser.execute('return fixtureErrors')
            if errors:
                raise RuntimeError(str(errors))

        for locale in ['de_DE', 'en_US']:
            url = render(locale)
            for width in [375, 1440]:
                browser.emulate_viewport(width, 1000)
                browser.navigate(url)
                browser.execute('sessionStorage.clear()')
                # Retain the original checkbox, free-period and responsive assertions.
                page = Path(url.removeprefix('file://'))
                original = page.read_text()
                page.write_text(original + script)
                browser.navigate(url + '?width=' + str(width))
                result = browser.execute("return document.getElementById('result').textContent")
                if not result.startswith('PASS:'):
                    raise RuntimeError(locale + '/' + str(width) + ': ' + result)
                print(locale + '/' + str(width) + ': ' + result)
                page.write_text(original)
                browser.execute('sessionStorage.clear()')
                browser.navigate(url)
                check_js("""
                    check(!document.body.textContent.includes('ADMIN_'), 'Export text is untranslated');
                    check(g.method === 'get' && l.method === 'get', 'Native GET export changed');
                    check([...document.querySelectorAll('.dw-export-list a')].map(a => new URL(a.href).searchParams.get('type')).join() === 'csv,sql', 'CSV/SQL routes changed');
                    check(g.querySelector('[type=reset]') && l.querySelector('[type=reset]'), 'Reset action missing');
                    g.elements.from.value = '2019-01-01'; g.elements.to.value = '2021-12-31'; g.elements.include_documents.checked = true;
                    // Submit captures values even without preceding change events.
                    g.addEventListener('submit', e => e.preventDefault()); g.requestSubmit();
                    l.elements.target.value = 'stocks'; $(l.elements.target).trigger('change');
                    check(Object.keys(JSON.parse(sessionStorage.getItem(g.dataset.exportFilterKey))).sort().join() === 'from,include_documents,to', 'Unexpected values persisted');
                """)
                for state in ['success', 'invalid', 'failure', '']:
                    browser.navigate(render(locale, state))
                    check_js("""
                        check(g.elements.from.value === '2019-01-01' && g.elements.to.value === '2021-12-31' && g.elements.include_documents.checked, 'GoBD filters lost after navigation');
                        check(l.elements.target.value === 'stocks', 'List selection lost');
                        check(!document.body.textContent.includes('ADMIN_'), 'Flash translation missing');
                    """)
                    if state:
                        alert_type = 'success' if state == 'success' else 'danger'
                        check_js("check(document.querySelectorAll('.dw-alert--" + alert_type + "').length === 1 && document.querySelectorAll('.dw-alert').length === 1, 'Wrong flash design');")
                        if state == 'success':
                            expected = 'Export erfolgreich erstellt.' if locale == 'de_DE' else 'Export created successfully.'
                            check_js("check(document.querySelector('.dw-alert li').textContent === " + json.dumps(expected) + ", 'Success message has wrong language');")
                # A different user, tenant or installation must start with defaults.
                for client, user, base in [(8, 42, '/fixture'), (7, 43, '/fixture'), (7, 42, '/other')]:
                    browser.navigate(render(locale, client=client, user=user, base=base))
                    check_js("check(!g.elements.include_documents.checked && g.elements.from.value === g.elements.from.defaultValue && l.elements.target.value === 'contacts', 'Filter state crossed scope');")
                browser.navigate(url)
                check_js("check(g.elements.include_documents.checked && l.elements.target.value === 'stocks', 'Original scope lost');")
                # A new tab has its own sessionStorage, even for the same operator.
                window = browser.command('GET', '/window')
                new_tab = browser.command('POST', '/window/new', {'type': 'tab'})['handle']
                browser.command('POST', '/window', {'handle': new_tab})
                browser.navigate(url)
                check_js("check(!g.elements.include_documents.checked && l.elements.target.value === 'contacts', 'Filters crossed tabs');")
                browser.command('DELETE', '/window')
                browser.command('POST', '/window', {'handle': window})
                check_js("""
                    // Replacing the list content must restore filters as well.
                    const html = $('#content').html(); $('#content').html(html); initExportFilters('#content');
                    const restored = document.querySelector('input[value=gobd]').form;
                    check(restored.elements.from.value === '2019-01-01' && restored.elements.include_documents.checked, 'AJAX refresh lost filters');
                    restored.querySelector('[type=reset]').click();
                    check(restored.elements.from.value === restored.elements.from.defaultValue && restored.elements.to.value === restored.elements.to.defaultValue && !restored.elements.include_documents.checked, 'GoBD reset failed');
                    check(sessionStorage.getItem(restored.dataset.exportFilterKey) === null, 'Reset did not clear storage');
                """)
                browser.navigate(url)
                check_js("""
                    check(!g.elements.include_documents.checked && l.elements.target.value === 'stocks', 'Reset affected wrong form');
                    l.querySelector('[type=reset]').click();
                    check(l.elements.target.value === 'contacts' && sessionStorage.getItem(l.dataset.exportFilterKey) === null, 'List reset failed');
                    g.elements.from.value = ''; $(g.elements.from).trigger('input');
                """)
                browser.navigate(url)
                check_js("""
                    check(g.elements.from.value === '' && !g.checkValidity(), 'Empty invalid date was discarded');
                    sessionStorage.setItem(g.dataset.exportFilterKey, '{broken JSON');
                    sessionStorage.setItem(l.dataset.exportFilterKey, JSON.stringify({target: '<script>', clientid: 8}));
                """)
                browser.navigate(url)
                check_js("""
                    check(g.elements.from.value === g.elements.from.defaultValue && l.elements.target.value === 'contacts', 'Corrupt storage broke defaults');
                    const get = Storage.prototype.getItem, set = Storage.prototype.setItem, remove = Storage.prototype.removeItem;
                    Storage.prototype.getItem = Storage.prototype.setItem = Storage.prototype.removeItem = function() { throw new Error('Storage denied'); };
                    $(g).removeData('dw-export-filters'); initExportFilters(document);
                    $(g.elements.from).trigger('input'); g.reset();
                    check(g.checkValidity() && !g.querySelector('[type=submit]').disabled, 'Unavailable storage blocked export');
                    Storage.prototype.getItem = get; Storage.prototype.setItem = set; Storage.prototype.removeItem = remove;
                """)
                print(locale + '/' + str(width) + ': PASS: flash, submit/revisit, reset, scope/tab isolation, AJAX refresh and unavailable/corrupt storage')
    finally:
        browser.close()
