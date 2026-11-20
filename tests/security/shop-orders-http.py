#!/usr/bin/env python3
"""Use only the database created by shop-checkout-db.py for HTTP and browser tests."""
import html
from html.parser import HTMLParser
import http.cookiejar
import json
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
work = Path(sys.argv[1]).resolve()
if not str(work).startswith('/tmp/dewawi-shop-checkout-db-'):
    raise SystemExit('Isolated fixture directory required')
fixtures = json.loads((work / 'order-fixtures.json').read_text())
checks = 0

def check(condition, message):
    global checks
    checks += 1
    if not condition:
        raise AssertionError(message)

class FormInputs(HTMLParser):
    def __init__(self, text):
        super().__init__()
        self.tokens = []
        self.feed(text)
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'input' and attrs.get('name') == 'csrf':
            self.tokens.append(attrs.get('value', ''))

with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0))
    port = sock.getsockname()[1]
origin = f'http://127.0.0.1:{port}'
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
env = dict(os.environ, DEWAWI_ORDER_TEST_SOCKET=str(work / 'mysql.sock'))

def request(path, data=None, ajax=False):
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    headers = {'X-Requested-With': 'XMLHttpRequest'} if ajax else {}
    try:
        with client.open(urllib.request.Request(origin + path, data=body, headers=headers), timeout=15) as response:
            return response.status, response.read().decode(), response.headers
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode(), e.headers

def check_erp_layout(text, detail=False):
    check('dw-app-shell' in text and 'dw-topbar__nav' in text, 'Production ERP layout/navigation missing')
    check(re.search(r'<a class="dw-nav-dropdown__link is-active" href="/shops/order">', text), 'Shop order navigation entry missing or inactive')
    breadcrumbs = re.search(r'<div class="dw-breadcrumbs" id="breadcrumbs">(.*?)</div>', text, re.S)
    check(breadcrumbs is not None, 'ERP breadcrumbs missing')
    check('href="/"' in breadcrumbs[1] and 'href="/shops"' in breadcrumbs[1] and 'href="/shops/order"' in breadcrumbs[1], 'ERP breadcrumb hierarchy/links incorrect')
    check('breadcrumb-item' not in breadcrumbs[1] and 'BreadcrumbList' not in text, 'Public SEO breadcrumbs leaked into ERP')
    check(('dw-page-layout--detail' in text) == detail, 'Wrong ERP list/detail layout')

