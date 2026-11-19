<?php

// Isolated CLI characterization tests: no application bootstrap, DB, session files or mail transport.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);
define('BASE_PATH', dirname(__DIR__, 2));
define('APPLICATION_PATH', BASE_PATH . '/application');
set_include_path(BASE_PATH . '/library' . PATH_SEPARATOR . get_include_path());

// Replace only external context acquisition. Production translation classes remain real.
class Zend_Auth
{
	public static bool $identity = false;
	public static function getInstance() { return new self(); }
	public function hasIdentity() { return self::$identity; }
}
class Zend_Session_Namespace
{
	public static $selectedLanguage = null;
	public static $lastStorage;
	public $storage;
	public function __construct($namespace) { $this->storage = (object)['language' => self::$selectedLanguage]; self::$lastStorage = $this->storage; }
}
class Zend_Application_Bootstrap_Bootstrap
{
	public $view;
	public function bootstrap($resource) { return $this; }
	public function getResource($resource) { return $this->view; }
}
class Application_Model_DbTable_Config
{
	public static array $config = ['language' => 'de_DE', 'timezone' => 'Europe/Berlin'];
	public function getConfig() { return self::$config; }
}
spl_autoload_register(static function ($class) {
	$path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php');
	if ($path) require_once $path;
});
require BASE_PATH . '/application/Bootstrap.php';
require BASE_PATH . '/application/plugins/Translate.php';
require BASE_PATH . '/application/views/helpers/Translate.php';
require BASE_PATH . '/application/views/helpers/Messages.php';
require BASE_PATH . '/application/modules/users/controllers/UserController.php';
require BASE_PATH . '/application/controllers/helpers/Email.php';

class TranslationAuditUserController extends Users_UserController { public function init() {} }
class Shops_Model_DbTable_Emailtemplate
{
	public function getEmailtemplate(string $module, string $controller): array { return ['subject' => 't#tMESSAGES_THANK_YOU_CONTACTING_USt#t', 'body' => '#HELLO_NAME# t#tSHOPS_CONTACT_FORMt#t']; }
}

class TranslationAuditBootstrap extends Bootstrap
{
	public function locale() { $this->_initLocale(); }
}
class TranslationAuditTranslator extends DEEC_Translate
{
	public function domains(): array { return array_keys($this->data); }
	public function data(): array { return $this->data; }
	public function loaded(): array { return $this->loaded; }
	public function fileCount(): int { return count($this->files); }
}
class TranslationAuditPdf extends DEEC_Pdf
{
	public function documentPayload(array $document, string $module): array { return $this->buildDocumentPayload(['document' => $document, 'meta' => ['module' => $module, 'controller' => 'quote', 'templateid' => null]]); }
	protected function resolveDocumentPayloadData(string $module, string $controller, array $document, array $options = []): array { return ['document' => $document, 'settings' => []]; }
}

$checks = []; $observations = []; $fixtures = [];
$work = sys_get_temp_dir() . '/dewawi-translations-' . bin2hex(random_bytes(8));
mkdir($work, 0700);
function translationCheck(bool $condition, string $name): void
{
	global $checks;
	if (!$condition) throw new RuntimeException($name);
	$checks[] = $name;
}
function translationFixture(string $name, string $source): string
{
	global $work, $fixtures;
	$path = $work . '/' . $name;
	file_put_contents($path, $source); $fixtures[] = $path;
	return $path;
}
function translationException(callable $run): ?string
{
	try { $run(); return null; } catch (Throwable $e) { return get_class($e); }
}

