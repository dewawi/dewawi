<?php

// Render real cleanup components with synthetic data; never connect to a database or send mail.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
require_once 'Zend/Loader/Autoloader.php';
Zend_Loader_Autoloader::getInstance()->registerNamespace('DEEC_');
require_once $root . '/application/modules/admin/forms/ToolbarInline.php';
require_once $root . '/application/modules/shops/forms/DynamicForm.php';
require_once $root . '/application/modules/shops/models/FormConfig.php';
require_once $root . '/application/modules/contacts/services/InquiryView.php';
foreach (['Media', 'MainMenu', 'MultiForm', 'Messages'] as $helper) require_once $root . '/application/views/helpers/' . $helper . '.php';
require_once $root . '/install/helper.php';
restore_exception_handler();

class TranslationCleanupView extends Zend_View
{
    public function url($params = [], $name = null, $reset = false, $encode = true) { return '/fixture/' . ($name ?? implode('/', $params)); }
    public function baseUrl($file = null) { return '/fixture' . ($file ?? ''); }
    public function Messages() { return ''; }
    public function SlugUrl($type, $id) { return '/fixture/' . $type . '/' . $id; }
    public function PageByType($type) { return ['id' => 3]; }
}

$results = [];
function verifyCleanup(bool $ok, string $locale, string $scenario): void
{
    global $results;
    $results[] = ['locale' => $locale, 'scenario' => $scenario, 'status' => $ok ? 'passed' : 'failed'];
}
function includesCleanup(string $html, DEEC_Translate $translator, array $keys): bool
{
    foreach ($keys as $key) if ($translator->t($key) === $key || !str_contains(html_entity_decode($html, ENT_QUOTES, 'UTF-8'), $translator->t($key))) return false;
    return true;
}
function renderInstallerCleanup(string $template, bool $success): string
{
    global $root;
    $product = 'DEWAWI'; $productVersion = '1.1.7'; $company = 'Synthetic company';
    $step = 'introduction'; $header = installerText('INSTALL_TITLE');
    $goToNextStep = $success; $phpVersion = '8.0'; $currentPhpVersion = '8.2'; $phpVersionOk = $success;
    $showExtensions = ['pdo_mysql' => $success];
    $showPermissions = ['fixture' => ['realpath' => '/fixture', 'showRequired' => 'r/w', 'error' => $success ? '' : installerTranslate('INSTALL_NOT_READABLE')]];
    $license = 'Synthetic license'; $error = $success ? '' : '<script>synthetic</script>';
    $database = 'synthetic"><script>'; $username = 'synthetic'; $host = '<script>synthetic</script>';
    $errors = $success ? [] : ['<script>synthetic</script>']; $furtherInstructions = '';
    $config = ['applicationPath' => '/fixture'];
    ob_start();
    include $root . '/install/templates/' . $template . '.php';
    return ob_get_clean();
}

