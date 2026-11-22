"""Real shipping controller/forms and native browser actions on disposable fixtures."""
from pathlib import Path
import html, http.cookiejar, json, os, re, shutil, socket, subprocess, sys, tempfile, time
from html.parser import HTMLParser
import urllib.error, urllib.parse, urllib.request
ROOT = Path(__file__).resolve().parents[2]
work = Path(sys.argv[1]).resolve()
if not str(work).startswith('/tmp/dewawi-migrations-shipping-'):
    raise SystemExit('Isolated shipping database required')
fixtures = json.loads((work/'shipping-fixtures.json').read_text())
checks=0
def check(value, message):
    global checks
    checks+=1
    if not value: raise AssertionError(message)
with socket.socket() as sock:
    sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
origin=f'http://127.0.0.1:{port}'
jar=http.cookiejar.CookieJar()
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
class StopRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs): return None
no_redirect=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar),StopRedirect())
def request(path,data=None,follow=True):
    encoded=urllib.parse.urlencode(data,doseq=True).encode() if data is not None else None
    try:
        with (client if follow else no_redirect).open(urllib.request.Request(origin+path,data=encoded),timeout=15) as response:
            return response.status,response.read().decode(),response.headers
    except urllib.error.HTTPError as e: return e.code,e.read().decode(),e.headers
class Inputs(HTMLParser):
    def __init__(self,source):
        super().__init__();self.csrf='';self.feed(source)
    def handle_starttag(self,tag,attrs):
        attrs=dict(attrs)
        if tag=='input' and attrs.get('name')=='csrf':self.csrf=attrs.get('value','')
