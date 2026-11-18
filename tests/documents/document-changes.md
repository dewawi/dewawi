# Document revisions and corrections

## PDF markings

New draft PDFs display `MUSTER` in German and `SAMPLE` in English. Newly rendered historical reconstructions and diagnostic copies display `KOPIE` or `COPY`, including internal reconstructions. Every page uses bold dark-grey text in the existing top margin with a thin neutral border and no colored fill. Stored original PDFs retain their exact bytes. The historical viewing UI retains the explanation about reconstruction limits, current templates and missing historical evidence.

## Quotation and sales order revisions

The view offers **Angebot überarbeiten** or **Auftragsbestätigung überarbeiten**, then **Neue Fassung erstellen** with a mandatory reason. Creating a revision requires the existing document view, add and edit permissions and a valid session token. It copies the selected finalized, uncancelled document into a normal editable draft through the existing models and position/pricing helper. Position sets, child/master relationships and saved price rules are retained. Existing fields and AJAX autosave continue to operate on the new draft.

Each revision receives a new ordinary quotation/order number through the existing sequence at finalization. The PDF explicitly identifies its revision sequence and first document number. Original numbers are integer fields used by downstream documents; reusing them with suffixes would create ambiguous relationships. The `documentrevision` table records the first document, selected base, new draft, sequence, reason, creator and creation time. Approval uses the new document's existing completion time and user fields. The UI links the first document and every later version, including drafts. New revisions can be based on a selected earlier finalized version; the base remains explicit in history.

Only authorized administrators with document view/edit permissions may finalize a revision. The standard finalization button is hidden for other users. Revision finalization requires POST and a session token; an autosaved state change cannot approve the revision. Root-row locks serialize revision sequence allocation, request keys make creation retries idempotent, and the existing document lock and archive transaction serialize approval. Existing finalized PDFs, email attachments and downstream invoice/order references are never replaced or redirected. Sending a new version remains a separate explicit action through the existing email composer.

## Editorial invoice corrections

The finalized invoice view offers **Rechnung berichtigen**, requiring a reason. This creates a separate `invoicecorrection` draft; it never copies an invoice into a new fiscal invoice. Its external identity is `RB-<original invoice number>-<correction sequence>`. Sequence numbers include drafts and remain reserved. The original invoice number sequence is untouched. The chain points to the latest approved correction when the draft was created; approval rejects a draft whose approved base has since changed. Create request keys and root invoice locks make retries and concurrent approval safe.

A frozen snapshot retains the invoice, positions, position sets and saved rules, original PDF reference/hash/provenance, issuer details at correction creation, original text and the effective text after the preceding correction. The editor selects one field, sees its previous value, saves a specific correction and reloads the comparison. All changes remain visible before approval. Original/before/after values, creator, latest editor, timestamps, reason, approving administrator, actual approval date, approval declarations and evidence references persist separately. Every approved correction remains in chronological history. The correction PDF includes the original invoice number/date, its own current identity/date, issuer and recipient, cumulative corrected text, previous correction identity and unchanged stored amounts. It uses the existing TCPDF renderer without recalculating invoice amounts or adopting a current invoice template/logo. Drafts carry MUSTER/SAMPLE on every page; approved files have no draft marking.

Allowed fields are recipient name/additional name/department/street/postcode/city, subject, reference, and existing active position title/description. The issuer and recipient must remain the same legal persons and the underlying supply must remain unchanged. Country, VAT/tax identifiers, contact assignment, dates, article numbers, quantities, prices, tax rates, currency, totals and cancellation are excluded. Unknown fields and other positions are rejected on the server. Semantic changes hidden in text still require human review; a whitelist cannot determine legal or fiscal significance. Financial/tax changes require separate review of the existing cancellation/new-document process; this workflow performs neither automatically.

