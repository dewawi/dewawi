"""Run actual dynamic-form markup and dependency JavaScript in isolated Chromium."""
from html import escape
from pathlib import Path
import subprocess
import tempfile

root = Path(__file__).resolve().parents[2]
rendered = subprocess.run(['php', str(root / 'tests/security/inquiry-dynamic-form.php'), 'render'], check=True, capture_output=True, text=True).stdout.split('<!--fixture-->', 1)[1]
source = (root / 'js/shops.js').read_text()
dependency_js = source[source.index('function toggleDependentFields()'):source.index('// Function to show or hide shipping fields')]
checks = r'''
try {
    function check(value, message) { if (!value) throw new Error(message); }
    const form = document.querySelector('form');
    const medium = form.querySelector('[name="medium"]');
    const glycol = form.querySelector('[name="glykol_anteil"]');
    const initialMarkup = form.outerHTML;
    toggleDependentFields();
    check(glycol.disabled && getComputedStyle(glycol.closest('.dependent-field')).display === 'none', 'inactive wrapper or native validation');
    check(!$(form).serialize().includes('glykol_anteil'), 'inactive value serialized');
    medium.value = '1'; $(medium).trigger('change');
    check(!glycol.disabled && getComputedStyle(glycol.closest('.dependent-field')).display !== 'none', 'numeric selection failed');
    check(!form.checkValidity(), 'active missing required input accepted');
    glycol.value = '30'; form.querySelector('[name="glykol_typ"]').value = 'PG';
    check(form.checkValidity(), 'valid active requirements rejected');
    for (let i = 0; i < 10; i++) toggleDependentFields();
    medium.value = '0'; $(medium).trigger('change');
    check(glycol.disabled && form.checkValidity(), 'repeat initialization left invalid hidden fields');
    check(!$(form).serialize().includes('glykol_typ'), 'stale hidden selection serialized');
    const replacement = $(initialMarkup)[0]; form.replaceWith(replacement);
    toggleDependentFields();
    const nextMedium = replacement.querySelector('[name="medium"]');
    nextMedium.value = '1'; $(nextMedium).trigger('change');
    check(!replacement.querySelector('[name="glykol_anteil"]').disabled, 'AJAX replacement did not activate');
    document.getElementById('result').textContent = 'PASS: dependency wrappers, numeric values, native validation, serialization and AJAX replacement';
} catch (error) { document.getElementById('result').textContent = 'FAIL: ' + error.message; }
'''
with tempfile.TemporaryDirectory(prefix='dewawi-inquiry-ui-') as work:
    fixture = Path(work) / 'fixture.html'
    fixture.write_text('<!doctype html><meta charset="utf-8"><form>' + rendered + '</form><pre id="result">PENDING</pre><script>' + (root / 'library/jQuery/jquery-1.12.4.min.js').read_text() + '</script><script>' + dependency_js + checks + '</script>')
    for width in [390, 1280]:
        result = subprocess.run(['chromium', '--headless', '--no-sandbox', '--disable-gpu', '--disable-background-networking', '--no-first-run', '--user-data-dir=' + str(Path(work) / str(width)), '--window-size=' + str(width) + ',900', '--dump-dom', fixture.as_uri()], capture_output=True, text=True, timeout=30)
        marker = result.stdout.split('<pre id="result">', 1)[-1].split('</pre>', 1)[0]
        print(str(width) + 'px: ' + marker)
        if result.returncode or not marker.startswith('PASS:'):
            print(result.stderr)
            raise SystemExit(1)
