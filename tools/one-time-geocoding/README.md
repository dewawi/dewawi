# One-time Geoapify export and controlled import

This directory is a removable CLI tool, not a DEWAWI integration. Nothing installs a cron job, changes contact workflows or touches K2B/K2C state. All processing is restricted to contact owner 100. Requires the existing DEWAWI PHP environment plus PHP 8.1+, curl, intl and mysqli. Use a single machine and a local filesystem with reliable `flock`, atomic rename and `fsync` support.

Reserve sufficient CLI memory for the frozen inventory and result validation; use `php -d memory_limit=1024M` for the full live inventory when the server has sufficient available memory. This affects only that command, not the web application, and does not preallocate the limit. The synthetic full-size city regression with one candidate per address runs within 512M (480 MiB measured); multiple saved candidates per address need additional headroom. Actual live result size was not measured.

## Provider contract checked on 2026-11-18

- The [official Batch OpenAPI](https://apidocs.geoapify.com/assets/openapi/specs/batch/batch-api-openapi-specs.json) documents `https://api-eu.geoapify.com/v1`, server-side `x-api-key` authentication and at most 1,000 inputs per job. This tool fixes the endpoint to EU, uses `/v1/geocode/search`, priority 0.5 and requests three candidates. Provider polling URLs are never followed or saved. Contact/address IDs are never sent.
- [Batch documentation](https://apidocs.geoapify.com/docs/batch/) describes asynchronous POST/GET processing, ordered result items and 24-hour result availability after completion. Download jobs promptly. The tool uses echoed postal parameters when present; otherwise it uses the documented input order with exact job-ID and cardinality checks.
- The [Free plan](https://www.geoapify.com/pricing/) currently includes 3,000 credits/day. [Pricing details](https://www.geoapify.com/pricing-details/) describe 0.5 credits per geocoding input at low priority, plus one credit for job creation and each polling request. Reservations round upward and include the first GET. The sample and `run` mode default to 1,000 inputs/job; existing explicit smaller batches remain respected (`geocode` alone retains its legacy fallback of 500). Limits remain 2,800 credits and 5,000 inputs per rolling 24 hours, one HTTP operation/minute, polling after 300 seconds. A later GET is charged again if its reservation is older than 24 hours. Other users of the same account consume credits too: use a dedicated key, leave headroom, verify the dashboard and use the SAME quota directory for every workspace for that key. Local reservations are conservative estimates, not provider billing records.
- Free usage requires Geoapify and data-source attribution near the map/information. The contact map now shows a Geoapify link beside the approximate-location notice and retains OpenStreetMap tile attribution. Before import, verify required attribution for the actual stored provider `datasource` records and arrange any additional source notices or obtain a written alternative; record this in the import permit. Relevant [terms](https://www.geoapify.com/terms-and-conditions/) and [geocoding confidence documentation](https://apidocs.geoapify.com/docs/geocoding/) must be reviewed by the operator. An EU endpoint alone does not settle privacy clearance, DPA, retention or transfer questions.

For up to 27,864 unique inputs, the default input cap requires at least six budget windows (roughly five to six days under favorable provider timing, not one immediate run). Duplicates reduce this; processing time, polling, failures and account usage can extend it. No live API usage was tested during implementation.

## Prepare private files on the execution server

The following are operator examples, not commands run by Codex. Keep API keys out of command lines, source control and public logs. Do not copy production configuration to development. The preparation commands are for NEW workspaces only: for existing `pilot-02`/`run-01`, reuse their files and shared quota directory. Do not reinstall configuration over existing keys/releases or re-export the inventory.

```bash
cd /var/www/dewawi
install -d -m 700 /root/dewawi-geocoding /root/dewawi-geocoding/run-01 /root/dewawi-geocoding/quota
install -m 600 tools/one-time-geocoding/provider.sample.ini /root/dewawi-geocoding/provider.ini
install -m 600 tools/one-time-geocoding/import.sample.ini /root/dewawi-geocoding/import.ini
php tools/one-time-geocoding/cli.php --help
```

The known live SELECT-only configuration is `/root/dewawi-diagnostic/readonly.ini`, section `diagnostic`, database `cloud`, DEWAWI user 100. Its file must be 0600 and its parent directory private (0700). No database configuration is auto-discovered. Export/plan explicitly run in read-only repeatable-read transactions, even with broader SQL credentials. Authentication checks the active DEWAWI user, switchable client, actual contact owner and view permission. Import also requires contact edit permission.

## 1. Export and inspect counts

```bash
php tools/one-time-geocoding/cli.php --client=100 --work=/root/dewawi-geocoding/run-01 \
  --mode=export --config=/root/dewawi-diagnostic/readonly.ini --environment=diagnostic --user=100
php tools/one-time-geocoding/cli.php --client=100 --work=/root/dewawi-geocoding/run-01 --mode=status
```

Record the returned `export_sha256`. Export reuses the map's first active billing address, `ordering ASC, id ASC`, and selects contacts `created DESC, id DESC` in bounded keyset pages. Valid existing coordinates are skipped regardless of timestamps. The existing DEEC address assessment excludes incomplete/bad addresses. Local deduplication normalizes case, NFC, whitespace and the narrow K2C.2 number formats; punctuation and meaningful tokens remain significant.

`export.json` stores postal fields, address/contact IDs, the complete relevant original address/coordinate snapshot and contact creation date. Its digest and the query mapping are immutable. Normal console output contains aggregate counts and hashes only; the explicit `entries` mode below also lists private identifiers. Default export cap is 50,000 selected candidates; `remaining_older` explicitly reports any truncation. `--limit=100` can create a separately reviewed small pilot, but it is a subset, not a continuation of an existing export. Never overwrite an export or remove quota state to start over.

### Defer individual unsent pilot entries

Use the EXISTING pilot workspace, not a new export. The path below is an example: replace it with the actual pilot directory. `entry` is the immutable **one-based export position**, not a contact ID, address ID or provider job index. First inspect the mapping and copy its `export_sha256`:

```bash
cd /var/www/dewawi
geo_cmd=(php -d memory_limit=512M tools/one-time-geocoding/cli.php --client=100 --work=/root/dewawi-geocoding/run-01)
"${geo_cmd[@]}" --mode=entries
"${geo_cmd[@]}" --mode=status
```

`entries` lists entry/contact/address IDs, query keys, duplicate counts and status without postal text. Review the postal details privately in the frozen export using those IDs. Keep this output private as well. Entries 7 and 8 below are EXAMPLES, not a recommendation about the live pilot. Replace them with the reviewed positions and `EXPORT_SHA256` with the exact digest:

```bash
"${geo_cmd[@]}" --mode=defer --entry=7 --execute=EXPORT_SHA256 \
  --operator=remzi --reason=postal_review --reference=GEO-PILOT-007
"${geo_cmd[@]}" --mode=defer --entry=8 --execute=EXPORT_SHA256 \
  --operator=remzi --reason=incomplete_address --reference=GEO-PILOT-008
"${geo_cmd[@]}" --mode=status
```

Allowed reasons: `postal_review`, `incomplete_address`, `privacy_review`, `other_review`. Use an operator label and a private ticket/reference code, never postal details or free-form customer information. The label is self-declared audit metadata, not ERP authentication; offline operations require OS access to the private workspace. Neither a provider configuration nor a database configuration is accepted by `defer`.

If `duplicate_count` exceeds one, the same postal query belongs to multiple entries. An individual deferral would still transmit that address through its duplicate. The command therefore refuses it unless the operator explicitly confirms deferring the **whole shared query group** after reviewing all affected entries:

```bash
"${geo_cmd[@]}" --mode=defer --entry=7 --execute=EXPORT_SHA256 \
  --operator=remzi --reason=postal_review --reference=GEO-PILOT-007 --include-duplicates=true
```

The response reports `affected_addresses`. Every affected entry then has status `deferred`. Repeating a deferral returns `already_deferred` without changing the original audit entry. Any query already assigned to ANY job is refused, including completed, pending, uncertain, expired or abandoned jobs. These operations cannot retract a submitted address and never modify jobs or their saved results.

The only new persistent file is `deferrals.jsonl` (0600): a durable, append-only, export-bound hash chain with entry/contact/address/query mapping, timestamp, operator, reason, reference and explicit duplicate confirmation. The existing workspace lock serializes deferral, submission and import. A malformed, truncated or inconsistent journal blocks processing before submission. Keep the journal with the workspace backup; do not edit, truncate or delete it. There is no undo/release command: reconsideration requires a separately reviewed workflow. Export snapshots/hashes, previous plans, results, backups, releases and quota accounting are not rewritten.

`status.counts` partitions exported addresses into `processed` (saved result qualifies), `open` (unsent or in progress), `deferred`, and `rejected` (unsuitable result or failed terminal job). `processed` does not mean imported into the database. `queries` gives the same counts after deduplication; `not_submitted_queries` and `in_progress_queries` split open queries. Deferred queries count in neither open counter and will not be submitted. Rejected queries remain rejected rather than being reclassified as deferrals.

After deferrals, create a NEW read-only import plan using a new filename (see step 3). Plans count `deferred` separately, omit these entries from updates and exclude them from `pending_queries`. The new plan binds the deferral journal as well as the provider state. Old plans/approvals stay untouched; they cannot authorize an import against a changed deferral policy. With no deferrals, existing completed plans and approvals retain their original result-state hash.

## 2. Separately release and perform geocoding

After privacy/provider clearance, privately edit `provider.ini`: set the API key, approval references and `enabled = true`. Keep the quota directory shared and private. Replace `EXPORT_SHA256` below with the exact export hash. Each invocation performs at most one POST or GET and then exits; manually repeat after the indicated wait. No scheduler is installed.

```bash
php tools/one-time-geocoding/cli.php --client=100 --work=/root/dewawi-geocoding/run-01 \
  --mode=geocode --provider-config=/root/dewawi-geocoding/provider.ini --execute=EXPORT_SHA256
```

`waiting` and `quota_wait` perform no HTTP call. Jobs, conservative credit reservations and input limits are persisted before network I/O. The first reserved GET can run even when there is no room for another POST. 429/GET transport failures retain the provider ID and apply at least an hour's backoff plus Retry-After. A 404 marks an expired job rejected and lets later batches proceed. Per-item errors become rejected matches. Results are saved locally and deduplicated addresses share them. No automatic re-geocoding of rejected/expired records occurs.

An interrupted/failed POST can have an unknown outcome. It is never automatically submitted twice. Obtain the actual ID from the provider/operations record and reconnect it, or explicitly abandon its inputs after investigation. Abandoning preserves reserved credits and does not resubmit the addresses:

```bash
php tools/one-time-geocoding/cli.php --client=100 --work=/root/dewawi-geocoding/run-01 \
  --mode=recover-job --job=0 --provider-job=PROVIDER_JOB_ID --execute=EXPORT_SHA256
php tools/one-time-geocoding/cli.php --client=100 --work=/root/dewawi-geocoding/run-01 \
  --mode=abandon-job --job=0 --execute=EXPORT_SHA256
```

Both recovery commands are local only. Use the actual uncertain job index. Status, geocoding and recovery open no database. An import never makes an HTTP request. The provider's response IDs and results stay in private files; raw network errors and authentication URLs are not logged.

## 3. Validate and create an import dry-run

```bash
php tools/one-time-geocoding/cli.php --client=100 --work=/root/dewawi-geocoding/run-01 \
  --mode=plan --config=/root/dewawi-diagnostic/readonly.ini --environment=diagnostic --user=100 --plan=plan-01.json
```

Review `planned`, `conflicts`, `rejected`, `pending`, `deferred`, `pending_queries`, rejection reasons and `plan_sha256`. Each plan is a new immutable private file. To refresh after changes use another name, e.g. `plan-02.json`. Deferred entries are neither pending API queries nor proposed database updates.

Every returned candidate is checked independently. Automatic eligibility requires exactly one qualifying building result: full/building match type, confidence >=0.95 for the address, city, street AND building, complete matching country/postcode/city/street/house number, valid finite nonzero coordinates and matching Point geometry. Optional bounding boxes must be plausible and contain the point. Additional amenity/POI results do not compete with a qualifying building. Multiple qualifying buildings remain ambiguous, including duplicate building entries; no result-order tie-break is used. Missing confidence/component fields, abbreviation differences, alternative locality spellings and coarse positions cannot qualify. A single rejected candidate retains its specific rejection reason; multiple candidates without a qualifying building report `no_suitable_building`. Technical export eligibility does not guarantee an importable result.

Saved provider results can be reassessed with `--mode=plan --plan=plan-02.json` using the existing workspace and SELECT-only configuration. This makes no API call and does not alter the export, previous plans, backups or approvals. A newly accepted result belongs to a new plan hash and requires separate review/release before any import.

### Explicit city accuracy for map orientation

The default remains `--accuracy=strict`. `--accuracy=city` is available only for `plan`, `status`, `entries` and `run`; it never changes queries, frozen postal fields, export hashes, provider jobs or saved results. `import` derives the policy solely from the released plan and rejects a command-line accuracy override.

City policy `city-postcode-v1` checks ALL returned candidates (at most the requested three), rather than blindly taking the first:

- Valid finite Point coordinates must agree with `lat`/`lon`; zero/zero is rejected. Optional bounding boxes must contain the point and span at most two degrees per axis for city assessment. This is a safety bound, not a claim that a bbox proves a municipal boundary.
- Country, exact normalized city name and complete normalized postcode must match the frozen export for every candidate. No postcode prefix matching, alternative locality guessing or inferred region is used. Postcode whitespace may differ. Missing postcodes still require manual review; even a nearby/different postcode is not automatically accepted.
- `confidence_city_level` must be numeric, finite and >=0.95 (<=1). Overall/street/building confidence may be lower in the city fallback. Only known city-or-finer result/match types qualify; country/state/county-only matches do not. `match_by_city_or_disrict` follows the provider's documented spelling.
- Supplied state, state code and county fields must have no contradictions across candidates. New downloads retain these fields privately. Old saved results did not retain region fields, so offline reassessment uses the exact postcode as the additional identity check and cannot recover missing region evidence. No extra API call is made. A previously downloaded legacy file can resume without rewriting its digest when only newly retained region fields are added by the next download; all original retained data must still match exactly.
- Every candidate pair must be within 10 km. This conservative bound rejects distant namesakes and may also exclude large cities or widely separated valid locations. Inconsistent country, city, postcode, region, invalid/missing identity/geometry or insufficient city confidence causes rejection of the response, even beside an exact building. No candidate is silently dropped to manufacture agreement.
- A unique strict building is preferred after those checks. Otherwise a returned city position is preferred; if absent, use the medoid (most central actual returned point), with stable coordinate tie-breaking. Never average a new point or use provider order. Multiple nearby buildings may support city accuracy, but do not become a uniquely identified building.

The fallback relies on the provider's reliable locality assignment plus matching postcode and candidate consistency; no independent municipal polygon/country boundary lookup is performed. It does not verify street/house positions. There is no expected state in the existing frozen four-field export, so conflicting supplied regions can be rejected but an absent expected region cannot be independently established. Abbreviated or alternative city names remain excluded.

City plans use version 2 and bind `accuracy_mode`, `validation_policy`, counts per accuracy, and each update's `accuracy`, `decision` and city evidence into the plan digest. Import revalidates those decisions against the sealed results. Intent and outcome journal records include the same decision; resume rejects altered decision metadata. Strict version-1 plans retain their existing structure/result-state hash and existing files are never rewritten. Newly qualifying city points always require a NEW plan and a separate plan-specific permit.

The map labels all positions as approximate and warns that locations may identify only a city. A generic orientation notice avoids implying building accuracy without adding an address schema field or coupling the map to a removable tool's private journal. Individual accuracy remains auditable in the private plan/journal, not in the application database. No SQL migration is needed.

The original address ID, contact/tenant association, active billing selection, postal content, ordering, timestamps and previous coordinates must still match. No automatic postal corrections, invented numbers, privacy inference or manual override of rejected matches is implemented. City mode deliberately permits street/house differences only after the identity checks above; it does not edit those address fields.

## 4. Separately release the production import

Do not enable this during preparation. First complete/reject or explicitly defer all queries, inspect the immutable plan, take and verify a current database backup using your approved operational procedure, settle attribution and obtain separate import approval.

Prepare a private writer configuration, e.g. `/root/dewawi-geocoding/writer.ini`, section `diagnostic`. It must use the identical host/port/socket/database parameters as the export; only credentials may differ. Grant SELECT plus UPDATE of `address.latitude`, `address.longitude`, `address.geoupdated`, without broader update/DDL rights. Do not run migrations. Set `import.ini` to `enabled = true`, the exact reviewed `plan_sha256`, the approval reference, tested backup reference and attribution reference.

Only after that separate release:

```bash
php tools/one-time-geocoding/cli.php --client=100 --work=/root/dewawi-geocoding/run-01 \
  --mode=import --config=/root/dewawi-geocoding/writer.ini --environment=diagnostic --user=100 \
  --plan=plan-01.json --permit=/root/dewawi-geocoding/import.ini --execute=PLAN_SHA256 --limit=100
```

Repeat the same command until `status=complete` and `remaining=0`; at most 100 records are handled per invocation (configurable up to 1,000). A call with zero SQL updates can still have conflicts and remaining work. Before the first write the tool fsyncs `backup-PLAN_SHA256.json`, containing the original snapshots and proposed coordinates. Each write has a durable intent record, its own transaction and a parameterized, byte-exact compare-and-swap UPDATE including the current map billing selection. Concurrent changes become conflicts; only the three coordinate fields are updated. Accepted points are revalidated against the sealed provider results during import. `import-PLAN_SHA256.jsonl` records private before/after intent metadata and outcomes with operator/time. Recovery recognizes a commit that happened before the outcome could be logged. Partial/corrupt journal lines stop processing instead of guessing. Completed records are not written again. New files and renames also sync their parent directory before database writes.

Keep the full database backup, immutable exports/plans, local before-images, provider source/licensing metadata and journal together under your retention policy. Local files supplement a tested database backup; they do not replace one. Any restoration must be reviewed separately and use current-state guards so subsequent business changes are preserved. No automatic rollback command is included.

## Existing pilot and inventory: operator handover

Use the actual existing private directories; the paths below assume `/root/dewawi-geocoding/pilot-02` and `/root/dewawi-geocoding/run-01`. Keep old files/approvals and choose unused plan/permit names. These commands were NOT run against live data by Codex.

### 1. Reassess pilot-02 without API calls

```bash
cd /var/www/dewawi
geo=(/usr/bin/php -d memory_limit=1024M /var/www/dewawi/tools/one-time-geocoding/cli.php --client=100)
"${geo[@]}" --work=/root/dewawi-geocoding/pilot-02 --mode=status --accuracy=city
"${geo[@]}" --work=/root/dewawi-geocoding/pilot-02 --mode=plan --accuracy=city \
  --config=/root/dewawi-diagnostic/readonly.ini --environment=diagnostic --user=100 \
  --plan=plan-city-01.json
```

The first command is entirely offline; the second uses only the SELECT-only connection. Inspect aggregate rejections, `accuracy_counts`, conflicts (including already imported points), deferrals, `pending_queries` and the private plan's per-update decisions. Actual pilot acceptance cannot be predicted from the reported 9/15/1 counts. Existing accepted/imported points and the deferral remain protected.

### 2. Activate automatic run-01 submission ONLY after clearance

First inspect the frozen inventory and copy its exact `export_sha256`:

```bash
"${geo[@]}" --work=/root/dewawi-geocoding/run-01 --mode=status --accuracy=city
```

Privately edit the EXISTING provider configuration: retain the key and shared quota directory, set `batch_size = 1000`, `daily_credits = 2800`, `daily_inputs = 5000`, `poll_seconds = 300`, and fill the processing/terms approval references covering this entire export. Enable only after privacy/provider clearance. Do not enable K2B/K2C or change the general DEWAWI cron. The scheduler has no database configuration or import capability.

After clearance, a single tick can be invoked manually:

```bash
"${geo[@]}" --work=/root/dewawi-geocoding/run-01 --mode=run --accuracy=city \
  --provider-config=/root/dewawi-geocoding/provider.ini --execute=RUN_EXPORT_SHA256
```

Each tick performs at most ONE POST/GET and exits. Defaults to 1,000 inputs if omitted; explicit smaller sizes are respected. Existing jobs, deferrals, deduplication, local results, locks and global key budgets are reused. `run.json` is new private, sealed aggregate progress, not provider state or an import permit; losing it does not lose job resumption. It does not enter the export/results/plan hash. `status --accuracy=city` includes its last automation state; counts always use the explicitly selected policy. Progress includes accepted, rejected and deferred entries; 100% is not a guarantee of acceptance/import.

`waiting`/`quota_wait` perform no HTTP request. The latter reports the earliest rolling-budget release in `next_at`/`next_at_utc`. If one configured batch cannot fit even an empty daily budget, `reason=batch_exceeds_daily_budget` and a null retry time require adjusting the configuration; no budget is bypassed. GET failures preserve IDs/backoff. `unknown`/`submission_unknown` requires the manual recovery/abandon procedure in step 2 above, with NO automatic resubmission. Terminal failed jobs do not block later inputs. A workspace lock blocks simultaneous scheduler/manual/import work; all workspaces for the key must share the quota lock. Use one execution host, not copied workspaces on multiple servers.

For unattended operation, prepare a private log and add ONE line using `crontab -e` (do not replace the existing crontab). Replace `RUN_EXPORT_SHA256` first:

```bash
umask 077
touch /root/dewawi-geocoding/run-01.log
chmod 600 /root/dewawi-geocoding/run-01.log
crontab -e
```

```cron
* * * * * /usr/bin/php -d memory_limit=1024M /var/www/dewawi/tools/one-time-geocoding/cli.php --client=100 --work=/root/dewawi-geocoding/run-01 --mode=run --accuracy=city --provider-config=/root/dewawi-geocoding/provider.ini --execute=RUN_EXPORT_SHA256 >> /root/dewawi-geocoding/run-01.log 2>&1
```

Output contains aggregate counts/state only, never API keys/postal text. Review logs/status and provider dashboard regularly. `complete` is safely repeatable without new requests; remove this temporary cron line at completion or to pause, and before import review. No cron entry is created by the tool.

### 3. Create and separately approve a new inventory import plan

After `complete`, stop the temporary scheduler and run:

```bash
"${geo[@]}" --work=/root/dewawi-geocoding/run-01 --mode=plan --accuracy=city \
  --config=/root/dewawi-diagnostic/readonly.ini --environment=diagnostic --user=100 \
  --plan=plan-city-01.json
```

Require `pending_queries = 0`, review private update decisions and all aggregate counts, and preserve the exact `plan_sha256`. Rejected/conflicting/deferred entries are not proposed updates. Take and TEST a full database backup through the approved operational procedure. Local before-images are not a substitute. Verify Geoapify and actual data-source notices on the deployed map, including small screens, and settle any additional source-license conditions.

Create a NEW private permit (do not overwrite an existing release):

```bash
install -m 600 /var/www/dewawi/tools/one-time-geocoding/import.sample.ini /root/dewawi-geocoding/import-run-01-city.ini
```

Only after separate import approval, edit `[import]`: `enabled = true`, `plan_sha256 = "PLAN_SHA256"`, and the nonempty `approval_reference`, `backup_reference`, `attribution_reference`. Keep 0600/private parent. A pilot import needs its own permit with the pilot plan's hash, not this inventory release. The writer configuration must target the exact original database parameters and retain the restricted column grants described in step 4.

### 4. Controlled import, local backup and audit

```bash
"${geo[@]}" --work=/root/dewawi-geocoding/run-01 --mode=import \
  --config=/root/dewawi-geocoding/writer.ini --environment=diagnostic --user=100 \
  --plan=plan-city-01.json --permit=/root/dewawi-geocoding/import-run-01-city.ini \
  --execute=PLAN_SHA256 --limit=100
```

Inspect the first outcome, immutable `backup-PLAN_SHA256.json` and decision-bearing `import-PLAN_SHA256.jsonl`. Repeat this exact released command to resume, up to 100 entries per invocation, until `remaining=0`. The command cannot make provider calls. It only updates `latitude`, `longitude`, `geoupdated` if the entire original tenant/address/contact/coordinate snapshot and map billing selection still match; existing valid coordinates remain intact. For an approved pilot import, use `--work=.../pilot-02`, its new city plan hash and its separately prepared pilot permit instead. Do not reuse or modify an old strict plan/permit.

## Tests and removal

```bash
python3 tests/contacts/external-geocoding.py
python3 tests/contacts/geocoding.py
python3 tests/contacts/geocoding-diagnostic.py
```

These runners create disposable databases and use synthetic addresses and mocked HTTP. They must not be pointed at production. After operational completion and the required retention period, this tool directory and its dedicated tests can be removed without application changes. Removing the tool does not remove stored coordinates or ongoing attribution/license obligations. Do not delete private state while work or import reconciliation remains unfinished.
