#!/usr/bin/env python3
"""Parse changed JS and exercise real jQuery inquiry labels in offline Chromium."""
import html
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[2]


def main():
    chromium = shutil.which('chromium')
    if not chromium:
        print(json.dumps({'status': 'unavailable', 'reason': 'Chromium not installed'}))
        return 1
    sources = {name: (ROOT / 'js' / name).read_text() for name in ['global.js', 'shops.js', 'settings.js']}
    # Read reviewed translation values without bootstrapping an HTTP request.
    paths = [ROOT / f'languages/{locale}/{catalogue}.php' for locale in ['de_DE', 'en_US'] for catalogue in ['shops/shops', 'default/messages']]
    inspected = subprocess.run(['php', str(ROOT / 'tests/translations/inspect.php')], input=json.dumps({'mode': 'catalogues', 'files': list(map(str, paths))}), capture_output=True, text=True, check=True)
    entries = json.loads(inspected.stdout)
    labels = {locale: {e['key']: e['value'] for path in paths if f'/{locale}/' in str(path) for e in entries[str(path)]['entries']} for locale in ['de_DE', 'en_US']}
    script = '''
let checks = 0;
function check(ok, message) { if (!ok) throw new Error(message); checks++; }
try {
  Object.values(SOURCES).forEach(source => { new Function(source); checks++; });
  const source = SOURCES['shops.js'];
  const start = source.indexOf('function selectInquiryCatalogItem(');
  const end = source.indexOf('function selectInquiryVariant(', start);
  const functions = new Function(source.slice(start, end) + '; return {selectInquiryCatalogItem, invalidateInquiryVariant};')();
  const globalSource = SOURCES['global.js'];
  const errorHandler = globalSource.slice(globalSource.indexOf('function handleEditError('), globalSource.indexOf('function isSelectionField('));
  const runErrorHandler = new Function('Dewawi', 'pushMessages', 'unlock', errorHandler + '; return handleEditError;');
  for (const locale of ['de_DE', 'en_US']) {
    const text = LABELS[locale];
    const messages = [], unlocked = [];
    const handler = runErrorHandler({message: key => text[key]}, message => messages.push(message), id => unlocked.push(id));
    for (const [response, key] of [[{message: 'not_found'}, 'MESSAGES_RECORD_UNAVAILABLE'], [{message: 'save_failed'}, 'MESSAGES_SAVE_FAILED'], [null, 'MESSAGES_SAVE_FAILED']]) {
      check(handler(response, {id: 41}) === true, locale + ' AJAX error consumed');
      check(messages.pop()[0] === text[key] && text[key] !== key, locale + ' AJAX error translated');
      check(unlocked.pop() === 41, locale + ' AJAX error unlocks original record');
    }
    const errors = {email: 'Synthetic server validation'};
    handler({errors}, {id: 41});
    check(messages.pop().errors === errors, locale + ' server field errors preserved');
    const form = $('<form><input name="item"><input name="sku"><input name="titel"><input name="variant"><p class="inquiry-catalog-choice"></p><div class="inquiry-variants" data-variant-field="variant"><p class="variant-pending"></p></div><button class="select-catalog-suggestion" data-item-field="item" data-itemid="12" data-sku="A12" data-title="Fixture"></button></form>').appendTo(document.body);
    form.attr('data-inquiry-selected', text.SHOPS_CATALOG_SELECTED).attr('data-inquiry-unselected', text.SHOPS_CATALOG_UNSELECTED).attr('data-inquiry-changed', text.SHOPS_VARIANT_CHANGED);
    const button = form.find('button');
    functions.selectInquiryCatalogItem(button[0], false);
    check(form.find('[name=item]').val() === '12', locale + ' selected item value');
    check(form.find('.inquiry-catalog-choice').text() === text.SHOPS_CATALOG_SELECTED + ' A12 – Fixture', locale + ' selected label');
    button.attr('data-itemid', ''); functions.selectInquiryCatalogItem(button[0], false);
    check(form.find('.inquiry-catalog-choice').text() === text.SHOPS_CATALOG_UNSELECTED, locale + ' empty label');
    functions.invalidateInquiryVariant(form);
    check(form.find('.variant-pending').text() === text.SHOPS_VARIANT_CHANGED, locale + ' variant label');
    check(form.find('[name=variant]').val() === '', locale + ' stale variant cleared');
    form.remove();
  }
  document.body.textContent = JSON.stringify({status: 'passed', checks, locales: ['de_DE', 'en_US']});
} catch (error) { document.body.textContent = JSON.stringify({status: 'failed', checks, error: error.message}); }
'''
    script = 'const SOURCES = ' + json.dumps(sources).replace('<', '\\u003c') + '; const LABELS = ' + json.dumps(labels).replace('<', '\\u003c') + ';\n' + script
    with tempfile.TemporaryDirectory(prefix='dewawi-translation-browser-') as directory:
        work = Path(directory)
        page = work / 'fixture.html'
        page.write_text('<!doctype html><meta charset="utf-8"><script src="' + (ROOT / 'library/jQuery/jquery-1.12.4.min.js').as_uri() + '"></script><body><script>' + script + '</script></body>')
        run = subprocess.run([chromium, '--headless', '--no-sandbox', '--disable-gpu', '--disable-background-networking', '--allow-file-access-from-files', '--host-resolver-rules=MAP * ~NOTFOUND', '--user-data-dir=' + str(work / 'profile'), '--dump-dom', page.as_uri()], capture_output=True, text=True, timeout=45)
        body = run.stdout.split('<body>', 1)[-1].split('</body>', 1)[0]
        try:
            result = json.loads(html.unescape(body))
        except json.JSONDecodeError:
            result = {'status': 'failed', 'error': 'Chromium returned no fixture result', 'exit_code': run.returncode, 'stderr': run.stderr[-1500:]}
        print(json.dumps(result, ensure_ascii=False, indent=2))
        return int(result['status'] != 'passed')


if __name__ == '__main__':
    sys.exit(main())
