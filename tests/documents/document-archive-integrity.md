# Original document archive integrity

The checker inspects finalized sales and purchase documents, their canonical PDFs, existing invoice XML files and archive registrations. It never renders a PDF, registers an original, updates business data or changes stored files. It creates one new private JSONL report and prints aggregate counts to standard output.

## Run the check

Use a database account with SELECT permission only. Keep its INI configuration readable only by the operator, using the existing `resources.db.params.*` format. Select an active application user who can switch to the requested tenant and has view permission for every selected document type. A user with narrower permissions can select one authorized type with `--type`.

Create a report directory outside the installation and repository with mode 0700. Each report must have a new filename; existing files and symlinks are rejected. Reports use mode 0600 and include internal document IDs, archive IDs, canonical paths, hashes and issue codes. Restrict their retention and access accordingly.

```sh
php tools/document-archive-integrity.php \
  --config=/secure/dewawi-reader.ini \
  --base=/var/www/dewawi \
  --client=100 --operator-user=42 \
  --required-since='2026-11-18 12:00:00' \
  --report=/private/dewawi-reports/archive-unique.jsonl
```

Replace the tenant, operator, report location and example cutover timestamp before use. `--base` is the installation whose files correspond to the selected database. The example timestamp is not an asserted deployment date: set `--required-since` to the actual time reliable original archiving became mandatory, using the same local time convention as stored completion timestamps.

The expectation classification uses stored completion and creation timestamps, never the visible document date. Completion or creation on or after the cutover produces `current_expectation`; a known earlier completion produces `historical`. Missing or invalid completion timestamps produce `undated_or_unknown` unless creation proves the document is current. Without a cutover, entries use `unknown_expectation`. These classifications describe which expectation to investigate; they do not establish dispatch or original provenance.

Each document check and each registration check is counted separately. `documents` and `registrations` count the two inventories; `checked` and issue totals can include both checks for the same damaged original. XML is checked when an invoice has an XML registration or a canonical XML file. An invoice without XML does not require XML generation.

## Bounded scans

The default limit is 1000 rows per document table and 1000 registrations; the maximum is 10000. The checker returns continuation IDs and `complete=false` when a limit is reached. For large tenants, run each type separately and continue using its `next_document_ids` value and `next_archive_id`:

```sh
php tools/document-archive-integrity.php \
  --config=/secure/dewawi-reader.ini --base=/var/www/dewawi \
  --client=100 --operator-user=42 --type=sales/invoice --limit=1000 \
  --after-id=5000 --after-archive-id=8500 \
  --required-since='2026-11-18 12:00:00' \
  --report=/private/dewawi-reports/invoice-next-unique.jsonl
```

Document IDs and archive IDs are separate cursors. When scanning all types, each document table has its own cursor; use one type per continuation command rather than applying the largest ID across unrelated tables. A partial scan is not a clean full-tenant result. Records created while batches run require a later complete pass. File reads are observations during the database snapshot, not a filesystem snapshot.

## Exit codes and optional daily scheduling

| Exit | Meaning |
| --- | --- |
| 0 | Selected inventories completed without detected issues |
| 1 | Issues found; inspect `complete` because the scan may also be partial |
| 2 | Invalid arguments, denied authorization, database/report failure or incomplete execution |
| 3 | No issue found in the scanned batch, but more rows remain |

For an optional daily run, place a wrapper outside the webroot. Use a fresh timestamped report name, retain the JSON summary and alert on every nonzero exit. For example:

```sh
#!/bin/sh
umask 077
stamp=$(date -u +%Y%m%dT%H%M%SZ)
php /var/www/dewawi/tools/document-archive-integrity.php \
  --config=/secure/dewawi-reader.ini --base=/var/www/dewawi \
  --client=100 --operator-user=42 --limit=10000 \
  --required-since='2026-11-18 12:00:00' \
  --report="/private/dewawi-reports/archive-$stamp-$$.jsonl" \
  >"/private/dewawi-reports/summary-$stamp-$$.json" \
  2>"/private/dewawi-reports/error-$stamp-$$.log"
result=$?
if [ "$result" -ne 0 ]; then
  logger -p user.err -t dewawi-archive "Integrity check exit $result; inspect private report $stamp-$$"
fi
exit "$result"
```

The operator can optionally install `15 3 * * * /private/bin/dewawi-archive-check.sh`. Configure the existing monitoring system to alert on the log entry or job exit. For larger inventories the wrapper must finish all batches and types; increasing the limit alone does not guarantee a complete scan. No cron entry or external alert is installed by this change.

## Failure handling and compatibility

Normal finalization locks the document and number counter, renders the PDF, publishes it without replacing an occupied path, verifies and registers it, then commits. Shop offers use their existing inquiry/document locks and the same canonical sales quote archive. XRechnung storage locks the invoice and commits XML publication and registration together; a retry returns the registered XML rather than replacing it.

A confirmed rollback removes only an original identified by the publishing attempt's device, inode and SHA-256. An uncertain commit retains the file and reports failure. After a process interruption or uncertain commit, inspect database state, the private report and application logs before any separately authorized recovery. An occupied unregistered path is deliberately not adopted or overwritten by a retry. Physical storage failures and privileged filesystem changes still require backups and operational recovery.

Archive reads and attachment selection reject ambiguous versions. Historical originals and approvals are preserved. K1 viewing copies and diagnostics remain private, marked and outside the original archive; viewing never creates a registration. Draft PDF markings remain in place. The checker reports defects without attempting historical recovery or document correction.

The process finalize service was inspected: the current process controller uses the generic entity controller, and no live caller of that service was found. It is not one of the eight archived fiscal document types and was not changed.

## Regression commands

Run `python3 tests/documents/document-archive-integrity-db.py` for disposable database tests covering all eight real PDF finalization paths, failure/rollback/retry, concurrent PDF/XML requests, uncertain commits and a SELECT-only integrity scan. Existing PDF, archive, lifecycle, recovery, shop, attachment, XRechnung and GoBD suites remain applicable. Do not run these against an existing database.
