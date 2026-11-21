# M2E hosting update verification

Checked locally on 2026-11-21 with PHP 8.3.6, MariaDB 10.11.14 and Chromium.
All DB mutations used disposable databases and synthetic accounts/data. No live
database, private installation configuration, OS account, service or grant was
changed. Version remains 1.1.8; frozen reference and all migration SQL are intact.

## Implemented interfaces

The regular `shared` mode uses the existing PHP account and application PDO
connection. It adds explicit POST-only `initialize`, `maintenance`, `prepare`,
`approve`, `execute` and `finish` operations to the existing Updates controller.
GET/HEAD remain read-only. Initialization writes private installation files only.
The private mode/path settings remain installation configuration, not user fields
or database settings. Existing queuePath installations still select optional
worker mode unless explicitly changed; the B worker/Files implementation is intact.

`DEEC_UpdateMaintenance` holds a shared lease before front-controller bootstrap,
through cron and technical CLI work. Exclusive maintenance/update leases drain
existing work and a persistent marker blocks ordinary dynamic traffic, including
GET writers. The MVC dispatch guard rejects forwarding into ordinary controllers.
Geocoding (single/batch/diagnostic), technical import, document-operation and M1 CLI
entries take the same fence. External writers require independent evidence; the
mode is restricted to a coordinated single deployment on reliable local storage.

`DewawiHostingUpdates` orchestrates the existing M1 engine, not another SQL runner.
The full current plan is checked under both locks. Approval binds the next step,
entire catalog/history, DB identity, maintenance epoch and evidence bytes. Baseline
verification fingerprints the actual frozen schema; features execute one at a
time using M1's optional single-step argument. Existing CLI/worker defaults still
execute their complete approved M1 plans. M1 history is the sole DB completion
record; there is no new migration/schema-version table or manual history editing.

Initialization, maintenance, approval, HTTP execution and maintenance completion
require password reauthentication using the existing verifier. All operations
require active admin authorization, persisted-account verification and CSRF;
HTTP execution is session/tenant-bound. Nonces and immutable claims prevent reuse.
Permission, plan, evidence/expiry and DB identity are checked again before SQL.
The exact evidence bytes and their approved hash are retained before acceptance.
Rejected preflight plans cannot be restored/reused; accepted interruptions are
manual-review states and never automatically retried or repaired.

## Actual results

| Command | Result |
| --- | --- |
| `python3 -B tests/admin/update-hosting.py` | 674 final M2E checks passed using the shared disposable worker-test environment |
| `python3 -B tests/admin/update-worker.py` | 867 existing M2B/M2C/M2D checks passed; that full run also exercised the earlier 627-case A suite before the final targeted additions |
| `python3 -B tests/admin/updates.py` | 334 M2A/M2C history, version, menu/dashboard, authorization, read-only HTTP and DE/EN Chromium checks passed |
| `python3 -B tests/install/migrations.py` | 124 M1 checks passed |
| `python3 -B tests/install/import-db.py` | Historical/fresh schema equality across 124 tables, installation failure cases and 66 UOM checks passed |
| `python3 -B tests/security/shop-shipping-db.py` | Fresh/upgraded schema equality and repeated apply, 70 DB and 147 HTTP/browser checks passed |
| `php tests/security/shop-shipping.php` | 404 tariff/validation/precision checks passed |
| `php tests/security/system-updates.php` | 122 administrator/identity/tenant/ACL cases passed |
| `php tests/security/admin-access.php` | 68 cases and forbidden response passed |
| `php tests/security/bootstrap-auth.php` | 29 cases passed |
| `php tests/security/permissions.php` | 450 cases passed |
| `php tests/security/configuration.php` | 22 entry-point/environment cases passed |
| `python3 -B tests/security/configuration-http.py` | 9,232 Apache HTTP cases with 4,324 inventoried PHP/include files passed after staging new paths |
| `python3 -B tests/security/session-http.py` | 25 real session cases passed |
| `python3 -B tests/security/error-handling-http.py` | 152 error/initialization/cron cases passed |
| `php tests/ui/dashboard-buttons.php` | 3,620 assertions passed |
| `php tests/translations/runtime.php` | 157 checks passed |
| `python3 -B tests/translations/audit-test.py` | 30 tests passed |
| `php tests/documents/document-recovery-assessment.php` | 183 synthetic PDF/private CLI/streaming checks passed |
| Single and batch geocoding `--help` | Existing CLI startup passed outside maintenance; explicitly blocked during maintenance |
| Syntax / whitespace | All 24 affected PHP/PHTML files passed `php -l`; three Python files parsed; staged/worktree whitespace checks passed |

The independent pre-existing `php tests/translations/cleanup.php` still exits
255: its unchanged cart fixture lacks SiteContext for `hasFeature()` at
`application/modules/shops/views/scripts/cart/index.phtml:37`. It is not counted
as passed and neither affected file is modified here.

During implementation the tests exposed the early CLI include-path dependency,
an unconfigured initialization control, and a test-asset maintenance exemption;
these were corrected. The fixture servers now disable OPcache explicitly because
the version-source tests replace PHP files within seconds; no assertion was
removed or relaxed in the existing regression suites, and no production OPcache setting was changed. A transient
isolated MariaDB initialization failure passed on rerun.

## Coverage and remaining acceptance

A uses the real private filesystem implementation under the actual existing PHP
OS identity, without the worker's ownership test double. The suite checks rootless
CLI and browser initialization, different administrator/tenant contexts, real M1
baseline registration followed by separately approved shipping, exactly one next
feature, missing/incomplete baseline, actual isolated backup/restore with tenant
data verification, missing/invalid reports and backup hashes, writer exclusion,
missing DDL privileges, persistent-PDO rejection, DB pin and catalog/history/plan
changes, stale/repeated requests, CSRF/password/session failures, admin revocation,
parallel approvals, active lease draining and cron/CLI/forwarding denial.

Runtime cases include insufficient PHP time before acceptance, a real statement
timeout with committed preceding DDL and persisted failure, browser disconnection,
a killed PHP process during SQL, refusal to repeat/release interrupted work and
the approved same-account CLI alternative. DE/EN browsers at 1280/390/320 pixels
check explicit confirmation, password/native validity, focus, no auto-save and no
horizontal overflow. Existing B checks continue to cover its stricter boundary.

**A has no OS/DB separation from a compromised web process.** Mode 0700/0600 and
immutable records do not change that. Direct database grants must be provable;
role/table-specific grants are conservatively blocked. No privileges are expanded.
HTTP supports short reviewed MariaDB jobs with sufficient runtime; MySQL HTTP,
large jobs and tighter limits require the CLI/operational alternative. Persistent
PDO connections are refused so migration session settings cannot leak into ERP.
There is no guarantee that a killed DDL has stopped or rolled back.

Actual www-data/Apache and separate PHP-FPM pool identities, MySQL execution,
hosting proxy/process limits, storage durability/power loss and all custom or
external writer paths remain target-host acceptance requirements. The host has
one usable mapped UID: B still uses its explicit ownership test double and real
cross-UID/service acceptance remains outstanding. A requires no additional UID.

Before enabling production, provision the private state path/settings, initialize
the DB pin, verify scoped DB privileges and nonpersistent PDO, inventory/fence all
writers, and supply real independent backup/restoration/historical evidence under
the documented procedure. Reports and backups must be retained independently;
the application verifies artifacts/hashes, not the truth of an external audit.
No production setup, service, migration, push, deployment or C4B work was performed.
See [the operating procedure](../../tools/updates/HOSTING.md).
