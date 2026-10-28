#!/usr/bin/env python3
"""Exercise real PHP/Zend sessions with isolated files and HTTP cookies, without a database."""
from pathlib import Path
import http.client
import hashlib
import json
import os
import socket
import subprocess
import tempfile
import time
from urllib.parse import urlencode

root = Path(__file__).resolve().parents[2]
php = os.environ.get('PHP_BINARY', 'php')
front = r'''<?php
error_reporting(E_ALL & ~E_DEPRECATED);
define('BASE_PATH', __DIR__);
define('APPLICATION_PATH', ROOT_PATH . '/application');
set_include_path(ROOT_PATH . '/library' . PATH_SEPARATOR . get_include_path());
require_once 'Zend/Application.php';
$application = new Zend_Application('testing', [
    'bootstrap' => ['path' => APPLICATION_PATH . '/Bootstrap.php', 'class' => 'Bootstrap'],
    'appnamespace' => 'Application',
    'resources' => ['view' => []],
]);
header('Content-Type: application/json');
try {
    $bootstrap = $application->getBootstrap();
    $bootstrap->bootstrap('DeecSession');
    $action = $_GET['action'] ?? 'bootstrap';
    if ($action === 'prestart') Zend_Session::setId('invalid!session');
    $auth = Zend_Auth::getInstance();
    $auth->hasIdentity();
    if ($action === 'bootstrap') $bootstrap->bootstrap('Locale');
    if ($action === 'login') {
        $auth->getStorage()->write((object)['id' => 42, 'clientid' => 7]);
        $clientSwitch = new Zend_Session_Namespace('ClientSwitch');
        $clientSwitch->token = 'fixture-token';
        foreach (['FileManager', 'MediaManager'] as $name) {
            $manager = new Zend_Session_Namespace($name);
            $manager->verify = 'old-grant';
        }
        DEEC_Session::login(!empty($_GET['remember']));
    }
    if ($action === 'logout') {
        foreach (['FileManager', 'MediaManager'] as $name) {
            $manager = new Zend_Session_Namespace($name);
            $manager->verify = 'old-grant';
        }
        DEEC_Session::logout();
        $auth->clearIdentity();
    }
    if ($action === 'regenerate') Zend_Session::regenerateId();
    if ($action === 'destroy') {
        Zend_Session::destroy();
        Zend_Session::start();
    }
    if ($action === 'refresh') DEEC_Session::refresh();
    DEEC_Session::configure();
    new Zend_Session_Namespace('Zend_Auth');
    new Zend_Session_Namespace('AnotherNamespace');
    echo json_encode([
        'id' => session_id(), 'started' => Zend_Session::isStarted(),
        'identity' => $auth->hasIdentity() ? (array)$auth->getIdentity() : null,
        'remember' => $_SESSION[DEEC_Session::REMEMBER_KEY] ?? false,
        'clientSwitch' => Zend_Session::namespaceIsset('ClientSwitch'),
        'managers' => Zend_Session::namespaceIsset('FileManager') || Zend_Session::namespaceIsset('MediaManager'),
        'locale' => Zend_Registry::isRegistered('Zend_Locale'),
        'db' => $bootstrap->hasResource('db'),
        'cookie' => session_get_cookie_params(),
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => get_class($e) . ': ' . $e->getMessage()]);
}
'''.replace('ROOT_PATH', "'" + str(root).replace("'", "\\'") + "'")

