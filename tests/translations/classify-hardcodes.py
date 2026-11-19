#!/usr/bin/env python3
"""Export T3's reviewed classification of every T2 candidate, without editing product sources."""
from collections import Counter
import hashlib
import json
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[2]
REPORT = ROOT / 'tests/translations/report.json'
CANDIDATES_SHA256 = '177e4789a0ef67e560c18c959ee5b50111fb5f3ec47f17cdc5e8f6025d84aaec'
CATEGORIES = {
    'visible_translatable': 'tatsächlich sichtbarer, übersetzbarer Text',
    'technical_internal': 'technischer oder interner String',
    'dynamic_or_translated': 'dynamisch beziehungsweise bereits übersetzt',
    'business_unclear': 'fachlich unklar',
}
# These decisions refer to the reviewed, fixed T2 list, not arbitrary future scan results.
SPECIAL = {
    2: ('technical_internal', 'Version identifier; Version is usable in both locales. Do not translate the release number.'),
    3: ('technical_internal', 'Inline JavaScript function call misread as HTML text; no literal UI wording.'),
    4: ('business_unclear', 'Persisted initial template name. Decide whether seed business names should follow tenant language; never rename existing records.'),
    5: ('business_unclear', 'Persisted initial warehouse name. Tenant/business naming policy required; never rename existing records.'),
    7: ('dynamic_or_translated', 'Intentional German language endonym in invitation-language selector.'),
    8: ('dynamic_or_translated', 'Intentional English language endonym in invitation-language selector.'),
    11: ('technical_internal', 'Geoapify service/attribution brand, not a sentence to translate.'),
    12: ('technical_internal', 'Rendered diagnostic enum and eligibility flag; preserve machine identifiers.'),
    13: ('technical_internal', 'Rendered diagnostic enum and eligibility flag; preserve machine identifiers.'),
    19: ('technical_internal', 'SQL expression inside model code; scanner false positive.'),
    53: ('dynamic_or_translated', 'Top plus computed row limit; language-neutral ranking title, not untranslated German prose.'),
    67: ('dynamic_or_translated', 'E-Mail accessible link label already understandable in DE/EN; optional English house-style Email is editorial.'),
    72: ('technical_internal', 'Developer-only exception diagnostics guarded by APPLICATION_ENV=development; not public production error text.'),
    73: ('technical_internal', 'Developer-only exception diagnostics guarded by APPLICATION_ENV=development.'),
    74: ('technical_internal', 'Developer-only stack-trace heading guarded by APPLICATION_ENV=development.'),
    114: ('technical_internal', 'DEWAWI Open Source product/brand name in installer footer.'),
    151: ('dynamic_or_translated', 'JavaScript interpolates contact name/email; no fixed wording. Personal data must not be translated.'),
    152: ('technical_internal', 'OpenStreetMap attribution/brand; preserve attribution.'),
    153: ('dynamic_or_translated', 'Auto-save interpolates response display/option text; inspect upstream translations, not this concatenation.'),
    154: ('dynamic_or_translated', 'Escaped document validation title supplied by server; no literal text at this location.'),
    155: ('dynamic_or_translated', 'Escaped validation issue supplied by server; upstream message codes still require API/browser coverage.'),
    156: ('technical_internal', 'HTTP status comparison parsed as HTML text; scanner false positive.'),
    203: ('dynamic_or_translated', 'E-Mail accessible mailto label already usable in DE/EN; optional editorial normalization only.'),
    204: ('technical_internal', 'PHPDoc usage example for form API, never rendered by this source location.'),
}
for identifier in range(167, 203):
    SPECIAL[identifier] = ('technical_internal', 'GoBD export data-dictionary description/schema metadata, not ERP UI. Localized export specifications require a separate compatibility decision.')


def main():
    source = REPORT.read_bytes()
    report = json.loads(source)
    candidates = report['hardcoded_candidates']
    fingerprint = hashlib.sha256(json.dumps(candidates, sort_keys=True, separators=(',', ':'), ensure_ascii=False).encode()).hexdigest()
    if len(candidates) != 204 or fingerprint != CANDIDATES_SHA256:
        raise RuntimeError('T2 candidate list changed; review classification before regenerating')
    rows = []
    for identifier, candidate in enumerate(candidates, 1):
        category, reason = SPECIAL.get(identifier, ('visible_translatable', 'Literal user-facing text/label/accessible name; route through existing translator in a separately scoped correction.'))
        if identifier not in SPECIAL and candidate['file'].startswith('install/templates/'):
            reason = 'Visible standalone installer text. Installer runs before application translation bootstrap; requires a separate installer language decision, not a blanket replacement.'
        if identifier in [15, 16]:
            reason = 'Literal document-preview page title passed to headTitle; actual generated PDF metadata/content needs separate document-locale validation.'
        path = ROOT / candidate['file']
        if hashlib.sha256(path.read_bytes()).hexdigest() != report['scan']['source_hashes'][candidate['file']]:
            raise RuntimeError('Reviewed source changed; reassess candidate: ' + candidate['file'])
        lines = path.read_text().splitlines()
        line = candidate['line']
        rows.append({'id': identifier, **candidate, 'classification': category,
                     'review_certainty': 'source_confirmed', 'rationale': reason,
                     'source_sha256': hashlib.sha256(path.read_bytes()).hexdigest(),
                     'context_start': max(1, line - 2), 'context': lines[max(0, line - 3):line + 2],
                     'priority': ('P3' if candidate['file'].startswith('install/') else 'P2') if category == 'visible_translatable' else 'review_only'})
    output = {'scope': 'All 204 T2 candidates individually source-reviewed; not a complete inventory of remaining UI hardcodes',
              'report_sha256': hashlib.sha256(source).hexdigest(), 'source_sha256': report['source_sha256'],
              'categories': CATEGORIES, 'counts': dict(Counter(row['classification'] for row in rows)), 'candidates': rows}
    json.dump(output, sys.stdout, indent=2, ensure_ascii=False)
    print()


if __name__ == '__main__':
    main()
