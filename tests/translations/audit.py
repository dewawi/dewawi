#!/usr/bin/env python3
"""Read-only translation audit. JSON goes to stdout; no database, network or app bootstrap."""

import argparse
from collections import Counter, defaultdict
import hashlib
import html
import json
from pathlib import Path
import re
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
KEY = re.compile(r"^[A-Z][A-Z0-9_]*(?:%[0-9$]*[sdf][A-Z0-9_]*)*$")
QUOTED = re.compile(r"(['\"])((?:\\.|(?!\1)[^\\\n])*?)\1")
FORMAT = re.compile(r"%(?:(\d+)\$)?[-+ 0]*(?:'[^\n])?(?:\d+|\*)?(?:\.(?:\d+|\*))?([bcdeEfFgGhHosuxXd])")
SUFFIXES = {'.php', '.phtml', '.js', '.json', '.ini', '.xml', '.sql', '.html', '.yaml', '.yml'}


def php_inspect(mode, paths):
    result = subprocess.run(['php', str(ROOT / 'tests/translations/inspect.php')],
                            input=json.dumps({'mode': mode, 'files': [str(p) for p in paths]}),
                            capture_output=True, text=True, check=True)
    return {Path(path).relative_to(ROOT).as_posix(): value for path, value in json.loads(result.stdout).items()}


def placeholders(value):
    """Compare argument position/type, allowing positional reordering and escaped percent."""
    signature = []; invalid = []; unmatched = []; sequential = 1; offset = 0
    while offset < len(value):
        if value[offset] != '%':
            offset += 1
            continue
        if value[offset:offset + 2] == '%%':
            offset += 2
            continue
        match = FORMAT.match(value, offset)
        if match:
            position = int(match[1]) if match[1] else sequential
            if not match[1]:
                sequential += 1
            signature.append((position, match[2]))
            invalid.extend(['star-width-or-precision'] if '*' in match[0] else [])
            offset = match.end()
        else:
            unmatched.append(value[offset:offset + 15])
            # A bare percentage in prose is not by itself an invalid translation.
            if offset + 1 < len(value) and (value[offset + 1].isalpha() or value[offset + 1] in '%$'):
                invalid.append(value[offset:offset + 15])
            offset += 1
    return sorted(signature), sorted(set(invalid + (unmatched if signature else [])))


def source_paths(root=ROOT):
    paths = set()
    # No customer files/media/cache/logs/session or local deployment configuration are read.
    for name in ['application', 'js', 'library/DEEC', 'install', 'tools', 'templates']:
        for path in (root / name).rglob('*'):
            if path.is_file() and not path.is_symlink() and path.suffix in SUFFIXES:
                paths.add(path)
    paths.update(p for p in root.glob('*.php') if p.is_file() and not p.is_symlink())
    paths.update(p for p in (root / 'configs').glob('*') if p.name in ['application.ini', 'routes.ini'])
    return sorted(paths)


def module_of(path):
    parts = Path(path).parts
    if len(parts) > 3 and parts[:2] == ('application', 'modules'):
        return parts[2]
    return None


def location(path, line, **extra):
    return {'file': path, 'line': line, **extra}


def hardcoded_candidates(path, text, base_line=1):
    result = []
    # Deliberately narrow UI sinks; these remain candidates until manually reviewed.
    patterns = [
        ('html_text', r'>\s*([^<>\n]*[A-Za-zÄÖÜäöüß][^<>\n]*)\s*<'),
        ('html_attribute', r'\b(?:placeholder|title|aria-label|alt)\s*=\s*[\"\']([^\"\'<>]+)[\"\']'),
        ('js_ui_sink', r'(?:\balert|\bconfirm|\.text|pushMessages)\s*\(\s*\[?\s*[\"\']([^\"\'\n]+)[\"\']'),
        ('form_label', r'[\"\'](?:label|description|heading)[\"\']\s*=>\s*[\"\']([^\"\'\n]+)[\"\']'),
    ]
    for kind, pattern in patterns:
        for match in re.finditer(pattern, text):
            value = html.unescape(match[1].strip())
            if not value or KEY.fullmatch(value) or any(x in value for x in ['<?', '?>', 'function', '$', '{', '=>']):
                continue
            if not re.search(r'[a-zäöüß]{2}', value):
                continue
            result.append(location(path, base_line + text.count('\n', 0, match.start()), kind=kind, text=value[:240], certainty='manual_review'))
    return result


