# M2F automatic hosting setup verification

Verified locally on 2026-11-21 with PHP 8.3.6, MariaDB 10.11.14 and Chromium.
The starting tree was clean at `04874482` (including the separately completed
C4B work). Only disposable databases, synthetic accounts/data and isolated private
test directories were used. No production setup, database or service was changed.

## Selection and persistence

The repository has no general private writable data root: sessions, caches and
document archives are in the installation/web tree. The existing private GoBD
report provider, `DEWAWI_GOBD_REPORT_DIR`, is therefore the only automatic source.
No home-directory, sibling-directory or temporary-path guessing was introduced.
The source and derived child must pass canonical/private path, public-root,
ownership, permissions, local-filesystem and durable write/lock capability checks.
An explicit valid M2E statePath remains the initial authority and is not moved.

The derived child is `dewawi-updates-` plus the first 32 hex characters of SHA-256
of the canonical installation root. A protected immutable technical locator in
configs claims its path, installation identity, DB fingerprint and public-root
facts before directory creation. A second protected record marks completion.
Both are Git-ignored installation configuration, not migration histories. All
actual state, jobs and evidence remain outside the repository/public roots.

The same resolver serves the web controller, early pre-bootstrap fence, technical
CLI entries and hosting CLI consumer. Normal entries read a pinned locator only;
changed or missing provider environment variables cannot relocate initialized
state. There is no automatic A/B mode switch or recovery selection. Existing
M2E directories, locks, IDs and evidence are reused without overwriting them.

GET performs no setup writes. Browser setup requires active admin identity,
CSRF, password reauthentication and explicit confirmation before capability probes,
claims or directory creation. A narrowly interrupted, unused initialization may
be completed with its already claimed ID after another confirmation. Unknown
files, jobs, maintenance, corrupt/missing initialized state and conflicting pins
are never repaired or replaced automatically. Database identity conflicts are
checked before creating a claimed state directory. Same-identity DB restores
retain file state and identity; their actual restored data/schema need independent
operator verification, not identity regeneration.

## Actual results

| Command / suite | Result |
| --- | --- |
| `python3 -B tests/admin/update-storage.py` | 33 filesystem/pinning checks and 100 M2F integration/browser checks passed |
| `python3 -B tests/admin/update-worker.py` | 867 M2B/M2C/M2D, 701 M2E and 100 M2F integration checks passed, plus the filesystem fixture |
| `python3 -B tests/admin/updates.py` | 334 M2A/M2C overview, authorization, menu/dashboard, version and DE/EN browser checks passed |
| `python3 -B tests/install/migrations.py` | 124 M1 checks passed |
| `python3 -B tests/install/import-db.py` | Historical/fresh installation/schema/failure checks and 66 UOM cases passed |
| `php tests/security/system-updates.php` | 122 administrator/identity/tenant/ACL cases passed |
| `php tests/security/admin-access.php` | 68 cases and forbidden response passed |
| `php tests/security/bootstrap-auth.php` | 29 cases passed |
| `php tests/security/permissions.php` | 450 cases passed |
| `php tests/security/configuration.php` | 22 configuration/entry-point cases passed |
| `python3 -B tests/security/configuration-http.py` | 9,250 Apache cases / 4,327 inventoried PHP/include paths passed after staging; includes both locator JSON paths |
| `python3 -B tests/security/session-http.py` | 25 real HTTP session cases passed |
| `python3 -B tests/security/error-handling-http.py` | 152 initialization/error/cron cases passed |
| `php tests/ui/dashboard-buttons.php` | 3,620 assertions passed |
| `php tests/translations/runtime.php` | 157 checks passed |
| `python3 -B tests/translations/audit-test.py` | 30 tests passed |
| Syntax / whitespace | All 10 changed/new PHP/PHTML and five Python files checked; worktree/staged whitespace checks passed |

The known independent `php tests/translations/cleanup.php` still exits 255 in the
unchanged cart fixture: no SiteContext for `hasFeature()` at
`application/modules/shops/views/scripts/cart/index.phtml:37`. It is not counted
as passed and neither affected file is changed by M2F.

## Coverage

Filesystem checks cover no provider, explicit precedence (including an invalid
automatic provider), local versus memory/temporary filesystems, missing write rights, unsafe/public paths,
an additional public root equal to the derived child, symlinks, directory/file
modes, native flock/fsync/atomic publication, claims and retries, CLI resolution
without provider environment, new public-root conflicts, corrupt locators/control
and loss of an initialized directory. Public-root aliases are resolved canonically;
storage/configuration path aliases are rejected.

The real controller/browser flow covers setup without statePath, no GET writes,
wrong password/CSRF/missing confirmation, native initialization in DE/EN at
1280/390/320 pixels, repeat and simultaneous browser requests, actual competing
PHP processes during first initialization, a claim interrupted before directory
creation, unchanged IDs/files across CLI calls and provider changes, early cron
and HTTP maintenance denial, active maintenance, accepted/failed jobs, failed M1
history, DB routing changes, same-identity restoration, malformed locators, missing
locks and vanished state. Initial browser setup also runs with PHP shell/process
functions disabled. HTTP responses contain neither private paths nor credentials.

M2E's existing full baseline/feature, backup/restore, plan/CSRF/session, rights,
concurrency, interruption, runtime and replay cases remain active. Its invalid-pin
test now submits the previously captured CSRF form because the corrected setup
status hides execution controls immediately after discovering that conflict.
No existing negative assertion was weakened.

## Hosting prerequisites and boundaries

Automatic selection deliberately requires a pre-existing approved private GoBD
area, readable Linux mount information and a recognized durable local filesystem,
private modes/ownership, complete public-root facts and writable canonical local
configs for the technical binding. An arbitrary directory named private is not
accepted. If these cannot be proven, the UI gives the one-time private-statePath
hosting instruction. No new account, systemd or CLI is required for browser setup.
Do not invent a GoBD provider or relax checks to force compatibility.

Actual www-data/Apache and PHP-FPM pool identities, all configured virtual-host
roots/aliases, external cleanup policies, hosting durability/power-loss behavior,
and independently verified backups/writer exclusion still require target-host
acceptance. Tests use the real A filesystem implementation under the existing
mapped UID. The stricter B worker remains unchanged and still has its documented
single-UID ownership-emulation/test-server acceptance limit.

No safe automatic provider is asserted for the current installation. Without one,
the operator must still provision/configure a private durable location once.
Retain locator/ready and external state together across deployments/restores.
Automatic setup grants no DDL privileges, generates no backup evidence, performs
no SQL migration and makes no restoration guarantee. All M1/M2E safeguards remain.

Version, frozen reference, SQL, migration checksums, stricter B worker and C4B code
are unchanged. No push, deployment, production migration, server activation or
follow-on development block was performed. See [the operating guide](../../tools/updates/HOSTING.md).
