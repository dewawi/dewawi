# Controlled SQL migrations (M1)

This is development infrastructure for transitions starting at **1.1.8 → 1.1.9**.
It neither releases 1.1.9 nor changes application versions. The frozen 1.1.8
reference, `update-1.1.7-1.1.8.sql`, installer and old update paths are unchanged.
C4A.2 adds the first feature migration and synchronizes the development
`structure.sql`; it does not regenerate the frozen reference. No ordinary
application bootstrap, cron, shop or API call runs migrations. Use explicitly
authorized update actions in [hosting mode A](../updates/HOSTING.md), the separate
B worker, or the technical CLI with an explicitly selected database configuration.

## Read-only system overview (M2A)

`Administration → System → Updates` (`/admin/updates/index`) displays the
released version from the Git-versioned `configs/version.php`, shared with the
ERP footer and installer. The released version remains **1.1.8**, independently
of development transitions and registered database migrations. No reliable build
identifier is currently shipped, so none is inferred from Git, timestamps or dates.
Missing, malformed or output-producing version files display an unknown version;
there is no hardcoded fallback.

Since M2C, every authenticated, active, undeleted account with `user.admin = 1`
may open the update overview and confirm a concrete plan. The authenticated user
ID must match the current account. No INI or JSON user allowlist is required.
Administrators from any tenant, including selected parent/child contexts, therefore
receive this **installation-wide** authority. Ordinary users, inactive/deleted
admins and mismatched identities are denied. The left menu and central System
card use the same gate as the ACL and controller.

In mode B, execution requires the separate worker and per-request root-controlled
backup/restore and maintenance release. The worker independently checks the actual
approving account in the database before execution and again at each precondition
check; revoking admin/active status or deleting the account blocks queued work.
`systemUpdates.queuePath` and private migration credentials remain protected
technical installation settings. See the [M2B operations guide](../updates/README.md)
for setup and removal of obsolete allowlist settings.

The overview loads `Inspector.php`, the common M1 catalog/history reader. The
CLI migrator inherits the same catalog, SQL splitter and history validation;
ordinary overview GET/HEAD never include the migrator or runner. The installer SQL
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
belong to explicit locked preparation/execution in A or the separate CLI in B.
The page always says verification is required.
The shared row validation also rejects invalid/zero history timestamps instead
of displaying them as successful completion.
It cannot prove current schema integrity or past data backfills, even when every
registered checksum matches. Concurrent CLI changes can invalidate the displayed
snapshot immediately. An empty history never certifies the baseline; existing
shipping tables never certify migration 001. An invalid or unreadable history
withholds the pending count and last-success summary. Unknown IDs, raw database
exceptions, SQLSTATE values, SQL contents and private paths are never displayed.
Descriptions come only from reviewed translation strings for known catalog IDs.

GET and HEAD of `index` remain read-only. In optional mode B, M2B adds a protected
POST `approve` action, which writes an untrusted bounded request to an external
file queue. It cannot execute SQL or start a process. Without a verified worker
snapshot and safe queue configuration there is no approval form. Inherited CRUD,
export/search, baseline/apply/bundle/SQL and repair actions remain rejected.
Responses are private/no-store. DE/EN status text, native navigation, focusable
alerts and responsive migration cards support keyboard and narrow-screen use.

No per-user configuration is necessary for administrator access. The implementation
does not edit private INI configuration or change an installation database on GET.
No SQL migration is required for this feature. Since M2D, a missing baseline can
be checked/registered through its own UI approval and CLI-worker operation; failed
or ambiguous history still needs the separately authorized recovery process.

## Controlled execution (M2B)

The [M2B operations guide](../updates/README.md) specifies the implemented worker,
external file queue, mandatory ownership separation, exact-plan approval, root-owned
per-request backup/restore release, technical write fence, service examples and
manual recovery procedure. No worker service or private configuration is installed
implicitly. The ordinary web process has no privileged credentials or shell channel.

M1's shared `executionPlan` exposes only catalog/history metadata and an opaque
installation/database identity. `applyApproved` requires the M1 lock and identical
approved/current plans, then uses the existing `apply` implementation. Its optional
precondition callback rechecks the worker release, write fence and operator before
each migration, each statement and completion recording. Ordinary M1 CLI commands
retain their existing interface and do not gain any HTTP execution path.

Worker acceptance/results and external operational releases are audit evidence,
not a second database-version history. Only `dewawi_schema_migration` records
migration success. Failed/started states remain blocked. M2D adds only the separately approved
baseline-registration worker operation described below; checksum overrides and
automatic repair remain unavailable.

## Baseline operation (M2D)

Normal administrators use **Check and register database baseline** in the update
UI. The CLI worker's `baselinePlan` reuses `verifyBaseline`, checks actual schema
under lock and binds its normalized fingerprint plus reference/catalog/history
metadata. `registerApprovedBaseline` repeats that comparison, then calls M1's
existing `baseline` with the worker's preconditions. Missing schema never becomes
a registered basis. Successful registration publishes only the next pending
feature plan; it cannot execute shipping automatically.

Historical data/backfills need separate independent root-controlled evidence in
`evidence/REQUEST.historical.json`, in addition to backup/restore/maintenance
release. Both sources are bound to the same request/plan/identity and preserved
unchanged with the worker audit. See the [M2D operations procedure](../updates/README.md#first-baseline-registration-through-the-ui-m2d).
No browser request writes schema/history, runs a shell or automatically registers
anything. Existing diagnostic/emergency CLI commands below remain available to
separately authorized technical operators with the normal operational safeguards.

## Operator commands

M2E adds [hosting mode A](../updates/HOSTING.md): explicit authorized HTTP or
same-account CLI execution reuses this engine, executing only the next approved
feature step. The standalone migrator URL remains denied. Normal GET, ERP, shop,
API and cron requests never run migrations. CLI `baseline`, `apply` and bundle
interfaces remain compatible; default calls still apply the complete M1 plan.
The separate worker retains its original guards and CLI-only boundary.

When hosting mode A is configured, technical installation CLI entry points also
take the cooperative shared maintenance lease, including `migrate.php`. Use the
documented `update-hosting.php` consumer for an approved request during maintenance;
do not bypass its fence with an emergency CLI command.

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
python3 -B tests/admin/update-worker.py
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
