#!/usr/bin/env python3
"""Exercise the public frontend with the pinned jQuery and Bootstrap assets."""

from __future__ import annotations

import base64
import gzip
import hashlib
import html
import json
import re
import shutil
import subprocess
import tempfile
import threading
import urllib.parse
import urllib.request
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
CHROMIUM = shutil.which("chromium") or shutil.which("chromium-browser") or shutil.which("google-chrome")
ASSET_DEFINITIONS = {
    "/assets/jquery.min.js": (
        "https://code.jquery.com/jquery-3.7.1.min.js",
        "sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs",
        b"jQuery v3.7.1",
        "/tmp/dewawi-jquery-3.7.1.min.js",
    ),
    "/assets/bootstrap.min.css": (
        "https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css",
        "sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N",
        b"Bootstrap v4.6.2",
        "/tmp/dewawi-bootstrap-4.6.2.min.css",
    ),
    "/assets/bootstrap.bundle.min.js": (
        "https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js",
        "sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct",
        b"Bootstrap v4.6.2",
        "/tmp/dewawi-bootstrap-4.6.2.bundle.min.js",
    ),
}


def integrity(data: bytes) -> str:
    return "sha384-" + base64.b64encode(hashlib.sha384(data).digest()).decode()


def load_assets() -> dict[str, bytes]:
    assets = {}
    for path, (url, expected_integrity, version_marker, cache_path) in ASSET_DEFINITIONS.items():
        cache = Path(cache_path)
        data = cache.read_bytes() if cache.is_file() else b""
        if integrity(data) != expected_integrity:
            with urllib.request.urlopen(url, timeout=30) as response:
                data = response.read()
            cache.write_bytes(data)
        if integrity(data) != expected_integrity:
            raise RuntimeError(f"Integrity mismatch for {url}")
        if version_marker not in data[:500]:
            raise RuntimeError(f"Version marker missing from {url}")
        assets[path] = data
    return assets


