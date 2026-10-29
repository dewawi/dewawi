"""Run isolated browser fixtures; AJAX is captured and no email or database is used."""
from html import escape
from html.parser import HTMLParser
from pathlib import Path
import json
import re
import subprocess
import tempfile


class ResultParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.in_result = False
        self.result = ''

    def handle_starttag(self, tag, attrs):
        if tag == 'pre' and dict(attrs).get('id') == 'result':
            self.in_result = True

    def handle_endtag(self, tag):
        if tag == 'pre':
            self.in_result = False

    def handle_data(self, data):
        if self.in_result:
            self.result += data


root = Path(__file__).resolve().parents[2]
catalogs = {}
for locale in ['de_DE', 'en_US']:
    result = subprocess.run(['php', '-r', 'echo json_encode(array_replace(require $argv[1], require $argv[2]));', str(root / 'languages' / locale / 'default/messages.php'), str(root / 'languages' / locale / 'default/document.php')], check=True, capture_output=True, text=True)
    catalogs[locale] = json.loads(result.stdout)
# Execute the actual rendered editor save callbacks with a captured editor API in the browser.
editor = subprocess.run(['php', '-r', """
set_include_path($argv[1] . '/library' . PATH_SEPARATOR . get_include_path());
require 'Zend/View.php'; require 'Zend/View/Helper/Abstract.php'; require 'Zend/Registry.php';
require $argv[1] . '/application/views/helpers/TinyMCE.php';
Zend_Registry::set('Zend_Locale', 'de_DE');
$view = new Zend_View();
(new Zend_View_Helper_TinyMCE())->setView($view)->TinyMCE();
echo $view->headScript();
""", str(root)], check=True, capture_output=True, text=True)
editor_script = '\n'.join(re.findall(r'<script[^>]*>(.*?)</script>', editor.stdout, re.S))
html = Path(__file__).with_suffix('.html').read_text().replace('__ROOT__', root.as_uri())
html = html.replace('/* editor helper */', editor_script)
html = html.replace('/* translations */', 'var catalogs = ' + json.dumps(catalogs) + ';')
with tempfile.TemporaryDirectory(prefix='dewawi-notifications-ui-') as directory:
    # Record hard reloads in the fixture instead of navigating away from its assertions.
    script = Path(directory) / 'global.js'
    script.write_text((root / 'js/global.js').read_text().replace('location.reload()', 'window.fixtureReload()'))
    html = html.replace((root / 'js/global.js').as_uri(), script.as_uri())
    contacts_script = Path(directory) / 'contacts.js'
    contacts_script.write_text((root / 'js/contacts.js').read_text().replace('location.reload()', 'window.fixtureReload()'))
    html = html.replace((root / 'js/contacts.js').as_uri(), contacts_script.as_uri())
    fixture = Path(directory) / 'fixture.html'
    for width in [1280, 390]:
        # Chromium enforces a 500px window minimum; use an iframe for a true mobile viewport.
        content = html
        if width < 500:
            content = '<pre id="result">RUNNING</pre><script>window.addEventListener("message", function(event) { document.getElementById("result").textContent = event.data; });</script>'
            content += '<iframe style="width:' + str(width) + 'px;height:900px;border:0" srcdoc="' + escape(html, quote=True) + '"></iframe>'
        fixture.write_text(content)
        result = subprocess.run([
            'chromium', '--headless', '--disable-gpu', '--disable-background-networking',
            '--no-first-run', '--no-default-browser-check', '--window-size=' + str(width) + ',900',
            '--user-data-dir=' + str(Path(directory) / ('profile-' + str(width))),
            '--virtual-time-budget=1000', '--dump-dom', fixture.as_uri(),
        ], capture_output=True, text=True, timeout=30)
        parser = ResultParser()
        parser.feed(result.stdout)
        print(parser.result or 'FAIL: no browser result')
        if result.returncode or not parser.result.startswith('PASS:'):
            print(result.stderr)
            raise SystemExit(1)
