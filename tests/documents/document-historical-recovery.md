# Historical file recovery: K4

## Rule and provenance

`attachment-legacy-sha256-v1` prefers an actual document-specific attachment PDF when it is byte-identical to the actual legacy contact-folder PDF. An additional differing PDF in the current contact folder is recorded and retained; it does not disqualify the pair. This is a practical historical **file recovery** rule, not independent evidence that the file was the version originally issued. No document is recalculated, renumbered, finalized or stamped by recovery.

The operator tool reuses the complete private JSONL inventory produced by `document-archive-migration.php`, existing ZF1 boot/permission checks, attachment metadata, PDF inspection/extraction, archive file publication and `Documentarchive::registerOriginal`. It derives source paths from current tenant-scoped document/contact records rather than trusting paths or hashes copied into an inventory. The scope includes non-complete inventory document records; complete originals are outside the recovery scope. All eight existing document types are supported. Old migration/release commands retain their existing rules; use this explicit batch workflow for K4.

Eligibility requires the exact internal ID, module/controller, visible number, finalized server-side state and selected tenant; a unique visible number and authorized contact mapping; active document-specific attachment association at the expected path with matching recorded size; both readable, nonempty, matching PDF files with equal SHA-256 and size; a successful bounded PDF parser check without diagnostics; and a missing canonical path with no conflicting registration. Definite extracted number/type/date contradictions are excluded. Unavailable or ambiguous text identity is recorded as unresolved and does not become authenticity evidence: the association and reviewed file-matching policy remain the basis. Known reconstruction proofs, copy/draft markings, invoice correction hashes, symlinks, foreign attachment metadata and ambiguous contacts are excluded. A missing parser or an over-limit/unreadable/encrypted PDF requires separate review.

An existing registration is protected. If its identity/path/name/version/SHA/size exactly match the pair and only its canonical bytes are missing, the tool may restore those exact bytes while preserving the entire existing registration and all approvals. Conflicting registrations or occupied canonical paths are never replaced or silently adopted. A retry may recognize an intact result from the same approved batch, or the exact bytes of an already matching registration, without writing again.

## Private preview and one administrator approval

Replace the illustrative installation, client, operator and private paths below with the intended installation and an authorized administrator. The inventory must be a complete tenant dry-run JSONL with its final summary, stored privately outside both installation and repository. The private parent directory must already exist with mode 0700; configuration/inventory files require 0600. Each output directory must be new. A SELECT-only account is sufficient for preview. Applying new registrations requires the existing appropriate database/file write access.

Create the preview; this never copies PDFs or updates the database:

```sh
php tools/document-historical-recovery.php \
  --action=plan \
  --config=/private/dewawi/select-only.ini \
  --base=/canonical/dewawi \
  --client=100 \
  --operator-user=42 \
  --inventory=/private/dewawi/inventory.jsonl \
  --output=/private/dewawi/k4-preview
```

Review `report.json`, `documents.csv` and `summary.json` privately. The CLI returns `report_sha256`. The detailed JSON contains exact identities, source paths, current existence/validity/size/hashes, attachment associations, stored-data fingerprint, completed checks, extracted identity comparisons, existing registrations and explicit exclusion reasons. Missing fields after an exclusion were not validated. CSV includes a header and exact IDs, numbers, decisions, reasons and pair paths/hashes/sizes. Reports use mode 0600 in a mode 0700 directory and must never be committed or published.

After the administrator has reviewed that exact preview and a usable backup of **both database and source/archive files** has been separately verified, apply it with one approval:

```sh
php tools/document-historical-recovery.php \
  --action=apply \
  --config=/private/dewawi/archive-writer.ini \
  --base=/canonical/dewawi \
  --client=100 \
  --operator-user=42 \
  --inventory=/private/dewawi/inventory.jsonl \
  --plan=/private/dewawi/k4-preview/report.json \
  --confirm-plan=REVIEWED_REPORT_SHA256 \
  --backup-verified \
  --backup-reference='verified database and filesystem backup identifiers and restore-check reference' \
  --output=/private/dewawi/k4-application
```

`--confirm-plan` is the actual returned 64-character SHA-256, not the placeholder above. Backup verification is an explicit operator attestation and reference; the tool does not independently test a backup restore. There is no production recovery authorization implied by installing this code or creating a preview.

The plan binds installation, tenant, administrator, input inventory hash, extractor and exact document scope. Another administrator needs their own reviewed preview. Apply serializes attempts for the same preview, checks the whole approved eligible set before any archive writes, and revalidates each document and its metadata under database locks immediately before publication. A changed source, association, snapshot or current-folder observation requires a new preview/review. Excluded rows cannot silently become approved. Every published result receives a final registration/path/size/SHA and unchanged-business-data check before commit. Existing original and source files are retained byte-for-byte, including differing current versions.

New registrations retain source paths/hashes/sizes, attachment associations, recovery date/operator, applied rule, reviewed plan hash, backup reference and batch approval in existing archive parameters. `original_provenance_verified` remains false and the origin category is `historical_file_recovery_origin_unconfirmed`. The existing provenance classifier therefore reports `registered_archive_origin_unconfirmed`; it is not upgraded to `verified_recovered_original`. The invoice/document UI explicitly describes this limitation. Restorations of existing matching registrations retain their previous parameters; their K4 rule/source evidence is retained in the private application report.

Apply writes `approval.json`, per-document results, `report.json`, `documents.csv` and `summary.json`. Per-document transactions permit partial success if a later write fails; failed rows are explicit and the CLI exits nonzero. A known rollback removes only the newly published file identified by inode/hash. An uncertain commit retains the file for inspection; an unregistered occupied path is not automatically adopted on retry. Inspect database/file state and preserve evidence before resolving such a case. Reusing the same approved plan is allowed only while its inputs still match; every retry needs a new private output directory.

