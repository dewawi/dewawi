# M2B verification and activation prerequisites

Checked locally on 2026-11-21 with PHP 8.3.6, MariaDB 10.11.14 and headless
Chromium. No customer database, production configuration or production service
was used. Application version remains 1.1.8.

## Concrete results

| Command | Result |
| --- | --- |
| `python3 -B tests/admin/update-worker.py` | 396 protocol/MariaDB/HTTP/Chromium checks passed, with the explicit ownership-emulation limit below |
| `python3 -B tests/admin/updates.py` | 306 existing M2A checks passed |
| `python3 -B tests/install/migrations.py` | 124 existing M1 checks passed |
| `python3 -B tests/install/import-db.py` | Fresh/historical installer and failure paths passed; 66 UOM checks passed |
| `python3 -B tests/security/shop-shipping-db.py` | Fresh/upgraded schema equality and repeated apply passed; 70 DB and 147 HTTP/browser checks passed |
| `php tests/security/admin-access.php` | 68 ACL cases and forbidden dispatch passed |
| `php tests/security/bootstrap-auth.php` | 29 authentication/tenant/sharing cases passed |
| `php tests/security/permissions.php` | 450 permission/ACL cases passed |
| `php tests/security/configuration.php` | 22 entry-point cases passed |
| `python3 -B tests/security/session-http.py` | 25 real PHP/Zend session cases passed |
| `python3 -B tests/security/error-handling-http.py` | 152 cases passed |
| `python3 -B tests/security/configuration-http.py` | 9,220 Apache HTTP cases passed, including 4,318 inventoried PHP/include paths |
| `php tests/translations/runtime.php` | 157 checks passed |
| `python3 -B tests/translations/audit-test.py` | 30 tests passed |
| PHP syntax | All 14 changed PHP/view files passed; changed Python fixtures parsed successfully |
| `systemd-analyze verify tools/updates/dewawi-update-worker.service tools/updates/dewawi-update-worker.timer` | Passed; no service installed or executed |

The Apache/private-file inventory ran after staging so that newly introduced
PHP/include paths were covered as well.

`php tests/translations/cleanup.php` still exits 255 in the unchanged cart view
at `application/modules/shops/views/scripts/cart/index.phtml:37`, because its
existing fixture supplies no `SiteContext` for `hasFeature()`. It is not counted
as passed. This independent fixture issue was already documented in M2A; neither
that test nor the cart view is changed here.

An initial M2B fixture placed its queue inside its temporary document root; the
production path check correctly rejected it. The test document root was fixed,
without weakening the path check. One subsequent isolated MariaDB startup did
not become ready; the harness now checks server exit/readiness explicitly, and
the final complete run passed.

## What the integration run exercises

A temporary source copy, non-networked disposable MariaDB server and loopback PHP
server exercise actual M1 plan/apply logic, the real controller/service/view/session
flow and persistent file queue. The web database account has SELECT only; the
separate migration account has explicitly scoped test DB privileges and MariaDB's
read-only bypass. Authentic baseline history is established through the M1 CLI.
The actual shipping migration is executed only on an isolated frozen-release test
schema. Smaller synthetic migrations/history copies cover negative/interrupt cases.

The suite checks operator/tenant authorization, direct controller calls, CSRF,
explicit confirmation, account/session/tenant switching, replay, double submission,
parallel approvals, missing/offline worker, unsafe/missing configuration, missing
or invalid backup/restore release, wrong request/plan/database binding, stale or
changed catalog/checksums/history, invalid actors, symlinks/traversal, writable
published-directory modes, and absence of privileged HTTP migration actions.

It confirms no migration runs on HTTP approval or while external evidence is
missing. It retains private approved-plan and release records. It tests success,
actual SQL failure with committed preceding DDL, independent persisted-account
revocation, real process kill after durable acceptance but before M1's start
INSERT, process kill during SQL, competing worker exclusion, M1 started/failed
blocking, and loss of the write fence between SQL statements. Prerequisite loss
is durably marked `precondition_failed`. Subsequent worker calls do not rerun
completed/failed/interrupted SQL. Synthetic tenant balances remain unchanged.

DE/EN Chromium checks at 1280, 390 and 320 pixels verify the ERP shell, checksum
wrapping/no horizontal overflow, explicit native form confirmation, focusability,
validity constraints and disabled auto-save. They do not replace screen-reader
or deployment-browser acceptance. Responses exclude SQL, credentials, SQLSTATE
diagnostics and private paths. The production CLI exposes only fixed commands,
never a browser-triggered shell or arbitrary SQL channel.

## Explicit limits: production activation is not certified

The current execution host has one usable mapped UID. It cannot provision the
root authority, web and worker service identities required by production. The
protocol test therefore uses `UpdatesProtocolFiles`, a test-only subclass that
emulates numeric ownership/identity checks. Real file modes, paths, symlink/link
checks, atomic writes, fsync, locks, HTTP authorization and actual MariaDB checks
remain active. The production gate is separately asserted to reject the emulated
single-user layout; production has no ownership bypass or test flag.

**The following have not been executed and remain mandatory before activation:**

- Full production CLI startup and service execution under the dedicated real OS
  worker account with root-owned read-only code.
- Real cross-UID negative tests proving the web user cannot read privileged
  credentials/private state/evidence or alter worker results/code/control, and the
  worker cannot alter root evidence/control. Include ACLs and supplementary groups.
- A real externally validated backup/restore artifact and maintenance release for
  the target installation; the protocol tests use synthetic attestations.
- Target-server storage/power-loss behavior and service sandbox acceptance.
- A separate MySQL server; MariaDB is the tested database implementation.

[The operations guide](../../tools/updates/README.md) specifies accounts, modes,
private configuration, pinned database identity, mandatory per-request root release,
technical fence and recovery. No actual setup has been enabled. Default application
configuration still has no queue path or executable worker action.

No SQL migration file/table/schema change is introduced by M2B. Frozen baseline,
historical update SQL, migration 001 and the published version are unchanged.
