# Controlled SQL migrations (M1)

This is development infrastructure for transitions starting at **1.1.8 → 1.1.9**.
It neither releases 1.1.9 nor changes application versions. The frozen 1.1.8
reference, `update-1.1.7-1.1.8.sql`, installer and old update paths are unchanged.
C4A.2 adds the first feature migration and synchronizes the development
`structure.sql`; it does not regenerate the frozen reference. No application bootstrap, HTTP, cron or shop entry point calls it.
Run it manually with PHP CLI and an explicitly selected database configuration.

## Read-only system overview (M2A)

`Administration → System → Updates` (`/admin/updates/index`) displays the
released version from the Git-versioned `configs/version.php`, shared with the
ERP footer and installer. The released version remains **1.1.8**, independently
of development transitions and registered database migrations. No reliable build
identifier is currently shipped, so none is inferred from Git, timestamps or dates.
Missing, malformed or output-producing version files display an unknown version;
there is no hardcoded fallback.

`User.admin` is tenant administration, not installation operator authority. Access
therefore requires an active, undeleted, authenticated admin account **and** its
persisted user ID in the explicit installation allowlist. Add this setting to the
appropriate environment section of the protected, untracked
`configs/database.ini`, replacing the placeholder with the reviewed account ID:

```ini
[production]
; Existing database settings remain here.
systemUpdates.operatorUserIds[] = "REPLACE_WITH_PERSISTED_ACCOUNT_ID"
```

Use one numeric ID per entry. Missing settings, scalar settings, malformed lists,
inactive/deleted accounts or mismatched identities deny access. The default is
no operators. Manage this grant through the installation's protected filesystem,
never through the tenant Config table, user forms or permission JSON. Protect the
operator account from delegation to ordinary tenant administrators. The selected
tenant and parent/child sharing neither grant nor revoke this installation-wide
authority. The menu, ACL and controller independently use the same gate. Both ACL
and controller reject ungranted admins; the controller also checks requests that
bypass the routing ACL.

The overview loads `Inspector.php`, the common M1 catalog/history reader. The
CLI migrator inherits the same catalog, SQL splitter and history validation;
the web process never includes the CLI migrator or runner. The installer SQL
splitter is extracted without changing its API or parsing behavior, so inspection
does not install the installer's error handlers. The shared inspector has no
baseline registration, SQL execution, lock/session mutation or history repair API.
It reads only catalog files and the current database's central
`dewawi_schema_migration` metadata/history. It does not initialize a missing table.
No second schema-version store or SQL migration is introduced.

This is an **unlocked snapshot**, not an approved execution plan or a live schema
certificate. Only the central history table's engine/columns/primary key,
registered row state, statement counts, ordered dependencies, and file/definition
checksums are checked. Full application-schema inspection and lock acquisition
remain in the separate CLI run. The page always says verification is required.
The shared row validation also rejects invalid/zero history timestamps instead
of displaying them as successful completion.
It cannot prove current schema integrity or past data backfills, even when every
registered checksum matches. Concurrent CLI changes can invalidate the displayed
snapshot immediately. An empty history never certifies the baseline; existing
shipping tables never certify migration 001. An invalid or unreadable history
withholds the pending count and last-success summary. Unknown IDs, raw database
exceptions, SQLSTATE values, SQL contents and private paths are never displayed.
Descriptions come only from reviewed translation strings for known catalog IDs.

GET and HEAD of `index` are the only allowed operations. Every other action,
including inherited CRUD/export/search actions, is rejected before base-controller
initialization; mutation methods on `index` return 405. There are no forms,
execution buttons, SQL inputs, command parameters or shell calls. Responses are
private/no-store. DE/EN labels, status text, native anchor navigation, focusable
alerts and responsive migration cards support keyboard and narrow-screen use.

Configure operator access manually when the installation owner authorizes it.
M2A itself does not edit private configuration or any installation database.
No SQL follow-up is required for this feature. Any missing baseline or failed
migration still needs the separately authorized M1 verification/recovery process.

## M2B boundary (design only)

No worker, queue or update-request table is implemented in M2A. A separately
authorized M2B must implement this sequence:

1. The system operator reviews a freshly verified plan with explicit immutable
   migration IDs, dependencies, checksums and installation/database identity.
2. An explicit, authenticated, CSRF-protected confirmation approves exactly that
   plan. Bind approval to the operator, plan identity and expiry; changed plans
   require new approval. A read-only M2A snapshot is insufficient approval.
3. Require auditable evidence of a suitable backup and demonstrated restorability,
   plus the agreed maintenance window and control of application writes.
4. A separate privileged CLI worker consumes only a validated structured approval
   record. The web process must not have worker credentials, migration privileges
   or a general shell/SQL channel. It cannot supply shell commands, executables,
   configuration paths, arbitrary SQL, database credentials or bundle paths. The
   worker resolves fixed installation configuration and approved catalog IDs
   locally, and must reject replayed, expired or altered requests.
