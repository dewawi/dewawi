#!/usr/bin/env python3
"""Run isolated translation/regression checks and emit reproducible JSON on stdout."""
import argparse
import json
from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
BASELINE_FAILURES = {'tests/security/notifications.php', 'tests/contacts/history-states.php'}
TESTS = ['tests/security/quote-email.php', 'tests/security/shop-email-content.php',
         'tests/security/contact-data.php', 'tests/security/form-input-validation.php',
         'tests/security/user-profile.php', 'tests/ui/buttons.php', 'tests/ui/toolbar-buttons.php',
         'tests/sales/quote-monitoring.php', 'tests/security/notifications.php', 'tests/contacts/history-states.php']
PHP_CODE = ['library/DEEC/Translate.php', 'library/DEEC/Pdf.php', 'library/DEEC/Feedback.php',
            'application/Bootstrap.php', 'application/plugins/Translate.php', 'application/views/helpers/Translate.php',
            'application/modules/users/controllers/UserController.php', 'application/controllers/helpers/Email.php',
            'application/modules/sales/services/DocumentPayloadService.php',
            'application/modules/sales/services/CreateDataFactory.php', 'application/modules/purchases/services/CreateDataFactory.php']


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--stage', choices=['T2A', 'T2B'], required=True)
    args = parser.parse_args()
    baseline = json.loads(subprocess.check_output(['git', 'show', 'c6325897:tests/translations/report.json'], cwd=ROOT))
    results = []
    lint_paths = sorted(set(PHP_CODE) | {str(p.relative_to(ROOT)) for p in (ROOT / 'languages').rglob('*.php')} |
                        {str(p.relative_to(ROOT)) for p in (ROOT / 'tests/translations').glob('*.php')})
    lint_errors = []
    for path in lint_paths:
        process = subprocess.run(['php', '-l', path], cwd=ROOT, capture_output=True, text=True)
        if process.returncode: lint_errors.append({'file': path, 'output': process.stdout + process.stderr})
    results.append({'command': 'php -l (catalogues, affected PHP and audit fixtures)', 'files': len(lint_paths), 'status': 'failed' if lint_errors else 'passed', 'errors': lint_errors})
    commands = [[sys.executable, '-B', 'tests/translations/audit-test.py'], ['php', 'tests/translations/runtime.php']]
    commands += [['php', path] for path in TESTS]
    for command in commands:
        process = subprocess.run(command, cwd=ROOT, capture_output=True, text=True)
        expected = command[-1] in BASELINE_FAILURES
        status = 'passed' if process.returncode == 0 else ('baseline_failure' if expected else 'failed')
        result = {'command': ' '.join(['python3' if c == sys.executable else c for c in command]), 'exit_code': process.returncode, 'status': status}
        if command[-1] == 'tests/translations/runtime.php' and process.returncode == 0:
            result['checks'] = json.loads(process.stdout)['checks']
        else:
            # unittest durations vary and are not evidence identifiers.
            result['stdout'] = process.stdout
            result['stderr'] = '\n'.join(line for line in process.stderr.splitlines() if not line.startswith('Ran ') and not line.startswith('----'))
        results.append(result)
    process = subprocess.run([sys.executable, 'tests/translations/audit.py'], cwd=ROOT, capture_output=True, text=True)
    report = json.loads(process.stdout)
    results.append({'command': 'python3 tests/translations/audit.py', 'exit_code': process.returncode, 'status': 'passed' if process.returncode == 0 else 'failed',
                    'static_model_matches': sum(c['matches'] for c in report['runtime'].get('static_model_comparisons', []))})
    repeat = subprocess.run([sys.executable, 'tests/translations/audit.py'], cwd=ROOT, capture_output=True, text=True)
    results.append({'command': 'repeat complete audit and compare JSON bytes', 'status': 'passed' if process.returncode == repeat.returncode == 0 and process.stdout == repeat.stdout else 'failed'})
    output = {'stage': args.stage, 'source_sha256': report['source_sha256'], 'baseline_summary': baseline['summary'], 'current_summary': report['summary'], 'commands': results,
              'database_changes': False, 'not_run': ['production access', 'customer data', 'authenticated browser', 'live SMTP', 'database integration/migrations'],
              'notes': ['Baseline failures are retained separately, never counted as passing checks.', 'Runtime PDF payload and mail template contexts use isolated fixtures.']}
    json.dump(output, sys.stdout, ensure_ascii=False, indent=2)
    print()
    return int(any(r['status'] == 'failed' for r in results))


if __name__ == '__main__':
    sys.exit(main())
