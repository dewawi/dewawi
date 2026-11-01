#!/usr/bin/env python3
"""Crawl one explicitly selected public website and report HTTP and SEO issues."""

from __future__ import annotations

import argparse
import json
import posixpath
import re
import socket
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from collections import Counter, deque
from html.parser import HTMLParser
from typing import Any


TRACKING_PARAMETERS = {"gclid", "fbclid", "msclkid"}
DOCUMENT_EXTENSIONS = {
    ".7z", ".avi", ".csv", ".doc", ".docx", ".gz", ".mp3", ".mp4",
    ".ods", ".odt", ".pdf", ".ppt", ".pptx", ".rar", ".tar", ".webm",
    ".xls", ".xlsx", ".xml", ".zip",
}
RESOURCE_EXTENSIONS = {
    ".avif", ".css", ".gif", ".ico", ".jpeg", ".jpg", ".js", ".png",
    ".svg", ".webp", ".woff", ".woff2",
}
BACKEND_PATH = re.compile(r"/(?:admin|users?|login|logout)(?:/|$)", re.I)
SEARCH_PATH = re.compile(r"/(?:search|suche)(?:/|$)", re.I)
SOFT_404 = re.compile(r"(?:^|\b)404(?:\b|$)|not found|seite nicht gefunden", re.I)


class ParsedHtml(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.titles: list[str] = []
        self.h1: list[str] = []
        self.descriptions: list[str] = []
        self.canonicals: list[str] = []
        self.open_graph: dict[str, list[str]] = {}
        self.links: list[dict[str, Any]] = []
        self.images: list[dict[str, Any]] = []
        self.resources: list[tuple[str, str]] = []
        self.embeds: list[tuple[str, str | None]] = []
        self.unsafe_attributes: list[tuple[str, str]] = []
        self.styles: list[str] = []
        self.ids: set[str] = set()
        self.json_ld: list[str] = []
        self.base_href: str | None = None
        self._capture: str | None = None
        self._text: list[str] = []
        self._json: list[str] | None = None
        self._style: list[str] | None = None

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        values = {key.lower(): value for key, value in attrs}
        tag = tag.lower()

        if values.get("id"):
            self.ids.add(values["id"] or "")
        for name, value in attrs:
            name = name.lower()
            if name.startswith("on") or name in {"srcdoc", "formaction"}:
                self.unsafe_attributes.append((tag, name))
            if name == "style" and value:
                self.styles.append(value)
                if re.search(r"(?:expression\s*\(|@import|behavior\s*:|-moz-binding)", value, re.I):
                    self.unsafe_attributes.append((tag, "style"))

        if tag in {"title", "h1"}:
            self._capture = tag
            self._text = []
        elif tag == "meta":
            name = (values.get("name") or "").strip().lower()
            prop = (values.get("property") or "").strip().lower()
            content = (values.get("content") or "").strip()
            if name == "description":
                self.descriptions.append(content)
            if prop.startswith("og:"):
                self.open_graph.setdefault(prop, []).append(content)
        elif tag == "a":
            self.links.append({"present": "href" in values, "href": values.get("href") or ""})
        elif tag == "base" and self.base_href is None and values.get("href"):
            self.base_href = values["href"]
        elif tag == "img":
            self.images.append({
                "src": values.get("src"),
                "srcset": values.get("srcset"),
                "sizes": values.get("sizes"),
                "width": values.get("width"),
                "height": values.get("height"),
                "loading": (values.get("loading") or "").lower(),
                "fetchpriority": (values.get("fetchpriority") or "").lower(),
                "alt_present": "alt" in values,
                "alt": values.get("alt") or "",
                "role": (values.get("role") or "").lower(),
                "class": values.get("class") or "",
            })
        elif tag == "link" and values.get("href"):
            rel = {part.lower() for part in (values.get("rel") or "").split()}
            if "canonical" in rel:
                self.canonicals.append(values["href"] or "")
            if rel.intersection({"stylesheet", "icon", "preload", "modulepreload"}):
                kind = "css" if "stylesheet" in rel else "image" if "icon" in rel else (values.get("as") or "resource")
                self.resources.append((kind, values["href"] or ""))
        elif tag == "script":
            if values.get("src"):
                self.resources.append(("js", values["src"] or ""))
            if (values.get("type") or "").lower() == "application/ld+json":
                self._json = []
        elif tag == "style":
            self._style = []
        elif tag in {"iframe", "embed", "video", "audio", "source"}:
            self.embeds.append((tag, values.get("src")))
            if values.get("src"):
                self.resources.append((tag, values["src"] or ""))
        elif tag == "object":
            self.embeds.append((tag, values.get("data")))
            if values.get("data"):
                self.resources.append((tag, values["data"] or ""))

    def handle_endtag(self, tag: str) -> None:
        tag = tag.lower()
        if self._capture == tag:
            text = " ".join("".join(self._text).split())
            (self.titles if tag == "title" else self.h1).append(text)
            self._capture = None
            self._text = []
        if tag == "script" and self._json is not None:
            self.json_ld.append("".join(self._json).strip())
            self._json = None
        if tag == "style" and self._style is not None:
            self.styles.append("".join(self._style))
            self._style = None

    def handle_data(self, data: str) -> None:
        if self._capture is not None:
            self._text.append(data)
        if self._json is not None:
            self._json.append(data)
        if self._style is not None:
            self._style.append(data)


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):  # noqa: ANN001
        return None


