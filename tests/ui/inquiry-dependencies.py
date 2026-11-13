"""Run actual dynamic-form markup and dependency JavaScript in isolated Chromium."""
from html import escape
from pathlib import Path
import subprocess
import tempfile

root = Path(__file__).resolve().parents[2]
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
nested_checks = r'''
try {
    function check(value, message) { if (!value) throw new Error(message); }
    let form = document.querySelector('form');
    const initialMarkup = form.outerHTML;
    function set(name, value) { const input = form.querySelector('[name="' + name + '"]'); input.value = value; $(input).trigger('change'); }
    function active(name) { const input = form.querySelector('[name="' + name + '"]'); return !input.disabled && getComputedStyle(input.closest('.dependent-field')).display !== 'none'; }
    toggleDependentFields();
    check(!active('glykol_typ') && !active('glykol_typ_sonstiges') && !active('glykol_bezugstemperatur'), 'nested water fields visible');
    set('medium', '1'); set('glykol_typ', '2'); set('glykol_bezugsart', 'volumen');
    check(active('glykol_typ_sonstiges') && active('glykol_bezugstemperatur') && !form.checkValidity(), 'nested required activation failed');
    set('glykol_typ_sonstiges', 'Session fluid'); set('glykol_bezugstemperatur', '0');
    check(form.checkValidity() && $(form).serialize().includes('glykol_bezugstemperatur=0'), 'zero temperature or nested value lost');
    for (let i = 0; i < 10; i++) toggleDependentFields();
    set('medium', '0');
    check(!active('glykol_typ_sonstiges') && !active('glykol_bezugstemperatur') && form.checkValidity(), 'inactive grandparent not applied');
    check(!$(form).serialize().includes('glykol_'), 'nested stale data serialized');
    set('medium', '1'); set('glykol_typ', '1'); set('glykol_bezugsart', 'masse');
    check(!active('glykol_typ_sonstiges') && !active('glykol_bezugstemperatur') && form.checkValidity(), 'mass/known type kept extra requirements');
    const replacement = $(initialMarkup)[0]; form.replaceWith(replacement); form = replacement;
    form.querySelector('[name="medium"]').value = '1';
    form.querySelector('[name="glykol_typ"]').value = '2';
    form.querySelector('[name="glykol_typ_sonstiges"]').value = 'Restored session';
    form.querySelector('[name="glykol_bezugsart"]').value = 'volumen';
    form.querySelector('[name="glykol_bezugstemperatur"]').value = '0';
    toggleDependentFields();
    check(active('glykol_typ_sonstiges') && active('glykol_bezugstemperatur') && form.checkValidity(), 'AJAX/session restoration failed');
    document.getElementById('result').textContent = 'PASS: nested dependencies, zero temperature, serialization and restored AJAX values';
} catch (error) { document.getElementById('result').textContent = 'FAIL: ' + error.message; }
'''
with tempfile.TemporaryDirectory(prefix='dewawi-inquiry-ui-') as work:
    fixture = Path(work) / 'fixture.html'
    for mode, script in [('render', checks), ('render-nested', nested_checks)]:
        rendered = subprocess.run(['php', str(root / 'tests/security/inquiry-dynamic-form.php'), mode], check=True, capture_output=True, text=True).stdout.split('<!--fixture-->', 1)[1]
        fixture.write_text('<!doctype html><meta charset="utf-8"><form>' + rendered + '</form><pre id="result">PENDING</pre><script>' + (root / 'library/jQuery/jquery-1.12.4.min.js').read_text() + '</script><script>' + dependency_js + script + '</script>')
        for width in [390, 1280]:
            result = subprocess.run(['chromium', '--headless', '--no-sandbox', '--disable-gpu', '--disable-background-networking', '--no-first-run', '--user-data-dir=' + str(Path(work) / (mode + str(width))), '--window-size=' + str(width) + ',900', '--dump-dom', fixture.as_uri()], capture_output=True, text=True, timeout=30)
            marker = result.stdout.split('<pre id="result">', 1)[-1].split('</pre>', 1)[0]
            print(mode + ' ' + str(width) + 'px: ' + marker)
            if result.returncode or not marker.startswith('PASS:'):
                print(result.stderr)
                raise SystemExit(1)
