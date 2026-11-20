#!/usr/bin/env python3
"""Use the real public checkout in the temporary server created by shop-checkout-flow.py."""
import importlib.util
import json
from pathlib import Path
import sys

sys.dont_write_bytecode = True
origin = sys.argv[1]
if not origin.startswith('http://127.0.0.1:'):
    raise SystemExit('Local checkout fixture server required')
spec = importlib.util.spec_from_file_location('frontend', Path(__file__).with_name('frontend-assets-http.py'))
frontend = importlib.util.module_from_spec(spec)
spec.loader.exec_module(frontend)
frontend.load_assets()
results = []
for width, mode in [(1365, 'normal'), (768, 'normal'), (390, 'normal'), (320, 'normal'), (390, 'network'), (390, 'recovery-fail'), (390, 'rejected')]:
    result = frontend.measure(f'{origin}/_checkout-runner?width={width}&mode={mode}', max(800, width), 1000)
    results.append(result)
    if result['failures']:
        print(json.dumps(results, indent=2))
        raise AssertionError('; '.join(result['failures']))
print(json.dumps(results, indent=2))
print(f"PASS: {sum(result['checks'] for result in results)} Chromium checkout field/review, keyboard, desktop/mobile, duplicate-submit and recovery checks")
