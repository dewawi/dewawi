<?php

// Private CLI-server fixture: real ZF1 controllers/views and translation bootstrap, synthetic data only.
$work = getenv('DEWAWI_TRANSLATION_ACCEPTANCE_WORK');
if (PHP_SAPI !== 'cli-server' || !is_string($work) || !preg_match('#^/tmp/dewawi-translation-acceptance-[a-zA-Z0-9_-]+$#D', $work) || !is_file($work . '/fixture.json')) { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
define('BASE_PATH', $work . '/base');
define('APPLICATION_PATH', dirname(__DIR__, 2) . '/application');
define('APPLICATION_ENV', 'testing');
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class) use ($root): void {
    if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { require_once $path; return; }
    $parts = explode('_', $class);
    if (count($parts) < 3) return;
    $module = array_shift($parts);
    $base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
    $path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
    if (is_file($path)) require_once $path;
});
$path = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(css|js|images|library)/#', $path)) {
    $file = realpath($root . $path);
    $types = ['js' => 'text/javascript', 'css' => 'text/css', 'png' => 'image/png', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'woff2' => 'font/woff2'];
    $ext = pathinfo($file ?: '', PATHINFO_EXTENSION);
    if (!$file || !str_starts_with($file, $root . '/') || !isset($types[$ext])) { http_response_code(404); exit; }
    header('Content-Type: ' . $types[$ext]); readfile($file); exit;
}
if ($path === '/favicon.ico') { http_response_code(204); exit; }
require_once 'Zend/Application.php';
require_once $root . '/application/Bootstrap.php';
require_once $root . '/tests/security/fixtures/entity.php';

