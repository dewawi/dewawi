#!/usr/bin/env python3
"""Rendered cart templates and real shops.js with deterministic AJAX fault fixtures; no application DB."""
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlsplit, parse_qs

sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('frontend', Path(__file__).with_name('frontend-assets-http.py'))
frontend = importlib.util.module_from_spec(spec)
spec.loader.exec_module(frontend)

SCRIPT = r'''
const frame=document.querySelector('iframe');
frame.addEventListener('load',()=>setTimeout(async()=>{
 const w=frame.contentWindow,d=w.document,$=w.jQuery;let checks=0,failures=[];
 function check(v,m){checks++;if(!v)failures.push(m)}
 const wait=ms=>new Promise(resolve=>setTimeout(resolve,ms));
 try {
 check(d.documentElement.scrollWidth<=w.innerWidth,'Horizontal overflow');
 check(d.querySelector('#cart').textContent.includes('Nettopreise'),'Net-price notice missing');
 check(!!d.querySelector('a[href="/site/page/99"]'),'Shipping link missing');
 check(!!d.querySelector('[role="status"][aria-live]'),'Accessible feedback missing');
 check(!d.querySelector('#cart img'),'Initial title was interpreted as HTML');
 if(MODE==='empty') {
  check(!d.querySelector('#cart-empty').hidden,'Empty message missing');
  check(d.querySelector('.cart-summary').hidden,'Empty cart offers actions');
 } else {
  const input=d.querySelector('.quantity-input');
  check(input.min==='0'&&input.max==='100000'&&input.step==='0.0001','Decimal quantity constraints missing');
  check(input.getAttribute('aria-label').includes('Wärmepumpe'),'Input has no accessible item name');
  check(d.querySelector('.cart-uom').textContent==='Meter','Unit missing');
  check(d.querySelector('.product-details a').getAttribute('href')==='/site/item/7','Product link missing');
  if(MODE==='disabled') check(!d.querySelector('#checkout-btn')&&d.querySelector('#cart').textContent.includes('derzeit nicht möglich'),'Disabled checkout not explained');
  else check(!!d.querySelector('#checkout-btn'),'Checkout missing');
  let server={success:true,cart:{7:{title:'Updated <img src=x>',sku:'P-7',price:19.5,quantity:1.25,uom:'Meter',total:24.38}},total:24.38,cartItemCount:1.25,revision:1,checkoutEnabled:MODE!=='disabled',message:'Saved'},requests=[],failNext='',getFail=false;
  $.ajax=function(options){
   const deferred=$.Deferred();requests.push(options);
   setTimeout(()=>{
    if(options.method!=='POST') {
     if(getFail)deferred.reject({});else deferred.resolve(JSON.parse(JSON.stringify(server)));
     return;
    }
    const op=options.url.split('/').pop();
    if(op==='update')server.cart[7].quantity=Number(options.data.quantity);
    if(op==='remove'||op==='clear'||(op==='update'&&Number(options.data.quantity)===0))server.cart={};
    server.revision++;server.cartItemCount=Object.values(server.cart).reduce((s,i)=>s+i.quantity,0);server.total=Math.round(server.cartItemCount*19.5*100)/100;Object.values(server.cart).forEach(i=>i.total=server.total);
    const response=JSON.parse(JSON.stringify(server));
    if(failNext==='conflict'){failNext='';response.success=false;response.message='Changed in another tab';deferred.reject({responseJSON:response,status:409});}
    else if(failNext==='lost'){failNext='';deferred.reject({status:0});}
    else deferred.resolve(response);
   },200);
   return deferred.promise();
  };
  $(input).val('-1').trigger('change');
  check(requests.length===0&&input.getAttribute('aria-invalid')==='true'&&!d.querySelector('#cart-feedback').hidden,'Invalid quantity failed silently');
  $(input).val('2.5000').trigger('change');
  $('.remove-item').trigger('click');$('#clear-cart').trigger('click');$(input).trigger('change');
  check(requests.length===1&&input.disabled&&d.querySelector('#cart-container').getAttribute('aria-busy')==='true','Parallel changes were not blocked');
  check(requests[0].url==='/site/cart/update'&&requests[0].data.csrf_token&&requests[0].data.revision===1,'Scoped endpoint or CSRF/revision missing');
  await wait(300);
  check($('.quantity-input').val()==='2.5'&&!$('.quantity-input').prop('disabled'),'Quantity response not rendered');
  check(d.activeElement===d.querySelector('.quantity-input'),'Keyboard focus lost after update');
  check(!d.querySelector('#cart img')&&d.querySelector('.product-title').textContent==='Updated <img src=x>','AJAX title was interpreted as HTML');
  check(d.querySelector('.product-details a').getAttribute('href')==='/site/item/7','AJAX update lost product link');
  check($('#cart-count').text()==='2,5'&&$('#cart-total').text().includes('48,75'),'Count or total inconsistent');
  failNext='conflict';$('.quantity-input').val('3').trigger('change');await wait(300);
  check($('#cart-feedback').text().includes('Changed in another tab')&&$('.quantity-input').val()==='3','Conflict did not show current state');
  check(requests[1].data.revision===2,'Revision not advanced');
  failNext='lost';$('.quantity-input').val('4').trigger('change');await wait(550);
  check(requests.length===4&&requests[3].url==='/site/cart/count','Ambiguous write was not reconciled');
  check($('.quantity-input').val()==='4'&&!$('.quantity-input').prop('disabled')&&$('#cart-feedback').text().includes('Verbindung'),'Network reconciliation failed');
  $('#clear-cart').trigger('click');await wait(300);
  check(!d.querySelector('#cart-empty').hidden&&d.querySelector('.cart-summary').hidden&&$('#cart-count').text()==='0','Clearing did not render empty state');
  check(d.activeElement.id==='cart-feedback','Keyboard focus lost after clearing');
  // A lost response followed by a failed read must keep writes locked.
  server.cart={7:{title:'Recovered',sku:'P-7',price:19.5,quantity:1,uom:'Meter'}};
  failNext='lost';getFail=true;$('#clear-cart').trigger('click');await wait(550);
  check(!d.querySelector('#cart-reload').hidden&&d.activeElement.id==='cart-reload'&&$('#clear-cart').prop('disabled'),'Unconfirmed cart allows another write');
 }
 } catch(e){failures.push(String(e.stack||e))}
 document.querySelector('#metrics').textContent=JSON.stringify({checks,failures,mode:MODE,width:w.innerWidth});
},150));
'''

