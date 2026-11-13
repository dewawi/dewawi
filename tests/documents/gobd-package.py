"""Read-only GoBD package preflight and comparison; all operator output stays private."""
import argparse
import csv
from decimal import Decimal, InvalidOperation
import hashlib
import io
import json
import os
from pathlib import Path
import re
import resource
import sqlite3
import stat
import struct
import subprocess
import zlib
import tempfile
from datetime import datetime, timezone
import zipfile

ROOT = Path(__file__).resolve().parents[2]
TECHNICAL = {'data_dictionary.csv', 'relationships.csv', 'document_files.csv', 'document_index.csv',
             'document_manifest.csv', 'missing_documents.csv', 'excluded_documents.csv'}
SALES = ['quote', 'salesorder', 'invoice', 'deliveryorder', 'creditnote', 'reminder']
TYPES = {'sales': SALES, 'purchases': ['quoterequest', 'purchaseorder']}
STATUS = {'completed', 'state', 'cancelled', 'deleted', 'issued'}
MONEY = {'price', 'quantity', 'taxrate', 'taxes', 'taxfree', 'subtotal', 'total', 'prepayment',
         'currency', 'cashdiscountdays', 'cashdiscountpercent', 'paymentmethod', 'paymentterm'}
csv.field_size_limit(16 * 1024 * 1024)


def digest(path):
    with open(path, 'rb') as source:
        return hashlib.file_digest(source, 'sha256').hexdigest()


def private_path(value, output=False):
    path = Path(value)
    if not path.is_absolute() or '..' in path.parts or path == ROOT or ROOT in path.parents:
        raise ValueError('Operator files must be absolute paths outside the repository')
    for parent in [path, *path.parents]:
        if parent.is_symlink():
            raise ValueError('Symlinks are not accepted')
    if not path.parent.is_dir() or stat.S_IMODE(path.parent.stat().st_mode) != 0o700:
        raise ValueError('Operator directory requires mode 0700')
    if output:
        if path.exists():
            raise ValueError('Existing output is never overwritten')
    elif not path.is_file() or stat.S_IMODE(path.stat().st_mode) != 0o600 or path.stat().st_nlink != 1:
        raise ValueError('Operator input requires a regular private 0600 file')
    return path


def write_json(path, value):
    with open(path, 'x', encoding='utf-8') as target:
        os.chmod(path, 0o600)
        json.dump(value, target, ensure_ascii=False, indent=2)
        target.write('\n')


def bounded_json(path, limit=32 * 1024 * 1024):
    if path.stat().st_size > limit:
        raise ValueError('Private JSON input exceeds limit')
    with open(path, encoding='utf-8') as source:
        return json.load(source)


def decision_key(identity, sha):
    return json.dumps(identity, sort_keys=True, ensure_ascii=False) + '/' + sha


def validate_issuer_identity(identity, sha, client):
    if (type(identity.get('clientid')) is not int or identity.get('clientid') != client or type(identity.get('id')) is not int or identity['id'] <= 0
            or identity.get('controller') not in TYPES.get(identity.get('module'), [])
            or not isinstance(identity.get('document_number'), str) or not re.fullmatch('[a-f0-9]{64}', sha)):
        raise ValueError('Invalid issuer decision identity')


def validate_issuer_attestation(decision, scope=False):
    if decision.get('decision') == 'unresolved':
        return
    if any(not isinstance(decision.get(field), str) or not decision[field].strip() for field in ['reviewed_by', 'reviewed_at', 'basis', 'actual_issuer']):
        raise ValueError('Incomplete issuer attestation')
    reviewed = datetime.strptime(decision['reviewed_at'], '%Y-%m-%dT%H:%M:%S%z')
    if reviewed.isoformat(timespec='seconds') != decision['reviewed_at'] or reviewed > datetime.now(timezone.utc) or decision.get('issuer_compared_with_tenant') is not True:
        raise ValueError('Issuer comparison is not explicitly attested')
    required = ['scope_reviewed', 'independent_issuer_evidence_confirmed'] if scope else ['full_document_reviewed']
    if any(decision.get(field) is not True for field in required):
        raise ValueError('Issuer evidence is not explicitly attested')


