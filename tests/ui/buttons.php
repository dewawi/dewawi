<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/ui/buttons.php (in-memory adapters, no application bootstrap).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class) use ($root): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) {
		require_once $path;
		return;
	}
	$parts = explode('_', $class);
	if (count($parts) < 3) return;
	$module = array_shift($parts);
	$base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
	$path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
	if (is_file($path)) require_once $path;
});
require_once $root . '/application/views/helpers/Button.php';
require_once $root . '/application/views/helpers/Toolbar.php';

$checks = 0;
function check(bool $condition, string $message): void
{
	global $checks;
	$checks++;
	if (!$condition) throw new RuntimeException($message);
}

require_once $root . '/tests/security/fixtures/client-switch.php';
$adapter = new ClientSwitchFixtureAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('User', ['id' => 42, 'admin' => 0, 'clientid' => 70]);
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 7]);
$request = new Zend_Controller_Request_Simple('index', 'inquiry', 'contacts');
Zend_Controller_Front::getInstance()->setRequest($request);

function buttonPermissions(array $permissions, int $userid = 42, int $clientid = 70): void
{
	global $adapter;
	$adapter->permissions = [['id' => 1, 'userid' => $userid, 'clientid' => $clientid, 'deleted' => 0, 'contacts' => json_encode(['contact' => $permissions])]];
}

function buttonTranslations(string $locale): DEEC_Translate
{
	global $root;
	$translator = new DEEC_Translate($locale);
	$translator->loadDir('default', $root . '/languages/' . $locale . '/default');
	$translator->loadDir('contacts', $root . '/languages/' . $locale . '/contacts');
	Zend_Registry::set('DEEC_Translate', $translator);
	return $translator;
}

function buttonDom(string $html): DOMElement
{
	$dom = new DOMDocument();
	$previous = libxml_use_internal_errors(true);
	$dom->loadHTML('<?xml encoding="UTF-8"><main>' . $html . '</main>');
	libxml_clear_errors();
	libxml_use_internal_errors($previous);
	return $dom->getElementsByTagName('main')->item(0);
}

class ButtonInquiryListFixture extends Contacts_Model_List_Inquiries
{
	public function actions(array $row): string
	{
		foreach ($this->getColumns() as $column) {
			if ($column['name'] === 'actions') return $this->renderActionsCell($row, $column);
		}
		throw new RuntimeException('Inquiry actions column missing');
	}
}

class ButtonViewFixture extends Zend_View
{
	public function translate($key) { return Zend_Registry::get('DEEC_Translate')->t((string)$key); }
	public function baseUrl($url = null) { return '/fixture' . (string)$url; }
}

function buttonPilot(string $iconFormat = 'png'): array
{
	$view = new ButtonViewFixture();
	$view->assign(['action' => 'index', 'module' => 'contacts', 'controller' => 'inquiry', 'permissions' => ['view' => true, 'edit' => true], 'options' => []]);
	$toolbar = new Contacts_Form_InquiryToolbar();
	$inline = new Contacts_Form_InquiryToolbarInline();
	$form = new Contacts_Form_Inquiry();
	// Keep the PNG baseline explicit; inquiry-buttons.php tests unmodified production defaults.
	foreach (['filter', 'reset', 'clear'] as $name) $toolbar->setElementData($name, ['iconFormat' => $iconFormat]);
	foreach (['view', 'edit'] as $name) $inline->setElementData($name, ['iconFormat' => $iconFormat]);
	$form->setElementData('save', ['iconFormat' => $iconFormat]);
	$view->toolbar = $toolbar;
	$list = new ButtonInquiryListFixture(['view' => $view, 'module' => 'contacts', 'controller' => 'inquiry', 'toolbarInline' => $inline]);
	$form->setValue('status', 'new');
	return [
		'toolbar' => (new Zend_View_Helper_Toolbar())->setView($view)->toolbar(),
		'row' => $list->actions(['id' => 17]),
		'form' => $form->renderElement('status') . $form->renderElement('save'),
	];
}

buttonPermissions(['view', 'edit']);
buttonTranslations($argv[2] ?? 'de_DE');
$form = new DEEC_Form();
$helper = new Zend_View_Helper_Button();

