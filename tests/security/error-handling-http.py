#!/usr/bin/env python3
"""Exercise real error responses and logs with isolated PHP/Zend fixtures and no database."""
import base64
import http.client
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
from urllib.parse import urlencode

root = Path(__file__).resolve().parents[2]
php = os.environ.get("PHP_BINARY", "php")
secret = "SQLSTATE fixture /private/dewawi/database.ini <script>technical</script>"
parameter = "private-request-password"

router = r'''<?php
$case = $_GET['failure'] ?? ($argv[1] ?? '');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (preg_match('~^/manager/(FileManager|MediaManager)/~', $path, $match)) {
    chdir(__DIR__ . '/site/library/' . $match[1]);
    require 'probe.php';
    return;
}
if (str_starts_with($path, '/install/')) {
    chdir(__DIR__ . '/installer/install');
    require 'index.php';
    return;
}
define('APPLICATION_PATH', __DIR__ . '/site/application');
define('BASE_PATH', __DIR__ . (in_array($case, ['config', 'database', 'missing'], true) ? '/' . $case : '/site'));
if (PHP_SAPI === 'cli' || $path === '/cron') define('_CRONJOB_', true);
require ROOT_PATH . '/index.php';
if (defined('_CRONJOB_') && _CRONJOB_) echo 'CRON_CONTINUED';
'''

bootstrap = r'''<?php
class Bootstrap extends Zend_Application_Bootstrap_Bootstrap {
    protected function _initProbe() {
        if (($_GET['failure'] ?? '') === 'bootstrap') {
            echo 'PRIVATE STARTUP OUTPUT';
            throw new RuntimeException(SENSITIVE, 0, new PDOException('Previous database exception'));
        }
        $this->bootstrap('frontController');
        $front = $this->getResource('frontController');
        $front->setBaseUrl('');
        if (isset($_GET['display'])) $front->setParam('displayExceptions', (bool)$_GET['display']);
    }
    public function hasPluginResource($resource) {
        return strtolower($resource) === 'log' && isset($_GET['logger']) ? true : parent::hasPluginResource($resource);
    }
    public function getResource($name) {
        if (strtolower($name) !== 'log' || !isset($_GET['logger'])) return parent::getResource($name);
        return new class {
            public function crit($message) {
                if ($_GET['logger'] === 'broken') throw new RuntimeException('Configured logger failed');
                error_log('Configured logger: ' . $message);
            }
        };
    }
}
'''

controller = r'''<?php
require_once ROOT_PATH . '/application/modules/items/services/Variant.php';
require_once ROOT_PATH . '/library/DEEC/Controller/Action.php';
require_once ROOT_PATH . '/application/modules/items/controllers/VariantController.php';
class ErrorFixtureVariantService extends Items_Service_Variant {
    public function __construct() {}
    public function create(int $parentId, array $optionIds): int {
        if (($_POST['kind'] ?? '') === 'technical') throw new PDOException(SENSITIVE);
        if (!empty($_POST['message'])) throw new Exception($_POST['message']);
        return 42;
    }
    public function updateOptions(int $itemId, array $optionIds): array {
        $this->create($itemId, $optionIds);
        return ['id' => $itemId];
    }
}
class ErrorFixtureVariantController extends Items_VariantController {
    public function init() {}
    protected function getVariantService(): Items_Service_Variant { return new ErrorFixtureVariantService(); }
}
class ProbeController extends Zend_Controller_Action {
    public function failAction() {
        echo 'PRIVATE RESPONSE OUTPUT';
        $this->getResponse()->setBody('PRIVATE RESPONSE OUTPUT');
        throw new RuntimeException(SENSITIVE, 0, new PDOException('Previous database exception'));
    }
    public function typeAction() { throw new TypeError(SENSITIVE); }
    public function notfoundAction() { throw new Zend_Controller_Action_Exception(SENSITIVE, 404); }
    public function variantAction() {
        $request = clone $this->getRequest();
        $operation = ($_GET['operation'] ?? 'add') === 'edit' ? 'edit' : 'add';
        $request->setModuleName('items')->setControllerName('variant')->setActionName($operation);
        $variant = new ErrorFixtureVariantController($request, $this->getResponse());
        $variant->{$operation . 'Action'}();
    }
}
'''

