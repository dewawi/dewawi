"""Synthetic package fixtures only; no operator inputs or reports enter the worktree."""
import copy
import csv
from datetime import datetime, timezone
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import zipfile

sys.dont_write_bytecode = True
spec = importlib.util.spec_from_file_location('gobd_package', Path(__file__).with_name('gobd-package.py'))
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
ROOT = Path(__file__).resolve().parents[2]
checks = 0


def check(value):
    global checks
    if not value:
        raise AssertionError('Synthetic package assertion failed')
    checks += 1


def csv_bytes(rows, fields):
    stream = io.StringIO(newline='')
    writer = csv.DictWriter(stream, fieldnames=fields, delimiter=';', lineterminator='\n')
    writer.writeheader()
    writer.writerows(rows)
    return stream.getvalue().encode()


os.umask(0o077)
with tempfile.TemporaryDirectory(prefix='dewawi-gobd-package-fixture-') as temporary:
    work = Path(temporary)
    generator = work / 'render.php'
    generator.write_text('''<?php
require $argv[1] . '/library/Tcpdf/tcpdf.php';
class SyntheticPdf extends TCPDF { public function __construct() { parent::__construct(); $this->tcpdflink = false; } }
$pdf = new SyntheticPdf(); $pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->AddPage();
if ($argv[3] === 'scan') $pdf->Rect(20, 20, 40, 40, 'F');
else $pdf->writeHTML($argv[3] === 'foreign' ? 'SYNTHETIC ISSUER B<br>SYNTHETIC REGISTRATION B<br>SYNTHETIC RECIPIENT A' : 'SYNTHETIC ISSUER A<br>SYNTHETIC REGISTRATION A<br>SYNTHETIC RECIPIENT B');
$pdf->Output($argv[2], 'F');
''')
    for kind in ['text', 'scan', 'foreign']:
        subprocess.run(['php', str(generator), str(ROOT), str(work / (kind + '.pdf')), kind], check=True, capture_output=True)
    sample = (work / 'text.pdf').read_bytes()
    scanned = (work / 'scan.pdf').read_bytes()
    date = '2000-01-02'  # Fixed artificial fixture date; no operator period.
    base_rows = [dict(id=str(i), invoiceid='SYNTHETIC-NUMBER', clientid='5', contactid='7', invoicedate=date,
                      completed='0', state='105', cancelled='0', deleted='0', subtotal='100.0000', taxes='19.0000',
                      total='119.0000', prepayment='', header='Synthetic "quoted" text\\\nwith newline') for i in [9, 10]]

    def package(name, *, rows=None, pdf=sample, old=False, add_column=False, price='100.0000', wrong_count=False, wrong_reference=False, foreign_gap=False, wrong_type=False, extra_dataset=False):
        rows = copy.deepcopy(rows or base_rows)
        if add_column:
            for row in rows:
                row['extra_reference'] = 'SYNTHETIC-REFERENCE'
        fields = list(rows[0])
        customers = [dict(id='7', clientid='5', contactid='7', name1='SYNTHETIC RECIPIENT')]
        positions = [dict(id='20', parentid='9', clientid='5', price=price, quantity='1.0000', taxrate='19.0000', total='119.0000'),
                     dict(id='21', parentid='10', clientid='5', price='-5.0000', quantity='1.0000', taxrate='19.0000', total='-5.9500')]
        data = {'invoices.csv': csv_bytes(rows, fields), 'invoice_positions.csv': csv_bytes(positions, list(positions[0])),
                'customers.csv': csv_bytes(customers, list(customers[0]))}
        if extra_dataset:
            data['additional_references.csv'] = csv_bytes([dict(id='99', title='SYNTHETIC REFERENCE')], ['id', 'title'])
        columns = {name: list(next(csv.reader(io.StringIO(value.decode()), delimiter=';'))) for name, value in data.items()}
        overview = []
        paths = []
        for i, row in enumerate(rows):
            path = 'documents/sales/invoice/SYNTHETIC-NUMBER' + ('--id-' + row['id'] if i else '') + '.pdf'
            paths.append(path)
            if not foreign_gap or i:
                data[path] = pdf
            overview.append(dict(record_key='5/sales/invoice/' + row['id'], module='sales', controller='invoice', documentid=row['id'],
                                 document_number=row['invoiceid'], document_date=date, filename='synthetic.pdf', deleted=row['deleted'], issued='1', completed=row['completed'], state=row['state'], cancelled=row['cancelled'],
                                 csv_file='invoice_positions.csv' if wrong_type else 'invoices.csv', csv_key_column='id', csv_key_value='20' if wrong_type else ('ABSENT' if wrong_reference else row['id']),
                                 pdf_path='' if foreign_gap and not i else path, pdf_supplied='0' if foreign_gap and not i else '1',
                                 pdf_origin='not_supplied' if foreign_gap and not i else ('reviewed_reconstruction_duplicate' if i else 'issued_original'),
                                 delivery_note='requested_pdf_not_supplied' if foreign_gap and not i else 'supplied',
                                 missing_reason='issuer_conflict' if foreign_gap and not i else '', issuer_status='conflict' if foreign_gap and not i else 'manually_verified'))
        data['document_files.csv'] = csv_bytes(overview, list(overview[0]))
        relations = [dict(source_file='invoice_positions.csv', source_column='parentid', target_file='invoices.csv', target_column='id', description='Synthetic parent'),
                     dict(source_file='invoices.csv', source_column='contactid', target_file='customers.csv', target_column='contactid', description='Synthetic contact')]
        data['relationships.csv'] = csv_bytes(relations, list(relations[0]))
        dictionary = [dict(file=name, column=column, column_description='Synthetic stored field', data_type='varchar') for name, values in columns.items() for column in values]
        data['data_dictionary.csv'] = csv_bytes(dictionary, list(dictionary[0]))
        by_type = {'sales/' + controller: dict(total=0, issued=0, delivered_pdfs=0, missing_pdfs=0, deleted_issued_without_pdf=0, unresolved_original_references=0) for controller in module.SALES}
        by_type['sales/invoice'].update(total=2, issued=2, delivered_pdfs=1 if foreign_gap else 2, missing_pdfs=int(foreign_gap))
        meta = dict(clientId=5, from_=date, to=date, export_kind='outgoing_documents', tenant_identity_sha256='a' * 64,
                    tables={name[:-4]: dict(file=name, columns=values, table={'invoices.csv': 'invoice', 'invoice_positions.csv': 'invoicepos', 'customers.csv': 'contact'}.get(name, name[:-4]), rowCount=len(rows) if name == 'invoices.csv' else (2 if name == 'invoice_positions.csv' else 1),
                                           relations=[dict(column=item['source_column'], target=item['target_file'], targetColumn=item['target_column']) for item in relations if item['source_file'] == name]) for name, values in columns.items()},
                    documents=dict(enabled=True, types={'sales': module.SALES}, by_type=by_type,
                                   summary=dict(dated_documents=2, included=1 if foreign_gap else 2, missing=int(foreign_gap), excluded=0, issued=2, deleted_issued=0, complete=not foreign_gap)))
        meta['from'] = meta.pop('from_')
        meta['csv_files'] = {name: dict(rows=len(list(csv.reader(io.StringIO(value.decode()), delimiter=';'))) - 1, columns=next(csv.reader(io.StringIO(value.decode()), delimiter=';'))) for name, value in data.items() if name.endswith('.csv')}
        # Count parsed CSV records, not physical lines.
        if wrong_count:
            meta['csv_files']['invoices.csv']['rows'] += 1
        if old:
            meta.pop('csv_files')
            meta.pop('export_kind')
            meta['documents']['types'] = module.TYPES
            for controller in module.TYPES['purchases']:
                meta['documents']['by_type']['purchases/' + controller] = dict(total=0, issued=0, delivered_pdfs=0, missing_pdfs=0, deleted_issued_without_pdf=0, unresolved_original_references=0)
        data['metadata.json'] = json.dumps(meta).encode()
        data['readme.txt'] = b'Synthetic export fixture only\n'
        path = work / name
        with zipfile.ZipFile(path, 'x', zipfile.ZIP_DEFLATED) as archive:
            for key, value in data.items():
                archive.writestr(key, value)
        proof = dict(clientid=5, sha256=module.digest(path), size=path.stat().st_size,
                     entries={key: dict(size=len(value), sha256=hashlib.sha256(value).hexdigest()) for key, value in data.items()})
        proof_path = work / (name + '.verification.json')
        review = dict(schema_version=1, clientid=5, tenant_identity_sha256=meta['tenant_identity_sha256'], decisions=[])
        for row in overview:
            review['decisions'].append(dict(identity=dict(clientid=5, module='sales', controller='invoice', id=int(row['documentid']), document_number=row['document_number']),
                                            pdf_sha256=hashlib.sha256(pdf).hexdigest(), decision='confirmed_same_issuer', reviewed_by='synthetic fixture actor',
                                            reviewed_at=datetime.now(timezone.utc).replace(microsecond=0).isoformat(), basis='Synthetic independent full-document issuer comparison',
                                            actual_issuer='SYNTHETIC ISSUER A', full_document_reviewed=True, issuer_compared_with_tenant=True))
        review_path = work / (name + '.issuer.json')
        module.write_json(review_path, review)
        proof['issuer_review_sha256'] = module.digest(review_path)
        module.write_json(proof_path, proof)
        return path, proof_path, review_path

    serial = 0
    def run(arguments, expected):
        global serial
        serial += 1
        output = work / ('report-' + str(serial) + '.json')
        process = subprocess.run([sys.executable, str(Path(module.__file__)), '--output=' + str(output), *arguments], capture_output=True, text=True)
        check(process.returncode == expected)
        check('SYNTHETIC' not in process.stdout + process.stderr)
        check(output.exists() and output.stat().st_mode & 0o777 == 0o600)
        return json.loads(output.read_text()), output

    current, proof, review = package('current.zip')
    original_sha = module.digest(current)
    args = ['--action=preflight', '--zip=' + str(current), '--client=5', '--verification=' + str(proof), '--issuer-review=' + str(review)]
    report, _ = run(args, 0)
    check(report['result'] == 'technically_verified' and len(report['pdfs']) == 2 and report['by_type']['sales/invoice']['total'] == 2)
    check('SYNTHETIC ISSUER A' in json.loads(Path(report['pdf_details']).read_text().splitlines()[0])['text_evidence']['text'])
    check(Path(report['pdf_details']).stat().st_mode & 0o777 == 0o600)
    check(report['legal_conformity_asserted'] is False and report['dispatch_asserted'] is False)
    check(report['csv_files']['invoices.csv']['rows'] == 2)
    template = work / 'template.json'
    report, _ = run(['--action=preflight', '--zip=' + str(current), '--client=5', '--issuer-template=' + str(template)], 1)
    check(all(row['decision'] == 'unresolved' for row in json.loads(template.read_text())['decisions']))
    check(all(row['issuer_status'] == 'review_required' for row in report['pdfs']))
    foreign_zip, _, foreign_policy = package('foreign-issuer.zip', pdf=(work / 'foreign.pdf').read_bytes())
    foreign = json.loads(foreign_policy.read_text())
    for decision in foreign['decisions']:
        decision['decision'] = 'confirmed_foreign_issuer'
        decision['actual_issuer'] = 'SYNTHETIC ISSUER B'
    foreign_review = work / 'foreign-review.json'; module.write_json(foreign_review, foreign)
    report, _ = run([args[0], '--zip=' + str(foreign_zip), args[2], '--issuer-review=' + str(foreign_review)], 2)
    check(any(item['kind'] == 'confirmed_foreign_issuer' for item in report['findings']))
    scan, scan_proof, _ = package('scanned.zip', pdf=scanned)
    report, _ = run(['--action=preflight', '--zip=' + str(scan), '--client=5', '--verification=' + str(scan_proof)], 1)
    check(all(row['text_evidence']['status'] == 'no_machine_readable_text' and row['issuer_status'] == 'review_required' for row in report['pdfs']))
    bad_proof = json.loads(proof.read_text()); bad_proof['sha256'] = '0' * 64
    wrong_proof = work / 'wrong-proof.json'; module.write_json(wrong_proof, bad_proof)
    run([*args[:3], '--verification=' + str(wrong_proof)], 2)
    run([args[0], args[1], '--client=6'], 2)
    for label, flags in [('wrong-count', dict(wrong_count=True)), ('wrong-reference', dict(wrong_reference=True)), ('wrong-type', dict(wrong_type=True)), ('foreign-gap', dict(foreign_gap=True)), ('bad-pdf', dict(pdf=b'%PDF-1.4\nmissing end marker'))]:
        path, _, _ = package(label + '.zip', **flags)
        result, _ = run(['--action=preflight', '--zip=' + str(path), '--client=5'], 2)
        check(result['result'] == 'not_releasable')
    with zipfile.ZipFile(current) as source:
        public_meta = json.loads(source.read('metadata.json'))
        public_rows = list(csv.DictReader(io.StringIO(source.read('document_files.csv').decode()), delimiter=';'))
        internal_data, internal_rows = {}, []
        for row in public_rows:
            path = row['pdf_path'].replace('documents/', 'originals/', 1)
            internal_data[path] = source.read(row['pdf_path'])
            entry = dict(record_key=row['record_key'], clientid='5', module=row['module'], controller=row['controller'], documentid=row['documentid'],
                         document_number=row['document_number'], document_date=row['document_date'], filename=row['filename'], deleted=row['deleted'], issued='1',
                         completed=row['completed'], state=row['state'], cancelled=row['cancelled'], csv_file='document_index.csv', csv_key_column='record_key', csv_key_value=row['record_key'],
                         status='archived_original', reason='', zip_path=path, filesize=str(len(internal_data[path])), sha256=hashlib.sha256(internal_data[path]).hexdigest(), pdf_origin=row['pdf_origin'])
            internal_rows.append(entry)
        internal_fields = list(internal_rows[0])
        for file, rows in [('document_index.csv', internal_rows), ('document_manifest.csv', internal_rows), ('missing_documents.csv', []), ('excluded_documents.csv', [])]:
            internal_data[file] = csv_bytes(rows, internal_fields)
        internal_data['data_dictionary.csv'] = csv_bytes([], ['file', 'column', 'column_description'])
        internal_data['relationships.csv'] = csv_bytes([], ['source_file', 'source_column', 'target_file', 'target_column', 'description'])
        meta = copy.deepcopy(public_meta); meta['tables'] = []; meta['export_kind'] = 'internal_document_review'
        meta['documents']['types'] = module.TYPES
        for controller in module.TYPES['purchases']:
            meta['documents']['by_type']['purchases/' + controller] = dict(total=0, issued=0, delivered_pdfs=0, missing_pdfs=0, deleted_issued_without_pdf=0, unresolved_original_references=0)
        meta['documents']['summary'] = dict(documents=2, dated_documents=2, undated_diagnostic_documents=0, issued=2, deleted_issued=0, archived_original=2,
                                           historical_reconstruction_pending_original_comparison=0, historical_diagnostic_reconstruction=0,
                                           historical_unreviewed_candidate=0, missing=0, invalid=0, excluded=0, complete=True)
        meta['csv_files'] = {name: dict(rows=len(list(csv.reader(io.StringIO(value.decode()), delimiter=';'))) - 1, columns=next(csv.reader(io.StringIO(value.decode()), delimiter=';'))) for name, value in internal_data.items() if name.endswith('.csv')}
        internal_data['metadata.json'] = json.dumps(meta).encode()
        internal_data['readme.txt'] = b'INTERNAL DOCUMENT REVIEW ONLY; synthetic fixture'
        internal_data['checksums.sha256'] = ''.join(hashlib.sha256(value).hexdigest() + '  ' + name + '\n' for name, value in internal_data.items()).encode()
        internal_zip = work / 'internal.zip'
        with zipfile.ZipFile(internal_zip, 'x', zipfile.ZIP_DEFLATED) as target:
            for name, value in internal_data.items():
                target.writestr(name, value)
    internal_report, _ = run(['--action=preflight', '--zip=' + str(internal_zip), '--client=5'], 1)
    check(internal_report['independent_integrity_verified'] is True and len(internal_report['by_type']) == 8 and len(internal_report['pdfs']) == 2)
    old, _, _ = package('old.zip', old=True)
    new_rows = copy.deepcopy(base_rows)
    for row in new_rows:
        row['completed'] = '1'
    new, _, _ = package('new.zip', rows=new_rows, add_column=True, price='100.00', extra_dataset=True)
    report, changes_path = run(['--action=compare', '--before=' + str(old), '--after=' + str(new)], 1)
    changes = report['comparison']['changes']
    check(changes['financial_changes'] == 0 and changes['business_changes'] == 0 and changes['completed_normalization_candidates'] == 2 and changes['value_format_changes'] == 1)
    check(report['comparison']['csv_structure_and_counts']['invoices.csv']['added_columns'] == ['extra_reference'])
    check(changes['newly_delivered_dataset_rows'] == 1 and changes['added_business_rows'] == 0)
    check(Path(str(changes_path) + '.changes.jsonl').is_file())
    changed, _, _ = package('changed.zip', price='101.0000')
    report, _ = run(['--action=compare', '--before=' + str(current), '--after=' + str(changed)], 1)
    check(report['comparison']['changes']['financial_changes'] == 1)
    same, _ = run(['--action=compare', '--before=' + str(current), '--after=' + str(current)], 1)
    check(not any(same['comparison']['changes'].values()))
    check(module.digest(current) == original_sha)
    unsafe = work / 'unsafe.zip'
    with zipfile.ZipFile(unsafe, 'x') as archive:
        archive.writestr('../outside.pdf', sample)
    run(['--action=preflight', '--zip=' + str(unsafe), '--client=5'], 2)
    link = work / 'input-link.zip'; link.symlink_to(current)
    result = subprocess.run([sys.executable, str(Path(module.__file__)), '--action=preflight', '--zip=' + str(link), '--client=5', '--output=' + str(work / 'symlink-report.json')], capture_output=True)
    check(result.returncode == 2)
    check(module.digest(current) == original_sha)
print('PASS:', checks, 'synthetic GoBD package checks; issuers, scans, identities, inventories, private reports, comparison and unchanged inputs')
