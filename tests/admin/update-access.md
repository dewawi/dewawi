# M2C administrator access verification

M2C removes the extra per-user grants from web authorization and the CLI worker.
Every authenticated, active, undeleted `user.admin = 1` account can view updates
and confirm a concrete plan, including administrators from other tenants. The
current user ID must match the authenticated identity. Menu, System dashboard
card, ACL and controller share `DEEC_SystemOperator::allowed()`.

The worker independently rereads the actual approving account from the database
before execution and in the existing per-statement precondition checks. There is
no configured list of database user IDs. Ordinary users, missing accounts,
inactive/deleted accounts and revoked admins cannot execute a queued plan.

## Results

Verified locally on 2026-11-21 using disposable databases and synthetic fixtures.
No production database, private configuration, service or worker was changed.

| Command | Result |
| --- | --- |
| `php tests/security/system-updates.php` | 122 administrator, identity, missing/invalid flags, tenant and ACL cases passed without an INI/bootstrap user grant |
| `python3 -B tests/admin/updates.py` | 334 M2A/M2C overview, history, authorization, menu/dashboard and DE/EN desktop/mobile checks passed |
| `python3 -B tests/admin/update-worker.py` | 470 M2B/M2C protocol/MariaDB/HTTP/Chromium checks passed, subject to the existing ownership-emulation limit below |
| `python3 -B tests/install/migrations.py` | 124 M1 checks passed |
| `php tests/security/admin-access.php` | 68 ACL cases and forbidden response passed |
| `php tests/security/bootstrap-auth.php` | 29 authentication/tenant/sharing cases passed |
| `php tests/security/permissions.php` | 450 permission/ACL cases passed |
| `python3 -B tests/security/configuration-http.py` | 9,222 Apache HTTP cases passed, including 4,319 inventoried PHP/include paths |
| `php tests/ui/dashboard-buttons.php` | 3,620 existing dashboard assertions passed |
| `php tests/translations/runtime.php` | 157 checks passed |
| `python3 -B tests/translations/audit-test.py` | 30 tests passed |
| Syntax validation | All 9 changed PHP/view files, changed Python files and worker sample JSON passed |

The Apache security inventory ran after staging to include the new PHP test path.

The existing independent `php tests/translations/cleanup.php` still exits 255:
its unchanged cart fixture has no `SiteContext` for
`application/modules/shops/views/scripts/cart/index.phtml:37`. That suite is not
claimed as passed and neither affected file is modified by M2C.

## Scope of the checks

The overview fixtures supply no user allowlist. Two different administrator IDs
are accepted across three selected tenant contexts, including parent admin sharing.
The sidebar and System card each expose the same Updates link. Non-admins,
inactive/deleted admins, guests and mismatched identities receive no Updates entry
and cannot open/confirm the update route, including direct controller dispatch.
Chromium tests both the overview and dashboard at 1280, 390 and 320 pixels in
German and English, including translations, overflow and focusable links.

Worker integration adds a second real stored admin account in a different tenant.
Each of its approvals remains waiting without the external release and completes
only with the existing root-controlled backup/restore/maintenance evidence. The
captured actor ID is preserved. The suite rejects ordinary/unknown stored actors
independently of the web fixture's claimed flags. Revocation is tested before
capture and after capture/release; admin revocation, deactivation and deletion
all block SQL and leave only the baseline history entry.

All previous negative cases for CSRF, replay, exact plan/checksums, double/parallel
submission, technical write fence, invalid/missing backup evidence, unsafe paths,
symlinks, failed/interrupted M1 history and no HTTP SQL/shell execution remain.
Read-only web DB credentials, response-secret checks and the production queue
rejection remain in place. The M1 engine, migration SQL and Files.php ownership
and access controls are unchanged.

## Deployment and compatibility

This deliberately broadens the installation-wide overview/approval capability to
**all tenant administrators**. It does not permit ordinary tenant users to approve
updates. Actual migration execution still requires the independent external root
release, verified backup/restore evidence, maintenance fence, private worker
credentials and the existing M1 locks/history safeguards.

No SQL migration or version change is required. Version remains 1.1.8. Existing
`systemUpdates.operatorUserIds[]` entries are ignored and may be removed. Remove
`operator_ids` from an existing private `worker.json`, whose strict schema now
contains only `format`, `queue`, `database_config` and `environment`. No replacement
user grant is required. `systemUpdates.queuePath` and private credentials remain
technical installation settings outside user profiles and database tables.

The host still exposes one usable mapped UID. The M2B protocol fixture explicitly
emulates ownership while exercising real files, locks and MariaDB. Real cross-UID
credential/private-state denial tests and full production CLI/service startup
remain deployment-server acceptance prerequisites; they are not claimed as
verified locally. No production bypass was added and no worker was activated.
See [the operational guide](../../tools/updates/README.md) for the unchanged OS,
backup, maintenance and recovery requirements.