manager_probe = r'''<?php
$config = include 'config/config.php';
$manager = basename(__DIR__);
$_SESSION[$manager] = ['verify' => 'RESPONSIVEfilemanager', 'language' => 'en_EN'];
$case = $_GET['case'] ?? '';
if ($case === 'uncaught') throw new TypeError(SENSITIVE);
if ($case === 'uploadhandler') {
    require ROOT_PATH . '/library/' . $manager . '/UploadHandler.php';
    $handler = new class(['image_library' => 0], false) extends UploadHandler {
        protected function gd_create_scaled_image($fileName, $version, $options) { throw new RuntimeException(SENSITIVE); }
        public function failImage() { return $this->create_scaled_image('fixture.png', 'thumbnail', []); }
    };
    header('Content-Type: application/json');
    echo json_encode(['created' => $handler->failImage()]);
    return;
}
if ($case === 'upload' || $case === 'url') {
    $_POST['fldr'] = '';
    $_FILES = [];
    if ($case === 'url') {
        $_POST['url'] = 'invalid url';
    } else {
        $_FILES['files'] = ['name' => ['fixture.pdf'], 'tmp_name' => [__DIR__ . '/fixture.pdf'], 'size' => [64], 'type' => ['application/pdf']];
    }
    include 'upload.php';
    return;
}
include 'include/utils.php';
if ($case === 'ftp') {
    $config['ftp_host'] = 'fixture.invalid';
    ftp_con($config);
} elseif ($case === 'thumbnail') {
    header('Content-Type: application/json');
    echo json_encode(['created' => create_img(__DIR__ . '/fixture.png', __DIR__ . '/thumb.png', 1, 1)]);
} elseif ($case === 'mkdir') {
    create_folder(null, __DIR__ . '/blocked/thumbs');
} else {
    header('Content-Type: application/json');
    echo json_encode(['display_errors' => (bool)ini_get('display_errors'), 'display_startup_errors' => (bool)ini_get('display_startup_errors')]);
}
'''

