"""Chromium checks against the disposable C4B checkout HTTP fixture."""
import importlib.util
import json
from pathlib import Path
import sys

sys.dont_write_bytecode = True
origin = sys.argv[1]
if not origin.startswith('http://127.0.0.1:'):
    raise SystemExit('Local checkout fixture required')
spec = importlib.util.spec_from_file_location('frontend', Path(__file__).with_name('frontend-assets-http.py'))
frontend = importlib.util.module_from_spec(spec)
spec.loader.exec_module(frontend)
frontend.load_assets()
results = []
for width, lang, mode in [(1280, 'de', 'order'), (390, 'de', 'order'), (320, 'en', 'order'), (390, 'de', 'quote'), (320, 'en', 'quote')]:
    result = frontend.measure(f'{origin}/_shipping-runner?width={width}&lang={lang}&mode={mode}', max(800, width), 1000)
    results.append(result)
    if result['failures']:
        print(json.dumps(results, indent=2))
        raise AssertionError('; '.join(result['failures']))
print(f"PASS: {sum(result['checks'] for result in results)} Chromium shipping delivery, collection, unavailable country, inquiry, DE/EN and responsive checks")