log = (work / 'order-http.log').open('w')
server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(work), str(ROOT / 'tests/security/fixtures/shop-orders-http.php')], env=env, stdout=log, stderr=log)
try:
    for _ in range(100):
        try:
            status, text, headers = request('/view?contactquery=Changed')
            break
        except urllib.error.URLError:
            time.sleep(.05)
    else:
        raise AssertionError('HTTP fixture did not start')
    check(status == 200, 'Detail failed: ' + (work / 'order-http.log').read_text())
    check_erp_layout(text, detail=True)
    status, canonical_detail, _ = request('/shops/order/view/id/' + str(fixtures['new']))
    check(status == 200, 'Canonical internal order detail failed')
    check_erp_layout(canonical_detail, detail=True)
    check('Original buyer GmbH' in text and 'Original item title' in text and 'ORIGINAL-SKU' in text, 'Detail reconstructed current master data')
    check('SHOPS_ORDER_' not in text, 'Detail contains untranslated labels')
    check('no-store' in headers.get('Cache-Control', ''), 'Customer order response can be publicly cached')
    csrf = FormInputs(text).tokens[0]
    state_url = f"/shops/order/state/id/{fixtures['new']}"
    convert_url = f"/shops/order/createsalesorder/id/{fixtures['new']}"
    check(request(state_url)[0] == 405, 'GET changes order status')
    check(request(state_url, {'csrf': 'wrong', 'status': 'in_progress'})[0] == 403, 'Missing CSRF accepted')
    check(request(state_url, {'csrf': csrf, 'status': 'in_progress', 'total': '1'})[0] == 400, 'Snapshot mutation field accepted')
    check(request(state_url, {'csrf': csrf, 'status': 'in_progress'})[0] == 200, 'Valid status transition failed')
    check(request('/view?role=reader')[0] == 200, 'Reader cannot inspect order')
    check(request(state_url + '?role=reader', {'csrf': csrf, 'status': 'completed'})[0] == 403, 'Reader changes order')
    for role in ('guest', 'denied'):
        check(request('/view?role=' + role)[0] == 403, role + ' can inspect order')
        check(request('/index?role=' + role)[0] == 403, role + ' can list stored orders')
        check(request('/search?role=' + role)[0] == 403, role + ' can search stored orders')
        check(request(convert_url + '?role=' + role, {'csrf': csrf, 'contactid': fixtures['contact']})[0] == 403, role + ' can convert stored orders')
    check(request(convert_url + '?role=reader', {'csrf': csrf, 'contactid': fixtures['contact']})[0] == 403, 'Reader converts stored orders')
    check(request('/view?tenant=8')[0] == 404, 'Foreign tenant accesses order')
    check(request('/view?id=999999')[0] == 404, 'Missing order is not 404')
    check(request('/add')[0] == 404 and request('/delete')[0] == 404, 'Legacy write actions remain available')
    for role in ('noadd', 'nocontacts', 'noedit'):
        check(request('/view?role=' + role)[0] == 200, role + ' cannot inspect authorized order')
        check(request(convert_url + '?role=' + role, {'csrf': csrf, 'contactid': fixtures['contact']})[0] == 403, role + ' bypassed combined conversion rights')
    status, notification_view, _ = request('/view?contactquery=Changed')
    check(all(address in notification_view for address in ['customer-order@example.test', 'internal-order@example.test', 'pending-order@example.test']), 'Linked notification recipients missing')
    check(all(address not in notification_view for address in ['foreign-notification@example.test', 'unverified-legacy@example.test', 'other-module@example.test', 'deleted-notification@example.test']), 'Unverified or foreign mail metadata leaked')
    check('fixture-secret' not in notification_view and 'Vom Mailserver angenommen' in notification_view and 'Versandversuchs unbekannt' in notification_view, 'Notification status is unsafe or misleading')
    check('data-autosave="false"' in notification_view and '<option value=""></option>' not in notification_view and 'value=""' in notification_view, 'Explicit action controls missing')
    check('12,3456' in notification_view and 'Versandkosten werden in diesem Checkout nicht berechnet' in notification_view, 'Historical precision or shipping notice missing')
    check(request('/resend')[0] == 404, 'Uncontrolled notification resend action available')
    status, orphan, _ = request('/view?id=' + str(fixtures['orphan']))
    check(status == 200 and 'name="contactid"' not in orphan and 'value="in_progress"' not in orphan, 'Orphan transferred order advertises invalid actions')
    check(request('/shops/order/state/id/' + str(fixtures['orphan']), {'csrf': csrf, 'status': 'in_progress'})[0] == 200, 'Invalid orphan transition did not return a reviewable error')
    status, malformed, _ = request('/view?id=' + str(fixtures['malformed']))
    check(status == 200 and 'name="contactid"' not in malformed and 'Fatal error' not in malformed, 'Malformed snapshot breaks the detail view or offers conversion')
    status, no_contacts, _ = request('/view?contactquery=NoKnownContact')
    check(status == 200 and 'Keine zugänglichen Kontakte gefunden' in no_contacts and 'id="order-contact"' in no_contacts and 'required aria-describedby="order-conversion-notice" disabled' in no_contacts, 'Empty contact search offers an active transfer')
    status, legacy, _ = request('/view?id=' + str(fixtures['legacy']))
    check(status == 200 and 'Historische Bestelldaten fehlen' in legacy and 'name="contactid"' not in legacy, 'Incomplete historical order exposes conversion')
    status, listing, _ = request('/shops/order')
    check(status == 200 and 'Original buyer GmbH' in listing and 'SHOPS_ORDER_' not in listing, 'Internal order list failed: ' + (work / 'order-http.log').read_text())
    check_erp_layout(listing)
    check('value="41"' in listing and 'value="42"' in listing and 'Other shop' in listing, 'Shop source did not resolve actual tenant shops')
    check('value="43"' not in listing and 'Foreign shop' not in listing and 'Deleted shop' not in listing, 'Shop choices leak foreign/deleted shops')

    def rows(text):
        return re.findall(r'>(LIST-[A-Z0-9]+)</a>', text)

    def listing_search(expected, data, query=''):
        status, body, headers = request('/shops/order/search' + query, data, ajax=True)
        check(status == 200 and 'application/json' in headers.get('Content-Type', ''), 'AJAX search failed: ' + body)
        payload = json.loads(body)
        check(isinstance(payload.get('toolbar'), str) and isinstance(payload.get('content'), str), 'Real ListResponse AJAX contract missing')
        check(rows(payload['content']) == expected, 'Unexpected rendered order results: ' + str(rows(payload['content'])))
        if query != '?tenant=8':
            check('LIST-FOREIGN' not in payload['content'] and 'Foreign shop' not in payload['toolbar'], 'AJAX leaked foreign tenant data')
        return payload

    all_rows = [f'LIST-{i:03d}' for i in range(1, 14)]
    base = {'keyword': 'LIST-', 'order': 'total', 'sort': 'ASC', 'limit': 25, 'page': 1, 'shopid': 'all', 'status': 'all'}
    listing_search(all_rows, base)
    listing_search(all_rows, base, '?role=reader')
    listing_search(all_rows, base, '?tenant=9')
    status, shared, _ = request('/shops/order?tenant=9')
    check(status == 200 and 'value="41"' in shared and 'value="42"' in shared and 'value="43"' not in shared, 'Parent-child shop choices differ from shared order owner')
    status, empty, _ = request('/shops/order?tenant=10')
    check(status == 200 and not rows(empty) and 'Original buyer GmbH' not in empty, 'Empty first list failed or leaked data')
    listing_search([], base, '?tenant=10')
    listing_search(all_rows[:11], dict(base, shopid=41))
    listing_search(all_rows[11:], dict(base, shopid=42))
    listing_search(all_rows[1::2], dict(base, status='cancelled'))
    listing_search(all_rows[::2], dict(base, status='open'))
    listing_search(['LIST-012'], dict(base, shopid=42, status='cancelled'))
    listing_search(['LIST-013'], dict(base, keyword='List customer 13'))
    listing_search([], dict(base, keyword='No matching customer'))
    listing_search([], dict(base, shopid=43))
    listing_search([], dict(base, shopid='41 OR 1=1'))
    listing_search([], dict(base, status='forged'))
    listing_search(['LIST-FOREIGN'], dict(base, shopid=43), '?tenant=8')
    for role in ('guest', 'denied'):
        check(request('/shops/order/search?role=' + role, base, ajax=True)[0] == 403, role + ' can use AJAX search')
    listing_search(list(reversed(all_rows)), dict(base, sort='DESC'))
    listing_search(all_rows[:10], dict(base, limit=10))
    listing_search(all_rows[10:], dict(base, limit=10, page=2))
    status, body, _ = request('/search?keyword=LIST-012&shopid=42&status=cancelled')
    check(status == 200 and rows(body) == ['LIST-012'], 'Non-AJAX search results differ')
    # Render the actual detail view in Chromium at desktop and mobile widths.
    chrome = shutil.which('chromium') or shutil.which('google-chrome')
    if not chrome:
        raise AssertionError('Chromium unavailable')
    for width in (1280, 390, 320):
        script = """const frame=document.querySelector('iframe');frame.onload=()=>setTimeout(()=>{const d=frame.contentDocument,w=frame.contentWindow;const failures=[];let checks=0;function check(v,m){checks++;if(!v)failures.push(m)}check(d.body.textContent.includes('Original buyer GmbH'),'Missing customer');check(!!d.querySelector('select[name="contactid"]'),'Missing customer selection');check(d.documentElement.scrollWidth<=w.innerWidth,'Horizontal page overflow');check(d.querySelectorAll('input[name="csrf"]').length===2,'Missing action tokens');check(d.querySelector('form[action*="createsalesorder"]').method==='post','Conversion is not explicit POST');check(!d.querySelector('[data-name="price"]'),'Historical price is editable');document.querySelector('#metrics').textContent=JSON.stringify({checks,failures});},300);"""
        page = f'<!doctype html><html><body><iframe src="{origin}/view?contactquery=Changed" style="width:{width}px;height:1400px;border:0"></iframe><pre id="metrics"></pre><script>{script}</script></body></html>'
        # The parent must share the fixture origin to inspect its iframe.
        (work / 'order-browser.html').write_text(page)
        with tempfile.TemporaryDirectory(prefix='dewawi-order-chromium-') as profile:
            result = subprocess.run([chrome, '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', f'--user-data-dir={profile}', f'--window-size={max(width,800)},1500', '--virtual-time-budget=3000', '--dump-dom', origin + '/order-browser.html'], capture_output=True, text=True, timeout=35)
        found = re.findall(r'<pre id="metrics">(.*?)</pre>', result.stdout, re.S)
        check(result.returncode == 0 and bool(found) and bool(found[-1]), 'Chromium did not return order metrics')
        metrics = json.loads(html.unescape(found[-1]))
        check(not metrics['failures'], f"{width}px: " + '; '.join(metrics['failures']))
        checks += metrics['checks']

    # Exercise the repository's global.js events, real AJAX response plugin and rendered list.
    for width in (1280, 390, 320):
        script = r"""
const frame=document.querySelector('iframe');
frame.onload=()=>setTimeout(async()=>{
 const d=frame.contentDocument,w=frame.contentWindow,$=w.jQuery,failures=[];let checks=0,requests=0;
 function check(v,m){checks++;if(!v)throw new Error(m)}
 function rows(){return [...d.querySelectorAll('#content tbody a')].map(a=>a.textContent.trim()).filter(t=>/^LIST-/.test(t))}
 function field(name,value){const f=$(d).find('[name="'+name+'"]');if(f.is(':radio'))f.filter('[value="'+value+'"]').prop('checked',true).trigger('change');else f.val(value).trigger(name==='keyword'?'input':'change')}
 async function expect(expected){const before=requests;for(let i=0;i<100;i++){await new Promise(r=>setTimeout(r,50));if(!w.Dewawi.searchRequest&&requests>before&&JSON.stringify(rows())===JSON.stringify(expected)){checks++;return}}throw new Error('Rendered results: '+JSON.stringify(rows())+' expected '+JSON.stringify(expected))}
 $(d).ajaxSuccess((event,xhr,settings,data)=>{if(settings.url.includes('/shops/order/search')){requests++;check(typeof data.content==='string'&&typeof data.toolbar==='string','AJAX response contract')}});
 try{
  check(!!w.DewawiToolbar,'Production toolbar events not initialized: '+JSON.stringify(w.fixtureErrors));
  $(d).find('[data-action="filter"]').trigger('click');check($(d).find('.dw-filter-panel').hasClass('is-open'),'Filter panel did not open');
  field('keyword','LIST-');await expect(Array.from({length:13},(_,i)=>'LIST-'+String(13-i).padStart(3,'0')));
  field('shopid','42');await expect(['LIST-013','LIST-012']);
  field('status','cancelled');await expect(['LIST-012']);
  field('keyword','LIST-013');await expect([]);
  field('keyword','LIST-');field('status','all');field('shopid','all');field('order','total');field('sort','DESC');field('limit','10');
  await expect(Array.from({length:10},(_,i)=>'LIST-'+String(13-i).padStart(3,'0')));
  field('page','2');await expect(['LIST-003','LIST-002','LIST-001']);
  field('sort','ASC');await expect(Array.from({length:10},(_,i)=>'LIST-'+String(i+1).padStart(3,'0')));
  check($(d).find('#pagination-page').val()==='1','Sorting did not reset pagination');
  $(d).find('[name="shopid"]').append('<option value="43">Forged foreign shop</option>');field('shopid','43');await expect([]);
  check(requests>=8,'Expected search/filter/pagination AJAX requests missing');
  check(!w.fixtureErrors.length,'Browser script errors: '+JSON.stringify(w.fixtureErrors));
  check(d.documentElement.scrollWidth<=w.innerWidth,'Horizontal list page overflow');
 }catch(error){failures.push(error.message)}
 document.querySelector('#metrics').textContent=JSON.stringify({checks,requests,failures});
},400);
"""
        page = f'<!doctype html><html><body><iframe src="{origin}/shops/order?order=total&sort=DESC" style="width:{width}px;height:1400px;border:0"></iframe><pre id="metrics"></pre><script>{script}</script></body></html>'
        (work / 'order-browser.html').write_text(page)
        with tempfile.TemporaryDirectory(prefix='dewawi-order-chromium-') as profile:
            result = subprocess.run([chrome, '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', f'--user-data-dir={profile}', f'--window-size={max(width,800)},1500', '--virtual-time-budget=18000', '--dump-dom', origin + '/order-browser.html'], capture_output=True, text=True, timeout=45)
        found = re.findall(r'<pre id="metrics">(.*?)</pre>', result.stdout, re.S)
        check(result.returncode == 0 and bool(found) and bool(found[-1]), 'Chromium did not complete list interactions')
        metrics = json.loads(html.unescape(found[-1]))
        check(not metrics['failures'], f"{width}px list: " + '; '.join(metrics['failures']))
        checks += metrics['checks']
    status, text, _ = request('/view?contactquery=Changed')
    csrf = FormInputs(text).tokens[0]
    check(request(convert_url, {'csrf': csrf, 'contactid': fixtures['foreign_contact']})[0] == 200, 'Foreign customer rejection did not return review page')
    status, linked, _ = request(convert_url, {'csrf': csrf, 'contactid': fixtures['contact']})
    check(status == 200 and '/sales/salesorder/' in linked and 'name="contactid"' not in linked, 'Conversion did not create a linked draft')
    check(request(convert_url, {'csrf': csrf, 'contactid': fixtures['contact']})[0] == 200, 'Conversion replay failed')
    # Exercise explicit native actions with the existing submit guard and real SQL history.
    for width in (1280, 390, 320):
        order_id = fixtures['browser'][str(width)]
        script = r"""
const frame=document.querySelector('iframe');
frame.onload=()=>{frame.onload=null;setTimeout(async()=>{
 let d=frame.contentDocument,w=frame.contentWindow,$=w.jQuery;const failures=[];let checks=0;
 function check(v,m){checks++;if(!v)throw new Error(m)}
 const wait=ms=>new Promise(r=>setTimeout(r,ms));
 async function until(fn,phase){for(let i=0;i<100;i++){if(fn())return;await wait(50)}throw new Error(phase+' navigation did not complete: '+frame.contentWindow.location.href+' | '+frame.contentDocument.body.textContent.slice(0,250))}
 try{
  check(d.documentElement.scrollWidth<=w.innerWidth,'Detail overflow: '+JSON.stringify([...d.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>w.innerWidth+1).slice(0,8).map(e=>[e.tagName,e.className,e.getBoundingClientRect().right])));
  check($('#order-contact').val()==='','A contact was selected automatically');
  check($('#order-status').val()==='','A state transition was selected automatically');
  check(d.querySelector('[role="region"][tabindex="0"]'),'Positions cannot receive keyboard focus');
  const state=d.querySelector('#shop-order-state');
  state.requestSubmit();check(!$(state).data('dw-submit-complete'),'Invalid state submission was allowed');
  $('#order-contact-query').val('Unsubmitted search').trigger('input');
  $('#order-status').val('in_progress').trigger('change');
  check(!d.querySelector('[data-autosave-unsaved]'),'Explicit order fields triggered auto-save');
  const previous=d;
  state.requestSubmit();state.requestSubmit();
  check($(state).attr('aria-busy')==='true'&&$(state).find(':submit').prop('disabled'),'Status action has no submit lock');
  await until(()=>frame.contentDocument!==previous&&frame.contentDocument.querySelector('#shop-order-state'),'status');
  await wait(150);d=frame.contentDocument;w=frame.contentWindow;$=w.jQuery;
  check(!$('#order-status option[value="in_progress"]').length,'Current status is offered as a new transition');
  check($('#order-contact').val()==='','Status change selected a contact automatically');
  const beforeSearch=d;
  $('#order-contact-query').val('Changed');
  d.querySelector('.dw-order-contact-search').requestSubmit();
  await until(()=>frame.contentDocument!==beforeSearch&&frame.contentDocument.querySelector('#order-contact option[value="CONTACT_ID"]'),'contact search');
  await wait(150);d=frame.contentDocument;w=frame.contentWindow;$=w.jQuery;
  check($('#order-contact').val()==='','Contact search selected a customer automatically');
  $('#order-contact').val('CONTACT_ID').trigger('change');
  check(!d.querySelector('[data-autosave-unsaved]'),'Contact choice triggered auto-save');
  const transfer=d.querySelector('#shop-order-transfer'),before=d;
  transfer.requestSubmit();transfer.requestSubmit();
  check($(transfer).find(':submit').prop('disabled'),'Transfer action has no submit lock');
  await until(()=>frame.contentDocument!==before&&frame.contentDocument.querySelector('a[href*="/sales/salesorder/"]'),'transfer');
  await wait(150);d=frame.contentDocument;w=frame.contentWindow;$=w.jQuery;
  check(!d.querySelector('#shop-order-transfer'),'Linked order still offers another transfer');
  check(d.querySelectorAll('.dw-order-history li').length===3,'Native actions duplicated or lost status history');
  check(!$('#order-status option[value="in_progress"]').length,'Linked order can revert to in-progress');
  check(d.body.textContent.includes('Original buyer GmbH')&&d.body.textContent.includes('ORIGINAL-SKU'),'Transfer replaced the immutable snapshot');
  check(d.documentElement.scrollWidth<=w.innerWidth,'Transferred detail overflow');
 }catch(error){failures.push(error.message)}
 document.querySelector('#metrics').textContent=JSON.stringify({checks,failures});
},350)};
""".replace('CONTACT_ID', str(fixtures['contact']))
        page = f'<!doctype html><html><body><iframe src="{origin}/view?id={order_id}&contactquery=Changed" style="width:{width}px;height:1800px;border:0"></iframe><pre id="metrics"></pre><script>{script}</script></body></html>'
        (work / 'order-browser.html').write_text(page)
        with tempfile.TemporaryDirectory(prefix='dewawi-order-operations-') as profile:
            result = subprocess.run([chrome, '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', f'--user-data-dir={profile}', f'--window-size={max(width,800)},1500', '--virtual-time-budget=14000', '--dump-dom', origin + '/order-browser.html'], capture_output=True, text=True, timeout=45)
        found = re.findall(r'<pre id="metrics">(.*?)</pre>', result.stdout, re.S)
        check(result.returncode == 0 and bool(found) and bool(found[-1]), 'Chromium did not complete operational order actions')
        metrics = json.loads(html.unescape(found[-1]))
        check(not metrics['failures'], f"{width}px operations: " + '; '.join(metrics['failures']))
        checks += metrics['checks']
        events = (work / 'order-write-events.log').read_text().splitlines()
        check(events.count(str(order_id) + ':state') == 1 and events.count(str(order_id) + ':createsalesorder') == 1, 'Double native submit sent duplicate mutations')
    print(f'PASS: {checks} order HTTP, CSRF, permission, legacy and responsive browser checks')
finally:
    server.terminate()
    server.wait(timeout=10)
    log.close()
    if sys.exc_info()[0]:
        print((work / 'order-http.log').read_text()[-9000:])
