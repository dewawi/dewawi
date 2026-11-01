from hashlib import sha256
from pathlib import Path, PurePosixPath
import os
import shutil
import stat
import subprocess
import tarfile
import tempfile
import time


ROOT = Path(__file__).resolve().parents[2]
checks = 0


def check(condition, message):
    global checks
    if not condition:
        raise AssertionError(message)
    checks += 1


def run(command, **kwargs):
    result = subprocess.run(command, text=True, stdout=subprocess.PIPE,
                            stderr=subprocess.STDOUT, **kwargs)
    if result.returncode:
        raise RuntimeError('$ ' + ' '.join(map(str, command)) + '\n' + result.stdout)
    return result.stdout


def file_hash(path):
    digest = sha256()
    with path.open('rb') as handle:
        for block in iter(lambda: handle.read(1024 * 1024), b''):
            digest.update(block)
    return digest.hexdigest()


def persistent_files(source):
    result = {}
    for top in ('files', 'media', 'templates'):
        for path in sorted((source / top).rglob('*')):
            relative = path.relative_to(source)
            if relative.parts[:2] == ('files', 'import') or not path.is_file():
                continue
            result[relative.as_posix()] = (file_hash(path), stat.S_IMODE(path.stat().st_mode))
    for relative in ('configs/database.ini', 'configs/magento.ini'):
        path = source / relative
        if path.exists():
            result[relative] = (file_hash(path), stat.S_IMODE(path.stat().st_mode))
    return result


def reject_symlinks(source):
    for top in ('files', 'media', 'templates'):
        for path in (source / top).rglob('*'):
            if path.is_symlink():
                raise RuntimeError('persistent path contains a symbolic link: ' + str(path))


def create_file_backup(source, target):
    required = source / 'configs/database.ini'
    if not required.is_file():
        raise RuntimeError('configs/database.ini is missing')
    reject_symlinks(source)
    target.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    partial = target.with_suffix(target.suffix + '.partial')
    partial.unlink(missing_ok=True)
    command = [
        'tar', '--create', '--file', str(partial), '--numeric-owner',
        '--directory', str(source), '--exclude=files/import',
        '--exclude=files/import/**', '--', 'files', 'media', 'templates',
        'configs/database.ini'
    ]
    if (source / 'configs/magento.ini').exists():
        command.append('configs/magento.ini')
    try:
        run(command)
        if not partial.is_file() or partial.stat().st_size == 0:
            raise RuntimeError('file archive is empty')
        partial.chmod(0o600)
        partial.replace(target)
    except Exception:
        partial.unlink(missing_ok=True)
        raise


def inspect_archive(archive, target, required=None):
    names = set()
    with tarfile.open(archive, 'r') as handle:
        for member in handle.getmembers():
            path = PurePosixPath(member.name)
            if path.is_absolute() or '..' in path.parts or not path.parts:
                raise RuntimeError('unsafe archive member: ' + member.name)
            if path.parts[0] not in ('files', 'media', 'templates', 'configs'):
                raise RuntimeError('unexpected archive member: ' + member.name)
            if member.issym() or member.islnk() or member.isdev():
                raise RuntimeError('unsupported archive member: ' + member.name)
            destination = target.joinpath(*path.parts)
            if destination.exists() and not (member.isdir() and destination.is_dir()):
                raise RuntimeError('restore would overwrite: ' + member.name)
            names.add(path.as_posix().rstrip('/'))
    for name in required or ():
        if name not in names:
            raise RuntimeError('required archive member is missing: ' + name)
    return names


def restore_files(archive, target):
    expected = persistent_files_from_archive(archive)
    inspect_archive(archive, target, expected)
    target.mkdir(mode=0o700, parents=True, exist_ok=True)
    run(['tar', '--extract', '--file', str(archive), '--directory', str(target),
         '--no-same-owner', '--same-permissions', '--keep-old-files'])


def persistent_files_from_archive(archive):
    result = set()
    with tarfile.open(archive, 'r') as handle:
        for member in handle.getmembers():
            if member.isfile():
                result.add(PurePosixPath(member.name).as_posix())
    return result


