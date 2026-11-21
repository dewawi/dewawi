# Controlled system updates (M2B–M2D)

M2B adds approval submission to `/admin/updates/index`. Application version stays
**1.1.8**. No service, account, credential, database privilege, production migration
or private installation setting is installed/changed by this implementation.
M1 remains the only migration engine. Since M2D, the UI also prepares and confirms
a separate baseline-registration operation executed by this worker, including an
independent historical data-backfill audit. Normal administrators do not need to
run tools/migrate.php manually. That tool remains available for separately
authorized technical diagnosis/emergency work; its CLI interface is unchanged.

## Trust boundary and workflow

1. A separately operated CLI worker publishes a locked M1 plan and a 60-second
   heartbeat. The snapshot has a random token and expires after 15 minutes.
2. An authenticated, active, undeleted admin reviews the application version,
   registered history and exact ordered pending IDs, file/definition SHA-256 values
   and plan hash. The server compares current read-only M1 state before accepting
   a POST with CSRF, one-use nonce and explicit confirmation. Tokens also bind the
   account, selected tenant and session ID. No browser SQL, path, database name,
   configuration or command is accepted.
3. The web process writes only `inbox/request.json`. It cannot write the worker's
   private state, published plans/results, configuration or operational evidence.
   An inbox file is untrusted input, not permission to execute SQL.
4. The worker captures one strictly validated request into an immutable private
   record. Until a separate server operator releases **that exact request**, its
   status is waiting and no migration SQL runs.
5. The server operator verifies the actual backup and restore test, maintenance
   fence and reviewed plan, then installs a root-owned release tied to the captured
   request ID, exact captured bytes, operator, plan and installation/database.
   This additional out-of-band release is mandatory. It prevents a compromised
   inbox/web process from independently authorizing privileged SQL.
6. Under both a local worker lock and M1's database lock, the worker rereads M1
   state, compares the complete plan, verifies the external release and actual
   `@@GLOBAL.read_only = 1`, and independently checks the persisted operator account.
   It durably consumes the plan token and records acceptance before invoking
   the matching M1 entry point (`applyApproved` or `registerApprovedBaseline`). Evidence, its lease, database identity, account status and
   the write fence are checked again before each SQL statement. The approved
   catalog bytes are held in memory; deployment must remain frozen throughout.
7. M1 records `started` and each statement number before execution and preserves
   failed/interrupted history. Worker acceptance, sanitized diagnostics and final
   results are retained. A restart never resumes an accepted unfinished request.

The execution plan hashes application version, opaque database identity, frozen
baseline checksum, complete registered history, the entire ordered code catalog
with dependencies/checksums, and the exact pending IDs. Adding a migration,
changing a checksum or changing history invalidates approval; a worker never
silently includes newly installed migrations. M1 plan validation is not a promise
that arbitrary future SQL succeeds or proof of historical data backfills.

The identity hashes a random installation ID and the live selected database,
server hostname, port and server ID. It exposes no database name. Provision a
unique installation ID, pin the resulting fingerprint and use one authoritative
DB server. Do not copy an installation identity/queue to a clone. After restore,
server replacement, failover or connection-routing changes, require a separately
reviewed identity/plan and fresh evidence; replication/multi-primary coordination
is not implemented here.

## Mandatory one-time server setup

These are instructions for an authorized server operator, not commands executed by
Codex. Stage/review the setup on a test installation before enabling production.
Requires PHP with POSIX, fsync, PDO MySQL, reliable local flock/rename/hard-link and
directory-fsync semantics (for example local ext4/XFS), and MariaDB read-only
maintenance support. NFS/shared-host and Windows queues are unsupported. This
implementation was exercised on MariaDB 10.11; separate MySQL acceptance is pending.

Use a dedicated **non-root** OS account/group `dewawi-update`, distinct from the
web account/group (examples below use `www-data`). Do not put the web user in the
worker group, and do not grant it privileged supplementary groups or ACLs. All
ancestors of the queue must be real directories controlled by root; a root-owned
sticky `/tmp` ancestor is permitted for isolated tests only. Production belongs
outside all served document roots and the repository. Never make code, CLI entry
points, application configuration or migration files writable by the web user.
Deploy reviewed code as root-owned/read-only to the web and worker accounts.

The installation owner provisions this layout:

