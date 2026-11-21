# Variant A: updates under the existing PHP account (M2E)

This is the regular mode for a single installation on a single hosting server.
It uses the existing PHP OS account (Apache www-data, a PHP-FPM pool account or
another hosting identity) and the existing application DB adapter. No root, new
Linux user, daemon, systemd, global MariaDB read_only privilege, shell command,
SQL upload or extra database credential is required. No privileges are granted.
The application version remains 1.1.8. Frozen baseline and migration SQL are intact.

## Authority and limits

**This mode has no OS or DB isolation between web and migration.** A compromised
web process can read/alter the same files and use the same DDL privileges. Mode
0700/0600, random tokens and immutable audit writes protect against accidental
exposure/reuse; they are not separation from that process. No same-account secret
or second user allowlist is used. Select [mode B](README.md) when separate OS/DB
credentials and an independent root release are required; B remains unchanged.

Only authenticated active undeleted administrators with matching identity can
operate A, including admins of other tenants. This is an installation-wide action.
The controller checks authentication, CSRF and session/tenant binding, rereads the
persisted account and rechecks its password for initialization, maintenance,
approval, HTTP execution and maintenance completion. It matches the existing
password verifier including legacy MD5 login hashes, without rehashing during
maintenance. Passwords are never stored in requests/audit or logged. Each approved
request is tied to its full plan and session; another session cannot execute it
over HTTP. The optional CLI consumer checks the stored approving account again.

All supported application traffic must use one code deployment and one reliable
local filesystem. The existing front controller takes a shared flock **before
bootstrap**, retains it through request/background completion and includes GET,
AJAX, CMS, shop, API, imports and file-manager routes. Cron enters through the same
index.php and holds its lease for the whole script. Technical import, geocoding,
document-operation and migration CLI entry points take that same lease before
database work. Existing requests must drain before the exclusive update lease
can be acquired. No lock is forcibly revoked.

A persistent maintenance record rejects ordinary dynamic traffic with 503,
including reads because legacy GETs can write. Only canonical update routes may
enter; the MVC dispatch guard rejects forwarding to another controller during
maintenance. Static assets remain available. Update actions must separately pass
admin checks. Login itself writes tracking/password data, so sign in **before**
maintenance; lost authentication during maintenance requires operator assistance.
The maintenance record never expires or disappears on a worker/PHP crash.

Custom entry points, remote scripts, other deployments, DB events, replication,
external integrations and human DB sessions do not become fenced by flock.
Inventory and stop them independently, with a verified writer-exclusion report.
If that exclusion cannot be established, do not approve the run. Multi-host/NFS,
load-balanced deployments and uncoordinated writers are unsupported in A and must
use an appropriate separately reviewed operating alternative. The fixed
single-local-host control scope is an operating contract, not topology detection.

## Automatic setup through the UI (M2F)

Open Administration → System → Updates. The page distinguishes available,
initialized, incomplete, unavailable and blocked storage. GET does not create a
directory, locator, identity or migration row. Initialization requires a current
active administrator, CSRF, password reauthentication and explicit confirmation.

An existing explicit `systemUpdates.statePath` takes priority before automatic
selection. Existing M2E directories/control/evidence remain in place; there is no
automatic conversion or move. A valid repeated initialization preserves the
existing control and lock. Damaged/missing locks, identity conflicts, unsafe paths
or unresolved initialization data are not repaired automatically.

There is no general private application data root in the current installation
model. Session/cache/archive directories are inside the repository/web tree and
cannot be reused. The one automatic provider is the **already configured**
`DEWAWI_GOBD_REPORT_DIR`, used by the existing GoBD exporter for private reports.
No HOME, PHP temporary directory, sibling named private or list of guessed paths
is searched. Do not configure a fake GoBD directory just to enable updates.

Automatic setup requires all of the following:

- An existing canonical provider directory, mode 0700, readable/writable by the
  actual PHP account, with safe ancestors and no symlinks. Its name proves nothing.
- Provider and derived child outside the repository and all known public roots.
  Web setup uses the canonical `DOCUMENT_ROOT`; declare other public document roots
  or public alias targets in the private `systemUpdates.documentRoots[]` list.
  Operators must supply the complete deployment boundary; PHP cannot discover
  arbitrary Apache/nginx aliases or unrelated virtual hosts automatically.
- Durable local storage, not `/tmp`, `/var/tmp`, `/run`, `/dev/shm` or PHP's temporary
  directory. Automatic selection reads Linux `/proc/self/mountinfo` and accepts
  only ext2/ext3/ext4, XFS, Btrfs and ZFS. Unknown, network, overlay and memory
  filesystems, or unavailable mount information, cause a controlled refusal.