class SiteCrawler:
    def __init__(
        self,
        start_url: str,
        *,
        max_pages: int = 200,
        max_depth: int = 20,
        timeout: float = 10.0,
        expected_indexable: bool | None = None,
        extra_urls: list[str] | None = None,
        max_resources: int = 1000,
    ) -> None:
        self.start_url = self.normalize_start_url(start_url)
        self.start_parts = urllib.parse.urlsplit(self.start_url)
        self.origin = self.url_origin(self.start_url)
        self.base_path = self.start_parts.path.rstrip("/")
        self.max_pages = max(1, max_pages)
        self.max_depth = max(0, max_depth)
        self.timeout = max(0.1, timeout)
        self.expected_indexable = expected_indexable
        self.extra_urls = extra_urls or []
        self.max_resources = max(1, max_resources)
        self.opener = urllib.request.build_opener(NoRedirect())
        self.findings: list[dict[str, str]] = []
        self._finding_keys: set[tuple[str, str, str]] = set()
        self.pages: list[dict[str, Any]] = []
        self.redirects: list[dict[str, Any]] = []
        self.internal_links: set[str] = set()
        self.resources: dict[str, set[str]] = {}
        self.sitemap_urls: list[str] = []
        self.sitemap_entries: list[str] = []
        self.sitemap_statuses: dict[str, int] = {}
        self.breadcrumb_urls: set[str] = set()
        self.robots: dict[str, Any] = {}
        self._query_variants: dict[str, set[str]] = {}
        self._page_results: dict[str, dict[str, Any]] = {}
        self._queue: deque[tuple[str, int, str]] = deque()
        self._queued: set[str] = set()

    @staticmethod
    def normalize_start_url(url: str) -> str:
        url = url.strip()
        parts = urllib.parse.urlsplit(url)
        if parts.scheme not in {"http", "https"} or not parts.hostname:
            raise ValueError("The website URL must be an absolute HTTP or HTTPS URL")
        if parts.username is not None or parts.password is not None or parts.fragment:
            raise ValueError("Credentials and fragments are not allowed in the website URL")
        return SiteCrawler.normalize_absolute_url(url)

    @staticmethod
    def normalize_absolute_url(url: str) -> str:
        parts = urllib.parse.urlsplit(url)
        host = (parts.hostname or "").lower()
        if ":" in host and not host.startswith("["):
            host = f"[{host}]"
        port = parts.port
        if port and not ((parts.scheme == "http" and port == 80) or (parts.scheme == "https" and port == 443)):
            host += f":{port}"
        path = parts.path or "/"
        trailing = path.endswith("/")
        path = posixpath.normpath(path)
        if not path.startswith("/"):
            path = "/" + path
        if trailing and path != "/":
            path += "/"
        query = []
        for key, value in urllib.parse.parse_qsl(parts.query, keep_blank_values=True):
            if key.lower().startswith("utm_") or key.lower() in TRACKING_PARAMETERS:
                continue
            query.append((key, value))
        query.sort()
        return urllib.parse.urlunsplit((parts.scheme.lower(), host, path, urllib.parse.urlencode(query), ""))

    @staticmethod
    def url_origin(url: str) -> tuple[str, str, int]:
        parts = urllib.parse.urlsplit(url)
        port = parts.port or (443 if parts.scheme == "https" else 80)
        return parts.scheme.lower(), (parts.hostname or "").lower(), port

    def is_internal(self, url: str) -> bool:
        if self.url_origin(url) != self.origin:
            return False
        path = urllib.parse.urlsplit(url).path
        return not self.base_path or path == self.base_path or path.startswith(self.base_path + "/")

    def resolve_url(self, value: str, current_url: str) -> str | None:
        value = value.strip()
        if not value or value.startswith("#"):
            return None
        if re.match(r"^(?:mailto|tel|javascript|data):", value, re.I):
            return None
        try:
            absolute = urllib.parse.urljoin(current_url, value)
            parts = urllib.parse.urlsplit(absolute)
            if parts.scheme not in {"http", "https"} or not parts.hostname:
                return None
            if parts.username is not None or parts.password is not None:
                return None
            return self.normalize_absolute_url(absolute)
        except (ValueError, UnicodeError):
            return None

    def add_finding(self, severity: str, category: str, code: str, url: str, message: str) -> None:
        key = (code, url, message)
        if key in self._finding_keys:
            return
        self._finding_keys.add(key)
        self.findings.append({
            "severity": severity,
            "category": category,
            "code": code,
            "url": url,
            "message": message,
        })

    def request(self, url: str, method: str = "GET", body_limit: int = 2_000_000) -> dict[str, Any]:
        request = urllib.request.Request(
            url,
            method=method,
            headers={"User-Agent": "DEWAWI-Site-Check/1.0", "Accept": "*/*"},
        )
        started = time.monotonic()
        try:
            response = self.opener.open(request, timeout=self.timeout)
        except urllib.error.HTTPError as error:
            response = error
        except (urllib.error.URLError, TimeoutError, socket.timeout, OSError) as error:
            return {
                "url": url,
                "status": 0,
                "headers": {},
                "body": b"",
                "error": str(getattr(error, "reason", error)),
                "elapsed": time.monotonic() - started,
            }

        try:
            body = b"" if method == "HEAD" else response.read(body_limit + 1)
            truncated = len(body) > body_limit
            if truncated:
                body = body[:body_limit]
            return {
                "url": url,
                "status": int(response.getcode()),
                "headers": {key.lower(): value for key, value in response.headers.items()},
                "body": body,
                "truncated": truncated,
                "error": None,
                "elapsed": time.monotonic() - started,
            }
        except (TimeoutError, socket.timeout, OSError) as error:
            return {
                "url": url,
                "status": 0,
                "headers": {},
                "body": b"",
                "error": str(error),
                "elapsed": time.monotonic() - started,
            }
        finally:
            response.close()

    def fetch(self, url: str, method: str = "GET", body_limit: int = 2_000_000) -> dict[str, Any]:
        current = url
        seen = {current}
        hops: list[dict[str, Any]] = []

        for _ in range(11):
            result = self.request(current, method, body_limit)
            status = result["status"]
            if status < 300 or status >= 400:
                result["requested_url"] = url
                result["final_url"] = current
                result["hops"] = hops
                if len(hops) > 1:
                    self.add_finding("error", "redirect", "redirect_chain", url, f"Redirect chain contains {len(hops)} hops")
                if hops:
                    self.redirects.append({"url": url, "finalUrl": current, "hops": hops})
                return result

            location = result["headers"].get("location", "").strip()
            hop = {"url": current, "status": status, "location": location}
            hops.append(hop)
            if not location:
                self.add_finding("error", "redirect", "redirect_location_missing", current, f"HTTP {status} has no Location header")
                result.update({"requested_url": url, "final_url": current, "hops": hops})
                return result

            target = self.resolve_url(location, current)
            if target is None:
                self.add_finding("error", "redirect", "redirect_location_invalid", current, f"Invalid redirect target: {location}")
                result.update({"requested_url": url, "final_url": current, "hops": hops})
                return result
            if not self.is_internal(target):
                self.add_finding("error", "redirect", "redirect_external", current, f"Redirect leaves the approved website: {target}")
                result.update({"requested_url": url, "final_url": current, "hops": hops})
                return result
            if target in seen:
                self.add_finding("error", "redirect", "redirect_loop", url, f"Redirect loop reaches {target}")
                result.update({"requested_url": url, "final_url": target, "hops": hops})
                return result
            seen.add(target)
            current = target

        self.add_finding("error", "redirect", "redirect_limit", url, "Redirect limit exceeded")
        return {"requested_url": url, "final_url": current, "status": 0, "headers": {}, "body": b"", "error": "redirect limit", "hops": hops}

    def queue_page(self, url: str, depth: int, source: str) -> None:
        if url in self._queued:
            return
        if depth > self.max_depth:
            self.add_finding("warning", "crawl", "max_depth", url, f"Maximum crawl depth {self.max_depth} reached")
            return
        parts = urllib.parse.urlsplit(url)
        path_key = parts.path
        variants = self._query_variants.setdefault(path_key, set())
        if parts.query not in variants and len(variants) >= 5:
            self.add_finding("warning", "crawl", "query_limit", url, "Query variant limit reached for this path")
            return
        variants.add(parts.query)
        self._queued.add(url)
        self._queue.append((url, depth, source))

    def record_resource(self, kind: str, value: str, page_url: str) -> None:
        if page_url.startswith("https://") and value.strip().lower().startswith("http://"):
            self.add_finding("error", "resource", "mixed_content", page_url, f"HTTP {kind} resource on HTTPS page: {value}")
        url = self.resolve_url(value, page_url)
        if url is None:
            self.add_finding("error", "resource", "resource_url_invalid", page_url, f"Invalid {kind} URL: {value}")
            return
        if self.is_internal(url):
            self.resources.setdefault(url, set()).add(kind)
        else:
            self.add_finding("warning", "resource", "external_resource", page_url, f"External {kind} resource requires editorial and consent review: {url}")

    def parse_html(self, result: dict[str, Any], depth: int) -> dict[str, Any]:
        page_url = result["final_url"]
        charset = "utf-8"
        content_type = result["headers"].get("content-type", "")
        match = re.search(r"charset=([^;\s]+)", content_type, re.I)
        if match:
            charset = match.group(1).strip('"\'')
        try:
            html = result["body"].decode(charset, errors="replace")
        except LookupError:
            html = result["body"].decode("utf-8", errors="replace")

        parser = ParsedHtml()
        parser.feed(html)
        title = parser.titles[0] if len(parser.titles) == 1 else ""
        robots_values = []
        robots_values.extend(
            content for name, values in self.meta_robots(html).items() for content in values if name in {"robots", "googlebot"}
        )
        if result["headers"].get("x-robots-tag"):
            robots_values.append(result["headers"]["x-robots-tag"])
        noindex = any("noindex" in value.lower() for value in robots_values)

        if len(parser.titles) != 1 or not re.search(r"[\w\d]", title, re.UNICODE):
            self.add_finding("error", "seo", "title_count", page_url, f"Expected one meaningful title, found {len(parser.titles)}")
        if len(parser.descriptions) > 1:
            self.add_finding("error", "seo", "description_duplicate", page_url, f"Found {len(parser.descriptions)} meta descriptions")
        elif not parser.descriptions or not re.search(r"[\w\d]", parser.descriptions[0], re.UNICODE):
            self.add_finding("warning", "seo", "description_missing", page_url, "No meaningful meta description found")
        if len(parser.h1) != 1:
            code = "legacy_multiple_h1" if len(parser.h1) > 1 else "h1_missing"
            message = f"Found {len(parser.h1)} H1 elements; additional stored legacy headings may require editorial cleanup" if len(parser.h1) > 1 else "No main H1 found"
            self.add_finding("warning" if len(parser.h1) > 1 else "error", "seo", code, page_url, message)
        if any(not re.search(r"[\w\d]", heading, re.UNICODE) for heading in parser.h1):
            self.add_finding("error", "seo", "h1_empty", page_url, "At least one H1 has no meaningful text")

        canonical_urls = [self.resolve_url(value, page_url) for value in parser.canonicals]
        canonical = canonical_urls[0] if len(canonical_urls) == 1 else None
        if noindex:
            if parser.canonicals:
                self.add_finding("warning", "seo", "canonical_on_noindex", page_url, "Noindex page also emits a canonical URL")
        elif len(parser.canonicals) != 1:
            self.add_finding("error", "seo", "canonical_count", page_url, f"Expected one canonical URL, found {len(parser.canonicals)}")
        elif canonical is None or not self.is_internal(canonical):
            self.add_finding("error", "seo", "canonical_invalid", page_url, f"Canonical URL is invalid or outside the approved website: {parser.canonicals[0]}")
        elif canonical != page_url:
            self.add_finding("error", "seo", "canonical_mismatch", page_url, f"Canonical points to {canonical}")

        for prop, values in parser.open_graph.items():
            if len(values) > 1:
                self.add_finding("error", "seo", "open_graph_duplicate", page_url, f"Open Graph property {prop} occurs {len(values)} times")
        for prop in ("og:url", "og:image"):
            for value in parser.open_graph.get(prop, []):
                url = self.resolve_url(value, page_url)
                if url is None or not self.is_internal(url):
                    self.add_finding("error", "seo", "open_graph_url_invalid", page_url, f"{prop} is invalid or outside the approved website: {value}")
                elif prop == "og:url" and url != page_url:
                    self.add_finding("error", "seo", "open_graph_url_mismatch", page_url, f"og:url points to {url}")
                elif prop == "og:image":
                    self.resources.setdefault(url, set()).add("image")

        for value in parser.json_ld:
            try:
                data = json.loads(value)
            except (json.JSONDecodeError, TypeError) as error:
                self.add_finding("error", "structured_data", "json_ld_invalid", page_url, f"Invalid JSON-LD: {error}")
                continue
            self.check_json_ld(data, page_url, depth)

        link_base = page_url
        if parser.base_href:
            candidate = self.resolve_url(parser.base_href, page_url)
            if candidate is None or not self.is_internal(candidate):
                self.add_finding("error", "link", "base_url_invalid", page_url, f"Base URL leaves the approved website: {parser.base_href}")
            else:
                link_base = candidate

        for link in parser.links:
            value = link["href"]
            if not link["present"] or not value.strip() or value.strip() == "#":
                self.add_finding("warning", "link", "link_target_empty", page_url, "Anchor has no usable link target")
                continue
            if re.match(r"^(?:javascript|data|vbscript):", re.sub(r"[\x00-\x20\x7f]+", "", value), re.I):
                self.add_finding("error", "link", "link_scheme_unsafe", page_url, f"Anchor uses an unsafe URL scheme: {value}")
                continue
            if value.startswith("#"):
                fragment = urllib.parse.unquote(value[1:])
                if fragment not in parser.ids:
                    self.add_finding("warning", "link", "fragment_target_missing", page_url, f"Fragment target does not exist on this page: {value}")
                continue
            if re.match(r"^(?:mailto|tel):", value, re.I):
                continue
            if page_url.startswith("https://") and value.strip().lower().startswith("http://"):
                self.add_finding("error", "link", "mixed_content", page_url, f"HTTP link on HTTPS page: {value}")
            url = self.resolve_url(value, link_base)
            if url is None:
                self.add_finding("warning", "link", "link_target_invalid", page_url, f"Anchor target is not a valid HTTP, HTTPS, mail or telephone URL: {value}")
                continue
            if not self.is_internal(url):
                self.add_finding("warning", "link", "external_link", page_url, f"External link requires editorial review: {url}")
                continue
            self.internal_links.add(url)
            extension = posixpath.splitext(urllib.parse.urlsplit(url).path.lower())[1]
            if extension in DOCUMENT_EXTENSIONS or extension in RESOURCE_EXTENSIONS:
                self.resources.setdefault(url, set()).add("link")
            else:
                self.queue_page(url, depth + 1, "link")

        for image in parser.images:
            src = image["src"]
            if src is None or not src.strip():
                self.add_finding("error", "resource", "image_src_missing", page_url, "Image has no usable src attribute")
            else:
                self.record_resource("image", src, page_url)
            if image["srcset"]:
                for candidate in image["srcset"].split(","):
                    value = candidate.strip().split()[0] if candidate.strip() else ""
                    if value:
                        self.record_resource("image", value, page_url)
                if not image["sizes"]:
                    self.add_finding("warning", "performance", "image_sizes_missing", page_url, f"Responsive image has no sizes attribute: {src or '[missing src]'}")
            width = image["width"]
            height = image["height"]
            if (width is None) != (height is None):
                self.add_finding("error", "performance", "image_dimensions_incomplete", page_url, f"Image has only one dimension: {src or '[missing src]'}")
            elif width is None and not re.search(r"\.svg(?:$|[?#])", src or "", re.I):
                self.add_finding("warning", "performance", "image_dimensions_missing", page_url, f"Raster image has no intrinsic dimensions: {src or '[missing src]'}")
            elif width is not None and (not width.isdigit() or not height.isdigit() or int(width) < 1 or int(height) < 1):
                self.add_finding("error", "performance", "image_dimensions_invalid", page_url, f"Image dimensions are not positive integers: {src or '[missing src]'}")
            if image["fetchpriority"] == "high" and image["loading"] == "lazy":
                self.add_finding("error", "performance", "priority_image_lazy", page_url, f"High-priority image is lazy loaded: {src or '[missing src]'}")
            if not image["alt_present"]:
                self.add_finding("error", "accessibility", "image_alt_missing", page_url, f"Image has no alt attribute: {src or '[missing src]'}")
            elif image["alt"].strip() == "" and image["role"] not in {"presentation", "none"} and "decorative" not in image["class"].lower():
                self.add_finding("warning", "accessibility", "image_alt_empty", page_url, f"Possibly informative image has an empty alt attribute: {src or '[missing src]'}")

        high_priority = [image for image in parser.images if image["fetchpriority"] == "high"]
        if len(high_priority) > 1:
            self.add_finding("warning", "performance", "priority_image_multiple", page_url, f"Found {len(high_priority)} high-priority images; only the actual LCP candidate should be prioritized")

        for kind, value in parser.resources:
            self.record_resource(kind, value, page_url)

        for tag, value in parser.embeds:
            if not value:
                self.add_finding("warning", "resource", "embed_source_missing", page_url, f"{tag} element has no source")
                continue
            url = self.resolve_url(value, page_url)
            if url is not None and not self.is_internal(url):
                self.add_finding("warning", "resource", "third_party_embed", page_url, f"External {tag} embed requires provider-specific consent review: {url}")

        for tag, attribute in parser.unsafe_attributes:
            self.add_finding("error", "security", "unsafe_html_attribute", page_url, f"Unsafe {attribute} attribute found on {tag} element")

        for style in parser.styles:
            for match in re.finditer(r"url\s*\(\s*(['\"]?)(.*?)\1\s*\)", style, re.I):
                value = match.group(2).strip()
                if value:
                    self.record_resource("css", value, page_url)

        heading_text = " ".join(parser.titles + parser.h1)
        if SOFT_404.search(heading_text):
            self.add_finding("warning", "http", "soft_404", page_url, "HTTP 200 page appears to be a not-found page")

        return {
            "title": title,
            "canonical": canonical,
            "noindex": noindex,
            "h1Count": len(parser.h1),
            "descriptionCount": len(parser.descriptions),
        }

    @staticmethod
    def meta_robots(html: str) -> dict[str, list[str]]:
        values: dict[str, list[str]] = {}
        for match in re.finditer(r"<meta\b[^>]*>", html, re.I):
            tag = match.group(0)
            name = re.search(r"\bname\s*=\s*(['\"])(.*?)\1", tag, re.I)
            content = re.search(r"\bcontent\s*=\s*(['\"])(.*?)\1", tag, re.I)
            if name and content:
                values.setdefault(name.group(2).strip().lower(), []).append(content.group(2).strip())
        return values

    def check_json_ld(self, data: Any, page_url: str, depth: int) -> None:
        entries = data if isinstance(data, list) else [data]
        for entry in entries:
            if not isinstance(entry, dict):
                continue
            graph = entry.get("@graph")
            if isinstance(graph, list):
                self.check_json_ld(graph, page_url, depth)
            types = entry.get("@type", [])
            if isinstance(types, str):
                types = [types]
            if "BreadcrumbList" not in types:
                continue
            elements = entry.get("itemListElement")
            if not isinstance(elements, list) or not elements:
                self.add_finding("error", "structured_data", "breadcrumb_invalid", page_url, "BreadcrumbList has no itemListElement entries")
                continue
            positions = []
            for item in elements:
                if not isinstance(item, dict):
                    self.add_finding("error", "structured_data", "breadcrumb_invalid", page_url, "BreadcrumbList contains a non-object entry")
                    continue
                positions.append(item.get("position"))
                value = item.get("item")
                if isinstance(value, dict):
                    value = value.get("@id")
                if not isinstance(value, str):
                    self.add_finding("error", "structured_data", "breadcrumb_url_missing", page_url, "Breadcrumb item has no URL")
                    continue
                url = self.resolve_url(value, page_url)
                if url is None or not self.is_internal(url):
                    self.add_finding("error", "structured_data", "breadcrumb_url_invalid", page_url, f"Breadcrumb URL is outside the approved website: {value}")
                    continue
                self.breadcrumb_urls.add(url)
                self.queue_page(url, min(depth + 1, self.max_depth), "breadcrumb")
            if positions != list(range(1, len(elements) + 1)):
                self.add_finding("error", "structured_data", "breadcrumb_positions_invalid", page_url, "Breadcrumb positions are not consecutive")

    def crawl_page(self, url: str, depth: int, source: str) -> None:
        result = self.fetch(url)
        status = result["status"]
        page = {
            "url": url,
            "finalUrl": result["final_url"],
            "status": status,
            "depth": depth,
            "source": source,
            "redirects": len(result["hops"]),
            "contentType": result["headers"].get("content-type", ""),
        }
        self.pages.append(page)
        self._page_results[url] = page
        self._page_results[result["final_url"]] = page

        if source == "link" and result["hops"]:
            self.add_finding("warning", "link", "internal_link_redirect", url, f"Internal link redirects to {result['final_url']}")

        if status == 0:
            self.add_finding("error", "http", "request_failed", url, f"Request failed or timed out: {result.get('error') or 'unknown error'}")
            return
        if status >= 400:
            self.add_finding("error", "http", "http_error", url, f"HTTP {status}")
            return
        if 300 <= status < 400:
            return
        if result.get("truncated"):
            self.add_finding("warning", "crawl", "body_truncated", result["final_url"], "Response body exceeded the 2 MB inspection limit")

        content_type = result["headers"].get("content-type", "").lower()
        body_start = result["body"].lstrip()[:100].lower()
        if "text/html" in content_type or body_start.startswith((b"<!doctype html", b"<html")):
            page.update(self.parse_html(result, depth))

    def inspect_resources(self) -> None:
        for index, (url, kinds) in enumerate(sorted(self.resources.items())):
            if index >= self.max_resources:
                self.add_finding("warning", "crawl", "resource_limit", self.start_url, f"Resource check limit {self.max_resources} reached")
                break
            result = self.fetch(url, "HEAD", 0)
            status = result["status"]
            if status in {405, 501}:
                result = self.fetch(url, "GET", 1)
                status = result["status"]
            if status == 0 or status >= 400:
                category = "image" if "image" in kinds else "asset"
                code = "image_missing" if category == "image" else "asset_missing"
                message = f"Internal {category} returned HTTP {status or 'request failure'}"
                self.add_finding("error", "resource", code, url, message)

    def inspect_robots(self) -> None:
        url = self.endpoint_url("robots.txt")
        result = self.fetch(url, body_limit=200_000)
        body = result["body"].decode("utf-8", errors="replace") if result["status"] == 200 else ""
        sitemaps = []
        disallow_all = False
        for line in body.splitlines():
            key, separator, value = line.partition(":")
            if not separator:
                continue
            if key.strip().lower() == "sitemap":
                candidate = self.resolve_url(value.strip(), url)
                if candidate is None or not self.is_internal(candidate):
                    self.add_finding("error", "robots", "robots_sitemap_invalid", url, f"Sitemap leaves the approved website: {value.strip()}")
                else:
                    sitemaps.append(candidate)
            if key.strip().lower() == "disallow" and value.strip() == "/":
                disallow_all = True
        if result["status"] != 200:
            self.add_finding("error", "robots", "robots_missing", url, f"robots.txt returned HTTP {result['status'] or 'request failure'}")
        self.robots = {"url": url, "status": result["status"], "sitemaps": sitemaps, "disallowAll": disallow_all}
        self.sitemap_urls = sitemaps or [self.endpoint_url("sitemap.xml")]

    def inspect_sitemaps(self) -> None:
        pending = deque(self.sitemap_urls)
        inspected: set[str] = set()
        entries: set[str] = set()
        while pending and len(inspected) < 20:
            url = pending.popleft()
            if url in inspected:
                continue
            inspected.add(url)
            result = self.fetch(url, body_limit=4_000_000)
            self.sitemap_statuses[url] = result["status"]
            if result["status"] == 404:
                continue
            if result["status"] != 200:
                self.add_finding("error", "sitemap", "sitemap_http_error", url, f"Sitemap returned HTTP {result['status'] or 'request failure'}")
                continue
            if result["hops"]:
                self.add_finding("error", "sitemap", "sitemap_redirect", url, "Sitemap URL redirects")
            try:
                root = ET.fromstring(result["body"])
            except ET.ParseError as error:
                self.add_finding("error", "sitemap", "sitemap_invalid", url, f"Invalid XML sitemap: {error}")
                continue
            root_type = root.tag.rsplit("}", 1)[-1]
            if root_type not in {"urlset", "sitemapindex"}:
                self.add_finding("error", "sitemap", "sitemap_invalid", url, f"Unexpected sitemap root element: {root_type}")
                continue
            locations = [element.text.strip() for element in root.iter() if element.tag.rsplit("}", 1)[-1] == "loc" and element.text]
            for value in locations:
                candidate = self.resolve_url(value, url)
                if candidate is None or not self.is_internal(candidate):
                    self.add_finding("error", "sitemap", "sitemap_foreign_url", url, f"Sitemap contains an unapproved URL: {value}")
                    continue
                if root_type == "sitemapindex":
                    if candidate not in inspected:
                        pending.append(candidate)
                        if candidate not in self.sitemap_urls:
                            self.sitemap_urls.append(candidate)
                    continue
                if candidate in entries:
                    self.add_finding("error", "sitemap", "sitemap_duplicate", candidate, "Sitemap contains this URL more than once")
                    continue
                entries.add(candidate)
                path = urllib.parse.urlsplit(candidate).path
                if urllib.parse.urlsplit(candidate).query or BACKEND_PATH.search(path) or SEARCH_PATH.search(path):
                    self.add_finding("error", "sitemap", "sitemap_private_url", candidate, "Sitemap contains a search, backend or parameterized URL")
                    continue
                self.sitemap_entries.append(candidate)
                self.queue_page(candidate, 0, "sitemap")
        if pending:
            self.add_finding("warning", "sitemap", "sitemap_limit", self.start_url, "Sitemap file limit 20 reached")

    def endpoint_url(self, filename: str) -> str:
        path = (self.base_path + "/" + filename) if self.base_path else "/" + filename
        return urllib.parse.urlunsplit((self.start_parts.scheme, self.start_parts.netloc, path, "", ""))

    def validate_indexing(self) -> bool | None:
        indexable = self.expected_indexable
        homepage = self._page_results.get(self.start_url)
        sitemap_available = bool(self.sitemap_entries)
        if indexable is None:
            if homepage and homepage.get("noindex"):
                indexable = False
            elif self.robots.get("sitemaps") or sitemap_available:
                indexable = True
            else:
                self.add_finding("warning", "robots", "indexability_unknown", self.start_url, "Indexability could not be inferred; use --indexable=yes or --indexable=no")

        for page in self.pages:
            if "noindex" not in page:
                continue
            if indexable is True and page["noindex"]:
                self.add_finding("error", "robots", "unexpected_noindex", page["finalUrl"], "Explicitly indexable website emits noindex")
            if indexable is False and not page["noindex"]:
                self.add_finding("error", "robots", "noindex_missing", page["finalUrl"], "Non-indexable website page is missing noindex")

        if indexable is True and not self.robots.get("sitemaps"):
            self.add_finding("error", "robots", "robots_sitemap_missing", self.robots.get("url", self.start_url), "Indexable website does not advertise a sitemap")
        if indexable is True and not any(status == 200 for status in self.sitemap_statuses.values()):
            self.add_finding("error", "sitemap", "sitemap_missing", self.sitemap_urls[0], "Indexable website has no reachable sitemap")
        if indexable is False:
            if self.robots.get("sitemaps"):
                self.add_finding("error", "robots", "robots_sitemap_on_noindex", self.robots["url"], "Non-indexable website advertises a sitemap")
            if self.robots.get("disallowAll"):
                self.add_finding("error", "robots", "noindex_blocked_by_robots", self.robots["url"], "Disallow: / prevents crawlers from reading noindex")
            if any(status == 200 for status in self.sitemap_statuses.values()):
                self.add_finding("error", "sitemap", "sitemap_on_noindex", self.sitemap_urls[0], "Non-indexable website exposes a sitemap")
        return indexable

    def validate_sitemap_entries(self) -> None:
        sitemap_set = set(self.sitemap_entries)
        for url in self.sitemap_entries:
            page = self._page_results.get(url)
            if not page:
                continue
            if page["redirects"]:
                self.add_finding("error", "sitemap", "sitemap_redirect_entry", url, f"Sitemap URL redirects to {page['finalUrl']}")
            if page.get("noindex"):
                self.add_finding("error", "sitemap", "sitemap_noindex_entry", url, "Sitemap URL is noindex")
            if page.get("canonical") and page["canonical"] != url:
                self.add_finding("error", "sitemap", "sitemap_canonical_mismatch", url, f"Canonical points to {page['canonical']}")
        for page in self.pages:
            if page.get("status") == 200 and page.get("noindex") is False and page.get("canonical") == page.get("finalUrl"):
                if sitemap_set and page["finalUrl"] not in sitemap_set:
                    self.add_finding("warning", "sitemap", "page_missing_from_sitemap", page["finalUrl"], "Indexable canonical page is not listed in the sitemap")
        for url in self.breadcrumb_urls:
            page = self._page_results.get(url)
            if page and page["redirects"]:
                self.add_finding("error", "structured_data", "breadcrumb_redirect", url, f"Breadcrumb URL redirects to {page['finalUrl']}")

    def run(self) -> dict[str, Any]:
        self.inspect_robots()
        self.inspect_sitemaps()
        self.queue_page(self.start_url, 0, "start")
        for value in self.extra_urls:
            candidate = self.resolve_url(value, self.start_url)
            if candidate is None or not self.is_internal(candidate):
                self.add_finding("error", "crawl", "extra_url_invalid", self.start_url, f"Additional URL is outside the approved website: {value}")
            else:
                self.queue_page(candidate, 0, "provided")

        while self._queue and len(self.pages) < self.max_pages:
            url, depth, source = self._queue.popleft()
            self.crawl_page(url, depth, source)
        if self._queue:
            self.add_finding("warning", "crawl", "page_limit", self.start_url, f"Maximum page count {self.max_pages} reached with {len(self._queue)} URLs pending")

        self.inspect_resources()
        effective_indexable = self.validate_indexing()
        self.validate_sitemap_entries()
        counts = Counter(finding["category"] for finding in self.findings)
        severity = Counter(finding["severity"] for finding in self.findings)
        return {
            "startUrl": self.start_url,
            "indexable": effective_indexable,
            "options": {
                "maxPages": self.max_pages,
                "maxDepth": self.max_depth,
                "timeout": self.timeout,
                "maxResources": self.max_resources,
            },
            "summary": {
                "pages": len(self.pages),
                "internalLinks": len(self.internal_links),
                "resources": len(self.resources),
                "redirects": len(self.redirects),
                "errors": severity["error"],
                "warnings": severity["warning"],
                "httpFindings": counts["http"],
                "redirectFindings": counts["redirect"],
                "seoFindings": counts["seo"] + counts["structured_data"],
                "resourceFindings": counts["resource"],
                "accessibilityFindings": counts["accessibility"],
            },
            "findings": sorted(self.findings, key=lambda item: (item["severity"] != "error", item["category"], item["url"], item["code"])),
            "redirects": self.redirects,
            "pages": self.pages,
            "robots": self.robots,
            "sitemap": {"urls": self.sitemap_urls, "entries": self.sitemap_entries},
            "limitations": [
                "The crawler uses GET for pages and HEAD for internal media and assets; it never submits forms.",
                "Soft-404 and potentially informative empty-alt findings are heuristic and require review.",
                "Historical URLs can only be checked when linked, listed in the sitemap, or supplied with --paths.",
                "External resources are inspected as references but are not downloaded.",
            ],
        }


