# Historical PDF recovery assessment

`tools/document-recovery-assessment.php` analyzes every blocked PDF document in an existing private migration inventory. It produces evidence for operator review: observed file hashes, byte groups, document and email attachment references, and available text differences. Every result requires independent origin confirmation. Matching text, filenames, timestamps, copied hashes, SMTP acceptance and the number of copies never establish the issued original.

The tool writes reports only. It does not copy PDFs, reconstruct documents, register or approve originals, update business data, bind a review directory to the application, or change email attachments and GoBD exports. Recovery and document corrections remain separate tasks.

## Inputs and dependencies

Use the complete JSONL dry run from [document-archive-migration.php](document-archive-migration.php), including its final summary with `mode=dry-run` and `unassigned_scan=complete`. A dry run containing blocked records normally exits 1; its final summary and counts determine whether the inventory is complete. A truncated report, duplicate document, foreign document tenant, inconsistent totals or records after the summary are rejected before output is created. Inventory dates are optional; the existing migration format does not include them in document plans.

Supply the canonical installation base corresponding to the inventory paths and the document owner client ID. Paths are not remapped to another installation. The private inventory must be a regular file with mode 0600, one hard link, no symlinks, and an existing parent directory with mode 0700 outside both installation and repository. Output requires a new directory name under an existing private parent with mode 0700. Existing output is never overwritten.

PHP CLI needs the application's existing Zend and DEEC libraries and JSON/mbstring support. Database correlation additionally uses PDO MySQL and mysqli. Text extraction uses the existing Poppler `pdftotext` executable at `/usr/bin/pdftotext`; override its absolute path with `--pdftotext`. The verified open PDF descriptor is passed through `/proc/self/fd/3`, requiring the installation's Linux proc filesystem. No temporary PDF or public cache is created. An unavailable extractor is reported as unresolved; there is no OCR fallback.

## Operator commands

Run as an operating system account authorized to read the installation PDFs and private inventory. Replace the example client ID and private paths with the corresponding installation values. Keep all inputs and outputs outside Git. No production connection is needed for inventory-only analysis.

```sh
cd /var/www/dewawi
umask 077
INSTALL_BASE=/var/www/dewawi
PRIVATE_ROOT=/srv/dewawi-private
CLIENT_ID=100

chmod 700 "$PRIVATE_ROOT"
chmod 600 "$PRIVATE_ROOT/inventory.jsonl"

php tools/document-recovery-assessment.php \
  --inventory="$PRIVATE_ROOT/inventory.jsonl" \
  --base="$INSTALL_BASE" \
  --client="$CLIENT_ID" \
  --output="$PRIVATE_ROOT/assessment-offline-$(date -u +%Y%m%dT%H%M%SZ)" \
  --recent-since=2025-11-18
```

Inventory-only mode reads the explicitly supplied inventory and scoped files. It cannot verify database permissions or historical contact associations; contact candidates explicitly retain that uncertainty. It discovers additional PDFs only in the exact tenant/module/type/internal-document attachment directory. It does not search arbitrary contact folders, other tenants or the installation tree for filename matches.

For current database correlation, provide an existing configuration using SELECT-only database credentials and an active authorized operator ID. Never use a write-capable application credential for this assessment. The configuration file must have mode 0600 with no symlinks or hard links. Its format is the existing migration INI convention:

```ini
[production]
resources.db.params.host = "localhost"
resources.db.params.port = "3306"
resources.db.params.username = "existing_select_only_account"
resources.db.params.password = "existing_private_password"
resources.db.params.dbname = "corresponding_installation_database"
```

```sh
OPERATOR_USER_ID=42
chmod 600 "$PRIVATE_ROOT/select-only.ini"

php tools/document-recovery-assessment.php \
  --inventory="$PRIVATE_ROOT/inventory.jsonl" \
  --base="$INSTALL_BASE" \
  --client="$CLIENT_ID" \
  --config="$PRIVATE_ROOT/select-only.ini" \
  --environment=production \
  --operator-user="$OPERATOR_USER_ID" \
  --output="$PRIVATE_ROOT/assessment-db-$(date -u +%Y%m%dT%H%M%SZ)" \
  --recent-since=2025-11-18
```

Both database connections use read-only transactions and are rolled back. The operator must be active, able to access the selected tenant through the existing client relationship rules, and have view permission for every valid blocked document type. Edit and historical-original approval rights are unnecessary. Document lookup uses the existing server-side object context. Changed visible numbers, missing documents or foreign ownership prevent candidate reads; they do not trigger an offline fallback. Parent-owned contacts are resolved through the existing tenant relationship, while PDF paths remain scoped to the document owner. A mismatch between inventory and authorized contact tenant rejects the run.

For a nondefault local MySQL socket, pass the same socket to both PHP drivers before the script name:

```sh
php -d pdo_mysql.default_socket=/private/mysql.sock \
    -d mysqli.default_socket=/private/mysql.sock \
    tools/document-recovery-assessment.php --help
```

## Reports and classifications

The output directory has mode 0700 and contains four files with mode 0600:

| File | Content |
| --- | --- |
| `started.json` | Start time, tenant and inventory path/hash; always an incomplete start marker |
| `documents.jsonl` | One schema-version-1 result per blocked document in inventory order |
| `review.csv` | Semicolon CSV sorted by priority and descending date, including the corresponding detail JSONL line number; spreadsheet formulas are escaped |
| `summary.json` | Completion status, input hash and counts, classifications, observed SHA patterns, priorities, extraction outcomes and detail file hash |