class TranslationAcceptanceAdapter extends ContactpersonTestAdapter
{
    public int $reads = 0;
    public array $writes = [];
    // Parent metadata/quoting paths may call connect; this adapter never opens a connection.
    protected function _connect() {}
    public function query($sql, $bind = []) {
        $this->reads++;
        if ($sql instanceof Zend_Db_Table_Select) {
            $sql->assemble();
            $from = $sql->getPart(Zend_Db_Select::FROM);
            $table = reset($from)['tableName'];
            if (in_array($table, ['user', 'client', 'config', 'language', 'permission'], true)) return parent::query($sql, $bind);
        }
        if (!preg_match('/^\s*SELECT\b/i', (string)$sql)) throw new RuntimeException('Unexpected non-fixture SQL');
        $rows = preg_match('/\b(?:COUNT|SUM)\s*\(/i', (string)$sql) ? [['value' => 0]] : [];
        return new class($rows) {
            public function __construct(private array $rows) {}
            public function fetchAll($mode = null) { return $this->rows; }
            public function fetch($mode = null) { return $this->rows[0] ?? false; }
            public function fetchColumn($column = 0) { return isset($this->rows[0]) ? array_values($this->rows[0])[$column] ?? false : false; }
        };
    }
    public function update($table, array $bind, $where = '') {
        if ($table !== 'user') throw new RuntimeException('Only synthetic profile updates are allowed');
        $this->writes[] = ['table' => $table, 'fields' => array_keys($bind)];
        return parent::update($table, $bind, $where);
    }
    public function insert($table, array $bind) {
        if ($table !== 'usertracking') throw new RuntimeException('Only synthetic login tracking is allowed');
        $this->writes[] = ['table' => $table, 'fields' => array_keys($bind)];
        return parent::insert($table, $bind);
    }
}
class TranslationAcceptanceDbResource extends Zend_Application_Resource_ResourceAbstract
{
    public function init() { Zend_Db_Table::setDefaultAdapter($GLOBALS['adapter']); return $GLOBALS['adapter']; }
}
$fixture = json_decode(file_get_contents($work . '/fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$adapter = new TranslationAcceptanceAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
DEEC_Session::configure();
Zend_Session::setOptions(['save_path' => BASE_PATH . '/session', 'cache_limiter' => '', 'cookie_secure' => false]);
Zend_Session::start();
$state = new Zend_Session_Namespace('T3Synthetic');
$adapter->rows = $state->rows ?? $fixture['rows'];
if ($path === '/acceptance/guest-locale') {
    $locale = DEEC_Translate::normalizeLocale((string)($_GET['locale'] ?? ''), '');
    if ($locale === '') { http_response_code(400); exit; }
    $state->guestLocale = $locale;
    header('Content-Type: application/json'); echo json_encode(['locale' => $locale]); exit;
}
$application = new Zend_Application('testing', [
    'bootstrap' => ['path' => APPLICATION_PATH . '/Bootstrap.php', 'class' => 'Bootstrap'],
    'appnamespace' => 'Application',
    'resources' => [
        'view' => ['helperPath' => ['Zend_View_Helper' => APPLICATION_PATH . '/views/helpers'], 'doctype' => 'HTML5'],
        'frontController' => ['controllerDirectory' => APPLICATION_PATH . '/controllers', 'moduleDirectory' => APPLICATION_PATH . '/modules'],
        'modules' => [], 'layout' => ['layoutPath' => APPLICATION_PATH . '/layouts/scripts'],
    ],
]);
try {
    $bootstrap = $application->getBootstrap();
    $bootstrap->registerPluginResource('db', new TranslationAcceptanceDbResource());
    $bootstrap->bootstrap('Locale')->bootstrap('Doctype')->bootstrap('ControllerHelpers')->bootstrap('layout');
    // Guest presentation injection only: production has no anonymous locale selector.
    if (!Zend_Auth::getInstance()->hasIdentity() && isset($state->guestLocale)) {
        Zend_Registry::get('DEEC_Translate')->setLocale($state->guestLocale);
        Zend_Registry::get('DEEC_Translate')->loadModule('default');
        Zend_Registry::set('Zend_Locale', new Zend_Locale($state->guestLocale));
    }
    $front = Zend_Controller_Front::getInstance();
    $front->setParam('bootstrap', $bootstrap)->setParam('noErrorHandler', true)->throwExceptions(true)->returnResponse(true)->setBaseUrl('');
    if (str_starts_with($path, '/shops/')) {
        $bootstrap->bootstrap('modules');
        $locale = DEEC_Translate::normalizeLocale((string)($_GET['site_locale'] ?? 'de_DE'));
        $site = ['id' => 1, 'clientid' => 7, 'language' => $locale, 'title' => 'Synthetic bilingual site', 'url' => 'http://' . $_SERVER['HTTP_HOST'], 'theme' => 'default', 'email' => 'fixture@example.invalid', 'emailsender' => 'fixture@example.invalid', 'activated' => 1];
        Zend_Registry::set('SiteContext', new DEEC_Site_Context($site, ['indexable' => false], ['catalog', 'contact', 'inquiry']));
        Zend_Registry::set('Shop', $site);
        Zend_Controller_Action_HelperBroker::addPath(APPLICATION_PATH . '/modules/shops/controllers/helpers', 'Shops_Controller_Action_Helper_');
        (new DEEC_Site_Router())->registerRoutes(Zend_Registry::get('SiteContext'));
        // Named public URLs remain real; explicit /shops paths expose actions for this private fixture.
        $front->getRouter()->addRoute('t3-shop-action', new Zend_Controller_Router_Route('shops/:controller/:action/*', ['module' => 'shops', 'action' => 'index']));
    }
    $front->registerPlugin(new Application_Plugin_Acl());
    $front->registerPlugin(new Application_Plugin_Translate());
    $front->registerPlugin(new Application_Plugin_ListResponse());
    $front->registerPlugin(new Application_Plugin_SecurityHeaders());
    Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->setExit(false);
    Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;
    $response = $front->dispatch();
    $state->rows = $adapter->rows;
    $tr = Zend_Registry::get('DEEC_Translate');
    $data = (new ReflectionProperty(DEEC_Translate::class, 'data'))->getValue($tr);
    $effective = []; foreach ($data as $entries) $effective += $entries; ksort($effective);
    $meta = ['locale' => $tr->getLocale(), 'authenticated' => Zend_Auth::getInstance()->hasIdentity(), 'clientid' => Zend_Registry::isRegistered('Client') ? Zend_Registry::get('Client')['id'] : null,
        'domains' => array_keys($data), 'issues' => $tr->getIssues(), 'keys' => count($effective), 'hash' => hash('sha256', json_encode($effective, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 'reads' => $adapter->reads, 'writes' => $adapter->writes];
    $response->setHeader('X-T3-Meta', base64_encode(json_encode($meta)), true);
    $response->sendResponse();
} catch (Throwable $error) {
    error_log((string)$error);
    http_response_code(500); header('Content-Type: application/json');
    echo json_encode(['fixture_error' => get_class($error) . ': ' . $error->getMessage()]);
}