class Handler(BaseHTTPRequestHandler):
    assets = {}
    fixtures = {}
    def log_message(self, *args):
        pass
    def do_GET(self):
        url = urlsplit(self.path)
        query = parse_qs(url.query)
        mode = query.get('mode', ['normal'])[0]
        if url.path in self.assets:
            self.send(self.assets[url.path], 'text/css' if url.path.endswith('.css') else 'application/javascript')
        elif url.path in ('/js/shops.js', '/css/shop.css'):
            self.send((ROOT / url.path.lstrip('/')).read_bytes(), 'text/css' if url.path.endswith('.css') else 'application/javascript')
        elif url.path == '/frame':
            self.send(self.fixtures[mode])
        elif url.path == '/runner':
            width = int(query.get('width', ['390'])[0])
            script = SCRIPT.replace('MODE', json.dumps(mode))
            self.send(f'<!doctype html><html><body><iframe src="/frame?mode={mode}" style="width:{width}px;height:1700px;border:0"></iframe><pre id="metrics"></pre><script>{script}</script></body></html>')
        else:
            self.send('Not found', status=404)
    def send(self, data, kind='text/html; charset=utf-8', status=200):
        if isinstance(data, str):
            data = data.encode()
        self.send_response(status)
        self.send_header('Content-Type', kind)
        self.end_headers()
        self.wfile.write(data)


def main():
    Handler.assets = frontend.load_assets()
    for mode in ['normal', 'empty', 'disabled']:
        Handler.fixtures[mode] = subprocess.check_output(['php', str(ROOT / 'tests/site/cart-fixture.php'), mode])
    server = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    origin = f'http://127.0.0.1:{server.server_port}'
    try:
        results = [frontend.measure(f'{origin}/runner?width={width}', max(width, 800), 1000) for width in (1365, 768, 390, 320)]
        results += [frontend.measure(f'{origin}/runner?width=390&mode={mode}', 800, 1000) for mode in ['empty', 'disabled']]
    finally:
        server.shutdown()
        server.server_close()
        thread.join(timeout=5)
    print(json.dumps(results, indent=2))
    for result in results:
        if result['failures']:
            raise AssertionError('; '.join(result['failures']))
    print(f"PASS: {sum(r['checks'] for r in results)} Chromium cart desktop/mobile, focus, concurrency and network-failure checks")

if __name__ == '__main__':
    main()
