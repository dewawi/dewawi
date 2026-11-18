# One-time Geoapify export and controlled import

This directory is a removable CLI tool, not a DEWAWI integration. Nothing installs a cron job, changes contact workflows or touches K2B/K2C state. All processing is restricted to contact owner 100. Requires the existing DEWAWI PHP environment plus PHP 8.1+, curl, intl and mysqli. Use a single machine and a local filesystem with reliable `flock`, atomic rename and `fsync` support.

Reserve sufficient CLI memory for the frozen inventory and result validation; use `php -d memory_limit=512M` in place of `php` below for the full inventory. This affects only that command, not the web application. The synthetic full-size regression runs with this bound.

## Provider contract checked on 2026-11-18

- The [official Batch OpenAPI](https://apidocs.geoapify.com/assets/openapi/specs/batch/batch-api-openapi-specs.json) documents `https://api-eu.geoapify.com/v1`, server-side `x-api-key` authentication and at most 1,000 inputs per job. This tool fixes the endpoint to EU, uses `/v1/geocode/search`, priority 0.5 and requests three candidates. Provider polling URLs are never followed or saved. Contact/address IDs are never sent.
- [Batch documentation](https://apidocs.geoapify.com/docs/batch/) describes asynchronous POST/GET processing, ordered result items and 24-hour result availability after completion. Download jobs promptly. The tool uses echoed postal parameters when present; otherwise it uses the documented input order with exact job-ID and cardinality checks.
- The [Free plan](https://www.geoapify.com/pricing/) currently includes 3,000 credits/day. [Pricing details](https://www.geoapify.com/pricing-details/) describe 0.5 credits per geocoding input at low priority, plus one credit for job creation and each polling request. Reservations round upward and include the first GET. Defaults: 500 inputs/job, 2,800 credits and 5,000 inputs per rolling 24 hours, one HTTP operation/minute, polling after 300 seconds. A later GET is charged again if its reservation is older than 24 hours. Other users of the same account consume credits too: use a dedicated key, leave headroom, verify the dashboard and use the SAME quota directory for every workspace for that key. Local reservations are conservative estimates, not provider billing records.
- Free usage requires Geoapify and data-source attribution near the map/information. Before import, arrange the required visible attribution or obtain a written alternative from the provider; record this in the import permit. This task does not change the map. Relevant [terms](https://www.geoapify.com/terms-and-conditions/) and [geocoding confidence documentation](https://apidocs.geoapify.com/docs/geocoding/) must be reviewed by the operator. An EU endpoint alone does not settle privacy clearance, DPA, retention or transfer questions.

For 27,863 unique inputs, the default input cap requires at least six budget windows. Duplicates reduce this; processing time, polling, failures and account usage can extend it. No live usage or tariff availability was tested during implementation.

## Prepare private files on the execution server

The following are operator examples, not commands run by Codex. Keep API keys out of command lines, source control and public logs. Do not copy production configuration to development.

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

`export.json` stores postal fields, address/contact IDs, the complete relevant original address/coordinate snapshot and contact creation date. Its digest and the query mapping are immutable. Console output contains aggregate counts and hashes only. Default export cap is 50,000 selected candidates; `remaining_older` explicitly reports any truncation. `--limit=100` can create a separately reviewed small pilot, but it is a subset, not a continuation of an existing export. Never overwrite an export or remove quota state to start over.

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

Review `planned`, `conflicts`, `rejected`, `pending`, `pending_queries`, rejection reasons and `plan_sha256`. Each plan is a new immutable private file. To refresh after changes use another name, e.g. `plan-02.json`.

Every returned candidate is checked independently. Automatic eligibility requires exactly one qualifying building result: full/building match type, confidence >=0.95 for the address, city, street AND building, complete matching country/postcode/city/street/house number, valid finite nonzero coordinates and matching Point geometry. Optional bounding boxes must be plausible and contain the point. Additional amenity/POI results do not compete with a qualifying building. Multiple qualifying buildings remain ambiguous, including duplicate building entries; no result-order tie-break is used. Missing confidence/component fields, abbreviation differences, alternative locality spellings and coarse positions cannot qualify. A single rejected candidate retains its specific rejection reason; multiple candidates without a qualifying building report `no_suitable_building`. Technical export eligibility does not guarantee an importable result.

Saved provider results can be reassessed with `--mode=plan --plan=plan-02.json` using the existing workspace and SELECT-only configuration. This makes no API call and does not alter the export, previous plans, backups or approvals. A newly accepted result belongs to a new plan hash and requires separate review/release before any import.

The original address ID, contact/tenant association, active billing selection, postal content, ordering, timestamps and previous coordinates must still match. No fuzzy matching, automatic corrections, invented numbers, privacy inference or manual override of rejected matches is implemented.

## 4. Separately release the production import

Do not enable this during preparation. First complete/reject all queries, inspect the immutable plan, take and verify a current database backup using your approved operational procedure, settle attribution and obtain separate import approval.

Prepare a private writer configuration, e.g. `/root/dewawi-geocoding/writer.ini`, section `diagnostic`. It must use the identical host/port/socket/database parameters as the export; only credentials may differ. Grant SELECT plus UPDATE of `address.latitude`, `address.longitude`, `address.geoupdated`, without broader update/DDL rights. Do not run migrations. Set `import.ini` to `enabled = true`, the exact reviewed `plan_sha256`, the approval reference, tested backup reference and attribution reference.

Only after that separate release:

```bash
php tools/one-time-geocoding/cli.php --client=100 --work=/root/dewawi-geocoding/run-01 \
  --mode=import --config=/root/dewawi-geocoding/writer.ini --environment=diagnostic --user=100 \
  --plan=plan-01.json --permit=/root/dewawi-geocoding/import.ini --execute=PLAN_SHA256 --limit=100
```

Repeat the same command until `status=complete` and `remaining=0`; at most 100 records are handled per invocation (configurable up to 1,000). A call with zero SQL updates can still have conflicts and remaining work. Before the first write the tool fsyncs `backup-PLAN_SHA256.json`, containing the original snapshots and proposed coordinates. Each write has a durable intent record, its own transaction and a parameterized, byte-exact compare-and-swap UPDATE including the current map billing selection. Concurrent changes become conflicts; only the three coordinate fields are updated. Accepted points are revalidated against the sealed provider results during import. `import-PLAN_SHA256.jsonl` records private before/after intent metadata and outcomes with operator/time. Recovery recognizes a commit that happened before the outcome could be logged. Partial/corrupt journal lines stop processing instead of guessing. Completed records are not written again. New files and renames also sync their parent directory before database writes.

Keep the full database backup, immutable exports/plans, local before-images, provider source/licensing metadata and journal together under your retention policy. Local files supplement a tested database backup; they do not replace one. Any restoration must be reviewed separately and use current-state guards so subsequent business changes are preserved. No automatic rollback command is included.

## Tests and removal

```bash
python3 tests/contacts/external-geocoding.py
python3 tests/contacts/geocoding.py
python3 tests/contacts/geocoding-diagnostic.py
```

These runners create disposable databases and use synthetic addresses and mocked HTTP. They must not be pointed at production. After operational completion and the required retention period, this tool directory and its dedicated tests can be removed without application changes. Removing the tool does not remove stored coordinates or ongoing attribution/license obligations. Do not delete private state while work or import reconciliation remains unfinished.
