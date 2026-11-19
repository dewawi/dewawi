#!/usr/bin/env python3
"""Fixture tests for audit precision; no repository or database changes."""

import importlib.util
import json
from pathlib import Path
import subprocess
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('translation_audit', Path(__file__).with_name('audit.py'))
audit = importlib.util.module_from_spec(spec)
spec.loader.exec_module(audit)


class AuditTests(unittest.TestCase):
    def inspect(self, mode, source):
        with tempfile.TemporaryDirectory(prefix='dewawi-translation-parser-') as directory:
            path = Path(directory) / 'catalogue.php'
            path.write_text(source, encoding='utf-8')
            result = subprocess.run(['php', str(Path(__file__).with_name('inspect.php'))],
                                    input=json.dumps({'mode': mode, 'files': [str(path)]}),
                                    capture_output=True, text=True, check=True)
            return json.loads(result.stdout)[str(path)]

    def test_duplicate_definitions_preserved(self):
        result = self.inspect('catalogues', "<?php return array('A' => 'first', 'A' => 'second');")
        self.assertEqual([e['value'] for e in result['entries']], ['first', 'second'])

    def test_comments_and_arrows_in_values(self):
        result = self.inspect('catalogues', "<?php // 'FAKE' => 'ignore'\nreturn ['A' => 'B => C', /* comment */ 'EMPTY' => ''];")
        self.assertEqual(len(result['entries']), 2)
        self.assertEqual(result['entries'][0]['line'], 2)

    def test_string_escaping(self):
        result = self.inspect('catalogues', r'''<?php return ['A' => 'It\'s C:\\temp', 'B' => "line\n\x41\101\$\q"];''')
        self.assertEqual(result['entries'][0]['value'], "It's C:\\temp")
        self.assertEqual(result['entries'][1]['value'], 'line\nAA$\\q')

    def test_unicode_escapes(self):
        result = self.inspect('catalogues', r'''<?php return ['A' => "\u{41}\u{1f600}", 'B' => "\\u{41}"];''')
        self.assertEqual(result['entries'][0]['value'], 'A😀')
        self.assertEqual(result['entries'][1]['value'], '\\u{41}')

    def test_no_catalogue_code_execution(self):
        with tempfile.TemporaryDirectory(prefix='dewawi-translation-side-effect-') as directory:
            marker = Path(directory) / 'marker'
            result = self.inspect('catalogues', f"<?php file_put_contents('{marker}', 'bad'); return ['A' => 'B'];")
            self.assertIn('error', result)
            self.assertFalse(marker.exists())

    def test_parse_error(self):
        self.assertIn('ParseError', self.inspect('catalogues', "<?php return ['A' => ;")['error'])

    def test_non_array_return(self):
        self.assertIn('error', self.inspect('catalogues', '<?php return false;'))

    def test_non_string_values_and_expressions(self):
        for source in ["<?php return ['A' => null];", "<?php return ['A' => 'x' . 'y'];", "<?php return ['A' => getenv('X')];"]:
            self.assertIn('error', self.inspect('catalogues', source))

    def test_calls_ignore_comments_and_distinguish_concatenation(self):
        result = self.inspect('source', "<?php // $v->translate('FAKE');\n$v->translate('REAL'); $v->translate('PREFIX_' . strtoupper($key)); $tr->t($map[$key]);")
        self.assertEqual([c['key'] for c in result['calls']], ['REAL', None, None])
        self.assertEqual(result['calls'][1]['prefix'], 'PREFIX_')

    def test_multiline_calls_and_inline_html(self):
        result = self.inspect('source', "<button>Speichern</button><?php $v->translate(\n /* comment */ 'KEY', ['value']); ?>")
        self.assertEqual(result['calls'][0]['key'], 'KEY')
        self.assertEqual(result['calls'][0]['line'], 1)
        self.assertEqual(len(result['html']), 1)

    def test_method_declaration_is_not_call(self):
        result = self.inspect('source', "<?php function translate($key) {} class Test { function t($key) {} }")
        self.assertEqual(result['calls'], [])

    def test_printf_positional_reordering(self):
        self.assertEqual(audit.placeholders('%s %d')[0], audit.placeholders('%2$d %1$s')[0])

    def test_printf_escaped_percent(self):
        self.assertEqual(audit.placeholders('100%% %s')[0], [(1, 's')])
        self.assertEqual(audit.placeholders('100%')[0], [])

    def test_printf_invalid_and_types(self):
        self.assertTrue(audit.placeholders('%Q')[1])
        self.assertTrue(audit.placeholders('%i')[1])
        self.assertTrue(audit.placeholders('%s 100%')[1])
        self.assertNotEqual(audit.placeholders('%s')[0], audit.placeholders('%d')[0])
        self.assertTrue(audit.placeholders('%*s')[1])

    def test_domain_precedence_and_file_precedence(self):
        catalogues = {'a': {'entries': [{'key': 'A', 'value': 'a', 'line': 1}]},
                      'z': {'entries': [{'key': 'A', 'value': 'z', 'line': 1}]},
                      'module': {'entries': [{'key': 'A', 'value': 'module', 'line': 1}]}}
        effective, within, collisions = audit.effective_catalogue(catalogues, [('default', ['a', 'z']), ('module', ['module'])])
        self.assertEqual(effective['A']['value'], 'z')
        self.assertEqual(len(within), 1)
        self.assertEqual(collisions[0]['winner']['value'], 'z')
        self.assertTrue(collisions[0]['different_values'])

    def test_real_request_order_model(self):
        catalogues = {f'languages/de_DE/{path}': {} for path in ['default/common.php', 'sales/common.php', 'items/common.php', 'sales/quote.php', 'items/item.php']}
        domains = audit.request_domains(catalogues, 'de_DE', 'sales')
        self.assertEqual([d for d, files in domains], ['default', 'items_common', 'sales_common', 'sales'])
        self.assertNotIn('languages/de_DE/items/item.php', [p for d, files in domains for p in files])
        self.assertEqual([d for d, files in audit.request_domains(catalogues, 'de_DE', 'default')].count('default'), 1)

    def test_hardcoded_candidates_are_not_proven(self):
        candidates = audit.hardcoded_candidates('view.phtml', '<button>Speichern</button><input placeholder="Was suchen Sie?">')
        self.assertEqual(len(candidates), 2)
        self.assertTrue(all(c['certainty'] == 'manual_review' for c in candidates))

    def test_catalogue_key_detection(self):
        self.assertTrue(audit.KEY.fullmatch('DOCUMENTS_QUOTE_ID_%s_FROM_%s'))
        self.assertFalse(audit.KEY.fullmatch('Translate this text'))


if __name__ == '__main__':
    unittest.main()