def load_issuer_decisions(path, client, context):
    decisions = {}
    if path is None:
        return decisions
    record = bounded_json(path, 128 * 1024 * 1024)
    if type(record.get('schema_version')) is not int or record.get('schema_version') != 1 or type(record.get('clientid')) is not int or record.get('clientid') != client or record.get('tenant_identity_sha256') != context:
        raise ValueError('Issuer review context differs')
    identities = set()
    for item in record['decisions']:
        validate_issuer_identity(item.get('identity', {}), item.get('pdf_sha256', ''), client)
        if item.get('decision') not in {'unresolved', 'confirmed_same_issuer', 'confirmed_foreign_issuer'}:
            raise ValueError('Invalid issuer decision')
        validate_issuer_attestation(item)
        entity = tuple(item['identity'][field] for field in ['clientid', 'module', 'controller', 'id']) + (item['pdf_sha256'],)
        key = decision_key(item['identity'], item['pdf_sha256'])
        if entity in identities or key in decisions:
            raise ValueError('Ambiguous issuer attestation')
        identities.add(entity)
        decisions[key] = item
        if item['decision'] == 'confirmed_foreign_issuer':
            negative = 'foreign:' + '/'.join(str(item['identity'][field]) for field in ['clientid', 'module', 'controller', 'id']) + '/' + item['pdf_sha256']
            decisions[negative] = item
    scoped = set()
    for batch in record.get('batches', []):
        if batch.get('decision') == 'unresolved':
            continue
        if batch.get('decision') != 'confirmed_same_issuer':
            raise ValueError('Invalid issuer scope decision')
        validate_issuer_attestation(batch, scope=True)
        scope_path = private_path(batch['scope_file'])
        scope = bounded_json(scope_path, 128 * 1024 * 1024)
        if (digest(scope_path) != batch['scope_sha256'] or type(scope.get('schema_version')) is not int or scope.get('schema_version') != 1 or type(scope.get('clientid')) is not int or scope.get('clientid') != client
                or scope.get('tenant_identity_sha256') != context or not scope.get('documents')):
            raise ValueError('Issuer scope is invalid or changed')
        for item in scope['documents']:
            validate_issuer_identity(item['identity'], item['pdf_sha256'], client)
            if item.get('screening_status') not in {'unobtrusive_candidate', 'confirmed_same_issuer'}:
                raise ValueError('Issuer scope includes a suspicious or unresolved case')
            key = decision_key(item['identity'], item['pdf_sha256'])
            entity = tuple(item['identity'][field] for field in ['clientid', 'module', 'controller', 'id']) + (item['pdf_sha256'],)
            if entity in scoped:
                raise ValueError('Ambiguous issuer scope')
            scoped.add(entity)
            if decisions.get(key, {}).get('decision', 'unresolved') == 'unresolved':
                decisions[key] = dict(batch, identity=item['identity'], pdf_sha256=item['pdf_sha256'], scope_attested=True)
    return decisions


def find_issuer_decision(decisions, identity, sha):
    foreign_key = 'foreign:' + '/'.join(str(identity[field]) for field in ['clientid', 'module', 'controller', 'id']) + '/' + sha
    return decisions.get(foreign_key) or decisions.get(decision_key(identity, sha))


def issuer_state(decision):
    if not decision or decision['decision'] == 'unresolved':
        return 'review_required'
    if decision['decision'] == 'confirmed_foreign_issuer':
        return 'conflict'
    return 'scope_attested' if decision.get('scope_attested') else 'manually_verified'


def check_directory(path):
    # Bound central-directory allocation before ZipFile constructs entry objects; support ZIP64.
    with open(path, 'rb') as source:
        source.seek(max(0, path.stat().st_size - 65557))
        tail = source.read()
        offset = tail.rfind(b'PK\x05\x06')
        if offset < 0 or len(tail) - offset < 22:
            raise ValueError('Missing ZIP end record')
        _, disk, start, disk_count, count, size, directory, comment = struct.unpack('<4s4H2IH', tail[offset:offset + 22])
        if disk or start or disk_count != count or offset + 22 + comment != len(tail):
            raise ValueError('Invalid or multi-volume ZIP directory')
        if count == 65535 or size == 0xffffffff or directory == 0xffffffff:
            if offset < 20:
                raise ValueError('Missing ZIP64 locator')
            sig, disk, position, disks = struct.unpack('<4sIQI', tail[offset - 20:offset])
            if sig != b'PK\x06\x07' or disk or disks != 1:
                raise ValueError('Invalid ZIP64 locator')
            source.seek(position)
            sig, record_size, _, _, disk, start, disk_count, count, size, directory = struct.unpack('<4sQ2H2I4Q', source.read(56))
            if sig != b'PK\x06\x06' or record_size < 44 or disk or start or disk_count != count:
                raise ValueError('Invalid ZIP64 directory')
        if count > 300000 or size > 128 * 1024 * 1024 or directory + size > path.stat().st_size:
            raise ValueError('ZIP directory exceeds bounds')
        return count


