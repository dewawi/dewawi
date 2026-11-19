# Central document archive completeness audit

`tools/document-archive-completeness.php` audits every non-deleted document of the selected tenant, across all eight types from `DEEC_DocumentArchive::documents()`. `--type=sales/invoice` restricts both the document pass and the filesystem pass. `DEEC_Permission::isDocumentReadonly()` determines issuance/completion, including cancelled documents; this technical status does not prove dispatch. Drafts are counted and exported but require no original.

The tool reuses `relativePath()`, `inspect()` and `registrationIssues()`. The internal table ID determines the physical path: tenant 100, invoice ID 59505 resolves to `files/documents/1/0/100/sales/invoice/59/59505.pdf`, regardless of the visible number or display filename. Inspection includes regular-file/symlink checks, actual process readability, non-empty content, PDF header/trailer, size and SHA-256. This is a structural basic check, not full PDF conformance or historical provenance certification.

Registrations are checked for tenant/module/type/internal ID, PDF format, version 1, canonical path, display filename, size and hash. Related registrations and registrations claiming the canonical path are checked together, including foreign metadata. At most two registrations are loaded: two already prove ambiguity. Valid XML originals are separate records and do not make a PDF ambiguous. No archive paths are constructed from database path values.

If the canonical original is unavailable or its registration is invalid, `DEEC_DocumentReview::inspectViewingCopies()` checks existing protected K4 copies in both supported locales, using the same receipt validator as ordinary viewing. It verifies the current stored-data snapshot, receipt identity/locale, non-original markers, private directories/files, PDF structure, size and SHA-256. Old snapshot copies, missing receipts, missing PDFs, invalid receipts and unreadable PDFs are distinguished. These copies never satisfy `original_complete`. Neither this method nor the audit calls a renderer, copy publication, locks, registration, recovery, HTTP routes or document changes. Ordinary viewing still uses the same validator before reading bytes.

## Production command for tenant 100

Prepare `/var/lib/dewawi/archive-audit/reader.ini` (0600) and `/var/lib/dewawi/archive-audit/reports` (0700), readable/writable respectively by the **actual PHP account**. Reports and credentials must be outside the installation, repository and any other webroot. The reports parent must already exist; the audit creates a new 0700 subdirectory and exclusive 0600 report files. Existing outputs and symlink paths are rejected. Do not use the application's write-enabled database credentials.

The INI uses the existing migration configuration format:

```ini
[production]
resources.db.params.host = 'localhost'
resources.db.params.username = 'dewawi_archive_reader'
resources.db.params.password = 'REPLACE_WITH_READER_PASSWORD'
resources.db.params.dbname = 'dewawi'
resources.db.params.port = '3306'
```

Use a dedicated database account with SELECT-only access to the installation's database. The audit rejects any grants other than SELECT/USAGE, roles and grant options; a read-only session is set before bootstrap queries, with a repeatable-read transaction rolled back at the end. No account or grant is created by the tool. The application operator must be active, able to select tenant 100, and have view permission for every selected document type. Normal authorized parent/child tenant selection remains available; document queries use the selected tenant exclusively.

Set `DEWAWI_AUDIT_OPERATOR` to that existing operator's internal user ID. Assuming the actual PHP account is `www-data`, execute:

```bash
sudo -u www-data php -d memory_limit=256M /var/www/dewawi/tools/document-archive-completeness.php \
  --config=/var/lib/dewawi/archive-audit/reader.ini \
  --base=/var/www/dewawi \
  --client=100 \
  --operator-user="${DEWAWI_AUDIT_OPERATOR:?set the authorized operator user ID}" \
  --web-user=www-data \
  --output="/var/lib/dewawi/archive-audit/reports/client-100-$(date -u +%Y%m%dT%H%M%SZ)"
```

If PHP uses another account, replace both `www-data` occurrences. `--web-user` must match the non-root effective CLI account, so root cannot mask missing file permissions. The CLI does not change identity. Optional `--environment=production`, `--batch-size=250` (1–1000), and `--type=module/controller` are supported. Never run the synthetic tests against this installation's database.

## Reports and counts

- `summary.json`: completion marker, timestamps, effective UID, counts per type and aggregate, reconciliation, filesystem counts and peak memory.
- `documents.csv`: one row per non-deleted document, including drafts, internal ID, visible number, issuance, expected original path, original/registration/K4 status, exclusive group, size, hash and concrete reasons. `copy_details` contains the per-locale checks.
- `problems.csv`: document problems plus separate filesystem findings. The latter may describe the same file as a document problem, so this report's row count is **not** an issued-document total. Spreadsheet formula prefixes in CSV cells are escaped with an apostrophe.

Issued documents receive exactly one group, with this precedence:

| Group | Meaning |
| --- | --- |
| `original_complete` | Canonical PDF and exactly one matching registration pass all checks |
| `damaged_or_unreadable_pdf` | Canonical path contains an invalid/empty/unreadable PDF, symlink or collision; or an absent original has a missing/damaged/unreadable/invalid K4 copy |
| `invalid_archive_metadata` | Registration identity, version, filename, path, size, hash or uniqueness fails; takes precedence over missing-original copy groups |
| `original_without_registration` | Valid canonical PDF without a registration |
| `missing_original_valid_copy` | Original absent, no conflicting metadata, current K4 copy valid |
| `missing_original_copy_not_generated` | Original absent, no conflicting metadata, no generated K4 copy |
| `other_blockers` | Remaining copy access/snapshot/safety blockers |

`groups` always sums to `issued`; `non_deleted` equals `issued + drafts`. Independent fields and reasons retain simultaneous problems even when another group takes precedence. A registration whose file is missing can only be reported `metadata_consistent_file_unavailable`; byte integrity cannot be confirmed.

The filesystem pass streams PDFs beneath the tenant/type roots derived from `relativePath()`. It reports files with no active tenant document (including deleted documents), draft associations, noncanonical paths, and invalid/missing registrations. Symlink directories are never traversed. Unreadable directories and unsafe traversal keep `complete=false`. No files are deleted, moved, rewritten or assigned.

Console output contains only progress counts and generic completion/error status. Exit 0 means complete and clean, 1 means complete with findings, and 2 means incomplete/error. Require `complete=true` in `summary.json`; interrupted/error reports may be partial or lack a summary. Unknown files/directories that cannot be scanned are blockers, not evidence of completeness. Retain and review private reports manually.

Processing uses keyset batches, streamed CSV output and incremental directory traversal; full PDFs are hashed/inspected without loading their bytes. Memory depends on the configured batch and one document's existing snapshot, not on the tenant's complete document list. A long repeatable-read transaction can retain database undo history; run large audits in an appropriate maintenance window. Database reads have a consistent snapshot; the filesystem can change concurrently, so findings describe the observed files and may require a repeat audit during quiescence. No migration or repair is included.

## Synthetic regression

```bash
python3 tests/documents/document-archive-completeness.py
php tests/documents/document-archive.php
php tests/documents/document-archive-migration-config.php
php tests/documents/document-review-test.php
python3 tests/documents/document-recovery-db.py
```

The completeness runner starts a temporary network-disabled MariaDB under `/tmp`, imports the existing fresh schema, uses a SELECT-only audit account and the current non-root test user, and removes its own fixture. It covers all eight types, internal IDs/shards, originals, missing originals/K4 copies, integrity and metadata failures, tenant/view denials, drafts/cancellations/deletions, orphan files, inaccessible files/directories, private report paths and database/filesystem immutability. No production command was executed by these tests.