5. Before any write the worker invokes M1 under its existing installation lock,
   rereads central history, checksums, dependencies and the approved plan, and
   stops on drift, unresolved state or missing prerequisites. Reuse M1's central
   history and interruption semantics; never add an independent success ledger.
6. Record approval, start, result and sanitized errors with an auditable request
   identity. Keep privileged diagnostic details outside the web response.
7. Started/failed migrations remain blocked for separately authorized manual
   inspection and recovery. DDL may already be committed. Never resume, override
   checksums or manufacture successful history automatically.

Choose and review the worker trust boundary, approval persistence/replay control,
backup evidence, maintenance coordination and audit access in M2B before adding
execution. M2A does not start that work or grant permission to update a database.

## Operator commands

Copy [database.sample.ini](database.sample.ini) to a private location outside the
web root; restrict filesystem access and choose the existing Zend INI section.
Use a dedicated connection without persistent connections, proxies that multiplex
connections, or external transactions. Connection errors and SQL errors never
print passwords, connection parameters, raw SQL or exception traces.

```sh
php tools/migrate.php --help
php tools/migrate.php --config=/private/migration.ini --environment=migration
php tools/migrate.php --config=/private/migration.ini --environment=migration --command=plan
php tools/migrate.php --config=/private/migration.ini --environment=migration --command=baseline
php tools/migrate.php --config=/private/migration.ini --environment=migration --command=apply
```

Default `status` and `plan` are equivalent read-only inspections. They create no
tables or history rows. They show the baseline, existing history and pending IDs.
An invalid schema, changed checksum, missing migration, dependency gap or
unresolved execution exits nonzero. For unresolved rows the error names the
migration, status and statement number; inspect its timestamps and SQLSTATE in the
central table using an authorized read-only SQL session. Plan verifies file
syntax restrictions and ordering, not whether arbitrary SQL will succeed on a
particular future schema; it never executes migration statements for validation.

`baseline` is an explicit write command. It first validates all 124 required
tables, their engines/collations, column types/nullability/defaults/extra/generated
expressions, ordered index definitions including prefixes and uniqueness, and
ordered foreign keys with update/delete rules and the referenced database. Only
then does it create the installation-wide InnoDB table `dewawi_schema_migration`
and register
`baseline/1.1.8`. The table has no client/tenant key. A second valid invocation
does nothing. `apply` requires the registered baseline; it never registers it
implicitly. History creation/registration requires CREATE/INSERT/UPDATE plus
schema visibility; migration privileges depend on the reviewed SQL.

The frozen [baseline-1.1.8.json](baseline-1.1.8.json) was extracted from the released
fresh schema on isolated MariaDB 10.11.14. It records the source SQL hashes.
It is independent of later changes to `structure.sql`. Do not regenerate or edit
this reference when developing 1.1.9. Integer display widths, `utf8mb3` aliases,
MariaDB quoted defaults, column order and current AUTO_INCREMENT counters are
normalized/ignored. Required named indexes must match; additional indexes,
columns and tables are retained, including the legitimate historical
`address.street` index. Baseline validation performs no ALTER or data repair.
The released schema contains no views, triggers, routines, events or CHECK
constraints to register. Local extras are not a certification of custom code.

An application version label proves nothing about the actual schema. Existing
installations must complete the old version transitions in order before baseline
registration. A partially applied 1.1.7 → 1.1.8 update is rejected if any required
schema element is missing or changed. Review and apply only missing historical
steps after checking the actual state and the authorized backup/restore process;
never rerun the non-idempotent update wholesale. Schema inspection cannot prove
that historical data backfills were performed. The operator must verify those
separately against the old upgrade SQL. The baseline row certifies the checked
schema, **not** execution of every old SQL statement. No historical SQL file is
silently entered as a successful migration.

## History, locking and partial failure

Each row stores the full ID, SHA-256 of the exact SQL bytes, a second checksum of
ID/file checksum/dependencies, `started_at`/`completed_at` UTC timestamps, status,
current statement number and sanitized SQLSTATE error code. Baseline checksums
refer to the frozen reference file. Completed history must form an unbroken
prefix of the catalog, and executed definitions must still be present unchanged.
Malformed history or unresolved rows block further commands.

All database commands take a zero-wait MySQL/MariaDB `GET_LOCK` named by database
identity. The same connection holds it throughout validation and execution;
another updater/baseline/status command fails immediately with
`migration_lock_busy`. This coordinates cooperating CLI processes on the same
database server; it does not block application SQL or a separate multi-primary
server. Use one authoritative database server and the normal maintenance window
to control application writes. The lock is released in `finally`; losing the
connection also releases it. No HTTP updater is installed, and both PHP files
return 404 before loading any configuration when called outside CLI.

Before running any migration SQL, `started` is committed in the InnoDB history
table with autocommit enabled. The statement number is persisted before each
statement. Only a complete successful run becomes `completed`. SQL failures are
marked `failed`; a killed process or lost connection can leave `started` instead.
Both states block retry and later migrations. There is no automatic rollback,
resume, retry, mark-completed, checksum override, baseline override or repair
command. Earlier DDL and DML may remain committed. Even an interruption after the
last SQL statement but before completion recording requires manual review.

