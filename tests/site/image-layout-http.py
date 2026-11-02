#!/usr/bin/env python3
"""Verify public image component layouts with Chromium."""

from __future__ import annotations

import html
import json
import re
import shutil
import subprocess
import tempfile
import threading
import urllib.parse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
CHROMIUM = shutil.which("chromium") or shutil.which("chromium-browser") or shutil.which("google-chrome")
VIEWPORTS = (1365, 768, 500, 390)
IMAGES = (("square", 1000, 1000), ("portrait", 400, 1200), ("landscape", 1200, 600))
CARD_IMAGES = (("landscape", 1672, 941), ("square", 1000, 1000), ("portrait", 400, 1200))


class FixtureHandler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):  # noqa: A002, ANN001
        return

    def do_GET(self) -> None:
        parsed = urllib.parse.urlsplit(self.path)
        if parsed.path == "/css/shop.css":
            self.send_body((ROOT / "css/shop.css").read_bytes(), "text/css")
            return
        if parsed.path == "/thumbnail-frame":
            self.send_body(self.thumbnail_frame().encode(), "text/html; charset=utf-8")
            return
        if parsed.path == "/thumbnail":
            width = int(urllib.parse.parse_qs(parsed.query).get("width", ["1365"])[0])
            self.send_body(self.runner(width).encode(), "text/html; charset=utf-8")
            return
        if parsed.path == "/cards-frame":
            self.send_body(self.cards_frame().encode(), "text/html; charset=utf-8")
            return
        if parsed.path == "/cards":
            width = int(urllib.parse.parse_qs(parsed.query).get("width", ["1365"])[0])
            self.send_body(self.cards_runner(width).encode(), "text/html; charset=utf-8")
            return
        match = re.fullmatch(r"/image-(\d+)x(\d+)\.svg", parsed.path)
        if match:
            width, height = map(int, match.groups())
            svg = f'<svg xmlns="http://www.w3.org/2000/svg" width="{width}" height="{height}" viewBox="0 0 {width} {height}"><rect width="100%" height="100%" fill="#2684ff"/></svg>'
            self.send_body(svg.encode(), "image/svg+xml")
            return
        self.send_body(b"Not found", "text/plain", 404)

    @staticmethod
    def thumbnail_frame() -> str:
        rows = []
        for name, width, height in IMAGES:
            rows.append(f"""<div class="fixture-row" data-image="{name}">
<div class="dw-product-list__thumbnail-column"><div class="image-grid dw-product-list__thumbnail"><div class="image"><a href="/image-{width}x{height}.svg">
<img class="image-thumbnail dw-product-list__thumbnail-image" src="/image-{width}x{height}.svg" srcset="/image-{width}x{height}.svg {width}w" sizes="80px" width="{width}" height="{height}" alt="{name}" loading="lazy" decoding="async">
</a></div></div></div><div class="fixture-copy">Product {name}</div></div>""")
        return f"""<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/css/shop.css"><style>*{{box-sizing:border-box}}body{{margin:0}}.fixture-row{{display:flex;align-items:center;border-bottom:1px solid #ddd}}.fixture-copy{{padding:8px}}</style></head>
<body>{''.join(rows)}</body></html>"""

    @staticmethod
    def runner(width: int) -> str:
        script = """
const frame=document.getElementById('fixture');
frame.addEventListener('load',async function(){
 const doc=frame.contentDocument;const failures=[];let checks=0;
 function check(value,message){checks++;if(!value)failures.push(message)}
 await Promise.all(Array.from(doc.images).map(image=>image.decode()));
 doc.querySelectorAll('.fixture-row').forEach(function(row){
  const image=row.querySelector('img');const box=image.getBoundingClientRect();const wrapper=row.querySelector('.dw-product-list__thumbnail').getBoundingClientRect();const style=frame.contentWindow.getComputedStyle(image);
  const scale=Math.min(box.width/image.naturalWidth,box.height/image.naturalHeight);const paintedWidth=image.naturalWidth*scale;const paintedHeight=image.naturalHeight*scale;
  check(box.width<=80.01&&box.height<=80.01,row.dataset.image+' exceeds the thumbnail size');
  check(wrapper.width<=80.01,row.dataset.image+' wrapper exceeds the thumbnail size');
  check(row.getBoundingClientRect().height<=81.01,row.dataset.image+' enlarged the product row');
  check(style.objectFit==='contain',row.dataset.image+' is not contained');
  check(Math.abs(paintedWidth/paintedHeight-image.naturalWidth/image.naturalHeight)<0.001,row.dataset.image+' is distorted');
  check(paintedWidth<=box.width+0.01&&paintedHeight<=box.height+0.01,row.dataset.image+' is cropped');
  check(image.sizes==='80px'&&image.loading==='lazy',row.dataset.image+' responsive or lazy attributes changed');
 });
 document.getElementById('metrics').textContent=JSON.stringify({checks,failures,viewport:frame.contentWindow.innerWidth});
});
"""
        return f"""<!doctype html><html><body><iframe id="fixture" title="Thumbnail fixture" src="/thumbnail-frame" style="width:{width}px;height:400px;border:0"></iframe><pre id="metrics"></pre><script>{script}</script></body></html>"""

    @staticmethod
    def cards_frame() -> str:
        layouts = []
        for columns in (2, 3, 4):
            cards = []
            for name, width, height in CARD_IMAGES:
                cards.append(f"""<article class="dw-site-card" data-image="{name}"><div class="dw-site-card__media">
<img src="/image-{width}x{height}.svg" width="{width}" height="{height}" alt="{name}" loading="lazy" decoding="async" data-original="{width}x{height}">
</div><div class="dw-site-card__content">Card {name}</div></article>""")
            layouts.append(f'<section class="fixture-layout" data-columns="{columns}" style="--columns:{columns}">{"".join(cards)}</section>')
        return f"""<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/css/shop.css"><style>*{{box-sizing:border-box}}body{{margin:0}}.fixture-layout{{display:grid;grid-template-columns:repeat(var(--columns),minmax(0,1fr));gap:24px;width:1200px;max-width:100%;margin-bottom:24px}}</style></head>
<body>{''.join(layouts)}</body></html>"""

    @staticmethod
    def cards_runner(width: int) -> str:
        script = """
const frame=document.getElementById('fixture');
frame.addEventListener('load',async function(){
 const doc=frame.contentDocument;const failures=[];let checks=0;
 function check(value,message){checks++;if(!value)failures.push(message)}
 await Promise.all(Array.from(doc.images).map(image=>image.decode()));
 doc.querySelectorAll('.fixture-layout').forEach(function(layout){
  const columns=Number(layout.dataset.columns);const media=Array.from(layout.querySelectorAll('.dw-site-card__media'));const expectedHeight=media[0].getBoundingClientRect().height;
  check(frame.contentWindow.getComputedStyle(layout).gridTemplateColumns.split(' ').length===columns,columns+'-column card layout was not applied');
  media.forEach(function(area){
   const image=area.querySelector('img');const areaBox=area.getBoundingClientRect();const imageBox=image.getBoundingClientRect();const style=frame.contentWindow.getComputedStyle(image);const original=image.dataset.original.split('x');
   check(Math.abs(areaBox.width/areaBox.height-4/3)<0.001,columns+'-column '+image.alt+' media is not 4:3');
   check(Math.abs(areaBox.height-expectedHeight)<0.01,columns+'-column card heights depend on the source image');
   check(Math.abs(imageBox.width-areaBox.width)<0.01&&Math.abs(imageBox.height-areaBox.height)<0.01,columns+'-column '+image.alt+' image does not fill its media area');
   check(style.objectFit==='cover',columns+'-column '+image.alt+' image is not covered');
   check(image.getAttribute('width')===original[0]&&image.getAttribute('height')===original[1],columns+'-column '+image.alt+' original dimensions changed');
  });
 });
 document.getElementById('metrics').textContent=JSON.stringify({checks,failures,viewport:frame.contentWindow.innerWidth});
});
"""
        return f"""<!doctype html><html><body><iframe id="fixture" title="CMS card fixture" src="/cards-frame" style="width:{width}px;height:1800px;border:0"></iframe><pre id="metrics"></pre><script>{script}</script></body></html>"""

    def send_body(self, body: bytes, content_type: str, status: int = 200) -> None:
        self.send_response(status)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)


