"""Real calendar controller, views and layout on the disposable MariaDB fixture."""
from pathlib import Path
import http.cookiejar
import json
import re
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request

work = Path(sys.argv[1]).resolve()
address = sys.argv[2]
if not re.fullmatch(r'/tmp/dewawi-calendar-[a-zA-Z0-9_]+', str(work)) or not re.fullmatch(r'http://127\.0\.0\.1:[0-9]+', address):
    raise RuntimeError('Only the disposable local calendar fixture is accepted')
jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
checks = 0

def check(ok, message):
    global checks
    checks += 1
    if not ok:
        raise AssertionError(message)

def request(path, values=None, ajax=False):
    data = urllib.parse.urlencode(values, doseq=True).encode() if values is not None else None
    headers = {'X-Requested-With': 'XMLHttpRequest'} if ajax else {}
    try:
        response = opener.open(urllib.request.Request(address + path, data=data, headers=headers), timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, response.read().decode(), response.headers

def token(html):
    return re.search(r'name="csrf_token"[^>]*value="([a-f0-9]+)"', html).group(1)

fixture = json.loads((work/'events.json').read_text())
base = '/calendar/index'
status, page, headers = request(base + '/add?actor=1&tenant=1')
check(status == 200 and 'data-autosave="false"' in page, 'Create form unavailable: ' + page)
csrf = token(page)
check('private, no-store' in headers.get('Cache-Control', ''), 'Calendar cache can expose data')
post = dict(title='HTTP event <script>throw 1</script>', description='HTTP description', location='Room', start='2026-11-16T10:00', end='2026-11-16T11:00', timezone='Europe/Berlin', visibility='team', csrf_token=csrf)
check(request(base + '/add', dict(post, csrf_token='wrong'), True)[0] == 403, 'Create CSRF bypass')
check(request(base + '/add', {k:v for k,v in post.items() if k != 'csrf_token'}, True)[0] == 403, 'Missing CSRF bypass')
status, body, _ = request(base + '/add', post, True)
check(status == 200, 'HTTP create failed: ' + body)
event_id = json.loads(body)['id']
status, detail, _ = request(base + '/view/id/' + str(event_id))
check(status == 200 and '&lt;script&gt;throw 1&lt;/script&gt;' in detail and '<script>throw 1</script>' not in detail, 'HTML escaping failed')
check('/calendar/index/edit/id/' + str(event_id) in detail, 'Edit link lost calendar module')
check(request(base + '/edit/id/' + str(event_id), dict(post, title='HTTP updated'), True)[0] == 200, 'HTTP edit failed')
status, body, _ = request(base + '/get/id/' + str(event_id))
check(status == 200 and json.loads(body)['events'][0]['title'] == 'HTTP updated', 'JSON detail failed')
for bad in [dict(title=''), dict(start='2026-02-30T10:00'), dict(ownerid='2'), dict(clientid='2'), dict(start='2026-03-29T02:30'), dict(start='2026-10-25T02:30', end='2026-10-25T03:30')]:
    status, body, _ = request(base + '/edit/id/' + str(event_id), dict(post, **bad), True)
    check(status == 422 and not json.loads(body)['ok'] and 'HTTP updated' not in body, 'Invalid HTTP input accepted/exposed details')
status, invalid, _ = request(base + '/edit/id/' + str(event_id), dict(post, title=''))
check(status == 422 and 'role="alert"' in invalid and 'name="start"' in invalid, 'Validation form not preserved')
check(request(base + '/delete/id/' + str(event_id))[0] == 405, 'Delete GET changes state')
check(request(base + '/delete/id/' + str(event_id), {'csrf_token':'wrong'}, True)[0] == 403, 'Delete CSRF bypass')
check(request(base + '/edit/id/' + str(event_id), dict(post, csrf_token='wrong'), True)[0] == 403, 'Edit CSRF bypass')
check(request(base + '/get?date[]=2026-11-16')[0] == 422, 'Array date accepted')
check(request(base + '/get?date=2026-11-16&ownerid=4')[0] == 422, 'Foreign member accepted')
check(request(base + '/get?date=2026-11-16&ownerid=1%20OR%201=1')[0] == 422, 'Invalid owner filter accepted')
check(request(base + '/get?date=2026-11-16&mode=year')[0] == 422, 'Invalid range accepted')
request(base + '?actor=2&tenant=1')
status, other_detail, _ = request(base + '/view/id/' + str(event_id))
check(status == 200 and 'HTTP updated' in other_detail and '/edit/id/' not in other_detail and 'data-calendar-delete' not in other_detail, 'Other owner sees write controls')
status, body, _ = request(base + '/get/id/' + str(fixture['private']))
check(status == 200 and json.loads(body)['events'][0]['private'], 'Private busy interval missing')
for action in ['view', 'get', 'edit']:
    status, body, _ = request(base + '/' + action + '/id/' + str(fixture['private']))
    check(status == (403 if action == 'edit' else 200), 'Private route status differs: ' + action)
    check(all(secret not in body for secret in ['PRIVATE_TITLE', 'PRIVATE_DESCRIPTION', 'PRIVATE_LOCATION']), 'Private detail leaked in ' + action)
status, body, _ = request(base + '/get?date=2026-03-28&mode=month&keyword=PRIVATE_TITLE')
check(status == 200 and 'PRIVATE_' not in body, 'Private list/search leaked content')
other_csrf = token(request(base + '/add')[1])
check(request(base + '/edit/id/' + str(event_id), dict(post, csrf_token=other_csrf), True)[0] == 403, 'Other owner write bypass')
check(request(base + '/delete/id/' + str(event_id), {'csrf_token':other_csrf}, True)[0] == 403, 'Other owner deletion bypass')
check(request(base + '/add', dict(post, csrf_token=csrf), True)[0] == 403, 'CSRF reused across users')
request(base + '?actor=3&tenant=2')
for action in ['view', 'get', 'edit']:
    status, body, _ = request(base + '/' + action + '/id/' + str(fixture['private']))
    check(status == 404 and 'PRIVATE_' not in body, 'Foreign tenant route leaks data: ' + action)
foreign_csrf = token(request(base + '/add')[1])
check(request(base + '/delete/id/' + str(event_id), {'csrf_token':foreign_csrf}, True)[0] == 404, 'Foreign tenant delete')
check(request(base + '/get/id/' + str(event_id) + '?guest=1')[0] == 403, 'Guest endpoint bypass')
request(base + '?actor=1&tenant=2')
check(request(base + '/add', post, True)[0] == 403, 'CSRF reused across selected tenants')
request(base + '?actor=1&tenant=1')
check(request(base + '/delete/id/' + str(event_id), {'csrf_token':csrf}, True)[0] == 200, 'HTTP delete failed')
check(request(base + '/get/id/' + str(event_id))[0] == 404, 'Deleted event remains readable')
print(f'PASS: {checks} calendar HTTP/controller/privacy/CSRF checks')

script = r'''
(async function () {
 const result = document.getElementById('result'), frame = document.getElementById('workflow');
 const doc = () => frame.contentDocument;
 function check(ok, message) { if (!ok) throw new Error(message); }
 function load(action) { return new Promise((resolve,reject) => { const timer=setTimeout(()=>reject(new Error('Navigation timeout')),12000);frame.onload=()=>{clearTimeout(timer);resolve();};action(); }); }
 function overflow() { return doc().documentElement.scrollWidth > frame.contentWindow.innerWidth; }
 try {
   await load(()=>frame.src='/calendar/index?actor=1&tenant=1&date=2026-11-16&mode=month');
   check(doc().querySelectorAll('.dw-calendar-day').length===42,'Month grid missing: '+doc().URL+' count='+doc().querySelectorAll('.dw-calendar-day').length+' '+doc().body.textContent.slice(-250));
   check(!overflow(),'Month horizontal overflow');
   check(doc().querySelector('.dw-app-header') && doc().querySelector('.dw-topbar'),'Shared header/navigation missing');
   for (const mode of ['week','day','agenda']) {
     const link=Array.from(doc().querySelectorAll('.dw-calendar-toolbar a')).find(a=>new URL(a.href).searchParams.get('mode')===mode);
     await load(()=>link.click());
     check(doc().querySelectorAll('.dw-calendar-day').length===({week:7,day:1,agenda:30})[mode],mode+' view missing');
     check(!overflow(),mode+' overflow');
   }
   await load(()=>doc().querySelector('a[href$="/calendar/index/add"]').click());
   const form=doc().querySelector('[data-calendar-form]');check(form,'New event link wrong');
   form.querySelector('[name=title]').value='Browser event';
   form.querySelector('[name=description]').value='Browser description';
   form.querySelector('[name=start]').value='2026-11-16T10:00';form.querySelector('[name=end]').value='2026-11-16T11:00';
   const allDay=form.querySelector('[name=allday][type=checkbox]');allDay.click();
   check(form.querySelector('[name=start]').type==='date' && form.querySelector('[name=end]').value==='2026-11-17','All-day toggle did not set exclusive end');
   check(!overflow(),'Editor overflow');
   await load(()=>form.querySelector('button[type=submit]').click());
   check(doc().querySelector('.dw-calendar-detail').textContent.includes('Browser event'),'Browser create failed');
   const edit=doc().querySelector('a[href*="/edit/id/"]');await load(()=>edit.click());
   check(doc().querySelector('[name=start]').type==='date','All-day edit type lost');
   doc().querySelector('[name=allday][type=checkbox]').click();
   check(doc().querySelector('[name=start]').value==='2026-11-16T09:00','All-day to timed conversion broken');
   doc().querySelector('[name=title]').value='Browser edited';
   await load(()=>doc().querySelector('[data-calendar-form] button').click());
   check(doc().querySelector('.dw-calendar-detail').textContent.includes('Browser edited'),'Browser edit failed');
   frame.contentWindow.confirm=()=>false;
   doc().querySelector('[data-calendar-delete] button').click();
   check(doc().querySelector('.dw-calendar-detail'),'Delete confirmation ignored');
   frame.contentWindow.confirm=()=>true;
   await load(()=>doc().querySelector('[data-calendar-delete] button').click());
   check(doc().querySelector('.dw-calendar-grid'),'Browser delete did not return to calendar');
   await load(()=>frame.src='/calendar/index?actor=2&tenant=1&date=2026-03-28&mode=month');
   check(!doc().body.textContent.includes('PRIVATE_TITLE') && doc().body.textContent.includes('Belegt'),'Browser private content leaked');
   doc().querySelector('[name=ownerid]').value='2';
   await load(()=>doc().querySelector('form[method=get] button').click());
   check(!doc().querySelector('.dw-calendar-event'),'Employee filter ignored');
   result.textContent='PASS: calendar browser navigation, responsive views, all-day toggle, CRUD, employee filter and privacy';
 } catch (error) { result.textContent='FAIL: '+error.message; }
})();
'''
(work/'browser.html').write_text('<!doctype html><meta charset="utf-8"><style>body{margin:0}iframe{height:850px;border:0}pre{white-space:pre-wrap}</style><pre id="result">PENDING</pre><iframe id="workflow" style="width:WIDTHpx"></iframe><script>'+script+'</script>')
template = (work/'browser.html').read_text()
for width in [1280, 390]:
    (work/'browser.html').write_text(template.replace('WIDTH', str(width)))
    browser = subprocess.run(['chromium', '--headless', '--no-sandbox', '--disable-gpu', '--disable-background-networking', '--no-proxy-server', '--virtual-time-budget=25000', '--dump-dom', '--user-data-dir='+str(work/f'chromium-{width}'), address+'/browser.html'], capture_output=True, text=True, timeout=60)
    match = re.search(r'<pre id="result">(.*?)</pre>', browser.stdout, re.S)
    check(match and match.group(1).startswith('PASS:'), f'Browser {width}: '+(match.group(1) if match else browser.stdout[-2000:]))
    print(f'{width}px: {match.group(1)}')