def create_database_backup(client_file, database, target):
    target.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    partial = target.with_suffix(target.suffix + '.partial')
    partial.unlink(missing_ok=True)
    command = [
        'mariadb-dump', '--defaults-extra-file=' + str(client_file),
        '--single-transaction', '--quick', '--routines', '--events', '--triggers',
        '--hex-blob', '--default-character-set=utf8mb4', '--databases', database
    ]
    try:
        with partial.open('wb') as handle:
            result = subprocess.run(command, stdout=handle, stderr=subprocess.PIPE)
        if result.returncode:
            raise RuntimeError(result.stderr.decode('utf-8', 'replace'))
        if partial.stat().st_size == 0:
            raise RuntimeError('database dump is empty')
        partial.chmod(0o600)
        partial.replace(target)
    except Exception:
        partial.unlink(missing_ok=True)
        raise


def create_code_backup(root, commit, target):
    target.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    partial = target.with_suffix(target.suffix + '.partial')
    partial.unlink(missing_ok=True)
    try:
        run(['git', '-C', str(root), 'archive', '--format=tar',
             '--output=' + str(partial), commit])
        if not partial.is_file() or partial.stat().st_size == 0:
            raise RuntimeError('code archive is empty')
        partial.chmod(0o600)
        partial.replace(target)
    except Exception:
        partial.unlink(missing_ok=True)
        raise


def query(client_file, sql, database=None):
    command = ['mariadb', '--defaults-extra-file=' + str(client_file),
               '--batch', '--skip-column-names']
    if database:
        command.append(database)
    result = subprocess.run(command, input=sql, text=True, stdout=subprocess.PIPE,
                            stderr=subprocess.STDOUT)
    if result.returncode:
        raise RuntimeError(result.stdout)
    return result.stdout.strip()


def fixture_files(source):
    fixtures = {
        'files/contacts/7/0/7/invoice.pdf': b'issued document',
        'files/attachments/7/0/7/original.pdf': b'protected attachment',
        'files/items/501/specification.pdf': b'item document',
        'files/downloads/7/0/7/manual.pdf': b'customer download',
        'files/images/logo.png': b'private image',
        'files/export/7/0/7/export.csv': b'id,value\n1,test\n',
        'files/import/7/0/7/pending.csv': b'temporary import',
        'media/7/0/7/images/product.jpg': b'public tenant 7 media',
        'media/8/0/8/images/product.jpg': b'public tenant 8 media',
        'templates/modules/items/itemlist/custom.phtml': b'custom template',
        'configs/database.ini': b'[production]\nresources.db.params.dbname = database\n',
        'configs/magento.ini': b'[production]\nurl = example.invalid\n',
        'cache/thumbs/media/generated.jpg': b'regenerable thumbnail',
        'session/sess_test': b'regenerable session',
        'logs/application.log': b'operational log',
    }
    for relative, content in fixtures.items():
        path = source / relative
        path.parent.mkdir(mode=0o750, parents=True, exist_ok=True)
        path.write_bytes(content)
        path.chmod(0o600 if relative.startswith('configs/') else 0o640)


def test_code(work, commit):
    archive = work / 'backup' / 'application.tar'
    create_code_backup(ROOT, commit, archive)
    check(archive.is_file() and archive.stat().st_size > 0, 'code archive is empty')
    check(stat.S_IMODE(archive.stat().st_mode) == 0o600, 'code archive mode is not 0600')
    restored = work / 'restored-code'
    restored.mkdir(mode=0o700)
    files = ['index.php', 'install/config/structure.sql',
             'install/config/update-1.1.7-1.1.8.sql']
    run(['tar', '--extract', '--file', str(archive), '--directory', str(restored),
         '--same-permissions', '--no-same-owner', '--'] + files)
    for relative in files:
        expected = subprocess.run(
            ['git', '-C', str(ROOT), 'show', commit + ':' + relative],
            stdout=subprocess.PIPE, stderr=subprocess.STDOUT, check=True).stdout
        check((restored / relative).read_bytes() == expected,
              'restored code differs from recorded commit: ' + relative)
    check(not (restored / 'configs/database.ini').exists(),
          'ignored installation secret was included in code archive')