def measure(origin: str, fixture: str, width: int) -> dict:
    if not CHROMIUM:
        raise RuntimeError("Chromium is not installed")
    with tempfile.TemporaryDirectory(prefix="dewawi-image-layout-") as profile:
        command = [
            CHROMIUM, "--headless", "--no-sandbox", "--disable-gpu", "--disable-dev-shm-usage",
            f"--user-data-dir={profile}", f"--window-size={max(width + 40, 600)},700",
            "--virtual-time-budget=3000", "--dump-dom", f"{origin}/{fixture}?width={width}",
        ]
        result = subprocess.run(command, check=False, capture_output=True, text=True, timeout=30)
    if result.returncode != 0:
        raise RuntimeError(result.stderr.strip() or f"Chromium exited with {result.returncode}")
    matches = re.findall(r'<pre id="metrics">(.*?)</pre>', result.stdout, re.S)
    if not matches or not matches[-1].strip():
        raise RuntimeError("Chromium did not return image layout metrics")
    return json.loads(html.unescape(matches[-1]))


def main() -> int:
    server = ThreadingHTTPServer(("127.0.0.1", 0), FixtureHandler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    origin = f"http://127.0.0.1:{server.server_port}"
    try:
        thumbnail_results = [measure(origin, "thumbnail", width) for width in VIEWPORTS]
        card_result = measure(origin, "cards", 1365)
    finally:
        server.shutdown()
        server.server_close()
        thread.join(timeout=5)
    for result in thumbnail_results:
        if result["viewport"] not in VIEWPORTS:
            raise AssertionError(f"Unexpected viewport: {result['viewport']}")
        if result["failures"]:
            raise AssertionError("; ".join(result["failures"]))
    if card_result["viewport"] != 1365:
        raise AssertionError(f"Unexpected card viewport: {card_result['viewport']}")
    if card_result["failures"]:
        raise AssertionError("; ".join(card_result["failures"]))
    print(json.dumps({"thumbnails": thumbnail_results, "cards": card_result}, indent=2, sort_keys=True))
    checks = sum(result["checks"] for result in thumbnail_results) + card_result["checks"]
    print(f"PASS: {checks} Chromium product thumbnail and CMS card layout checks")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
