#!/usr/bin/env python3
"""Run public navigation and accessibility regressions with real browser input."""

from __future__ import annotations

import importlib.util
import json
import shutil
import socket
import subprocess
import sys
import threading
import time
import urllib.error
import urllib.request
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
FRONTEND_TEST = ROOT / "tests/site/frontend-assets-http.py"
CHROMEDRIVER = shutil.which("chromedriver")
ELEMENT_KEY = "element-6066-11e4-a52e-4f735466cecf"
KEYS = {"tab": "\ue004", "enter": "\ue007", "shift": "\ue008", "escape": "\ue00c", "space": "\ue00d"}


def load_frontend_module():
    spec = importlib.util.spec_from_file_location("dewawi_frontend_assets", FRONTEND_TEST)
    if spec is None or spec.loader is None:
        raise RuntimeError("Unable to load frontend fixture")
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


def free_port() -> int:
    with socket.socket() as connection:
        connection.bind(("127.0.0.1", 0))
        return int(connection.getsockname()[1])


class WebDriver:
    def __init__(self) -> None:
        if not CHROMEDRIVER:
            raise RuntimeError("chromedriver is not installed")
        self.port = free_port()
        self.process = subprocess.Popen(
            [CHROMEDRIVER, f"--port={self.port}", "--silent"],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
        )
        self.session = ""
        self.wait_until_ready()
        response = self.request("POST", "/session", {
            "capabilities": {"alwaysMatch": {
                "browserName": "chrome",
                "goog:chromeOptions": {"args": ["--headless", "--no-sandbox", "--disable-gpu", "--disable-dev-shm-usage"]},
            }},
        })
        value = response.get("value", {})
        self.session = value.get("sessionId") or response.get("sessionId", "")
        if not self.session:
            self.close()
            raise RuntimeError(f"Unable to create Chromium session: {response}")

    def wait_until_ready(self) -> None:
        for _ in range(50):
            try:
                if self.request("GET", "/status").get("value", {}).get("ready"):
                    return
            except (urllib.error.URLError, ConnectionError):
                pass
            time.sleep(.1)
        raise RuntimeError("chromedriver did not become ready")

    def request(self, method: str, path: str, payload: dict | None = None) -> dict:
        data = None if payload is None else json.dumps(payload).encode()
        request = urllib.request.Request(
            f"http://127.0.0.1:{self.port}{path}",
            data=data,
            method=method,
            headers={"Content-Type": "application/json"},
        )
        with urllib.request.urlopen(request, timeout=20) as response:
            result = json.loads(response.read().decode())
        error = result.get("value", {})
        if isinstance(error, dict) and error.get("error"):
            raise RuntimeError(f"WebDriver {error['error']}: {error.get('message', '')}")
        return result

    def command(self, method: str, path: str, payload: dict | None = None):
        return self.request(method, f"/session/{self.session}{path}", payload).get("value")

    def navigate(self, url: str) -> None:
        self.command("POST", "/url", {"url": url})
        self.wait("return document.readyState === 'complete'")

    def execute(self, script: str, args: list | None = None):
        return self.command("POST", "/execute/sync", {"script": script, "args": args or []})

    def wait(self, script: str, timeout: float = 3) -> None:
        deadline = time.monotonic() + timeout
        while time.monotonic() < deadline:
            if self.execute(script):
                return
            time.sleep(.05)
        raise AssertionError(f"Browser condition timed out: {script}")

    def element(self, selector: str) -> str:
        value = self.command("POST", "/element", {"using": "css selector", "value": selector})
        return value[ELEMENT_KEY]

    def click(self, selector: str) -> None:
        self.command("POST", f"/element/{self.element(selector)}/click", {})

    def keys(self, *keys: str) -> None:
        actions = []
        for key in keys:
            value = KEYS[key]
            actions.extend([{"type": "keyDown", "value": value}, {"type": "keyUp", "value": value}])
        self.command("POST", "/actions", {"actions": [{"type": "key", "id": "keyboard", "actions": actions}]})
        self.command("DELETE", "/actions")

    def shift_tab(self) -> None:
        self.command("POST", "/actions", {"actions": [{"type": "key", "id": "keyboard", "actions": [
            {"type": "keyDown", "value": KEYS["shift"]},
            {"type": "keyDown", "value": KEYS["tab"]},
            {"type": "keyUp", "value": KEYS["tab"]},
            {"type": "keyUp", "value": KEYS["shift"]},
        ]}]})
        self.command("DELETE", "/actions")

    def emulate_viewport(self, width: int, height: int = 900) -> None:
        self.command("POST", "/goog/cdp/execute", {
            "cmd": "Emulation.setDeviceMetricsOverride",
            "params": {"width": width, "height": height, "deviceScaleFactor": 1, "mobile": False},
        })

    def reduced_motion(self, enabled: bool) -> None:
        features = [{"name": "prefers-reduced-motion", "value": "reduce"}] if enabled else []
        self.command("POST", "/goog/cdp/execute", {"cmd": "Emulation.setEmulatedMedia", "params": {"features": features}})

    def cookies(self) -> list[dict]:
        return self.command("GET", "/cookie")

    def add_cookie(self, name: str, value: str) -> None:
        self.command("POST", "/cookie", {"cookie": {"name": name, "value": value, "path": "/", "sameSite": "Lax"}})

    def close(self) -> None:
        if self.session:
            try:
                self.command("DELETE", "")
            except Exception:
                pass
            self.session = ""
        if self.process.poll() is None:
            self.process.terminate()
            try:
                self.process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                self.process.kill()