class Package:
    def __init__(self, path, db, label):
        self.path, self.db, self.label = path, db, label
        count = check_directory(path)
        self.zip = zipfile.ZipFile(path, 'r')
        self.entries, self.csv, self.problems = {}, {}, []
        self.client = None
        self.references = []
        self.document_gaps = []
        self.issuer_decisions = []
        self.pdf_report = []
        self.by_type = {}
        self.summary = {}
        self.review_required = False
        self.package_sha256 = digest(path)
        self.package_size = path.stat().st_size
        infos = self.zip.infolist()
        if len(infos) != count:
            raise ValueError('ZIP directory count differs')
        total = 0
        for entry in infos:
            name = entry.filename
            mode = entry.external_attr >> 16
            limit = 256 * 1024**2 if name.lower().endswith('.pdf') else (4 * 1024**3 if name.endswith('.csv') else (64 * 1024**2 if name == 'checksums.sha256' else 8 * 1024**2))
            if (name in self.entries or name.startswith('/') or '\\' in name
                    or any(part in {'', '.', '..'} for part in name.split('/'))
                    or re.search(r'[\x00-\x1f\x7f]', name)
                    or stat.S_IFMT(mode) not in {0, stat.S_IFREG}
                    or entry.flag_bits & 1 or entry.compress_type not in {zipfile.ZIP_STORED, zipfile.ZIP_DEFLATED}
                    or entry.file_size > limit or entry.file_size > max(1, entry.compress_size) * 2000):
                raise ValueError('Unsafe, duplicated or excessive ZIP entry')
            total += entry.file_size
            if total > 64 * 1024**3:
                raise ValueError('ZIP expansion exceeds bounds')
            value = hashlib.sha256()
            size = 0
            with self.zip.open(entry) as source:
                for chunk in iter(lambda: source.read(1024 * 1024), b''):
                    size += len(chunk)
                    if size > entry.file_size:
                        raise ValueError('ZIP expansion differs')
                    value.update(chunk)
            if size != entry.file_size:
                raise ValueError('ZIP size differs')
            self.entries[name] = {'size': size, 'sha256': value.hexdigest()}
        self.meta = self.json_entry('metadata.json')
        tables = self.meta.get('tables', {})
        self.tables = {} if tables == [] else tables
        if not isinstance(self.tables, dict):
            raise ValueError('Invalid table metadata')
        self.client = self.meta.get('clientId')
        if type(self.client) is not int or self.client <= 0:
            raise ValueError('Package has no valid tenant identity')
        if self.meta.get('from') is not None or self.meta.get('to') is not None or self.meta.get('documents'):
            for field in ['from', 'to']:
                value = self.meta.get(field, '')
                if not isinstance(value, str) or not re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2}', value):
                    raise ValueError('Invalid export period')
                datetime.strptime(value, '%Y-%m-%d')
            if self.meta['from'] > self.meta['to']:
                raise ValueError('Reversed export period')
        self.targets = {'id', 'record_key'}
        for table in self.tables.values():
            self.targets.update(item['targetColumn'] for item in table.get('relations', []))
        for name in self.entries:
            if name.endswith('.csv'):
                self.read_csv(name)
        self.verify_metadata()
        self.verify_checksums()
        self.verify_relations()
        self.verify_documents()

    def close(self):
        self.zip.close()

    def json_entry(self, name):
        if name not in self.entries or self.entries[name]['size'] > 8 * 1024 * 1024:
            raise ValueError('Required bounded metadata entry is missing')
        return json.loads(self.zip.read(name).decode('utf-8'))

    def iter_csv(self, name):
        with self.zip.open(name) as source:
            with io.TextIOWrapper(source, encoding='utf-8', newline='') as text:
                # DEWAWI writes doubled enclosures. A legacy backslash is data, not a CSV escape.
                reader = csv.DictReader(text, delimiter=';', strict=True)
                fields = reader.fieldnames
                if not fields or len(set(fields)) != len(fields) or any(not re.fullmatch(r'[A-Za-z_][A-Za-z0-9_]*', field) for field in fields):
                    raise ValueError('Invalid CSV header')
                for row in reader:
                    if None in row or None in row.values():
                        raise ValueError('CSV row length differs')
                    yield fields, row

    def read_csv(self, name):
        fields, rows = None, 0
        for fields, row in self.iter_csv(name):
            rows += 1
            if name not in TECHNICAL and 'clientid' in row and row['clientid'] != str(self.client):
                raise ValueError('Foreign CSV tenant')
            if 'id' in row:
                key = row['id']
            elif name in {'document_files.csv', 'document_index.csv'}:
                key = row['record_key']
            else:
                key = str(rows)
            if not key:
                raise ValueError('Missing internal CSV key')
            self.db.execute('INSERT INTO records VALUES (?, ?, ?, ?)', (self.label, name, key, json.dumps(row, ensure_ascii=False)))
            self.db.executemany('INSERT INTO refs VALUES (?, ?, ?, ?, ?)', [(self.label, name, field, row[field], key) for field in self.targets & row.keys() if row[field] != ''])
        if fields is None:
            with self.zip.open(name) as source:
                fields = next(csv.reader(io.TextIOWrapper(source, encoding='utf-8', newline=''), delimiter=';'), None)
            if not fields or len(set(fields)) != len(fields) or any(not re.fullmatch(r'[A-Za-z_][A-Za-z0-9_]*', field) for field in fields):
                raise ValueError('Invalid empty CSV header')
        self.csv[name] = {'rows': rows, 'columns': fields}

    def rows(self, name):
        for (value,) in self.db.execute('SELECT data FROM records WHERE package = ? AND file = ? ORDER BY rowid', (self.label, name)):
            yield json.loads(value)

    def contains(self, file, column, value):
        return self.db.execute('SELECT COUNT(DISTINCT record_key) FROM refs WHERE package = ? AND file = ? AND column_name = ? AND value = ?', (self.label, file, column, value)).fetchone()[0]

    def verify_metadata(self):
        if 'csv_files' in self.meta and self.meta['csv_files'] != self.csv:
            raise ValueError('CSV inventory or row counts differ from metadata')
        if 'csv_files' not in self.meta:
            self.problems.append({'kind': 'legacy_csv_inventory_absent'})
        for table in self.tables.values():
            if table['file'] not in self.csv or table['columns'] != self.csv[table['file']]['columns']:
                raise ValueError('Table metadata columns differ')
            if 'rowCount' in table and table['rowCount'] != self.csv[table['file']]['rows']:
                raise ValueError('Table metadata row count differs')
        known = {value['file'] for value in self.tables.values()} | TECHNICAL
        if set(self.csv) - known:
            self.problems.append({'kind': 'unclassified_csv_files', 'files': sorted(set(self.csv) - known)})
        if 'data_dictionary.csv' in self.csv:
            for row in self.rows('data_dictionary.csv'):
                if row['file'] not in self.csv or row['column'] not in self.csv[row['file']]['columns']:
                    raise ValueError('Data dictionary references an absent column')
                table = next((table for table in self.tables.values() if table['file'] == row['file']), {})
                schema = table.get('columnSchema', {}).get(row['column'], {})
                for csv_field, attribute in {'data_type': 'DATA_TYPE', 'length': 'LENGTH', 'precision': 'PRECISION', 'scale': 'SCALE', 'nullable': 'NULLABLE', 'primary_key': 'PRIMARY'}.items():
                    if csv_field in row and attribute in schema:
                        value = schema[attribute]
                        expected = '' if value is None else (str(int(value)) if isinstance(value, bool) else str(value))
                        if row[csv_field] != expected:
                            raise ValueError('Data dictionary schema attributes differ from metadata')

    def verify_checksums(self):
        self.checksums_verified = False
        if 'checksums.sha256' not in self.entries:
            return
        if self.entries['checksums.sha256']['size'] > 64 * 1024 * 1024:
            raise ValueError('Checksum inventory exceeds bounds')
        seen = set()
        with self.zip.open('checksums.sha256') as source:
            for line in source:
                match = re.fullmatch(rb'([a-f0-9]{64})  (.+)\n?', line.rstrip(b'\n'))
                if not match:
                    raise ValueError('Invalid checksum line')
                sha, name = match[1].decode(), match[2].decode('utf-8')
                if name in seen or name not in self.entries or self.entries[name]['sha256'] != sha:
                    raise ValueError('Checksum inventory differs')
                seen.add(name)
        if seen != set(self.entries) - {'checksums.sha256'}:
            raise ValueError('Checksum coverage is incomplete')
        self.checksums_verified = True

    def verify_relations(self):
        if 'relationships.csv' not in self.csv:
            self.problems.append({'kind': 'relationships_absent'})
            return
        for relation in self.rows('relationships.csv'):
            source, target = relation['source_file'], relation['target_file']
            column, target_column = relation['source_column'], relation['target_column']
            if (source not in self.csv or target not in self.csv or column not in self.csv[source]['columns']
                    or target_column not in self.csv[target]['columns']):
                raise ValueError('Relationship references an absent file or column')
            # A legacy package may not describe its logical relations in metadata.
            if target_column not in self.targets:
                self.db.executemany('INSERT INTO refs VALUES (?, ?, ?, ?, ?)', [(self.label, target, target_column, row[target_column], str(index)) for index, row in enumerate(self.rows(target))])
                self.targets.add(target_column)
            gaps, ambiguous = 0, 0
            for row in self.rows(source):
                value = row[column]
                if value in {'', '0'}:
                    continue
                count = self.contains(target, target_column, value)
                if count == 0:
                    gaps += 1
                elif count > 1:
                    ambiguous += 1
            if gaps or ambiguous:
                self.problems.append({'kind': 'relationship_review_required', 'relation': relation,
                                      'missing_target_rows': gaps, 'ambiguous_target_rows': ambiguous,
                                      'note': 'May refer outside the date scope or to historical variants; no row is removed or invented'})

    def verify_documents(self):
        docs = self.meta.get('documents')
        pdfs = {name for name in self.entries if name.lower().endswith('.pdf')}
        if not docs:
            if pdfs:
                raise ValueError('Uninventoried PDFs')
            return
        external = 'document_files.csv' in self.csv
        index = 'document_files.csv' if external else 'document_index.csv'
        if index not in self.csv or docs.get('enabled') is not True:
            raise ValueError('Document inventory is missing or disabled')
        expected_types = {'sales': SALES} if external and self.meta.get('export_kind') == 'outgoing_documents' else docs['types']
        if any(controller not in TYPES.get(module, []) for module, values in expected_types.items() for controller in values):
            raise ValueError('Unknown document type scope')
        if {key: sorted(value) for key, value in docs['types'].items()} != {key: sorted(value) for key, value in expected_types.items()}:
            raise ValueError('Unexpected document type scope')
        self.by_type = {module + '/' + controller: dict(total=0, issued=0, delivered_pdfs=0, missing_pdfs=0,
                       deleted_issued_without_pdf=0, unresolved_original_references=0)
                       for module, types in expected_types.items() for controller in types}
        if external and (docs['types'] != {'sales': SALES} or self.meta.get('export_kind') != 'outgoing_documents'):
            self.problems.append({'kind': 'legacy_external_scope_requires_new_export'})
        paths, keys = set(), set()
        counts = dict(included=0, missing=0, excluded=0, issued=0, deleted_issued=0)
        for row in self.rows(index):
            module, controller, documentid = row['module'], row['controller'], row['documentid']
            key = '/'.join([str(self.client), module, controller, documentid])
            if key != row['record_key'] or key in keys or controller not in expected_types.get(module, []) or ('clientid' in row and row['clientid'] != str(self.client)):
                raise ValueError('Document identity or type differs')
            keys.add(key)
            if not (self.meta['from'] <= row['document_date'] <= self.meta['to']):
                if external or row.get('date_scope') != 'undated_diagnostic_not_assigned_to_period' or row['document_date'] not in {'', '0000-00-00'}:
                    raise ValueError('Document lies outside stated date scope')
            if row['document_date'] not in {'', '0000-00-00'}:
                datetime.strptime(row['document_date'], '%Y-%m-%d')
            if {'completed', 'state', 'cancelled'} <= row.keys():
                readonly_marker = row['document_number'] not in {'', '0'} or row['completed'] not in {'', '0'} or row['cancelled'] not in {'', '0'} or row['state'] in {'105', '106'}
                if row['issued'] != str(int(readonly_marker)):
                    raise ValueError('Technical readonly classification differs from stored workflow fields')
            if row['issued'] not in {'0', '1'}:
                raise ValueError('Invalid technical readonly flag')
            path = row['pdf_path'] if external else row['zip_path']
            supplied = row['pdf_supplied'] == '1' if external else bool(path)
            if external and (row['pdf_supplied'] not in {'0', '1'} or bool(path) != supplied):
                raise ValueError('PDF delivery statement differs')
            if path:
                paths.add(path)
                if external and row.get('pdf_origin') not in {'issued_original', 'verified_recovered_original', 'reviewed_reconstruction_duplicate', 'registered_archive_origin_unconfirmed'}:
                    raise ValueError('Unapproved or unknown PDF provenance')
                if external and row.get('pdf_origin') == 'registered_archive_origin_unconfirmed':
                    self.problems.append({'kind': 'archive_origin_requires_review', 'record_key': key})
            count = self.by_type[module + '/' + controller]
            count['total'] += 1
            count['delivered_pdfs'] += int(supplied)
            readonly = row['issued'] == '1'
            deleted = row['deleted'] != '0'
            if readonly:
                count['issued'] += 1
                counts['issued'] += 1
                counts['deleted_issued'] += int(deleted)
                count['missing_pdfs'] += int(not supplied)
                count['deleted_issued_without_pdf'] += int(deleted and not supplied)
            note = row.get('delivery_note', '')
            status = row.get('status', '')
            unresolved = note == 'original_reference_not_supplied_issue_status_unconfirmed' if external else status == 'excluded' and not readonly
            count['unresolved_original_references'] += int(unresolved)
            if external:
                actual_status = {'supplied': 'included', 'requested_pdf_not_supplied': 'missing',
                                 'original_reference_not_supplied_issue_status_unconfirmed': 'excluded',
                                 'draft_no_original_requested': 'not_issued'}.get(note)
                if actual_status is None or (actual_status == 'included') != supplied or supplied and not readonly:
                    raise ValueError('Inconsistent document delivery classification')
                if actual_status in counts:
                    counts[actual_status] += 1
                if 'missing_reason' in row and bool(row['missing_reason']) != (actual_status in {'missing', 'excluded'}):
                    raise ValueError('Document gap reason differs')
            if row.get('missing_reason') == 'issuer_conflict':
                self.problems.append({'kind': 'confirmed_foreign_issuer', 'record_key': key})
            if readonly and not supplied or unresolved:
                self.document_gaps.append({field: row.get(field, '') for field in ['record_key', 'module', 'controller', 'documentid', 'document_number', 'document_date', 'missing_reason', 'reason']})
            ref_file, ref_column, ref_value = row['csv_file'], row['csv_key_column'], row['csv_key_value']
            dataset = next((table['file'] for table in self.tables.values() if table.get('table') == controller), None)
            if external and (ref_file != (dataset or index) or ref_column != ('id' if dataset else 'record_key') or ref_value != (documentid if dataset else key)):
                raise ValueError('Document reference points to another document type')
            if ref_file not in self.csv or ref_column not in self.csv[ref_file]['columns'] or self.contains(ref_file, ref_column, ref_value) != 1:
                raise ValueError('Document CSV reference is missing or ambiguous')
            if dataset:
                business = json.loads(self.db.execute('SELECT data FROM records WHERE package = ? AND file = ? AND record_key = ?', (self.label, dataset, documentid)).fetchone()[0])
                if any(field in row and row[field] != business.get(field) for field in ['deleted', 'completed', 'state', 'cancelled']):
                    raise ValueError('Document workflow fields differ from its business row')
                if business.get(controller + 'id') != row['document_number'] or business.get(controller + 'date') != row['document_date']:
                    raise ValueError('Document number or date differs from its business row')
        manifest = index if external else 'document_manifest.csv'
        if manifest not in self.csv:
            raise ValueError('PDF manifest is missing')
        pdf_rows, used = [], set()
        review_counts = {status: 0 for status in ['archived_original', 'historical_reconstruction_pending_original_comparison', 'historical_diagnostic_reconstruction', 'historical_unreviewed_candidate', 'missing', 'invalid', 'excluded']}
        manifest_paths = set()
        for row in self.rows(manifest):
            path = row.get('pdf_path', row.get('zip_path', ''))
            if row['record_key'] not in keys:
                raise ValueError('Orphan PDF manifest record')
            if not external:
                if row.get('status') not in review_counts or row.get('clientid') != str(self.client):
                    raise ValueError('Invalid review status or tenant')
                review_counts[row['status']] += 1
                if bool(path) != (row['status'] not in {'missing', 'invalid', 'excluded'}):
                    raise ValueError('Review status and PDF availability differ')
                indexed = json.loads(self.db.execute('SELECT data FROM records WHERE package = ? AND file = ? AND record_key = ?', (self.label, index, row['record_key'])).fetchone()[0])
                if any(indexed.get(field) != row.get(field) for field in ['module', 'controller', 'documentid', 'document_number', 'document_date', 'issued', 'deleted', 'completed', 'state', 'cancelled']):
                    raise ValueError('Historical version identity differs from document index')
            if not path:
                continue
            if path not in pdfs or path in used or not path.startswith(('documents/' if external else ('originals/', 'review/', 'diagnostics/', 'candidates/'))):
                raise ValueError('PDF inventory path differs')
            expected_prefix = 'documents/' if external else path.split('/')[0] + '/'
            if not path.startswith(expected_prefix + row['module'] + '/' + row['controller'] + '/'):
                raise ValueError('PDF type assignment differs')
            used.add(path)
            manifest_paths.add((row['record_key'], path))
            if not external and (self.entries[path]['sha256'] != row['sha256'] or self.entries[path]['size'] != int(row['filesize'])):
                raise ValueError('PDF manifest integrity differs')
            identity = {'clientid': self.client, 'module': row['module'], 'controller': row['controller'],
                        'id': int(row['documentid']), 'document_number': row['document_number']}
            pdf_rows.append({'identity': identity, 'path': path, 'sha256': self.entries[path]['sha256'],
                             'declared_issuer_status': row.get('issuer_status', 'not_checked'),
                             'pdf_origin': row.get('pdf_origin', ''), 'role': row.get('status', 'registered_archive')})
        if used != pdfs:
            raise ValueError('PDF inventory coverage differs')
        self.pdf_rows = pdf_rows
        if 'by_type' in docs and docs['by_type'] != self.by_type:
            raise ValueError('Per-type completeness counts differ')
        self.summary = counts
        if external:
            summary = docs['summary']
            for key, value in counts.items():
                if summary[key] != value:
                    raise ValueError('Document summary differs')
            if summary['dated_documents'] != len(keys) or summary['complete'] != (counts['missing'] == 0 and counts['excluded'] == 0):
                raise ValueError('False completeness statement')
        else:
            for indexed in self.rows(index):
                if indexed['zip_path'] and (indexed['record_key'], indexed['zip_path']) not in manifest_paths:
                    raise ValueError('Review index path is not a version of that document')
            summary = docs['summary']
            if any(summary.get(key) != value for key, value in review_counts.items()):
                raise ValueError('Internal review summary differs')
            complete = not any(review_counts[key] for key in ['missing', 'invalid', 'excluded'])
            if summary.get('documents') != len(keys) or summary.get('complete') is not complete or summary.get('issued') != counts['issued'] or summary.get('deleted_issued') != counts['deleted_issued']:
                raise ValueError('Internal review completeness differs')
            self.review_required = True

    def verify_delivery_evidence(self, path):
        if path is None:
            return self.checksums_verified
        proof = bounded_json(path)
        self.delivery_proof = proof
        if (proof.get('clientid') != self.client or proof.get('sha256') != self.package_sha256
                or proof.get('size') != self.path.stat().st_size or proof.get('entries') != self.entries):
            raise ValueError('Independent delivery verification differs')
        return True

    def issuer_review(self, path, scratch, details):
        proof = getattr(self, 'delivery_proof', {})
        if path and proof.get('issuer_review_sha256') and digest(path) != proof['issuer_review_sha256']:
            raise ValueError('Issuer review differs from the attestation used at export')
        decisions = load_issuer_decisions(path, self.client, self.meta.get('tenant_identity_sha256'))
        for pdf in getattr(self, 'pdf_rows', []):
            decision = find_issuer_decision(decisions, pdf['identity'], pdf['sha256'])
            state = issuer_state(decision)
            text = extract_pdf(self.zip, pdf['path'], scratch)
            detail = dict(pdf, issuer_status=state, text_evidence=text, decision=decision,
                          note='Text extraction is a review aid only; no automatic issuer or dispatch confirmation')
            details.write(json.dumps(detail, ensure_ascii=False) + '\n')
            self.pdf_report.append(dict(pdf, issuer_status=state, text_evidence={'status': text['status']}))
            self.issuer_decisions.append({'identity': pdf['identity'], 'pdf_sha256': pdf['sha256'], 'decision': 'unresolved',
                                         'reviewed_by': '', 'reviewed_at': '', 'basis': '', 'actual_issuer': '',
                                         'full_document_reviewed': False, 'issuer_compared_with_tenant': False})
            if state not in {'manually_verified', 'scope_attested'} or text['status'] not in {'text_available', 'no_machine_readable_text'}:
                self.review_required = True
            if state == 'conflict':
                self.problems.append({'kind': 'confirmed_foreign_issuer', 'identity': pdf['identity'], 'sha256': pdf['sha256']})
            if self.meta.get('export_kind') == 'outgoing_documents' and pdf['declared_issuer_status'] not in {'manually_verified', 'scope_attested'}:
                self.review_required = True


