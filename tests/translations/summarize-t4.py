#!/usr/bin/env python3
"""Verify unchanged T3 acceptance criteria and link every original failure to its T4 result."""
from collections import Counter
import json
from pathlib import Path

BASE = Path(__file__).resolve().parent


def read(name):
    return json.loads((BASE / name).read_text())


def main():
    before, after = read('t3-http-browser.json'), read('t4-http-browser.json')
    pdf_before, pdf_after = read('t3-pdf.json'), read('t4-pdf.json')
    mail_before, mail_after = read('t3-mail.json'), read('t4-mail.json')
    audit, old_audit = read('t4-report.json'), read('report.json')
    assert Counter(r['scenario'] for r in before['results']) == Counter(r['scenario'] for r in after['results'])
    paths = {
        'login HTML language de_DE': ['application/modules/users/views/scripts/user/login.phtml'],
        'failed login message en_US': ['application/modules/users/controllers/UserController.php', 'languages/en_US/users/user.php'],
        'English admin descriptions': ['application/modules/admin/views/scripts/index/index.phtml', 'languages/en_US/admin/admin.php'],
        'English password dialog trigger': ['application/modules/users/views/scripts/user/edit.phtml'],
        'public contact wording error en_US': ['application/modules/shops/views/scripts/contact/error.phtml', 'languages/en_US/shops/shops.php'],
        'browser actual language selector reload': ['js/global.js'],
    }
    corrections = []
    for row in before['results']:
        if row['status'] != 'failed':
            continue
        current = next(r for r in after['results'] if r['scenario'] == row['scenario'])
        files = paths.get(row['scenario'], ['application/views/helpers/Breadcrumbs.php'])
        if row['scenario'].startswith('browser module ebay'):
            files += ['application/modules/ebay/forms/Listing.php']
        corrections.append({'id': len(corrections) + 1, 'scenario': row['scenario'], 'before': row['status'], 'after': current['status'], 'files': files, 'evidence': 't4-http-browser.json'})
    identity = lambda row: (row['ui_locale'], row['document_locale'], row['type'])
    assert {identity(r): set(r['checks']) for r in pdf_before['results']} == {identity(r): set(r['checks']) for r in pdf_after['results']}
    for row in pdf_before['results']:
        current = next(r for r in pdf_after['results'] if identity(r) == identity(row))
        for check, ok in row['checks'].items():
            if not ok:
                corrections.append({'id': len(corrections) + 1, 'scenario': {'pdf': identity(row), 'check': check}, 'before': 'failed', 'after': 'passed' if current['checks'][check] else 'failed',
                                    'files': ['library/DEEC/Pdf.php', 'application/modules/sales/services/DocumentPayloadService.php', 'application/modules/purchases/services/DocumentPayloadService.php', 'application/controllers/helpers/Currency.php', 'application/controllers/helpers/Calculate.php'], 'evidence': 't4-pdf.json'})
    assert {r['locale']: set(r['checks']) for r in mail_before['results']} == {r['locale']: set(r['checks']) for r in mail_after['results']}
    counts = {status: sum(result['counts'].get(status, 0) for result in [after, pdf_after, mail_after]) for status in ['passed', 'failed']}
    assert counts == {'passed': 235, 'failed': 0}
    assert len(corrections) == 18 and all(r['after'] == 'passed' for r in corrections)
    assert all(r['status'] == 'passed' for r in after['extended_results'])
    assert all(item['matches'] for item in audit['runtime']['static_model_comparisons']) and len(audit['runtime']['static_model_comparisons']) == 28
    assert not any(audit['defect_counts'].values())
    for path, catalogue in old_audit['catalogues'].items():
        old = {e['key']: e['value'] for e in catalogue['entries']}
        new = {e['key']: e['value'] for e in audit['catalogues'][path]['entries']}
        assert old.items() <= new.items(), path
    old_domains = {(r['locale'], r['module']): r['domains'] for r in old_audit['runtime']['observations']['request_contexts']}
    assert all(r['domains'] == old_domains[r['locale'], r['module']] for r in audit['runtime']['observations']['request_contexts'])
    numeric = {module: read('t4-' + module + '-format.json')['counts'] for module in ['sales', 'purchases']}
    assert all(count['failed'] == 0 for count in numeric.values())
    output = {'base_commit': '87ac98c1', 'acceptance': 'passed', 'source_sha256': audit['source_sha256'], 'counts': counts, 'corrections': corrections,
              'extended_browser': after['extended_counts'], 'extended_numeric': numeric,
              'module_language_contexts': 28, 'http_domain_and_text_comparisons': 39,
              'existing_catalogue_keys_and_values_preserved': True, 'domain_order_preserved': True,
              'baseline_failure_note': 'Independent suite failures remain failures; see t4-suites.json, t4-document-regressions.json and t4-recovery-baseline.json. This acceptance is the targeted 235-case contract.'}
    print(json.dumps(output, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()