class FixtureHandler(BaseHTTPRequestHandler):
    assets: dict[str, bytes] = {}

    def log_message(self, format, *args):  # noqa: A002, ANN001
        return

    def do_GET(self) -> None:
        parsed = urllib.parse.urlsplit(self.path)
        path = parsed.path
        if path in self.assets:
            content_type = "text/css" if path.endswith(".css") else "application/javascript"
            self.send_body(self.assets[path], content_type)
            return
        if path == "/css/shop.css":
            self.send_body((ROOT / "css/shop.css").read_bytes(), "text/css")
            return
        if path == "/js/shops.js":
            self.send_body((ROOT / "js/shops.js").read_bytes(), "application/javascript")
            return
        if path == "/checkout":
            self.send_body(b'<!doctype html><html><body><div id="checkout-result">Checkout</div></body></html>', "text/html; charset=utf-8")
            return
        if path == "/search":
            query = urllib.parse.parse_qs(parsed.query).get("q", [""])[0]
            body = f'<!doctype html><html><body><div id="search-result" data-query="{html.escape(query)}">Search</div></body></html>'
            self.send_body(body.encode(), "text/html; charset=utf-8")
            return
        if path == "/checkout-fixture":
            self.send_body(self.checkout_fixture().encode(), "text/html; charset=utf-8")
            return
        if path == "/search-fixture":
            self.send_body(b'<!doctype html><html><body><form action="/search" method="get"><input name="q" value="heat"></form><script>document.forms[0].submit()</script></body></html>', "text/html; charset=utf-8")
            return
        if path in {"/", "/cms/"}:
            self.send_body(self.fixture_page(path).encode(), "text/html; charset=utf-8")
            return
        self.send_body(b"Not found", "text/plain", 404)

    def do_POST(self) -> None:
        length = int(self.headers.get("Content-Length", "0"))
        self.rfile.read(length)
        path = urllib.parse.urlsplit(self.path).path
        if path == "/cart/update":
            self.send_json({
                "success": True,
                "cart": {"7": {"title": "Pump", "sku": "P-7", "price": 19.5, "quantity": 3}},
                "total": 58.5,
                "cartItemCount": 3,
            })
            return
        if path in {"/", "/cms/"}:
            self.send_json({"status": "ok", "formHtml": '<main><div id="form-container" data-saved="true">Saved</div></main>'})
            return
        self.send_json({"success": False}, 404)

    def fixture_page(self, path: str) -> str:
        mode = "cms" if path == "/cms/" else "home"
        return f"""<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Frontend fixture</title><link rel="icon" href="data:,">
<script>window.fixtureErrors=[];window.addEventListener('error',function(e){{window.fixtureErrors.push(e.message||e.target.src||e.target.href||'resource error')}},true);window.addEventListener('unhandledrejection',function(e){{window.fixtureErrors.push(String(e.reason))}});</script>
<link rel="stylesheet" href="/assets/bootstrap.min.css" integrity="{ASSET_DEFINITIONS['/assets/bootstrap.min.css'][1]}" crossorigin="anonymous">
<link rel="stylesheet" href="/css/shop.css"></head><body data-page="{mode}">
<header class="dw-site-header"><nav class="navbar navbar-expand-xl navbar-light"><div class="container">
<a class="navbar-brand" href="/">DEWAWI</a><button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-expanded="false"><span class="navbar-toggler-icon"></span></button>
<div class="collapse navbar-collapse" id="navbarNav"><ul class="navbar-nav ml-auto dw-main-nav"><li class="nav-item dw-mega-nav-item"><button class="nav-link dw-mega-toggle" data-mega-toggle aria-expanded="false">Products</button><div class="dw-mega-menu"><div class="container"><div class="dw-mega-menu__grid"><a href="#product-list">Pumps</a></div></div></div></li></ul>
<form class="dw-header-search" action="/search" method="get" role="search"><label class="sr-only" for="query">Search</label><input id="query" name="q" value="heat"><button type="submit">Search</button></form></div></div></nav></header>
<main><section class="dw-site-block dw-site-block--hero"><h1>{'CMS page' if mode == 'cms' else 'Start page'}</h1></section>
<section id="product-list"><article class="product-card">Product list</article></section>
<div id="slider" class="carousel slide" data-interval="false"><div class="carousel-inner"><div class="carousel-item active">First</div><div class="carousel-item">Second</div></div></div>
<button id="details-button" data-toggle="collapse" data-target="#details">Details</button><div id="details" class="collapse"><ul class="nav nav-tabs"><li><a id="tab-one" class="nav-link active" data-toggle="tab" href="#pane-one">One</a></li><li><a id="tab-two" class="nav-link" data-toggle="tab" href="#pane-two">Two</a></li></ul><div class="tab-content"><div id="pane-one" class="tab-pane active">One</div><div id="pane-two" class="tab-pane">Two</div></div></div>
<button id="gallery-button" data-toggle="modal" data-target="#imageModal">Gallery</button><div id="imageModal" class="modal fade" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-body">Image</div></div></div></div>
<div id="cart-items"><input class="quantity-input" data-id="7" value="2"></div><span id="cart-total"></span><span id="cart-count">2</span>
<form id="inquiry-form" onsubmit="return submitStep(this, 1)"><input name="message" value="Request" required><button type="submit">Send</button></form><div id="form-container">Form</div>
<button id="checkout-btn">Checkout</button></main>
<script src="/assets/jquery.min.js" integrity="{ASSET_DEFINITIONS['/assets/jquery.min.js'][1]}" crossorigin="anonymous"></script>
<script src="/assets/bootstrap.bundle.min.js" integrity="{ASSET_DEFINITIONS['/assets/bootstrap.bundle.min.js'][1]}" crossorigin="anonymous"></script>
<script src="/js/shops.js"></script><script>{self.test_script()}</script></body></html>"""

    @staticmethod
    def checkout_fixture() -> str:
        return f"""<!doctype html><html><body><button id="checkout-btn">Checkout</button>
<script src="/assets/jquery.min.js" integrity="{ASSET_DEFINITIONS['/assets/jquery.min.js'][1]}" crossorigin="anonymous"></script>
<script src="/assets/bootstrap.bundle.min.js" integrity="{ASSET_DEFINITIONS['/assets/bootstrap.bundle.min.js'][1]}" crossorigin="anonymous"></script>
<script src="/js/shops.js"></script><script>setTimeout(function(){{$('#checkout-btn').trigger('click')}},100)</script></body></html>"""

    @staticmethod
    def test_script() -> str:
        return r"""
(function(){
 const failures=[];let checks=0;
 function check(value,message){checks++;if(!value)failures.push(message)}
 function wait(ms){return new Promise(resolve=>setTimeout(resolve,ms))}
 function frameResult(url,selector,validate){return new Promise(resolve=>{const frame=document.createElement('iframe');frame.hidden=true;const timer=setTimeout(()=>resolve(false),2000);frame.onload=function(){try{const node=frame.contentDocument.querySelector(selector);if(node&&validate(node)){clearTimeout(timer);resolve(true)}}catch(error){resolve(false)}};frame.src=url;document.body.appendChild(frame)})}
 async function run(){
  check(window.jQuery&&jQuery.fn.jquery==='3.7.1','jQuery 3.7.1 not active');
  check(!jQuery.migrateVersion,'jQuery Migrate was loaded');
  check(jQuery.fn.modal&&jQuery.fn.modal.Constructor.VERSION==='4.6.2','Bootstrap 4.6.2 not active');
  check(document.querySelectorAll('link[rel=stylesheet]').length===2,'unexpected stylesheet count');
  check(document.querySelectorAll('script[src]').length===3,'unexpected script count');
  const mobile=innerWidth<1200;
  const togglerVisible=getComputedStyle(document.querySelector('.navbar-toggler')).display!=='none';
  check(togglerVisible===mobile,'responsive navbar breakpoint failed');
  if(mobile){$('.navbar-toggler').trigger('click');await wait(450);check($('#navbarNav').hasClass('show'),'mobile collapse did not open')}
  $('.dw-mega-toggle').trigger('click');check($('.dw-mega-nav-item').hasClass('is-open')&&$('.dw-mega-toggle').attr('aria-expanded')==='true','mega menu did not open');
  $(document).trigger($.Event('keydown',{key:'Escape'}));check(!$('.dw-mega-nav-item').hasClass('is-open'),'Escape did not close mega menu');
  $('#details').collapse('show');await wait(450);check($('#details').hasClass('show'),'product accordion did not open');
  $('#tab-two').tab('show');check($('#tab-two').hasClass('active')&&$('#pane-two').hasClass('active'),'product tab did not activate');
  $('#imageModal').modal('show');await wait(350);check($('#imageModal').hasClass('show'),'gallery modal did not open');$('#imageModal').modal('hide');await wait(350);
  $('#slider').carousel('next');await wait(700);check($('#slider .carousel-item').eq(1).hasClass('active'),'slider did not advance');
  $('.quantity-input').val('3').trigger('change');await wait(350);check($('#cart-count').text()==='3','cart AJAX update failed');
  submitStep(document.getElementById('inquiry-form'),1);await wait(350);check($('#form-container').attr('data-saved')==='true','inquiry AJAX submit failed');
  const search=document.querySelector('.dw-header-search');check(search&&search.method==='get'&&search.action.endsWith('/search')&&document.querySelector('label[for=query]'),'search form is inaccessible or invalid');
  check(document.querySelector('[data-page]').dataset.page==='home'||document.querySelector('[data-page]').dataset.page==='cms','page fixture missing');
  const resources=performance.getEntriesByType('resource').filter(entry=>['script','link'].includes(entry.initiatorType));
  check(resources.length===5,'CSS/JS request count changed');
  check(await frameResult('/checkout-fixture','#checkout-result',function(){return true}),'checkout navigation failed');
  check(await frameResult('/search-fixture','#search-result',function(node){return node.dataset.query==='heat'}),'search submission failed');
  check(window.fixtureErrors.length===0,'browser or resource error: '+window.fixtureErrors.join(', '));
  const result={checks,failures,viewport:innerWidth,page:document.body.dataset.page,requests:resources.length,bytes:resources.reduce((sum,entry)=>sum+entry.transferSize,0),resources:resources.map(entry=>({url:entry.name.split('/').pop(),bytes:entry.transferSize,blocking:entry.renderBlockingStatus||''}))};
  document.body.insertAdjacentHTML('beforeend','<pre id="metrics"></pre>');document.getElementById('metrics').textContent=JSON.stringify(result);
 }
 setTimeout(function(){run().catch(function(error){document.body.insertAdjacentHTML('beforeend','<pre id="metrics"></pre>');document.getElementById('metrics').textContent=JSON.stringify({checks,failures:failures.concat(String(error)),errors:window.fixtureErrors})})},50);
})();
"""

    def send_json(self, value: dict, status: int = 200) -> None:
        self.send_body(json.dumps(value).encode(), "application/json", status)

    def send_body(self, body: bytes, content_type: str, status: int = 200) -> None:
        self.send_response(status)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.send_header("Access-Control-Allow-Origin", "*")
        self.end_headers()
        self.wfile.write(body)