def extract_pdf(zip_file, name, scratch):
    path = scratch / 'review.pdf'
    if zip_file.getinfo(name).file_size > 256 * 1024 * 1024:
        return {'status': 'review_required_size_limit'}
    with zip_file.open(name) as source, open(path, 'wb') as target:
        os.chmod(path, 0o600)
        for chunk in iter(lambda: source.read(1024 * 1024), b''):
            target.write(chunk)
    try:
        return extract_pdf_file(path, scratch / 'review.txt')
    finally:
        path.unlink(missing_ok=True)


def extract_pdf_file(path, text):
    if path.stat().st_size > 256 * 1024 * 1024:
        return {'status': 'review_required_size_limit'}
    with open(path, 'rb') as source:
        header = source.read(8)
        source.seek(max(0, path.stat().st_size - 2048))
        tail = source.read()
    if not header.startswith(b'%PDF-') or b'%%EOF' not in tail:
        raise ValueError('PDF structure is invalid')
    def limit_output():
        resource.setrlimit(resource.RLIMIT_FSIZE, (4 * 1024 * 1024, 4 * 1024 * 1024))
        resource.setrlimit(resource.RLIMIT_AS, (512 * 1024 * 1024, 512 * 1024 * 1024))
        resource.setrlimit(resource.RLIMIT_CPU, (20, 20))
    try:
        result = subprocess.run(['pdftotext', '-layout', str(path), str(text)], stdin=subprocess.DEVNULL,
                                stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=20, preexec_fn=limit_output)
        if result.returncode != 0:
            return {'status': 'text_unreadable'}
        os.chmod(text, 0o600)
        value = text.read_text(encoding='utf-8', errors='replace')
        return {'status': 'text_available' if value.strip() else 'no_machine_readable_text', 'text': value}
    except (FileNotFoundError, subprocess.SubprocessError):
        return {'status': 'extractor_unavailable_or_timed_out'}
    finally:
        text.unlink(missing_ok=True)


