#!/usr/bin/env python3
"""Repeat existing isolated suites; retain failures as evidence without accepting them as passes."""
import json
from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
COMMANDS = [
    ['python3', '-B', 'tests/translations/validate.py', '--stage', 'T2B', '--browser'],
    ['python3', '-B', 'tests/security/session-http.py'],
    ['python3', '-B', 'tests/security/tenant-access-http.py'],
    ['python3', '-B', 'tests/security/error-handling-http.py'],
    ['python3', '-B', 'tests/security/shop-inquiry-quote.py'],
    ['python3', '-B', 'tests/security/shop-checkout-db.py'],
    ['python3', '-B', 'tests/calendar/run.py'],
    ['python3', '-B', 'tests/ui/button-page-integration.py', '--dashboard', '--output', '/tmp/dewawi-t3-dashboard'],
]


def main():
    results = []
    for command in COMMANDS:
        print('Running ' + ' '.join(command), file=sys.stderr, flush=True)
        process = subprocess.run(command, cwd=ROOT, capture_output=True, text=True)
        result = {'command': command, 'exit_code': process.returncode,
                  'status': 'passed' if process.returncode == 0 else 'failed',
                  'stdout': process.stdout, 'stderr': process.stderr}
        if command[2].endswith('validate.py') and process.returncode == 0:
            result['validation'] = json.loads(result.pop('stdout'))
        results.append(result)
        print(result['status'], file=sys.stderr, flush=True)
    json.dump({'scope': 'isolated existing fixtures only; no production configuration', 'commands': results}, sys.stdout, indent=2, ensure_ascii=False)
    print()
    return int(any(item['status'] == 'failed' for item in results))


if __name__ == '__main__':
    sys.exit(main())
