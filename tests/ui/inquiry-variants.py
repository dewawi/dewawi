"""Exercise actual variant HTTP markup and existing AJAX controls in isolated Chromium."""
from pathlib import Path
import json
import subprocess
import sys

root = Path(__file__).resolve().parents[2]
work = Path(sys.argv[1])
if not str(work).startswith('/tmp/dewawi-shop-inquiry-quote-'):
    raise SystemExit('Only isolated variant fixtures are accepted')
source = (root / 'js/shops.js').read_text()
helpers = source[source.index('function toggleDependentFields()'):source.index('// Function to show or hide shipping fields')]
binding = source[source.index("$(document).on('click', '#inquiry-form .select-catalog-suggestion'"):source.index("$(document).on('click', '[data-show-tab]'")]
script = r'''
try {
    new Function(shopsSource);
    function check(value, message) { if (!value) throw new Error(message); }
    function submitStep(form, step, preview) { window.previewCall = { form, step, preview }; }
    toggleDependentFields();
    let form = document.getElementById('inquiry-form');
    check(form.querySelectorAll('.select-inquiry-variant').length === 2, 'existing variants missing');
    check(form.querySelector('.variant-pricing') && form.textContent.includes('kein automatisches Angebot'), 'price or manual review notice missing');
    const changed = form.querySelector('[name="execution"]');
    changed.value = 'large'; $(changed).trigger('change');
    check(form.querySelector('[name="variantid"]').value === '', 'stale variant identity survived option change');
    check(!$(form.querySelector('.variant-pricing')).is(':visible') && form.querySelector('.variant-pending').textContent.includes('erneut geprüft'), 'stale price remains confirmed');
    const button = form.querySelectorAll('.select-inquiry-variant')[1];
    button.click();
    check(form.querySelector('[name="variantid"]').value === button.getAttribute('data-variantid') && form.querySelector('[name="execution"]').value === 'large', 'variant did not populate actual option mapping');
    check(button.getAttribute('aria-pressed') === 'true', 'variant selected state missing');
    check(document.querySelector('#decoy [name="variantid"]').value === 'untouched', 'variant escaped form scope');
    form.querySelector('.preview-inquiry-variant').click();
    check(window.previewCall.form === form && window.previewCall.step === 'configuration' && window.previewCall.preview === true, 'preview does not use existing AJAX submission');
    const replacement = $(form.outerHTML)[0]; form.replaceWith(replacement); form = replacement;
    toggleDependentFields();
    form.querySelector('.select-inquiry-variant').click();
    check(form.querySelector('[name="execution"]').value === 'small', 'AJAX replacement lost option handlers');
    $(form.querySelector('[name="quantity"]')).trigger('input');
    check(form.querySelector('[name="variantid"]').value === '', 'changed quantity retained stale pricing state');
    check(document.documentElement.scrollWidth <= window.innerWidth, 'variant configuration horizontal overflow');
    document.getElementById('test-result').textContent = 'PASS: variant identities, mapped options, price invalidation, scope, review notice and AJAX controls';
} catch(error) { document.getElementById('test-result').textContent = 'FAIL: ' + error.message; }
'''
for family in ['equipment', 'trading']:
    markup = (work / ('variant-' + family + '.html')).read_text()
    fixture = work / ('variant-ui-' + family + '.html')
    fixture.write_text('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>' + (root / 'css/ui.css').read_text() + (root / 'css/shop.css').read_text() + '</style>' + markup + '<form id="decoy"><input name="variantid" value="untouched"></form><pre id="test-result" style="white-space:pre-wrap">PENDING</pre><script>' + (root / 'library/jQuery/jquery-1.12.4.min.js').read_text() + '</script><script>const shopsSource=' + json.dumps(source).replace('</', '<\\/') + ';' + helpers + binding + script + '</script>')
    for width in [390, 1280]:
        result = subprocess.run(['chromium', '--headless', '--no-sandbox', '--disable-gpu', '--disable-background-networking', '--no-first-run', '--user-data-dir=' + str(work / ('chrome-variant-' + family + str(width))), '--window-size=' + str(width) + ',900', '--dump-dom', fixture.as_uri()], capture_output=True, text=True, timeout=30)
        marker = result.stdout.split('id="test-result" style="white-space:pre-wrap">', 1)[-1].split('</pre>', 1)[0]
        print('variants ' + family + ' ' + str(width) + 'px: ' + marker)
        if result.returncode or not marker.startswith('PASS:'):
            print(result.stderr)
            raise SystemExit(1)
