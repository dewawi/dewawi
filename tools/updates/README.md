# Controlled system updates (M2B)

M2B adds approval submission to `/admin/updates/index`. Application version stays
**1.1.8**. No service, account, credential, database privilege, production migration
or private installation setting is installed/changed by this implementation.
M1 remains the only migration engine. Baseline registration remains a separate
explicit CLI operator procedure, including historical data-backfill review.

## Trust boundary and workflow

1. A separately operated CLI worker publishes a locked M1 plan and a 60-second
   heartbeat. The snapshot has a random token and expires after 15 minutes.
2. An explicitly allowlisted, active admin reviews the application version,
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
   `M1::applyApproved`. Evidence, its lease, database identity, account status and
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
operator checks, INSERT/UPDATE on the existing central history table, and **only**
the SQL rights needed by the reviewed catalog. Current shipping migration 001
needs CREATE/ALTER/INDEX/REFERENCES; no generic SUPER privilege is necessary on
MariaDB 10.11 when a DBA grants `READ_ONLY ADMIN` for the maintenance bypass.
Review actual grants with the DBA, especially for future DML/DROP migrations.
The ordinary web DB account must have no DDL, M1-history mutation or read-only
bypass authority. Never copy worker credentials into application configuration,
URLs, environment variables visible through phpinfo, inbox files or public results.

Configure the existing protected `configs/database.ini` environment section:

```ini
systemUpdates.operatorUserIds[] = "REVIEWED_PERSISTED_ACCOUNT_ID"
systemUpdates.queuePath = "/var/lib/dewawi-updates"
```

Replace the account placeholder with a numeric active admin ID. Independently
list the same reviewed numeric IDs as integers in private `worker.json`'s
`operator_ids`. Worker allowlist/account validation is additional to the existing
web authorization. Changes/revocation must be reflected in both protected lists.
Tenant permissions, sharing and SaaS entitlements never grant operator authority.
Protect operator accounts from ordinary delegated tenant account management.

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
only request ID, operator ID, plan digest, timestamp and sanitized status/reason.
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
See the concrete [M2B test report](../../tests/admin/update-worker.md).