checks = 0
with tempfile.TemporaryDirectory(prefix="dewawi-errors-http-") as temp:
    temp = Path(temp)
    site = temp / "site"
    for directory in ["configs", "application/controllers", "application/modules/items/controllers", "application/modules/items/views/scripts", "application/views/scripts", "session"]:
        (site / directory).mkdir(parents=True, exist_ok=True)
    (site / "library").mkdir()
    # Only Zend is linked; standalone manager session files remain in the temporary site.
    (site / "library/Zend").symlink_to(root / "library/Zend", target_is_directory=True)
    shutil.copyfile(root / "configs/application.ini", site / "configs/application.ini")
    shutil.copyfile(root / "configs/database.sample.ini", site / "configs/database.ini")

    def fixture(source):
        return source.replace("ROOT_PATH", json.dumps(str(root))).replace("SENSITIVE", json.dumps(secret))

    (temp / "router.php").write_text(fixture(router))
    (site / "application/Bootstrap.php").write_text(fixture(bootstrap))
    (site / "application/controllers/ProbeController.php").write_text(fixture(controller))
    (site / "application/controllers/ErrorController.php").symlink_to(root / "application/controllers/ErrorController.php")
    (site / "application/views/scripts/error").symlink_to(root / "application/views/scripts/error", target_is_directory=True)
    for case in ["config", "database", "missing"]:
        (temp / case / "configs").mkdir(parents=True)
        if case == "missing":
            continue
        shutil.copyfile(site / "configs/application.ini", temp / case / "configs/application.ini")
        shutil.copyfile(site / "configs/database.ini", temp / case / "configs/database.ini")
        (temp / case / "configs" / ("application.ini" if case == "config" else "database.ini")).write_text('[broken]\nvalue = "unterminated\n')

    for relative in ["index.php", "helper.php", "database.php", "importSQL.php", "introduction.php", "requirements.php", "config/config.php", "templates/header.php", "templates/footer.php", "templates/introduction.php", "templates/requirements.php", "templates/database.php", "templates/importSQL.php"]:
        target = temp / "installer/install" / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(root / "install" / relative, target)
    for name in ["FileManager", "MediaManager"]:
        manager = site / "library" / name
        for relative in ["config/config.php", "upload.php", "include/utils.php", "include/Response.php", "include/mime_type_lib.php", "lang/en_EN.php", "lang/languages.php"]:
            target = manager / relative
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(root / "library" / name / relative, target)
        (manager / "probe.php").write_text(fixture(manager_probe))
        (manager / "fixture.pdf").write_bytes(b"%PDF-1.4\nError handling fixture\n%%EOF\n")
        (manager / "fixture.png").write_bytes(base64.b64decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z4D8AAAAASUVORK5CYII="))
        (manager / "blocked").write_text("not a directory")
        (manager / "UploadHandler.php").write_text(fixture("<?php class UploadHandler { public function __construct(...$args) { throw new RuntimeException(SENSITIVE); } }"))
        (manager / "include/php_image_magician.php").write_text(fixture("<?php class imageLib { public function __construct(...$args) { throw new RuntimeException(SENSITIVE); } }"))
        (manager / "include/FtpClient.php").write_text(fixture("<?php namespace FtpClient; class FtpClient { public function connect(...$args) { throw new FtpException(SENSITIVE); } }"))
        (manager / "include/FtpException.php").write_text(r"<?php namespace FtpClient; class FtpException extends \RuntimeException {}")
        (manager / "include/FtpWrapper.php").write_text("<?php")

    log = temp / "php-error.log"
    command = [php, "-d", "error_reporting=8191", "-d", "display_errors=1", "-d", "display_startup_errors=1", "-d", "error_log=" + str(log)]
    for environment in ["production", "development"]:
        env = os.environ.copy()
        env.pop("APPLICATION_ENV", None)
        if environment == "development":
            env["APPLICATION_ENV"] = environment
        debug = environment == "development"
        with socket.socket() as listener:
            listener.bind(("127.0.0.1", 0))
            port = listener.getsockname()[1]
        with (temp / "server.log").open("w+") as server_log:
            server = subprocess.Popen(command + ["-S", f"127.0.0.1:{port}", str(temp / "router.php")], env=env, stdout=server_log, stderr=server_log)
            try:
                for _ in range(100):
                    if server.poll() is not None:
                        server_log.seek(0)
                        raise RuntimeError(server_log.read())
                    try:
                        with socket.create_connection(("127.0.0.1", port), timeout=0.1):
                            break
                    except OSError:
                        time.sleep(0.05)
                else:
                    raise RuntimeError("Isolated PHP server did not start")

                def request(path, data=None, headers=None, status=500):
                    global checks
                    headers = dict(headers or {})
                    body = None
                    if data is not None:
                        headers["Content-Type"] = "application/x-www-form-urlencoded"
                        body = urlencode(data)
                    connection = http.client.HTTPConnection("127.0.0.1", port, timeout=10)
                    connection.request("POST" if data is not None else "GET", path, body, headers)
                    response = connection.getresponse()
                    content = response.read().decode("utf-8")
                    content_type = response.getheader("Content-Type", "")
                    connection.close()
                    assert response.status == status, (environment, path, response.status, content)
                    assert parameter not in content and "PRIVATE RESPONSE OUTPUT" not in content and "PRIVATE STARTUP OUTPUT" not in content, (path, content)
                    if not debug:
                        for private in ["SQLSTATE", "/private/dewawi", str(temp), str(root), "Stack trace:", "PDOException", "TypeError", "<script>", "unterminated"]:
                            assert private not in content, (path, private, content)
                    checks += 1
                    return content, content_type

                for path in ["/probe/fail", "/probe/type", "/?failure=bootstrap", "/?failure=config", "/?failure=database"]:
                    body, content_type = request(path + ("&" if "?" in path else "?") + "password=" + parameter)
                    assert "text/html" in content_type and "Application error" in body
                    if debug and "failure=config" not in path and "failure=database" not in path:
                        assert "&lt;script&gt;technical&lt;/script&gt;" in body, body
                    for headers in [{"X-Requested-With": "XMLHttpRequest"}, {"Accept": "application/json"}]:
                        body, content_type = request(path, headers=headers)
                        result = json.loads(body)
                        assert "application/json" in content_type and result["ok"] is False and result["message"] == "Application error", body
                        assert ("exception" in result) == debug, body
                for logger in ["working", "broken"]:
                    request("/probe/fail?logger=" + logger)
                body, _ = request("/probe/fail?display=" + ("0" if debug else "1"))
                assert "SQLSTATE" not in body, "displayExceptions/environment guard bypassed"
                for path in ["/absent/controller", "/probe/notfound"]:
                    body, _ = request(path, status=404)
                    assert "Page not found" in body
                body, _ = request("/absent/controller", headers={"Accept": "application/json"}, status=404)
                assert json.loads(body)["message"] == "Page not found"
                request("/error/error")
                request("/cron?failure=config")
                request("/cron?failure=missing")
                for operation in ["add", "edit"]:
                    path = "/probe/variant?operation=" + operation
                    body, _ = request(path, {"id": 42}, status=200)
                    assert json.loads(body)["ok"] is True
                    for message in ["MESSAGES_ITEM_NOT_FOUND", "MESSAGES_ITEM_VARIANT_INVALID", "MESSAGES_ITEM_VARIANT_OPTIONS_REQUIRED", "MESSAGES_ITEM_OPTION_INVALID", "MESSAGES_ITEM_VARIANT_EXISTS"]:
                        body, _ = request(path, {"message": message}, status=200)
                        assert json.loads(body) == {"ok": False, "message": message}
                    for data in [{"kind": "technical"}, {"message": secret}]:
                        body, _ = request(path, data, status=200)
                        assert json.loads(body) == {"ok": False, "message": "save_failed"}
                    body, _ = request(path, status=200)
                    assert json.loads(body)["message"] == "MESSAGES_INVALID_REQUEST"

                for name in ["FileManager", "MediaManager"]:
                    path = "/manager/" + name + "/probe"
                    body, _ = request(path, status=200)
                    assert json.loads(body) == {"display_errors": debug, "display_startup_errors": debug}
                    body, content_type = request(path + "?case=upload", status=200)
                    result = json.loads(body)
                    assert "application/json" in content_type and result["files"][0]["error"] == "There was an error while saving the file.", body
                    assert result["files"][0]["name"] == "fixture.pdf"
                    body, _ = request(path + "?case=url", status=200)
                    assert json.loads(body) == {"error": "Is not a valid URL."}
                    request(path + "?case=ftp")
                    request(path + "?case=uncaught")
                    request(path + "?case=mkdir")
                    body, _ = request(path + "?case=thumbnail", status=200)
                    assert json.loads(body) == {"created": False}, body
                    body, _ = request(path + "?case=uploadhandler", status=200)
                    assert json.loads(body) == {"created": False}, body

                body, _ = request("/install/index.php", {"nextStep": "database", "database": ""}, status=200)
                assert "Please fill in all database connection fields." in body
                body, _ = request("/install/index.php", {"nextStep": "importSQL"}, status=200)
                assert "Database connection settings are missing." in body and 'value="done"' not in body
                for step in ["../configs/database.ini", "missing", "<script>technical</script>"]:
                    request("/install/index.php", {"nextStep": step}, status=400)
                body, _ = request("/install/index.php", {"nextStep": "requirements"}, status=200)
                assert "PHP Extensions" in body
            except Exception:
                server_log.seek(0)
                print(server_log.read()[-3000:])
                if log.exists():
                    print(log.read_text()[-5000:])
                raise
            finally:
                server.terminate()
                server.wait(timeout=10)

        for case, success in [("bootstrap", True), ("config", False), ("database", False), ("missing", False)]:
            result = subprocess.run(command + [str(temp / "router.php"), case], env=env, capture_output=True, text=True, timeout=10)
            assert result.returncode == (0 if success else 1), result
            assert ("CRON_CONTINUED" in result.stdout) == success, result
            if not debug:
                assert "SQLSTATE" not in result.stdout + result.stderr and str(root) not in result.stdout + result.stderr
            checks += 1
    internal = log.read_text()
    for expected in [secret, "Previous database exception", "Stack trace:", "Configured logger:", "Configured logger failed", "Unable to create thumbnail directory", "No database configuration found"]:
        assert expected in internal, f"Missing technical context in internal logs: {expected}"
print(f"PASS: {checks} error handling cases (production/development, HTML/JSON, initialization/bootstrap, logging fallback, cron, variants, installer and managers)")