## Permanent marked copies and normal Download

Normal authorized users keep the existing Download link. A valid registered original follows its existing download path unchanged. If that path is unavailable, the existing reconstruction/diagnostic renderer produces a marked `KOPIE` (`COPY` in English) from stored information, including clearly identified gaps where reliable reconstruction is impossible. The UI retains the factual reconstruction explanation and explicit copy-view action. Draft previews still carry `MUSTER`/`SAMPLE`.

Copies persist below `files/documents/.viewing-copies/<client>/<module>/<controller>/<id>/<snapshot-sha>/<locale>/`. The existing web-server and direct-file restrictions protect the document tree; the copy area/directories use 0700 and PDFs/records/locks use 0600. An immutable content-addressed PDF and private `copy.json` retain identity, stored-data fingerprint, locale, generation date/operator, size/SHA and explicit non-original status. Concurrent first readers serialize publication and receive the same bytes. Later downloads reuse the stored file even if templates change. A changed stored snapshot or locale gets a separate retained copy; existing copies are not expired or overwritten. A missing receipt can be rebuilt while retaining orphaned PDF evidence. A receipt pointing to a missing, corrupt or unsafe PDF fails closed for operator inspection rather than silently replacing the stored copy.

Ordinary copy access requires document view permission, not historical-original approval rights or database write access. Copies never use the canonical original path, enter `documentarchive`, become default invoice email attachments or enter original GoBD delivery. Original registration and privileged recovery selection explicitly reject hashes retained in the permanent viewing-copy area, including orphaned copies. Existing GoBD issuer/evidence checks remain in force; recovered original files carry their unconfirmed provenance and detailed archive parameters. K3 continues to block this unproven recovery category as an invoice correction original. Independent original proof must use the existing privileged review workflow; K4 does not supply it.

## Verification and deployment

No SQL migration is required. Deploy after the existing K3 schema/application prerequisites; no production SQL, recovery, deployment or data access is performed during development. Keep the existing protected document-tree web-server rules enabled. PHP needs the existing PDF renderer and `/usr/bin/pdftotext` (or the explicit `--pdftotext` path) for batch validation.

Synthetic coverage is integrated into `python3 tests/documents/document-archive-integrity-db.py`: all eight types; matching/conflicting/missing versions; invalid PDF structures; identity contradictions; marked copies; missing/deleted/foreign/incorrect-size attachment metadata; ambiguous contacts; symlinks; matching and conflicting registrations; occupied targets; administrator/plan/backup rejection; stale preflight; database rollback; parent-child ownership; concurrent application and retries; source/fiscal immutability; archive/export resolution and K3 provenance rejection. Existing K2/K2R/K3, SMTP and browser checks run alongside it.

`python3 tests/documents/document-recovery-db.py` covers the permanent-copy HTTP path with a SELECT-only account, normal Download, every-page labels, concurrent first creation, missing receipt, corrupt/missing stored PDF, original preference, tenant/permission denial, diagnostic gaps, no cache/original registration or GoBD leakage, and German/English desktop/mobile panels. Existing GoBD, archive, PDF, label/schema, file-access, attachment and permission suites remain applicable.

## Local results and affected files

All checks below passed on 18 November 2026 using synthetic fixtures and disposable local databases:

| Command | Actual result |
| --- | --- |
| `python3 tests/documents/document-archive-integrity-db.py` | 245 K4 checks; existing 164 K2, 27 K2R, 40 revision and 83 correction checks; exact SMTP bytes and 8 desktop/mobile locale panels |
| `python3 tests/documents/document-recovery-db.py` | 2198 recovery checks, including 669 viewing/draft checks; original/mixed batch fixtures; 16 desktop/mobile locale panels |
| `python3 tests/documents/gobd-export-db.py` | 705 checks |
| `php tests/documents/document-recovery-assessment.php` | 183 checks, including 50,001-record streaming |
| `php tests/documents/document-archive.php` | 129 checks |
| `php tests/documents/pdf.php` | 208 checks |
| `php tests/documents/document-labels.php` | 67 checks |
| `php tests/documents/e-invoice.php` | 371 checks |
| `php tests/security/file-access.php` | 430 checks |
| `php tests/security/permissions.php` | 415 checks |
| `php tests/security/attachments.php` | 269 checks, with the included 364 quote-email checks also passing |

PHP syntax checks passed for all 12 changed PHP/view files; the changed Python runner parsed successfully. Failure/denial messages from deliberate negative fixtures are expected. No production inventory, PDFs or database were processed. No SQL migration or production/manual data updates were performed.

Exact changed files:

- `application/models/DbTable/Documentarchive.php`
- `application/views/helpers/Document.php`
- `languages/de_DE/default/document.php`
- `languages/en_US/default/document.php`
- `library/DEEC/Controller/DocumentAction.php`
- `library/DEEC/DocumentArchive.php`
- `library/DEEC/DocumentRecovery.php`
- `library/DEEC/DocumentReview.php`
- `tests/documents/document-archive-integrity-db.php`
- `tests/documents/document-archive-migration.md`
- `tests/documents/document-historical-recovery-db-fixtures.php`
- `tests/documents/document-historical-recovery.md`
- `tests/documents/document-recovery-db.py`
- `tests/documents/document-recovery.md`
- `tests/documents/document-viewing-fixtures.php`
- `tools/document-historical-recovery.php`
