# Inquiry configuration and quote approval

The existing `shopinquiryform.fields` JSON retains its numbered or named steps. An optional top-level `_settings` object contains form-specific selection and quote settings. `quoteheader`, `quotefooter` and `quotetemplateid` continue to use their existing database columns. No schema migration is needed.

## Fields and dependencies

Select/radio options retain their existing HTML values: a list uses numeric keys, an associative object uses its explicit keys. A `depends` trigger may name an option key or a unique configured label. For example, the actual form 865 lists `Wasser/Glykol` at index 1; its label-based dependency is normalized to the submitted value `1`.

Dependencies may be nested or declared with `depends_on`/`depends_value`. They refer to fields in the same step. Missing parents, cycles, ambiguous label matches, invalid field definitions and duplicate/reserved names across steps fail configuration validation. Browser dependency handling scopes triggers to the form, toggles the complete wrapper and disables inactive controls. The server evaluates the same dependency chain and excludes inactive values, including defaults and forged values, from validation and business data.

`depends_value` also accepts a nonempty list of allowed values for the same parent, for example `"depends_on": "application", "depends_value": ["cooling", "climate"]`. This is a bounded membership condition, not a rule language. Nested field activity is evaluated against the complete element set before inactive elements are temporarily excluded from validation. A hidden ancestor disables every descendant, even when a stale descendant selection still matches its immediate parent.

### Basic and advanced presentation

Inquiry fields reuse DEEC_Form's `section`, `tab` and `col` metadata. The default tab stays visible; `"tab": "advanced"` renders that section inside a native, initially closed `details` element in the same form. Collapsing a section does not disable fields, remove their values, or change validation. Server errors open the affected section; browser invalid events reveal closed sections before focusing invalid inputs. All activity conditions still come exclusively from dependencies.

The existing AJAX navigation now includes a back button. Back navigation retains the current filtered draft, including incomplete input; it does not approve it. Moving forward through unchanged earlier steps preserves later drafts. Changing an earlier step discards subsequent steps and selection results as before. Every step is revalidated before persistence.

For optional numbers, `"attribs": {"min": "0", "step": "any", "data-min-exclusive": "0"}` requires a strictly positive value when supplied. The exclusive minimum is checked on the server and in browser validity; it is not applied to other numeric fields automatically. Temperature fields can retain `0`. Numeric comma inputs are normalized without the generic decimal filter's zero-to-null conversion. Nonfinite numeric input is rejected.

### Country sources and delivery addresses

Select fields may declare `"source": "country"` and `"options": {"": "Bitte Land wählen"}`. Every controller-created step loads options from `Shops_Model_DbTable_Country::getCountries()` for the current site tenant. Configured options cannot add country codes outside that source. Posted membership is validated on every step and again before persistence. No internal user/contact registry is required; no default country or tax approval follows from selecting a country. Other dynamic option sources are outside this change.

`country` (legacy alias `land`) continues to supply the billing country. If `lieferadresse_abweichend` is absent or `nein`, the shipping snapshot copies the submitted billing address, preserving legacy forms. With `ja`, the structured fields `lieferempfaenger`, `lieferansprechpartner` (optional), `lieferstrasse`, `lieferplz`, `lieferstadt` and `lieferland` populate the existing `shipping*` columns separately. The required structured fields must be complete; `unbekannt` cannot be turned into a finished quote. Supplemental `lieferadresse` free text remains a request for manual review, not an address parser. Matching an existing email never supplies private master address data. Different delivery tax contexts continue to require manual review.

Defaults must be explicit `default` values. A description promising 35 degrees does not supply an ambient-temperature input. Form 865 must be reviewed manually: make this calculation input required, or explicitly approve and configure a default and matching description. No production form configuration is modified by this change.

