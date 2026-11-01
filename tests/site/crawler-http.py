#!/usr/bin/env python3
"""Exercise the public site crawler against isolated real HTTP fixtures."""

from __future__ import annotations

import importlib.util
import json
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
import subprocess
import sys
import threading
import time
import urllib.parse


root = Path(__file__).resolve().parents[2]
module_path = root / "tests/site/crawl.py"
spec = importlib.util.spec_from_file_location("dewawi_site_crawl", module_path)
assert spec and spec.loader
crawl = importlib.util.module_from_spec(spec)
spec.loader.exec_module(crawl)


class FixtureHandler(BaseHTTPRequestHandler):
    methods: list[str] = []
    base_url = ""

    def log_message(self, format, *args):  # noqa: A002, ANN001
        return

    def do_GET(self) -> None:
        self.methods.append("GET")
        self.respond(False)

    def do_HEAD(self) -> None:
        self.methods.append("HEAD")
        self.respond(True)

    def respond(self, head: bool) -> None:
        path = urllib.parse.urlsplit(self.path).path
        parts = [part for part in path.split("/") if part]
        scenario = parts[0] if parts else ""
        remainder = "/".join(parts[1:])

        if remainder == "robots.txt":
            if scenario == "noindex":
                self.send_text("User-agent: *\nAllow: /\n", "text/plain", head=head)
            else:
                self.send_text(
                    f"User-agent: *\nAllow: /\nSitemap: {self.base_url}/{scenario}/sitemap.xml\n",
                    "text/plain",
                    head=head,
                )
            return

        if remainder == "sitemap.xml":
            if scenario == "noindex":
                self.send_text("Not found", status=404, head=head)
                return
            if scenario == "sitemap-missing":
                self.send_text("Not found", status=404, head=head)
                return
            if scenario == "sitemap-bad":
                self.send_text("<urlset><url>", "application/xml", head=head)
                return
            if scenario == "foreign":
                other = self.base_url.replace("127.0.0.1", "localhost")
                self.send_text(self.sitemap([f"{other}/{scenario}/foreign"]), "application/xml", head=head)
                return
            if scenario == "sitemap-index":
                child = f"{self.base_url}/{scenario}/child-sitemap.xml"
                self.send_text(f'<?xml version="1.0"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>{child}</loc></sitemap></sitemapindex>', "application/xml", head=head)
                return
            entries = [f"{self.base_url}/{scenario}/"]
            if scenario == "ok":
                entries.append(f"{self.base_url}/{scenario}/about")
            self.send_text(self.sitemap(entries), "application/xml", head=head)
            return
        if scenario == "sitemap-index" and remainder == "child-sitemap.xml":
            self.send_text(self.sitemap([f"{self.base_url}/{scenario}/"]), "application/xml", head=head)
            return

        if remainder in {"site.css", "site.js", "image.png"}:
            content_type = "text/css" if remainder.endswith(".css") else "application/javascript" if remainder.endswith(".js") else "image/png"
            self.send_text("fixture", content_type, head=head)
            return
        if remainder == "missing.png" or remainder == "missing":
            self.send_text("Not found", status=404, head=head)
            return

        if scenario == "chain" and remainder == "old":
            self.send_redirect(f"/{scenario}/mid")
            return
        if scenario == "chain" and remainder == "mid":
            self.send_redirect(f"/{scenario}/current")
            return
        if scenario == "loop" and remainder == "loop-a":
            self.send_redirect(f"/{scenario}/loop-b")
            return
        if scenario == "loop" and remainder == "loop-b":
            self.send_redirect(f"/{scenario}/loop-a")
            return
        if scenario == "single" and remainder == "old":
            self.send_redirect(f"/{scenario}/current")
            return
        if scenario == "breadcrumb" and remainder == "old":
            self.send_redirect(f"/{scenario}/current")
            return
        if scenario == "invalid-redirect" and remainder == "invalid":
            self.send_response(302)
            self.send_header("Content-Length", "0")
            self.end_headers()
            return
        if scenario == "timeout" and remainder == "slow":
            time.sleep(1.0)
            self.send_text(self.page(scenario, path), head=head)
            return

        if scenario == "depth" and remainder in {"level-1", "level-2", "level-3"}:
            number = int(remainder[-1])
            link = f'<a href="/{scenario}/level-{number + 1}">Next</a>' if number < 3 else ""
            self.send_text(self.page(scenario, path, body=link), head=head)
            return

        if scenario == "canonical" and remainder == "other":
            self.send_text(self.page(scenario, path), head=head)
            return
        if scenario in {"chain", "single", "breadcrumb"} and remainder == "current":
            self.send_text(self.page(scenario, path), head=head)
            return
        if remainder not in {"", "about"}:
            self.send_text("Not found", status=404, head=head)
            return

        link = ""
        canonical = None
        h1 = "Fixture page"
        descriptions = 1
        json_ld = None
        image = "image.png"
        noindex = scenario == "noindex"

        if scenario == "ok" and remainder == "":
            link = f'<a href="/{scenario}/about">About</a>'
        elif scenario == "broken":
            link = f'<a href="/{scenario}/missing">Broken</a>'
        elif scenario == "chain":
            link = f'<a href="/{scenario}/old">Old</a>'
        elif scenario == "single":
            link = f'<a href="/{scenario}/old">Old</a>'
        elif scenario == "loop":
            link = f'<a href="/{scenario}/loop-a">Loop</a>'
        elif scenario == "canonical":
            canonical = f"{self.base_url}/{scenario}/other"
        elif scenario == "h1":
            h1 = "First</h1><h1>Second"
        elif scenario == "description":
            descriptions = 2
        elif scenario == "json":
            json_ld = "{invalid"
        elif scenario == "image":
            image = "missing.png"
        elif scenario == "timeout":
            link = f'<a href="/{scenario}/slow">Slow</a>'
        elif scenario == "depth":
            link = f'<a href="/{scenario}/level-1">Depth</a>'
        elif scenario == "breadcrumb":
            json_ld = json.dumps({
                "@context": "https://schema.org",
                "@type": "BreadcrumbList",
                "itemListElement": [{"@type": "ListItem", "position": 1, "name": "Old", "item": f"{self.base_url}/{scenario}/old"}],
            })
        elif scenario == "invalid-redirect":
            link = f'<a href="/{scenario}/invalid">Invalid redirect</a>'
        elif scenario == "soft404":
            h1 = "404 Not Found"
        elif scenario == "asset":
            link = f'<script src="/{scenario}/missing.js"></script><img src="/{scenario}/image.png">'
        elif scenario == "performance":
            link = f'<img src="/{scenario}/image.png" width="800" height="450" alt="Second" fetchpriority="high">'

        headers = {"X-Robots-Tag": "noindex, nofollow"} if noindex else {}
        self.send_text(
            self.page(
                scenario,
                path,
                body=link,
                canonical=canonical,
                h1=h1,
                descriptions=descriptions,
                json_ld=json_ld,
                image=image,
                noindex=noindex,
                image_attributes='width="1200" height="675" loading="lazy" fetchpriority="high" srcset="image.png 1200w"' if scenario == "performance" else 'width="1200" height="675" loading="lazy" decoding="async"',
            ),
            headers=headers,
            head=head,
        )

    def page(
        self,
        scenario: str,
        path: str,
        *,
        body: str = "",
        canonical: str | None = None,
        h1: str = "Fixture page",
        descriptions: int = 1,
        json_ld: str | None = None,
        image: str = "image.png",
        noindex: bool = False,
        image_attributes: str = 'width="1200" height="675" loading="lazy" decoding="async"',
    ) -> str:
        current = f"{self.base_url}{path}"
        if path.endswith(f"/{scenario}"):
            current += "/"
        canonical = canonical if canonical is not None else current
        description = '<meta name="description" content="Fixture description">' * descriptions
        robots = '<meta name="robots" content="noindex, nofollow">' if noindex else ""
        canonical_tag = "" if noindex else f'<link rel="canonical" href="{canonical}">'
        if json_ld is not None:
            structured = json_ld
        elif scenario == "ok":
            items = [{"@type": "ListItem", "position": 1, "name": "Home", "item": f"{self.base_url}/{scenario}/"}]
            if not path.endswith("/"):
                items.append({"@type": "ListItem", "position": 2, "name": "About", "item": current})
            structured = json.dumps({"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": items})
        else:
            structured = json.dumps({
                "@context": "https://schema.org",
                "@type": "Organization",
                "name": "Fixture",
                "url": f"{self.base_url}/{scenario}/",
            })
        return f"""<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Fixture {scenario}</title>
{description}{robots}{canonical_tag}
<meta property="og:url" content="{current}"><meta property="og:image" content="{self.base_url}/{scenario}/{image}">
<link rel="stylesheet" href="/{scenario}/site.css"><script src="/{scenario}/site.js"></script>
<script type="application/ld+json">{structured}</script></head>
<body><h1>{h1}</h1>{body}<img src="/{scenario}/{image}" alt="Fixture image" {image_attributes}></body></html>"""

    @staticmethod
    def sitemap(entries: list[str]) -> str:
        urls = "".join(f"<url><loc>{url}</loc></url>" for url in entries)
        return f'<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">{urls}</urlset>'

    def send_redirect(self, location: str) -> None:
        self.send_response(301)
        self.send_header("Location", location)
        self.send_header("Content-Length", "0")
        self.end_headers()

    def send_text(self, body: str, content_type: str = "text/html; charset=utf-8", status: int = 200, headers: dict[str, str] | None = None, head: bool = False) -> None:
        encoded = body.encode()
        self.send_response(status)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(encoded)))
        for name, value in (headers or {}).items():
            self.send_header(name, value)
        self.end_headers()
        if not head:
            try:
                self.wfile.write(encoded)
            except BrokenPipeError:
                pass


