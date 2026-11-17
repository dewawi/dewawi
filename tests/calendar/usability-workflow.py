"""K4 checks against real controllers and desktop/mobile forms, with loopback SMTP only."""
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

def request(path, post=None, ajax=True, client=opener):
    data = urllib.parse.urlencode(post, doseq=True).encode() if post is not None else None
    try:
        response = client.open(urllib.request.Request(address+path, data=data, headers={'X-Requested-With':'XMLHttpRequest'} if ajax else {}), timeout=25)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, response.read().decode()

def token(page):
    return re.search(r'name="csrf_token"[^>]*value="([a-f0-9]+)"', page).group(1)

base = '/calendar/index'
csrf = token(request(base+'/add?actor=1&tenant=1&locale=de_DE')[1])
input_data = dict(title='K4 HTTP private event', description='K4_PRIVATE_DESCRIPTION', location='K4_PRIVATE_LOCATION', start='2026-11-17T16:00', end='2026-11-17T17:00', timezone='Europe/Berlin', visibility='private')
event_id = json.loads(request(base+'/add', dict(input_data, csrf_token=csrf))[1])['id']
suffix = '/id/'+str(event_id)

def event():
    return json.loads(request(base+'/get'+suffix)[1])['events'][0]

def add(**values):
    return request(base+'/participants'+suffix, dict(csrf_token=csrf, sequence=event()['sequence'], operation='add', **values))

before = set(work.glob('smtp-*.eml'))
status, body = add(kind='internal', userid='2', sendinvite='0')
check(status == 200 and set(work.glob('smtp-*.eml')) == before, 'Unchecked internal addition sent email')
status, body = add(kind='external', name='Unchecked external', email='unchecked@example.invalid', sendinvite='0')
check(status == 200 and set(work.glob('smtp-*.eml')) == before, 'Unchecked external addition sent email')
status, body = add(kind='external', name='CRM address', email='contact101@example.invalid', sendinvite='1')
data = json.loads(body)
check(status == 200 and data['mail']['sent'] == 1 and len(set(work.glob('smtp-*.eml'))-before) == 1, 'Checked contact addition failed: '+body)
check(data['participants'][-1]['contactemailid'] == 101 or data['participants'][-1]['contactemailid'] == '101', 'Manual contact not resolved')
for values, text in [
    [dict(kind='external', name='Bad', email='bad'), 'gültige E-Mail-Adresse'],
    [dict(kind='external', name='', email='missing-name@example.invalid'), 'Namen mit 1 bis 255'],
    [dict(kind='internal', userid='3'), 'zulässigen aktiven Benutzer'],
    [dict(kind='external', name='Already invited', email='member2@example.invalid'), 'bereits eingetragen'],
    [dict(kind='external', name='Organizer', email='member1@example.invalid'), 'Organisator'],
]:
    status, body = add(**values)
    check(status == 422 and text in json.loads(body)['message'], 'Specific validation absent: '+body)
    check('K4_PRIVATE_' not in body, 'Validation response leaked event details')
# Native validation retains the form, input and explicit opt-out.
post = dict(csrf_token=csrf, sequence=event()['sequence'], operation='add', kind='external', name='Preserved name', email='invalid-address', sendinvite='0')
status, page = request(base+'/participants'+suffix, post, ajax=False)
check(status == 422 and 'value="Preserved name"' in page and 'value="invalid-address"' in page and 'data-mail-choice="0"' in page and 'gültige E-Mail-Adresse' in page, 'Native validation lost form/input')
check(request(base+'/participants', post, ajax=False)[0] == 422, 'Native validation rendered an unavailable event')
for recipient in ['failure@example.invalid','uncertain@example.invalid']:
    commands_before = (work/'smtp-commands.txt').read_text()
    sequence = event()['sequence']
    post = dict(csrf_token=csrf, sequence=sequence, operation='add', kind='external', name='Transport fixture', email=recipient, sendinvite='1')
    status, body = request(base+'/participants'+suffix, post)
    check(status == 200 and json.loads(body)['ok'] and json.loads(body)['mail']['failed'] == 1 and 'nicht für alle' in json.loads(body)['message'], 'Saved participant/SMTP failure not explained: '+body)
    check(any(p['email'] == recipient for p in json.loads(body)['participants']), 'SMTP failure lost participant')
    commands_after = (work/'smtp-commands.txt').read_text()
    check(commands_after != commands_before, 'SMTP failure fixture not exercised')
    check(request(base+'/participants'+suffix, post)[0] == 409 and (work/'smtp-commands.txt').read_text() == commands_after, 'Repeated unknown/rejected add retried SMTP')
