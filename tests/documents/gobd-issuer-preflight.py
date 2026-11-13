"""Private read-only archive screening, explicit scope attestations and package release."""
import argparse
import csv
from datetime import datetime, timezone
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import sqlite3
import stat
import subprocess
import sys
import tempfile
import zipfile

sys.dont_write_bytecode = True
spec = importlib.util.spec_from_file_location('gobd_package', Path(__file__).with_name('gobd-package.py'))
package = importlib.util.module_from_spec(spec)
spec.loader.exec_module(package)


def normalized(value):
    return ''.join(character for character in value.casefold() if character.isalnum())


def issuer_profile(path, client, context):
    if path is None:
        return None
    value = package.bounded_json(path)
    if value.get('schema_version') != 1 or value.get('clientid') != client or value.get('tenant_identity_sha256') != context:
        raise ValueError('Screening profile context differs')
    for identity in [value['expected_issuer'], *value.get('foreign_issuers', [])]:
        if len(normalized(identity.get('legal_name', ''))) < 5 or not identity.get('identifiers') or any(len(normalized(item)) < 6 for item in identity['identifiers']):
            raise ValueError('Screening needs a legal name and independent substantial identifiers')
    return value


def classify(text, profile):
    if profile is None or text['status'] != 'text_available':
        return 'unclear', {'reason': 'profile_or_machine_readable_issuer_unavailable', 'text_status': text['status']}
    blocks = [normalized('\n'.join([line for line in page.splitlines() if line.strip()][-12:])) for page in text.get('text', '').split('\f')]
    def matches(identity):
        return any(normalized(identity['legal_name']) in block and any(normalized(identifier) in block for identifier in identity['identifiers']) for block in blocks)
    expected = matches(profile['expected_issuer'])
    foreign = [identity['legal_name'] for identity in profile.get('foreign_issuers', []) if matches(identity)]
    evidence = {'expected_name_and_identifier_found': expected, 'foreign_name_and_identifier_candidates': foreign,
                'method': 'same-page footer-region name plus independent identifier; screening only, no legal confirmation'}
    return ('contradictory_issuer_evidence' if foreign else ('unobtrusive_candidate' if expected else 'unclear')), evidence


def inventory_rows(path):
    hasher = hashlib.sha256()
    with open(path, 'rb') as source:
        first = source.readline()
        header = json.loads(first)
        if header.get('kind') != 'header' or header.get('schema_version') != 1 or header.get('mode') != 'registered_archive_read_only':
            raise ValueError('A complete registered archive inventory is required')
        hasher.update(first)
        yield header
        count = 0
        for line in source:
            value = json.loads(line)
            if value.get('kind') == 'summary':
                if value.get('content_sha256') != hasher.hexdigest() or value['counts']['registered'] != count or source.read(1):
                    raise ValueError('Inventory summary differs')
                return
            if value.get('kind') not in {'archive', 'invalid_archive'}:
                raise ValueError('Invalid inventory row')
            hasher.update(line); count += 1
            yield value
    raise ValueError('Incomplete inventory; no successful footer')


