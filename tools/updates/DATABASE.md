# Database-backed administration (M2G)

Administration → System → Updates shows application version, installed catalog
updates and their status. Technical identifiers, history/checksum diagnostics and
the exceptional technical execution command are in collapsed details. The
application release remains 1.1.8; a release number never proves a schema state.

## Normal operation

1. An authenticated, active, undeleted administrator chooses “Check and perform
   updates”. Its POST uses the existing session and CSRF; it asks for no password.
   Administrator access is installation-wide across tenants. It is reread from
   the actual user row on approval and before every M1 statement.
2. If needed, the application creates `dewawi_system_update` under a named setup
   lock. GET never creates tables or registers history. This infrastructure table
   is not a feature migration or a second history. Its immutable records contain
   installation/database binding, exact plans, session-bound expiring approvals,
   operational references, acceptance and results; only the maintenance record
   can be removed by a successful execution or explicit safe cancellation.
3. With no registered baseline, the existing M1 verifier checks the real schema
   against the frozen 1.1.8 reference, including columns, indexes and constraints.
   Missing elements are errors. Historical data transfers require independent
   operational records and explicit confirmation; schema matching cannot prove
   them. Registration and feature migrations require separate confirmations.
4. Preparation drains participating requests and activates database maintenance.
   Review the next concrete step. Confirm a current backup reference, a verified
   restore-procedure reference, and that all other writers have stopped. First
   registration additionally requires the historical verification reference.
   These are recorded operator attestations, not automated validation of backups,
   restore results or historical data. Do not confirm unverified prerequisites.
5. Approval executes exactly one short suitable catalog migration via M1, or only
   registers the verified initial state. A successful result removes maintenance.
   Choose the button again for the next open migration. Cancellation removes
   maintenance only when no accepted unresolved job/history exists. It never
   removes history, approval, identity or results.

## Concurrency and interruption

The state table uses InnoDB. Every participating ordinary HTTP/cron/technical
entry takes a shared row lock on its own nonpersistent connection before
bootstrap. Preparation/execution uses an exclusive row lock on that same gate.
The gate connection is separate from the SQL connection: MySQL/MariaDB DDL
implicit commits do not release it. New requests are denied while maintenance
is set. A three-second lock wait fails safely if existing requests cannot drain.
This cooperative fence cannot stop direct SQL clients or old code/requests that
started before the table existed. Explicitly drain those and stop independent
jobs/integrations before confirming, especially on first setup/deployment.

M1 retains its independent named database lock, immutable checksums, dependency
order and started/failed/completed history in `dewawi_schema_migration`. An
accepted request is durable before calling M1. SQL errors or termination leave
maintenance and unresolved state for inspection. No retry, repair, ID regeneration
or deletion is automatic. After a restore, reconcile state/history and actual
schema/data with backup records. Same-identity restores cannot be intrinsically
detected; do not clear records to bypass a conflict. Back up both metadata tables
with the database. A partially created state table also requires operator review.

## Hosting requirements and exceptional execution

The normal database user needs SELECT/CREATE/INSERT/UPDATE/DELETE for setup and
metadata. Feature execution additionally requires ALTER/INDEX/DROP/REFERENCES.
Role/table-only grants not recognized by the conservative grant check are refused;
have the operator review appropriate database-scoped grants, never grant global
privileges merely to bypass a failure. Two simultaneous nonpersistent database
connections and InnoDB row/named locks are required. All writers must participate
in maintenance or be independently stopped. No new account, root or systemd is
required and no setup is performed against production by this implementation.

HTTP execution requires MariaDB, at least 25 seconds of remaining PHP execution
time (or no PHP limit), at most 20 statements/128 KiB, and a conservative 8 MiB
largest-table size limit. Only CREATE TABLE and additive ALTER TABLE without
SELECT/DROP/RENAME/MODIFY/CHANGE are eligible. Statement/metadata lock timeouts
and a total deadline bound execution; a timeout can leave partially committed
DDL and always requires inspection. This is a conservative suitability filter,
not a performance or rollback guarantee. HTTP/server/hosting timeouts still need
target-host acceptance. MySQL, larger or other changes require supervised execution.

For that alternative, confirm the same exact plan and operational references in
the UI. The details show the request ID. Within its ten-minute lease, an authorized
operator can run the existing technical entry:

```
php tools/update-hosting.php --config=/private/database.ini --environment=production --command=execute --request=<approved-id>
```

It consumes that persisted database approval, independently checks the saved
administrator and uses the same maintenance gate and M1 history. No new command,
SQL upload or arbitrary command/SQL from the browser is accepted. The CLI is an
exceptional alternative, not a prerequisite for short web updates. The original
`tools/migrate.php` remains available for separately reviewed emergency work;
it does not bypass active database maintenance.

## Existing M2E/M2F and worker installations

An existing statePath, M2F locator/ready pin or queuePath retains the old mode.
An explicit `systemUpdates.mode = shared` or `worker` still selects that mode.
An uninitialized GoBD provider alone no longer selects shared mode. Its derived
existing update directory is a conflict requiring review before database setup.
The old paths, identities, evidence and in-progress jobs are never imported,
overwritten, ignored to start fresh, or deleted. Missing/corrupt pins still fail
closed. A deliberate `mode = database` with legacy bindings is also blocked.
Database state and a legacy mode cannot coexist for execution. There is no
automatic conversion/mode switch. Have the operator resolve existing jobs and
design a reviewed transition; changing paths/settings is not recovery.

Legacy hosting keeps password/file-evidence checks. The separate worker keeps
its root-controlled release, account isolation and backup/global-write-fence checks.
Their technical UI is collapsed, and the normal button opens those existing
controls. These optional installation choices do not configure the new default.

No SQL feature file, published migration, frozen reference, application version
or fresh-install schema is changed. Runtime setup is the only new table creation,
explicitly authorized through the admin POST. No SQL migration executes on GET.
