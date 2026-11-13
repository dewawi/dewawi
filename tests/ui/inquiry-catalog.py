"""Render actual catalog HTTP responses in isolated mobile/desktop Chromium."""
from pathlib import Path
import json
import subprocess
import sys

root = Path(__file__).resolve().parents[2]
work = Path(sys.argv[1])
if not str(work).startswith('/tmp/dewawi-shop-inquiry-quote-'):
    raise SystemExit('Only isolated catalog fixtures are accepted')
source = (root / 'js/shops.js').read_text()
helpers = source[source.index('function toggleDependentFields()'):source.index('// Function to show or hide shipping fields')]
binding = source[source.index("$(document).on('click', '#inquiry-form .select-catalog-suggestion'"):source.index("$(document).on('click', '[data-show-tab]'")]
script = r'''
try {
    function check(value, message) { if (!value) throw new Error(message); }
    toggleDependentFields();
    const form = document.getElementById('inquiry-form');
    let buttons = form.querySelectorAll('.select-catalog-suggestion');
    check(buttons.length === expectedCount, 'wrong candidate count');
    buttons[buttons.length - 1].click();
    const selected = buttons[buttons.length - 1].getAttribute('data-itemid');
    check(form.querySelector('[name="itemid"]').value === selected, 'selected ID is not canonical');
    check(document.querySelector('#decoy [name="itemid"]').value === 'untouched', 'selection escaped form scope');
    check(form.querySelector('.inquiry-catalog-choice').textContent.includes(buttons[buttons.length - 1].getAttribute('data-sku')), 'selection confirmation missing');
    if (expectedCount === 2) {
        check(buttons[0].getAttribute('data-sku') === buttons[1].getAttribute('data-sku') && buttons[0].getAttribute('data-itemid') !== buttons[1].getAttribute('data-itemid'), 'duplicate SKU identity collapsed');
    }
    form.querySelector('.clear-catalog-suggestion').click();
    check(form.querySelector('[name="itemid"]').value === '' && !form.querySelector('[aria-pressed="true"]'), 'optional selection cannot be cleared');
    buttons[buttons.length - 1].click();
    form.querySelector('[name="itemid"]').setAttribute('value', selected);
    const replacement = $(form.outerHTML)[0]; form.replaceWith(replacement);
    for (let i=0;i<5;i++) toggleDependentFields();
    check(replacement.querySelector('[aria-pressed="true"]').getAttribute('data-itemid') === selected, 'AJAX restored selection was not marked');
    replacement.querySelector('.select-catalog-suggestion').click();
    check(replacement.querySelector('[name="itemid"]').value === replacement.querySelector('.select-catalog-suggestion').getAttribute('data-itemid'), 'AJAX click handling lost');
    check(!replacement.textContent.includes('Berechneter Wert') && replacement.textContent.includes('Fachlich ungeprüft'), 'legacy calculation or automatic suitability claim displayed');
    check(document.documentElement.scrollWidth <= window.innerWidth, 'catalog horizontal overflow');
    document.getElementById('test-result').textContent = 'PASS: catalog cards, canonical duplicate-SKU identity, scope, review status and AJAX selection';
} catch(error) { document.getElementById('test-result').textContent = 'FAIL: ' + error.message; }
'''
for group, expected_count in [('equipment', 2), ('trading', 1)]:
    rendered = (work / ('catalog-' + group + '.html')).read_text()
    image = (work / 'base/media/1/0/100/images/catalog.png').as_uri()
    rendered = rendered.replace('/media/images/catalog.png', image)
    fixture = work / ('catalog-ui-' + group + '.html')
    fixture.write_text('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>' + (root / 'css/ui.css').read_text() + (root / 'css/shop.css').read_text() + '</style>' + rendered + '<form id="decoy"><input name="itemid" value="untouched"></form><pre id="test-result" style="white-space:pre-wrap">PENDING</pre><script>' + (root / 'library/jQuery/jquery-1.12.4.min.js').read_text() + '</script><script>const expectedCount=' + json.dumps(expected_count) + ';' + helpers + binding + script + '</script>')
    for width in [390, 1280]:
        result = subprocess.run(['chromium', '--headless', '--no-sandbox', '--disable-gpu', '--disable-background-networking', '--no-first-run', '--user-data-dir=' + str(work / ('chrome-' + group + str(width))), '--window-size=' + str(width) + ',900', '--dump-dom', fixture.as_uri()], capture_output=True, text=True, timeout=30)
        marker = result.stdout.split('id="test-result" style="white-space:pre-wrap">', 1)[-1].split('</pre>', 1)[0]
        print(group + ' ' + str(width) + 'px: ' + marker)
        if result.returncode or not marker.startswith('PASS:'):
            print(result.stderr)
            raise SystemExit(1)
