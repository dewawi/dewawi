"""Participant HTTP boundaries, real MIME delivery, and desktop/mobile interaction."""
from pathlib import Path
from email import policy
from email.parser import BytesParser
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
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
checks = 0

def check(ok, message):
    global checks
    checks += 1
    if not ok:
        raise AssertionError(message)

def request(path, post=None):
    data = urllib.parse.urlencode(post, doseq=True).encode() if post is not None else None
    try:
        response = opener.open(urllib.request.Request(address+path, data=data, headers={'X-Requested-With': 'XMLHttpRequest'}), timeout=25)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, response.read().decode(), response.headers

def token(page):
    return re.search(r'name="csrf_token"[^>]*value="([a-f0-9]+)"', page).group(1)

base = '/calendar/index'
input_data = json.loads((work/'participant-fixture.json').read_text())['input']
csrf = token(request(base+'/add?actor=1&tenant=1')[1])
before = set(work.glob('smtp-*.eml'))
status, body, _ = request(base+'/add', dict(input_data, csrf_token=csrf))
check(status == 200, 'Create failed: '+body)
event_id = json.loads(body)['id']
suffix = '/id/'+str(event_id)
sequence = 0

def event():
    return json.loads(request(base+'/get'+suffix)[1])['events'][0]

def action(endpoint, **values):
    return request(base+'/'+endpoint+suffix, dict(csrf_token=csrf, sequence=sequence, **values))

for name in ['participants', 'respond', 'cancel', 'send']:
    check(request(base+'/'+name+suffix)[0] == 405, 'GET mutation: '+name)
    check(request(base+'/'+name+suffix, dict(csrf_token='bad', sequence=0))[0] == 403, 'CSRF bypass: '+name)
status, body, _ = action('participants', operation='add', kind='internal', userid='2')
check(status == 200, 'Add internal failed: '+body)
internal = json.loads(body)['participants'][0]['id']
sequence = event()['sequence']
check(action('participants', operation='add', kind='internal', userid='2')[0] == 422, 'Duplicate accepted')
check(action('participants', operation='add', kind='internal', userid='3')[0] == 422, 'Foreign internal accepted')
check(action('participants', operation='add', kind='external', name='Unsafe', email='x@example.invalid', contactemailid='102')[0] == 422, 'Foreign contact accepted')
check(request(base+'/contacts'+suffix+'?keyword=C')[0] == 422, 'Unbounded contact search')
status, contacts, _ = request(base+'/contacts'+suffix+'?keyword=CRM')
check(status == 200 and [int(r['id']) for r in json.loads(contacts)['contacts']] == [101], 'Contact picker crossed tenants: '+contacts)
status, body, _ = action('participants', operation='add', kind='external', name='HTTP external <script>', email='http-external@example.invalid')
check(status == 200, 'External creation failed: '+body)
external = json.loads(body)['participants'][1]['id']
sequence = event()['sequence']
check(set(work.glob('smtp-*.eml')) == before, 'Participant creation automatically emailed')
check(action('send', **{'recipients[]':[external], 'locale':'en_US', 'ownerid':'2'})[0] == 422, 'Unexpected sender inputs accepted')
check(action('send', locale='en_US')[0] == 422, 'Implicit recipient list accepted')
status, page, _ = request(base+'/view'+suffix)
check(status == 200 and 'HTTP external &lt;script&gt;' in page and '<script>HTTP external' not in page, 'Participant escaping failed')
check('checked' not in re.findall(r'<input[^>]+name="recipients\[\]"[^>]*>', page)[0], 'Recipient preselected')
request(base+'?actor=2&tenant=1')
invite_csrf = token(request(base+'/view'+suffix)[1])
invitation = event()
check(invitation['participation'] == 'invited' and not invitation['private'] and 'reminderminutes' not in invitation, 'Private invite/reminder projection wrong')
check(request(base+'/respond'+suffix, dict(csrf_token=invite_csrf, sequence=sequence, status='accepted', participantid=external))[0] == 422, 'RSVP spoof input accepted')
check(request(base+'/respond'+suffix, dict(csrf_token=invite_csrf, sequence=sequence, status='accepted'))[0] == 200, 'Own RSVP failed')
check(event()['participation'] == 'accepted', 'RSVP not persistent')
for name, post in [('participants',dict(operation='remove',participantid=external)), ('cancel',{}), ('send',{'recipients[]':[external],'locale':'de_DE'})]:
    status, body, _ = request(base+'/'+name+suffix, dict(csrf_token=invite_csrf, sequence=sequence, **post))
    check(status == 404 and 'Private description' not in body, 'Invited user gained organizer action: '+name)
check(request(base+'/contacts'+suffix+'?keyword=CRM')[0] == 404, 'Invite can search organizer contacts')
request(base+'?actor=3&tenant=2')
foreign_csrf = token(request(base+'/add')[1])
for name in ['view','get','contacts']:
    status, body, _ = request(base+'/'+name+suffix+'?keyword=CRM')
    check(status == 404 and 'Private description' not in body, 'Foreign tenant leaked: '+name)