foreach (['de_DE', 'en_US'] as $locale) {
    $translator = new DEEC_Translate($locale); $translator->loadModule('shops');
    Zend_Registry::set('DEEC_Translate', $translator);
    $view = new TranslationCleanupView();
    $view->addHelperPath($root . '/application/views/helpers', 'Zend_View_Helper');
    $view->setScriptPath($root . '/application/modules/shops/views/scripts');
    $view->module = 'items'; $view->controller = 'item'; $view->action = 'edit'; $view->id = 41;
    $view->media = []; $view->subfolders = []; $view->client = ['id' => 7];
    $media = (new Zend_View_Helper_Media())->setView($view);
    verifyCleanup(includesCleanup($media->Media(), $translator, ['MEDIA_EMPTY', 'MEDIA_MAIN_FOLDER', 'UPLOAD']), $locale, 'shared empty media and upload');
    $view->media = [['id' => 2, 'module' => 'items', 'controller' => 'item', 'parentid' => 41, 'type' => 'image', 'url' => 'fixture.png', 'title' => '"><script>synthetic</script>', 'description' => '</textarea><script>synthetic</script>', 'target' => '"><script>synthetic</script>']];
    $html = $media->Media();
    verifyCleanup(includesCleanup($html, $translator, ['TOOLBAR_TITLE', 'MEDIA_DESCRIPTION', 'MEDIA_TARGET_URL', 'TOOLBAR_ORDERING']), $locale, 'shared media placeholders');
    verifyCleanup(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), $locale, 'shared media values remain escaped');
    foreach (['renderMobileDrawer' => ['NAV_MENU', 'NAV_MOBILE', 'NAV_CLOSE_MENU'], 'renderDesktopTopbar' => ['NAV_MAIN']] as $method => $keys) {
        $helper = (new Zend_View_Helper_MainMenu())->setView($view);
        $html = (new ReflectionMethod($helper, $method))->invoke($helper, []);
        verifyCleanup(includesCleanup($html, $translator, $keys), $locale, $method . ' accessible labels');
    }
    $helper = (new Zend_View_Helper_MultiForm())->setView($view);
    foreach (['renderDeleteButton' => ['items', 'item', '"><script>synthetic</script>'], 'renderAddButton' => ['items', 'item']] as $method => $args) {
        $html = (new ReflectionMethod($helper, $method))->invokeArgs($helper, $args);
        $key = $method === 'renderDeleteButton' ? 'TOOLBAR_DELETE' : 'TOOLBAR_ADD';
        verifyCleanup(includesCleanup($html, $translator, [$key]) && !str_contains($html, '<script>') && str_contains($html, 'data-module="items"'), $locale, $method . ' label and data attributes');
    }
    $config = ['first' => [['name' => 'email', 'type' => 'email', 'label' => 'SHOPS_PLACEHOLDER_EMAIL']], 'last' => [['name' => 'message', 'type' => 'textarea', 'label' => 'SHOPS_PLACEHOLDER_MESSAGE']]];
    foreach (['first' => ['SHOPS_FORM_NEXT'], 'last' => ['SHOPS_SEND_INQUIRY', 'TOOLBAR_BACK']] as $step => $keys) {
        $form = new Shops_Form_DynamicForm($config, $step, 'synthetic-csrf', 'synthetic-inquiry');
        verifyCleanup(includesCleanup($form->render(), $translator, $keys) && $form->getValue('csrf_token') === 'synthetic-csrf', $locale, 'dynamic inquiry ' . $step . ' buttons and CSRF');
    }
    foreach ([[], ['name' => '<script>synthetic</script>', 'email' => 'fixture@example.invalid', 'subject' => 'Synthetic', 'message' => '<img src=x onerror=synthetic>']] as $data) {
        $view->formData = $data;
        $html = $view->render('checkout/error.phtml');
        $keys = ['SHOPS_CONTACT_ERROR_TITLE', 'SHOPS_CONTACT_ERROR_MESSAGE', 'SHOPS_BACK_HOME', $data ? 'SHOPS_YOUR_CONTACT_DATA' : 'SHOPS_NO_CONTACT_DATA'];
        verifyCleanup(includesCleanup($html, $translator, $keys) && !str_contains($html, '<script>') && !str_contains($html, '<img'), $locale, 'public checkout failure ' . ($data ? 'with escaped submitted data' : 'without data'));
    }
    $view->items = [12 => ['title' => 'Synthetic item', 'sku' => 'T5', 'price' => '12.34 EUR', 'quantity' => 2, 'total' => '24.68 EUR']];
    $view->total = '24.68 EUR';
    $view->siteContext = new DEEC_Site_Context(['id' => 41, 'clientid' => 7], [], ['cart', 'checkout']);
    $html = $view->render('cart/index.phtml');
    verifyCleanup(includesCleanup($html, $translator, ['SHOPS_CART_NET_NOTICE', 'SHOPS_SHIPPING_COSTS']) && str_contains($html, '24.68 EUR'), $locale, 'cart shipping CMS link and stored amounts');
    $view->search = [];
    verifyCleanup(includesCleanup($view->render('search/index.phtml'), $translator, ['SHOPS_SEARCH_PLACEHOLDER', 'SHOPS_SEARCH_PROMPT']), $locale, 'public search form');
    $view->tags = []; $view->categories = []; $view->step = 'last'; $view->suggestions = true;
    $view->form = new Shops_Form_DynamicForm($config, 'last', 'synthetic-csrf', 'synthetic-inquiry');
    foreach ([0, 80] as $specialPrice) {
        $view->suggestedItems = [['item' => ['sku' => 'T5', 'title' => 'Synthetic unit', 'description' => 'Synthetic specification', 'price' => 100, 'specialprice' => $specialPrice, 'currency' => 'EUR'], 'required' => 50, 'value' => 25, 'percent' => 50, 'capacity_raw' => 25, 'unit' => 'kW']];
        $html = $view->render('inquiry/index.phtml');
        $keys = ['SHOPS_REQUIREMENT', 'SHOPS_CALCULATED_VALUE', 'SHOPS_PERFORMANCE_REVIEW', 'SHOPS_SELECT_DEVICE', 'SHOPS_LIST_PRICE'];
        if ($specialPrice) $keys[] = 'SHOPS_SPECIAL_PRICE';
        verifyCleanup(includesCleanup($html, $translator, $keys) && str_contains($html, 'data-sku="T5"'), $locale, 'inquiry suggestion warning and ' . ($specialPrice ? 'special' : 'list') . ' price');
    }
    $contactsTranslator = new DEEC_Translate($locale); $contactsTranslator->loadModule('contacts');
    foreach ([true, false] as $eligible) {
        $row = (new Contacts_Service_InquiryView())->build(['data' => json_encode(['_selection' => ['eligible' => $eligible, 'reasons' => ['synthetic_code', '<script>synthetic</script>']]])]);
        $field = $row['fields'][0];
        $text = $contactsTranslator->t($field['value'], $field['value_args']);
        verifyCleanup($field['label'] === 'CONTACT_SELECTION_REVIEW' && $field['translate_value'] && $text !== $field['value'] && ($eligible || str_contains($text, 'synthetic_code')), $locale, 'contact selection review ' . ($eligible ? 'approved' : 'manual with unchanged reason codes'));
    }
    $exports = (new Zend_View_Helper_Messages())->setView($view)->getTranslations();
    foreach (['MESSAGES_DELETE_CONFIRM', 'MESSAGES_VARIANT_CREATE_FAILED', 'MESSAGES_RECORD_UNAVAILABLE', 'MESSAGES_SAVE_FAILED'] as $key) verifyCleanup(($exports[$key] ?? null) === $translator->t($key) && $exports[$key] !== $key, $locale, 'JS export ' . $key);
    $view->setScriptPath($root . '/application/views/scripts');
    foreach (['Page not found' => 'ERROR_NOT_FOUND', 'Application error' => 'ERROR_APPLICATION'] as $message => $key) {
        $view->message = $message;
        $html = $view->render('error/error.phtml');
        verifyCleanup(includesCleanup($html, $translator, ['ERROR_OCCURRED', 'ERROR_RETRY', $key]) && str_contains($html, '<html lang="' . substr($locale, 0, 2) . '">'), $locale, 'localized error page ' . $key);
    }
    $_SESSION = ['installer_locale' => $locale, 'installer_csrf' => 'synthetic-csrf'];
    foreach (['header', 'introduction', 'license', 'requirements', 'filePermissions', 'database', 'importSQL', 'done', 'footer'] as $template) foreach ([false, true] as $success) {
        $html = renderInstallerCleanup($template, $success);
        verifyCleanup(!preg_match('/\bINSTALL_[A-Z_]+\b/', $html) && !str_contains($html, '<script>'), $locale, 'installer ' . $template . ' ' . ($success ? 'success' : 'failure'));
    }
    verifyCleanup(Zend_Registry::get('DEEC_Translate') === $translator && $translator->getLocale() === $locale, $locale, 'installer translator preserves application context');
    verifyCleanup(!str_contains(installerText('INSTALL_DATABASE_WARNING', ['<script>synthetic</script>']), '<script>'), $locale, 'installer interpolated database name escaped');
}
$counts = array_count_values(array_column($results, 'status')) + ['passed' => 0, 'failed' => 0];
echo json_encode(['scope' => 'Actual helpers, dynamic form, public checkout error and all installer templates; synthetic data only', 'counts' => $counts, 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
exit($counts['failed'] ? 1 : 0);