checks = 0
with tempfile.TemporaryDirectory(prefix='dewawi-session-http-') as temp:
    temp = Path(temp)
    (temp / 'session').mkdir(mode=0o700)
    (temp / 'languages').symlink_to(root / 'languages', target_is_directory=True)
    (temp / 'index.php').write_text(front)
    with socket.socket() as listener:
        listener.bind(('127.0.0.1', 0))
        port = listener.getsockname()[1]
    with (temp / 'server.log').open('w+') as log:
        server = subprocess.Popen([php, '-d', 'session.sid_bits_per_character=4', '-d', 'session.sid_length=32', '-d', 'session.use_strict_mode=0', '-S', f'127.0.0.1:{port}', '-t', str(temp)], stdout=log, stderr=log)
        try:
            for attempt in range(100):
                if server.poll() is not None:
                    log.seek(0)
                    raise RuntimeError(log.read())
                try:
                    with socket.create_connection(('127.0.0.1', port), timeout=0.1):
                        break
                except OSError:
                    time.sleep(0.05)

            def request(action='bootstrap', sid=None, expected=200, **params):
                global checks
                connection = http.client.HTTPConnection('127.0.0.1', port, timeout=10)
                headers = {'Cookie': 'PHPSESSID=' + sid} if sid else {}
                connection.request('GET', '/?' + urlencode(dict(action=action, **params)), headers=headers)
                response = connection.getresponse()
                body = response.read().decode()
                cookies = response.headers.get_all('Set-Cookie', [])
                connection.close()
                assert response.status == expected, (response.status, body)
                checks += 1
                return json.loads(body), cookies

            fresh, cookies = request()
            assert fresh['started'] and fresh['locale'] and not fresh['db'] and fresh['identity'] is None
            assert fresh['cookie']['secure'] and fresh['cookie']['httponly'] and fresh['cookie']['samesite'] == 'Lax'
            assert cookies and all('secure' in c.lower() and 'HttpOnly' in c and 'SameSite=Lax' in c for c in cookies)
            assert 'expires=' not in cookies[-1].lower(), 'Guest cookie became persistent'
            same, _ = request(sid=fresh['id'])
            assert same['id'] == fresh['id'], 'Existing cookie changed unexpectedly'

            # PHP accepts these older IDs even when current generation uses hexadecimal.
            for legacy in ['0123456789abcdefghijklmnopqrstuv', 'LegacyWXYZ-0123456789,abcdef']:
                (temp / 'session' / ('sess_' + legacy)).write_text('')
                result, _ = request(sid=legacy)
                assert result['id'] == legacy and result['identity'] is None and result['locale']

            stale = 'a' * 32
            result, _ = request(sid=stale)
            assert result['identity'] is None, 'Unknown session gained authentication'
            for invalid in ['invalid!session', 'a' * 257]:
                result, _ = request(sid=invalid, expected=500)
                assert 'Zend_Session_Exception' in result['error'], 'Invalid ID was not rejected'
            repaired, _ = request('prestart')
            assert repaired['started'] and repaired['identity'] is None
            assert repaired['id'] not in ['invalid!session', hashlib.md5(b'invalid!session').hexdigest()], 'Invalid explicit ID was not regenerated'

            for remember in [0, 1]:
                guest, _ = request()
                login, cookies = request('login', guest['id'], remember=remember)
                assert login['identity'] == {'id': 42, 'clientid': 7}
                assert login['id'] != guest['id'] and not login['clientSwitch'] and not login['managers']
                assert login['remember'] == bool(remember)
                assert ('expires=' in cookies[-1].lower()) == bool(remember), 'Remember-me cookie lifetime changed'
                assert not (temp / 'session' / ('sess_' + guest['id'])).exists(), 'Login did not remove the old session'
                resumed, _ = request('status', login['id'])
                assert resumed['identity'] == login['identity'] and resumed['id'] == login['id']
                refreshed, refresh_cookies = request('refresh', resumed['id'])
                assert refreshed['identity'] == login['identity'] and bool(refresh_cookies) == bool(remember)
                if remember:
                    assert 'expires=' in refresh_cookies[-1].lower()
                regenerated, _ = request('regenerate', login['id'])
                assert regenerated['id'] != login['id'] and regenerated['identity'] == login['identity']
                assert not (temp / 'session' / ('sess_' + login['id'])).exists()
                logout, _ = request('logout', regenerated['id'])
                assert logout['identity'] is None and not logout['remember'] and not logout['clientSwitch'] and not logout['managers']
                after, _ = request('status', logout['id'])
                assert after['identity'] is None and not after['remember']
                replay, _ = request('status', login['id'])
                assert replay['identity'] is None, 'Old session restored authentication'

            destroyed, _ = request('destroy', expected=500)
            assert 'explicitly destroyed' in destroyed['error'], 'Destroyed session was restarted'
        except Exception:
            log.flush()
            log.seek(0)
            print(log.read())
            raise
        finally:
            server.terminate()
            server.wait(timeout=10)
print(f'PASS: {checks} HTTP session cases (guest bootstrap, legacy/invalid/stale cookies, login/logout, regeneration, remember-me)')