Creation requires a complete stored amount basis and an available, checksum-verified original registration. Missing originals, known reconstructions, viewing copies, unapproved recovery origins and corrupt files cannot supply the basis. Existing ordinary registrations may have unconfirmed provenance (`parameters = NULL`); their archive classification is retained. Before every approval an administrator must independently compare the actually issued original and enter a specific evidence reference, confirm unchanged legal identities, confirm the nonfinancial scope and check the remaining mandatory invoice details. This is a recorded correction review, not a historical archive approval or proof derived from a checksum. It neither changes archive approvals nor upgrades provenance. If independent evidence is unavailable, approval must not be given. Current issuer details are explicitly a snapshot at correction creation and need identity confirmation against the issued original.

## Format and legal review

[§31(5) UStDV](https://www.gesetze-im-internet.de/ustdv_1980/__31.html) permits a correcting document specifically and unambiguously referring to the invoice; form/content requirements continue to apply. [§14 UStG](https://www.gesetze-im-internet.de/ustg_1980/__14.html) governs mandatory details, authenticity, integrity and readability. Original plus correction must therefore remain available together.

The [BMF letter of 15 October 2026, Rn. 20–21 and 24–25 (official tax authority copy)](https://www.finanzamt-muenchen.de/fileadmin/RESSOURCEN/INFORMATIONEN/Steuerinfos/Weitere_Themen/E-Rechnung/2026-10-15-obligatorische-elektronische-rechnung.pdf) requires a suitable structured correction when electronic invoicing is mandatory; public procurement rules may add stricter requirements. The existing XRechnung implementation generates ordinary UBL invoice type 380 and has no supported correction/BillingReference workflow. It is unchanged. No XML is generated for these corrections and a PDF is never substituted for a required structured correction.

Approval requires an explicit, documented basis for an otherwise permitted nonstructured invoice: consumer, outside the domestic obligation, the permitted 2026 transition, or a supply before 2025. No basis is preselected. The server blocks unresolved/structured-required cases, known contradictory country/delivery-date information and the 2026 option after 2026. It also conservatively blocks approval if any canonical XML exists (including invalid/unregistered XML) or XML is registered. This also excludes some voluntarily structured invoices until structured correction support exists. These checks cannot determine recipient status, actual supply date, transaction scope, identity or public procurement duties from incomplete historical data. Administrators must resolve those cases independently; no automatic legal eligibility is inferred. Later transition arrangements are not implemented by this scope.

## Permissions, storage, delivery and exports

All operations reuse `DEEC_Permission`, selected tenant/document ownership and existing module rules. Authorized child documents can use shared parent contacts while retaining child ownership. The operation policy is centralized in `InvoiceCorrectionService::RIGHTS` so later granular permissions can replace the initial mapping.

| Operation | Existing invoice permissions | Additional requirement |
| --- | --- | --- |
| View history/PDF | view | Tenant/document context |
| Create draft | view + add + edit | Finalized, uncancelled source and original |
| Edit draft | view + edit | Draft revision/hash and scoped POST CSRF |
| Approve | view + edit | Administrator, scoped POST CSRF and evidence/format review |
| Send correction | view + add | Existing contacts/email/send permission, scoped CSRF, approved PDF and documented PDF consent |
| Export correction evidence | view | Administrator |

Commercial revision approval similarly requires an administrator with view/edit rights. Ordinary viewing does not require historical-original approval rights. Forms opt out of AJAX autosave. Draft revision/hash checks detect stale browser submissions. Downloads use authenticated GET/HEAD actions with private/no-store headers; POST downloads are refused.

Approved PDFs are published exclusively at `dirname(canonical invoice PDF)/corrections/<internal invoice id>/<correction id>.pdf` using the existing archive staging/validation and exclusive publication implementation. These files are mode 0600 inside the existing protected `files/documents` tree, which direct file routes and web-server rules deny. There is no public cache, original-path replacement or `documentarchive` registration. Retrieval checks the derived path, size, checksum and stored record fingerprint. Fingerprints detect accidental/stale changes; they are not digital signatures or protection against a database administrator rewriting both data and hashes. Original source hashes are rechecked under locks at approval. PDF/database failures roll back without changing source fiscal rows; cleanup removes only a newly published, identified file. An uncertain commit retains evidence and blocks silent adoption/overwrite. An operator must inspect database/file state before retrying an occupied path; no automatic recovery is added.

Sending remains explicit through the existing invoice composer. Only approved, consented and readable corrections appear as **unchecked** extra attachments; the original retains its existing default selection. Sending validates rights/token again and uses the existing email transport and immutable message attachment copies. `emailmessageattachment.invoicecorrectionid` preserves the relationship. Historical-original recovery tools explicitly reject these dispatch copies as `correction_not_original`. A missing/unconsented correction is never silently replaced with a generated PDF. Without PDF delivery consent the approved document remains available through authenticated download for an otherwise permitted delivery method. No email is sent by creation or approval.

Original GoBD delivery remains unchanged. A separate admin-only `/sales/invoice/correctionexport/id/<internal invoice id>` JSON export contains approved correction identities/chains, original references/provenance, before/after and effective text, audit/review/format/consent records, snapshot/record hashes and secure PDF download URLs with size/SHA-256. Drafts are excluded and every included final PDF is verified. PDFs are downloaded through their authenticated correction action; the manifest does not make private paths public or replace the invoice's original export. A complete external correction record consists of the original, the relevant correction PDFs and this separate manifest. No new official GoBD package semantics are claimed.

## Schema deployment

Only the official `install/config/update-1.1.7-1.1.8.sql` receives new SQL. Fresh installation uses identical `documentrevision` and `invoicecorrection` definitions in `install/config/structure.sql`, plus the nullable indexed `emailmessageattachment.invoicecorrectionid`. The separate table is necessary to retain independently approved correction snapshots, chains, audit data and immutable PDF references; original archive version fields are not overloaded. No historical accounting values or original approvals are migrated.

For a site that already ran all or part of the active migration, inspect the schema first. Apply only the missing appended table definitions and, if absent, the appended nullable email-copy column/index from that official migration through the normal backed-up deployment procedure. Do not rerun the entire migration: earlier statements are not generally idempotent, and the column ALTER requires an absence check. If the Part A `documentrevision` table already exists, retain it. Deploy schema before enabling code and existing review/operator tools that read the new email reference. No production SQL or customer data is used during local implementation.

## Verification

`php tests/documents/document-labels.php` checks short markings, neutral colors and preserved body text across three pages in both locales, plus fresh/migration schema equality. `python3 tests/documents/document-archive-integrity-db.py` uses disposable MariaDB databases for K2/K2R, real quote/order revisions and corrections. It applies the appended migration to prior compatible tables and compares resulting definitions with fresh installation. Correction tests cover amount/original isolation, prohibited fields, successive chains, record/PDF tampering, missing files/originals, rejected reconstructions, XML blocks, independent origin review, consent, scoped roles/CSRF/methods, parent-child access, filesystem collisions, PDF/database failure, concurrent publication, multipage English drafts and finalized PDFs. A loopback-only SMTP fixture checks exact original/correction MIME bytes and immutable message-copy references. Chromium runs actual German/English revision and correction views at 375 and 1440 pixels, checking forms, mandatory evidence, field selection, no accidental autosave and no horizontal overflow.

The existing recovery/viewing suite covers layouts with/without covers, multipage copies, diagnostic limitations and protected original delivery. Existing archive, recovery assessment, GoBD, e-invoice, PDF, position, email attachment and security tests remain applicable. Node is unavailable locally; actual Chromium loads and exercises the changed JavaScript.

## Local verification results

Synthetic checks completed on 18 November 2026:

| Command | Result |
| --- | --- |
| `php tests/documents/document-labels.php` | 67 checks |
| `python3 tests/documents/document-archive-integrity-db.py` | 164 archive, 27 assessment DB, 40 revision and 83 correction checks; applied schema comparison, exact SMTP attachments and 8 Chromium revision/correction panels |
| `python3 tests/documents/document-recovery-db.py` | 2182 recovery checks, including historical viewing/draft coverage reported as 653; original/mixed batch fixtures and 16 Chromium panels passed |
| `python3 tests/documents/gobd-export-db.py` | 705 checks |
| `python3 tests/documents/document-review-db.py` | 38 checks |
| `php tests/documents/document-recovery-assessment.php` | 183 checks, including 50,001-record streaming |
| `php tests/documents/document-archive.php` | 129 checks |
| `php tests/documents/pdf.php` | 208 checks |
| `php tests/documents/position-read.php` | 244 checks |
| `php tests/documents/e-invoice.php` | 371 checks |
| `php tests/security/document-edits.php` | 7424 checks |
| `php tests/security/permissions.php` | 415 checks |
| `php tests/security/file-access.php` | 430 checks |
| `php tests/security/attachments.php` | 269 checks |
| `php tests/security/quote-email.php` | 364 checks |

PHP syntax checks passed for every changed PHP/view file. Changed Python runners parsed successfully. Expected refusal/failure fixtures log denied operations; all listed suites passed. Node syntax checking could not run because Node is not installed; Chromium exercised the changed JavaScript. Production data, reports and databases were not accessed or changed.

## Exact affected files

The two K3 blocks affect the following files (the pre-existing untracked UI analysis is excluded):

- `application/controllers/helpers/Email.php`
- `application/controllers/helpers/Position.php`
- `application/models/DbTable/Documentrevision.php`
- `application/models/DbTable/Invoicecorrection.php`
- `application/modules/contacts/models/DbTable/Emailmessageattachment.php`
- `application/modules/sales/controllers/InvoiceController.php`
- `application/modules/sales/services/AttachmentService.php`
- `application/modules/sales/services/DocumentFinalizeService.php`
- `application/modules/sales/services/DocumentPayloadService.php`
- `application/modules/sales/services/DocumentRevisionService.php`
- `application/modules/sales/services/InvoiceCorrectionService.php`
- `application/modules/sales/views/helpers/Finish.php`
- `application/modules/sales/views/scripts/invoice/correction.phtml`
- `application/modules/sales/views/scripts/invoice/view.phtml`
- `application/modules/sales/views/scripts/quote/edit.phtml`
- `application/modules/sales/views/scripts/quote/view.phtml`
- `application/modules/sales/views/scripts/salesorder/edit.phtml`
- `application/modules/sales/views/scripts/salesorder/view.phtml`
- `application/views/helpers/DocumentChanges.php`
- `application/views/helpers/InvoiceCorrections.php`
- `application/views/scripts/partials/document-revisions.phtml`
- `application/views/scripts/partials/email-messages.phtml`
- `application/views/scripts/partials/invoice-corrections.phtml`
- `install/config/structure.sql`
- `install/config/update-1.1.7-1.1.8.sql`
- `js/global.js`
- `languages/de_DE/default/document.php`
- `languages/en_US/default/document.php`
- `library/DEEC/Controller/DocumentAction.php`
- `library/DEEC/DocumentArchive.php`
- `library/DEEC/Pdf.php`
- `library/DEEC/Permission.php`
- `tests/documents/document-archive-integrity-db.php`
- `tests/documents/document-archive-integrity-db.py`
- `tests/documents/document-changes.md`
- `tests/documents/document-diagnostic-fixtures.php`
- `tests/documents/document-labels.php`
- `tests/documents/document-recovery-assessment.php`
- `tests/documents/document-review-db.php`
- `tests/documents/document-review.php`
- `tests/documents/document-revision-db-fixtures.php`
- `tests/documents/document-viewing-fixtures.php`
- `tests/documents/invoice-correction-db-fixtures.php`
- `tests/documents/invoice-correction-worker.php`
- `tools/document-recovery-assessment.php`
