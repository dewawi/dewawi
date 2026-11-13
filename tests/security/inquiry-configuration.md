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
- Existing inquiry, contact workflow, calculation, PDF, archive, permission and email-fixture suites.

The local references `/tmp/dewawi-inquiry-referenzen/form-865.fields.json` and `Angebot-50635-anonymisiert.pdf` are not committed. When available, the form reference is read by additional regression assertions. The PDF reference is inspected as a read-only layout reference, never regenerated or edited. Database fixtures, images and new PDFs are synthetic and isolated under the test runner's temporary root. Optional `DEWAWI_INQUIRY_TEST_ARTIFACT_DIR=/tmp/dewawi-inquiry-pdf-review` keeps synthetic PDFs for visual inspection; no real email transport is used.

The revised pilot artifacts stay outside Git under `/tmp/dewawi-inquiry-pilot-865-2026-11-13/`: `form-865.pilot.fields.json`, the field CSV, proposed title/description in `form-865.metadata.json`, and local validation/handoff notes. They are not installed into any database. They retain the original numeric option meanings, use nested glycol dependencies and application-specific power fields, and remain `manual` / `automatic_quote: false`. The pilot now has 69 fields (including five structured delivery additions); this count is documentation, not an application rule. Unspecified technical data remain unspecified.

The database runner additionally serves the actual Inquiry controller and views over isolated loopback HTTP, with real cookies/session navigation and the temporary MariaDB. It checks foreign tenant/shop forms and countries, CSRF, skipped steps, back navigation, comma/zero values, structured delivery, manual persistence and replay. Only site chrome, submission throttling and notification transport are stubbed; no real emails are sent. The router accepts only the test socket prefix and never loads application database credentials. A portable synthetic configuration is committed under `tests/security/fixtures`; the private reference files are not required to run the suites.