- The existing canonical configs directory must be writable and on a recognized
  local filesystem. After authorization, private probe files test exclusive flock,
  atomic publication/rename and file/directory fsync in both relevant directories;
  only those probe files are removed. Existing reports are never modified.
- The provider must remain retained storage: exempt the update child from any
  external report cleanup policy. Back up the technical binding and state together.

The fixed child name is `dewawi-updates-` plus the first 32 hex characters of
SHA-256 of the canonical installation root. Two distinct installations get distinct
children. This hash is not an application/database schema version.

Only after confirmation, immutable mode-0600 `configs/update-storage.json` pins
the chosen path, known public roots, installation-root hash and proposed control
identity. This is small technical installation configuration, replacing the manual
path entry; all actual update state, plans, jobs, evidence and history remain in
the private external directory. The technical locator is Git-ignored and protected
by the existing configs HTTP denial, like other private installation configuration.
It contains no DB credentials or SQL and is not a second migration history.

The claim is durable **before** directory creation. Initialization creates the
0700 child and 0600 gate/control with that exact claimed ID/database fingerprint.
`configs/update-storage.ready.json` records completion and binds the locator hash.
Both records are immutable: competing processes cannot replace the winner's path
or identity. A confirmed retry can finish a claim interrupted before completion
only while the state contains no jobs/maintenance/unknown files, retaining its pin.
An already initialized directory is reused without overwriting any state file.

Every normal web entry, pre-bootstrap maintenance guard, technical CLI and optional
hosting consumer uses this same locator resolver. There is no provider search on
ordinary requests. Changed/absent GoBD or HOME environment variables cannot move
an initialized installation; CLI uses the public-root facts saved by web setup.
For initial automatic CLI setup without a web DOCUMENT_ROOT, the public roots must
be configured explicitly. No CLI is required for normal browser setup.

Missing, unreadable, corrupt, linked or conflicting locator/ready/control files,
lost initialized directories and changed database identities block reuse and
reinitialization. Ordinary requests fail closed when the binding is incomplete or
unsafe; authorized update routes remain available to explain the fault. No new
folder is selected as recovery. Do not delete either technical record, change the
private path, copy the locator to another code root or switch modes to bypass a
pending state. Restore configuration and complete private state as one reviewed
set; a DB restore never authorizes resetting file state or migration history.
A restore to the same server/database identity is not intrinsically detectable
from that identity fingerprint. Preserve all file state and review actual restored
M1/schema/data state under the existing recovery procedure; never infer a new
installation or regenerate its identity from a restore.

These checks do not replace hosting/storage acceptance or the existing independent
backup/restore, historical-data and writer-exclusion evidence. Drain requests and
jobs started before first setup as part of that writer review; initialization
itself runs no migration and does not certify a maintenance window.

## Manual fallback and existing M2E configuration

If no approved GoBD provider exists or a technical prerequisite cannot be proven,
the UI refuses automatic setup and explains the one-time hosting measure below.
It never relaxes checks or switches to mode B. Existing explicit M2E paths retain
their original validation/operating contract and do not require a GoBD provider
or a new locator in configs. Their path remains technical installation authority;
do not change it to abandon an existing state. An explicit path conflicting with
an already pinned automatic binding is rejected, not treated as a migration.

1. Use the hosting panel/SFTP to configure the installation's **private**
   `configs/database.ini` in its existing environment section:

   ```ini
   systemUpdates.mode = shared
   systemUpdates.statePath = /private-hosting-home/dewawi-update-state
   ```

   The path is installation configuration, never a browser parameter or user/DB
   setting. It must be outside the repository and **every** served document root,
   canonical without symlinks, on reliable local storage. Its parent must exist,
   be protected against replacement by other accounts and be writable by the PHP
   account for initial creation. The state directory requires 0700, its files
   0600 and the same owner. Reliable flock/link/rename, file/directory fsync and
   PHP 8.1+ with PDO MySQL are required. No POSIX extension is required by A.
   The existing PDO connection must be nonpersistent. A refuses persistent
   connections so M1/HTTP session settings cannot leak into later ERP requests;
   disable persistence in the private DB configuration before using A. The
   optional hosting CLI consumer explicitly opens a nonpersistent connection.
2. While signed in as admin, open Administration → System → Updates and explicitly
   initialize private setup with CSRF and your current password. It creates only
   file state, a stable lock inode and a random installation ID pinned to the live
   database fingerprint. No schema/history writes, service or migration run occur.
   Initialization cannot overwrite control/identity. Missing/unsafe storage blocks
   execution; use the hosting panel to correct rights rather than weakening checks.