For a failure/interruption:

1. Stop competing updater/application writes. Preserve database and history,
   the exact deployed catalog/bundle, and the error and statement number.
2. Check authorized backups/restorability and compare the **actual** schema and
   affected data with every statement of that migration. The statement number
   indicates attempted execution, not proof of completion or rollback.
3. Restore the consistent pre-run database/history backup where feasible, then
   rerun only after the cause is corrected. Otherwise obtain a separately reviewed
   recovery plan for the exact partial state. Any manual completion/history
   reconciliation must be explicitly authorized and audited outside this tool;
   never just delete a started/failed row and retry or falsify success.
4. Re-run status/plan after the reviewed recovery. Keep successful migrations
   immutable and retain the incident evidence.

## Consolidated release updates

The catalog convention is in
[install/config/migrations/README.md](../../install/config/migrations/README.md).
When an actual transition exists, generate its deterministic consolidated SQL
without a database connection:

```sh
php tools/migrate.php --command=bundle --transition=1.1.8-1.1.9 --output=/private/update-1.1.8-1.1.9.sql
php tools/migrate.php --config=/private/migration.ini --environment=migration --command=plan --bundle=/private/update-1.1.8-1.1.9.sql
php tools/migrate.php --config=/private/migration.ini --environment=migration --command=apply --bundle=/private/update-1.1.8-1.1.9.sql
```

Generation refuses to overwrite a file. The bundle includes each exact SQL file,
ID, file checksum and definition checksum. Applying a bundle requires byte-for-byte
equality with the local catalog's generated bundle, including all metadata.
The CLI then uses the same per-migration execution and history as ordinary apply;
it does not send the whole bundle to the server. It applies only that transition,
requires all earlier dependencies to be completed, and skips already completed
IDs. Switching execution paths cannot run the migration twice.

The SQL starts with a deliberate SIGNAL guard so an ordinary direct SQL import
stops. Direct import (especially force/continue-on-error clients, removing the
guard or executing a selected fragment) is **not a supported update path** and
cannot maintain reliable history. Distribute individual migrations and registry
with the bundle; the bundle is not a standalone replacement for the catalog.
No official `update-1.1.8-1.1.9.sql` has been generated. The development catalog
now contains `1.1.8-1.1.9/001-shop-shipping.sql`; it is not a release declaration.

## Fresh installations and future releases

Fresh installs use the existing `structure.sql` + `data.sql` importer. The
development snapshot already includes C4A.2 shipping tables and must **not** run
their CREATE migration again. It has no automatically initialized M1 history.
The separately approved initialization procedure described below is still
required before a future snapshot release. Do not infer executed migration IDs
from table existence or manually insert successful history rows.

Existing complete **1.1.8** installations use explicit baseline registration
followed by plan/apply. The normal installer never invokes this runner. The
installer tests exercise the development snapshot and the historical 1.1.7 →
1.1.8 schema separately. M1 safety tests use the frozen release source from
`8ab77ce4`; shipping tests compare the actual M1-upgraded schema with a development
fresh install, excluding the central history table.

For every later feature migration, updating `structure.sql` for the equivalent
fresh schema is mandatory in the same development block. Keep any required
initial-data changes in sync too. Test both the complete prior-release upgrade
and fresh installation, including schema equality, tenant/data invariants and
already/partially applied states. Keep the frozen 1.1.8 reference unchanged.

Before shipping 1.1.9, explicitly decide and implement the verified initialization
of history for a fresh **1.1.9 snapshot**. It must compare the actual final schema
and initial-data requirements with the approved release reference before entering
the covered immutable IDs/checksums, using the same lock and central history.
Do not run CREATE migrations over an already updated fresh snapshot or infer
completion from a version string/table existence. M1 supports the currently
released 1.1.8 fresh schema; it intentionally does not assert that future feature
migrations were executed by an installer that does not yet exist.

Before release, also approve the actual version change, finalize/catalog the real
migrations, compare fresh/upgrade schemas, generate the consolidated file using
this tool, run both update paths on target database versions, verify backups and
historical data backfills, and review custom installation differences. MySQL
compatibility is designed around shared INFORMATION_SCHEMA/GET_LOCK/DDL APIs;
M1's actual database run uses MariaDB, not a separate MySQL server.

## Regression tests

```sh
python3 -B tests/install/migrations.py
python3 -B tests/install/import-db.py
python3 -B tests/admin/updates.py
```

M2A coverage and concrete results are recorded in
[tests/admin/updates.md](../../tests/admin/updates.md).

The M1 runner creates its own temporary data directory, Unix socket, synthetic
databases and loopback HTTP server. MariaDB has networking disabled. It never
reads application database settings. Fixtures cover baseline/fresh/full historical
upgrade, partial schema, checksums/dependencies/order, repeated apply, shared
bundle history, failed/partial SQL, process interruption and concurrent commands,
invalid history, credential-safe errors and PHP HTTP guards. Synthetic migrations
are generated only inside its temporary source copy and are removed with it.