def render_text(report: dict[str, Any]) -> str:
    summary = report["summary"]
    lines = [
        f"DEWAWI site check: {report['startUrl']}",
        f"Indexable: {report['indexable'] if report['indexable'] is not None else 'unknown'}",
        f"Pages: {summary['pages']} | Internal links: {summary['internalLinks']} | Resources: {summary['resources']} | Redirects: {summary['redirects']}",
        f"Errors: {summary['errors']} | Warnings: {summary['warnings']}",
    ]
    if report["findings"]:
        lines.append("Findings:")
        for finding in report["findings"]:
            lines.append(f"- {finding['severity'].upper()} [{finding['category']}/{finding['code']}] {finding['url']}: {finding['message']}")
    else:
        lines.append("No findings.")
    lines.append("Limitations:")
    lines.extend(f"- {item}" for item in report["limitations"])
    return "\n".join(lines) + "\n"


def read_paths(filename: str | None) -> list[str]:
    if filename is None:
        return []
    with open(filename, encoding="utf-8") as handle:
        return [line.strip() for line in handle if line.strip() and not line.lstrip().startswith("#")]


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--url", required=True, help="Explicitly approved website homepage URL")
    parser.add_argument("--max-pages", type=int, default=200)
    parser.add_argument("--max-depth", type=int, default=20)
    parser.add_argument("--max-resources", type=int, default=1000)
    parser.add_argument("--timeout", type=float, default=10.0)
    parser.add_argument("--indexable", choices=["auto", "yes", "no"], default="auto")
    parser.add_argument("--paths", help="Optional UTF-8 file with additional relative or same-site URLs")
    parser.add_argument("--format", choices=["text", "json"], default="text")
    args = parser.parse_args(argv)
    expected = {"auto": None, "yes": True, "no": False}[args.indexable]
    try:
        crawler = SiteCrawler(
            args.url,
            max_pages=args.max_pages,
            max_depth=args.max_depth,
            timeout=args.timeout,
            expected_indexable=expected,
            extra_urls=read_paths(args.paths),
            max_resources=args.max_resources,
        )
        report = crawler.run()
    except (ValueError, OSError) as error:
        parser.error(str(error))
    if args.format == "json":
        print(json.dumps(report, ensure_ascii=False, indent=2, sort_keys=True))
    else:
        print(render_text(report), end="")
    return 1 if report["summary"]["errors"] else 0


if __name__ == "__main__":
    raise SystemExit(main())