smtp_config = (work/'smtp.json').read_text()
try:
    (work/'smtp.json').write_text(json.dumps(dict(json.loads(smtp_config), disabled=True)))
    status, page = request(base+'/participants'+suffix, dict(csrf_token=csrf, sequence=event()['sequence'], operation='add', kind='external', name='Config error', email='config-error@example.invalid', sendinvite='1'), ajax=False)
    check(status == 200 and 'Config error' in page and 'Teilnehmer bleibt gespeichert' in page and 'SMTP-Zugang' in page, 'Native SMTP preparation failure lost participant/warning')
finally:
    (work/'smtp.json').write_text(smtp_config)
# Repeated authenticated AJAX requests cannot add or send twice.
post = dict(csrf_token=csrf, sequence=event()['sequence'], operation='add', kind='external', name='Parallel HTTP', email='parallel-http@example.invalid', sendinvite='1')
cookie = '; '.join(c.name+'='+c.value for c in jar)
def parallel(_):
    client = urllib.request.build_opener(); client.addheaders=[('Cookie',cookie)]
    return request(base+'/participants'+suffix, post, client=client)[0]
before = set(work.glob('smtp-*.eml'))
with ThreadPoolExecutor(max_workers=4) as pool:
    statuses = list(pool.map(parallel, range(4)))
check(statuses.count(200) == 1 and statuses.count(409) == 3 and len(set(work.glob('smtp-*.eml'))-before) == 1, 'Parallel HTTP add/send duplicated')
shift = dict(start=input_data['start'], end=input_data['end'], newstart='2026-11-18T16:00', timezone='Europe/Berlin', csrf_token=csrf)
snapshot = event()
check(request(base+'/endtime')[0] == 405, 'Date calculation GET accepted')
check(request(base+'/endtime', dict(shift, csrf_token='wrong'))[0] == 403, 'Date calculation CSRF bypass')
status, body = request(base+'/endtime', shift)
check(status == 200 and json.loads(body)['end'] == '2026-11-18T17:00' and event() == snapshot, 'Date suggestion mutated saved appointment')
check(request(base+'/endtime', dict(shift, ownerid='2'))[0] == 422, 'Unexpected time input accepted')
check(request(base+'/endtime', dict(shift, **{'newstart[]':'invalid'}))[0] == 422, 'Malformed time input accepted')
check(request(base+'/endtime', dict(shift, newstart='2026-03-29T02:30'))[0] == 422, 'DST gap suggestion accepted')
request(base+'?actor=2&tenant=1')
invite_csrf = token(request(base+'/view'+suffix)[1])
status, body = request(base+'/participants'+suffix, dict(post, csrf_token=invite_csrf, sequence=snapshot['sequence']))
check(status == 404 and 'K4_PRIVATE_' not in body, 'Invitee gained add/send permissions')
request(base+'?actor=3&tenant=2')
foreign_csrf = token(request(base+'/add')[1])
status, body = request(base+'/participants'+suffix, dict(post, csrf_token=foreign_csrf))
check(status == 404 and 'K4_PRIVATE_' not in body, 'Cross-tenant add/send accepted')
request(base+'?actor=5&tenant=1')
readonly_csrf = token(request(base+'/add')[1]) if request(base+'/add')[0] == 200 else json.loads(request(base+'/reminders?context=5:1')[1])['csrf_token']
check(request(base+'/endtime', dict(shift, csrf_token=readonly_csrf))[0] == 403, 'Read-only user used editing helper')
edit_id = json.loads((work/'usability-fixture.json').read_text())['edit_only_id']
request(base+'?actor=6&tenant=1')
status, page = request(base+'/view/id/'+str(edit_id))
edit_csrf = token(page)
check(status == 200 and 'data-calendar-send-on-add' not in page, 'Send checkbox bypassed existing send permission')
status, body = request(base+'/participants/id/'+str(edit_id), dict(csrf_token=edit_csrf, sequence='0', operation='add', kind='external', name='Allowed without mail', email='edit-only@example.invalid', sendinvite='1'))
check(status == 403, 'Edit-only owner sent invitation')
check(request(base+'/participants/id/'+str(edit_id), dict(csrf_token=edit_csrf, sequence='0', operation='add', kind='external', name='Allowed without mail', email='edit-only@example.invalid', sendinvite='0'))[0] == 200, 'Edit-only owner cannot add without email')
request(base+'?actor=1&tenant=1')
request(base+'/delete'+suffix, {'csrf_token':csrf})
print(f'PASS: {checks} usability HTTP, explicit send, failures, replay, validation, CSRF and permission checks')