def measure(url: str, width: int, height: int) -> dict:
    if not CHROMIUM:
        raise RuntimeError("Chromium is not installed")
    with tempfile.TemporaryDirectory(prefix="dewawi-frontend-") as profile:
        command = [
            CHROMIUM, "--headless", "--no-sandbox", "--disable-gpu", "--disable-dev-shm-usage",
            f"--user-data-dir={profile}", f"--window-size={width},{height}", "--virtual-time-budget=7000", "--dump-dom", url,
        ]
        result = subprocess.run(command, check=False, capture_output=True, text=True, timeout=40)
    if result.returncode != 0:
        raise RuntimeError(result.stderr.strip() or f"Chromium exited with {result.returncode}")
    matches = re.findall(r'<pre id="metrics">(.*?)</pre>', result.stdout, re.S)
    if not matches:
        raise RuntimeError("Chromium did not return frontend metrics")
    return json.loads(html.unescape(matches[-1]))


def main() -> int:
    FixtureHandler.assets = load_assets()
    server = ThreadingHTTPServer(("127.0.0.1", 0), FixtureHandler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    origin = f"http://127.0.0.1:{server.server_port}"
    try:
        desktop = measure(origin + "/", 1365, 900)
        mobile = measure(origin + "/cms/", 500, 900)
    finally:
        server.shutdown()
        server.server_close()
        thread.join(timeout=5)

    for result in (desktop, mobile):
        if result.get("failures"):
            raise AssertionError("; ".join(result["failures"]))
    checks = desktop["checks"] + mobile["checks"]
    raw_bytes = sum(len(data) for data in FixtureHandler.assets.values()) + (ROOT / "css/shop.css").stat().st_size + (ROOT / "js/shops.js").stat().st_size
    gzip_bytes = sum(len(gzip.compress(data, compresslevel=9)) for data in FixtureHandler.assets.values()) + len(gzip.compress((ROOT / "css/shop.css").read_bytes(), compresslevel=9)) + len(gzip.compress((ROOT / "js/shops.js").read_bytes(), compresslevel=9))
    print(json.dumps({"desktop": desktop, "mobile": mobile, "assetBytes": {"raw": raw_bytes, "gzip": gzip_bytes}}, indent=2, sort_keys=True))
    print(f"PASS: {checks} Chromium frontend dependency, navigation, component, AJAX and responsive checks")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