3. Ensure the existing DB account has the needed rights. A proves direct database
   or global grants via SHOW GRANTS; baseline needs SELECT/CREATE/INSERT/UPDATE,
   feature execution conservatively also needs ALTER/INDEX/DROP/DELETE/REFERENCES.
   Unresolvable role-based or table-specific grants are blocked, even if an
   individual operation might work. Review them with the hosting operator. The
   application never changes grants. Shared hosting without sufficient DDL rights
   cannot migrate automatically.

An optional same-account CLI initialization uses the same existing private DB
configuration and adds no DB privileges:

```sh
php tools/update-hosting.php --config=/private/path/database.ini --environment=production --command=init
```

Do not copy the private state/control to another installation, restore or database
server. Changed database identity requires separate operator review and new
initialization/state, retaining the original audit. Never overwrite control to
make an old approval valid. Do not switch to B to clear interrupted A state.

## Per-run process and independently verified evidence

1. Sign in, coordinate all writers and explicitly enable maintenance with your
   current password. The exclusive lease must first drain existing application
   requests/cron jobs; a busy lease blocks activation and SQL. The UI displays the
   persistent maintenance state. The private `maintenance.json` supplies its ID
   and UTC `since`, and `control.json` supplies opaque installation/database IDs.
2. After maintenance begins, take a full consistent backup including M1 history
   if it exists. Compute its actual SHA-256. Restore **that artifact** to an isolated
   environment, check integrity/data and record an independent report with its
   verifier/time and actual SHA-256. A readable backup and report hash are verified
   by the application; it cannot itself certify the external restoration or the
   truth of the independent report. A checkbox is never considered evidence.
3. Inventory/stop all writers outside the instrumented installation, including
   external jobs and privileged clients. Record an independently checked report,
   hash, confirmer and time. Keep deployment/code/catalog frozen during the run.
4. For first registration, independently audit historical data/backfills through
   1.1.8 and installation provenance. Matching schema is insufficient. Retain a
   dated report with the actual checks/results, verifier and SHA-256. Never attest
   merely from table existence or a displayed application version.
5. Review the next catalog migration on the actual data volume and hosting limits.
   Record the exact ID/checksum and a conservatively reviewed runtime estimate.
   Registering the baseline is a separate reviewed operation. Install the evidence
   below through the hosting panel/SFTP, not through a browser upload/checkbox.
6. Explicitly **verify prerequisites and exact plan**. The exclusive filesystem
   lease plus M1 database lock protect this check. Baseline verification reads
   the actual frozen schema; missing tables/columns are never assumed present.
   The complete M1 plan/catalog/history and schema fingerprint (baseline),
   maintenance epoch and exact evidence-file hash form the approval plan.
7. Review the selected next ID, checksums and plan hash, confirm the operation and
   re-enter your password. The immutable request consumes the nonce once and
   expires in ten minutes. Concurrent approvals cannot create a second live job.
   Approval does **not** execute SQL.
8. Explicitly execute it with your password, or use the optional same-account CLI
   consumer below. Immediately before SQL, compare the complete live plan again.
   The accepted record and exact evidence snapshot are durable before M1 writes.
   M1 is the sole schema/history writer. A executes exactly the next feature step;
   it cannot silently include a newly deployed migration or execute features as
   part of baseline registration. Inspect the result and M1 status afterwards.
9. A subsequent feature needs a new backup/restore evidence set for that state,
   reviewed execution ID/checksum, new plan and new approval. Archive superseded
   evidence; do not overwrite it during an accepted run. End maintenance explicitly
   with your password only when requests/history are settled. Failed/started or
   ambiguously accepted jobs block this action; no automatic repair/resume exists.

`evidence.json` in the state directory (0600, no links) has this strict top-level
schema; replace identifiers, paths, hashes and UTC Unix timestamps with real values:

