"""Exercise actual pilot markup, layout and inquiry navigation code in Chromium."""
from pathlib import Path
import json
import os
import subprocess
import tempfile

root = Path(__file__).resolve().parents[2]
forms = {}
for step in [1, 2, 3]:
    result = subprocess.run(['php', str(root / 'tests/security/inquiry-pilot.php'), 'render-' + str(step)], check=True, capture_output=True, text=True)
    forms[str(step)] = '<form id="inquiry-form" data-current-step="' + str(step) + '">' + result.stdout.split('<!--fixture-->', 1)[1] + '</form>'
source = (root / 'js/shops.js').read_text()
script = source[source.index('function toggleDependentFields()'):source.index('// Function to show or hide shipping fields')]
script += source[source.index('function submitStep('):source.index("$(document).on('submit', '#inquiry-form'")]
script += source[source.index('function goBackStep('):]
checks = r'''
try {
    function check(value, message) { if (!value) throw new Error(message); }
    function form() { return document.querySelector('#inquiry-form'); }
    function input(name) { return form().querySelector('[name="' + name + '"]'); }
    function set(name, value) { const field = input(name); if (!field) return; field.value = value; $(field).trigger('input').trigger('change'); }
    function show(step) { $('#form-container').html(forms[step]); toggleDependentFields(); }
    function active(name) { return input(name) && !input(name).disabled; }
    function dispatchSiteConversions() {}
    function enhancePublicForms() {}
    let sent = '';
    let nextStep = '3';
    $.post = function(url, data, callback) {
        sent = data;
        callback({status:'ok', formHtml:'<section><div id="form-container">' + forms[nextStep] + '</div></section>'});
        return {fail: function() { return this; }, always: function(callback) { callback(); return this; }};
    };
    show('1');
    check(form().querySelector('details:not([open])'), 'advanced sections not collapsed');
    set('aufstellung', 'unbekannt'); set('medium', '0');
    for (const application of ['0','1','2','3']) {
        set('einsatzbereich', application);
        check(active('kuehlleistung') === ['0','1'].includes(application), 'cooling visibility mismatch');
        check(active('heizleistung') === ['2','3'].includes(application), 'heating visibility mismatch');
    }
    set('einsatzbereich', '0'); set('kuehlleistung', '0');
    check(!input('kuehlleistung').checkValidity(), 'zero demand accepted');
    set('kuehlleistung', '12.5'); set('vorlauf_temp', '0');
    set('medium', '1'); set('glykol_typ', '2'); set('glykol_bezugsart', 'volumen');
    check(active('glykol_typ_sonstiges') && active('glykol_bezugstemperatur'), 'nested glycol dependencies failed');
    set('glykol_typ_sonstiges', 'Other fluid'); set('glykol_anteil', '30.5'); set('glykol_bezugstemperatur', '0');
    check($(form()).serialize().includes('glykol_bezugstemperatur=0'), 'collapsed active temperature not serialized');
    set('glykol_typ', '1'); set('glykol_bezugsart', 'masse');
    check(!active('glykol_typ_sonstiges') && !active('glykol_bezugstemperatur') && !$(form()).serialize().includes('glykol_bezugstemperatur'), 'mass/known type retained inactive descendants');
    set('medium', '0');
    check(!$(form()).serialize().includes('glykol_'), 'water serialized inactive glycol');
    check(getComputedStyle(input('glykol_bezugstemperatur').closest('details')).display === 'none', 'empty fluid detail section remained visible');
    show('2');
    set('pumpe', 'unbekannt'); set('tank', 'unbekannt'); set('elektrische_versorgung', 'bekannt');
    set('netzspannung', '400'); set('netzfrequenz', '0');
    const details = input('netzfrequenz').closest('details');
    details.open = false;
    check(!form().checkValidity() && details.open, 'invalid collapsed input was not revealed');
    set('netzfrequenz', '50'); details.open = false;
    check(form().checkValidity() && $(form()).serialize().includes('netzspannung=400'), 'closed active electrical values blocked or lost');
    submitStep(form(), '2');
    check(sent.includes('netzspannung=400') && sent.includes('netzfrequenz=50') && form().getAttribute('data-current-step') === '3', 'AJAX submission lost advanced values');
    const country = input('country');
    check(country.tagName === 'SELECT' && country.querySelector('[value="DE"]') && !country.querySelector('[value="XX"]'), 'country is not a scoped select');
    set('country', 'DE'); set('lieferadresse_abweichend', 'ja');
    check(active('lieferstrasse') && active('lieferland'), 'structured delivery controls not activated');
    set('lieferempfaenger', 'Delivery GmbH'); set('lieferstrasse', 'Delivery Street'); set('lieferplz', '98765'); set('lieferstadt', 'Delivery City'); set('lieferland', 'CH');
    check($(form()).serialize().includes('country=DE') && $(form()).serialize().includes('lieferland=CH'), 'separate countries mixed');
    set('lieferadresse_abweichend', 'nein');
    check(!active('lieferland') && !$(form()).serialize().includes('lieferstrasse'), 'same-address selection kept stale delivery data');
    nextStep = '2';
    goBackStep('3');
    check(sent.includes('back=1') && form().getAttribute('data-current-step') === '2', 'back AJAX replacement failed');
    for (let i=0;i<5;i++) toggleDependentFields();
    set('pumpe', 'unbekannt'); set('tank', 'unbekannt'); set('elektrische_versorgung', 'bekannt'); set('netzspannung', '230');
    check(active('netzspannung') && $(form()).serialize().includes('netzspannung=230'), 'repeat AJAX initialization disabled active fields');
    check(document.documentElement.scrollWidth <= window.innerWidth, 'mobile/desktop horizontal overflow');
    document.getElementById('result').textContent = 'PASS: basic/advanced layout, nested fluid and application dependencies, numeric bounds, scoped country controls, shipping and AJAX navigation';
} catch (error) { document.getElementById('result').textContent = 'FAIL: ' + error.message; }
'''
with tempfile.TemporaryDirectory(prefix='dewawi-inquiry-pilot-ui-') as work:
    fixture = Path(work) / 'fixture.html'
    css = (root / 'css/ui.css').read_text() + (root / 'css/shop.css').read_text()
    fixture.write_text('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>' + css + '</style><div id="form-container"></div><pre id="result" style="white-space:pre-wrap">PENDING</pre><script>' + (root / 'library/jQuery/jquery-1.12.4.min.js').read_text() + '</script><script>const forms=' + json.dumps(forms) + ';' + script + checks + '</script>')
    for width in [390, 1280]:
        artifacts = os.environ.get('DEWAWI_INQUIRY_TEST_ARTIFACT_DIR')
        screenshot = []
        if artifacts:
            if not artifacts.startswith('/tmp/dewawi-'):
                raise ValueError('UI artifacts must stay under /tmp/dewawi-*')
            Path(artifacts).mkdir(exist_ok=True)
            screenshot = ['--screenshot=' + str(Path(artifacts) / ('pilot-' + str(width) + '.png'))]
        result = subprocess.run(['chromium', '--headless', '--no-sandbox', '--disable-gpu', '--disable-background-networking', '--no-first-run', '--user-data-dir=' + str(Path(work) / str(width)), '--window-size=' + str(width) + ',900', '--dump-dom', *screenshot, fixture.as_uri()], capture_output=True, text=True, timeout=30)
        marker = result.stdout.split('id="result" style="white-space:pre-wrap">', 1)[-1].split('</pre>', 1)[0]
        print(str(width) + 'px: ' + marker)
        if result.returncode or not marker.startswith('PASS:'):
            print(result.stderr)
            raise SystemExit(1)
