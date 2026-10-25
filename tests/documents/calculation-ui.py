"""Run the document browser regressions using Chromium and a temporary profile."""
from html.parser import HTMLParser
from pathlib import Path
import subprocess
import tempfile


class ResultParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.in_result = False
        self.result = ''

    def handle_starttag(self, tag, attrs):
        if tag == 'pre' and dict(attrs).get('id') == 'result':
            self.in_result = True

    def handle_endtag(self, tag):
        if tag == 'pre':
            self.in_result = False

    def handle_data(self, data):
        if self.in_result:
            self.result += data


for name in ['notes-ui.html', 'calculation-ui.html']:
    fixture = Path(__file__).resolve().with_name(name)
    with tempfile.TemporaryDirectory(prefix='dewawi-calculation-ui-') as profile:
        result = subprocess.run([
            'chromium', '--headless', '--disable-gpu', '--disable-background-networking',
            '--no-first-run', '--no-default-browser-check', '--user-data-dir=' + profile,
            '--dump-dom', fixture.as_uri(),
        ], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=30)
    parser = ResultParser()
    parser.feed(result.stdout)
    print(name + ': ' + (parser.result or 'FAIL: no browser result'))
    if result.returncode or not parser.result.startswith('PASS:'):
        print(result.stderr)
        raise SystemExit(1)
