<?php

// Render production templates with synthetic records and the existing memory-only adapter.
ob_start();
$pageArguments = $argv;
$argv = [$argv[0]];
require __DIR__ . '/buttons.php';
$argv = $pageArguments;
$baseline = ob_get_clean();
$checks = 0;

class InquiryButtonRequestFixture extends Zend_Controller_Request_Http
{
	public function isXmlHttpRequest() { return $this->getActionName() === 'search'; }
}

function inquiryButtonPage(string $locale, string $action = 'index', string $layoutName = 'layout', array $rights = ['view', 'edit'], bool $readonly = false, string $controller = 'inquiry', bool $search = false): array
{
	global $root;
	$translator = buttonTranslations($locale);
	foreach (glob($root . '/languages/' . $locale . '/*/common.php') as $common) $translator->load(basename(dirname($common)) . '_common', $common);
	buttonPermissions($rights);
	Zend_Registry::set('Zend_Locale', new Zend_Locale($locale));
	Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
	$request = new InquiryButtonRequestFixture('http://localhost/fixture/contacts/' . $controller . '/' . $action);
	$request->setBaseUrl('/fixture')->setModuleName('contacts')->setControllerName($controller)->setActionName($action);
	if ($search) $request->setActionName('search');
	$front = Zend_Controller_Front::getInstance();
	$front->setRequest($request)->setBaseUrl('/fixture');
	$front->setControllerDirectory(['default' => $root . '/application/controllers', 'contacts' => $root . '/application/modules/contacts/controllers']);
	$front->setRouter(new Zend_Controller_Router_Rewrite());
	$front->getRouter()->addDefaultRoutes();
	$view = new Zend_View();
	$view->addHelperPath($root . '/application/views/helpers', 'Zend_View_Helper');
	$view->addScriptPath($root . '/application/views/scripts');
	$view->addScriptPath($root . '/application/modules/contacts/views/scripts');
	foreach (['headTitle', 'headScript', 'headLink', 'headMeta'] as $helperName) $view->$helperName()->getContainer()->exchangeArray([]);
	$view->doctype('HTML5');
	$view->headScript()->appendFile('/fixture/setup.js');
	$view->assign([
		'language' => $locale, 'module' => 'contacts', 'controller' => $controller, 'action' => $action,
		'contextAction' => 'index', 'id' => $action === 'index' ? 0 : 17,
		'user' => Zend_Registry::get('User') + ['name' => 'Fixture User'],
		'permissions' => array_fill_keys($rights, true), 'options' => [], 'messages' => [],
		'canCreateQuote' => false, 'canViewQuote' => false, 'workflow' => [],
	]);
	$toolbar = new Contacts_Form_InquiryToolbar();
	// buildListView() adds this existing legacy control for exportable index lists.
	if (!$search) $toolbar->addElement(['name' => 'export', 'type' => 'button', 'label' => 'TOOLBAR_EXPORT', 'toolbar' => 'actions', 'wrap' => false, 'attribs' => ['class' => 'export']]);
	$inline = new Contacts_Form_InquiryToolbarInline();
	$form = new Contacts_Form_Inquiry();
	if ($readonly) {
		$toolbar->setMode('readonly');
		$inline->setMode('readonly');
		$form->setMode('readonly');
	}
	$rows = [];
	foreach ([17, 18] as $id) {
		$rows[] = [
			'id' => $id, 'clientid' => 7, 'formid' => 0, 'status' => 'new',
			'created' => '2026-01-12 10:30:00', 'website_title' => 'Fixture website',
			'website_url' => 'https://example.test', 'token' => 'fixture-' . $id,
			'data' => ['company' => 'Example & Co ' . $id, 'email' => 'fixture' . $id . '@example.test', 'subject' => 'Synthetic inquiry', 'source_path' => '/contact'],
		];
	}
	$list = new Contacts_Model_List_Inquiries([
		'view' => $view, 'module' => 'contacts', 'controller' => 'inquiry',
		'toolbar' => $toolbar, 'toolbarInline' => $inline, 'items' => $rows,
		'selectable' => false, 'listFooter' => true, 'context' => ['action' => 'index'],
	]);
	$detail = $rows[0];
	$detail['data'] = json_encode($detail['data']);
	$form->setValue('status', 'new');
	$view->assign([
		'inquiries' => $list, 'toolbar' => $action === 'index' ? $toolbar : null,
		'inquiry' => (new Contacts_Service_InquiryView())->build($detail), 'form' => $form,
		'assignmentForm' => new Contacts_Form_InquiryAssignment(),
		'contactForm' => new Contacts_Form_InquiryContact(),
		'pagination' => ['count' => 2, 'start' => 1, 'end' => 2, 'records' => 2, 'page' => 1, 'pages' => 1, 'limit' => 25],
	]);
	$content = $controller === 'inquiry' ? $view->render('inquiry/' . $action . '.phtml') : '';
	if ($controller !== 'inquiry') $view->assign(['toolbar' => null, 'title' => 'Fixture']);
	$toolbarHtml = $view->Toolbar();
	if ($search) {
		Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->setView($view);
		$view->action = 'search';
		$request->setParam('context_action', 'index');
		$response = new Zend_Controller_Response_Http();
		$response->setBody($content);
		$plugin = new Application_Plugin_ListResponse();
		$plugin->setResponse($response);
		$plugin->postDispatch($request);
		return json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
	}
	$layout = new Zend_Layout(['layoutPath' => $root . '/application/layouts/scripts', 'layout' => $layoutName]);
	$layout->setView($view);
	$view->getHelper('layout')->setLayout($layout);
	$layout->content = $content;
	return ['html' => $layout->render(), 'content' => $content, 'toolbar' => $toolbarHtml];
}

