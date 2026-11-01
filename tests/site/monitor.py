#!/usr/bin/env python3
"""Check explicitly configured DEWAWI HTTP endpoints and TLS certificates."""

from __future__ import annotations

import argparse
import datetime as dt
import http.client
import json
import math
import queue
import re
import socket
import ssl
import threading
import time
import urllib.parse
from pathlib import Path
from typing import Any, Callable


EXIT_CODES = {"OK": 0, "WARNING": 1, "CRITICAL": 2, "UNKNOWN": 3}
STATE_PRIORITY = {"OK": 0, "WARNING": 1, "UNKNOWN": 2, "CRITICAL": 3}
REDIRECT_STATUSES = {301, 302, 303, 307, 308}
SENSITIVE_QUERY = re.compile(r"(?:token|key|secret|pass(?:word)?|signature|auth|code)", re.I)
USER_AGENT = "DEWAWI-Availability-Check/1.0"


class ConfigurationError(ValueError):
    pass


def utc_now() -> dt.datetime:
    return dt.datetime.now(dt.timezone.utc)


def timestamp(value: dt.datetime | None = None) -> str:
    return (value or utc_now()).isoformat(timespec="seconds").replace("+00:00", "Z")


def normalize_url(value: str) -> str:
    value = value.strip()
    if not value or re.search(r"[\x00-\x1f\x7f]", value):
        raise ConfigurationError("Target URL is invalid")
    try:
        parts = urllib.parse.urlsplit(value)
        port = parts.port
    except (ValueError, UnicodeError) as error:
        raise ConfigurationError("Target URL is invalid") from error
    if parts.scheme.lower() not in {"http", "https"} or not parts.hostname:
        raise ConfigurationError("Target URL must be an absolute HTTP or HTTPS URL")
    if parts.username is not None or parts.password is not None or parts.fragment:
        raise ConfigurationError("Credentials and fragments are not allowed in target URLs")
    if port is not None and not 1 <= port <= 65535:
        raise ConfigurationError("Target URL contains an invalid port")
    host = parts.hostname.lower().rstrip(".")
    if not host or re.search(r"[\x00-\x20\x7f/@?#]", host):
        raise ConfigurationError("Target URL contains an invalid host")
    authority = f"[{host}]" if ":" in host else host
    if port is not None and not ((parts.scheme.lower() == "http" and port == 80) or (parts.scheme.lower() == "https" and port == 443)):
        authority += f":{port}"
    path = parts.path or "/"
    if not path.startswith("/") or path.startswith("//") or "\\" in urllib.parse.unquote(path):
        raise ConfigurationError("Target URL contains an invalid path")
    return urllib.parse.urlunsplit((parts.scheme.lower(), authority, path, parts.query, ""))


def redact_url(value: str) -> str:
    try:
        parts = urllib.parse.urlsplit(value)
        query = [
            (key, "<redacted>" if SENSITIVE_QUERY.search(key) else item)
            for key, item in urllib.parse.parse_qsl(parts.query, keep_blank_values=True)
        ]
        return urllib.parse.urlunsplit((parts.scheme, parts.netloc, parts.path, urllib.parse.urlencode(query), ""))
    except (ValueError, UnicodeError):
        return "invalid target"


def origin(value: str) -> tuple[str, str, int]:
    parts = urllib.parse.urlsplit(value)
    return parts.scheme, parts.hostname or "", parts.port or (443 if parts.scheme == "https" else 80)


def certificate_threshold(days_remaining: int, thresholds: list[int]) -> int | None:
    for threshold in sorted(set(thresholds)):
        if days_remaining <= threshold:
            return threshold
    return None


def resolve_host(
    host: str,
    port: int,
    timeout: float,
    resolver: Callable[..., list[tuple[Any, ...]]] = socket.getaddrinfo,
) -> tuple[list[tuple[Any, ...]] | None, str | None, float]:
    result: queue.Queue[list[tuple[Any, ...]] | Exception] = queue.Queue(maxsize=1)
    started = time.monotonic()

    def resolve() -> None:
        try:
            result.put(resolver(host, port, type=socket.SOCK_STREAM))
        except Exception as error:  # The exception is classified, never rendered.
            result.put(error)

    threading.Thread(target=resolve, daemon=True).start()
    try:
        resolved = result.get(timeout=timeout)
    except queue.Empty:
        return None, "dns_timeout", time.monotonic() - started
    elapsed = time.monotonic() - started
    if isinstance(resolved, Exception) or not resolved:
        return None, "dns_failed", elapsed
    return resolved, None, elapsed


