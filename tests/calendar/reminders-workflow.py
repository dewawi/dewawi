"""Reminder HTTP/CSRF and real header interaction, with a deterministic polling clock."""
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor
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

def request(path, post=None, client=opener, method=None):
    data = urllib.parse.urlencode(post).encode() if post is not None else None
    try:
        response = client.open(urllib.request.Request(address+path, data=data, headers={'X-Requested-With': 'XMLHttpRequest'}, method=method), timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, response.read().decode(), response.headers

base = '/calendar/index'
endpoint = base+'/reminders?context=1:1'
input_data = json.loads((work/'reminder-fixture.json').read_text())['input']
status, page, _ = request(base+'/add?actor=1&tenant=1')
csrf = re.search(r'name="csrf_token"[^>]*value="([a-f0-9]+)"', page).group(1)
check('name="reminderminutes"' in page and 'data-calendar-reminders' in page, 'Reminder form/header absent')
status, english, _ = request(base+'/add?locale=en_US')
check(status == 200 and 'Calendar reminders' in english and 'No reminder' in english and '1 day before (24 hours)' in english, 'English reminder translations missing')
request(base+'/add?locale=de_DE')
post = dict(input_data, title='PRIVATE_REMINDER_TITLE', reminderminutes='0', csrf_token=csrf)
status, body, _ = request(base+'/add', post)
event_id = json.loads(body)['id']
status, body, headers = request(endpoint)
due = json.loads(body)
check(status == 200 and due['count'] == 1 and due['reminders'][0]['id'] == event_id, 'Due endpoint failed: '+body)
check('no-store' in headers.get('Cache-Control', '') and due['csrf_token'] == csrf, 'Reminder cache/token incorrect')
revision = due['reminders'][0]['revision']
status, _, headers = request(endpoint, method='PUT')
check(status == 405 and headers.get('Allow') == 'GET, POST', 'Unsupported reminder method accepted')
check(json.loads(request(endpoint+'&operation=done&id='+str(event_id)+'&revision='+str(revision))[1])['count'] == 1, 'GET changed reminder state')
action = dict(id=event_id, revision=revision, operation='done', csrf_token=csrf)
for invalid in [dict(csrf_token='wrong'), dict(operation='bad'), dict(revision='0'), dict(revision='1 OR 1=1'), dict(id='0'), dict(clientid='2'), dict(ownerid='2'), dict(operation='snooze', minutes='1'), dict(operation='snooze', minutes='5.0'), dict(operation='snooze', minutes='-5'), dict(operation='done', minutes='5')]:
    status, body, _ = request(endpoint, dict(action, **invalid))
    check(status == (403 if 'csrf_token' in invalid else 422) and 'PRIVATE_REMINDER_TITLE' not in body, 'Invalid reminder action accepted/leaked detail: '+body)
check(request(endpoint, {k:v for k,v in action.items() if k != 'csrf_token'})[0] == 403, 'Missing reminder CSRF bypass')
check(request(base+'/reminders?context=2:1')[0] == 403, 'Stale user context accepted')
check(request(base+'/reminders?context[]=1:1')[0] == 403, 'Malformed context accepted')
check(request(base+'/reminders')[0] == 403, 'Missing context accepted')
request(base+'?actor=2&tenant=1')
status, other_due, _ = request(base+'/reminders?context=2:1')
other_token = json.loads(other_due)['csrf_token']
check(status == 200 and 'PRIVATE_REMINDER_TITLE' not in other_due and json.loads(other_due)['count'] == 0, 'Foreign private reminder leaked')
status, body, _ = request(base+'/reminders?context=2:1', dict(action, csrf_token=other_token))
check(status == 409 and 'PRIVATE_REMINDER_TITLE' not in body, 'Foreign owner can acknowledge reminder')
check(request(base+'/reminders?context=2:1', action)[0] == 403, 'Reminder token crossed users')
request(base+'?actor=1&tenant=2')
status, foreign_due, _ = request(base+'/reminders?context=1:2')
check(status == 200 and json.loads(foreign_due)['count'] == 0, 'Reminder crossed selected tenants')
check(request(base+'/reminders?context=1:2', action)[0] == 403, 'Reminder token crossed selected tenants')
check(request(base+'/reminders?context=1:2&guest=1')[0] == 403, 'Guest can poll reminders')
request(base+'?actor=1&tenant=1')
status, body, _ = request(endpoint, dict(action, operation='snooze', minutes='15'))
check(status == 200 and json.loads(body)['count'] == 0, 'HTTP snooze failed')
check(request(endpoint, dict(action, operation='snooze', minutes='15'))[0] == 409, 'Repeated HTTP snooze applied twice')
check(request(base+'/edit/id/'+str(event_id), dict(post, title='Renamed reminder'))[0] == 200, 'HTTP text edit failed')
check(json.loads(request(endpoint)[1])['count'] == 0, 'Text edit lost snooze')
request(base+'/edit/id/'+str(event_id), dict(post, reminderminutes='5'))
due = json.loads(request(endpoint)[1])
check(due['count'] == 1, 'Changed reminder offset not rearmed')
action['revision'] = due['reminders'][0]['revision']
# Independent HTTP connections use the same authenticated session, as parallel tabs do.
cookie = '; '.join(c.name+'='+c.value for c in jar)
def confirm(_):
    client = urllib.request.build_opener()
    client.addheaders = [('Cookie', cookie)]
    return request(endpoint, action, client)[0]
with ThreadPoolExecutor(max_workers=4) as pool:
    statuses = list(pool.map(confirm, range(4)))
check(statuses.count(200) == 1 and statuses.count(409) == 3, 'Parallel HTTP confirmations duplicated')
check(json.loads(request(endpoint)[1])['count'] == 0, 'Done reminder reappeared')
request(base+'/delete/id/'+str(event_id), {'csrf_token':csrf})
check(json.loads(request(endpoint)[1])['count'] == 0, 'Deleted reminder reappeared')
print(f'PASS: {checks} reminder HTTP, CSRF, context, privacy and parallel-request checks')

setup = r'''
window.fixtureClock=1000000;window.fixtureHidden=false;window.fixtureOffline=false;window.fixtureCalls=[];window.fixtureStatus=200;window.fixtureContext=null;
Date.now=function(){return window.fixtureClock;};
Object.defineProperty(document,'hidden',{configurable:true,get:function(){return window.fixtureHidden;}});
Object.defineProperty(navigator,'onLine',{configurable:true,get:function(){return !window.fixtureOffline;}});
var timers={}, timerId=0;
window.setTimeout=function(callback,delay){var key=++timerId;timers[key]={callback:callback,due:fixtureClock+(delay||0)};return key;};
window.clearTimeout=function(key){delete timers[key];};
window.fixtureTick=function(ms){fixtureClock+=ms;Object.keys(timers).forEach(function(key){var timer=timers[key];if(timer && timer.due<=fixtureClock){delete timers[key];timer.callback();}});};
jQuery.ajaxTransport('+*',function(options){
 if(options.url.indexOf('/reminders')<0)return;
 return {send:function(headers,complete){fixtureCalls.push(options);var widget=document.querySelector('[data-calendar-reminders]');complete(fixtureStatus,fixtureStatus===200?'OK':'Unauthorized',{text:JSON.stringify({ok:true,context:fixtureContext||widget.getAttribute('data-context'),timezone:'Europe/Berlin',csrf_token:'fixture',count:0,reminders:[]})},'Content-Type: application/json');},abort:function(){}};
});
'''
script = r'''
(async function(){
 const result=document.getElementById('result'), frame=document.getElementById('workflow');
 const doc=()=>frame.contentDocument, win=()=>frame.contentWindow;let phase='create';
 function check(ok,message){if(!ok)throw new Error(message);}
 function load(action){return new Promise((resolve,reject)=>{const t=setTimeout(()=>reject(new Error('Navigation timeout')),12000);frame.onload=()=>{clearTimeout(t);resolve();};action();});}
 async function until(test){for(let i=0;i<100;i++){if(test())return;await new Promise(r=>setTimeout(r,25));}throw new Error('AJAX condition timeout ('+phase+'): '+doc().URL+' count='+doc().querySelector('[data-reminder-count]')?.textContent+' status='+doc().querySelector('[data-reminder-status]')?.textContent);}
 function count(){return +doc().querySelector('[data-reminder-count]').textContent;}
 try{
  await load(()=>frame.src='/calendar/index/add?actor=1&tenant=1');
  let form=doc().querySelector('[data-calendar-form]');
  check(form.querySelector('[name=reminderminutes]').value==='none','Existing default changed');
  const values=Object.assign({},INPUT,{title:'Browser reminder <script>bad()</scr'+'ipt>',reminderminutes:'5'});
  Object.keys(values).forEach(k=>{if(form.elements[k])form.elements[k].value=values[k];});
  await load(()=>form.querySelector('button[type=submit]').click());
  await until(()=>count()===1);
  check(doc().querySelector('.dw-reminders-panel').hidden,'Reminder opened automatically');
  win().fixtureMarker='same document';
  doc().querySelector('.dw-reminders-toggle').click();
  await until(()=>doc().querySelector('[data-reminder-action=snooze]') && !doc().querySelector('[data-reminder-action=snooze]').disabled);
  let panel=doc().querySelector('.dw-reminders-panel');
  check(panel.textContent.includes('Browser reminder <script>') && !panel.querySelector('script'),'Reminder title not escaped');
  const rect=panel.getBoundingClientRect();
  check(rect.left>=0 && rect.right<=frame.clientWidth && doc().documentElement.scrollWidth<=frame.clientWidth,'Reminder panel overflows: left='+rect.left+' right='+rect.right+' viewport='+frame.clientWidth+' page='+doc().documentElement.scrollWidth);
  phase='snooze';doc().querySelector('[data-reminder-action=snooze]').click();
  await until(()=>count()===0);
  check(win().fixtureMarker==='same document','Snooze reloaded page');
  await load(()=>doc().querySelector('a[href*="/edit/id/"]').click());
  form=doc().querySelector('[data-calendar-form]');
  check(form.querySelector('[name=reminderminutes]').value==='5','Reminder setting not restored');
  phase='reschedule';form.querySelector('[name=reminderminutes]').value='30';
  await load(()=>form.querySelector('button[type=submit]').click());
  await until(()=>count()===1);
  doc().querySelector('.dw-reminders-toggle').click();
  await until(()=>doc().querySelector('[data-reminder-id] a'));
  phase='event link';await load(()=>doc().querySelector('[data-reminder-id] a').click());
  check(doc().querySelector('.dw-calendar-detail').textContent.includes('Browser reminder'),'Reminder event link failed');
  await until(()=>count()===1);
  doc().querySelector('.dw-reminders-toggle').click();
  await until(()=>doc().querySelector('[data-reminder-action=done]') && !doc().querySelector('[data-reminder-action=done]').disabled);
  win().fixtureMarker='same document';
  phase='done';doc().querySelector('[data-reminder-action=done]').click();
  await until(()=>count()===0);
  check(win().fixtureMarker==='same document','Confirmation reloaded page');
  phase='reload';const current=win().location.href;
  await load(()=>frame.src=current);await until(()=>doc().querySelector('[data-calendar-reminders]') && !doc().querySelector('[data-calendar-reminders]').hasAttribute('aria-busy'));
  doc().querySelector('.dw-reminders-toggle').click();await until(()=>doc().querySelector('[data-reminder-status]').textContent.includes('Keine fälligen'));
  check(count()===0,'Confirmation did not survive reload');
  win().confirm=()=>true;
  await load(()=>doc().querySelector('[data-calendar-delete] button').click());
  phase='polling';const html=await(await fetch('/calendar/index?actor=1&tenant=1')).text();
  const injected=html.replace(/(<script src="[^"]*\/js\/calendar-reminders.js")/,'<script>'+SETUP+'</scr'+'ipt>$1');
  await load(()=>frame.srcdoc=injected);
  await until(()=>win().fixtureTick && win().jQuery.isReady);
  win().fixtureTick(0);
  check(win().fixtureCalls.length===1,'Initial poll missing');
  win().fixtureTick(60000);check(win().fixtureCalls.length===2,'Active polling interval wrong');
  win().fixtureHidden=true;win().jQuery(doc()).trigger('visibilitychange');
  win().fixtureTick(3600000);check(win().fixtureCalls.length===2,'Hidden page kept polling');
  win().fixtureHidden=false;win().jQuery(doc()).trigger('visibilitychange');win().fixtureTick(0);
  check(win().fixtureCalls.length===3,'Visible page did not resume');
  win().fixtureTick(300001);check(win().fixtureCalls.length===3,'Idle page kept polling');
  win().jQuery(doc()).trigger('keydown');win().fixtureTick(0);check(win().fixtureCalls.length===4,'Activity did not resume polling');
  win().fixtureOffline=true;win().jQuery(win()).trigger('offline');win().fixtureTick(120000);check(win().fixtureCalls.length===4,'Offline page kept polling');
  win().fixtureOffline=false;win().jQuery(win()).trigger('online');win().fixtureTick(0);check(win().fixtureCalls.length===5,'Online page did not resume');
  win().jQuery(win()).trigger('pagehide');win().fixtureTick(60000);check(win().fixtureCalls.length===5,'Page cache kept polling');
  win().jQuery(win()).trigger('pageshow');win().fixtureTick(0);check(win().fixtureCalls.length===6,'Page cache restore did not resume');
  win().fixtureStatus=409;win().fixtureTick(60000);check(win().fixtureCalls.length===7,'GET conflict caused immediate retry loop');
  win().fixtureTick(60000);check(win().fixtureCalls.length===7,'Network backoff missing');
  win().fixtureStatus=200;win().fixtureTick(60000);check(win().fixtureCalls.length===8,'Network retry did not recover');
  win().Dewawi.sessionExpired=true;win().fixtureTick(60000);check(win().fixtureCalls.length===8,'Expired session kept polling');
  win().Dewawi.sessionExpired=false;win().fixtureStatus=401;
  win().jQuery(doc()).trigger('keydown');win().fixtureTick(0);
  check(win().fixtureCalls.length===9 && !win().Dewawi.sessionExpired,'Background 401 triggered global reload handling');
  win().fixtureStatus=200;win().jQuery(doc()).trigger('keydown');win().fixtureTick(60000);
  check(win().fixtureCalls.length===9 && count()===0,'401 did not stop and clear reminders');
  check(win().fixtureCalls.every(o=>o.dewawiBackground===true),'Background request convention missing');
  await load(()=>frame.srcdoc=injected);await until(()=>win().fixtureTick && win().jQuery.isReady);win().fixtureTick(0);
  win().fixtureContext='99:99';win().fixtureTick(60000);
  check(win().fixtureCalls.length===2 && count()===0,'Stale-context response accepted');
  win().jQuery(doc()).trigger('keydown');win().fixtureTick(60000);check(win().fixtureCalls.length===2,'Stale context kept polling');
  result.textContent='PASS: reminder settings, header badge, explicit dropdown, escaping, event link, AJAX snooze/done, persistence, responsive layout and active/hidden/idle/session polling';
 }catch(error){result.textContent='FAIL: '+error.message;}
})();
'''
template = '<!doctype html><meta charset="utf-8"><style>body{margin:0}iframe{height:1000px;border:0}pre{white-space:pre-wrap}</style><pre id="result">PENDING</pre><iframe id="workflow" style="width:WIDTHpx"></iframe><script>const INPUT='+json.dumps(input_data)+';const SETUP='+json.dumps(setup)+';'+script+'</script>'
for width in [1280, 390]:
    (work/'browser.html').write_text(template.replace('WIDTH', str(width)))
    browser = subprocess.run(['chromium', '--headless', '--no-sandbox', '--disable-gpu', '--disable-background-networking', '--no-proxy-server', '--virtual-time-budget=35000', '--dump-dom', '--user-data-dir='+str(work/f'reminder-chromium-{width}'), address+'/browser.html'], capture_output=True, text=True, timeout=60)
    match = re.search(r'<pre id="result">(.*?)</pre>', browser.stdout, re.S)
    check(match and match.group(1).startswith('PASS:'), f'Reminder browser {width}: '+(match.group(1) if match else browser.stdout[-2000:]))
    print(f'{width}px: {match.group(1)}')
