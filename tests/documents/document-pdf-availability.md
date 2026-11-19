# Bulk historical PDF availability

`tools/document-pdf-availability.php` assesses issued documents and explicitly backfills the existing immutable, protected K4 viewing copies. It does not register/recover originals or update business/email data. Original recovery exclusions (differing files, contact mappings, attachment metadata or numbering) are separate from ordinary viewing permission. A valid original remains preferred and unchanged. Missing source PDFs do not prevent a stored-data copy; incomplete saved data uses the existing marked diagnostic PDF with explicit gaps.

## Execution identity and private files

Run preview, apply and historical recovery as the **actual non-root PHP-FPM/Apache account for this installation**, for example `www-data`. Check the pool/vhost configuration; do not assume the account name. `--web-user` must resolve to the current effective UID and cannot be root. The bulk tool requires this for every action. Historical recovery now requires it for apply and binds the declared publication UID into preview/approval evidence; generate a fresh historical preview with `--web-user` as the same account before apply.

Copy directories remain 0700; PDFs, receipts, locks and operator reports remain 0600. Historical recovery uses umask 0077 and retains exclusive archive publication. No world-readable copy cache is created. PHP POSIX identity functions must be enabled for these CLI identity checks. The operator database account may be SELECT-only for bulk preview **and apply**: apply writes only protected copy/report files.

Create a new private operator directory outside the installation/repository, owned by that PHP account. For example, an administrator can provision `/private/dewawi/pdf-availability` with owner/group `www-data` and mode 0700, and place the database configuration/scope there with mode 0600 and the same owner. Do not run publication as root or recursively chmod/chown the historical archive. Existing root-owned private directories/copies are reported as blockers when inaccessible; review and correct ownership of only the affected paths separately, preserving bytes and private modes. Never delete an immutable damaged copy or overwrite an original to clear a blocker.

## Scope and preview

Use `--scope=all` to snapshot every non-deleted issued document in the selected tenant across all eight supported sales/purchase types. Drafts are excluded using the existing server-side state rule. Alternatively pass a private JSON file with explicit internal identities:

```json
{"documents":[{"module":"sales","controller":"invoice","id":1},{"module":"purchases","controller":"purchaseorder","id":2}]}
```

Explicit missing, unauthorized, foreign or unfinalized records are reported individually as blocked. Supplied visible numbers or filesystem paths are not used for authorization. To select the remaining blocked documents from an existing private inventory, create the scope privately without copying its business data into Git:

```sh
sudo -u www-data python3 - <<'PY'
import json, os
documents = []
with open('/private/dewawi/inventory.jsonl') as source:
    for line in source:
        row = json.loads(line)
        if row.get('kind') == 'document' and row['plan']['status'] != 'complete':
            plan = row['plan']
            documents.append({key: plan[key] for key in ('module', 'controller', 'id')})
path = '/private/dewawi/pdf-availability/scope.json'
with os.fdopen(os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600), 'w') as target:
    json.dump({'documents': documents}, target)
PY
```

Preview does not render, create, repair or lock viewing copies. It inspects existing receipts/bytes and validates access using the normal document viewing service. A `copy_can_be_generated` classification means that authorized stored data is available to the existing renderer; actual rendering and HTTP verification happen only during apply. Original-recovery eligibility is assessed using K4 `file-association` validation and remains unconfirmed provenance; the bulk tool never applies that recovery.

```sh
sudo -u www-data php tools/document-pdf-availability.php \
  --action=preview --scope=all \
  --config=/private/dewawi/select-only.ini --base=/var/www/dewawi \
  --client=100 --operator-user=42 --web-user=www-data --locale=de_DE \
  --http-base=https://erp.example.invalid \
  --output=/private/dewawi/pdf-availability/preview
```

For a subset, replace `--scope=all` with `--scope=/private/dewawi/pdf-availability/scope.json`. No production access is needed for development; these commands are operator examples to run only after installation/review authorization.

## Reviewed apply, HTTP verification and resume

Review the private preview, exclusions and returned `report_sha256`. Obtain an authenticated administrator web session in the **same selected tenant and locale**. Store its Cookie header **value only** (for example `PHPSESSID=...`) in a private 0600 file readable by the PHP account, outside the webroot. Do not put the cookie in command arguments, Git, logs or shared reports. Keep the session valid during the run. HTTPS certificate/hostname verification remains enabled; redirects and login HTML fail verification. Plain HTTP is allowed only for loopback test servers.

```sh
sudo -u www-data php tools/document-pdf-availability.php \
  --action=apply \
  --config=/private/dewawi/select-only.ini --base=/var/www/dewawi \
  --client=100 --operator-user=42 --web-user=www-data --locale=de_DE \
  --http-base=https://erp.example.invalid \
  --plan=/private/dewawi/pdf-availability/preview/report.jsonl \
  --confirm-plan=REVIEWED_REPORT_SHA256 \
  --cookie-file=/private/dewawi/pdf-availability/session-cookie \
  --limit=200 \
  --output=/private/dewawi/pdf-availability/apply-001
```

Apply requires the exact reviewed preview hash, active administrator, same installation/tenant/operator/UID/locale/site and unchanged saved snapshots. It reuses existing copy locks/publication and verifies the copy through both `/module/controller/download/id/<id>` and `/module/controller/preview/id/<id>` using GET. Success requires HTTP 200, PDF MIME, attachment/inline disposition, normal filename, private/no-store and nosniff headers, and exact streamed size/SHA-256 matching the authorized viewing service. This verifies actual web-server readability/response bytes, not merely file existence. It does not bypass reverse proxies, SaaS restrictions or web-session permissions; those surface as explicit HTTP blockers. No public URLs or private filesystem paths are substituted into the UI.