def codes(report: dict) -> set[str]:
    return {finding["code"] for finding in report["findings"]}


checks = 0


def check(condition: bool, message: str) -> None:
    global checks
    checks += 1
    if not condition:
        raise AssertionError(message)


server = ThreadingHTTPServer(("127.0.0.1", 0), FixtureHandler)
FixtureHandler.base_url = f"http://127.0.0.1:{server.server_port}"
thread = threading.Thread(target=server.serve_forever, daemon=True)
thread.start()

try:
    def run(scenario: str, **options):
        return crawl.SiteCrawler(
            f"{FixtureHandler.base_url}/{scenario}/",
            timeout=options.pop("timeout", 2),
            expected_indexable=options.pop("expected_indexable", True),
            max_pages=options.pop("max_pages", 30),
            max_depth=options.pop("max_depth", 10),
            **options,
        ).run()

    successful = run("ok")
    check(successful["summary"]["errors"] == 0, f"Successful fixture has errors: {successful['findings']}")
    check(successful["summary"]["pages"] == 2, "Successful fixture did not crawl homepage and sitemap/link target")
    check(successful["summary"]["internalLinks"] == 1, "Internal links were not deduplicated")
    check("Pages: 2" in crawl.render_text(successful), "Readable terminal report is incomplete")

    check("http_error" in codes(run("broken")), "Broken internal link was not detected")
    check("redirect_chain" in codes(run("chain")), "Redirect chain was not detected")
    check("redirect_loop" in codes(run("loop")), "Redirect loop was not detected")
    single = run("single")
    check(single["summary"]["redirects"] == 1 and "redirect_chain" not in codes(single), "Legitimate one-hop redirect was treated as a chain")
    check("canonical_mismatch" in codes(run("canonical")), "Incorrect canonical was not detected")
    check("legacy_multiple_h1" in codes(run("h1")), "Duplicate legacy H1 was not classified")
    check("description_duplicate" in codes(run("description")), "Duplicate meta description was not detected")
    check("json_ld_invalid" in codes(run("json")), "Invalid JSON-LD was not detected")
    check("sitemap_invalid" in codes(run("sitemap-bad")), "Invalid XML sitemap was not detected")
    check("sitemap_missing" in codes(run("sitemap-missing")), "Advertised missing sitemap was not detected")
    check("image_missing" in codes(run("image")), "Missing internal image was not detected")
    check("sitemap_foreign_url" in codes(run("foreign")), "Foreign-tenant sitemap URL was not rejected")
    check(run("sitemap-index")["summary"]["errors"] == 0, "Sitemap index and child sitemap were not processed")
    check("breadcrumb_redirect" in codes(run("breadcrumb")), "Redirecting historical breadcrumb URL was not detected")
    check("redirect_location_missing" in codes(run("invalid-redirect")), "Redirect without Location was not detected")
    check("soft_404" in codes(run("soft404")), "Soft 404 was not detected")
    asset_codes = codes(run("asset"))
    check("asset_missing" in asset_codes and "image_alt_missing" in asset_codes, "Missing asset or image alt attribute was not detected")
    performance_codes = codes(run("performance"))
    check("priority_image_lazy" in performance_codes, "Lazy-loaded priority image was not detected")
    check("priority_image_multiple" in performance_codes, "Multiple high-priority images were not detected")
    check("image_sizes_missing" in performance_codes, "Responsive image without sizes was not detected")

    noindex = run("noindex", expected_indexable=False)
    check(noindex["summary"]["errors"] == 0, f"Correct noindex website has errors: {noindex['findings']}")
    check(not noindex["robots"]["sitemaps"] and noindex["robots"]["disallowAll"] is False, "Noindex robots.txt is not crawlable or advertises a sitemap")
    check("unexpected_noindex" in codes(run("noindex", expected_indexable=True)), "Unexpected noindex was not detected")

    timed_out = run("timeout", timeout=0.2)
    check("request_failed" in codes(timed_out), "Request timeout was not detected")
    depth_limited = run("depth", max_depth=1)
    check("max_depth" in codes(depth_limited), "Maximum crawl depth was not enforced")
    check(depth_limited["summary"]["pages"] == 2, "Depth-limited crawl fetched pages beyond its limit")
    page_limited = run("ok", max_pages=1)
    check("page_limit" in codes(page_limited) and page_limited["summary"]["pages"] == 1, "Maximum page count was not enforced")

    command = [
        sys.executable,
        str(module_path),
        "--url",
        f"{FixtureHandler.base_url}/ok/",
        "--indexable=yes",
        "--format=json",
        "--max-pages=10",
    ]
    cli = subprocess.run(command, check=False, capture_output=True, text=True, timeout=15)
    check(cli.returncode == 0, f"Crawler CLI failed: {cli.stdout}\n{cli.stderr}")
    cli_report = json.loads(cli.stdout)
    check(cli_report["summary"]["pages"] == 2, "JSON CLI report is incomplete")
    check(all(method in {"GET", "HEAD"} for method in FixtureHandler.methods), "Crawler sent a writing HTTP method")
finally:
    server.shutdown()
    server.server_close()
    thread.join(timeout=5)

print(f"PASS: {checks} real HTTP crawler, SEO, redirect, sitemap, resource and limit cases")
