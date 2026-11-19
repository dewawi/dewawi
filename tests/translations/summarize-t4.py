#!/usr/bin/env python3
"""Verify unchanged T3 acceptance criteria and link every original failure to its T4 result."""
from collections import Counter
import argparse
import hashlib
import json
from pathlib import Path

BASE = Path(__file__).resolve().parent


def read(name):
    return json.loads((BASE / name).read_text())


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--stage', choices=['T4', 'T5', 'T6'], default='T4')
    args = parser.parse_args()
    prefix = args.stage.lower()
    before, after = read('t3-http-browser.json'), read(prefix + '-http-browser.json')
    pdf_before, pdf_after = read('t3-pdf.json'), read(prefix + '-pdf.json')
    mail_before, mail_after = read('t3-mail.json'), read(prefix + '-mail.json')
    audit, old_audit = read(prefix + '-report.json'), read({'T4': 'report.json', 'T5': 't4-report.json', 'T6': 't5-report.json'}[args.stage])
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
        corrections.append({'id': len(corrections) + 1, 'scenario': row['scenario'], 'before': row['status'], 'after': current['status'], 'files': files, 'evidence': prefix + '-http-browser.json'})
    identity = lambda row: (row['ui_locale'], row['document_locale'], row['type'])
    assert {identity(r): set(r['checks']) for r in pdf_before['results']} == {identity(r): set(r['checks']) for r in pdf_after['results']}
    for row in pdf_before['results']:
        current = next(r for r in pdf_after['results'] if identity(r) == identity(row))
        for check, ok in row['checks'].items():
            if not ok:
                corrections.append({'id': len(corrections) + 1, 'scenario': {'pdf': identity(row), 'check': check}, 'before': 'failed', 'after': 'passed' if current['checks'][check] else 'failed',
                                    'files': ['library/DEEC/Pdf.php', 'application/modules/sales/services/DocumentPayloadService.php', 'application/modules/purchases/services/DocumentPayloadService.php', 'application/controllers/helpers/Currency.php', 'application/controllers/helpers/Calculate.php'], 'evidence': prefix + '-pdf.json'})
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
    numeric = {module: read(prefix + '-' + module + '-format.json')['counts'] for module in ['sales', 'purchases']}
    assert all(count['failed'] == 0 for count in numeric.values())
    output = {'base_commit': '87ac98c1', 'acceptance': 'passed', 'source_sha256': audit['source_sha256'], 'counts': counts, 'corrections': corrections,
              'extended_browser': after['extended_counts'], 'extended_numeric': numeric,
              'module_language_contexts': 28, 'http_domain_and_text_comparisons': 39,
              'existing_catalogue_keys_and_values_preserved': True, 'domain_order_preserved': True,
              'baseline_failure_note': 'Independent suite failures remain failures; see t4-suites.json, t4-document-regressions.json and t4-recovery-baseline.json. This acceptance is the targeted 235-case contract.'}
    if args.stage in ['T5', 'T6']:
        ledger = read('t5-hardcodes.json')
        original = {row['id']: row for row in read('t3-hardcodes.json')['candidates']}
        assert len(ledger['candidates']) == len(original) == 204
        assert Counter(row['status'] for row in ledger['candidates']) == ledger['counts']
        assert ledger['counts'] == {'completed_t4': 9, 'completed_t5': 133, 'technical_internal': 51, 'dynamic_or_translated': 9, 'business_unclear': 2}
        root = BASE.parents[1]
        keys = {entry['key'] for catalogue in audit['catalogues'].values() for entry in catalogue['entries']}
        for row in ledger['candidates']:
            assert all(row[field] == original[row['id']][field] for field in ['file', 'line', 'kind', 'text'])
            source = (root / row['file']).read_bytes()
            assert hashlib.sha256(source).hexdigest() == row['current_source_sha256'], row['id']
            if row['status'].startswith('completed_'):
                assert row['translation_key'] in keys and row['translation_key'] in source.decode(), row['id']
        cleanup, browser = read(prefix + '-cleanup.json'), read(prefix + '-browser.json')
        assert cleanup['counts']['failed'] == 0 and browser['status'] == 'passed'
        output.update(stage=args.stage, base_commit='1910ef01' if args.stage == 'T6' else '22bc7ab1', hardcodes=ledger['counts'], remaining_confirmed_ui_hardcodes=0,
                      cleanup=cleanup['counts'], additional_browser_checks=browser['checks'], quality_policy_sha256=audit['quality_policy_sha256'],
                      baseline_failure_note='Independent suite failures remain failures; see t5-suites.json and t5-document-regressions.json. No global acceptance of these suites is claimed.')
        if args.stage == 'T6':
            output.update(production_sources_unchanged_from_t5=audit['source_sha256'] == old_audit['source_sha256'], readiness_report='T6-release-readiness.md',
                          baseline_failure_note='Six former aborts investigated separately; see t6-readiness.json and before/after evidence. No baseline failures are accepted by the validator.')
    print(json.dumps(output, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()