```json
{
  "format": 1,
  "maintenance": "32_HEX_CURRENT_MAINTENANCE_ID",
  "installation": "32_HEX_INSTALLATION_ID",
  "database": "64_HEX_PINNED_DATABASE_ID",
  "expires_at": 0,
  "external_writers_stopped": true,
  "writers": {
    "file": "/private/writer-exclusion-report.txt",
    "sha256": "64_HEX_REPORT_HASH",
    "checked_at": 0,
    "confirmed_by": "external-server-operator"
  },
  "backup": {
    "file": "/private/complete-database-backup.sql",
    "sha256": "64_HEX_ACTUAL_BACKUP_HASH",
    "taken_at": 0
  },
  "restore": {
    "file": "/private/independent-restore-report.txt",
    "sha256": "64_HEX_REPORT_HASH",
    "tested_at": 0,
    "confirmed_by": "external-dba"
  },
  "historical": {
    "file": "/private/historical-data-audit.txt",
    "sha256": "64_HEX_REPORT_HASH",
    "checked_at": 0,
    "confirmed_by": "independent-data-reviewer"
  },
  "execution": {
    "migration": "baseline/1.1.8",
    "checksum": "64_HEX_FROZEN_REFERENCE_HASH_OR_SELECTED_SQL_FILE_HASH",
    "http_seconds": 5
  }
}
```

Historical evidence can be null for a feature step. Backup/report artifacts must
be canonical private regular files outside repository/webroots, with no symlinks
or hard links and no group/other permissions. Keep them independently retained.
The backup is at most one hour old and follows maintenance activation; its restore
test follows it. Writer/historical review times are within the current maintenance
period. Evidence must remain unexpired through execution. Prepare and fsync the
new evidence file privately, atomically rename it while no execution is active and
fsync its directory. Changing its bytes invalidates prepared/approved plans.
Never falsify timestamps or hashes to accommodate a long backup/restore window.
Use a separately reviewed operating procedure if these limits cannot be met.

## Runtime, cancellation and the optional same-account consumer

HTTP currently accepts only MariaDB, a reviewed estimate of at most ten seconds
and sufficient remaining PHP execution time (at least 25 seconds if limited).
It uses a 20-second application deadline, session metadata/InnoDB lock timeouts of
three seconds and MariaDB max_statement_time of five seconds. These are bounds
and failure controls, **not a guarantee that every DDL can be cancelled/rolled
back**, nor an upper bound imposed by every proxy/server. Hosting kills can leave
SQL committed or still finishing. Review the actual migration and data size first.
MySQL HTTP execution, long/unreviewed jobs or tighter PHP limits are blocked before
acceptance with an explicit alternative. No global read_only flag is set/required.

On browser disconnection PHP is asked to finish the accepted request. The persistent
acceptance, M1 started row and maintenance fence remain even if PHP is killed by
the hosting service. No automatic retry occurs. Inspect status after reconnecting;
never infer rollback from a lost response or an empty result file.

If CLI or hosting cron is reliably available, the same PHP hosting account can
consume the already approved ID without a daemon or second account:

```sh
php tools/update-hosting.php --config=/private/path/database.ini --environment=production --command=execute --request=32_HEX_APPROVED_ID
```

Arguments are fixed technical operator input, never interpolated browser values.
The consumer uses the existing DB connection settings, both locks, exact plan,
evidence/lease and persisted-account checks. It does not accept free SQL, bypass
failed history, accept expired approvals or initialize the baseline implicitly.
The UI supplies the approved request ID; the operator must execute it within the
approval lease. CLI has a one-hour application deadline, still bounded by the
approval/evidence expiry and external runtime limits. It never resets an accepted
request. Cron is optional; there is no automatic migration on ordinary cron calls.

Without CLI and with unsuitable HTTP limits, automatic execution is unavailable.
The UI cannot guarantee arbitrary hosting compatibility. Arrange a separately
authorized manual operating window or the stricter B worker; do not disable checks.

## Failure and recovery

Private immutable plan/request/accepted/evidence/result files identify the actor,
session binding (hashed), selected migration and outcome. No passwords are stored.
Only M1 history records database migration completion. A normal GET never writes
history or repairs an interrupted job; accepted without completion is displayed as
manual review. SQL errors can leave earlier DDL and M1 failed/started markers.

Keep maintenance active. Preserve all state, deployed catalog, backups/reports and
the real DB state. Stop uncoordinated writers and obtain a separate audited recovery
plan; do not edit migration rows, erase accepted files or repeat SQL. Neither file
permissions nor this web account can supply an independent recovery authority.
Only after actual state recovery/verification can a separately reviewed fresh
state epoch and new approval be considered, retaining the old incident evidence.
Fresh development snapshots containing feature schema without feature history
retain M1's existing provenance limitation; no inferred feature registration exists.

No production accounts, services, grants, migration execution or deployment are
installed by this implementation. Accept real hosting limits, filesystem durability,
all writer paths and independently verified backup/restoration before enabling it.

Concrete results and remaining acceptance limits: [M2E test report](../../tests/admin/update-hosting.md).
Automatic setup results: [M2F test report](../../tests/admin/update-storage.md).
