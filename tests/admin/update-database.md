# M2G verification and operating boundaries

Verified locally on 2026-11-22 with PHP 8.3, MariaDB 10.11 and Chromium.
All database work used disposable servers, synthetic users/data and private test
fixtures. No production database, service, installation state or credentials were
changed. Existing unrelated shop/shipping edits were left untouched and excluded
from this change. The M1–M2F compatibility runs used a temporary exported
`d113ae69` tree plus only the M2G files, so the unrelated uncommitted migration
catalog/schema did not alter their fixed historical expectations. M2G was also
tested with the current working catalog/fresh-install schema in disposable copies.

## Implementation

The new default needs no mode/statePath setting, private update directory,
additional password, Linux account or service. Existing statePath, M2F pins,
queuePath and explicit shared/worker modes retain their old route. Existing or
broken legacy bindings and the derived GoBD update directory block a forced
database setup. A database state table blocks conflicting legacy HTTP/CLI entry.
No automatic conversion, repair, reset or archival takes place.

`DEEC_UpdateDatabase` stores immutable control, plan, approval, acceptance and
result records in one small InnoDB table; only maintenance is removed on safe
completion/cancellation. Setup is an authenticated CSRF-protected POST, serialized
with a named lock. Ordinary entry uses a shared row lease before bootstrap;
execution uses an exclusive lease on a different connection from M1's DDL.
DDL commits cannot release that gate. M1's named lock/history/checksums/order
remain authoritative. Catalog SQL cannot modify either metadata table.

`DewawiDatabaseUpdates` is an approval/transport adapter around the existing M1,
not another migration engine. It verifies the actual frozen initial schema,
binds a ten-minute exact plan to account/tenant/session and database identity,
checks the actual active administrator and grants again, and executes one step.
Baseline registration and feature execution are separate confirmations. Existing
feature tables/columns without matching history are conflicts, not evidence of
successful execution. In particular, importing a fresh structure with feature
objects but no corresponding M1 history does not automatically mark them applied
or replay their SQL; operator reconciliation is required.

The regular UI shows version, update names/status and the main action. Exact
identifiers/diagnostics and the exceptional command are collapsed. No additional
password or SQL upload exists in this path. Native forms opt out of auto-save.
Legacy detailed workflows remain behind the same collapsed details with their
original password/evidence requirements.

Changed production files are the admin UpdatesController and three update views,
DE/EN update translations, index.php, application.ini comments,
DEEC/UpdateMaintenance.php and the new DEEC/UpdateDatabase.php,
tools/updates/Database.php, tools/update-hosting.php and the two M1 entry/catalog
boundary checks in Inspector.php/Migrator.php. Operating guides and the existing
admin/security fixtures plus the new M2G suite/report complete this change.

## Actual tests

| Command / suite | Result |
| --- | --- |
| `python3 -B tests/admin/update-database.py` | 301 M2G checks passed, including native browser check/confirmation/execution in DE/EN at 1280/390/320 pixels |
| `python3 -B tests/admin/update-worker.py` | 867 M2B/M2C/M2D checks passed on the exported compatibility tree |
| `python3 -B tests/admin/update-worker.py --hosting-only` | Final 701 M2E, 33 M2F filesystem and 100 M2F integration checks passed on the compatibility tree |
| `python3 -B tests/admin/updates.py` | 334 M2A/M2C version/history/access/menu/dashboard/browser checks passed on the compatibility tree |
| `python3 -B tests/install/migrations.py` | 124 M1 checks passed |
| `python3 -B tests/install/import-db.py` | Historical/fresh schema import, failure cases and 66 UOM checks passed |
| `php tests/security/system-updates.php` | 122 administrator/identity/tenant/ACL checks passed |
| `php tests/security/admin-access.php` | 68 checks passed |
| `php tests/security/bootstrap-auth.php` | 29 checks passed |
| `php tests/security/permissions.php` | 450 checks passed |
| `python3 -B tests/admin/update-worker.py --configuration-only` | 22 configuration/environment and 152 error-handling/cron/HTTP cases passed with isolated empty databases |
| `python3 -B tests/security/configuration-http.py` | 9,264 Apache HTTP cases / 4,334 inventoried PHP/include paths passed |
| `python3 -B tests/security/session-http.py` | 25 real session/login/logout/replay cases passed |
| `php tests/ui/dashboard-buttons.php` | 3,620 assertions passed |
| `php tests/translations/runtime.php` | 157 checks passed |
| `python3 -B tests/translations/audit-test.py` | 30 tests passed |
| `php tests/translations/cleanup.php` | 86 cases passed on the compatibility tree |

The configuration and error fixtures now use empty disposable MariaDB schemas:
the early fence intentionally needs database connectivity. The PHP configuration
test starts the existing isolated harness when no fixture socket was supplied.
Its error-HTTP companion can be run through `--configuration-only`.
Legacy browser tests explicitly open their now-collapsed technical details;
M2A and M2F explicitly select the legacy shared mode they exercise. Their existing
negative assertions remain active. The harness restores every registered test
catalog file, including additional local migrations, rather than assuming one file.

M2G covers no GET setup SQL, complete/incomplete/current fresh schemas, missing
and existing history, repeated checks/execution, unrecorded existing feature
tables/columns, all unauthorized roles and cross-tenant administrator access,
CSRF/session mismatch, revoked rights, expired plans, DB identity conflicts,
missing backup acknowledgement, initial and revoked DB privileges (including CREATE without metadata write rights), refused persistent execution connections, orphaned M2F state before bootstrap, actual
parallel requests and CLI execution, held ordinary-request leases, SQL failure,
actual process termination, accepted-without-result state, maintenance retention,
legacy conflicts, corrupt control and supervised execution of the same approval.
There is no automatic retry after SQL or process interruption.

## Security impact and remaining setup

The database mode deliberately shares the existing PHP/database authority.
Backup/restore/writer/historical references are persisted administrator
attestations; unlike legacy file evidence or worker B's external release they
are not independently checked artifacts. They never prove restorability or that
external writers stopped. Actual verification remains an operational prerequisite.
Stop all nonparticipating writers and drain old requests before first setup.

Target hosting still needs suitable database-scoped privileges, InnoDB locks,
two connections and adequate execution time. Short HTTP execution is conservative
and MariaDB-only; unsupported/large changes require supervised execution of an
explicitly approved request. No root/systemd/new account or CLI is required for
eligible web updates. Test the real Apache/PHP-FPM identity, pool/HTTP limits,
all writer entry points, backups and restore procedure on the target host.
Worker B still requires its existing real multi-user OS acceptance; its test
ownership emulation is not proof of deployment isolation.

Preserve both metadata tables on backup/restore. Same-identity restores are not
intrinsically detectable and require independent reconciliation. Unresolved or
partially created metadata requires operator review. Keep an authenticated admin
session until completion/cancellation; maintenance intentionally blocks normal
login/application routes, as in the previous fence.

All 16 changed/new PHP/PHTML and six Python files passed syntax checks; DE/EN
update translation keys match. Worktree and staged whitespace checks passed.

No application version, frozen baseline, published SQL/checksum, fresh-install
schema, worker/Files implementation or unrelated shop/C4B code is changed.
Only the UI POST creates the new infrastructure table; no manual SQL import or
production activation was performed. See [DATABASE.md](../../tools/updates/DATABASE.md).