def compare(before, after, db, changes):
    files = {'added': sorted(after.entries.keys() - before.entries.keys()), 'removed': sorted(before.entries.keys() - after.entries.keys())}
    structure = {}
    for file in sorted(before.csv.keys() | after.csv.keys()):
        a, b = before.csv.get(file), after.csv.get(file)
        if a != b:
            structure[file] = {'before': a, 'after': b, 'added_columns': sorted(set((b or {}).get('columns', [])) - set((a or {}).get('columns', []))), 'removed_columns': sorted(set((a or {}).get('columns', [])) - set((b or {}).get('columns', [])))}
    counts = {key: 0 for key in ['added_business_rows', 'removed_business_rows', 'financial_changes', 'business_changes', 'status_changes', 'completed_normalization_candidates', 'document_inventory_changes', 'value_format_changes', 'newly_delivered_dataset_rows', 'no_longer_delivered_dataset_rows']}
    def record(kind, file, key, detail):
        counts[kind] += 1
        changes.write(json.dumps({'kind': kind, 'file': file, 'internal_key': key, **detail}, ensure_ascii=False) + '\n')
    for side, other, kind in [('before', 'after', 'removed_business_rows'), ('after', 'before', 'added_business_rows')]:
        for file, key, data in db.execute('SELECT a.file, a.record_key, a.data FROM records a LEFT JOIN records b ON b.package = ? AND b.file = a.file AND b.record_key = a.record_key WHERE a.package = ? AND b.record_key IS NULL', (other, side)):
            category = 'document_inventory_changes' if file in TECHNICAL else kind
            if file not in TECHNICAL and file not in (after.csv if side == 'before' else before.csv):
                category = 'no_longer_delivered_dataset_rows' if side == 'before' else 'newly_delivered_dataset_rows'
            record(category, file, key, {side: json.loads(data)})
    for file, key, a, b in db.execute('SELECT a.file, a.record_key, a.data, b.data FROM records a JOIN records b ON b.package = ? AND b.file = a.file AND b.record_key = a.record_key WHERE a.package = ?', ('after', 'before')):
        a, b = json.loads(a), json.loads(b)
        changed = {column: {'before': a[column], 'after': b[column]} for column in a.keys() & b.keys() if a[column] != b[column]}
        if not changed:
            continue
        if file in TECHNICAL:
            record('document_inventory_changes', file, key, {'changes': changed})
            continue
        financial = {column: value for column, value in changed.items() if column in MONEY}
        status_values = {column: value for column, value in changed.items() if column in STATUS}
        business = {column: value for column, value in changed.items() if column not in MONEY | STATUS}
        formats = {}
        for column, value in list(financial.items()):
            try:
                if value['before'] and value['after'] and Decimal(value['before']).is_finite() and Decimal(value['before']) == Decimal(value['after']):
                    formats[column] = financial.pop(column)
            except InvalidOperation:
                pass
        if formats:
            record('value_format_changes', file, key, {'changes': formats})
        if financial:
            record('financial_changes', file, key, {'changes': financial})
        if business:
            record('business_changes', file, key, {'changes': business})
        if status_values:
            normal = set(changed) == {'completed'} and a.get('state') == b.get('state') == '105' and a['completed'] in {'', '0'} and b['completed'] == '1'
            record('completed_normalization_candidates' if normal else 'status_changes', file, key, {'changes': status_values, 'note': 'Requires operator evidence; no financial equivalence or dispatch is inferred'})
    pdfs = {name: proof for name, proof in before.entries.items() if name.endswith('.pdf')}
    next_pdfs = {name: proof for name, proof in after.entries.items() if name.endswith('.pdf')}
    return {'files': files, 'csv_structure_and_counts': structure,
            'table_metadata_changes': {key: {'before': before.tables.get(key), 'after': after.tables.get(key)} for key in before.tables.keys() | after.tables.keys() if before.tables.get(key) != after.tables.get(key)}, 'changes': counts,
            'selection_before': {key: before.meta.get(key) for key in ['clientId', 'from', 'to', 'export_kind']},
            'selection_after': {key: after.meta.get(key) for key in ['clientId', 'from', 'to', 'export_kind']},
            'pdf_inventory': {'added': sorted(next_pdfs.keys() - pdfs.keys()), 'removed': sorted(pdfs.keys() - next_pdfs.keys()),
                              'changed_bytes': sorted(name for name in pdfs.keys() & next_pdfs.keys() if pdfs[name] != next_pdfs[name])},
            'note': 'Added/removed columns and files are structural changes. Shared fields are compared exactly as stored; no amounts are recomputed. Matching technical IDs, not visible numbers, identify business rows. Changed export membership is not proof of database deletion or insertion.'}