| Path | Owner : group | Mode | Purpose |
| --- | --- | --- | --- |
| `/var/lib/dewawi-updates` | root : root | 0755 | Stable parent; web cannot rename subdirectories |
| `control.json` | root : root | 0644 | Non-secret pinned identities and numeric OS IDs |
| `inbox` | www-data : dewawi-update | 2750 | Only web writes; worker reads |
| `published` | dewawi-update : www-data | 2750 | Only worker writes; web reads |
| `private` | dewawi-update : dewawi-update | 0700 | Immutable captures, claims, results, diagnostics, worker lock |
| `evidence` | root : dewawi-update | 0750 | Only external root authority writes; worker reads |
| `evidence/REQUEST.json` | root : dewawi-update | 0640 | Per-request operational release |
| `/etc/dewawi-update-worker` | dewawi-update : dewawi-update | 0700 | Private CLI configuration directory |
| `worker.json`, `database.ini` in that directory | dewawi-update : dewawi-update | 0600 | Worker settings and DB credentials |

Setgid queue directories provide the correct group for new files without granting
web membership in the worker group. New inbox/public records are 0640; private
records are 0600. The code checks exact modes, numeric owners/groups, effective
UID, forbidden web group membership, real paths, regular-file type, link count,
file size and opened inode. Symlinks and hard-linked queue inputs are rejected.
There is no permissive single-user fallback. Public and private records are
fsynced; immutable records use no-overwrite publication and are never deleted.
Only temporary writes are cleaned up. Audit capacity is bounded at 10,000 retained
requests per queue; plan a separately reviewed archival/epoch change beforehand.

Copy [control.sample.json](control.sample.json) to the root-owned queue parent and
[worker.sample.json](worker.sample.json) to the private configuration directory.
The samples intentionally fail validation until every placeholder is configured.
Use actual numeric `id -u` / `id -g` values; both UID and GID pairs must differ.
Generate a new 128-bit installation ID through an approved random source. For
initial fingerprint provisioning set `database` to 64 zeroes and run, **as the
worker account**, only:

```sh
php /var/www/dewawi/tools/update-worker.php --config=/etc/dewawi-update-worker/worker.json --command=identity
```

The output is an opaque SHA-256 fingerprint. After independently confirming the
private connection selects the intended installation, pin that value in the
root-owned `control.json`. The worker cannot change this pin.