def open_tcp(addresses: list[tuple[Any, ...]], timeout: float) -> tuple[socket.socket | None, str | None, float]:
    started = time.monotonic()
    deadline = started + timeout
    timed_out = False
    for family, socktype, protocol, _, address in addresses:
        connection = socket.socket(family, socktype, protocol)
        try:
            remaining = max(0.01, deadline - time.monotonic())
            connection.settimeout(remaining)
            connection.connect(address)
            return connection, None, time.monotonic() - started
        except socket.timeout:
            timed_out = True
            connection.close()
        except OSError:
            connection.close()
        if time.monotonic() >= deadline:
            timed_out = True
            break
    return None, "connection_timeout" if timed_out else "connection_failed", time.monotonic() - started


def make_issue(state: str, code: str, message: str) -> dict[str, str]:
    return {"state": state, "code": code, "message": message}


def classify_tls_error(error: BaseException) -> tuple[str, str]:
    text = str(error).lower()
    if "expired" in text:
        return "certificate_expired", "TLS certificate has expired"
    if "hostname mismatch" in text or "not valid for" in text or "ip address mismatch" in text:
        return "certificate_hostname_mismatch", "TLS certificate does not match the target host"
    if isinstance(error, ssl.SSLCertVerificationError):
        return "certificate_validation_failed", "TLS certificate validation failed"
    return "tls_failed", "TLS connection failed"


def state_for(issues: list[dict[str, str]]) -> str:
    return max((issue["state"] for issue in issues), key=lambda item: STATE_PRIORITY[item], default="OK")


def validate_config(raw: Any) -> dict[str, Any]:
    if not isinstance(raw, dict):
        raise ConfigurationError("Configuration root must be an object")
    timeout = raw.get("timeout", 10)
    if not isinstance(timeout, (int, float)) or isinstance(timeout, bool) or not 0.1 <= float(timeout) <= 60:
        raise ConfigurationError("timeout must be between 0.1 and 60 seconds")
    thresholds = raw.get("certificate_warning_days", [30, 14, 7])
    if not isinstance(thresholds, list) or not thresholds or any(not isinstance(day, int) or isinstance(day, bool) or day < 1 or day > 365 for day in thresholds):
        raise ConfigurationError("certificate_warning_days must contain values from 1 to 365")
    max_redirects = raw.get("max_redirects", 10)
    if not isinstance(max_redirects, int) or isinstance(max_redirects, bool) or not 1 <= max_redirects <= 10:
        raise ConfigurationError("max_redirects must be between 1 and 10")
    targets = raw.get("targets")
    if not isinstance(targets, list) or not 1 <= len(targets) <= 100:
        raise ConfigurationError("targets must contain between 1 and 100 explicit checks")

    normalized = []
    for number, target in enumerate(targets, 1):
        if not isinstance(target, dict):
            raise ConfigurationError(f"Target {number} must be an object")
        name = target.get("name")
        if not isinstance(name, str) or not name.strip() or len(name.strip()) > 100 or re.search(r"[\x00-\x1f\x7f]", name):
            raise ConfigurationError(f"Target {number} has an invalid name")
        url = normalize_url(target.get("url", "") if isinstance(target.get("url", ""), str) else "")
        method = target.get("method", "GET")
        if method not in {"GET", "HEAD"}:
            raise ConfigurationError(f"Target {number} method must be GET or HEAD")
        statuses = target.get("expected_status", [200])
        if isinstance(statuses, int) and not isinstance(statuses, bool):
            statuses = [statuses]
        if not isinstance(statuses, list) or not statuses or any(not isinstance(status, int) or isinstance(status, bool) or not 100 <= status <= 599 for status in statuses):
            raise ConfigurationError(f"Target {number} expected_status is invalid")
        follow = target.get("follow_redirects", False)
        if not isinstance(follow, bool):
            raise ConfigurationError(f"Target {number} follow_redirects must be boolean")
        expected_final = target.get("expected_final_url")
        if expected_final is not None:
            if not isinstance(expected_final, str) or not follow:
                raise ConfigurationError(f"Target {number} expected_final_url requires follow_redirects")
            expected_final = normalize_url(expected_final)
        expected_location = target.get("expected_location")
        if expected_location is not None:
            if not isinstance(expected_location, str) or follow:
                raise ConfigurationError(f"Target {number} expected_location requires disabled redirects")
            expected_location = normalize_url(urllib.parse.urljoin(url, expected_location))
        response_warning_ms = target.get("response_warning_ms")
        if response_warning_ms is not None and (not isinstance(response_warning_ms, (int, float)) or isinstance(response_warning_ms, bool) or not 1 <= float(response_warning_ms) <= 300_000):
            raise ConfigurationError(f"Target {number} response_warning_ms is invalid")
        normalized.append({
            "name": name.strip(),
            "url": url,
            "method": method,
            "expected_status": sorted(set(statuses)),
            "follow_redirects": follow,
            "expected_final_url": expected_final,
            "expected_location": expected_location,
            "response_warning_ms": float(response_warning_ms) if response_warning_ms is not None else None,
        })
    return {
        "timeout": float(timeout),
        "certificate_warning_days": sorted(set(thresholds), reverse=True),
        "max_redirects": max_redirects,
        "targets": normalized,
    }