check(request(base+'/respond'+suffix, dict(csrf_token=foreign_csrf, sequence=sequence, status='declined'))[0] == 404, 'Cross-tenant RSVP accepted')
request(base+'?actor=1&tenant=1')
status, body, _ = action('send', **{'recipients[]':[external], 'locale':'en_US'})
check(status == 200 and json.loads(body)['result']['sent'] == 1, 'Explicit send failed: '+body)
check(action('send', **{'recipients[]':[external], 'locale':'en_US'})[0] == 200 and len(set(work.glob('smtp-*.eml'))-before) == 1, 'Repeated HTTP send duplicated')
status, body, _ = action('participants', operation='remove', participantid=external)
sequence = event()['sequence']
check(status == 200, 'Removal failed')
check(action('send', **{'recipients[]':[external], 'locale':'en_US'})[0] == 200, 'Explicit removal cancellation failed')
check(action('cancel')[0] == 200, 'Whole-event cancellation failed')
sequence = event()['sequence']
check(event()['cancelled'], 'Cancellation not persistent')
check(action('send', **{'recipients[]':[internal], 'locale':'de_DE'})[0] == 200, 'Whole-event cancellation email failed')
check(request(base+'/delete'+suffix, {'csrf_token':csrf})[0] == 200, 'Cleanup failed')

# Parse actual SMTP messages, including PHPMailer's calendar MIME alternative.
methods = set()
for path in work.glob('smtp-*.eml'):
    msg = BytesParser(policy=policy.default).parsebytes(path.read_bytes())
    check(not msg.defects and len(msg.get_all('To', [])) == 1 and not msg.get('Cc') and not msg.get('Bcc'), 'Malformed MIME or implicit recipients')
    alternatives = [part for part in msg.walk() if part.get_content_type() == 'text/calendar' and part.get_content_disposition() != 'attachment']
    attachments = [part for part in msg.iter_attachments() if part.get_filename() == 'invitation.ics']
    check(len(alternatives) == 1 and len(attachments) == 1, 'Calendar MIME alternative or .ics attachment absent')
    alt = alternatives[0].get_payload(decode=True).replace(b'\r\n', b'\n')
    ics = attachments[0].get_payload(decode=True).replace(b'\r\n', b'\n')
    method = alternatives[0].get_param('method')
    check(alt == ics and ('METHOD:'+method).encode() in ics, 'MIME calendar METHOD/attachment mismatch')
    check(b'UID:' in ics and b'DTSTAMP:' in ics and b'SEQUENCE:' in ics and ics.count(b'ATTENDEE;') == 1, 'Scheduling identity/selected attendees wrong')
    methods.add(method)
check(methods == {'REQUEST','CANCEL'}, 'MIME REQUEST/CANCEL not covered')
print(f'PASS: {checks} participant HTTP, CSRF, privacy, contact, explicit-mail and MIME checks')