Copy [M1's database template](../migrations/database.sample.ini) to the private
`database.ini`. Use a dedicated DB account with SELECT for M1 schema/history and
operator checks, CREATE plus INSERT/UPDATE for the central history table (including
its first creation), and **only**
the SQL rights needed by the reviewed catalog. Current shipping migration 001
needs CREATE/ALTER/INDEX/REFERENCES; no generic SUPER privilege is necessary on
MariaDB 10.11 when a DBA grants `READ_ONLY ADMIN` for the maintenance bypass.
Review actual grants with the DBA, especially for future DML/DROP migrations.
The ordinary web DB account must have no DDL, M1-history mutation or read-only
bypass authority. Never copy worker credentials into application configuration,
URLs, environment variables visible through phpinfo, inbox files or public results.

Configure the existing protected `configs/database.ini` environment section:

```ini
systemUpdates.queuePath = "/var/lib/dewawi-updates"
```

Since M2C, every authenticated, active, undeleted `user.admin = 1` account may
review and confirm an installation-wide plan, irrespective of its tenant. Web
checks require an identical authenticated/current user ID. Administrators from
other tenants deliberately receive the same update capability; parent/child
sharing and SaaS module permissions do not constrain this admin capability.
Ordinary users remain denied. Both the menu and System dashboard card use the
same permission gate.

There is **no user allowlist** in either the INI or worker JSON. The worker checks
the captured approving user's actual database row for `admin = 1`, `activated = 1`
and `deleted = 0`, independently of the web session. Revocation after approval
blocks both not-yet-captured and already-captured queued requests, and the existing
per-statement checks also detect revocation during execution. The root release
must still match that exact actor/request/plan; removing allowlists does not remove
external operational authorization, backup/restore checks or the write fence.

For existing M2A/M2B setups, the obsolete `systemUpdates.operatorUserIds[]` entry
is ignored by web authorization and can be removed. Remove `operator_ids` from an
existing private `worker.json`: the strict worker format now contains only
`format`, `queue`, `database_config` and `environment`, as shown in the sample.
An obsolete extra field fails configuration validation; it never acts as a user
grant or a filter. No new user configuration replaces these lists. Do not move
queue paths or private DB credentials into user profiles or database settings.
No private installation files are changed automatically by this code update.

Verify **under the actual service accounts** that the web user cannot read
`database.ini`, `worker.json`, `private` or `evidence`, cannot modify `control.json`,
`published` or code, and that the worker cannot modify `evidence` or `control.json`.
Check inherited/default ACLs, supplementary groups, parent directories and the
actual web document roots, not only the displayed modes. Do not enable execution
until these negative tests pass.

## Backup, maintenance and external release

Perform these steps under a separately authorized operational change:

1. Establish a maintenance window. Stop/drain all writers with a read-only bypass,
   including other privileged tools and deployments. A DBA sets and verifies
   MariaDB's **global** read-only fence. It affects the entire server, not just
   DEWAWI; coordinate other databases or use a dedicated server. The worker never
   sets or removes this flag. Ordinary application reads and filesystem-backed
   operator sessions can remain available for approval, but writes must be fenced.
2. Take a full consistent database backup **including M1 history** after the
   maintenance fence begins. Record a durable backup reference, SHA-256, time and
   scope. Independently restore **that artifact** into an isolated environment
   and verify restorability, integrity and required historical data. Record the
   restore test identity, confirmer and time. No automatic backup/restore promise
   is made by this software. Prepare this before the short approval window.
3. Run `--command=publish` as the worker, or let the reviewed periodic `tick` do so.
   `publish` never executes migrations. With no valid baseline/plan or write fence,
   the UI exposes blockers and no approval form.
4. Review and confirm the exact plan in Administration → System → Updates. The
   web writes one request; it cannot launch the worker or privileged subprocesses.
5. Let `tick` capture it. The worker shows `waiting/evidence_required`; find its
   immutable `private/REQUEST.request.json`. Independently compare it with the
   reviewed plan and actual operator approval. Never authorize an arbitrary inbox
   payload or a browser-supplied path. Hash the exact captured file, including its
   terminating newline. The root authority must verify this outside the web process.
6. As root prepare the following release,
   replacing placeholders with the verified captured values and Unix UTC seconds:

```json
{
  "format": 1,
  "request": "32_HEX_REQUEST_ID",
  "request_sha256": "SHA256_OF_PRIVATE_CAPTURED_REQUEST_BYTES",
  "plan": "64_HEX_APPROVED_PLAN_HASH",
  "installation": "32_HEX_INSTALLATION_ID",
  "database": "64_HEX_PINNED_DATABASE_FINGERPRINT",
  "operator": 123,
  "issued_at": 0,
  "expires_at": 0,
  "backup": {
    "reference": "backup-ticket-and-artifact-id",
    "sha256": "64_HEX_ACTUAL_BACKUP_HASH",
    "taken_at": 0,
    "scope": "database-and-history"
  },
  "restore": {
    "reference": "isolated-restore-test-id",
    "confirmed_by": "authorized-external-dba",
    "tested_at": 0
  },
  "maintenance": {
    "reference": "approved-maintenance-window-id",
    "since": 0,
    "until": 0,
    "privileged_writers_stopped": true
  }
}
```

The root-owned file is the external authority's attestation, not a browser checkbox
or an automatically generated assertion. The worker validates its ownership,
mode, exact schema, matching request bytes/plan/operator/installation, backup hash
format, full scope, time ordering, external restore confirmer/reference, maintenance
window and lease. It does not independently read or restore the backup artifact;
the root authority must verify the real artifact and restore evidence before
attesting. References are bounded plain identifiers, never executable commands or
paths. The backup must currently be no more than one hour old; the restore test
must follow it, and the fence must precede it. Schedule backup/restore accordingly.
Large backups requiring a longer window need a separately reviewed policy change;
never falsify timestamps to bypass this requirement.

`issued_at` must follow request creation and not lie in the future. `expires_at`
must not exceed the request expiry or 15 minutes after issue and must fit entirely
inside maintenance `until`. Web confirmations last at most ten minutes. After
expiry, preserve the rejected request and obtain a freshly reviewed plan/approval.

Stop the worker timer/service while installing evidence. Prepare a new temporary
file in `evidence` as root:dewawi-update, 0640, fsync it, atomically rename it to
`REQUEST.json` **without overwriting an existing record**, and fsync the evidence
directory before allowing the worker to run. Do not leave symlinks/hard links or
partial JSON. This is a controlled server-operator step; no HTTP endpoint creates
or edits evidence. Preserve superseded evidence outside the live queue under the
incident/change record; do not overwrite release evidence during a run.

## First baseline registration through the UI (M2D)

When validated M1 history has no `baseline/1.1.8`, worker publication selects the
separate **Check and register database baseline** operation. Under the existing
M1 lock it runs `verifyBaseline` against the actual schema: every required frozen
1.1.8 table, engine/collation, column, named index and foreign key must match.
Missing/changed requirements block the plan. No table/column is repaired or
assumed present. Additional objects remain subject to M1's existing compatibility
rules. The plan includes the frozen-reference SHA-256, normalized actual-schema
SHA-256, history-table presence, history/catalog checksums, database identity and
only the baseline registration in its `pending` list. Application version is not
evidence that these checks passed.

HTTP only validates inexpensive current metadata against that worker snapshot;
it never loads the CLI migrator or performs the full schema scan. The UI displays
the worker check time, operation and hashes, explains the historical-audit
prerequisite and requires a distinct explicit registration confirmation. The same
CSRF/one-use/session/account controls and external operational release apply.
Worker preflight repeats the actual schema check and compares the entire plan
under lock before accepting or writing. A changed schema, including compatible
extra objects, or a changed empty-history-table state requires a new plan/approval.

**Schema matching cannot certify historical DML/backfills.** In addition to the
ordinary root-owned `evidence/REQUEST.json` backup/restore/maintenance release,
require an independently reviewed data-audit report for the actual installation.
It must document installation provenance, the relevant historical upgrade path
through 1.1.8, each required data backfill and the concrete checks/results against
actual data. Never attest merely because tables exist, the label says 1.1.8 or an
old migration file exists. Unresolved historical data means no registration.

The external server authority verifies that report and installs a separate
root:dewawi-update, 0640 `evidence/REQUEST.historical.json`, with the same atomic,
fsynced/no-overwrite publication procedure as other evidence:

```json
{
  "format": 1,
  "request": "32_HEX_CAPTURED_REQUEST_ID",
  "request_sha256": "SHA256_OF_EXACT_PRIVATE_REQUEST_BYTES",
  "plan": "64_HEX_APPROVED_BASELINE_PLAN_HASH",
  "installation": "32_HEX_INSTALLATION_ID",
  "database": "64_HEX_PINNED_DATABASE_FINGERPRINT",
  "operator": 123,
  "scope": "historical-data-through-1.1.8",
  "baseline_sha256": "64_HEX_FROZEN_REFERENCE_HASH_FROM_PLAN",
  "reference": "historical-data-audit-ticket-id",
  "report_sha256": "64_HEX_ACTUAL_INDEPENDENT_AUDIT_REPORT_HASH",
  "confirmed_by": "independent-data-reviewer",
  "checked_at": 0
}
```

Use the captured actor ID and real Unix UTC review time. The audit must be checked
within the current fenced maintenance period (`checked_at >= maintenance.since`)
and not in the future. Both evidence files must name this exact request, captured
bytes, plan, installation and database. Ordinary operational evidence still owns
the expiry, backup/restore and maintenance lease; it is not replaced by the data
audit. Missing historical evidence leaves an unaccepted request waiting; invalid
or mismatched evidence rejects it. The worker independently checks root ownership,
mode, exact schema, binding, audit reference/confirmer, report-hash format and
review time. It does not independently inspect the report artifact or perform
business-specific backfill checks: the external authority must actually verify
them before attesting. Neither the web process nor the worker can create this
root-controlled file. This is a separate prerequisite, not a browser checkbox.

The accepted request, baseline plan and both exact evidence snapshots/hashes are
preserved in private records; public acceptance/results identify `operation =
baseline`. M1's existing `baseline` API is the sole writer of the central history:
it creates/validates that table and persists the baseline's started/completed
markers with the frozen-reference checksum. It never executes historical update
SQL or a feature migration. The durable worker acceptance precedes first history
creation, when no central start row can yet exist. Preconditions are rechecked
before creation, start and completion. A failure/interruption at any of these
points is unresolved and requires manual review, including a created but empty
history table; worker restart never guesses success or retries registration.

After success, the worker publishes the normal next plan immediately. M1 reports
the registered baseline and shipping migration 001 as pending. A new web
confirmation and a new per-request operational release are mandatory before that
migration executes. No feature SQL is automatically run. An already registered
baseline offers only the normal migration plan, never another registration.
Direct technical CLI baseline calls retain their existing idempotence for
separately authorized operator work; no manual history-row editing is introduced.

A development fresh-install snapshot may already contain shipping schema without
feature history. Registering the frozen baseline does **not** certify those
feature IDs or authorize replaying their CREATE statements. Retain M1's existing
snapshot/provenance limitation: obtain a separately reviewed initialization or
recovery procedure before approving a conflicting feature migration. M2D never
infers completed feature history from table existence.

No additional worker JSON settings, SQL migration files, frozen reference changes
or application-version changes are required. The DBA must ensure the dedicated
worker can create the previously absent history table and insert/update its rows
under the read-only maintenance fence. Existing installations with table-specific
grants may need that separately authorized privilege setup; no grants are applied
by this implementation. Real OS-account/service acceptance remains mandatory.

## Running and observing the worker

```sh
php /var/www/dewawi/tools/update-worker.php --config=/etc/dewawi-update-worker/worker.json --command=publish
php /var/www/dewawi/tools/update-worker.php --config=/etc/dewawi-update-worker/worker.json --command=tick
```

Run these as the dedicated worker user. Arguments are fixed operator-selected
configuration, never interpolated web input. There are no baseline, repair, SQL,
checksum-override, free-command or bundle parameters. Default is one `tick`, not
an endless daemon. Repeated inspection of missing evidence is safe; accepted SQL
is never retried. Invalid private configuration prints a generic message and exits
nonzero without leaking paths, credentials, SQL or traces.

[The service](dewawi-update-worker.service) and [timer](dewawi-update-worker.timer)
are examples only. Review paths, users, MySQL connectivity and mount restrictions,
then use `systemd-analyze verify` before separately approved installation. No unit
has been installed/enabled here. Do not use this worker under the PHP/web account,
root, an HTTP-triggered shell or a broadly privileged cron account.

Refresh the UI to see current/last request, prerequisite codes, UTC timestamps and
the ten most recent retained results. It uses native GET/POST, no auto-save or
background shell. Stale heartbeat or changed M1 state removes the approval form.
An offline worker's last state is retained; a `running` record plus lost heartbeat
is a reason to investigate, never proof of success. Final public records contain
only request ID, operator ID, plan digest, operation, timestamp and sanitized status/reason.
Private diagnostics and M1 history retain the migration/statement/SQLSTATE evidence
without putting SQL contents or credentials in HTTP responses or process output.

## Failure and recovery

Before SQL, plan/database/operator/evidence/fence mismatches reject the request.
Missing external evidence or a busy M1 lock leaves an unaccepted request waiting
until expiry; no migration runs. Duplicate POSTs, competing approvals and consumed
plan tokens cannot run a migration twice. Keep one queue on one local worker host.

After durable acceptance, SQL failures are recorded by M1 and worker. Process loss
leaves immutable acceptance without completion; the next worker marks manual review
and blocks all further execution. A crash before the first SQL statement is also
blocked conservatively. Neither existing tables nor last statement number prove
completion or rollback. Successful jobs have an immutable result; if result
publication fails after SQL, preserve evidence and reconcile manually.

Do not delete failed/started M1 rows, erase claims, edit checksums, mark completion,
retry SQL or restore a database without explicit operational authorization. Stop
worker and competing application writes, preserve the entire queue, root release,
exact deployed code/catalog, M1 history and backups. Inspect actual schema/data
against each attempted statement. Obtain a separate recovery plan, preferably a
verified consistent pre-run database/history restore where appropriate. M1 must
then independently validate the recovered state.

There is deliberately no web/worker repair or resume action. An unresolved worker
claim requires an audited operator recovery and a **new queue epoch/installation
identity**, retaining the original queue as incident evidence, plus a new backup,
root release and operator approval. This cannot silently clear unresolved M1
history: M1 still refuses it. Do not reuse a consumed approval after restore.

## Verification limits

Run `python3 -B tests/admin/update-worker.py`, plus the existing M1/M2A, installer,
shipping, authorization and HTTP/browser suites. The current execution host exposes
only one usable mapped UID and cannot provision three service identities. Protocol
integration tests therefore use an explicitly named ownership test double, real
files/fsync, real MariaDB and distinct SELECT-only web/migration DB accounts. The
production ownership/identity gate is separately asserted to reject that host's
unsafe queue. No production bypass/test mode exists.

**Actual multi-user OS denial tests and systemd service execution remain mandatory
on the deployment/test server before activation.** The local protocol tests are
not certification of that untested boundary. No production setup has been enabled.
See the current [M2D test report](../../tests/admin/update-baseline.md) and the
historical [M2B test report](../../tests/admin/update-worker.md).
