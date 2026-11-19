"""Reproducible source inventory; counts source sites, never runtime button instances."""
from collections import Counter
from pathlib import Path
import argparse
import hashlib
import json
import re
import subprocess

root = Path(__file__).resolve().parents[2]
roots = ['application', 'library/DEEC', 'js', 'css', 'templates', 'install']
suffixes = {'.php', '.phtml', '.js', '.css', '.html', '.sql', '.json'}
vendor_files = {'js/simpletreemenu.js'}
lexer = re.compile(r'/\*.*?\*/|//[^\n]*|\#[^\n]*|\'(?:\\.|[^\'\\])*\'|"(?:\\.|[^"\\])*"|->|=>|[A-Za-z_][A-Za-z_0-9]*|[^\s]', re.S)

def literal(token):
    if len(token) >= 2 and token[0] in "\'\"" and token[-1] == token[0]:
        return token[1:-1]
    return None

def inventory():
    paths = subprocess.check_output(['rg', '--files', *roots], cwd=root, text=True).splitlines()
    records = []
    files = []
    for path in sorted(paths):
        if Path(path).suffix not in suffixes or path.startswith('install/css/blueprint/') or path in vendor_files:
            continue
        files.append(path)
        source = (root / path).read_text()
        def add(kind, offset, **details):
            records.append({'kind': kind, 'file': path, 'line': source.count('\n', 0, offset) + 1, **details})
        if Path(path).suffix in {'.php', '.phtml'}:
            tokens = [(m.group(), m.start()) for m in lexer.finditer(source) if not m.group().startswith(('//', '/*', '#'))]
            values = [t[0] for t in tokens]
            for i in range(len(tokens) - 3):
                if values[i:i+3] == ['->', 'addElement', '(']:
                    if values[i+3] != '[':
                        add('add-element-other-argument', tokens[i][1])
                        continue
                    depth, config = 1, {}
                    for j in range(i+4, len(tokens)):
                        value = values[j]
                        if value == '[':
                            depth += 1
                        elif value == ']':
                            depth -= 1
                            if depth == 0:
                                break
                        if depth == 1 and j + 2 < len(tokens) and literal(value) is not None and values[j+1] == '=>':
                            config[literal(value)] = literal(values[j+2])
                    if config.get('type') in {'button', 'submit', 'reset'}:
                        add('form-config', tokens[i][1], type=config['type'], name=config.get('name'), label=config.get('label'))
                if values[i] == 'new' and values[i+1] in {'Zend_Form_Element_Submit', 'Zend_Form_Element_Button', 'Zend_Form_Element_Reset'}:
                    add('zf1-control', tokens[i][1], constructor=values[i+1])
                if values[i:i+3] == ['->', 'Button', '(']:
                    add('legacy-button-helper', tokens[i][1])
        for match in re.finditer(r'<button\b', source, re.I):
            add('html-button-lexical', match.start())
        for match in re.finditer(r'<a\b[^>]*\bclass\s*=\s*[\'\"]([^\'\"]*)', source, re.I):
            if re.search(r'\b(?:dw-btn|dw-button|btn|button)(?:\b|-)', match[1]):
                add('html-button-link-lexical', match.start())
        for match in re.finditer(r'<input\b[^>]*\btype\s*=\s*[\'\"](?:button|submit|reset|image)[\'\"]', source, re.I):
            add('html-input-control-lexical', match.start())
    toolbar_forms = sorted(p for p in files if '/forms/' in p and 'Toolbar' in Path(p).name)
    toolbar_helpers = sorted(p for p in files if '/views/helpers/' in p and 'Toolbar' in Path(p).name)
    svg_sources = sorted(p for p in files if p.endswith('.php') and re.search(r"['\"]iconFormat['\"]\s*=>\s*['\"]svg['\"]", (root / p).read_text()))
    lists = sorted(p for p in files if '/models/List/' in p)
    action_lists = [p for p in lists if re.search(r"['\"]type['\"]\s*=>\s*['\"]actions['\"]", (root / p).read_text())]
    assets = sorted((root / 'images').glob('*'))
    by_hash = {}
    for asset in assets:
        if asset.is_file():
            by_hash.setdefault(hashlib.sha256(asset.read_bytes()).hexdigest(), []).append(str(asset.relative_to(root)))
    return {
        'method': 'Own roots only; literal top-level PHP array configs with comments/strings tokenized; HTML counts lexical including comments/rewrite strings. Other addElement arguments are unresolved calls, not known buttons. No runtime/DB/tenant count.',
        'roots': roots, 'excluded_vendor_files': sorted(vendor_files), 'source_files': len(files),
        'counts': dict(sorted(Counter(r['kind'] for r in records).items())),
        'form_types': dict(Counter(r['type'] for r in records if r['kind'] == 'form-config')),
        'toolbar_forms': toolbar_forms, 'toolbar_helpers': toolbar_helpers,
        'svg_sources': svg_sources, 'action_list_models': action_lists, 'other_list_models': [p for p in lists if p not in action_lists],
        'svg_form_sources': [p for p in svg_sources if '/forms/' in p],
        'svg_helper_sources': [p for p in svg_sources if '/views/helpers/' in p],
        'identical_root_assets': [v for v in by_hash.values() if len(v) > 1],
        'sites': records,
    }

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--json', action='store_true', help='Include every source site and path list')
    args = parser.parse_args()
    result = inventory()
    if not args.json:
        for key in ['toolbar_forms', 'toolbar_helpers', 'svg_sources', 'svg_form_sources', 'svg_helper_sources', 'action_list_models', 'other_list_models']:
            result[key] = len(result[key])
        result.pop('sites')
    print(json.dumps(result, ensure_ascii=False, indent=2))