if (($argv[1] ?? '') === 'render') {
	$iconFormat = $argv[3] ?? 'png';
	$pilot = buttonPilot($iconFormat);
	$render = static fn(array $presentation, array $attribs = []): string => $helper->renderButton($presentation + ['iconFormat' => $iconFormat], $attribs);
	$controls = '';
	foreach (['default', 'primary', 'danger', 'ghost'] as $variant) {
		$controls .= $render(['text' => 'A & B <' . $variant . '>', 'variant' => $variant, 'icon' => false], ['id' => 'variant-' . $variant]);
	}
	$controls .= $render(['text' => 'Without icon', 'icon' => false], ['class' => 'save', 'id' => 'no-icon']);
	$controls .= $render(['text' => 'Save', 'icon' => 'save', 'size' => 'touch'], ['id' => 'touch']);
	$controls .= $render(['text' => 'Disabled', 'icon' => 'delete', 'variant' => 'danger'], ['id' => 'disabled', 'disabled' => true]);
	$controls .= $render(['text' => 'Navigation', 'tag' => 'a', 'icon' => 'view'], ['id' => 'link', 'href' => '#navigation', 'onclick' => 'window.inlineClicks++']);
	$controls .= $render(['text' => 'Blocked navigation', 'tag' => 'a', 'icon' => 'view'], ['id' => 'disabled-link', 'href' => '#blocked', 'disabled' => true, 'onclick' => 'window.inlineClicks++']);
	$controls .= $render(['text' => 'Long translated action label with several words', 'icon' => 'save'], ['id' => 'long-label']);
	$controls .= '<button type="button" class="dw-btn dw-btn--action" id="legacy-text">Legacy</button>';
	$submit = $render(['text' => 'Confirm & save', 'icon' => 'apply', 'variant' => 'primary'], ['id' => 'submit', 'type' => 'submit', 'name' => 'operation', 'value' => 'confirm']);
	$reset = $render(['text' => 'Reset', 'icon' => false], ['id' => 'native-reset', 'type' => 'reset']);
	if ($iconFormat === 'svg') {
		$pilot['symbols'] = $form->renderButtonSymbols();
		$pilot['catalog'] = '';
		foreach (['add', 'edit', 'save', 'delete', 'copy', 'view', 'download', 'upload', 'send', 'search', 'close', 'clear', 'filter', 'reset', 'apply'] as $icon) {
			$pilot['catalog'] .= $render(['text' => $icon, 'icon' => $icon], ['data-icon' => $icon]);
		}
	}
	echo json_encode($pilot + ['controls' => $controls, 'submit' => $submit, 'reset' => $reset], JSON_THROW_ON_ERROR);
	exit;
}

// Preserve the old HTML and event contracts without opting other controls into B2.
$form->addElement(['name' => 'edit', 'type' => 'button', 'wrap' => false, 'attribs' => ['class' => 'edit nolabel']]);
check($form->renderElement('edit') === '<button class="edit dw-btn dw-btn--icon" name="edit" type="button" aria-label="' . Zend_Registry::get('DEEC_Translate')->t('EDIT') . '"></button>', 'Legacy icon-only output changed');
$form->addElement(['name' => 'filter', 'type' => 'button', 'label' => 'Filter', 'toolbar' => 'actions', 'wrap' => false, 'attribs' => ['class' => 'filter']]);
check($form->renderElement('filter') === '<button class="filter dw-btn dw-btn--action" name="filter" type="button" data-action="filter">Filter</button>', 'Legacy toolbar contract changed');
$form->addElement(['name' => 'submit', 'type' => 'submit', 'label' => 'Confirm', 'wrap' => false, 'attribs' => ['class' => 'btn btn-primary']]);
check($form->renderElement('submit') === '<button class="btn btn-primary dw-btn dw-btn--action" name="submit" type="submit">Confirm</button>', 'Unmigrated Bootstrap submit changed');
$form->setElementData('submit', ['label' => 'A & B']);
check(buttonDom($form->renderElement('submit'))->firstChild->textContent === 'A & B', 'Legacy submit still double escapes text');
check($helper->Button('edit') === '<button type="button" class="edit nolabel"></button>', 'Legacy helper was implicitly migrated');
check($helper->Button('save', 'saveSetting(0)', null, null, 'display:none', 'keyword', 12, ['controller' => 'item']) === '<button type="button" class="save nolabel" style="display:none" onclick="saveSetting(0)" rel="keyword" data-id="12" data-controller="item"></button>', 'Positional helper attributes or handler changed');
check($helper->Button('copy', '', 'Copy', 'Copy') === '<button type="button" class="copy" title="Copy"><span>Copy</span></button>', 'Legacy label span changed');
$node = buttonDom($helper->Button('save', 'saveSetting("quoted")', 'A & "B"', '<b>Text</b>'))->firstChild;
check($node->getAttribute('onclick') === 'saveSetting("quoted")' && $node->getAttribute('title') === 'A & "B"' && $node->textContent === '<b>Text</b>' && !$node->getElementsByTagName('b')->length, 'Helper escaping changed values or accepted label markup');