def parser():
    result = argparse.ArgumentParser(description=__doc__)
    result.add_argument('--action', required=True, choices=['preflight', 'compare'])
    for option in ['zip', 'before', 'after', 'output', 'verification', 'issuer-review', 'issuer-template']:
        result.add_argument('--' + option)
    result.add_argument('--client', type=int)
    return result


def main(argv=None):
    args = parser().parse_args(argv)
    os.umask(0o077)
    output = private_path(args.output, output=True) if args.output else None
    if not output:
        raise ValueError('A private output path is required')
    report = {'schema_version': 1, 'checked_at': datetime.now(timezone.utc).isoformat(), 'checked_by_uid': os.getuid(), 'result': 'not_releasable',
              'legal_conformity_asserted': False, 'dispatch_asserted': False}
    packages = []
    try:
        template = private_path(args.issuer_template, output=True) if args.issuer_template else None
        if template == output:
            raise ValueError('Report and template must be separate files')
        review = private_path(args.issuer_review) if args.issuer_review else None
        verification = private_path(args.verification) if args.verification else None
        with tempfile.TemporaryDirectory(prefix='dewawi-gobd-private-', dir=output.parent) as temp:
            scratch = Path(temp)
            db = sqlite3.connect(scratch / 'records.sqlite')
            db.execute('CREATE TABLE records (package TEXT, file TEXT, record_key TEXT, data TEXT, PRIMARY KEY(package,file,record_key))')
            db.execute('CREATE TABLE refs (package TEXT, file TEXT, column_name TEXT, value TEXT, record_key TEXT)')
            db.execute('CREATE INDEX reference_lookup ON refs(package,file,column_name,value)')
            if args.action == 'compare':
                if not args.before or not args.after or args.zip or template or review or verification:
                    raise ValueError('Compare requires only two ZIP paths and private output')
                before = Package(private_path(args.before), db, 'before'); packages.append(before)
                after = Package(private_path(args.after), db, 'after'); packages.append(after)
                if before.client != after.client or args.client is not None and args.client != before.client:
                    raise ValueError('Cannot compare foreign tenant packages')
                sidecar = private_path(str(output) + '.changes.jsonl', output=True)
                with open(sidecar, 'x', encoding='utf-8') as changes:
                    report['comparison'] = compare(before, after, db, changes)
                report.update(result='professional_review_required', change_details=str(sidecar),
                              inputs=[{'path': str(item.path), 'sha256': item.package_sha256} for item in packages])
            else:
                if not args.zip or not args.client or args.before or args.after:
                    raise ValueError('Preflight requires ZIP, explicit client and private output')
                package = Package(private_path(args.zip), db, 'current'); packages.append(package)
                if package.client != args.client:
                    raise ValueError('Foreign package tenant')
                independently_verified = package.verify_delivery_evidence(verification)
                pdf_details = private_path(str(output) + '.pdfs.jsonl', output=True)
                with open(pdf_details, 'x', encoding='utf-8') as details:
                    package.issuer_review(review, scratch, details)
                report['pdf_details'] = str(pdf_details)
                if template:
                    if not re.fullmatch('[a-f0-9]{64}', package.meta.get('tenant_identity_sha256', '')):
                        raise ValueError('A current tenant identity fingerprint is required for an issuer template')
                    write_json(template, {'schema_version': 1, 'clientid': package.client,
                                          'tenant_identity_sha256': package.meta['tenant_identity_sha256'], 'decisions': package.issuer_decisions})
                blocked = any(item['kind'] == 'confirmed_foreign_issuer' for item in package.problems)
                review_needed = bool(package.problems or package.document_gaps or package.review_required or not independently_verified)
                report.update(result='not_releasable' if blocked else ('professional_review_required' if review_needed else 'technically_verified'),
                              input={'path': str(package.path), 'sha256': package.package_sha256}, clientid=package.client,
                              independent_integrity_verified=independently_verified, csv_files=package.csv, by_type=package.by_type,
                              gaps=package.document_gaps, findings=package.problems, pdfs=package.pdf_report,
                              scope={'from': package.meta.get('from'), 'to': package.meta.get('to'), 'kind': package.meta.get('export_kind')})
            for package in packages:
                if digest(package.path) != package.package_sha256 or package.path.stat().st_size != package.package_size:
                    raise ValueError('Input ZIP changed during verification')
            db.close()
    except (ValueError, KeyError, TypeError, AttributeError, OSError, sqlite3.Error, zipfile.BadZipFile, UnicodeError, csv.Error, RuntimeError, struct.error, zlib.error) as error:
        report['result'] = 'not_releasable'
        report['error'] = str(error)
    finally:
        for package in packages:
            package.close()
    write_json(output, report)
    print('Private report written; technical success is not a legal conformity statement.')
    return {'technically_verified': 0, 'professional_review_required': 1, 'not_releasable': 2}[report['result']]


if __name__ == '__main__':
    try:
        raise SystemExit(main())
    except (ValueError, OSError):
        print('Private operator paths or arguments are invalid; no package was modified.')
        raise SystemExit(2)
