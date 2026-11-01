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
import time
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
    inquiry_posts: dict[str, int] = {}

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
        if path in {"/tracking.js", "/site/tracking.js"}:
            self.send_body(b"window.consentTrackingLoaded=true;", "application/javascript")
            return
        if path in {"/consent-fixture", "/site/consent-fixture"}:
            self.send_body(self.consent_fixture(parsed).encode(), "text/html; charset=utf-8")
            return
        if path == "/checkout":
            self.send_body(b'<!doctype html><html><body><div id="checkout-result">Checkout</div></body></html>', "text/html; charset=utf-8")
            return
        if path == "/cart":
            self.send_body(b'<!doctype html><html><body><div id="cart-result" data-added="true">Cart</div></body></html>', "text/html; charset=utf-8")
            return
        if path == "/search":
            query = urllib.parse.parse_qs(parsed.query).get("q", [""])[0]
            body = f'<!doctype html><html><body><div id="search-result" data-query="{html.escape(query)}">Search</div></body></html>'
            self.send_body(body.encode(), "text/html; charset=utf-8")
            return
        if path == "/checkout-fixture":
            self.send_body(self.checkout_fixture().encode(), "text/html; charset=utf-8")
            return
        if path == "/cart-add-fixture":
            self.send_body(self.cart_add_fixture().encode(), "text/html; charset=utf-8")
            return
        if path == "/search-fixture":
            self.send_body(b'<!doctype html><html><body><form action="/search" method="get"><input name="q" value="heat"></form><script>document.forms[0].submit()</script></body></html>', "text/html; charset=utf-8")
            return
        if path in {"/", "/cms/"}:
            manual = "manual" in urllib.parse.parse_qs(parsed.query)
            self.send_body(self.fixture_page(path, manual).encode(), "text/html; charset=utf-8")
            return
        self.send_body(b"Not found", "text/plain", 404)

    def do_POST(self) -> None:
        length = int(self.headers.get("Content-Length", "0"))
        body = self.rfile.read(length)
        parsed = urllib.parse.urlsplit(self.path)
        path = parsed.path
        if path in {"/cookie-consent-fixture", "/site/cookie-consent-fixture"}:
            query = urllib.parse.parse_qs(parsed.query)
            form = urllib.parse.parse_qs(body.decode())
            client = int(query.get("client", ["3"])[0])
            shop = int(query.get("shop", ["7"])[0])
            consent = form.get("consent", [""])[0]
            return_path = form.get("return", ["/"])[0]
            if consent not in {"accepted", "declined"} or not re.match(r"^/(?!/)[^\r\n]*$", return_path):
                self.send_body(b"Invalid consent", "text/plain", 400)
                return
            self.send_response(303)
            self.send_header("Location", return_path)
            cookie_path = "/site/" if path.startswith("/site/") else "/"
            self.send_header("Set-Cookie", f"dw_site_consent_{client}_{shop}=1:{consent}:{client}:{shop}; Path={cookie_path}; HttpOnly; SameSite=Lax")
            self.send_header("Cache-Control", "no-store")
            self.end_headers()
            return
        if path == "/cart/add":
            self.send_json({
                "success": True,
                "cart": {"7": {"title": "Pump", "sku": "P-7", "price": 19.5, "quantity": 1}},
                "total": 19.5,
                "cartItemCount": 1,
            })
            return
        if path == "/cart/update":
            self.send_json({
                "success": True,
                "cart": {
                    "7": {"title": "<img src=x onerror=alert(1)>", "sku": "</div><script>alert(1)</script>", "price": 19.5, "quantity": 3},
                    "8": {"title": "Remaining item", "sku": "SAFE-8", "price": 10, "quantity": 1},
                },
                "total": 68.5,
                "cartItemCount": 4,
            })
            return
        if path == "/cart/remove":
            self.send_json({
                "success": True,
                "cart": {"8": {"title": "Remaining item", "sku": "SAFE-8", "price": 10, "quantity": 1}},
                "total": 10,
                "cartItemCount": 1,
            })
            return
        if path == "/cart/clear":
            self.send_json({"success": True, "cart": {}, "total": 0, "cartItemCount": 0})
            return
        if path in {"/", "/cms/"}:
            self.inquiry_posts[path] = self.inquiry_posts.get(path, 0) + 1
            request_count = self.inquiry_posts[path]
            time.sleep(0.15)
            marker = ("a" if path == "/" else "b") * 32
            self.send_json({
                "status": "ok",
                "formHtml": f'<main><div id="form-container" data-saved="true" data-request-count="{request_count}"><div class="dw-field"><label for="saved-field">Saved</label><input id="saved-field" class="is-invalid"><div class="dw-field__error">Check field</div></div></div></main>',
                "conversionEvents": [{
                    "id": marker,
                    "interface": "dataLayer",
                    "name": "technical_inquiry_submitted",
                    "parameters": {"shop_id": 7},
                }],
            })
            return
        self.send_json({"success": False}, 404)

    def consent_fixture(self, parsed: urllib.parse.SplitResult) -> str:
        query = urllib.parse.parse_qs(parsed.query)
        base_path = "/site" if parsed.path.startswith("/site/") else ""
        client = int(query.get("client", ["3"])[0])
        shop = int(query.get("shop", ["7"])[0])
        environment = query.get("env", ["production"])[0]
        indexable = query.get("indexable", ["1"])[0] == "1"
        analytics = query.get("analytics", ["1"])[0] == "1"
        cookie_name = f"dw_site_consent_{client}_{shop}"
        cookies = {}
        for part in self.headers.get("Cookie", "").split(";"):
            if "=" in part:
                name, value = part.strip().split("=", 1)
                cookies[name] = value
        state = None
        for choice in ("accepted", "declined"):
            if cookies.get(cookie_name) == f"1:{choice}:{client}:{shop}":
                state = choice
        return_path = parsed.path + ("?" + parsed.query if parsed.query else "")
        action = f"{base_path}/cookie-consent-fixture?client={client}&shop={shop}"
        tracking = f'<script src="{base_path}/tracking.js"></script>' if analytics and indexable and environment == "production" and state == "accepted" else ""
        status = "" if state is None else f'<p class="dw-consent-panel__status" role="status">Analytics {state}</p>'
        close = "" if state is None else '<button class="btn btn-link" type="button" data-consent-close>Close</button>'
        hidden = "" if state is None else " hidden"
        initial = ' data-consent-initial="true"' if state is None else ""
        return f"""<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Consent fixture</title><link rel="icon" href="data:,"><link rel="stylesheet" href="/assets/bootstrap.min.css"><link rel="stylesheet" href="/css/shop.css"></head>
<body class="dw-theme-default" data-consent-state="{state or 'unknown'}"><main id="main-content"><h1>Consent fixture</h1><a id="shop-cart" href="/cart">Cart</a><a id="shop-checkout" href="/checkout">Checkout</a></main>
<footer><nav aria-label="Footer navigation"><button class="btn btn-link text-white dw-consent-settings" type="button" data-consent-open aria-controls="site-consent-panel" aria-expanded="{'true' if state is None else 'false'}">Cookie settings</button></nav></footer>
<section id="site-consent-panel" class="dw-consent-panel" role="dialog" aria-modal="false" aria-labelledby="site-consent-title" aria-describedby="site-consent-description" tabindex="-1" data-consent-panel data-consent-state="{state or 'unknown'}"{initial}{hidden}><div class="dw-consent-panel__content">
<h2 id="site-consent-title" class="h5">Privacy settings</h2><p id="site-consent-description">May we load optional analytics services?</p>{status}
<form action="{action}" method="post"><input type="hidden" name="return" value="{html.escape(return_path)}"><div class="dw-consent-panel__actions">
<button class="btn btn-outline-secondary" type="submit" name="consent" value="declined">Necessary cookies only</button><button class="btn btn-primary" type="submit" name="consent" value="accepted">Allow analytics</button>{close}
</div></form></div></section>
<script src="/assets/jquery.min.js"></script><script src="/assets/bootstrap.bundle.min.js"></script><script src="/js/shops.js"></script>{tracking}</body></html>"""

    def fixture_page(self, path: str, manual: bool = False) -> str:
        mode = "cms" if path == "/cms/" else "home"
        return f"""<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Frontend fixture</title><link rel="icon" href="data:,">
<script>window.fixtureErrors=[];window.dataLayer=[];window.addEventListener('error',function(e){{window.fixtureErrors.push(e.message||e.target.src||e.target.href||'resource error')}},true);window.addEventListener('unhandledrejection',function(e){{window.fixtureErrors.push(String(e.reason))}});</script>
<link rel="stylesheet" href="/assets/bootstrap.min.css" integrity="{ASSET_DEFINITIONS['/assets/bootstrap.min.css'][1]}" crossorigin="anonymous">
<link rel="stylesheet" href="/css/shop.css"></head><body class="dw-theme-default" data-page="{mode}" data-theme="default">
<a class="dw-skip-link" href="#main-content">Skip to main content</a><header class="dw-site-header"><nav class="navbar navbar-expand-xl navbar-light" aria-label="Main navigation"><div class="container">
<a class="navbar-brand" href="/"><img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="DEWAWI" width="120" height="40"></a><button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false"><span class="navbar-toggler-icon"></span></button>
<div class="collapse navbar-collapse" id="navbarNav"><ul class="navbar-nav ml-auto dw-main-nav"><li class="nav-item dw-mega-nav-item"><button class="nav-link dw-mega-toggle" type="button" data-mega-toggle aria-expanded="false" aria-controls="mega-products">Products</button><div class="dw-mega-menu" id="mega-products" hidden><div class="container"><div class="dw-mega-menu__grid"><a href="#product-list">Pumps</a></div></div></div></li><li class="nav-item dw-submenu-item"><a class="nav-link" href="#categories">Categories</a><button class="dw-submenu-toggle" type="button" aria-expanded="false" aria-controls="category-menu"><span aria-hidden="true">▼</span><span class="sr-only">Categories submenu</span></button><ul class="submenu" id="category-menu" hidden><li><a href="#category-one">Category one</a></li></ul></li></ul>
<form class="dw-header-search" action="/search" method="get" role="search"><label class="sr-only" for="query">Search</label><input id="query" name="q" value="heat"><button type="submit">Search</button></form><ul class="navbar-nav dw-header-actions"><li class="nav-item"><a class="nav-link" id="navbar-cart" href="/cart">Cart <span id="cart-count" aria-live="polite">2</span></a></li></ul></div></div></nav><section id="breadcrumb"><nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/">Home</a></li><li class="breadcrumb-item active" aria-current="page">Products</li></ol></nav></section></header>
<main id="main-content" tabindex="-1"><section class="dw-site-block dw-site-block--hero"><h1>{'CMS page' if mode == 'cms' else 'Start page'}</h1></section>
<section id="product-list"><article class="product-card"><h2>Product list</h2><label for="product-option">Variant</label><select id="product-option"><option>Standard</option><option>High output</option></select></article></section>
<section id="categories" class="container"><div class="row"><div class="col-md-3"><article class="card dw-category-card"><a class="dw-category-card__media" href="#category-one"><img class="card-img-top dw-category-card__image" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" width="400" height="1200" alt="Tall category"></a><div class="card-body">Category</div></article></div></div></section>
<div id="category-carousel" class="carousel slide dw-category-carousel"><div class="carousel-inner"><div class="carousel-item active"><img class="d-block w-100 dw-category-carousel__image" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" width="400" height="1200" alt="Tall category detail"></div></div></div>
<div id="slider" class="carousel slide" data-ride="carousel" data-interval="5000"><button class="btn dw-carousel-toggle" type="button" data-carousel-toggle aria-controls="slider" data-paused="false" data-pause-label="Pause" data-play-label="Play">Pause</button><div class="carousel-inner"><div class="carousel-item active">First</div><div class="carousel-item">Second</div></div></div>
<button id="details-button" data-toggle="collapse" data-target="#details">Details</button><div id="details" class="collapse"><ul class="nav nav-tabs"><li><a id="tab-one" class="nav-link active" data-toggle="tab" href="#pane-one">One</a></li><li><a id="tab-two" class="nav-link" data-toggle="tab" href="#pane-two">Two</a></li></ul><div class="tab-content"><div id="pane-one" class="tab-pane active">One</div><div id="pane-two" class="tab-pane">Two</div></div></div>
<button id="gallery-button" type="button" data-toggle="modal" data-target="#imageModal">Gallery</button><div id="imageModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="modal-label"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h2 id="modal-label">Product images</h2><button type="button" data-dismiss="modal">Close</button></div><div class="modal-body"><a href="#modal-link">Image link</a></div></div></div></div>
<div id="cart-items" aria-live="polite" data-quantity-label="Quantity" data-remove-label="Remove" data-empty-label="Empty"><input class="quantity-input" data-id="7" value="2" aria-label="Quantity Pump"></div><span id="cart-total"></span><button id="clear-cart" type="button">Clear cart</button>
<label for="suggestion-sku">Suggested SKU</label><input id="suggestion-sku" name="sku"><label for="suggestion-title">Suggested product</label><input id="suggestion-title" name="titel"><button class="select-suggestion" type="button" data-sku="P-7" data-title="Heat pump">Choose suggestion</button>
<form id="inquiry-form" data-current-step="1"><label for="message">Message</label><input id="message" name="message" value="Request" required><button type="submit">Send</button></form><div id="form-container">Form</div>
<button id="checkout-btn" type="button">Checkout</button></main>
<script src="/assets/jquery.min.js" integrity="{ASSET_DEFINITIONS['/assets/jquery.min.js'][1]}" crossorigin="anonymous"></script>
<script src="/assets/bootstrap.bundle.min.js" integrity="{ASSET_DEFINITIONS['/assets/bootstrap.bundle.min.js'][1]}" crossorigin="anonymous"></script>
<script src="/js/shops.js"></script>{'' if manual else '<script>' + self.test_script() + '</script>'}<footer><nav aria-label="Footer navigation"><a href="#legal">Legal</a></nav></footer></body></html>"""

    @staticmethod
    def checkout_fixture() -> str:
        return f"""<!doctype html><html><body><button id="checkout-btn">Checkout</button>
<script src="/assets/jquery.min.js" integrity="{ASSET_DEFINITIONS['/assets/jquery.min.js'][1]}" crossorigin="anonymous"></script>
<script src="/assets/bootstrap.bundle.min.js" integrity="{ASSET_DEFINITIONS['/assets/bootstrap.bundle.min.js'][1]}" crossorigin="anonymous"></script>
<script src="/js/shops.js"></script><script>setTimeout(function(){{$('#checkout-btn').trigger('click')}},100)</script></body></html>"""

    @staticmethod
    def cart_add_fixture() -> str:
        return f"""<!doctype html><html><body><button class="add-to-cart" type="button" data-id="7">Add</button>
<script src="/assets/jquery.min.js" integrity="{ASSET_DEFINITIONS['/assets/jquery.min.js'][1]}" crossorigin="anonymous"></script>
<script src="/assets/bootstrap.bundle.min.js" integrity="{ASSET_DEFINITIONS['/assets/bootstrap.bundle.min.js'][1]}" crossorigin="anonymous"></script>
<script src="/js/shops.js"></script><script>setTimeout(function(){{$('.add-to-cart').trigger('click')}},100)</script></body></html>"""

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
  $('.dw-mega-toggle').trigger('focus').trigger('click');check($('.dw-mega-nav-item').hasClass('is-open')&&$('.dw-mega-toggle').attr('aria-expanded')==='true'&&!$('#mega-products').prop('hidden'),'mega menu did not open');
  $('.dw-mega-menu a').trigger('focus').trigger($.Event('keydown',{key:'Escape'}));check(!$('.dw-mega-nav-item').hasClass('is-open')&&document.activeElement===$('.dw-mega-toggle').get(0)&&$('#mega-products').prop('hidden'),'Escape did not close mega menu and restore focus');
  $('.dw-submenu-toggle').trigger('click');check($('.dw-submenu-item').hasClass('is-open')&&!$('#category-menu').prop('hidden'),'category submenu did not open');$('.dw-submenu-toggle').trigger('click');
  $('#details').collapse('show');await wait(450);check($('#details').hasClass('show'),'product accordion did not open');
  $('#tab-two').tab('show');check($('#tab-two').hasClass('active')&&$('#pane-two').hasClass('active'),'product tab did not activate');
  $('#gallery-button').trigger('click');await wait(500);check($('#imageModal').hasClass('show')&&$('#imageModal').attr('aria-labelledby')==='modal-label','gallery modal did not open with a name');$('#imageModal [data-dismiss=modal]').trigger('click');await wait(500);check(!$('#imageModal').hasClass('show'),'gallery modal did not close');
  $('#slider').carousel('next');await wait(700);check($('#slider .carousel-item').eq(1).hasClass('active'),'slider did not advance');
  $('.select-suggestion').trigger('click');check($('[name=sku]').val()==='P-7'&&$('[name=titel]').val()==='Heat pump','product suggestion was not applied');
  check($('#product-option option').length===2&&$('#product-option').val()==='Standard','product options were not rendered');
  const categoryMedia=document.querySelector('.dw-category-card__media');const categoryImage=document.querySelector('.dw-category-card__image');const categoryDetail=document.querySelector('.dw-category-carousel .carousel-inner');const categoryDetailImage=document.querySelector('.dw-category-carousel__image');
  check(Math.abs(categoryMedia.getBoundingClientRect().width/categoryMedia.getBoundingClientRect().height-4/3)<0.02&&categoryImage.getBoundingClientRect().height===categoryMedia.getBoundingClientRect().height&&getComputedStyle(categoryImage).objectFit==='cover','category card image area is not consistently cropped to 4:3');
  check(categoryDetail.getBoundingClientRect().height<=420&&categoryDetailImage.getBoundingClientRect().height===categoryDetail.getBoundingClientRect().height&&getComputedStyle(categoryDetailImage).objectFit==='contain','category detail image is not responsively contained within its height limit');
  $('.quantity-input').val('3').trigger('change');await wait(350);check($('#cart-count').text()==='4'&&$('#cart-items .cart-item').length===2,'cart AJAX update failed');check($('#cart-items script,#cart-items img').length===0&&$('#cart-items').text().includes('<img src=x onerror=alert(1)>')&&$('#cart-items').text().includes('</div><'+'script>alert(1)</'+'script>'),'cart response content was interpreted as markup');
  $('#cart-items .remove-item').first().trigger('click');await wait(350);check($('#cart-count').text()==='1'&&$('#cart-items .product-title').text()==='Remaining item','cart remove failed');
  $('#clear-cart').trigger('click');await wait(350);check($('#cart-count').text()==='0'&&$('#cart-items').text()==='Empty','cart clear failed');
  document.querySelector('#inquiry-form input').value='';check(submitStep(document.getElementById('inquiry-form'),1)===false&&document.querySelector('#inquiry-form input:invalid'),'native form validation failed');document.querySelector('#inquiry-form input').value='Request';
  submitStep(document.getElementById('inquiry-form'),1);submitStep(document.getElementById('inquiry-form'),1);await wait(500);check($('#form-container').attr('data-saved')==='true','inquiry AJAX response was not rendered');check($('#form-container').attr('data-request-count')==='1','inquiry multiple click sent more than one request');check($('#saved-field').attr('aria-invalid')==='true'&&$('#saved-field-error').attr('role')==='alert','inquiry AJAX errors were not announced');
  const inquiryEvents=window.dataLayer.filter(item=>item.event==='technical_inquiry_submitted');check(inquiryEvents.length===1&&Object.keys(inquiryEvents[0]).sort().join(',')==='event,shop_id','AJAX conversion was duplicated or contains non-minimal data');
  window.gtagCalls=[];window.gtag=function(){window.gtagCalls.push(Array.from(arguments))};const gtagEvent={id:'c'.repeat(32),interface:'gtag',name:'contact_submitted',parameters:{shop_id:7}};dispatchSiteConversions([gtagEvent]);dispatchSiteConversions([gtagEvent]);check(window.gtagCalls.length===1&&window.gtagCalls[0][0]==='event'&&window.gtagCalls[0][1]==='contact_submitted','gtag conversion was not mapped exactly once');
  check(dispatchSiteConversions([{id:'d'.repeat(32),interface:'dataLayer',name:'contact_submitted',parameters:{shop_id:7,email:'private@example.invalid'}}])===0,'Personal conversion parameter was accepted');
  check(dispatchSiteConversions([{id:'f'.repeat(32),interface:'dataLayer',name:'order_submitted',parameters:{shop_id:7,value:25.5,currency:123}}])===0,'Invalid numeric currency was accepted');
  const eventNode=document.createElement('script');eventNode.type='application/json';eventNode.textContent=JSON.stringify([{id:'e'.repeat(32),interface:'dataLayer',name:'order_submitted',parameters:{shop_id:7,value:25.5,currency:'EUR'}}]);document.body.appendChild(eventNode);dispatchSiteConversionElement(eventNode);dispatchSiteConversionElement(eventNode);check(window.dataLayer.filter(item=>item.event==='order_submitted').length===1,'Server-rendered order conversion was duplicated');
  const search=document.querySelector('.dw-header-search');check(search&&search.method==='get'&&search.action.endsWith('/search')&&document.querySelector('label[for=query]'),'search form is inaccessible or invalid');
  check(document.querySelectorAll('main').length===1&&document.getElementById('main-content')&&document.querySelector('nav[aria-label="Main navigation"]')&&document.querySelector('nav[aria-label="Footer navigation"]'),'page landmarks are invalid');
  check(document.body.dataset.theme==='default'&&document.querySelector('.navbar-brand img[width][height]')&&document.querySelector('#breadcrumb [aria-current=page]'),'theme, logo or breadcrumb fixture failed');
  const resources=performance.getEntriesByType('resource').filter(entry=>['script','link'].includes(entry.initiatorType));
  check(resources.length===5,'CSS/JS request count changed');
  check(await frameResult('/checkout-fixture','#checkout-result',function(){return true}),'checkout navigation failed');
  check(await frameResult('/cart-add-fixture','#cart-result',function(node){return node.dataset.added==='true'}),'cart add and navigation failed');
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
    if not matches[-1].strip():
        raise RuntimeError("Chromium returned an empty frontend metrics element: " + result.stdout[-2000:])
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
