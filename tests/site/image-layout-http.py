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

    def send_body(self, body: bytes, content_type: str, status: int = 200) -> None:
        self.send_response(status)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)


def measure(origin: str, width: int) -> dict:
    if not CHROMIUM:
        raise RuntimeError("Chromium is not installed")
    with tempfile.TemporaryDirectory(prefix="dewawi-image-layout-") as profile:
        command = [
            CHROMIUM, "--headless", "--no-sandbox", "--disable-gpu", "--disable-dev-shm-usage",
            f"--user-data-dir={profile}", f"--window-size={max(width + 40, 600)},700",
            "--virtual-time-budget=3000", "--dump-dom", f"{origin}/thumbnail?width={width}",
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
        results = [measure(origin, width) for width in VIEWPORTS]
    finally:
        server.shutdown()
        server.server_close()
        thread.join(timeout=5)
    for result in results:
        if result["viewport"] not in VIEWPORTS:
            raise AssertionError(f"Unexpected viewport: {result['viewport']}")
        if result["failures"]:
            raise AssertionError("; ".join(result["failures"]))
    print(json.dumps(results, indent=2, sort_keys=True))
    print(f"PASS: {sum(result['checks'] for result in results)} Chromium product thumbnail layout checks")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