`--limit` bounds attempted generation/HTTP verification per run. Continue with a **new** output directory and the previous apply report:

```sh
sudo -u www-data php tools/document-pdf-availability.php \
  --action=apply \
  --config=/private/dewawi/select-only.ini --base=/var/www/dewawi \
  --client=100 --operator-user=42 --web-user=www-data --locale=de_DE \
  --http-base=https://erp.example.invalid \
  --plan=/private/dewawi/pdf-availability/preview/report.jsonl \
  --confirm-plan=REVIEWED_REPORT_SHA256 \
  --cookie-file=/private/dewawi/pdf-availability/session-cookie \
  --resume=/private/dewawi/pdf-availability/apply-001/report.jsonl --limit=200 \
  --output=/private/dewawi/pdf-availability/apply-002
```

Resume rechecks current authorization, original/copy integrity and saved snapshot before retaining a prior successful HTTP verification for identical bytes/filename/size. Its original verification timestamp is preserved and `verification_reused` is explicit; the header references the prior private report by path/SHA for its HTTP evidence. Retain the report chain. Only compact identity/snapshot/hash records are held for the full scope; full HTTP evidence stays in the streamed reports. To freshly request every PDF again, omit `--resume`. Failed/pending rows are retried; no copy is overwritten. A partially written final line from an interrupted apply report is ignored only on resume. Existing copy locks serialize concurrent first publication; repeated backfills retain the same bytes and receipt. A snapshot change requires a fresh preview/review, while any previously stored copies remain retained.

## Reports and blockers

Each output has private `report.jsonl` and `summary.json`. JSONL contains a context/approval header, a flushed per-document record and a final coverage summary. It is bounded per line and contains no PDF bytes or cookies. Results preserve module/controller/internal ID, saved number, snapshot hash, normal filename, size/SHA, PDF status, original-recovery eligibility/exclusion, errors and HTTP evidence/timestamps. The five preview classifications are:

- `original_available`
- `original_recovery_candidate` (copy generation is still allowed; original recovery is separate)
- `copy_available`
- `copy_can_be_generated`
- `blocked_with_specific_reason`

Progress goes to stderr every 100 documents and at completion. Exit 0 means preview completed without blockers or the apply scope is fully verified; exit 2 means blocked/pending documents (inspect coverage and resume/review); exit 1 is an invalid command/approval/input or fatal run failure. Only `every_scoped_document_http_verified: true` establishes full checked coverage. `http_verified_this_run` and `http_verifications_reused` distinguish fresh requests from prior timestamped evidence. For a fresh check of current sessions, SaaS access and server configuration for the whole scope, omit `--resume`. The private production inventory was not available during development, so no production-wide success count is asserted.

Remaining blockers include absent/deleted/draft documents, tenant/view denial, conflicting/corrupt archive registrations or canonical originals, damaged/missing PDFs referenced by immutable copy receipts, unsafe/inaccessible directories, renderer resource failures, stale plans, and expired/wrong-tenant/wrong-locale web sessions. Original candidate conflicts, missing contact records and missing saved numbers are not themselves viewing blockers. Gaps remain explicitly visible in marked copies; no missing recipient, number, date or amount is invented. Ambiguous contact numbers are omitted from Contact Files/history rather than assigned to either contact; the authorized normal document route remains available.

Historical-original recovery still uses `tools/document-historical-recovery.php`, separately reviewed K4 batch approval, verified database/filesystem backup and final integrity checks. Add `--web-user=www-data` to **both** its fresh preview and apply commands and run both as that account; its stricter original/source/provenance and K3 rules remain intact. No SQL migration, automatic email substitution, GoBD original substitution or production recovery is performed by this bulk-copy tool.

## Synthetic verification

`python3 tests/documents/document-recovery-db.py` includes the new availability fixture alongside K1–K4 viewing/recovery checks: eight document types; missing/ambiguous/deleted/foreign contacts; missing saved values; original preference; differing historical PDFs; private read-only preview; administrator/identity/plan rejection; concurrent/repeated/resumed publication; damaged copies and stale snapshots; actual authenticated HTTP preview/downloads; and unchanged original/archive/business/email data. Existing desktop/mobile, locale, GoBD and security suites remain applicable. No production business data is used.

Actual local verification on 19 November 2026 (all passed):

| Command | Result |
| --- | --- |
| `python3 tests/documents/document-recovery-db.py` | 2615 checks, including 389 availability and 697 viewing/draft checks; 50,001-line report streaming; 20 German/English desktop/mobile panels |
| `python3 tests/documents/document-archive-integrity-db.py` | 164 original archive, 27 assessment, 40 revision, 83 correction and 338 historical recovery checks; exact SMTP bytes and 8 desktop/mobile panels |
| `python3 tests/documents/gobd-export-db.py` | 705 checks |
| `php tests/documents/document-archive.php` | 129 checks |
| `php tests/documents/pdf.php` | 208 checks |
| `php tests/documents/document-recovery-assessment.php` | 183 checks, including 50,001-record streaming |
| `php tests/security/permissions.php` | 415 checks |
| `php tests/security/attachments.php` | 269 checks, plus 364 included quote-email checks |
| `php tests/security/file-access.php` | 430 checks |

All ten changed/new PHP files passed syntax checks. The file-access fixture initially rejected the new raw contact-count query; the implementation was changed to the existing Contact model query API and the full security/recovery checks passed afterward. Only disposable databases, synthetic PDFs and loopback test services were used. No production files/database, SQL migration, push or deployment were involved. All ten documents in the eligible availability fixture scope were served through both normal HTTP routes; explicit negative scopes retained documented blockers.