class Monitor:
    def __init__(self, config: dict[str, Any], ca_file: str | None = None) -> None:
        self.config = config
        self.timeout = config["timeout"]
        self.ssl_context = ssl.create_default_context(cafile=ca_file)

    def check_tls(self, connection: socket.socket, host: str) -> tuple[dict[str, Any], list[dict[str, str]]]:
        started = time.monotonic()
        try:
            secured = self.ssl_context.wrap_socket(connection, server_hostname=host)
            certificate = secured.getpeercert()
            secured.close()
        except (ssl.SSLError, OSError) as error:
            connection.close()
            code, message = classify_tls_error(error)
            return {"checked": True, "durationMs": round((time.monotonic() - started) * 1000, 1)}, [make_issue("CRITICAL", code, message)]
        not_after = certificate.get("notAfter")
        if not isinstance(not_after, str):
            return {"checked": True, "durationMs": round((time.monotonic() - started) * 1000, 1)}, [make_issue("CRITICAL", "certificate_expiry_missing", "TLS certificate has no readable expiry")]
        expires = dt.datetime.fromtimestamp(ssl.cert_time_to_seconds(not_after), dt.timezone.utc)
        days = math.floor((expires - utc_now()).total_seconds() / 86400)
        tls = {
            "checked": True,
            "durationMs": round((time.monotonic() - started) * 1000, 1),
            "expiresAt": timestamp(expires),
            "daysRemaining": days,
        }
        threshold = certificate_threshold(days, self.config["certificate_warning_days"])
        issues = []
        if threshold is not None:
            issues.append(make_issue("WARNING", f"certificate_expiry_{threshold}", f"TLS certificate expires within {threshold} days"))
        return tls, issues

    def request(self, url: str, method: str) -> dict[str, Any]:
        started = time.monotonic()
        parts = urllib.parse.urlsplit(url)
        host = parts.hostname or ""
        port = parts.port or (443 if parts.scheme == "https" else 80)
        addresses, error, _ = resolve_host(host, port, self.timeout)
        if error:
            return {"status": 0, "headers": {}, "durationMs": round((time.monotonic() - started) * 1000, 1), "error": error}
        network, error, _ = open_tcp(addresses or [], self.timeout)
        if error:
            return {"status": 0, "headers": {}, "durationMs": round((time.monotonic() - started) * 1000, 1), "error": error}
        connection = None
        try:
            if parts.scheme == "https":
                network = self.ssl_context.wrap_socket(network, server_hostname=host)
            connection = http.client.HTTPConnection(host, port, timeout=self.timeout)
            connection.sock = network
            request_target = urllib.parse.urlunsplit(("", "", parts.path or "/", parts.query, ""))
            connection.request(method, request_target, headers={"User-Agent": USER_AGENT, "Accept": "*/*"})
            response = connection.getresponse()
            if method != "HEAD":
                response.read(1024)
            return {
                "status": int(response.status),
                "headers": {key.lower(): value for key, value in response.getheaders()},
                "durationMs": round((time.monotonic() - started) * 1000, 1),
                "error": None,
            }
        except ssl.SSLError as error:
            code, _ = classify_tls_error(error)
            return {"status": 0, "headers": {}, "durationMs": round((time.monotonic() - started) * 1000, 1), "error": code}
        except (TimeoutError, socket.timeout):
            return {"status": 0, "headers": {}, "durationMs": round((time.monotonic() - started) * 1000, 1), "error": "http_timeout"}
        except (http.client.HTTPException, OSError):
            return {"status": 0, "headers": {}, "durationMs": round((time.monotonic() - started) * 1000, 1), "error": "http_connection_interrupted"}
        finally:
            if connection is not None:
                connection.close()
            elif network is not None:
                network.close()

    def check_http(self, target: dict[str, Any]) -> tuple[dict[str, Any], list[dict[str, str]]]:
        current = target["url"]
        seen = {current}
        redirects = []
        total_duration = 0.0
        allowed_origins = {origin(target["url"])}
        if target["expected_final_url"]:
            allowed_origins.add(origin(target["expected_final_url"]))
        if target["expected_location"]:
            allowed_origins.add(origin(target["expected_location"]))

        for _ in range(self.config["max_redirects"] + 1):
            result = self.request(current, target["method"])
            total_duration += result["durationMs"]
            if result["error"]:
                code = result["error"]
                messages = {
                    "dns_timeout": "DNS resolution timed out",
                    "dns_failed": "DNS resolution failed",
                    "connection_timeout": "TCP connection timed out",
                    "connection_failed": "TCP connection failed",
                    "http_timeout": "HTTP request timed out",
                    "certificate_expired": "TLS certificate has expired",
                    "certificate_hostname_mismatch": "TLS certificate does not match the target host",
                    "certificate_validation_failed": "TLS certificate validation failed",
                    "tls_failed": "TLS connection failed",
                }
                message = messages.get(code, "HTTP connection failed")
                return {"status": 0, "finalUrl": redact_url(current), "redirects": redirects, "durationMs": round(total_duration, 1)}, [make_issue("CRITICAL", code, message)]
            status = result["status"]
            if status not in REDIRECT_STATUSES:
                issues = []
                if status not in target["expected_status"]:
                    code = "http_5xx" if status >= 500 else "http_status_unexpected"
                    issues.append(make_issue("CRITICAL", code, f"Expected HTTP {target['expected_status']}, received {status}"))
                if target["expected_final_url"] and current != target["expected_final_url"]:
                    issues.append(make_issue("CRITICAL", "final_url_unexpected", "Final URL does not match the configured canonical URL"))
                if target["response_warning_ms"] is not None and total_duration > target["response_warning_ms"]:
                    issues.append(make_issue("WARNING", "response_slow", "HTTP response exceeded the configured warning duration"))
                return {"status": status, "finalUrl": redact_url(current), "redirects": redirects, "durationMs": round(total_duration, 1)}, issues

            location = result["headers"].get("location", "").strip()
            if not location:
                return {"status": status, "finalUrl": redact_url(current), "redirects": redirects, "durationMs": round(total_duration, 1)}, [make_issue("CRITICAL", "redirect_location_missing", "Redirect response has no Location header")]
            try:
                destination = normalize_url(urllib.parse.urljoin(current, location))
            except ConfigurationError:
                return {"status": status, "finalUrl": redact_url(current), "redirects": redirects, "durationMs": round(total_duration, 1)}, [make_issue("CRITICAL", "redirect_location_invalid", "Redirect target is invalid")]
            redirects.append({"status": status, "url": redact_url(current), "location": redact_url(destination)})
            if origin(current)[0] == "https" and origin(destination)[0] != "https":
                return {"status": status, "finalUrl": redact_url(destination), "redirects": redirects, "durationMs": round(total_duration, 1)}, [make_issue("CRITICAL", "redirect_https_downgrade", "Redirect downgrades HTTPS to HTTP")]
            if origin(destination) not in allowed_origins:
                return {"status": status, "finalUrl": redact_url(destination), "redirects": redirects, "durationMs": round(total_duration, 1)}, [make_issue("CRITICAL", "redirect_host_unexpected", "Redirect leaves the configured scheme, host and port set")]
            if not target["follow_redirects"]:
                issues = []
                if status not in target["expected_status"]:
                    issues.append(make_issue("CRITICAL", "http_status_unexpected", f"Expected HTTP {target['expected_status']}, received {status}"))
                if target["expected_location"] is not None and destination != target["expected_location"]:
                    issues.append(make_issue("CRITICAL", "redirect_location_unexpected", "Redirect target does not match the configured URL"))
                return {"status": status, "finalUrl": redact_url(current), "redirects": redirects, "durationMs": round(total_duration, 1)}, issues
            if destination in seen:
                return {"status": status, "finalUrl": redact_url(destination), "redirects": redirects, "durationMs": round(total_duration, 1)}, [make_issue("CRITICAL", "redirect_loop", "Redirect loop detected")]
            seen.add(destination)
            current = destination

        return {"status": 0, "finalUrl": redact_url(current), "redirects": redirects, "durationMs": round(total_duration, 1)}, [make_issue("CRITICAL", "redirect_limit", "Redirect limit exceeded")]

    def check_target(self, target: dict[str, Any]) -> dict[str, Any]:
        started = time.monotonic()
        checked_at = timestamp()
        scheme, host, port = origin(target["url"])
        result = {
            "name": target["name"],
            "url": redact_url(target["url"]),
            "method": target["method"],
            "checkedAt": checked_at,
            "dns": {},
            "tcp": {},
            "tls": {"checked": False},
            "http": {"status": 0, "finalUrl": redact_url(target["url"]), "redirects": [], "durationMs": 0.0},
            "issues": [],
        }
        addresses, error, elapsed = resolve_host(host, port, self.timeout)
        result["dns"] = {"durationMs": round(elapsed * 1000, 1)}
        if error:
            result["issues"].append(make_issue("CRITICAL", error, "DNS resolution timed out" if error == "dns_timeout" else "DNS resolution failed"))
            return self.finish_target(result, started)
        connection, error, elapsed = open_tcp(addresses or [], self.timeout)
        result["tcp"] = {"durationMs": round(elapsed * 1000, 1)}
        if error:
            result["issues"].append(make_issue("CRITICAL", error, "TCP connection timed out" if error == "connection_timeout" else "TCP connection failed"))
            return self.finish_target(result, started)
        if scheme == "https":
            tls, issues = self.check_tls(connection, host)
            result["tls"] = tls
            result["issues"].extend(issues)
            if any(issue["state"] == "CRITICAL" for issue in issues):
                return self.finish_target(result, started)
        else:
            connection.close()
        http, issues = self.check_http(target)
        result["http"] = http
        result["issues"].extend(issues)
        return self.finish_target(result, started)

    @staticmethod
    def finish_target(result: dict[str, Any], started: float) -> dict[str, Any]:
        result["state"] = state_for(result["issues"])
        result["durationMs"] = round((time.monotonic() - started) * 1000, 1)
        return result

    def run(self) -> dict[str, Any]:
        started = time.monotonic()
        results = [self.check_target(target) for target in self.config["targets"]]
        state = max((item["state"] for item in results), key=lambda item: STATE_PRIORITY[item], default="UNKNOWN")
        return {
            "state": state,
            "exitCode": EXIT_CODES[state],
            "checkedAt": timestamp(),
            "durationMs": round((time.monotonic() - started) * 1000, 1),
            "targets": results,
        }