Each document preserves module, controller, internal ID and visible number. Each candidate preserves its original path, all inventory/database/discovery references, identity binding, observed existence/status, size, SHA-256, metadata discrepancies, unresolved questions and proposed next action. Identical bytes share one version record with all paths. The attachment/current/legacy SHA pattern uses local equality labels, not origin rankings. Inventory hashes and database hashes are compared with newly read bytes; changed or malformed recorded hashes remain explicit issues.

Database mode reuses the review loader to collect active and deleted document attachment versions and email-message attachment references. Messages must belong to the document tenant and explicit document context. Paths, ordering and archive/attachment references are checked. A message tied to the document but lacking a valid attachment reference can contribute only an additional candidate. Delivery status is retained as evidence requiring review. Rejected foreign paths are listed but never opened. Current contact metadata must uniquely associate the path with this document; filenames alone are insufficient.

| Classification | Review action |
| --- | --- |
| `consistent_candidates_needing_origin_confirmation` | Compare the available byte-identical candidates with independent retained origin evidence |
| `conflicting_versions_needing_review` | Review all byte versions and text differences; determine which, if any, was actually issued |
| `possible_additional_candidate` | Resolve the association of a newly discovered document-folder or incompletely referenced message copy |
| `contact_mapping_ambiguous` | Resolve the contact mapping before relying on contact-folder copies |
| `no_candidate_found` | Search authorized external paper, customer or dispatch archives |
| `invalid_or_unreadable_candidate` | Resolve unsafe paths, metadata discrepancies, invalid document context or unreadable PDFs |

Contact ambiguity takes classification precedence, followed by conflicting bytes and invalid evidence. All candidate issues remain visible even when another class takes precedence. Every result has `original_confirmed=false` and `approval_allowed=false`. These reports are not recovery approvals and are not input selections for the archive recovery workflow.

Priority 0 is recent invoices, priority 1 other invoices with conflicting bytes, priority 2 other sales documents, and priority 3 remaining document types. `--recent-since` defaults to one year before the run; set it explicitly for reproducible triage. A current stored or supplied inventory document date is preferred. If absent, a single identical labeled issue date across every extracted version may supply a triage hint only when each version's labeled number and type match. That date is explicitly marked `unverified_pdf_issue_date_for_triage_only`. Missing or conflicting dates remain unresolved.

## Text comparisons and unresolved evidence

Existing archive inspection validates the PDF header and EOF marker. Successful `pdftotext` extraction adds a readability check; neither constitutes a full PDF semantic validation or authenticity check. Extraction is bounded to ten seconds, 1 MiB of text and 8 KiB of diagnostics per byte group. The source hash and size are checked again after extraction. Failed, changed, empty, unavailable or bounded extraction retains no inferred facts.

Version records contain labeled document number, type, date, issuer, recipient and monetary comparisons when extractable. Missing labels, multiple values, ambiguous slash dates and malformed amounts remain unresolved. Current stored amounts are compared at two decimal places without recalculating or updating them. Issuer information from current client master data is explicitly identified as current, not a historical identity snapshot. Unlabeled address blocks, scanned pages and unusual layouts require visual review. Draft and reconstruction markers are reported when recognized.

Text differences compare normalized lines against the first successfully extracted byte group as a technical reference, never an original selection. Reports retain a 1,500-character excerpt and up to 20 added/removed lines of 300 characters each, with full difference counts and a text hash. They can contain business data and must remain private. Byte differences with equal extracted text remain conflicting versions. Line comparisons do not understand legal or visual significance, detect every layout/image difference, or prove delivery.

The default `--max-candidates=200` limits per-document inspection and discovery. A limit leaves `analysis_complete=false` and makes the summary incomplete; rerun into a new private directory with an explicitly higher limit after reviewing the scoped directory. The supported maximum is 10000. JSONL lines are limited to 8 MiB. Processing streams the document records; duplicate detection retains keys and the CSV retains only blocked-document index rows.

Exit 0 means the assessment completed, including unresolved recovery decisions. Exit 3 means a candidate cap left analysis incomplete. Exit 2 means invalid input, authorization failure or a runtime error. On an interrupted or failed run, a start marker and partial private details may remain; absence of a completed summary is not a successful assessment. Files are observed during the run, not as an atomic filesystem/database snapshot. Recheck candidate bytes and obtain independent origin evidence before any separately authorized recovery.

## Local verification

```sh
php -d error_reporting=8191 tests/documents/document-recovery-assessment.php
python3 tests/documents/document-archive-integrity-db.py
```

The first command uses generated synthetic PDFs and all 15 presence/equality partitions of attachment, current and legacy copies, including missing sources. It also checks changed hashes, invalid PDFs, extraction limits, content ambiguity, cross-tenant paths, private permissions, concurrent reads and 50001-record streaming under a 32 MiB PHP memory limit. The second command reuses K2's disposable full-schema database and SELECT-only account for actual migration JSONL, multiple/deleted attachment versions, email references, contact ambiguity, view-only and denied operators, foreign ownership, parent contacts and unchanged tables/files. Neither command connects to production.