def archive_path(header, row):
    identity, pdf = row['identity'], row['pdf']
    package.validate_issuer_identity(identity, pdf['sha256'], header['clientid'])
    base = Path(header['base'])
    if not base.is_absolute() or base.resolve() != base:
        raise ValueError('Archive base is not canonical')
    client = str(identity['clientid'])
    relative = Path('files/documents') / client[0] / (client[1] if len(client) > 1 else '0') / client / identity['module'] / identity['controller'] / str(identity['id'] // 1000) / (str(identity['id']) + '.pdf')
    path = base / relative
    if str(path) != pdf['path'] or path.resolve() != path or any(parent.is_symlink() for parent in [path, *path.parents]) or not path.is_file():
        raise ValueError('Archive path does not match tenant and document identity')
    if path.stat().st_size != pdf['size'] or package.digest(path) != pdf['sha256']:
        raise ValueError('Archive PDF size or SHA differs')
    return path


def prepare_profile(args):
    iterator = inventory_rows(package.private_path(args.inventory))
    header = next(iterator)
    if header['clientid'] != args.client:
        raise ValueError('Foreign inventory tenant')
    for _ in iterator:
        pass
    return dict(schema_version=1, clientid=args.client, tenant_identity_sha256=header['tenant_identity_sha256'],
                expected_issuer=dict(legal_name='', identifiers=[]), foreign_issuers=[],
                note='Populate actual issuer name and independent identifiers privately; this profile is not an issuer confirmation.')


def scan(args, output):
    inventory = package.private_path(args.inventory)
    source_hash = package.digest(inventory)
    iterator = inventory_rows(inventory); header = next(iterator)
    if header['clientid'] != args.client:
        raise ValueError('Foreign inventory tenant')
    context = header['tenant_identity_sha256']
    review_path = package.private_path(args.issuer_review) if args.issuer_review else None
    decisions = package.load_issuer_decisions(review_path, args.client, context)
    profile_path = package.private_path(args.profile) if args.profile else None
    profile = issuer_profile(profile_path, args.client, context)
    cache_path = package.private_path(args.cache, output=not Path(args.cache).exists())
    if cache_path == output:
        raise ValueError('Cache and report must be separate files')
    new_cache = not cache_path.exists()
    db = sqlite3.connect(cache_path)
    os.chmod(cache_path, 0o600)
    if new_cache:
        db.execute('PRAGMA application_id = 1145395017')
    elif db.execute('PRAGMA application_id').fetchone()[0] != 1145395017:
        db.close()
        raise ValueError('Existing file is not an issuer text cache')
    db.execute('CREATE TABLE IF NOT EXISTS texts (sha TEXT, extractor TEXT, data TEXT, PRIMARY KEY(sha,extractor))')
    db.execute('CREATE TEMP TABLE findings (priority INTEGER, data TEXT)')
    try:
        extractor = subprocess.run(['pdftotext', '-v'], capture_output=True, timeout=10)
        version = hashlib.sha256(extractor.stdout + extractor.stderr).hexdigest()
    except (FileNotFoundError, subprocess.SubprocessError):
        version = 'extractor-unavailable'
    results_path = package.private_path(str(output) + '.results.jsonl', output=True)
    priority_path = package.private_path(str(output) + '.priority.csv', output=True)
    counts, reused, extracted, hits, seen = {}, 0, 0, 0, set()
    priorities = {'invalid_archive': 0, 'confirmed_foreign_issuer': 0, 'contradictory_issuer_evidence': 1, 'unclear': 2, 'unobtrusive_candidate': 3, 'confirmed_same_issuer': 4}
    try:
        with tempfile.TemporaryDirectory(prefix='issuer-screen-', dir=output.parent) as temporary, open(results_path, 'x', encoding='utf-8') as results:
            for row in iterator:
                identity = row['identity']
                if identity.get('clientid') != args.client:
                    raise ValueError('Foreign inventory row')
                key = '/'.join(str(identity[field]) for field in ['clientid', 'module', 'controller', 'id'])
                if key in seen:
                    raise ValueError('Duplicate inventory identity')
                seen.add(key)
                result = dict(identity=identity, pdf_sha256=row.get('pdf', {}).get('sha256'), screening_status='invalid_archive',
                              legal_issuer_confirmed=False, evidence={}, pdf_origin=row.get('pdf_origin'))
                try:
                    if row['kind'] == 'invalid_archive':
                        raise ValueError(row.get('reason', 'Archive registration is invalid'))
                    path = archive_path(header, row)
                    if row.get('pdf_origin') == 'reconstruction_origin_unconfirmed':
                        raise ValueError('Archive reconstruction lacks original approval')
                    previous = package.find_issuer_decision(decisions, identity, row['pdf']['sha256'])
                    state = package.issuer_state(previous)
                    if state in {'manually_verified', 'scope_attested', 'conflict'}:
                        result.update(screening_status='confirmed_foreign_issuer' if state == 'conflict' else 'confirmed_same_issuer',
                                      legal_issuer_confirmed=state != 'conflict', evidence={'method': state, 'previous_decision': previous})
                        reused += 1
                    else:
                        cached = db.execute('SELECT data FROM texts WHERE sha = ? AND extractor = ?', (row['pdf']['sha256'], version)).fetchone()
                        if cached:
                            text = json.loads(cached[0]); hits += 1
                        else:
                            text = package.extract_pdf_file(path, Path(temporary) / 'text.txt')
                            if package.digest(path) != row['pdf']['sha256']:
                                raise ValueError('Archive PDF changed during text extraction')
                            if text['status'] in {'text_available', 'no_machine_readable_text'}:
                                db.execute('INSERT OR REPLACE INTO texts VALUES (?,?,?)', (row['pdf']['sha256'], version, json.dumps(text)))
                            extracted += 1
                        status, evidence = classify(text, profile)
                        result.update(screening_status=status, evidence=evidence)
                except (ValueError, OSError) as error:
                    result['evidence'] = {'reason': str(error)}
                status = result['screening_status']
                counts[status] = counts.get(status, 0) + 1
                serialized = json.dumps(result, ensure_ascii=False)
                results.write(serialized + '\n')
                db.execute('INSERT INTO findings VALUES (?,?)', (priorities[status], serialized))
            db.commit()
        with open(priority_path, 'x', encoding='utf-8', newline='') as target:
            fields = ['priority', 'module', 'controller', 'id', 'document_number', 'pdf_sha256', 'screening_status', 'evidence']
            writer = csv.DictWriter(target, fieldnames=fields, delimiter=';'); writer.writeheader()
            for priority, data in db.execute('SELECT priority,data FROM findings WHERE priority < 3 ORDER BY priority,rowid'):
                row = json.loads(data)
                writer.writerow(dict(priority=priority, **{key: row['identity'][key] for key in ['module','controller','id','document_number']},
                                     pdf_sha256=row['pdf_sha256'], screening_status=row['screening_status'], evidence=json.dumps(row['evidence'], ensure_ascii=False)))
    finally:
        db.close()
    if package.digest(inventory) != source_hash:
        raise ValueError('Inventory changed during screening')
    return dict(schema_version=1, clientid=args.client, tenant_identity_sha256=context, inventory_sha256=source_hash,
                profile_sha256=package.digest(profile_path) if profile_path else None, issuer_review_sha256=package.digest(review_path) if review_path else None,
                counts=counts, reused_attestations=reused, extracted=extracted, cache_hits=hits, results=str(results_path), results_sha256=package.digest(results_path),
                priority_csv=str(priority_path), priority_sha256=package.digest(priority_path), result='professional_review_required',
                note='Unobtrusive text findings are candidates only. No manual or legal issuer confirmation is generated.')


def prepare_batch(args, output):
    report_path = package.private_path(args.screening_report)
    report = package.bounded_json(report_path)
    if report['clientid'] != args.client or report.get('result') != 'professional_review_required':
        raise ValueError('Invalid screening context')
    results_path = package.private_path(report['results'])
    if package.digest(results_path) != report['results_sha256']:
        raise ValueError('Screening results changed')
    current_path = package.private_path(args.issuer_review) if args.issuer_review else None
    existing = package.load_issuer_decisions(current_path, args.client, report['tenant_identity_sha256'])
    record = package.bounded_json(current_path, 128 * 1024 * 1024) if current_path else dict(schema_version=1, clientid=args.client, tenant_identity_sha256=report['tenant_identity_sha256'], decisions=[])
    record.pop('result', None)
    selected = []
    with open(results_path, encoding='utf-8') as source:
        for line in source:
            row = json.loads(line)
            if row['screening_status'] != 'unobtrusive_candidate' or row['identity']['module'] != 'sales':
                continue
            if package.issuer_state(package.find_issuer_decision(existing, row['identity'], row['pdf_sha256'])) != 'review_required':
                continue
            selected.append({key: row[key] for key in ['identity', 'pdf_sha256', 'screening_status']})
    if not selected:
        raise ValueError('No new unobtrusive candidates; no scope declaration generated')
    scope_path = package.private_path(args.scope_output, output=True)
    if scope_path == output:
        raise ValueError('Scope and declaration must be separate files')
    package.write_json(scope_path, dict(schema_version=1, clientid=args.client, tenant_identity_sha256=report['tenant_identity_sha256'],
                                       screening_report_sha256=package.digest(report_path), profile_sha256=report['profile_sha256'], documents=selected))
    record.setdefault('batches', []).append(dict(scope_file=str(scope_path), scope_sha256=package.digest(scope_path), decision='unresolved', reviewed_by='', reviewed_at='',
                                               basis='', actual_issuer='', issuer_compared_with_tenant=False, scope_reviewed=False, independent_issuer_evidence_confirmed=False))
    return record


def prepare_release(args):
    path = package.private_path(args.preflight)
    report = package.bounded_json(path)
    if report.get('result') not in {'technically_verified', 'professional_review_required'} or report.get('clientid') != args.client:
        raise ValueError('A technically readable matching preflight is required')
    return dict(schema_version=1, clientid=args.client, package_sha256=report['input']['sha256'], preflight_sha256=package.digest(path),
                approved_for_delivery=False, limitations_acknowledged=False, reviewed_by='', reviewed_at='', basis='')


def verify_release(args, output):
    original_path = package.private_path(args.preflight)
    original = package.bounded_json(original_path)
    declaration_path = package.private_path(args.release)
    declaration = package.bounded_json(declaration_path)
    zip_path = package.private_path(args.zip)
    if (declaration.get('schema_version') != 1 or declaration.get('clientid') != args.client or original.get('clientid') != args.client
            or declaration.get('package_sha256') != package.digest(zip_path) or original.get('input', {}).get('sha256') != declaration['package_sha256']
            or declaration.get('preflight_sha256') != package.digest(original_path) or declaration.get('approved_for_delivery') is not True
            or declaration.get('limitations_acknowledged') is not True):
        raise ValueError('Package release scope is invalid or unconfirmed')
    for field in ['reviewed_by', 'reviewed_at', 'basis']:
        if not isinstance(declaration.get(field), str) or not declaration[field].strip():
            raise ValueError('Actual release reviewer, time and basis are required')
    reviewed = datetime.strptime(declaration['reviewed_at'], '%Y-%m-%dT%H:%M:%S%z')
    if reviewed.isoformat(timespec='seconds') != declaration['reviewed_at'] or reviewed > datetime.now(timezone.utc):
        raise ValueError('Invalid release time')
    if original.get('result') not in {'technically_verified', 'professional_review_required'} or original.get('scope', {}).get('kind') != 'outgoing_documents':
        raise ValueError('Only an external outgoing package can be released')
    with tempfile.TemporaryDirectory(prefix='release-check-', dir=output.parent) as temporary:
        fresh = Path(temporary) / 'preflight.json'
        command = [sys.executable, str(Path(package.__file__)), '--action=preflight', '--client=' + str(args.client), '--zip=' + str(zip_path),
                   '--verification=' + str(package.private_path(args.verification)), '--issuer-review=' + str(package.private_path(args.issuer_review)), '--output=' + str(fresh)]
        result = subprocess.run(command, capture_output=True)
        if result.returncode not in {0, 1}:
            raise ValueError('Current package preflight blocks release')
        checked = package.bounded_json(fresh)
        if checked.get('independent_integrity_verified') is not True or any(row['issuer_status'] not in {'manually_verified', 'scope_attested'} for row in checked.get('pdfs', [])):
            raise ValueError('Included PDFs lack current issuer evidence')
        with zipfile.ZipFile(zip_path) as source:
            context = json.loads(source.read('metadata.json'))['tenant_identity_sha256']
        current_path = package.private_path(args.current_issuer_review)
        current = package.load_issuer_decisions(current_path, args.client, context)
        for row in checked.get('pdfs', []):
            state = package.issuer_state(package.find_issuer_decision(current, row['identity'], row['sha256']))
            if state not in {'manually_verified', 'scope_attested'}:
                raise ValueError('Current issuer decisions block this package')
    if package.digest(zip_path) != declaration['package_sha256']:
        raise ValueError('Package changed during release verification')
    return dict(schema_version=1, clientid=args.client, package_sha256=declaration['package_sha256'], release_sha256=package.digest(declaration_path),
                preflight_sha256=declaration['preflight_sha256'], current_issuer_review_sha256=package.digest(current_path), result='professional_package_release_verified', declaration=declaration,
                note='Separate release of these exact package bytes; no PDF status, business data or historical issuer evidence was changed.')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--action', required=True, choices=['prepare-profile', 'scan', 'prepare-batch', 'prepare-release', 'verify-release'])
    parser.add_argument('--client', required=True, type=int)
    for name in ['inventory','profile','cache','issuer-review','current-issuer-review','screening-report','scope-output','preflight','release','zip','verification','output']:
        parser.add_argument('--' + name)
    args = parser.parse_args()
    os.umask(0o077)
    try:
        output = package.private_path(args.output, output=True)
    except (ValueError, OSError, TypeError):
        print('A new private output file is required.'); return 2
    try:
        if args.client <= 0:
            raise ValueError('A positive explicit tenant is required')
        if args.action == 'prepare-profile': report = prepare_profile(args)
        elif args.action == 'scan': report = scan(args, output)
        elif args.action == 'prepare-batch': report = prepare_batch(args, output)
        elif args.action == 'prepare-release': report = prepare_release(args)
        else: report = verify_release(args, output)
        report.setdefault('created_at', datetime.now(timezone.utc).isoformat())
        package.write_json(output, report)
        print('Private operator output written; no automatic issuer or package approval.')
        return 0 if args.action == 'verify-release' and report.get('result') == 'professional_package_release_verified' else 1
    except (ValueError, OSError, TypeError, KeyError, AttributeError, sqlite3.Error, subprocess.SubprocessError, zipfile.BadZipFile) as error:
        package.write_json(output, dict(result='not_releasable', error=str(error)))
        print('Private operator report records a blocked operation.'); return 2


if __name__ == '__main__':
    raise SystemExit(main())