def unknown_report(message: str) -> dict[str, Any]:
    return {
        "state": "UNKNOWN",
        "exitCode": EXIT_CODES["UNKNOWN"],
        "checkedAt": timestamp(),
        "durationMs": 0.0,
        "targets": [],
        "issues": [make_issue("UNKNOWN", "configuration_invalid", message)],
    }


def render_text(report: dict[str, Any]) -> str:
    lines = [f"{report['state']} - DEWAWI availability check ({len(report['targets'])} targets, {report['durationMs']:.1f} ms)"]
    for target in report["targets"]:
        http = target["http"]
        tls = target["tls"]
        details = [f"HTTP {http['status'] or '-'}", f"{target['durationMs']:.1f} ms"]
        if tls.get("checked") and "daysRemaining" in tls:
            details.append(f"TLS {tls['daysRemaining']} days")
        lines.append(f"[{target['state']}] {target['name']} {target['url']} - " + ", ".join(details))
        for issue in target["issues"]:
            lines.append(f"  {issue['state']} {issue['code']}: {issue['message']}")
    for issue in report.get("issues", []):
        lines.append(f"{issue['state']} {issue['code']}: {issue['message']}")
    return "\n".join(lines) + "\n"


def read_config(filename: str) -> Any:
    path = Path(filename)
    if not path.is_file() or path.stat().st_size > 1_000_000:
        raise ConfigurationError("Configuration file is missing or too large")
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as error:
        raise ConfigurationError("Configuration file is not valid UTF-8 JSON") from error


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--config", required=True, help="Trusted local JSON configuration")
    parser.add_argument("--format", choices=["text", "json"], default="text")
    parser.add_argument("--ca-file", help="Optional trusted CA bundle for isolated or private PKI checks")
    args = parser.parse_args(argv)
    try:
        config = validate_config(read_config(args.config))
        report = Monitor(config, args.ca_file).run()
    except (ConfigurationError, OSError, ssl.SSLError) as error:
        report = unknown_report(str(error) if isinstance(error, ConfigurationError) else "Monitoring initialization failed")
    if args.format == "json":
        print(json.dumps(report, ensure_ascii=False, indent=2, sort_keys=True))
    else:
        print(render_text(report), end="")
    return report["exitCode"]


if __name__ == "__main__":
    raise SystemExit(main())
