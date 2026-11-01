#!/usr/bin/env python3
"""Measure representative public image loading with the installed Chromium."""

from __future__ import annotations

import binascii
import html
import json
import random
import re
import shutil
import struct
import subprocess
import tempfile
import threading
import urllib.parse
import zlib
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
CHROMIUM = shutil.which("chromium") or shutil.which("chromium-browser") or shutil.which("google-chrome")


def png(width: int, height: int, seed: int) -> bytes:
    random_bytes = random.Random(seed).randbytes(width * height * 3)
    rows = b"".join(b"\0" + random_bytes[offset:offset + width * 3] for offset in range(0, len(random_bytes), width * 3))

    def chunk(kind: bytes, data: bytes) -> bytes:
        return struct.pack(">I", len(data)) + kind + data + struct.pack(">I", binascii.crc32(kind + data) & 0xFFFFFFFF)

    return b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", width, height, 8, 2, 0, 0, 0)) + chunk(b"IDAT", zlib.compress(rows, 6)) + chunk(b"IEND", b"")


IMAGES = {
    "/hero-500.png": png(500, 281, 500),
    "/hero-800.png": png(800, 450, 800),
    "/hero-1200.png": png(1200, 675, 1200),
    "/offscreen.png": png(800, 450, 1800),
}


class FixtureHandler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):  # noqa: A002, ANN001
        return

    def do_GET(self) -> None:
        path = urllib.parse.urlsplit(self.path).path
        if path == "/shop.css":
            self.send_body((ROOT / "css/shop.css").read_bytes(), "text/css")
            return
        if path in IMAGES:
            self.send_body(IMAGES[path], "image/png")
            return
        if path in {"/baseline/", "/optimized/"}:
            self.send_body(self.page(path == "/optimized/").encode(), "text/html; charset=utf-8")
            return
        self.send_body(b"Not found", "text/plain", 404)

    def page(self, optimized: bool) -> str:
        if optimized:
            hero = '<img src="/hero-1200.png" srcset="/hero-500.png 500w, /hero-800.png 800w, /hero-1200.png 1200w" sizes="100vw" width="1200" height="675" alt="" fetchpriority="high">'
            offscreen = '<img src="/offscreen.png" width="800" height="450" alt="Offscreen" loading="lazy" decoding="async">'
        else:
            hero = '<img src="/hero-1200.png" alt="" fetchpriority="high">'
            offscreen = '<img src="/offscreen.png" alt="Offscreen" fetchpriority="high">'
        return f"""<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Performance fixture</title><link rel="stylesheet" href="/shop.css">
<style>body{{margin:0}}.fixture-spacer{{height:20000px}}#metrics{{position:absolute;left:-9999px}}</style>
<script>
let cls=0,lcp=0;
new PerformanceObserver(list=>{{for(const entry of list.getEntries())if(!entry.hadRecentInput)cls+=entry.value}}).observe({{type:'layout-shift',buffered:true}});
new PerformanceObserver(list=>{{const entries=list.getEntries();if(entries.length)lcp=entries[entries.length-1].startTime}}).observe({{type:'largest-contentful-paint',buffered:true}});
window.addEventListener('load',()=>setTimeout(()=>{{
 const images=performance.getEntriesByType('resource').filter(entry=>entry.initiatorType==='img');
 document.getElementById('metrics').textContent=JSON.stringify({{
  cls,lcp,viewport:innerWidth,currentSrc:document.querySelector('.dw-site-block__media img').currentSrc,
  imageRequests:images.map(entry=>({{url:entry.name,startTime:entry.startTime,duration:entry.duration,bytes:entry.transferSize}})),
  imageBytes:images.reduce((sum,entry)=>sum+entry.transferSize,0),
  highPriority:document.querySelectorAll('img[fetchpriority="high"]').length
 }});
}},1200));
</script></head><body>
<section class="dw-site-block dw-site-block--hero has-image"><div class="dw-site-block__media">{hero}</div><div class="container dw-site-block__content"><h1>Efficient systems</h1></div></section>
<div class="fixture-spacer"></div><section>{offscreen}</section><pre id="metrics"></pre>
</body></html>"""

    def send_body(self, body: bytes, content_type: str, status: int = 200) -> None:
        self.send_response(status)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)


def measure(url: str, width: int, height: int) -> dict:
    if not CHROMIUM:
        raise RuntimeError("Chromium is not installed")
    with tempfile.TemporaryDirectory(prefix="dewawi-chromium-") as profile:
        command = [
            CHROMIUM,
            "--headless",
            "--no-sandbox",
            "--disable-gpu",
            "--disable-dev-shm-usage",
            f"--user-data-dir={profile}",
            f"--window-size={width},{height}",
            "--virtual-time-budget=3000",
            "--dump-dom",
            url,
        ]
        result = subprocess.run(command, check=False, capture_output=True, text=True, timeout=30)
    if result.returncode != 0:
        raise RuntimeError(result.stderr.strip() or f"Chromium exited with {result.returncode}")
    match = re.search(r'<pre id="metrics">(.*?)</pre>', result.stdout, re.S)
    if not match:
        raise RuntimeError("Chromium did not return performance metrics")
    return json.loads(html.unescape(match.group(1)))


def main() -> int:
    server = ThreadingHTTPServer(("127.0.0.1", 0), FixtureHandler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    origin = f"http://127.0.0.1:{server.server_port}"
    try:
        baseline = measure(origin + "/baseline/", 390, 844)
        mobile = measure(origin + "/optimized/", 390, 844)
        desktop = measure(origin + "/optimized/", 1365, 768)
    finally:
        server.shutdown()
        server.server_close()
        thread.join(timeout=5)

    checks = [
        (mobile["highPriority"] == 1 and desktop["highPriority"] == 1, "optimized fixture prioritizes exactly one image"),
        (sum("/hero-" in entry["url"] for entry in mobile["imageRequests"]) == 1, "responsive candidates caused duplicate hero downloads"),
        (mobile["imageBytes"] < baseline["imageBytes"], "responsive mobile image did not reduce transferred bytes"),
        (mobile["cls"] <= 0.01 and desktop["cls"] <= 0.01, "optimized fixture has layout shifts"),
    ]
    print(json.dumps({"baselineMobile": baseline, "optimizedMobile": mobile, "optimizedDesktop": desktop}, indent=2, sort_keys=True))
    for condition, message in checks:
        if not condition:
            raise AssertionError(message)
    print(f"PASS: {len(checks)} Chromium image priority, CLS and responsive transfer checks")
    print("LCP: observer unavailable in this headless dump; priority and selected resource were verified instead")
    print("INP: not measured; the isolated fixture contains no representative user interaction")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