foreach (['de_DE', 'en_US'] as $locale) {
	$translator = buttonTranslations($locale);
	$pilot = buttonPilot();
	$toolbarDom = buttonDom($pilot['toolbar']);
	$buttons = $toolbarDom->getElementsByTagName('button');
	check($buttons->length === 3, $locale . ': toolbar actions changed');
	foreach ($buttons as $button) {
		check(str_contains($button->getAttribute('class'), 'dw-btn--control') && $button->getAttribute('type') === 'button', $locale . ': toolbar was not opted in or became a submit');
		check($button->hasAttribute('data-action') && $button->getElementsByTagName('span')->item(0)->getAttribute('aria-hidden') === 'true', 'Toolbar icon affected action routing or accessibility');
	}
	$clear = $buttons->item(2);
	check($clear->getAttribute('aria-label') === $translator->t('CLEAR') && trim($clear->textContent) === '' && $clear->getAttribute('rel') === 'keyword', $locale . ': clear action lost its name, compact form or field target');
	$row = buttonDom($pilot['row'])->getElementsByTagName('button');
	check($row->length === 2, 'Inquiry row actions changed');
	foreach (['view' => 'TOOLBAR_VIEW', 'edit' => 'TOOLBAR_EDIT'] as $i => $key) {
		$button = $row->item($i === 'view' ? 0 : 1);
		check($button->getAttribute('aria-label') === $translator->t($key) && trim($button->textContent) === '', $locale . ': inline label is not translated or became visible');
		check($button->getAttribute('data-action') === $i && $button->getAttribute('data-id') === '17' && $button->getAttribute('data-module') === 'contacts' && $button->getAttribute('data-controller') === 'inquiry', 'List target attributes changed');
	}
	$save = buttonDom($pilot['form'])->getElementsByTagName('button')->item(0);
	check($save->getAttribute('type') === 'submit' && $save->getAttribute('name') === 'save' && !$save->hasAttribute('data-action') && $save->textContent === $translator->t('CONTACT_INQUIRIES_SAVE_STATUS'), 'Status save lost native submit or translation');
	$readonly = new Contacts_Form_Inquiry();
	$readonly->setMode('readonly');
	check(buttonDom($readonly->renderElement('save'))->getElementsByTagName('button')->item(0)->hasAttribute('disabled'), 'Readonly submit was enabled');
}

foreach (['button', 'submit'] as $type) {
	$form = new DEEC_Form();
	$form->addElement(['name' => 'save', 'type' => $type, 'label' => 'A & B <C> "D"', 'icon' => false, 'value' => '0', 'wrap' => false]);
	$node = buttonDom($form->renderElementWithAttribs('save', ['data-id' => '17', 'data-controller' => 'contact']))->firstChild;
	check($node->textContent === 'A & B <C> "D"' && $node->getAttribute('value') === '0' && $node->getAttribute('type') === $type, 'Text/value/type was double escaped or zero was lost');
	check(!$node->getElementsByTagName('span')->item(0)->hasAttribute('aria-hidden') && !$node->hasAttribute('data-action'), 'Icon=false or submit/action separation failed');
	check($node->getAttribute('data-id') === '17' && !$form->getElement('save')['attribs'], 'Row attributes leaked into form state');
}

