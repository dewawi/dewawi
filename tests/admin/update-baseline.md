# M2D baseline registration verification

Verified locally on 2026-11-21 with PHP 8.3.6, MariaDB 10.11.14 and headless
Chromium. All database writes used disposable synthetic test databases. No
production database, private installation setting or service was changed.
Application version remains 1.1.8; frozen baseline and migration SQL are unchanged.

## Implementation and trust boundaries

The existing update interface now distinguishes worker schema verification,
explicit baseline-registration approval and subsequent feature migration.
`Inspector::executionPlan` supports separate baseline metadata; only the CLI
`Migrator::baselinePlan` verifies/fingerprints the actual complete frozen schema.
`registerApprovedBaseline` repeats the locked plan comparison and uses the
existing M1 baseline writer with preconditions before creation/start/completion.
HTTP only reads metadata and submits the existing CSRF/session/identity-bound
one-use approval. It cannot execute SQL, register history or launch the worker.

The worker requires the existing root-controlled backup/restore/maintenance
release plus separate `REQUEST.historical.json` evidence bound to the exact
captured request, plan, actor, installation, database and frozen reference. The
independent audit reference, report SHA-256, reviewer and current maintenance
review time are checked and archived with the operational evidence. Root must
verify the actual report and historical data; a matching schema or syntactically
valid report hash cannot establish data-backfill correctness by itself.

The baseline job creates only M1 history and records `baseline/1.1.8` with zero
feature SQL statements. Success publishes the shipping migration as the next
pending plan immediately. It requires a fresh web confirmation and separate
operational release. Existing M2C administrator access, persisted-account worker
checks, database identity pin, write fence, locks, immutable queue records,
private credentials and conservative interruption handling remain mandatory.

## Results

| Command | Actual result |
| --- | --- |
| `python3 -B tests/admin/update-worker.py` | 867 M2B/M2C/M2D protocol, MariaDB, HTTP and Chromium assertions passed; ownership emulation limit below |
| `python3 -B tests/admin/updates.py` | 334 M2A/M2C overview, version/history, authorization, menu/dashboard, SELECT-only HTTP and DE/EN desktop/mobile assertions passed |
| `python3 -B tests/install/migrations.py` | 124 M1 checks passed |
| `python3 -B tests/install/import-db.py` | Historical/fresh schema equality across 124 tables, installer failures and 66 UOM checks passed |
| `python3 -B tests/security/shop-shipping-db.py` | Fresh/upgraded schema equality, repeated apply, 70 DB and 147 HTTP/browser checks passed |
| `php tests/security/shop-shipping.php` | 404 checks passed |
| `php tests/security/system-updates.php` | 122 administrator/identity/tenant/ACL checks passed |
| `php tests/security/admin-access.php` | 68 checks and forbidden response passed |
| `php tests/security/bootstrap-auth.php` | 29 checks passed |
| `php tests/security/permissions.php` | 450 checks passed |
| `php tests/security/configuration.php` | 22 entry-point checks passed |
| `python3 -B tests/security/configuration-http.py` | 9,222 Apache HTTP cases and 4,319 inventoried PHP/include paths passed after staging |
| `php tests/ui/dashboard-buttons.php` | 3,620 assertions passed |
| `php tests/translations/runtime.php` | 157 checks passed |
| `python3 -B tests/translations/audit-test.py` | 30 tests passed |
| Syntax / whitespace | All eight changed PHP/PHTML files passed `php -l`; both changed/new Python scripts parsed; `git diff --check` passed |

The pre-existing independent `php tests/translations/cleanup.php` exits 255 in
the unchanged cart view at
`application/modules/shops/views/scripts/cart/index.phtml:37`: its fixture lacks
the `SiteContext` used by `hasFeature()`. This suite is not counted as passed;
neither affected file is modified by M2D.

## M2D cases

`update-baseline.py` extends the existing isolated worker harness, rather than
creating another queue or migration implementation. It is run by
`update-worker.py`, not directly. The cases cover:

- Full schema without history, read-only publication and HTTP submission, then
  successful registration by a stored administrator from another tenant.
- Missing tables/columns and incompatible defaults, with no registration plan
  or automatic schema repair; already registered baseline exposes feature work.
- Missing backup release or unresolved historical audit, malformed/mismatched
  audit fields, symlink/writable evidence and immutable evidence retention.
- Changed schema (including compatible extras), newly appearing empty history,
  changed catalog, database identity mismatch and expired approval.
- Revoked administrator rights, deactivation/deletion, concurrent approvals,
  competing worker exclusion, replay and repeated worker execution.
- Real process kills after durable acceptance/history creation but before the
  start INSERT, and after the persisted started row before completion. Restart
  requires manual review and never resumes or guesses completion.
- Unchanged synthetic tenant balances; only the baseline row is registered;
  shipping is pending and cannot execute using the baseline release. A separate
  shipping approval/release then completes the actual shipping migration.
- Both operations in DE/EN at 1280, 390 and 320 pixels: operation-specific labels,
  explicit native confirmation, focus/validity, no auto-save and no overflow.

Existing worker cases for CSRF, session/tenant identity, private-path and response
secrets, SELECT-only HTTP credentials, backup/restore/maintenance validation,
write-fence loss, failed SQL, history/checksums and lock/recovery semantics remain.

## Server acceptance and manual prerequisites

The execution host has only one usable mapped UID. The existing explicit
`UpdatesProtocolFiles` fixture emulates ownership, while real modes, links,
paths, fsync, locks, MariaDB and HTTP checks remain active. Production rejects
this single-user layout; no ownership bypass or production test flag was added.
Real cross-UID credential/private-state/evidence denial and dedicated service
startup must still be accepted on the deployment/test server. MySQL and actual
storage/power-loss behavior also remain outside this MariaDB protocol test.

The operator must provision the existing private queue/accounts/credentials and
database pin, verify backup/restoration and maintenance, and independently audit
historical data for the first registration. Install both root-owned evidence
files using the documented immutable atomic publication procedure. Ensure the
worker's scoped privileges allow first history-table creation under the fence.
No account, grant, service or production worker was provisioned here.

There is no new SQL migration, version change or manual history-row editing.
The technical/emergency M1 CLI retains its existing interface. Development
snapshots with shipping schema but missing feature history retain M1's provenance
limitation; baseline registration does not certify or repair feature history.
See [the operational procedure](../../tools/updates/README.md#first-baseline-registration-through-the-ui-m2d).

No push, deployment, production SQL or automatic follow-on task was performed.
