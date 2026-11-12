"""Render the real export view and exercise its optional checkbox in Chromium."""
from pathlib import Path
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
class GobdViewFixture extends Zend_View {
    public array $texts = [];
    public function translate($key) { return $this->texts[$key] ?? $key; }
    public function headTitle($title = null) { return ''; }
    public function Messages() { return ''; }
    public function Pagination() { return ''; }
    public function url($options = [], $name = null, $reset = false, $encode = true) { return '/admin/export/' . ($options['action'] ?? 'index'); }
}
$view = new GobdViewFixture();
$view->texts = require $root . '/languages/' . $locale . '/admin/admin.php';
$view->exports = new class { public function render() { return '<table><tr><td>Fixture export list</td></tr></table>'; } };
$view->setScriptPath($root . '/application/modules/admin/views/scripts');
echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="file://' . $root . '/css/ui.css"></head><body>';
echo $view->render('export/index.phtml');
'''
script = r'''
<pre id="result"></pre><script>
try {
  const check = (ok, text) => { if (!ok) throw new Error(text); };
  check(innerWidth === Number(new URLSearchParams(location.search).get('width')), 'Unexpected viewport width: ' + innerWidth);
  const type = document.querySelector('input[name="type"][value="gobd"]');
  const form = type.form, box = form.querySelector('input[name="include_documents"]');
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
        for locale in ['de_DE', 'en_US']:
            rendered = subprocess.run(['php', str(renderer), str(root), locale], capture_output=True, text=True, check=True)
            page = directory / (locale + '.html')
            page.write_text(rendered.stdout + script)
            for width in [375, 1440]:
                browser.emulate_viewport(width, 1000)
                browser.navigate(page.as_uri() + '?width=' + str(width))
                result = browser.execute("return document.getElementById('result').textContent")
                if not result.startswith('PASS:'):
                    raise RuntimeError(locale + '/' + str(width) + ': ' + result + '\n' + rendered.stderr)
                print(locale + '/' + str(width) + ': ' + result)
    finally:
        browser.close()
