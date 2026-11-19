#!/usr/bin/env python3
"""Exercise the rendered leasing card with Chromium and isolated HTTP fixtures."""
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

class Handler(BaseHTTPRequestHandler):
    assets = {}
    def log_message(self, *args):
        pass
    def send(self, content, kind='text/html; charset=utf-8', status=200):
        data = content.encode() if isinstance(content, str) else content
        self.send_response(status)
        self.send_header('Content-Type', kind)
        self.end_headers()
        self.wfile.write(data)
    def do_GET(self):
        url = urlsplit(self.path)
        if url.path in self.assets:
            self.send(self.assets[url.path], 'text/css' if url.path.endswith('.css') else 'application/javascript')
        elif url.path == '/js/shops.js':
            self.send((ROOT / 'js/shops.js').read_bytes(), 'application/javascript')
        elif url.path == '/css/shop.css':
            self.send((ROOT / 'css/shop.css').read_bytes(), 'text/css')
        elif url.path in ('/rates', '/stale-rates'):
            self.send(json.dumps({'price': 6500 if url.path == '/stale-rates' else 7000, 'html': Path('/tmp/dewawi-albis-fixture.html').read_text()}), 'application/json')
        elif url.path == '/failed-rates':
            self.send('unavailable', 'text/plain', 503)
        elif url.path == '/frame':
            failure = 'failure' in parse_qs(url.query)
            stale = 'stale' in parse_qs(url.query)
            endpoint = '/stale-rates' if stale else ('/failed-rates' if failure else '/rates')
            self.send('''<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/assets/bootstrap.min.css"><link rel="stylesheet" href="/css/shop.css"></head><body>
<div class="container"><div class="row"><div class="col-md-6">Product image</div><div class="col-md-6"><h1>Product</h1><p>7.000,00 EUR netto</p>
<div data-albis-price="7000" data-albis-url="''' + endpoint + '''" hidden></div><button id="buy" class="btn btn-primary">Buy</button></div></div>
<section id="inquiry"><form id="contact"><input name="subject" value="Product inquiry"><label for="message">Message</label><textarea id="message" name="message">Existing customer text</textarea><button type="submit">Send</button></form></section></div>
<script src="/assets/jquery.min.js"></script><script src="/js/shops.js"></script></body></html>''')
        elif url.path == '/runner':
            query = parse_qs(url.query)
            width = int(query['width'][0])
            failure = 'failure' in query or 'stale' in query
            source = '/frame?stale=1' if 'stale' in query else ('/frame?failure=1' if failure else '/frame')
            script = r'''
const frame=document.querySelector('iframe');let checks=0,failures=[];
function check(v,m){checks++;if(!v)failures.push(m)}
frame.addEventListener('load',()=>setTimeout(()=>{
 const win=frame.contentWindow,doc=win.document,$=win.jQuery,card=doc.querySelector('.dw-leasing');
 check(doc.querySelector('#buy').getBoundingClientRect().width>0,'Buying controls disappeared');
 check(doc.documentElement.scrollWidth<=win.innerWidth,'Horizontal overflow');
 if(FAILURE){check(!card,'API failure displayed leasing offer');check(doc.querySelector('[data-albis-url]').hidden,'Failure placeholder visible')}
 else {
  check(!!card,'Leasing card did not load');
  check(doc.querySelector('.dw-leasing__headline').textContent.includes('95'),'Insurance missing from minimum');
  check(card.getBoundingClientRect().right<=win.innerWidth,'Card overflows viewport');
  const details=card.querySelector('details');details.open=true;
  const select=card.querySelector('select');check(select.options.length===2,'Invented terms offered');
  $(select).val('36').trigger('change');
  check(!card.querySelector('[data-albis-details="36"]').hidden && card.querySelector('[data-albis-details="60"]').hidden,'Term switch failed');
  const button=card.querySelector('[data-albis-interest="36"]');
  check(!button.hidden && card.querySelector('[data-albis-interest="60"]').hidden,'Interest button has stale term');
  check(button.getBoundingClientRect().right<=win.innerWidth,'Mobile button overflows');
  $(button).trigger('click');$(button).trigger('click');
  const message=doc.querySelector('textarea').value;
  check(message.startsWith('Existing customer text'),'Customer message overwritten');
  check(message.includes('36 Monate')&&message.includes('250')&&message.includes('75'),'Inquiry lost selected conditions');
  check(message.split('Ich interessiere mich').length===2,'Repeated click duplicated leasing interest');
  check(doc.querySelector('[name="subject"]').value==='Product inquiry','Product inquiry subject changed');
  check(!doc.querySelector('form').hasAttribute('data-submitting'),'Leasing interest submitted the form');
 }
 const resources=win.performance.getEntriesByType('resource');
 check(resources.every(r=>new URL(r.name).origin===location.origin),'Browser accessed external API');
 document.querySelector('#metrics').textContent=JSON.stringify({checks,failures,viewport:win.innerWidth,failure:FAILURE});
},700));
'''.replace('FAILURE', 'true' if failure else 'false')
            self.send(f'<!doctype html><html><body><iframe src="{source}" style="width:{width}px;height:1600px;border:0"></iframe><pre id="metrics"></pre><script>{script}</script></body></html>')
        else:
            self.send('Not found', 'text/plain', 404)

def main():
    subprocess.run(['php', str(ROOT / 'tests/site/albis-leasing.php'), '--fixture'], check=True)
    Handler.assets = frontend.load_assets()
    server = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    origin = f'http://127.0.0.1:{server.server_port}'
    try:
        results = [frontend.measure(f'{origin}/runner?width={width}', max(width, 800), 1000) for width in (1365, 768, 390, 320)]
        results.append(frontend.measure(f'{origin}/runner?width=390&failure=1', 800, 1000))
        results.append(frontend.measure(f'{origin}/runner?width=390&stale=1', 800, 1000))
    finally:
        server.shutdown()
        server.server_close()
        thread.join(timeout=5)
    for result in results:
        if result['failures']:
            raise AssertionError('; '.join(result['failures']))
    print(json.dumps(results, indent=2))
    print(f"PASS: {sum(r['checks'] for r in results)} Chromium ALBIS desktop/mobile, calculator, failure and inquiry checks")

if __name__ == '__main__':
    main()