if (($argv[1] ?? '') === 'render') {
	$search = ($argv[3] ?? '') === 'search';
	echo json_encode(inquiryButtonPage($argv[2] ?? 'de_DE', $search ? 'index' : ($argv[3] ?? 'index'), $argv[4] ?? 'layout', ['view', 'edit'], ($argv[5] ?? '') === 'readonly', 'inquiry', $search), JSON_THROW_ON_ERROR);
	exit;
}

function inquiryButtonDom(string $html): DOMXPath
{
	$dom = new DOMDocument();
	$previous = libxml_use_internal_errors(true);
	$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
	libxml_clear_errors();
	libxml_use_internal_errors($previous);
	return new DOMXPath($dom);
}

$controls = [
	[new Contacts_Form_InquiryToolbar(), ['filter' => 'filter', 'reset' => 'reset', 'clear' => 'clear']],
	[new Contacts_Form_InquiryToolbarInline(), ['view' => 'view', 'edit' => 'edit']],
	[new Contacts_Form_Inquiry(), ['save' => 'save']],
];
foreach ($controls as [$form, $icons]) foreach ($icons as $name => $icon) {
	$config = $form->getElement($name);
	check(($config['iconFormat'] ?? null) === 'svg' && $config['icon'] === $icon, 'Production Inquiry control is not explicitly opted in: ' . $name);
	$svg = buttonDom($form->renderElement($name))->firstChild;
	$form->setElementData($name, ['iconFormat' => 'png']);
	$png = buttonDom($form->renderElement($name))->firstChild;
	check($svg->textContent === $png->textContent && $svg->attributes->length === $png->attributes->length, 'SVG altered the control label/attributes');
	foreach ($png->attributes as $attribute) check($svg->getAttribute($attribute->name) === $attribute->value, 'SVG altered the attribute: ' . $attribute->name);
}