def request_domains(catalogues, locale, module):
    base = f'languages/{locale}/'
    domains = []
    default = sorted(p for p in catalogues if p.startswith(base + 'default/'))
    if default:
        domains.append(('default', default))
    commons = sorted(p for p in catalogues if p.startswith(base) and p.endswith('/common.php') and not p.startswith(base + 'default/'))
    domains.extend((Path(p).parent.name + '_common', [p]) for p in commons)
    active = sorted(p for p in catalogues if p.startswith(base + module + '/'))
    # For the default module loadDir is already marked loaded by bootstrap.
    if active and module != 'default':
        domains.append((module, active))
    return domains


def effective_catalogue(catalogues, domains):
    effective = {}; definitions = defaultdict(list); within = []
    for domain, files in domains:
        merged = {}
        for path in files:
            for entry in catalogues[path].get('entries', []):
                definition = location(path, entry['line'], domain=domain, value=entry['value'])
                if entry['key'] in merged:
                    within.append({'key': entry['key'], 'earlier': merged[entry['key']], 'winner': definition})
                merged[entry['key']] = definition
        for key, definition in merged.items():
            effective.setdefault(key, definition)
            definitions[key].append(definition)
    collisions = [{'key': key, 'winner': definitions[key][0], 'shadowed': definitions[key][1:],
                   'different_values': len({d['value'] for d in definitions[key]}) > 1}
                  for key in sorted(definitions) if len(definitions[key]) > 1]
    return effective, within, collisions