def test_files(work):
    source = work / 'source'
    backup = work / 'backup' / 'files.tar'
    source.mkdir(mode=0o700)
    fixture_files(source)

    outside = work / 'outside-secret'
    outside.write_text('must not be archived')
    link = source / 'media/7/0/7/images/outside'
    link.symlink_to(outside)
    try:
        create_file_backup(source, backup)
    except RuntimeError:
        pass
    else:
        raise AssertionError('symbolic link was accepted')
    check(not backup.exists(), 'failed file backup left a final archive')
    link.unlink()

    expected = persistent_files(source)
    create_file_backup(source, backup)
    check(backup.is_file() and backup.stat().st_size > 0, 'file archive is empty')
    check(stat.S_IMODE(backup.stat().st_mode) == 0o600, 'file archive mode is not 0600')
    names = inspect_archive(backup, work / 'inspect', expected)
    check('files/import/7/0/7/pending.csv' not in names, 'temporary import was archived')
    check(not any(name.startswith(('cache/', 'session/', 'logs/')) for name in names),
          'runtime data was archived')

    restored = work / 'restored'
    restore_files(backup, restored)
    actual = persistent_files(restored)
    check(actual == expected, 'restored persistent files or modes differ')
    check((restored / 'files/attachments/7/0/7/original.pdf').is_file(),
          'protected attachment moved out of its private hierarchy')
    check((restored / 'media/7/0/7/images/product.jpg').read_bytes() !=
          (restored / 'media/8/0/8/images/product.jpg').read_bytes(),
          'tenant media was merged')
    check(not (restored / 'files/import').exists(), 'temporary import was restored')
    check(not any((restored / name).exists() for name in ('cache', 'session', 'logs')),
          'runtime directories were restored')

    collision = work / 'collision'
    collision_file = collision / 'media/7/0/7/images/product.jpg'
    collision_file.parent.mkdir(parents=True)
    collision_file.write_bytes(b'existing media')
    try:
        restore_files(backup, collision)
    except RuntimeError:
        pass
    else:
        raise AssertionError('existing media was accepted for overwrite')
    check(collision_file.read_bytes() == b'existing media', 'existing media was overwritten')
    check(not (collision / 'files').exists(), 'restore wrote files before collision check')

    recorded_hash = file_hash(backup)
    damaged = work / 'damaged.tar'
    shutil.copy2(backup, damaged)
    with damaged.open('r+b') as handle:
        handle.seek(-1, os.SEEK_END)
        handle.write(b'X')
    check(file_hash(damaged) != recorded_hash, 'archive corruption was not detectable')

    missing_source = work / 'missing-config'
    shutil.copytree(source, missing_source)
    (missing_source / 'configs/database.ini').unlink()
    try:
        create_file_backup(missing_source, work / 'backup' / 'incomplete.tar')
    except RuntimeError:
        pass
    else:
        raise AssertionError('backup without database configuration was accepted')
    check(not (work / 'backup' / 'incomplete.tar').exists(),
          'incomplete file backup left a final archive')


