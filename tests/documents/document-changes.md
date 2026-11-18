# Document revisions and corrections

## PDF markings

New draft PDFs display `MUSTER` in German and `SAMPLE` in English. Newly rendered historical reconstructions and diagnostic copies display `KOPIE` or `COPY`, including internal reconstructions. Every page uses bold dark-grey text in the existing top margin with a thin neutral border and no colored fill. Stored original PDFs retain their exact bytes. The historical viewing UI retains the explanation about reconstruction limits, current templates and missing historical evidence.

## Quotation and sales order revisions

The view offers **Angebot überarbeiten** or **Auftragsbestätigung überarbeiten**, then **Neue Fassung erstellen** with a mandatory reason. Creating a revision requires the existing document view, add and edit permissions and a valid session token. It copies the selected finalized, uncancelled document into a normal editable draft through the existing models and position/pricing helper. Position sets, child/master relationships and saved price rules are retained. Existing fields and AJAX autosave continue to operate on the new draft.

Each revision receives a new ordinary quotation/order number through the existing sequence at finalization. The PDF explicitly identifies its revision sequence and first document number. Original numbers are integer fields used by downstream documents; reusing them with suffixes would create ambiguous relationships. The `documentrevision` table records the first document, selected base, new draft, sequence, reason, creator and creation time. Approval uses the new document's existing completion time and user fields. The UI links the first document and every later version, including drafts. New revisions can be based on a selected earlier finalized version; the base remains explicit in history.

Only authorized administrators with document view/edit permissions may finalize a revision. The standard finalization button is hidden for other users. Revision finalization requires POST and a session token; an autosaved state change cannot approve the revision. Root-row locks serialize revision sequence allocation, request keys make creation retries idempotent, and the existing document lock and archive transaction serialize approval. Existing finalized PDFs, email attachments and downstream invoice/order references are never replaced or redirected. Sending a new version remains a separate explicit action through the existing email composer.

## Schema deployment

The official `install/config/update-1.1.7-1.1.8.sql` migration appends `CREATE TABLE IF NOT EXISTS documentrevision`. Fresh installation uses the same definition in `install/config/structure.sql`. No existing business columns or `documentarchive.version` semantics change.

An operator whose installation has already run the active migration must apply the newly appended table definition from that official migration through the normal backed-up deployment procedure. Do not rerun the entire active migration: earlier statements are not generally idempotent. Deploy the schema before enabling the new application code. No production SQL is executed during local development.

## Verification

`php tests/documents/document-labels.php` checks exact markings, neutral colors and preserved body text across three pages in both locales, and compares the fresh/migration table definitions. `python3 tests/documents/document-archive-integrity-db.py` uses a disposable database for quote/order revision creation, new numbering, edited positions, successive history, duplicate creation retries, failed PDF writes, concurrent approval and immutable original rows/bytes. It also runs the actual revision panel in Chromium at 375 and 1440 pixels in German and English and checks that the mandatory reason form does not trigger autosave.

The existing K1 recovery/viewing suite covers draft layouts with and without cover pages, multipage copies, historical diagnostics and protected original delivery. Existing K2, PDF, position and permission regressions remain applicable.