def run_audit(runtime=True):
    paths = sorted((ROOT / 'languages').rglob('*.php'))
    catalogues = php_inspect('catalogues', paths)
    locales = sorted(p.name for p in (ROOT / 'languages').iterdir() if p.is_dir())
    modules = sorted(p.name for p in (ROOT / 'application/modules').iterdir() if p.is_dir())
    catalogue_modules = sorted({Path(p).parts[2] for p in catalogues})
    issues = defaultdict(list, {name: [] for name in [
        'catalogue_errors', 'invalid_utf8', 'utf8_bom', 'duplicate_keys_in_file', 'empty_keys', 'empty_values',
        'identity_values', 'placeholder_review', 'placeholder_mismatches', 'template_placeholder_mismatches',
        'mojibake_candidates', 'german_in_english_candidates', 'identical_locale_values', 'source_scan_errors', 'confirmed_spelling_errors']})
    by_locale = {}; by_key = defaultdict(lambda: defaultdict(list))
    all_keys = set(); file_pairs = []; unique_locale = {}; matrix = []
    for path, catalogue in catalogues.items():
        raw = (ROOT / path).read_bytes()
        catalogue['sha256'] = hashlib.sha256(raw).hexdigest()
        catalogue['bytes'] = len(raw)
        try:
            raw.decode('utf-8', errors='strict')
        except UnicodeDecodeError as error:
            issues['invalid_utf8'].append({'file': path, 'offset': error.start})
        if raw.startswith(b'\xef\xbb\xbf'):
            issues['utf8_bom'].append({'file': path})
        if 'error' in catalogue:
            issues['catalogue_errors'].append({'file': path, 'error': catalogue['error']})
            continue
        locale = Path(path).parts[1]; seen = {}
        for entry in catalogue['entries']:
            key, value = entry['key'], entry['value']
            all_keys.add(key)
            ref = location(path, entry['line'], key=key, value=value)
            by_key[locale][key].append(ref)
            if key in seen:
                issues['duplicate_keys_in_file'].append({'key': key, 'earlier': seen[key], 'winner': ref, 'different_values': seen[key]['value'] != value})
            seen[key] = ref
            if not value.strip():
                issues['empty_values'].append(ref)
            if not key.strip():
                issues['empty_keys'].append(ref)
            if value == key:
                issues['identity_values'].append(ref)
            signature, invalid = placeholders(value)
            if invalid:
                issues['placeholder_review'].append({**ref, 'tokens': invalid})
            if re.search(r'Ã[\x80-\u00bf]|Â[\x80-\u00bf]|\ufffd', value):
                issues['mojibake_candidates'].append(ref)
            spelling_rules = {'en_US': [(r'\bDowland\b', 'Download'), (r'\bBillding\b', 'Billing'),
                                        (r'\bCOuntry\b', 'Country'), (r'\bDoCument\b', 'Document'),
                                        (r'\bReflesh\b', 'Refresh'), (r'\bsuccesfully\b', 'successfully'),
                                        (r'\b[Pp]ayed\b', 'paid (preserve capitalization)')],
                              'de_DE': [(r'\bMesseinheit\b', 'Maßeinheit or context-specific Mengeneinheit')]}
            for pattern, correction in spelling_rules.get(locale, []):
                if re.search(pattern, value):
                    issues['confirmed_spelling_errors'].append({**ref, 'suggestion': correction, 'certainty': 'confirmed_text', 'priority': 'P2'})
            if locale == 'en_US' and re.search(r'\b(?:Nachricht|Käufer|Stückzahl|Rechnung|Angebotsnummer|Auftragsbestätigungs|Lieferscheinnummer|Gutschriftsnummer|Bestellungsnummer|Mahnungsnummer|Anfragenummer|Vorgangsnummer|Gebühren|Nachnahme|Warensendung|Anzahl|Bezeichnung|Bearbeiten|Löschen|Gesamt|Versandkosten)\b', value):
                issues['german_in_english_candidates'].append(ref)
    for locale in locales:
        files = [p for p in catalogues if Path(p).parts[1] == locale]
        unique_locale[locale] = set(by_key[locale])
        by_locale[locale] = {'files': len(files), 'entries': sum(len(catalogues[p].get('entries', [])) for p in files),
                             'unique_keys': len(unique_locale[locale]), 'catalogue_modules': len({Path(p).parts[2] for p in files})}
    de, en = unique_locale.get('de_DE', set()), unique_locale.get('en_US', set())
    for relative in sorted({str(Path(p).relative_to('languages/' + Path(p).parts[1])) for p in catalogues}):
        dp, ep = 'languages/de_DE/' + relative, 'languages/en_US/' + relative
        d = {e['key']: e for e in catalogues.get(dp, {}).get('entries', [])}
        e = {v['key']: v for v in catalogues.get(ep, {}).get('entries', [])}
        file_pairs.append({'relative_file': relative, 'de_DE': dp if dp in catalogues else None, 'en_US': ep if ep in catalogues else None,
                           'missing_in_en_US': sorted(d.keys() - e.keys()), 'missing_in_de_DE': sorted(e.keys() - d.keys())})
        for key in sorted(d.keys() & e.keys()):
            dv, ev = d[key]['value'], e[key]['value']
            refs = {'key': key, 'de_DE': location(dp, d[key]['line'], value=dv), 'en_US': location(ep, e[key]['line'], value=ev)}
            if dv == ev:
                issues['identical_locale_values'].append(refs)
            if placeholders(dv)[0] != placeholders(ev)[0]:
                issues['placeholder_mismatches'].append(refs)
            # Templates use bracket/hash tokens as well as printf arguments.
            template_tokens = lambda s: sorted(re.findall(r'\[[A-Z][A-Z_]+\]|#[A-Z][A-Z_]+#', s))
            if template_tokens(dv) != template_tokens(ev):
                issues['template_placeholder_mismatches'].append(refs)
    for module in sorted(set(modules) | set(catalogue_modules) | {'default'}):
        row = {'module': module, 'application_module': module in modules or module == 'default'}
        for locale in locales:
            files = [p for p in catalogues if Path(p).parts[1:3] == (locale, module)]
            entries = [e for p in files for e in catalogues[p].get('entries', [])]
            row[locale] = {'files': len(files), 'entries': len(entries), 'unique_keys': len({e['key'] for e in entries}), 'common': f'languages/{locale}/{module}/common.php' in catalogues}
        matrix.append(row)

    paths = source_paths(); php_paths = [p for p in paths if p.suffix in ['.php', '.phtml']]
    tokenized = php_inspect('source', php_paths)
    calls = []; indirect = defaultdict(list); hardcoded = []; dynamic = []; source_hashes = {}; non_php_files = []; missing_metadata = []
    for path in paths:
        relative = path.relative_to(ROOT).as_posix(); raw = path.read_bytes()
        source_hashes[relative] = hashlib.sha256(raw).hexdigest()
        text = raw.decode('utf-8', errors='replace')
        for match in re.finditer(r"['\"](?:label|description|heading)['\"]\s*=>\s*['\"]([A-Z][A-Z0-9_]+)['\"]|->setLabel\(\s*['\"]([A-Z][A-Z0-9_]+)['\"]", text):
            key = match[1] or match[2]
            if key not in all_keys and not re.match(r'\s*\.', text[match.end():]):
                missing_metadata.append(location(relative, text.count('\n', 0, match.start()) + 1, key=key, certainty='manual_review_indirect_sink'))
        if relative in tokenized:
            data = tokenized[relative]
            if 'error' in data:
                issues['source_scan_errors'].append({'file': relative, 'error': data['error']})
                continue
            for call in data['calls']:
                ref = location(relative, call['line'], method=call['method'], key=call['key'])
                if call['key'] is None:
                    dynamic.append({**ref, 'prefix': call['prefix'], 'expression': call['expression']})
                else:
                    calls.append(ref)
            for literal in data['literals']:
                if literal['value'] in all_keys or KEY.fullmatch(literal['value']):
                    indirect[literal['value']].append(location(relative, literal['line']))
            for block in data['html']:
                hardcoded.extend(hardcoded_candidates(relative, block['value'], block['line']))
            # Includes string-built HTML and form descriptions; all require manual confirmation.
            hardcoded.extend(c for c in hardcoded_candidates(relative, text) if c['kind'] in ['form_label', 'html_attribute'])
        else:
            non_php_files.append(relative)
            for literal in QUOTED.finditer(text):
                if literal[2] in all_keys or KEY.fullmatch(literal[2]):
                    indirect[literal[2]].append(location(relative, text.count('\n', 0, literal.start()) + 1))
            if path.suffix == '.js':
                for match in re.finditer(r'\b(Dewawi\.message|[\w.]+\.translate)\s*\(\s*([\'\"])([^\'\"\n]*)\2\s*([,)])', text):
                    calls.append(location(relative, text.count('\n', 0, match.start()) + 1, key=match[3], method=match[1]))
                for match in re.finditer(r'\bDewawi\.message\s*\(([^\n;]*)', text):
                    if not re.match(r'\s*([\'\"])[^\'\"]*\1\s*[,)]', match[1]):
                        dynamic.append(location(relative, text.count('\n', 0, match.start()) + 1, key=None, prefix=None, expression=match[1][:300]))
                hardcoded.extend(hardcoded_candidates(relative, text))
    # Deduplicate overlapping HTML/attribute scans.
    hardcoded = list({(c['file'], c['line'], c['kind'], c['text']): c for c in hardcoded}.values())
    literal_keys = {c['key'] for c in calls if c['key'] in all_keys or KEY.fullmatch(c['key'])}
    route_reports = []; reachable_missing = []
    for locale in locales:
        for module in ['default'] + modules:
            domains = request_domains(catalogues, locale, module)
            effective, within, collisions = effective_catalogue(catalogues, domains)
            module_calls = [c for c in calls if module_of(c['file']) == module and KEY.fullmatch(c['key'])]
            absent = [c for c in module_calls if c['key'] not in effective]
            reachable_missing.extend({**c, 'locale': locale, 'module': module, 'exists_elsewhere': c['key'] in unique_locale[locale], 'certainty': 'static_request_model'} for c in absent)
            files = [p for _, fs in domains for p in fs]
            route_reports.append({'locale': locale, 'module': module, 'domains': [d for d, _ in domains], 'files_in_load_order': files,
                                  'require_calls': len(files), 'unique_files': len(set(files)), 'bytes_in_require_calls': sum(catalogues[p]['bytes'] for p in files),
                                  'effective_keys': len(effective), 'effective_sha256': hashlib.sha256(json.dumps({key: ref['value'] for key, ref in sorted(effective.items())}, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest(),
                                  'literal_module_calls': len(module_calls), 'unresolved_module_calls': len(absent),
                                  'within_domain_overwrites': within, 'cross_domain_collisions': collisions})
    usage = []
    prefixes = sorted({d['prefix'] for d in dynamic if d['prefix']})
    for key in sorted(all_keys):
        if key in literal_keys:
            classification = 'literal_call_found'
        elif key in indirect:
            classification = 'indirect_literal_candidate'
        elif any(key.startswith(prefix) for prefix in prefixes) or re.fullmatch('[A-Z]{2,3}', key):
            classification = 'possibly_dynamic'
        else:
            classification = 'unresolved'
        usage.append({'key': key, 'classification': classification, 'literal_calls': [c for c in calls if c['key'] == key],
                      'indirect_references': indirect.get(key, [])})
    vendor = []
    vendor_dirs = {'lang', 'langs', 'i18n', 'cultures', 'localization'}
    for path in sorted((ROOT / 'library').rglob('*')):
        if path.is_file() and any(part in vendor_dirs for part in path.relative_to(ROOT / 'library').parts):
            vendor.append(path.relative_to(ROOT).as_posix())
    runtime_result = {'status': 'not_run'}
    if runtime and not issues['catalogue_errors'] and not issues['invalid_utf8']:
        process = subprocess.run(['php', str(ROOT / 'tests/translations/runtime.php')], capture_output=True, text=True)
        try:
            runtime_result = json.loads(process.stdout)
        except json.JSONDecodeError:
            runtime_result = {'status': 'error', 'stdout': process.stdout, 'stderr': process.stderr}
        runtime_result['exit_code'] = process.returncode
        comparisons = []
        for context in runtime_result.get('observations', {}).get('request_contexts', []):
            model = next(r for r in route_reports if r['locale'] == context['locale'] and r['module'] == context['module'])
            comparisons.append({'locale': context['locale'], 'module': context['module'],
                                'matches': all(context[field] == model[field] for field in ['domains', 'effective_keys', 'effective_sha256'])})
        runtime_result['static_model_comparisons'] = comparisons
        if not comparisons or not all(c['matches'] for c in comparisons):
            runtime_result['status'] = 'failed'
    js_literal_keys = {c['key'] for c in calls if c['method'] == 'Dewawi.message'}
    exported = set(runtime_result.get('observations', {}).get('javascript_export_keys', []))
    source_identity = hashlib.sha256(json.dumps({'source': source_hashes, 'catalogues': {p: c['sha256'] for p, c in catalogues.items()}}, sort_keys=True).encode()).hexdigest()
    report = {
        'schema_version': 1, 'scope': 'local repository; no database, customer data or network', 'source_sha256': source_identity,
        'audit_code_sha256': {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted((ROOT / 'tests/translations').iterdir()) if p.suffix in ['.php', '.py']},
        'scan': {'locales': locales, 'application_modules': modules, 'catalogue_modules': catalogue_modules,
                 'source_files': len(paths), 'php_phtml_files': len(php_paths), 'other_source_files': non_php_files,
                 'source_hashes': source_hashes, 'vendor_localization_files': vendor,
                 'zend_cldr_data_files': [p.relative_to(ROOT).as_posix() for p in sorted((ROOT / 'library/Zend/Locale/Data').glob('*.xml'))],
                 'other_language_files': [p.relative_to(ROOT).as_posix() for p in sorted((ROOT / 'languages').rglob('*')) if p.is_file() and p.suffix != '.php'],
                 'excluded': ['tests (not production key usage)', 'library except DEEC (vendor listed separately)', 'files', 'media', 'cache', 'logs', 'session', 'local deployment secrets', 'external extensions and database content']},
        'summary': {'locales': len(locales), 'application_modules': len(modules), 'catalogue_modules_including_default': len(catalogue_modules),
                    'catalogue_files': len(catalogues), 'entries': sum(c['entries'] for c in by_locale.values()), 'unique_keys_union': len(all_keys),
                    'by_locale': by_locale, 'shared_unique_keys': len(de & en), 'missing_unique_keys_en_US': len(de - en), 'missing_unique_keys_de_DE': len(en - de),
                    'missing_files_en_US': sum(p['en_US'] is None for p in file_pairs), 'missing_files_de_DE': sum(p['de_DE'] is None for p in file_pairs),
                    'literal_calls': len(calls), 'literal_key_calls': sum(KEY.fullmatch(c['key']) is not None for c in calls), 'unique_literal_keys': len(literal_keys),
                    'dynamic_calls': len(dynamic), 'usage_classifications': dict(Counter(u['classification'] for u in usage)), 'safely_unused': 0,
                    'hardcoded_candidates': len(hardcoded), 'issue_counts': {k: len(v) for k, v in sorted(issues.items())}},
        'module_language_matrix': matrix, 'catalogues': catalogues, 'file_pairs': file_pairs,
        'missing_unique_keys': {'en_US': sorted(de - en), 'de_DE': sorted(en - de)},
        'issues': dict(issues), 'request_loading': route_reports, 'unresolved_request_calls': reachable_missing,
        'literal_calls_absent_from_all_catalogues': [c for c in calls if KEY.fullmatch(c['key']) and c['key'] not in all_keys],
        'non_key_literal_calls': [c for c in calls if not KEY.fullmatch(c['key'])],
        'indirect_translation_candidates_absent_from_catalogues': missing_metadata,
        'javascript_bridge': {'literal_message_keys': sorted(js_literal_keys), 'exported_keys': sorted(exported),
                              'literal_keys_not_exported': sorted(js_literal_keys - exported) if exported else None},
        'usage': usage, 'dynamic_calls': dynamic, 'hardcoded_candidates': hardcoded, 'runtime': runtime_result,
        'limitations': ['Static request model assumes a fresh routed request; forwards, plugin order changes and injected translators need context review.',
                        'Indirect literals are candidates, not proof of a translation call. Dynamic calls, DB keys, extensions and country codes prevent safe deletion.',
                        'Spelling/terminology and hardcoded text checks are heuristics plus documented manual review, not a complete linguistic certification.',
                        'Vendor language packs are inventoried by path, not audited for upstream translation correctness.',
                        'Runtime tests exercise production translator/plugin/helper code with isolated context fixtures, not authenticated HTTP, database or outgoing mail/PDF generation.'],
    }
    return report


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--no-runtime', action='store_true', help='Only tokenize and compare; execute no application classes')
    parser.add_argument('--summary', action='store_true', help='Print just coverage and runtime outcomes')
    parser.add_argument('--fail-on-findings', action='store_true', help='Exit 1 for catalogue/parity/loading findings (expected on T1 baseline)')
    args = parser.parse_args()
    report = run_audit(not args.no_runtime)
    output = {'source_sha256': report['source_sha256'], 'summary': report['summary'], 'runtime': report['runtime']} if args.summary else report
    json.dump(output, sys.stdout, ensure_ascii=False, indent=2)
    print()
    if report['runtime'].get('status') in ['failed', 'error']:
        return 2
    if report['issues'].get('catalogue_errors') or report['issues'].get('invalid_utf8') or report['issues'].get('source_scan_errors'):
        return 2
    if args.fail_on_findings and (report['summary']['missing_unique_keys_en_US'] or report['summary']['missing_unique_keys_de_DE'] or report['unresolved_request_calls'] or any(report['issues'].values())):
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