def test_database(work):
    data = work / 'mariadb'
    run(['mariadb-install-db', '--no-defaults', '--datadir=' + str(data),
         '--auth-root-authentication-method=normal', '--skip-test-db'])
    socket = work / 'mariadb.sock'
    server = subprocess.Popen([
        'mariadbd', '--no-defaults', '--datadir=' + str(data),
        '--socket=' + str(socket), '--skip-networking',
        '--pid-file=' + str(work / 'mariadb.pid'),
        '--log-error=' + str(work / 'mariadb.log'), '--innodb-buffer-pool-size=32M',
        '--innodb-log-file-size=8M'
    ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(200):
            if server.poll() is not None:
                raise RuntimeError((work / 'mariadb.log').read_text())
            if socket.exists():
                break
            time.sleep(0.05)
        else:
            raise RuntimeError('temporary MariaDB did not start')

        client = work / 'client.cnf'
        client.write_text('[client]\nuser=root\nsocket=' + str(socket) + '\n')
        client.chmod(0o600)
        check(stat.S_IMODE(client.stat().st_mode) == 0o600, 'client file mode is not 0600')
        database = 'dewawi_recovery_test'
        query(client, """
            CREATE DATABASE dewawi_recovery_test CHARACTER SET utf8mb4;
            USE dewawi_recovery_test;
            CREATE TABLE tenant (id int PRIMARY KEY, name varchar(80) NOT NULL) ENGINE=InnoDB;
            CREATE TABLE document (
                id int PRIMARY KEY AUTO_INCREMENT,
                clientid int NOT NULL,
                body varchar(120) NOT NULL,
                INDEX clientid (clientid)
            ) ENGINE=InnoDB;
            CREATE TRIGGER document_trim BEFORE INSERT ON document
                FOR EACH ROW SET NEW.body = TRIM(NEW.body);
            CREATE PROCEDURE document_count() SELECT COUNT(*) FROM document;
            CREATE EVENT future_maintenance ON SCHEDULE AT CURRENT_TIMESTAMP + INTERVAL 1 DAY
                DO DELETE FROM document WHERE id < 0;
            INSERT INTO tenant VALUES (7, 'Mandant Sieben'), (8, 'Mandant Acht');
            INSERT INTO document (clientid, body) VALUES
                (7, '  Wärmepumpe  '), (8, 'Kaltwassersätze');
        """)
        expected_rows = query(client, 'SELECT clientid, body FROM document ORDER BY id;', database)
        expected_schema = query(client, 'SHOW CREATE TABLE document;', database)

        dump = work / 'backup' / 'database.sql'
        create_database_backup(client, database, dump)
        check(dump.is_file() and dump.stat().st_size > 0, 'database dump is empty')
        check(stat.S_IMODE(dump.stat().st_mode) == 0o600, 'database dump mode is not 0600')
        dump_text = dump.read_text(errors='replace')
        check('document_trim' in dump_text, 'trigger is missing from dump')
        check('document_count' in dump_text, 'routine is missing from dump')
        check('future_maintenance' in dump_text, 'event is missing from dump')

        try:
            create_database_backup(client, 'missing_database', work / 'backup' / 'failed.sql')
        except RuntimeError:
            pass
        else:
            raise AssertionError('invalid database backup succeeded')
        check(not (work / 'backup' / 'failed.sql').exists(),
              'failed database backup left a final dump')

        query(client, 'DROP DATABASE ' + database + ';')
        with dump.open('rb') as handle:
            result = subprocess.run(
                ['mariadb', '--defaults-extra-file=' + str(client)], stdin=handle,
                stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
        if result.returncode:
            raise RuntimeError(result.stdout.decode('utf-8', 'replace'))
        check(query(client, 'SELECT clientid, body FROM document ORDER BY id;', database) ==
              expected_rows, 'database rows changed during recovery')
        check(query(client, 'SHOW CREATE TABLE document;', database) == expected_schema,
              'database schema changed during recovery')
        check(query(client, "SELECT COUNT(*) FROM information_schema.TRIGGERS "
                            "WHERE TRIGGER_SCHEMA='dewawi_recovery_test';") == '1',
              'trigger was not recovered')
        check(query(client, "SELECT COUNT(*) FROM information_schema.ROUTINES "
                            "WHERE ROUTINE_SCHEMA='dewawi_recovery_test';") == '1',
              'routine was not recovered')
        check(query(client, "SELECT COUNT(*) FROM information_schema.EVENTS "
                            "WHERE EVENT_SCHEMA='dewawi_recovery_test';") == '1',
              'event was not recovered')
        query(client, "INSERT INTO document (clientid, body) VALUES (7, '  recovered  ');",
              database)
        check(query(client, 'SELECT body FROM document WHERE id=3;', database) == 'recovered',
              'recovered trigger is not functional')
        check(query(client, 'SELECT GROUP_CONCAT(id ORDER BY id) FROM tenant;', database) == '7,8',
              'tenant rows were not recovered separately')
        query(client, 'ALTER TABLE document ADD recovery_mismatch int NULL;', database)
        check(query(client, 'SHOW CREATE TABLE document;', database) != expected_schema,
              'schema difference was not detected')
    finally:
        server.terminate()
        server.wait(timeout=20)


def main():
    previous_umask = os.umask(0o077)
    work = Path(tempfile.mkdtemp(prefix='dewawi-backup-recovery-'))
    try:
        work.chmod(0o700)
        check(stat.S_IMODE(work.stat().st_mode) == 0o700, 'backup workspace mode is not 0700')
        commit = run(['git', '-C', str(ROOT), 'rev-parse', 'HEAD']).strip()
        check(len(commit) == 40 and all(character in '0123456789abcdef' for character in commit),
              'source commit was not recorded')
        run(['git', '-C', str(ROOT), 'cat-file', '-e', commit + '^{commit}'])
        check(True, 'recorded source commit is not available')
        test_code(work, commit)
        test_files(work)
        test_database(work)
        print('Backup and isolated recovery: PASS (' + str(checks) + ' checks)')
    finally:
        shutil.rmtree(work)
        os.umask(previous_umask)


if __name__ == '__main__':
    main()