try {
	$bootstrap = new TranslationAuditBootstrap(); $bootstrap->view = new stdClass();
	Zend_Controller_Front::getInstance()->setParam('bootstrap', $bootstrap);
	foreach ([null => 'de_DE', 'en_US' => 'en_US', 'de_DE' => 'de_DE', 'xx_YY' => 'de_DE'] as $selected => $expected) {
		Zend_Session_Namespace::$selectedLanguage = $selected;
		$bootstrap->locale();
		translationCheck((string)Zend_Registry::get('Zend_Locale') === $expected, 'bootstrap unauthenticated locale ' . ($selected ?: '(none)'));
	}
	Zend_Auth::$identity = true;
	Application_Model_DbTable_Config::$config['language'] = 'en_US';
	Zend_Session_Namespace::$selectedLanguage = null; $bootstrap->locale();
	translationCheck((string)Zend_Registry::get('Zend_Locale') === 'en_US', 'tenant config chooses en_US without session selection');
	Zend_Session_Namespace::$selectedLanguage = 'de_DE'; $bootstrap->locale();
	translationCheck((string)Zend_Registry::get('Zend_Locale') === 'de_DE', 'session locale takes precedence over tenant config');
	Zend_Session_Namespace::$selectedLanguage = '../application';
	$error = translationException(static function () use ($bootstrap) { $bootstrap->locale(); });
	translationCheck((string)Zend_Registry::get('Zend_Locale') === 'en_US' && $error === null, 'invalid locale path falls back to valid tenant locale without currency error');
	$observations['invalid_existing_locale_path'] = ['exception' => $error, 'locale' => (string)Zend_Registry::get('Zend_Locale')];
	Zend_Auth::$identity = false; Zend_Session_Namespace::$selectedLanguage = 'de_DE';
	$bootstrap->locale();
	$tr = Zend_Registry::get('DEEC_Translate');
	translationCheck($tr->t('INVOICES_NEW_INVOICE') === 'INVOICES_NEW_INVOICE', 'bootstrap alone does not load sales details');
	$plugin = new Application_Plugin_Translate();
	$modules = array_map('basename', array_filter(glob(APPLICATION_PATH . '/modules/*'), 'is_dir'));
	$contexts = [];
	foreach (['de_DE', 'en_US'] as $locale) {
		foreach (array_merge(['default'], $modules) as $module) {
			$tr = new TranslationAuditTranslator($locale);
			$tr->loadDir('default', BASE_PATH . '/languages/' . $locale . '/default');
			Zend_Registry::set('DEEC_Translate', $tr); Zend_Registry::set('Zend_Locale', new Zend_Locale($locale));
			$client = ['id' => 91, 'parentid' => 42, 'modules' => ['contacts' => 42]];
			Zend_Registry::set('Client', $client);
			$before = Zend_Registry::get('Config');
			$request = new Zend_Controller_Request_Simple('index', 'index', $module);
			$plugin->routeShutdown($request);
			$commons = glob(BASE_PATH . '/languages/' . $locale . '/*/common.php'); sort($commons);
			$expected = ['default'];
			foreach ($commons as $file) if (basename(dirname($file)) !== 'default') $expected[] = basename(dirname($file)) . '_common';
			if ($module !== 'default' && is_dir(BASE_PATH . '/languages/' . $locale . '/' . $module)) $expected[] = $module;
			translationCheck($tr->domains() === $expected, $locale . '/' . $module . ': real plugin domain order and module boundary');
			$files = glob(BASE_PATH . '/languages/' . $locale . '/default/*.php');
			foreach ($commons as $file) $files[] = $file;
			foreach (glob(BASE_PATH . '/languages/' . $locale . '/' . $module . '/*.php') as $file) $files[] = $file;
			translationCheck($tr->fileCount() === count(array_unique($files)), $locale . '/' . $module . ': shared files evaluated once per instance');
			translationCheck(Zend_Registry::get('Client') === $client && Zend_Registry::get('Config') === $before && !Zend_Registry::isRegistered('User'), $locale . '/' . $module . ': translation preserves context registries');
			$effective = [];
			foreach ($tr->data() as $entries) $effective += $entries;
			ksort($effective);
			$contexts[] = ['locale' => $locale, 'module' => $module, 'domains' => $tr->domains(), 'effective_keys' => count($effective),
				'effective_sha256' => hash('sha256', json_encode($effective, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))];
		}
	}
	$observations['request_contexts'] = $contexts;
	foreach (['de_DE', 'en_US'] as $locale) {
		$tr = new DEEC_Translate($locale); $tr->loadDir('default', BASE_PATH . '/languages/' . $locale . '/default');
		Zend_Registry::set('DEEC_Translate', $tr);
		$plugin->routeShutdown(new Zend_Controller_Request_Simple('index', 'index', 'sales'));
		translationCheck($tr->t('CONTACTS_PHONE') !== 'CONTACTS_PHONE', $locale . ': sales shared ContactData helper reaches shared labels');
		$tr = new DEEC_Translate($locale); $tr->loadDir('default', BASE_PATH . '/languages/' . $locale . '/default');
		Zend_Registry::set('DEEC_Translate', $tr);
		$plugin->routeShutdown(new Zend_Controller_Request_Simple('index', 'index', 'default'));
		$salesCommon = BASE_PATH . '/languages/' . $locale . '/sales/common.php';
		$menu = is_file($salesCommon) ? (require $salesCommon)['MENU_SALES'] : 'MENU_SALES';
		translationCheck($tr->t('MENU_SALES') === $menu, $locale . ': global navigation follows shared module labels');
	}
	$plugin->routeShutdown(new Zend_Controller_Request_Simple('index', 'index', 'sales'));
	$helper = new Zend_View_Helper_Translate();
	translationCheck($helper->translate('AUDIT_UNKNOWN') === 'AUDIT_UNKNOWN', 'view helper unknown key is returned unchanged');
	Zend_Registry::getInstance()->offsetUnset('DEEC_Translate');
	translationCheck($helper->translate('AUDIT_UNKNOWN', [123]) === 'AUDIT_UNKNOWN', 'view helper missing registry returns key without formatting');
	Zend_Registry::set('DEEC_Translate', new stdClass());
	translationCheck($helper->translate('AUDIT_UNKNOWN') === 'AUDIT_UNKNOWN', 'view helper invalid registry type returns key');

	$a = translationFixture('a.php', "<?php return ['AUDIT_COLLISION' => 'first', 'AUDIT_EMPTY' => '', 'AUDIT_FORMAT' => '%s %d', 'AUDIT_POSITIONAL' => '%2\$s %1\$d', 'AUDIT_PERCENT' => '100%% %s'];");
	$b = translationFixture('b.php', "<?php return ['AUDIT_COLLISION' => 'second'];");
	$tr = new TranslationAuditTranslator('de_DE'); $tr->load('one', $a); $tr->load('two', $b);
	translationCheck($tr->t('AUDIT_COLLISION') === 'first', 'earliest loaded domain wins');
	translationCheck($tr->t('AUDIT_COLLISION', [], ['two', 'one']) === 'second', 'explicit domain order overrides default lookup');
	$tr->load('one', $b);
	translationCheck($tr->t('AUDIT_COLLISION', [], ['one']) === 'first', 'same domain cannot be extended with load');
	translationCheck($tr->t('AUDIT_EMPTY') === '' && $tr->t('') === '', 'empty values do not trigger fallback');
	translationCheck($tr->t('AUDIT_FORMAT', ['word', 2]) === 'word 2', 'printf arguments');
	translationCheck($tr->t('AUDIT_POSITIONAL', [3, 'word']) === 'word 3', 'positional printf arguments');
	translationCheck($tr->t('AUDIT_PERCENT', ['word']) === '100% word', 'escaped percentage');
	translationCheck($tr->t('AUDIT_FORMAT') === '%s %d', 'format returned raw without arguments');
	translationCheck($tr->t('AUDIT_UNKNOWN_%s', ['word']) === 'AUDIT_UNKNOWN_word', 'unknown keys are formatted when arguments exist');
	$error = translationException(static function () use ($tr) { $tr->t('AUDIT_FORMAT', ['word']); });
	translationCheck($error === null && $tr->t('AUDIT_FORMAT', ['word']) === '%s %d', 'too few printf arguments return raw text on PHP 8');
	$observations['printf_argument_mismatch'] = $error;
	$error = translationException(static function () use ($tr) { $tr->t('unknown %Q', ['word']); });
	translationCheck($error === null && $tr->t('unknown %Q', ['word']) === 'unknown %Q', 'invalid fallback format returns raw text without exception');
	$observations['printf_invalid_format'] = $error;
	$tr->setLocale('en_US'); $tr->load('one', $b);
	translationCheck($tr->getLocale() === 'en_US' && $tr->t('AUDIT_COLLISION') === 'second', 'setLocale clears data and loaded flags before new catalogue load');
	$tr->setLocale(''); translationCheck($tr->getLocale() === 'de_DE' && $tr->domains() === [], 'empty setLocale selects installed default and clears old domains');
	$tr = new TranslationAuditTranslator(''); translationCheck($tr->getLocale() === 'de_DE', 'constructor normalizes empty locale');
	$tr = new TranslationAuditTranslator('en_US');
	$tr->load('missing', $work . '/missing.php'); $tr->load('missing', $a);
	translationCheck($tr->loaded()['missing'] && $tr->t('AUDIT_COLLISION') === 'first', 'missing file remains retryable with a valid file');
	$tr->loadDir('retry', $work . '/missing'); $tr->load('retry', $a);
	translationCheck($tr->t('AUDIT_COLLISION') === 'first', 'missing directory remains retryable');
	$tr->load('', $a); translationCheck(!array_key_exists('', $tr->loaded()), 'empty domain ignored');
	$invalid = translationFixture('invalid.php', '<?php return false;');
	$tr->load('invalid', $invalid); $tr->load('invalid', $b);
	translationCheck($tr->loaded()['invalid'] && $tr->data()['invalid']['AUDIT_COLLISION'] === 'second', 'non-array file remains retryable with a valid file');
	$broken = translationFixture('broken.php', '<?php return [;');
	translationCheck(translationException(static function () use ($tr, $broken) { $tr->load('broken', $broken); }) === null && !isset($tr->loaded()['broken']), 'catalogue parse error is diagnosed without poisoning domain');
	translationCheck(in_array('invalid_file', array_column($tr->getIssues(), 'type'), true) && in_array('invalid_catalogue', array_column($tr->getIssues(), 'type'), true), 'catalogue failures remain observable');
	// Keep the sorted-directory fixture separate from invalid PHP fixtures.
	mkdir($work . '/sorted', 0700);
	$fixtures[] = $work . '/sorted/a.php'; $fixtures[] = $work . '/sorted/z.php';
	copy($a, $work . '/sorted/a.php'); copy($b, $work . '/sorted/z.php');
	$tr->loadDir('sorted', $work . '/sorted');
	translationCheck($tr->t('AUDIT_COLLISION', [], ['sorted']) === 'second', 'alphabetically later file wins within one directory domain');
	file_put_contents($work . '/sorted/z.php', "<?php return ['AUDIT_COLLISION' => 'modified'];");
	$tr->loadDir('sorted', $work . '/sorted');
	translationCheck($tr->t('AUDIT_COLLISION', [], ['sorted']) === 'second', 'loaded directory ignores changes within same instance');
	$fresh = new DEEC_Translate('de_DE'); $fresh->loadDir('sorted', $work . '/sorted');
	translationCheck($fresh->t('AUDIT_COLLISION') === 'modified', 'fresh instance sees file changes without Zend cache invalidation');

	$de = new DEEC_Translate('de_DE'); $de->loadDir('default', BASE_PATH . '/languages/de_DE/default');
	$en = new DEEC_Translate('en_US'); $en->loadDir('default', BASE_PATH . '/languages/en_US/default');
	translationCheck($en->t('AUDIT_MISSING_KEY') === 'AUDIT_MISSING_KEY' && in_array('missing_key', array_column($en->getIssues(), 'type'), true), 'missing keys remain visible and diagnosed without changing language');
	Zend_Registry::set('DEEC_Translate', $de);
	foreach (['DEEC_Form', 'DEEC_Pdf', 'DEEC_List'] as $class) {
		$component = new $class();
		translationCheck($component->translate('VALIDATION_REQUIRED') === $de->t('VALIDATION_REQUIRED'), $class . ': registry translator used');
		$component->setTranslator($en);
		translationCheck($component->translate('VALIDATION_REQUIRED') === $en->t('VALIDATION_REQUIRED'), $class . ': explicit translator takes precedence');
		Zend_Registry::set('DEEC_Translate', $en);
		translationCheck($component->getTranslator() === $en, $class . ': translator retained on component');
		Zend_Registry::set('DEEC_Translate', $de);
	}
	$pdf = new DEEC_Pdf(); $pdf->document = ['language' => 'en_US'];
	translationCheck($pdf->translate('VALIDATION_REQUIRED') === $de->t('VALIDATION_REQUIRED'), 'PDF translate adapter does not select document language');
	foreach (['de_DE', 'en_US'] as $locale) {
		$pdf = new TranslationAuditPdf();
		$document = ['id' => 1, 'language' => $locale, 'subtotal' => '123.45', 'total' => '146.91'];
		$payload = $pdf->documentPayload($document, 'sales');
		translationCheck($pdf->getTranslator()->getLocale() === $locale && $payload['document'] === $document, $locale . ': actual PDF payload selects document language and preserves stored values');
		translationCheck(Zend_Registry::get('DEEC_Translate') === $de, $locale . ': PDF locale does not mutate UI registry');
	}
	$form = new DEEC_Form(); $form->setLocale('en_US');
	translationCheck($form->translate('VALIDATION_REQUIRED') === $de->t('VALIDATION_REQUIRED'), 'form setLocale does not change translator locale');
	$form->getTranslator(); Zend_Registry::set('DEEC_Translate', $en);
	translationCheck($form->getTranslator() === $de, 'existing form retains cached translator after registry replacement');
	$feedback = new DEEC_Feedback();
	translationCheck($feedback->getTranslator('de_DE')->getLocale() === 'de_DE', 'feedback explicitly loads requested locale');
	translationCheck($feedback->getTranslator('xx_YY') === $en, 'feedback invalid locale falls back to registry');
	$site = new DEEC_Site_Context(['language' => 'en_US']);
	Zend_Session_Namespace::$selectedLanguage = null;
	Zend_Registry::set('SiteContext', $site); $bootstrap->locale();
	$plugin->routeShutdown(new Zend_Controller_Request_Simple('index', 'index', 'shops'));
	translationCheck($site->getLanguage() === 'en_US' && Zend_Registry::get('DEEC_Translate')->getLocale() === 'en_US', 'public shop uses site language independently of UI session');
	$observations['anonymous_site_locale'] = ['site' => $site->getLanguage(), 'translator' => Zend_Registry::get('DEEC_Translate')->getLocale()];
	$view = new Zend_View(); $view->addHelperPath(APPLICATION_PATH . '/views/helpers', 'Zend_View_Helper');
	$messages = new Zend_View_Helper_Messages(); $messages->setView($view);
	$observations['javascript_export_keys'] = array_keys($messages->getTranslations());
	translationCheck(count($messages->getTranslations()) > 0 && !in_array('TOOLBAR_SAVE', $observations['javascript_export_keys'], true), 'JS message export is a fixed subset of registry translations');
	Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->view = $view;
	Zend_Registry::set('DEEC_Translate', $de);
	$mail = new ReflectionMethod(Application_Controller_Action_Helper_Email::class, 'getEmailBody');
	foreach (['de_DE', 'en_US'] as $locale) {
		Zend_Registry::set('Shop', ['language' => $locale, 'title' => 'Fixture', 'url' => 'https://example.invalid', 'emailsender' => 'fixture@example.invalid']);
		[$body, $subject] = $mail->invoke(new Application_Controller_Action_Helper_Email(), 'shops', 'contact', [], ['name' => 'Ada', 'subject' => 'Fixture', 'message' => 'Fixture'], []);
		$expected = $locale === 'de_DE' ? 'Hallo Ada' : 'Hello Ada';
		translationCheck(str_contains($body, $expected) && !str_contains($subject, 't#t'), $locale . ': actual background shop email body and subject follow shop language');
		translationCheck(Zend_Registry::get('DEEC_Translate') === $de, $locale . ': email locale does not replace UI translator');
	}
	Zend_Layout::startMvc();
	Zend_Controller_Front::getInstance()->setControllerDirectory(['users' => APPLICATION_PATH . '/modules/users/controllers']);
	foreach (['../application', 'fr_FR', ['en_US'], 'en-US', 'de_DE'] as $input) {
		$request = new Zend_Controller_Request_Simple('language', 'user', 'users', ['language' => $input]);
		$response = new Zend_Controller_Response_Http();
		$controller = new TranslationAuditUserController($request, $response, ['noViewRenderer' => true]);
		$controller->languageAction();
		$valid = is_string($input) && in_array($input, ['en-US', 'de_DE'], true);
		translationCheck($valid ? Zend_Session_Namespace::$lastStorage->language === DEEC_Translate::normalizeLocale($input) : $response->getHttpResponseCode() === 400, 'language endpoint validates input ' . json_encode($input));
	}
	$forward = new TranslationAuditTranslator('en_US'); $forward->loadModule('default');
	Zend_Registry::set('DEEC_Translate', $forward);
	$plugin->preDispatch(new Zend_Controller_Request_Simple('index', 'index', 'contacts'));
	translationCheck(in_array('contacts', $forward->domains(), true) && !in_array('sales', $forward->domains(), true), 'forward loads actual detail module without unrelated details');
	$view->addScriptPath(APPLICATION_PATH . '/modules/shops/views/scripts');
	foreach (['de_DE', 'en_US'] as $locale) {
		$tr = new DEEC_Translate($locale); $tr->loadModule('shops'); Zend_Registry::set('DEEC_Translate', $tr);
		$catalog = ['reasons' => ['catalog_inputs_incomplete'], 'conditions' => [['status' => 'missing_input', 'label' => '<script>fixture</script>']], 'candidates' => [], 'has_more' => false, 'summary' => false, 'item_field' => 'item'];
		$html = $view->partial('inquiry/catalog.phtml', ['selection' => $catalog]);
		translationCheck(str_contains($html, $tr->t('SHOPS_CATALOG_HEADING')) && !str_contains($html, 'SHOPS_') && !str_contains($html, '<script>fixture'), $locale . ': real catalog partial translates and escapes metadata');
		$variant = ['variant_field' => 'variant', 'base' => null, 'reasons' => ['variant_inputs_incomplete'], 'candidates' => [], 'editable' => true,
			'selected' => ['sku' => 'FIX', 'title' => '<script>fixture</script>'],
			'options' => [['title' => 'Fixture', 'status' => 'priced', 'included' => false, 'price' => 10, 'currency' => 'EUR', 'uom' => 'unit', 'quantity' => 2]],
			'commercial' => ['pricing_status' => 'complete', 'positions' => [['price' => 10, 'uom' => 'unit', 'price_source' => 'item.specialprice']], 'currency' => 'EUR', 'totals' => ['subtotal' => 10, 'total' => 11.9]]];
		$html = $view->partial('inquiry/variants.phtml', ['selection' => $variant]);
		translationCheck(str_contains($html, $tr->t('SHOPS_VARIANT_HEADING')) && str_contains($html, $tr->t('SHOPS_VARIANT_STORED_PRICE')) && !str_contains($html, 'SHOPS_') && !str_contains($html, '<script>fixture'), $locale . ': real variant and price partial translates and escapes metadata');
	}
	$result = ['status' => 'passed', 'php_version' => PHP_VERSION, 'checks' => count($checks), 'passed_checks' => $checks,
		'known_defects_are_characterized_not_fixed' => false, 'observations' => $observations];
	Zend_Registry::getInstance()->offsetUnset('SiteContext');
	if (in_array('--benchmark', $argv, true)) {
		foreach (['de_DE', 'en_US'] as $locale) {
			$start = hrtime(true); $requests = 0;
			for ($iteration = 0; $iteration < 20; $iteration++) foreach (array_merge(['default'], $modules) as $module) {
				$tr = new DEEC_Translate($locale); $tr->loadDir('default', BASE_PATH . '/languages/' . $locale . '/default');
				Zend_Registry::set('DEEC_Translate', $tr);
				$plugin->routeShutdown(new Zend_Controller_Request_Simple('index', 'index', $module));
				$requests++;
			}
			$milliseconds = (hrtime(true) - $start) / 1000000;
			$result['benchmark'][$locale] = ['fresh_translators' => $requests, 'total_ms' => round($milliseconds, 3), 'mean_ms' => round($milliseconds / $requests, 3)];
		}
		$result['benchmark']['opcache_enable_cli'] = ini_get('opcache.enable_cli');
		$result['benchmark']['process_peak_memory_bytes'] = memory_get_peak_usage(true);
	}
} catch (Throwable $e) {
	$result = ['status' => 'failed', 'checks' => count($checks), 'passed_checks' => $checks, 'error' => get_class($e) . ': ' . $e->getMessage(), 'line' => $e->getLine()];
} finally {
	foreach ($fixtures as $file) if (is_file($file)) unlink($file);
	if (is_dir($work . '/sorted')) rmdir($work . '/sorted');
	rmdir($work);
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
exit($result['status'] === 'passed' ? 0 : 1);