Suggestions render independently of step number. Form and field descriptions supply the product-specific guidance; the previous fixed chiller sidebar has been removed. The legacy calculation supports `calculate.required_field` for its demand field (otherwise the calculation field's name, with the previous `kuehlleistung` convention retained for older factor-field configurations) and `calculate.unit` for display. No new technical selection engine is implemented.

## Catalog suggestions

`_settings.selection.method: "catalog"` adds category/attribute suggestions to the existing controller, form and AJAX workflow. It always returns `eligible: false`, including when `automatic_quote` was accidentally set to true. It cannot number, finalize, archive or email an offer. The production configuration and local pilot of form 865 are not changed.

The following complete example uses **synthetic fixture category 40**, not a production mapping. Replace that ID and the attribute identifiers only after reviewing the actual shop data. The portable two-group examples are in `tests/security/fixtures/inquiry-catalog-config.php`.

```json
{
  "requirements": [
    {"name": "diameter", "label": "Mindestdurchmesser", "type": "number", "unit": "mm", "required": true, "attribs": {"step": "any"}},
    {"name": "material", "label": "Material", "type": "select", "required": true, "options": {"": "Bitte wählen", "0": "Stahl", "1": "Messing"}}
  ],
  "contact": [
    {"name": "itemid", "type": "hidden"},
    {"name": "sku", "label": "Gewünschte Artikelnummer", "type": "text"},
    {"name": "titel", "label": "Gewünschte Bezeichnung", "type": "text"},
    {"name": "email", "label": "E-Mail", "type": "email", "required": true},
    {"name": "privacy", "label": "Datenschutz bestätigt", "type": "checkbox", "required": true}
  ],
  "_settings": {
    "selection": {
      "method": "catalog",
      "automatic_quote": false,
      "step": "requirements",
      "item_field": "itemid",
      "categories": [40],
      "include_subcategories": false,
      "limit": 12,
      "filters": [
        {"field": "diameter", "attribute": "Diameter", "column": "description", "type": "number", "operator": "gte", "unit": "mm"},
        {"field": "material", "attribute": "Material", "column": "description", "type": "text", "operator": "eq", "value_map": {"0": "steel", "1": "brass"}},
        {"attribute": "Grade", "column": "value", "type": "text", "operator": "eq", "values": ["A", "B"]}
      ]
    }
  }
}
```

`step` identifies the submitted requirements step; suggestions appear in following steps. It defaults to the first step and must have a following step. Filter inputs may refer to fields in that step or preceding steps. Those submitted inputs are revalidated and inactive values excluded; future contact data and unknown posted properties are not selection inputs. Existing same-step dependencies apply, including inactive filters, which are explicitly marked `inactive` rather than satisfied. A missing/invalid active filter input prevents matches. No default technical values are supplied.

`item_field` names an unconditional, optional hidden field in a following step, without a preset item ID. Selection uses the item ID, preserving distinct records even with duplicate SKUs. The browser fills the existing optional `sku`/`titel` fields for convenience; on final submission the server repeats selection, validates the ID against current bounded candidates, and canonicalizes these fields. An inaccessible, stale, malformed or nonmatching selected ID returns an error before storing the inquiry. No selected ID is required to send a valid manual request, including when there are no matches. Selection can be cleared explicitly. Each rendered preview is refreshed from validated session inputs and current scoped data rather than retaining a product list across requests.

Each filter has a literal attribute `title`, an explicit source `column` (`value` or `description`), a `type` (`text` or `number`) and `operator`. Text supports exact, case-sensitive `eq`; numbers support `eq`, `gte` and `lte` without rounding or tolerance. Numbers accept decimal comma or point, preserve zero, and reject null/empty, nonfinite, decorated and ambiguous numeric strings. No SQL casts or formulas are used. A decimal format on an Inquiry number no longer converts an explicitly entered zero to null.

Use either `field` or a nonempty fixed `values` list. All filters combine with AND; `eq` accepts any member of its own list. A range comparison has exactly one fixed value. Optional `value_map` explicitly maps submitted field keys to stored attribute values; it never guesses meanings from translated option labels. Select/radio mapping keys must exist in the configured options. Lists/maps are limited to 20 values and a form to 10 filters. Unknown filter settings/operators/columns and invalid configuration fail validation.

Numeric filters require an explicit `unit`, including `""` for dimensionless data. A mapped field's unit and the attribute's trimmed `uom` must agree exactly; there is no W/kW, case, spelling or fluid conversion. Text filters default to dimensionless units. A literal attribute title must resolve to exactly one active attribute for the candidate's `parentid`; duplicate rows remain ambiguous even if their values agree. Optional `set_id` narrows to a reviewed attribute group (`0` means ungrouped). Attributes of deleted, foreign or wrong-parent groups cannot supply matches. No SKU-based attribute join or fallback to other columns is used.

The existing site-owned public category scope is retained. Every configured category must be an active shop category of the selected shop/tenant. Descendants are included only when requested and reached through active scoped categories. Candidate items must have `shopenabled=1`, a nonempty SKU and be undeleted. The selected SiteContext is authoritative even for a logged-in child user; internal `Client.modules` sharing metadata never widens public shop reads. An authorized parent-owned shop still uses its parent owner correctly. The `catalog` site feature is additionally required. No internal permissions, entitlements or public routes are expanded.

Results use fixed database ordering `sku ASC, id ASC`. The configurable candidate limit is 1–24 (default 12); rejected diagnostics are bounded separately by the same limit. At most 200 candidate articles and 5,000 relevant attribute rows are examined. An attribute overflow produces no partial matches. A truncated article/result list sets `has_more` and a visible refinement notice; it does not claim an exhaustive search. This initial bounded preview has no separate pagination workflow. Large catalogs should use narrower category/filter configurations; suitable SQL prefilters or paging need a separately reviewed extension. At most one scoped image reference per result is supplied and the existing SiteImage helper checks the published file before rendering.

### Selection result contract

The existing service returns these additive fields: `method`, `inputs`, `candidates`, `rejected`, `conditions`, `selected`, `technical`, `commercial`, `reasons`, `options` and `eligible`. Catalog results also include `item_field`, `limit`, `scanned`, `has_more` and diagnostic `summary` counts. Counts in `summary` represent failed conditions, not distinct products.

Catalog candidate/rejected records contain canonical `itemid`, `sku`, `title` and their condition outcomes. Conditions report the configured source, expected value(s), actual value/unit when unambiguous, and `matched`, `not_matched`, `missing_attribute`, `ambiguous_attribute`, `invalid_attribute`, `unit_mismatch` or `inactive`. Global input conditions can be `ready`, `missing_input` or `invalid_input`. Technical and commercial statuses remain `review_required`; an attribute match is never presented as complete suitability or pricing/tax approval.

Manual and legacy FORM_CALC calls retain their existing behavior and gate. Legacy controller suggestions now also carry IDs/SKUs/titles for the common result envelope. The legacy `eligible` value still permits only an attempt through the pre-existing quote creation/commercial/finalization checks; the open review status is not a new technical or tax certification. Catalog's gate is unconditionally false. Stored catalog metadata contains the chosen identity, open statuses, diagnostic summary and manual-review reasons, avoiding a large snapshot of every proposed/rejected record. Normal inquiry fields remain available to the existing internal workflow.

## Automatic quotes are opt-in

Without explicit approval, inquiries are saved for manual processing and no automatic quote is created or sent. A representative settings object is:

```json
"_settings": {
  "selection": {
    "method": "form_calc",
    "automatic_quote": true,
    "accepted_values": {
      "einsatzbereich": ["0"],
      "medium": ["0"],
      "pumpe": ["1"],
      "geraeuschreduktion": ["0"]
    }
  },
  "quote": {
    "tax_country": "DE",
    "pdf": {
      "cover": true,
      "attributes": true,
      "options": true,
      "images": true,
      "positionimages": true,
      "prices": true,
      "discounts": false
    }
  }
}
```

This is a configuration example, not a release of form 865 or a manufacturer approval. Accepted values are submitted keys, not translated labels. They declare reviewed baseline conditions; unknown additional requirements, nonempty customer instruction textareas, missing selection results, insufficient unrounded capacity and uncertain commercial data require manual review. The configured `tax_country` must be an approved domestic tax context and match an explicit submitted `country`/`land`; this mechanism is not a tax engine. The supplied form 865 has no country field, so it cannot meet this automatic-quote condition without a separately reviewed configuration change.

`method: "manual"` and unknown methods never automatically offer a product. `form_calc` remains the bounded legacy adapter: glycol and other unverified media cannot be approved through `accepted_values`. Actual form 865 has no selection approval settings or verified option mappings and therefore remains manual. Unknown/missing item prices, a currency differing from the primary quote currency, special option prices indicating unavailable/on-request, and unverified tax-free/ambiguous contact matches cannot be finalized automatically.

An additional option field can map submitted values to existing item options:

```json
"option_fields": {
  "accessory": {
    "none": null,
    "selected": {"itemoptid": 123, "selection_verified": true}
  }
}
```

`123` is only a placeholder. The mapping must be reviewed for the actual selected product/configuration, including technical effects; a known price alone is insufficient. The server verifies ownership, parent product, availability, currency and price. Affirmative pump/noise requests need a verified mapping and cannot be approved merely through accepted values or a null option mapping. `selection_verified` comes exclusively from trusted form configuration, never from customer data. There are no verified pump, noise or fluid corrections supplied with this change.

## Stored quotes and PDF output

Paid and free selected additions are stored as normal quote child positions and calculated by the existing Calculate helper. Options already included in a saved variant are snapshotted as included equipment without adding their surcharge again. Unselected catalog options are never loaded into the public quote payload.

PDF switches are stored in the existing quote `pdfshow*` columns. New approved inquiry quotes default to the normal Sales quote presentation with cover, attributes and images. Boolean form-specific switches can disable these sections; selected purchased positions remain part of the quote. The shared Sales attribute grouping and PDF defaults are reused, with public site-scoped models for guest data. The renderer uses the scoped product payload instead of constructing an internal item model for the cover description. Public PDFs use submitted address snapshots and suppress internal customer numbers; internal Sales document behavior is retained.

Long document terms render below totals using the available page width and normal page breaks. The cover table of contents records actual generated sections and pages, not placeholder pages. Customer replays cannot finalize a manually linked draft without its persisted automatic approval. Finalized originals remain read-only: retrieval returns the existing canonical archive, including after product/configuration changes.

`_selection` stores the review outcome in inquiry JSON; `_quote` stores included variant equipment. These are server-controlled metadata, not additional database columns or customer-confirmed technical specifications. The internal inquiry view shows manual-review reasons. Existing processing states remain unchanged.

## Regression checks

- `php tests/security/inquiry-dynamic-form.php`
- `python3 tests/ui/inquiry-dependencies.py` (isolated Chromium, mobile and desktop)
- `php tests/security/inquiry-pilot.php` (synthetic forms plus the local pilot when available)
- `python3 tests/ui/inquiry-pilot.py` (actual field rendering, collapsed sections, browser validity and AJAX navigation)
- `php tests/security/inquiry-selection.php`
- `python3 tests/security/shop-inquiry-quote.py` (isolated MariaDB, actual PDFs and stubbed notifications)
- The same isolated runner includes `tests/security/inquiry-catalog.php`, two catalog HTTP flows and `tests/ui/inquiry-catalog.py` at mobile/desktop widths.
- Existing inquiry, contact workflow, calculation, PDF, archive, permission and email-fixture suites.

The local references `/tmp/dewawi-inquiry-referenzen/form-865.fields.json` and `Angebot-50635-anonymisiert.pdf` are not committed. When available, the form reference is read by additional regression assertions. The PDF reference is inspected as a read-only layout reference, never regenerated or edited. Database fixtures, images and new PDFs are synthetic and isolated under the test runner's temporary root. Optional `DEWAWI_INQUIRY_TEST_ARTIFACT_DIR=/tmp/dewawi-inquiry-pdf-review` keeps synthetic PDFs for visual inspection; no real email transport is used.

The revised pilot artifacts stay outside Git under `/tmp/dewawi-inquiry-pilot-865-2026-11-13/`: `form-865.pilot.fields.json`, the field CSV, proposed title/description in `form-865.metadata.json`, and local validation/handoff notes. They are not installed into any database. They retain the original numeric option meanings, use nested glycol dependencies and application-specific power fields, and remain `manual` / `automatic_quote: false`. The pilot now has 69 fields (including five structured delivery additions); this count is documentation, not an application rule. Unspecified technical data remain unspecified.

The database runner additionally serves the actual Inquiry controller and views over isolated loopback HTTP, with real cookies/session navigation and the temporary MariaDB. It checks foreign tenant/shop forms and countries, CSRF, skipped steps, back navigation, comma/zero values, structured delivery, manual persistence and replay. Only site chrome, submission throttling and notification transport are stubbed; no real emails are sent. The router accepts only the test socket prefix and never loads application database credentials. A portable synthetic configuration is committed under `tests/security/fixtures`; the private reference files are not required to run the suites.