script = r'''
(async function(){
 const result=document.getElementById('result'),frame=document.getElementById('workflow');
 const doc=()=>frame.contentDocument,win=()=>frame.contentWindow;let phase='create';
 function check(ok,message){if(!ok)throw new Error(message);}
 function load(action){return new Promise((resolve,reject)=>{const t=setTimeout(()=>reject(new Error('Navigation timeout '+phase)),12000);frame.onload=()=>{clearTimeout(t);resolve();};action();});}
 async function until(test){for(let i=0;i<160;i++){if(test())return;await new Promise(r=>setTimeout(r,25));}throw new Error('AJAX timeout '+phase);}
 function change(form,name,value){form.elements[name].value=value;win().jQuery(form.elements[name]).trigger('change');}
 async function shift(form,start,expected){change(form,'start',start);await until(()=>!win().jQuery(form).data('calendar-time-pending'));check(form.elements.end.value===expected,'Wrong end: '+form.elements.end.value+' expected '+expected+' status '+form.querySelector('[data-calendar-time-result]').textContent);}
 async function mailCount(){return +(await(await fetch('/mail-count')).text());}
 try{
  await load(()=>frame.src='/calendar/index/add?actor=1&tenant=1&locale=de_DE');
  let form=doc().querySelector('[data-calendar-form]');
  change(form,'end','2026-11-17T17:00');form.elements.start.value='2026-11-17T16:00';change(form,'end','2026-11-17T17:00');
  change(form,'timezone','Europe/Berlin');
  await shift(form,'2026-11-18T16:00','2026-11-18T17:00');
  await shift(form,'2026-11-18T20:00','2026-11-18T21:00');
  change(form,'end','2026-11-18T22:30');
  await shift(form,'2026-11-19T20:00','2026-11-19T22:30');
  change(form,'end','2026-11-21T22:30');
  await shift(form,'2026-11-20T20:00','2026-11-22T22:30');
  phase='DST';form.elements.start.value='2026-03-28T16:00';change(form,'end','2026-03-28T17:00');change(form,'startfold','');
  await shift(form,'2026-03-29T01:30','2026-03-29T03:30');
  form.elements.start.value='2026-10-24T16:00';change(form,'end','2026-10-24T17:00');form.elements.startfold.value='earlier';
  await shift(form,'2026-10-25T02:30','2026-10-25T02:30');
  check(form.elements.endfold.value==='later','Repeated-hour fold lost');
  phase='all-day';form.elements.start.value='2026-03-28T16:00';change(form,'end','2026-03-30T17:00');
  form.querySelector('[data-calendar-allday]').click();
  await shift(form,'2026-10-24','2026-10-26');
  form.querySelector('[data-calendar-allday]').click();
  phase='manual race';form.elements.start.value='2026-11-17T16:00';change(form,'end','2026-11-17T17:00');form.elements.startfold.value='';
  // Delay a real response to prove it cannot overwrite a later manual end edit.
  const originalAjax=win().jQuery.ajax;let delayed;
  win().jQuery.ajax=function(options){if(options.url.includes('/endtime')){delayed=win().jQuery.Deferred();return delayed.promise({abort:function(){}});}return originalAjax.apply(this,arguments);};
  change(form,'start','2026-11-18T16:00');change(form,'end','2026-11-18T19:00');delayed.resolve({ok:true,end:'2026-11-18T17:00',endfold:'earlier'});
  check(form.elements.end.value==='2026-11-18T19:00','Delayed suggestion overwrote manual end');
  win().jQuery.ajax=originalAjax;
  await shift(form,'2026-11-19T16:00','2026-11-19T19:00');
  form.elements.title.value='K4 browser '+WIDTH;form.elements.visibility.value='private';
  await load(()=>form.querySelector('button[type=submit]').click());
  const detail=win().location.pathname;
  phase='internal checkbox';form=doc().querySelector('[data-calendar-add-internal]');let checkbox=form.querySelector('[data-calendar-send-on-add]');
  check(!checkbox.checked && checkbox.disabled,'Checkbox active without an address');
  change(form,'userid','5');check(!checkbox.checked && checkbox.disabled,'User without email enabled invitation');
  check(!form.getAttribute('data-user-email-availability').includes('@'),'User email addresses exposed instead of availability');
  change(form,'userid','2');check(checkbox.checked && !checkbox.disabled,'Valid internal address not enabled by default');
  const countBefore=await mailCount();
  await load(()=>form.querySelector('button[type=submit]').click());
  check((await mailCount())===countBefore+1 && doc().querySelector('.dw-calendar-detail').textContent.includes('Versand abgeschlossen'),'Internal add did not send immediately');
  phase='external checkbox';form=doc().querySelector('[data-calendar-add-external]');checkbox=form.querySelector('[data-calendar-send-on-add]');
  change(form,'name','K4 external');change(form,'email','k4-external-'+WIDTH+'@example.invalid');
  check(checkbox.checked && !checkbox.disabled,'Valid external address not selected');
  checkbox.click();change(form,'email','k4-external-unchecked-'+WIDTH+'@example.invalid');
  check(!checkbox.checked,'Explicit opt-out was overwritten');
  await load(()=>form.querySelector('button[type=submit]').click());
  check((await mailCount())===countBefore+1,'Unchecked add sent email');
  phase='contact/manual editing';doc().querySelector('[data-calendar-contact-query]').value='CRM';doc().querySelector('[data-calendar-contact-button]').click();
  await until(()=>doc().querySelector('[name=contactemailid] option[value="101"]'));
  form=doc().querySelector('[data-calendar-add-external]');change(form,'contactemailid','101');
  check(form.querySelector('[data-calendar-send-on-add]').checked,'Contact invitation not selected');
  form.elements.email.value='edited-contact-'+WIDTH+'@example.invalid';win().jQuery(form.elements.email).trigger('input');
  check(form.elements.contactemailid.value==='0','Old contact ID survived manual email edit');
  await load(()=>form.querySelector('button[type=submit]').click());
  check((await mailCount())===countBefore+2,'Checked external add did not send');
  check(doc().documentElement.scrollWidth<=frame.clientWidth,'Participant controls overflow viewport');
  phase='edit';await load(()=>doc().querySelector('a[href*="/edit/id/"]').click());
  form=doc().querySelector('[data-calendar-form]');check(form.elements.end.value==='2026-11-19T19:00','Saved manual duration changed on load');
  await shift(form,'2026-11-20T16:00','2026-11-20T19:00');
  change(form,'end','2026-11-20T18:00');await load(()=>form.querySelector('button[type=submit]').click());
  check(doc().querySelector('.dw-calendar-detail').textContent.includes('2026-11-20 18:00'),'Manual end not saved');
  check(doc().querySelector('[data-calendar-mail]'),'Separate later dispatch removed');
  win().confirm=()=>true;await load(()=>doc().querySelector('[data-calendar-delete] button').click());
  result.textContent='PASS: default/unchecked internal and external invitations, contact editing, immediate mail, duration, multiday/all-day, DST, manual-end race, create/edit and responsive layout';
 }catch(error){result.textContent='FAIL: '+phase+': '+error.message;}
})();
'''
template = '<!doctype html><meta charset="utf-8"><style>body{margin:0}iframe{height:1500px;border:0}pre{white-space:pre-wrap}</style><pre id="result">PENDING</pre><iframe id="workflow" style="width:VIEWPORTpx"></iframe><script>const WIDTH=VIEWPORT;'+script+'</script>'
for width in [1280,390]:
    (work/'browser.html').write_text(template.replace('VIEWPORT',str(width)))
    browser = subprocess.run(['chromium','--headless','--no-sandbox','--disable-gpu','--disable-background-networking','--no-proxy-server','--virtual-time-budget=55000','--dump-dom','--user-data-dir='+str(work/f'usability-chromium-{width}'),address+'/browser.html'],capture_output=True,text=True,timeout=60)
    match = re.search(r'<pre id="result">(.*?)</pre>',browser.stdout,re.S)
    check(match and match.group(1).startswith('PASS:'),f'Usability browser {width}: '+(match.group(1) if match else browser.stdout[-1500:]))
    print(f'{width}px: {match.group(1)}')