log=(work/'shipping-http.log').open('w')
server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(work),str(ROOT/'tests/security/fixtures/shop-shipping-http.php')],env=dict(os.environ,DEWAWI_SHIPPING_TEST_WORK=str(work)),stdout=log,stderr=log)
try:
    edit='/admin/shipping/edit?shopid=41&kind=classes'
    for _ in range(100):
        try:
            status,body,_=request(edit);break
        except urllib.error.URLError:time.sleep(.05)
    check(status==200,'Initial admin form failed')
    csrf=Inputs(body).csrf
    check(len(csrf)==64 and 'data-autosave="false"' in body,'Native explicit action token missing')
    check('SHIPPING_' not in body,'Untranslated shipping labels')
    check('dw-app-shell' in body and '/js/shipping.js' in body,'ERP shell or shipping script missing')
    for role in ['guest','reader','editor','denied']:
        check(request(edit+'&role='+role)[0]==403,'Unprivileged admin read: '+role)
        check(request(edit+'&role='+role,{'csrf':csrf,'title':'forged','active':1})[0]==403,'Unprivileged admin write: '+role)
    check(request(edit+'&tenant=8')[0]==404,'Cross-tenant form accessible')
    check(request(edit+'&tenant=9')[0]==403,'Shared items gave tariff admin rights')
    check(request(edit+'&tenant=9&share_admin=1')[0]==200,'Explicit admin sharing denied')
    check(request(edit,{'csrf':'wrong','title':'forged'})[0]==403,'Invalid CSRF accepted')
    check(request('/admin/shipping/delete?shopid=41&id=1',{'csrf':csrf})[0]==404,'Delete action available')
    check(request('/admin/shipping/copy?shopid=41&id=1',{'csrf':csrf})[0]==404,'Unvalidated copy available')
    check(request(edit,{'csrf':csrf,'title':'Injected','clientid':8})[0]==200,'Invalid input did not produce reviewable form')
    status,body,_=request(edit,{'csrf':csrf,'title':'HTTP class','active':1,'ordering':3,'combine_same_class':0})
    check(status==200 and 'HTTP class' in body and 'dw-alert--success' in body,'Native class create/PRG failed')
    status,body,_=request('/admin/shipping/index?shopid=41&kind=classes&q=HTTP')
    check(status==200 and 'HTTP class' in body and 'Parcel' not in body,'Server-side search failed')
    status,body,_=request('/admin/shipping/index?shopid=41&kind=classes&status=0')
    check(status==200 and 'HTTP class' not in body,'Active filter failed')
    check(request(edit,{'csrf':csrf,'title':'','active':0})[0]==200,'Incomplete draft could not be saved')
    check('Unbenannter Eintrag' in request('/admin/shipping/index?shopid=41&kind=classes&status=0')[1],'Unnamed draft has no accessible edit link')
    for kind in ['zones','tariffs','combinations','assignments','settings']:
        status,body,_=request('/admin/shipping/edit?shopid=41&kind='+kind)
        check(status==200 and 'SHIPPING_' not in body,'Form failed or untranslated: '+kind)
    tariff='/admin/shipping/edit?shopid=41&kind=tariffs&id='+str(fixtures['tariff1'])
    status,body,_=request(tariff,{'csrf':csrf,'title':'Bad tiers','active':1,'classid':fixtures['parcel'],'zoneid':fixtures['zone'],'currency':'EUR','model':'weight','tiers[0][up_to]':'10','tiers[0][amount]':'12','tiers[1][up_to]':'5','tiers[1][amount]':'8'})
    check(status==200 and 'shipping-tiers' in body and 'role="alert"' in body,'Invalid tiers not explained')
    status,body,_=request(edit,{'csrf':csrf,'title[]':'invalid','active':1})
    check(status==200 and 'role="alert"' in body and 'Warning' not in body,'Malformed scalar input broke the form')
    status,body,_=request('/admin/shipping/edit?shopid=41&kind=zones',{'csrf':csrf,'title':'Nested','active':1,'countries[0][nested]':'DE'})
    check(status==200 and 'role="alert"' in body and 'Warning' not in body,'Malformed country selection broke the form')
    item_url='/items/item/shipping?id=101'
    for role in ['reader','denied','guest']:
        check(request(item_url+'&role='+role,{'csrf':csrf,'classid':fixtures['parcel'],'active':1})[0]==403,'Unauthorized item assignment: '+role)
    check(request(item_url+'&role=editor',{'csrf':'invalid'})[0]==403,'Item CSRF bypass')
    check(request(item_url+'&role=editor',{'csrf':csrf,'classid':fixtures['parcel'],'active':1,'packages_per_unit':0,'separate':0})[0]==422,'Invalid item form not reviewable')
    check(request(item_url+'&role=editor',{'csrf':csrf,'classid':fixtures['parcel'],'active':1,'packages_per_unit':1,'separate':0},follow=False)[0]==302,'Authorized item assignment failed')
    chrome=shutil.which('chromium') or shutil.which('google-chrome')
    check(bool(chrome),'Chromium unavailable')
    for locale in ['de_DE','en_US']:
        for width in [1280,390,320]:
            script=r'''
const frame=document.querySelector('iframe');frame.onload=()=>{frame.onload=null;setTimeout(async()=>{
 let d=frame.contentDocument,w=frame.contentWindow,$=w.jQuery,checks=0;const failures=[];
 const check=(v,m)=>{checks++;if(!v)throw Error(m)},wait=ms=>new Promise(r=>setTimeout(r,ms));
 try{
 check(d.documentElement.scrollWidth<=w.innerWidth,'Page overflow');
 check(!d.body.textContent.includes('SHIPPING_'),'Untranslated shipping label');
 const form=d.querySelector('[data-shipping-form]');
 check(form.getAttribute('data-autosave')==='false','Auto-save enabled for compound tariff');
 $('#shipping-title').val('Browser tariff WIDTH LOCALE');
 $('#shipping-classid').val('');$('#shipping-zoneid').val('');$('#shipping-currency').val('EUR');
 $('#shipping-model').val('weight').trigger('change');
 check(!d.querySelector('#shipping-tiers').hidden,'Weight tiers not shown');
 check(d.querySelector('[name="amount"]').disabled,'Unrelated model amount can submit');
 $('[data-shipping-tiers] input').eq(0).val('5');$('[data-shipping-tiers] input').eq(1).val('10');
 $('[data-shipping-add]').click();check($('[data-shipping-tiers] tr').length===2,'Add tier failed');
 check(d.activeElement.name==='tiers[1][up_to]','New tier cannot receive keyboard focus');
 $('[data-shipping-tiers] tr').last().find('input').eq(0).val('10');$('[data-shipping-tiers] tr').last().find('input').eq(1).val('18');
 $('[data-shipping-remove]').last().click();check($('[data-shipping-tiers] tr').length===1,'Remove tier failed');
 $('#shipping-active').prop('checked',false);
 const previous=d;form.requestSubmit();form.requestSubmit();
 check($(form).attr('aria-busy')==='true'&&$(form).find(':submit').prop('disabled'),'Native action has no duplicate guard');
 for(let i=0;i<100;i++){await wait(50);if(frame.contentDocument!==previous&&frame.contentDocument.querySelector('[data-shipping-form]'))break}
 d=frame.contentDocument;w=frame.contentWindow;$=w.jQuery;
 check($('#shipping-title').val()==='Browser tariff WIDTH LOCALE','Draft tariff was not persisted');
 check(d.querySelector('.dw-alert--success'),'Successful save has no confirmation');
 check(d.documentElement.scrollWidth<=w.innerWidth,'Saved form overflow');
 const token=d.querySelector('[name="csrf"]').value;
 const errorResponse=await w.fetch('/items/item/shipping?id=101&role=editor',{method:'POST',body:new URLSearchParams({csrf:token,classid:'PARCEL',active:'1',packages_per_unit:'0',separate:'0'})});
 check(errorResponse.status===422,'Item field error has wrong status');
 const beforeItem=d;frame.srcdoc=await errorResponse.text();
 for(let i=0;i<100;i++){await wait(50);if(frame.contentDocument!==beforeItem&&frame.contentDocument.querySelector('#item-shipping'))break}
 await wait(200);d=frame.contentDocument;w=frame.contentWindow;$=w.jQuery;
 check(d.querySelector('#shipping-packages_per_unit').getAttribute('aria-invalid')==='true','Item field error is not accessible');
 check(d.querySelector('[data-shipping-form]')&&d.querySelector('[data-shipping-form]').closest('form')===d.querySelector('[data-shipping-form]'),'Item assignment form is nested');
 check(d.documentElement.scrollWidth<=w.innerWidth,'Item assignment overflow');

 }catch(e){failures.push(e.message)}
 document.querySelector('#metrics').textContent=JSON.stringify({checks,failures});
},350)};
'''.replace('WIDTH',str(width)).replace('LOCALE',locale).replace('PARCEL',str(fixtures['parcel'])).replace('ZONE',str(fixtures['zone']))
            # A draft may retain unselected references without colliding with active tariff uniqueness.
            source=origin+'/admin/shipping/edit?shopid=41&kind=tariffs&locale='+locale
            (work/'shipping-browser.html').write_text('<!doctype html><iframe src="'+source+'" style="width:'+str(width)+'px;height:2000px;border:0"></iframe><pre id="metrics"></pre><script>'+script+'</script>')
            with tempfile.TemporaryDirectory(prefix='dewawi-shipping-chromium-') as profile:
                result=subprocess.run([chrome,'--headless','--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--user-data-dir='+profile,'--window-size=1400,2200','--virtual-time-budget=10000','--dump-dom',origin+'/shipping-browser.html'],capture_output=True,text=True,timeout=45)
            metrics=re.findall(r'<pre id="metrics">(.*?)</pre>',result.stdout,re.S)
            check(result.returncode==0 and metrics and metrics[-1],'Browser did not complete')
            data=json.loads(html.unescape(metrics[-1]));check(not data['failures'],str(width)+' '+locale+': '+'; '.join(data['failures']));checks+=data['checks']
    print(f'PASS: {checks} shipping HTTP, CSRF, admin/item rights, fields and desktop/mobile Chromium checks')
    subprocess.run([sys.executable, '-B', str(ROOT/'tests/shops/shop-go-live-http.py'), origin, str(work)], check=True)
finally:
    server.terminate();server.wait(timeout=10);log.close()
    if sys.exc_info()[0]:print((work/'shipping-http.log').read_text()[-5000:])
