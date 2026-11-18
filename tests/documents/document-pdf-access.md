# Consistent document PDF access

## Fix

The K4 viewing stream assigned `controller-<internal id>-reconstructed-copy.pdf` independently of the document filename. The view helper returned before its normal iframe, `previewAction` had no K4 fallback, and contact issued files exposed a document link only for a verified original. These separate paths caused the reported inconsistencies.

Copies now use the safe stored document filename. A missing/unsafe stored filename falls back to the controller and visible document number (or just the controller when unnumbered). No file, snapshot, archive metadata or existing immutable PDF is renamed or rewritten. `Rechnung-112326.pdf` for internal ID 59505 is covered solely by generated synthetic invoice data.

Normal finalized preview falls back through `DEEC_DocumentReview::openViewingCopy`, just like Download. Preview is inline; Download is an attachment. Both reuse the same permanent protected bytes for the current stored snapshot and locale. AJAX preview returns a normal authenticated `/module/controller/preview/id/<internal id>` URL and normal filename, never a private file path or a protected copy cache URL. Draft preview behavior and MUSTER/SAMPLE remain unchanged.

The document view retains its factual copy explanation, one normal Download link and a responsive iframe. The special reconstruction route remains compatible for existing callers but is no longer needed in the ordinary view. Contact issued-document filenames link to the same authorized document Download route used by the existing history PDF action. The table uses the existing responsive wrapper and localized “Kopie verfügbar”/“Copy available” status. Historical source conflict/invalid statuses remain visible alongside the copy availability indication; conflicting historical files are not selected as originals.

Archive viewing explicitly distinguishes an absent file from an invalid registration. The optional `getForDocument(..., 'pdf', true)` permits only a physically missing file after validating registration identity/version/name/path/SHA/size metadata. Corrupt bytes, wrong checksums/sizes, symlinks, bad paths and ambiguous registrations still throw. The ordinary view reports an invalid archive; PDF/AJAX access fails closed. All non-viewing callers retain the strict default: original exports, email attachments and K3 correction approval do not substitute viewing copies.

## Verification

Executed locally with synthetic data and disposable databases:

- `python3 tests/documents/document-recovery-db.py`: 2226 checks, including 697 viewing/draft checks; 20 German/English desktop/mobile panels. Covers the reported synthetic invoice, stored response filename, inline/download byte equality, immutable copy receipt/file, actual AJAX response, contact filename route and actual history PDF JavaScript handler, draft covers/multipage marking, corrupt original/registration conflicts, missing/corrupt stored copies, permissions and original/export isolation.
- `python3 tests/documents/document-archive-integrity-db.py`: 164 K2, 27 K2R, 40 revision, 83 correction and 245 K4 checks; existing SMTP and browser checks passed.
- `python3 tests/documents/gobd-export-db.py`: 705 checks passed.
- `php tests/documents/pdf.php`: 208 checks passed.
- `php tests/documents/e-invoice.php`: 371 checks passed.
- `php tests/documents/document-archive.php`: 129 checks passed.
- `php tests/documents/document-recovery-assessment.php`: 183 checks passed.
- `php tests/documents/document-labels.php`: 67 checks passed.
- `php tests/security/file-access.php`: 430 checks passed.
- `php tests/security/attachments.php`: 269 attachment and included 364 quote-email checks passed.
- `php tests/security/permissions.php`: 415 checks passed.
- `php tests/security/document-edits.php`: 7424 checks passed.

All 14 changed PHP/view files passed syntax checking; the changed Python runner parsed successfully. Existing fixture setup was extended with the real front-controller response/router context needed to exercise JSON responses and contact URL rendering. Earlier reconstruction-only UI assertions were updated to require the ordinary embedded preview and a single normal Download link. Corrupt-original assertions now require rejection, reflecting the explicit security requirement.

No SQL migration, production data changes, original recovery, email substitution or GoBD format changes are included. Missing/corrupt permanent copies and corrupt/conflicting registrations still require operator inspection. The application must have the existing K4 protected document-tree configuration and storage access.

## Changed files

- `application/models/DbTable/Documentarchive.php`
- `application/modules/contacts/models/DbTable/Emailattachment.php`
- `application/modules/contacts/views/scripts/contact/document-files.phtml`
- `application/views/helpers/Document.php`
- `languages/de_DE/contacts/contact.php`
- `languages/de_DE/default/document.php`
- `languages/en_US/contacts/contact.php`
- `languages/en_US/default/document.php`
- `library/DEEC/Controller/DocumentAction.php`
- `library/DEEC/DocumentReview.php`
- `tests/documents/document-historical-recovery.md`
- `tests/documents/document-pdf-access.md`
- `tests/documents/document-recovery-db.php`
- `tests/documents/document-recovery-db.py`
- `tests/documents/document-recovery-download-fixture.php`
- `tests/documents/document-viewing-fixtures.php`
- `tests/security/file-access.php`