def check(condition: bool, message: str, counter: list[int]) -> None:
    counter[0] += 1
    if not condition:
        raise AssertionError(message)


def main() -> int:
    frontend = load_frontend_module()
    frontend.FixtureHandler.assets = frontend.load_assets()
    server = frontend.ThreadingHTTPServer(("127.0.0.1", 0), frontend.FixtureHandler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    origin = f"http://127.0.0.1:{server.server_port}"
    driver = WebDriver()
    checks = [0]
    try:
        for width in [320, 375, 500, 768, 1365]:
            driver.emulate_viewport(width)
            driver.navigate(origin + f"/?manual=1&width={width}")
            metrics = driver.execute(r"""
                function rgb(value){const parts=(value.match(/[\d.]+/g)||[]).map(Number);const alpha=parts.length>3?parts[3]:1;return parts.slice(0,3).map(channel=>channel*alpha+255*(1-alpha))}
                function luminance(color){return color.map(value=>{value/=255;return value<=.03928?value/12.92:Math.pow((value+.055)/1.055,2.4)}).reduce((sum,value,index)=>sum+value*[.2126,.7152,.0722][index],0)}
                function contrast(foreground,background){const values=[luminance(rgb(foreground)),luminance(rgb(background))].sort((a,b)=>b-a);return (values[0]+.05)/(values[1]+.05)}
                const controls=[...document.querySelectorAll('[aria-controls]')];
                const ids=[...document.querySelectorAll('[id]')].map(node=>node.id);
                const toggles=[...document.querySelectorAll('.navbar-toggler,.dw-mega-toggle,.dw-submenu-toggle,[data-carousel-toggle]')];
                const content=[...document.querySelectorAll('.navbar-brand,#breadcrumb,.product-card,footer')];
                const navStyle=getComputedStyle(document.querySelector('.dw-mega-toggle'));
                const footerStyle=getComputedStyle(document.querySelector('footer'));
                return {
                    width: innerWidth,
                    overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
                    landmarks: document.querySelectorAll('main').length === 1 && !!document.querySelector('nav[aria-label="Main navigation"]') && !!document.querySelector('footer nav[aria-label="Footer navigation"]'),
                    controls: controls.every(node=>node.getAttribute('aria-controls') && document.getElementById(node.getAttribute('aria-controls'))),
                    unique: ids.length === new Set(ids).size,
                    hiddenFocusable: [...document.querySelectorAll('[hidden] a,[hidden] button,[hidden] input')].some(node=>node.tabIndex >= 0 && node.getClientRects().length),
                    targetSize: toggles.filter(node=>node.getClientRects().length).every(node=>{const box=node.getBoundingClientRect();return box.width>=24&&box.height>=24}),
                    labels: [...document.querySelectorAll('input:not([type=hidden]),select,textarea')].every(node=>node.labels&&node.labels.length||node.getAttribute('aria-label')),
                    contrast: contrast(navStyle.color,'rgb(255,255,255)')>=4.5&&contrast(footerStyle.color,footerStyle.backgroundColor)>=4.5,
                    contentFits: content.every(node=>{const box=node.getBoundingClientRect();return box.left>=-1&&box.right<=document.documentElement.clientWidth+1}),
                    theme: document.body.dataset.theme==='default'&&document.body.classList.contains('dw-theme-default')&&!!document.querySelector('.navbar-brand img[width][height]')
                };
            """)
            check(metrics["width"] == width, f"Viewport emulation failed at {width}px", checks)
            check(not metrics["overflow"], f"Horizontal overflow at {width}px", checks)
            check(metrics["landmarks"], f"Landmarks failed at {width}px", checks)
            check(metrics["controls"] and metrics["unique"], f"ARIA controls or IDs failed at {width}px", checks)
            check(not metrics["hiddenFocusable"], f"Hidden controls are focusable at {width}px", checks)
            check(metrics["targetSize"], f"Control target is smaller than 24px at {width}px", checks)
            check(metrics["labels"], f"Form control label missing at {width}px", checks)
            check(metrics["contrast"], f"Navigation or footer text contrast failed at {width}px", checks)
            check(metrics["contentFits"] and metrics["theme"], f"Logo, theme, breadcrumb, product card or footer failed at {width}px", checks)
            if width < 1200:
                driver.click(".navbar-toggler")
                driver.wait("return document.getElementById('navbarNav').classList.contains('show')")
            check(driver.execute("return ['.dw-header-search','#navbar-cart'].every(selector=>{const node=document.querySelector(selector);if(!node||!node.getClientRects().length)return false;const box=node.getBoundingClientRect();return box.left>=-1&&box.right<=document.documentElement.clientWidth+1})&&document.documentElement.scrollWidth<=document.documentElement.clientWidth+1"), f"Open header search or cart failed at {width}px", checks)

        driver.emulate_viewport(375)
        driver.navigate(origin + "/?manual=1&keyboard=1")
        driver.keys("tab")
        check(driver.execute("return document.activeElement.classList.contains('dw-skip-link')"), "Tab did not reach the skip link first", checks)
        focus = driver.execute("const s=getComputedStyle(document.activeElement);return {width:parseFloat(s.outlineWidth),style:s.outlineStyle,visible:getComputedStyle(document.activeElement).transform!=='none'}")
        check(focus["width"] >= 3 and focus["style"] != "none" and focus["visible"], "Skip link focus is not visible", checks)
        check(driver.execute("const c=getComputedStyle(document.activeElement).outlineColor.match(/[\\d.]+/g).map(Number);const l=x=>{x/=255;return x<=.03928?x/12.92:Math.pow((x+.055)/1.055,2.4)};const a=.2126*l(c[0])+.7152*l(c[1])+.0722*l(c[2]);return (1.05/(a+.05))>=3"), "Focus indicator contrast is below 3:1", checks)
        driver.keys("enter")
        check(driver.execute("return document.activeElement.id==='main-content'"), "Skip link did not focus main content", checks)
        driver.navigate(origin + "/?manual=1&keyboard=2")
        driver.keys("tab", "tab", "tab")
        check(driver.execute("return document.activeElement.classList.contains('navbar-toggler')"), "Tab order did not reach mobile menu button", checks)
        driver.shift_tab()
        check(driver.execute("return document.activeElement.classList.contains('navbar-brand')"), "Shift+Tab order is incorrect", checks)
        driver.keys("tab", "enter")
        driver.wait("return document.getElementById('navbarNav').classList.contains('show')")
        check(driver.execute("return document.querySelector('.navbar-toggler').getAttribute('aria-expanded')==='true'"), "Mobile menu ARIA state did not open", checks)
        driver.keys("tab", "space")
        check(driver.execute("return document.querySelector('.dw-mega-toggle').getAttribute('aria-expanded')==='true'&&!document.getElementById('mega-products').hidden"), "Space did not open mega menu", checks)
        driver.keys("tab")
        check(driver.execute("return document.activeElement.closest('#mega-products')!==null"), "Tab did not enter open mega menu", checks)
        driver.keys("escape")
        check(driver.execute("return document.activeElement.classList.contains('dw-mega-toggle')&&document.getElementById('mega-products').hidden"), "Mega-menu Escape focus return failed", checks)
        driver.keys("tab", "tab", "enter")
        check(driver.execute("return document.querySelector('.dw-submenu-toggle').getAttribute('aria-expanded')==='true'&&!document.getElementById('category-menu').hidden"), "Enter did not open category submenu", checks)
        driver.keys("tab")
        check(driver.execute("return document.activeElement.closest('#category-menu')!==null"), "Tab did not enter category submenu", checks)
        driver.keys("escape")
        check(driver.execute("return document.activeElement.classList.contains('dw-submenu-toggle')&&document.getElementById('category-menu').hidden"), "Category Escape focus return failed", checks)
        driver.keys("escape")
        driver.wait("return !document.getElementById('navbarNav').classList.contains('show')&&document.activeElement.classList.contains('navbar-toggler')&&document.querySelector('.navbar-toggler').getAttribute('aria-expanded')==='false'")
        check(driver.execute("return document.activeElement.classList.contains('navbar-toggler')&&document.querySelector('.navbar-toggler').getAttribute('aria-expanded')==='false'"), "Mobile menu Escape focus return failed", checks)

        driver.emulate_viewport(1365)
        driver.navigate(origin + "/?manual=1&dialog=1")
        driver.click("#gallery-button")
        driver.wait("return document.getElementById('imageModal').classList.contains('show')&&document.getElementById('imageModal').contains(document.activeElement)")
        check(driver.execute("return document.getElementById('imageModal').contains(document.activeElement)&&document.getElementById('imageModal').getAttribute('aria-labelledby')==='modal-label'"), "Modal did not receive focus or an accessible name", checks)
        for _ in range(6):
            driver.keys("tab")
            check(driver.execute("return document.getElementById('imageModal').contains(document.activeElement)"), "Modal focus escaped", checks)
        driver.keys("escape")
        driver.wait("return !document.getElementById('imageModal').classList.contains('show')&&document.activeElement.id==='gallery-button'")
        check(driver.execute("return document.activeElement.id==='gallery-button'"), "Modal did not return focus", checks)

        driver.execute("document.querySelector('.quantity-input').value='3';document.querySelector('.quantity-input').dispatchEvent(new Event('change',{bubbles:true}))")
        driver.wait("return document.getElementById('cart-count').textContent==='4'")
        check(driver.execute("return !document.querySelector('#cart-items img,#cart-items script')&&document.getElementById('cart-items').textContent.includes('<img src=x onerror=alert(1)>')"), "Cart data created executable markup", checks)

        driver.execute("document.querySelector('#inquiry-form input').value=''")
        driver.click("#inquiry-form button[type=submit]")
        check(driver.execute("return document.activeElement===document.querySelector('#inquiry-form input')&&document.querySelector('#inquiry-form input').matches(':invalid')"), "Invalid form did not focus its native invalid field", checks)
        driver.execute("document.querySelector('#inquiry-form input').value='Request';submitStep(document.getElementById('inquiry-form'),1)")
        driver.wait("return !!document.getElementById('saved-field-error')")
        check(driver.execute("return document.activeElement.id==='saved-field'&&document.getElementById('saved-field').getAttribute('aria-invalid')==='true'&&document.getElementById('saved-field-error').getAttribute('role')==='alert'"), "Dynamic form error was not linked, announced and focused", checks)

        driver.reduced_motion(True)
        driver.navigate(origin + "/?manual=1&motion=reduce")
        check(driver.execute("return document.querySelector('[data-carousel-toggle]').getAttribute('data-paused')==='true'&&document.querySelector('[data-carousel-toggle]').textContent.trim()==='Play'"), "Reduced motion did not pause automatic carousel", checks)
        check(driver.execute("return parseFloat(getComputedStyle(document.querySelector('.carousel-item')).transitionDuration)<=0.001"), "Reduced-motion CSS did not suppress transitions", checks)

        driver.reduced_motion(False)
        driver.emulate_viewport(375)
        consent_url = origin + "/consent-fixture?client=3&shop=7&env=production"
        driver.navigate(consent_url)
        check(driver.execute("return document.body.dataset.consentState==='unknown'&&!document.querySelector('[data-consent-panel]').hidden&&document.querySelector('[data-consent-open]').getAttribute('aria-expanded')==='true'"), "First visit did not show undecided consent controls", checks)
        check(driver.execute("return document.activeElement.matches('[data-consent-panel]')"), "Initial consent controls did not receive focus", checks)
        check(driver.execute("return !window.consentTrackingLoaded&&!performance.getEntriesByName(location.origin+'/tracking.js').length"), "Tracking loaded before consent", checks)
        check(driver.execute("return [...document.querySelectorAll('[data-consent-panel] button[type=submit]')].every(node=>{const box=node.getBoundingClientRect();return box.height>=44&&box.left>=0&&box.right<=innerWidth})&&document.documentElement.scrollWidth<=innerWidth"), "Mobile consent controls overflow or have small targets", checks)
        check(driver.execute("return document.querySelector('[data-consent-panel]').getAttribute('role')==='dialog'&&document.querySelector('[data-consent-panel]').getAttribute('aria-modal')==='false'&&document.querySelector('[data-consent-panel]').getAttribute('aria-labelledby')==='site-consent-title'"), "Consent semantics are incomplete", checks)
        driver.click('button[name="consent"][value="accepted"]')
        driver.wait("return document.readyState==='complete'&&document.body.dataset.consentState==='accepted'&&window.consentTrackingLoaded===true")
        check(driver.execute("return document.querySelector('[data-consent-panel]').hidden&&performance.getEntriesByName(location.origin+'/tracking.js').length===1"), "Accepted analytics did not load after controlled reload", checks)
        cookie = next((item for item in driver.cookies() if item["name"] == "dw_site_consent_3_7"), None)
        check(bool(cookie) and cookie["value"] == "1:accepted:3:7" and cookie.get("httpOnly") is True and cookie.get("sameSite") == "Lax" and cookie.get("path") == "/", "Consent cookie attributes or value are incorrect", checks)
        driver.click("[data-consent-open]")
        check(driver.execute("return !document.querySelector('[data-consent-panel]').hidden&&document.activeElement.matches('[data-consent-panel]')&&document.querySelector('[data-consent-open]').getAttribute('aria-expanded')==='true'"), "Cookie settings did not reopen with focus", checks)
        driver.keys("escape")
        check(driver.execute("return document.querySelector('[data-consent-panel]').hidden&&document.activeElement.matches('[data-consent-open]')&&document.querySelector('[data-consent-open]').getAttribute('aria-expanded')==='false'"), "Consent Escape did not close settings and restore focus", checks)
        driver.click("[data-consent-open]")
        driver.click('button[name="consent"][value="declined"]')
        driver.wait("return document.readyState==='complete'&&document.body.dataset.consentState==='declined'")
        check(driver.execute("return !window.consentTrackingLoaded&&!performance.getEntriesByName(location.origin+'/tracking.js').length"), "Tracking remained after consent revocation", checks)
        driver.navigate(consent_url)
        check(driver.execute("return document.body.dataset.consentState==='declined'&&document.querySelector('[data-consent-panel]').hidden&&!window.consentTrackingLoaded"), "Declined consent did not persist across reload", checks)

        subdirectory_consent_url = origin + "/site/consent-fixture?client=6&shop=9&env=production"
        driver.navigate(subdirectory_consent_url)
        check(driver.execute("return new URL(document.querySelector('[data-consent-panel] form').action).pathname==='/site/cookie-consent-fixture'&&document.querySelector('input[name=return]').value.startsWith('/site/consent-fixture')"), "Subdirectory consent form action or return path is invalid", checks)
        driver.click('button[name="consent"][value="accepted"]')
        driver.wait("return location.pathname==='/site/consent-fixture'&&document.body.dataset.consentState==='accepted'&&window.consentTrackingLoaded===true")
        subdirectory_cookie = next((item for item in driver.cookies() if item["name"] == "dw_site_consent_6_9"), None)
        check(bool(subdirectory_cookie) and subdirectory_cookie["value"] == "1:accepted:6:9" and subdirectory_cookie.get("path") == "/site/", "Subdirectory consent acceptance or cookie path is incorrect", checks)
        driver.click("[data-consent-open]")
        driver.click('button[name="consent"][value="declined"]')
        driver.wait("return location.pathname==='/site/consent-fixture'&&document.body.dataset.consentState==='declined'&&!window.consentTrackingLoaded")
        check(driver.execute("return document.querySelector('[data-consent-panel]').hidden"), "Subdirectory consent decline did not return to the website", checks)

        driver.navigate(origin + "/consent-fixture?client=3&shop=8&env=production")
        check(driver.execute("return document.body.dataset.consentState==='unknown'&&!window.consentTrackingLoaded"), "Consent leaked into another shop", checks)
        driver.add_cookie("dw_site_consent_3_8", "invalid")
        driver.navigate(origin + "/consent-fixture?client=3&shop=8&env=production")
        check(driver.execute("return document.body.dataset.consentState==='unknown'&&!window.consentTrackingLoaded"), "Malformed consent cookie was accepted", checks)
        driver.add_cookie("dw_site_consent_3_8", "1:accepted:3:7")
        driver.navigate(origin + "/consent-fixture?client=3&shop=8&env=production")
        check(driver.execute("return document.body.dataset.consentState==='unknown'&&!window.consentTrackingLoaded"), "Consent copied from another shop was accepted", checks)
        driver.add_cookie("dw_site_consent_3_8", "1:accepted:3:8")
        for environment in ("development", "staging"):
            driver.navigate(origin + f"/consent-fixture?client=3&shop=8&env={environment}")
            check(driver.execute("return document.body.dataset.consentState==='accepted'&&!window.consentTrackingLoaded&&!performance.getEntriesByName(location.origin+'/tracking.js').length"), f"Tracking loaded in {environment}", checks)
        driver.navigate(origin + "/consent-fixture?client=4&shop=7&env=production")
        check(driver.execute("return document.body.dataset.consentState==='unknown'&&!window.consentTrackingLoaded"), "Consent leaked into another tenant", checks)
        check(driver.execute("return document.getElementById('shop-cart').href.endsWith('/cart')&&document.getElementById('shop-checkout').href.endsWith('/checkout')"), "Necessary shop paths depend on analytics consent", checks)
    finally:
        driver.close()
        server.shutdown()
        server.server_close()
        thread.join(timeout=5)

    print(f"PASS: {checks[0]} Chromium keyboard, focus, ARIA, modal, form, cart and responsive accessibility checks")
    print("WCAG note: automated checks do not replace manual screen-reader, contrast, cognition or real-device review")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
