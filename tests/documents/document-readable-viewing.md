# Readable historical viewing copies (renderer 2)

Normal download, preview and `reconstructed` requests use a readable stored-data PDF when the archived original is absent. `DEEC_Pdf::viewingCopy()` now renders an explicitly marked copy with saved document type/number/date, recipient, business text, ordered active line items, descriptions, quantities, unit prices and saved document net/tax/total. It uses the saved document language, falling back to the current display locale if absent. Every page is marked KOPIE/COPY. Saved cancellation state is visible, including state 106. Missing values are labeled rather than replaced with invented amounts, recipients or dates.

The reference patterns are tested synthetically as sales quotes ID 29 / number 28569 and ID 620 / number 28920. No production rows were read or modified. The immediate rendering defect was the extra viewing-only position classification check in `renderReconstruction()`: even when `validateReconstructionAmounts()` reproduced the saved header, a positive-price line with `total = 0` was rejected and `viewingCopy()` switched to the raw diagnostic renderer. The existing `Calculate::calculate()` derives values from saved prices, quantities, taxes and price rules without persisting changes. The separate `Calculate::direct()` operation persists gross position totals and header totals; viewing never invokes it. The historical event that originally left a zero position total is not established by this implementation.

The readable renderer shares the existing diagnostic amount analysis and `Calculate::calculate()` rather than implementing another financial calculator. Saved header totals are always displayed unchanged. Derived line net amounts are explicitly labeled as comparison values, including applicable valid saved price rules. Saved position amounts remain separately visible without asserting an unknown net/gross basis. A position inconsistent with both comparison values gets a plain-language warning. Reproducible headers with zero saved line totals therefore remain useful copies without pretending that the zero was a confirmed line price.

If saved headers differ from the comparison or are internally inconsistent, the saved figures remain prominent and a warning identifies the conflict; available calculated header figures are labeled separately. If currencies, prices, quantities, tax basis, hierarchy or saved price rules prevent a reliable comparison, derived amounts remain unavailable. Negative amounts and legitimate zero prices retain their values. No discrepancy is accepted as evidence of a historically correct original.

The PDF contains a whitelist of business fields. It does not render raw tables, JSON, internal IDs, tenant/user metadata, operator notes, SQL or debug messages. Stored rich text is rendered as escaped plain text with line breaks; embedded images/HTML are not executed or fetched. Current contacts, templates, logos and user master data do not fill missing stored information. This is a readable copy, not a reproduction of the old layout, issuer certification or dispatch evidence.

## Immutable copy versions

Legacy renderer-1 files remain at:

```
files/documents/.viewing-copies/<client>/<module>/<type>/<internal-id>/<snapshot-sha>/<locale>/<pdf-sha>.pdf
files/documents/.viewing-copies/<client>/<module>/<type>/<internal-id>/<snapshot-sha>/<locale>/copy.json
```

Renderer 2 uses a separate child directory:

```
files/documents/.viewing-copies/<client>/<module>/<type>/<internal-id>/<snapshot-sha>/<locale>/v2/<pdf-sha>.pdf
files/documents/.viewing-copies/<client>/<module>/<type>/<internal-id>/<snapshot-sha>/<locale>/v2/copy.json
```

New receipts bind `renderer_version: 2`, `status: historical_stored_viewing_copy`, identity, snapshot, locale, PDF size/hash and both explicit non-original markers. Directories stay 0700 and PDFs/receipts/locks 0600. The existing exclusive copy publication, locking and receipt integrity checks are retained. A corrupt/missing version-2 file or wrong renderer receipt fails closed; an older copy is not substituted to hide it.

Ordinary viewing opens/generates version 2 and does not serve a legacy diagnostic as its fallback. Existing legacy PDFs and receipts are neither overwritten nor removed, and remain private internal evidence. Existing version-2 PDFs/receipts are reused unchanged. The read-only archive audit inspects both current and retained legacy receipts and reports their renderer version; a retained legacy copy remains physical copy evidence rather than an original. Both layouts are recognized by the existing original-promotion guard. No canonical original path or archive registration changes.

K5 uses the same viewing service: a preview with only legacy copies reports that the preferred copy can be generated. Reviewed plans keep their document/scope/snapshot binding. Apply creates version 2 separately. Old resume evidence cannot skip new rendering because the current preferred file/hash is absent or different; subsequent resumes reuse matching new copies. Local and HTTP verification remain separate. The ordinary filename and download/inline preview behavior are unchanged.

After code delivery, the first normal authorized download/preview can create the new version. Operators may instead run the existing K5 preview/review/apply flow (including `--verification=local`) as the actual PHP account, choosing new private report outputs. Existing reports and old PDF directories must not be deleted to force regeneration. Production backfill was not performed during development. More disk space is needed because the old diagnostic copies are deliberately retained. No SQL migration or database repair is required.

## Internal diagnostics and unchanged release policy

`reconstructDiagnostic()` remains a CLI-only internal function requiring document view and edit permission. Separately published diagnostic review PDFs also require view and edit permission; view-only users still receive ordinary readable copies. Diagnostic data/proofs are retained in the existing internal workflow. Renderer 2 never invokes the raw diagnostic renderer for a normal PDF.

`validateReconstructionAmounts()`, original reconstruction approval, K3 selection/release rules, archive-registration restrictions and GoBD original export remain unchanged. Copies of either renderer version cannot be promoted into originals. Draft rendering and unrelated document functions are outside this change.

## Regression

Run `python3 tests/documents/document-recovery-db.py` against its disposable, network-disabled MariaDB and loopback HTTP fixtures. The new `document-readable-viewing-fixtures.php` covers both reference identities, matching and inconsistent headers, zero and negative lines, missing recipient/amount/date/currency values, cancelled documents, multipage copy marks, diagnostic authorization, normal download/preview equality, legacy PDF/receipt immutability, version receipt rejection, K5 old/new resume and unchanged database/archive data. Existing tests cover all eight types, German/English rendering, concurrent publication, authentication/tenant negatives, drafts, original preference, original promotion guards, local/HTTP K5 and GoBD.

`python3 tests/documents/document-archive-completeness.py` additionally checks that legacy receipts, snapshots and permission failures retain their audit semantics with the new cache version.

Validation on 19 November 2026: the full recovery suite passed 3263 checks, including 501 new readable-copy checks, 574 viewing/draft checks and 659 K5 checks, plus 20 German/English desktop/mobile browser checks. Archive completeness passed 254 checks. PDF (208), archive (129), review (80), migration configuration (79), permissions (415), file-access (430) and translation runtime (157) checks passed. All nine changed/new PHP files passed syntax checks; a synthetic user PDF was also inspected visually. No production execution, SQL migration or database repair was performed.

Further changes to the user-copy format require an explicit renderer version bump. Existing versioned PDFs and receipts must remain retained rather than being invalidated by source-file timestamps or overwritten.