script = r'''
(async function(){
 const result=document.getElementById('result'),frame=document.getElementById('workflow');
 const doc=()=>frame.contentDocument,win=()=>frame.contentWindow;let phase='create';
 function check(ok,message){if(!ok)throw new Error(message);}
 function load(action){return new Promise((resolve,reject)=>{const t=setTimeout(()=>reject(new Error('Navigation timeout '+phase)),12000);frame.onload=()=>{clearTimeout(t);resolve();};action();});}
 async function until(test){for(let i=0;i<150;i++){if(test())return;await new Promise(r=>setTimeout(r,25));}throw new Error('AJAX timeout '+phase);}
 try{
  await load(()=>frame.src='/calendar/index/add?actor=1&tenant=1&locale=de_DE');
  let form=doc().querySelector('[data-calendar-form]');
  Object.entries(Object.assign({},INPUT,{title:'Browser invitation <script>literal</scr'+'ipt>',reminderminutes:'none'})).forEach(([k,v])=>{if(form.elements[k])form.elements[k].value=v;});
  await load(()=>form.querySelector('button[type=submit]').click());
  const detail=win().location.pathname;
  check(doc().querySelectorAll('[name="recipients[]"]:checked').length===0,'Automatic email selection');
  phase='internal';form=doc().querySelector('[data-calendar-add-internal]');form.elements.userid.value='2';
  check(!Array.from(form.elements.userid.options).some(o=>o.value==='3'),'Foreign tenant user selectable');
  await load(()=>form.querySelector('button').click());
  phase='external';form=doc().querySelector('[data-calendar-add-external]');form.elements.name.value='Browser <script>external</scr'+'ipt>';form.elements.email.value='browser-'+WIDTH+'@example.invalid';
  await load(()=>form.querySelector('button[type=submit]').click());
  check(doc().querySelector('[data-calendar-participants]').textContent.includes('Browser <script>external</scr'+'ipt>'),'Participant escaping lost');
  check(doc().documentElement.scrollWidth<=frame.clientWidth,'Participant page overflows viewport');
  phase='contacts';doc().querySelector('[data-calendar-contact-query]').value='CRM';doc().querySelector('[data-calendar-contact-button]').click();
  await until(()=>doc().querySelector('[name=contactemailid] option[value="101"]'));
  form=doc().querySelector('[data-calendar-add-external]');form.elements.contactemailid.value='101';win().jQuery(form.elements.contactemailid).trigger('change');
  check(form.elements.email.value==='contact101@example.invalid','Contact selection did not fill address');
  await load(()=>form.querySelector('button[type=submit]').click());
  check(doc().querySelectorAll('[name="recipients[]"]:checked').length===0,'New recipients auto-selected');
  phase='invite';await load(()=>frame.src=detail+'?actor=2&tenant=1');
  check(doc().querySelector('h3').textContent.includes('Browser invitation <script>'),'Private invited detail absent');
  check(!doc().querySelector('[data-calendar-add-internal], [data-calendar-mail], [data-calendar-delete]'),'Invite gained organizer controls');
  check(!doc().querySelector('[data-calendar-participants]').textContent.includes('@example.invalid'),'Invite sees unrelated addresses');
  win().sameDocument=true;
  form=doc().querySelector('[data-calendar-response]');form.elements.status.value='accepted';form.querySelector('button').click();
  await until(()=>form.querySelector('[data-calendar-result]').textContent.includes('✓'));
  check(win().sameDocument===true,'RSVP reloaded document');
  form.elements.status.value='declined';form.querySelector('button').click();
  await until(()=>!form.querySelector('button').disabled);
  await load(()=>frame.src='/calendar/index?scope=invitations&date=2026-10-25&mode=day');
  check(doc().querySelector('.dw-calendar-event').textContent.includes('Abgelehnt'),'Invitation/status absent from personal calendar');
  phase='send';await load(()=>frame.src=detail+'?actor=1&tenant=1&locale=en_US');
  check(doc().querySelector('[data-calendar-participants]').textContent.includes('Declined'),'Organizer did not see response/English translations');
  form=doc().querySelector('[data-calendar-mail]');
  check(Array.from(form.querySelectorAll('[type=checkbox]')).every(c=>!c.checked),'Email selected automatically');
  form.querySelectorAll('[type=checkbox]')[1].checked=true;win().sameDocument=true;form.querySelector('button').click();
  await until(()=>form.querySelector('[data-calendar-result]').textContent.length>0 && !form.querySelector('button').disabled);
  check(win().sameDocument && form.querySelector('[data-calendar-result]').textContent.includes('Sending completed'),'AJAX email failed/reloaded: '+form.querySelector('[data-calendar-result]').textContent);
  phase='cancel';win().confirm=()=>true;await load(()=>doc().querySelector('[data-calendar-cancel] button').click());
  check(!doc().querySelector('[data-calendar-add-external]') && doc().querySelector('.dw-calendar-detail').textContent.includes('Event cancelled'),'Cancelled controls/state incorrect');
  form=doc().querySelector('[data-calendar-mail]');form.querySelectorAll('[type=checkbox]')[1].checked=true;form.querySelector('button').click();
  await until(()=>form.querySelector('[data-calendar-result]').textContent.length>0 && !form.querySelector('button').disabled);
  check(doc().documentElement.scrollWidth<=frame.clientWidth,'Cancellation page overflows');
  phase='delete';win().confirm=()=>true;await load(()=>doc().querySelector('[data-calendar-delete] button').click());
  result.textContent='PASS: internal/external/contact participants, private invitations, personal calendar, AJAX RSVP/email, explicit recipients, cancellation, escaping and responsive layout';
 }catch(error){result.textContent='FAIL: '+phase+': '+error.message;}
})();
'''
template = '<!doctype html><meta charset="utf-8"><style>body{margin:0}iframe{height:1400px;border:0}pre{white-space:pre-wrap}</style><pre id="result">PENDING</pre><iframe id="workflow" style="width:WIDTHpx"></iframe><script>const INPUT='+json.dumps(input_data)+';const WIDTH=VIEWPORT;'+script+'</script>'
for width in [1280,390]:
    (work/'browser.html').write_text(template.replace('WIDTHpx', str(width)+'px').replace('VIEWPORT', str(width)))
    browser = subprocess.run(['chromium','--headless','--no-sandbox','--disable-gpu','--disable-background-networking','--no-proxy-server','--virtual-time-budget=45000','--dump-dom','--user-data-dir='+str(work/f'participant-chromium-{width}'),address+'/browser.html'], capture_output=True, text=True, timeout=60)
    match = re.search(r'<pre id="result">(.*?)</pre>', browser.stdout, re.S)
    check(match and match.group(1).startswith('PASS:'), f'Participant browser {width}: '+(match.group(1) if match else browser.stdout[-1500:]))
    print(f'{width}px: {match.group(1)}')