$linkHtml = $helper->renderButton(['tag' => 'a', 'text' => 'Open & view', 'icon' => 'view'], ['href' => '/path?a=1&b=2', 'name' => 'target', 'value' => '0', 'target' => '_blank', 'rel' => 'noopener', 'data-id' => '17']);
$link = buttonDom($linkHtml)->firstChild;
check($link->tagName === 'a' && $link->getAttribute('href') === '/path?a=1&b=2' && $link->getAttribute('target') === '_blank' && $link->getAttribute('rel') === 'noopener' && !$link->hasAttribute('type'), 'Native link semantics changed');
check($link->getAttribute('name') === 'target' && $link->getAttribute('value') === '0' && !$link->hasAttribute('data-action'), 'Presentation inferred an action or lost attributes');
$typedLink = buttonDom($helper->renderButton(['tag' => 'a', 'text' => 'PDF'], ['href' => '/file.pdf', 'type' => 'application/pdf']))->firstChild;
check($typedLink->getAttribute('type') === 'application/pdf', 'Link MIME type was discarded');
$disabled = buttonDom($helper->renderButton(['tag' => 'a', 'text' => 'Open'], ['href' => '/path', 'disabled' => true]))->firstChild;
check($disabled->getAttribute('href') === '/path' && $disabled->getAttribute('aria-disabled') === 'true' && $disabled->getAttribute('tabindex') === '-1' && !$disabled->hasAttribute('disabled'), 'Disabled link lost its URL or used invalid native disabled');
$namedIcon = buttonDom($form->renderButtonHtml(['icon' => 'edit', 'iconOnly' => true], ['aria-labelledby' => 'external-label']))->firstChild;
check($namedIcon->getAttribute('aria-labelledby') === 'external-label' && !$namedIcon->hasAttribute('aria-label'), 'Explicit accessible name reference was overwritten');

foreach (['default', 'primary', 'danger', 'ghost'] as $variant) foreach (['sm', 'md', 'touch'] as $size) {
	$node = buttonDom($form->renderButtonHtml(['text' => 'Action', 'variant' => $variant, 'size' => $size, 'icon' => 'edit'], ['class' => 'edit poplight', 'data-action' => 'custom', 'data-id' => '17']))->firstChild;
	check(str_contains($node->getAttribute('class'), 'dw-btn--' . $variant) && str_contains($node->getAttribute('class'), 'dw-btn--' . $size) && str_contains($node->getAttribute('class'), 'poplight') && $node->getAttribute('data-action') === 'custom', 'Variants or sizes removed behavior hooks');
}
foreach ([['tag' => 'div'], ['variant' => 'unknown'], ['size' => 'large'], ['icon' => '" onmouseover="x'], ['iconOnly' => true, 'icon' => 'edit'], ['iconOnly' => true, 'icon' => 'edit', 'text' => ' '], ['iconOnly' => true, 'icon' => false, 'text' => 'Name']] as $invalid) {
	try {
		$form->renderButtonHtml($invalid);
		check(false, 'Invalid presentation was accepted');
	} catch (InvalidArgumentException $error) {
		check(true, 'Invalid presentation rejected');
	}
}

// Use real permission queries against the shared in-memory tenant fixture.
foreach ([['view'], [], ['view', 'edit']] as $rights) {
	buttonPermissions($rights);
	$toolbar = new Contacts_Form_InquiryToolbarInline();
	check(($toolbar->renderElement('view') !== '') === in_array('view', $rights, true), 'View permission changed');
	check(($toolbar->renderElement('edit') !== '') === in_array('edit', $rights, true), 'Edit permission changed');
	check(((new Contacts_Form_Inquiry())->renderElement('save') !== '') === in_array('edit', $rights, true), 'Save permission changed');
}
buttonPermissions(['view', 'edit'], 99);
check((new Contacts_Form_InquiryToolbarInline())->renderElement('edit') === '', 'Another user exposed an edit control');
// Permissions belong to the user; object tenancy is checked separately by the backend.
buttonPermissions(['view', 'edit'], 42, 99);
check((new Contacts_Form_InquiryToolbarInline())->renderElement('edit') !== '', 'Permission rendering lost an authorized shared context');
buttonPermissions(['view', 'edit']);
check((new Contacts_Form_InquiryToolbarInline())->renderElementWithAttribs('edit', ['data-module' => 'sales', 'data-controller' => 'invoice']) === '', 'An explicit unauthorized target bypassed the form guard');
$readonly = new Contacts_Form_InquiryToolbarInline();
$readonly->setMode('readonly');
check(buttonDom($readonly->renderElement('edit'))->firstChild->hasAttribute('disabled'), 'Readonly inline action was enabled');

echo 'PASS: ' . $checks . " button presentation, pilot, legacy, translation and permission checks\n";