foreach (['de_DE', 'en_US'] as $locale) foreach (['layout', 'plain'] as $layoutName) {
	foreach (['index', 'edit', 'view'] as $action) {
		$page = inquiryButtonPage($locale, $action, $layoutName);
		$dom = inquiryButtonDom($page['html']);
		check($dom->query('/html/body/svg[@class="dw-icon-symbols"]')->length === 1 && $dom->query('//symbol')->length === 14, 'Full Inquiry document lacks a single complete symbol block');
		$buttons = $dom->query('//button[contains(concat(" ", @class, " "), " dw-btn--control ")]');
		check($buttons->length === ['index' => 7, 'edit' => 1, 'view' => 0][$action], 'Unexpected production pilot scope');
		foreach ($buttons as $button) {
			$name = $button->getAttribute('name');
			check($button->getElementsByTagName('svg')->length === 1 && $button->getElementsByTagName('use')->length === 1, 'Production SVG missing');
			check($button->getAttribute('type') === ($name === 'save' ? 'submit' : 'button'), 'Native type changed');
			check($name === 'save' ? !$button->hasAttribute('data-action') : $button->getAttribute('data-action') === $name, 'Submit/action routing changed');
			if (in_array($name, ['clear', 'view', 'edit'], true)) {
				$key = ['clear' => 'CLEAR', 'view' => 'TOOLBAR_VIEW', 'edit' => 'TOOLBAR_EDIT'][$name];
				check($button->getAttribute('aria-label') === Zend_Registry::get('DEEC_Translate')->t($key) && trim($button->textContent) === '', 'Icon-only name is not translated');
				check(!preg_match('/^(?:TOOLBAR_|CONTACT_|CLEAR$|VIEW$|EDIT$)/', $button->getAttribute('aria-label') . $button->getAttribute('title')), 'Technical key exposed in accessible name/title');
			}
			if (in_array($name, ['view', 'edit'], true)) check(in_array($button->getAttribute('data-id'), ['17', '18'], true) && $button->getAttribute('data-module') === 'contacts' && $button->getAttribute('data-controller') === 'inquiry', 'List lost row target');
			if ($name === 'clear') check($button->getAttribute('rel') === 'keyword', 'Clear lost keyword target');
		}
		if ($action === 'index') {
			check($dom->query('//button[@name="export" and not(contains(@class,"dw-btn--control"))]')->length === 1, 'Legacy export was implicitly migrated');
			check($dom->query('//input[@type="checkbox" and @name="id[]"]')->length === 0, 'Inquiry list became selectable');
		}
	}
	foreach ([['view', 'edit'], ['view'], []] as $rights) foreach (['index', 'edit'] as $action) {
		$page = inquiryButtonPage($locale, $action, $layoutName, $rights, true);
		$dom = inquiryButtonDom($page['html']);
		$buttons = $dom->query('//button[contains(concat(" ", @class, " "), " dw-btn--control ")]');
		foreach ($buttons as $button) check($button->hasAttribute('disabled'), 'Readonly enabled an Inquiry control');
		check($dom->query('//button[@name="edit" or @name="save"]')->length === (in_array('edit', $rights, true) ? ($action === 'index' ? 2 : 1) : 0), 'Rendering bypassed edit permission');
	}
	$other = inquiryButtonDom(inquiryButtonPage($locale, 'view', $layoutName, ['view', 'edit'], false, 'contactperson')['html']);
	check($other->query('//symbol')->length === 14 && $other->query('//svg[@class="dw-icon-symbols"]')->length === 1 && $other->query('//button[contains(@class,"dw-btn--control")]')->length === 0, 'Shared definitions activated controls in another controller layout');
}
foreach (['de_DE', 'en_US'] as $locale) {
	$ajax = inquiryButtonPage($locale, 'index', 'layout', ['view', 'edit'], false, 'inquiry', true);
	check(array_keys($ajax) === ['content', 'toolbar'] && !str_contains($ajax['content'] . $ajax['toolbar'], '<symbol'), 'Real ListResponse changed its contract or duplicated symbols');
	check(substr_count($ajax['content'] . $ajax['toolbar'], '<use ') === 7 && !str_contains($ajax['toolbar'], 'name="export"'), 'Search response lost SVGs or changed the existing export visibility');
}
echo $baseline;
echo 'PASS: ' . $checks . " Inquiry production configuration, layouts, views, list response, translations and permissions checks\n";
