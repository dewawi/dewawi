<?php

// Reuse the existing memory-only form/helper/layout fixture and B5 baseline.
ob_start();
$applyArguments = $argv;
$argv = [$argv[0]];
require __DIR__ . '/toolbar-buttons.php';
$argv = $applyArguments;
$applyBaseline = ob_get_clean();
$checks = 0;

class ApplyContactsSubclassFixture extends Contacts_Form_Toolbar {}
class ApplyItemsSubclassFixture extends Items_Form_Toolbar {}
class ApplyContactsInlineSubclassFixture extends Contacts_Form_ToolbarInline {}
class ApplyItemsInlineSubclassFixture extends Items_Form_ToolbarInline {}

if (($argv[1] ?? '') === 'snapshot-inline') {
	$snapshots = [];
	foreach ([['contacts', 'contact'], ['items', 'item'], ['contacts', 'email'], ['items', 'itemlist'], ['shops', 'category']] as [$module, $controller]) foreach ([['index', 'select'], ['select', 'index'], ['search', 'select'], ['search', 'index']] as [$action, $context]) {
		toolbarContext($module, $controller, $action);
		Zend_Controller_Front::getInstance()->getRequest()->setParam('context_action', $context);
		foreach (['Contacts_Form_ToolbarInline', 'Items_Form_ToolbarInline', 'ApplyContactsInlineSubclassFixture', 'ApplyItemsInlineSubclassFixture'] as $class) $snapshots[$module . '/' . $controller . '/' . $action . '/' . $context . '/' . $class] = (new $class())->getElements();
	}
	echo json_encode($snapshots, JSON_THROW_ON_ERROR);
	exit;
}

class ApplySearchContextFixture extends DEEC_Controller_Action
{
	public array $elements = [];
	public array $inlineElements = [];
	public function init() {}
	protected function buildIndexView(): void
	{
		$class = $this->getToolbarClass();
		$this->elements = (new $class())->getElements();
		$inlineClass = $this->getToolbarInlineClass();
		$this->inlineElements = (new $inlineClass())->getElements();
	}
}

foreach (['de_DE', 'en_US'] as $locale) foreach (['contacts' => 'contact', 'items' => 'item'] as $module => $controller) {
	buttonTranslations($locale);
	$class = ucfirst($module) . '_Form_Toolbar';
	$legacyClass = 'Apply' . ucfirst($module) . 'SubclassFixture';
	foreach ([['select', null], ['select', 'index'], ['search', 'select'], ['search', 'index'], ['search', null], ['search', 'invalid'], ['index', 'select'], ['edit', 'select'], ['view', 'select'], ['get', 'select'], ['export', 'select']] as [$action, $context]) {
		toolbarContext($module, $controller, $action);
		if ($context !== null) Zend_Controller_Front::getInstance()->getRequest()->setParam('context_action', $context);
		$form = new $class();
		$legacy = new $legacyClass();
		$old = $legacy->getElement('apply');
		check(!isset($old['iconFormat']), 'Toolbar subclass inherited Apply opt-in');
		$enabled = $action === 'select' || ($action === 'search' && $context === 'select');
		check($form->getElement('apply') === ($enabled ? array_replace($old, ['icon' => 'apply', 'iconFormat' => 'svg']) : $old), 'Apply escaped its exact selection context');
		$attributes = ['data-parent' => 'tasks|task|501', 'data-setid' => '7', 'data-autosave' => 'false', 'value' => '0', 'onclick' => 'fixture()'];
		$a = buttonDom($form->renderElementWithAttribs('apply', $attributes))->firstChild;
		$b = buttonDom($legacy->renderElementWithAttribs('apply', $attributes))->firstChild;
		$attrs = static function (DOMElement $node): array { $out = []; foreach ($node->attributes as $attribute) if ($attribute->name !== 'class') $out[$attribute->name] = $attribute->value; ksort($out); return $out; };
		check($attrs($a) === $attrs($b) && $a->textContent === $b->textContent, 'Apply changed attributes, handlers or text');
		check($a->getAttribute('type') === 'button' && $a->getAttribute('name') === 'apply' && $a->getAttribute('data-action') === 'apply', 'Selection became submit/reset or lost its action');
		check($a->getElementsByTagName('svg')->length === (int)$enabled, 'Missing or unexpected Apply SVG');
		if ($enabled) {
			check($a->getElementsByTagName('use')->item(0)->getAttribute('href') === '#dw-icon-apply' && $a->getElementsByTagName('svg')->item(0)->getAttribute('aria-hidden') === 'true', 'Wrong symbol or duplicate accessible icon');
			check(trim($a->textContent) === Zend_Registry::get('DEEC_Translate')->t('TOOLBAR_APPLY') && !str_contains($a->textContent, 'TOOLBAR_'), 'Apply has a technical/untranslated label');
			foreach (explode(' ', $b->getAttribute('class')) as $css) check(in_array($css, explode(' ', $a->getAttribute('class')), true), 'Legacy action class removed');
		} else check($form->renderElement('apply') === $legacy->renderElement('apply'), 'Legacy Apply HTML changed');
	}
	foreach (['layout', 'plain'] as $layout) {
		$page = toolbarPage($locale, $module, 'select', $layout);
		$dom = buttonDom($page['toolbar'] . $page['bottom']);
		$count = 0;
		foreach ($dom->getElementsByTagName('button') as $button) if ($button->getAttribute('name') === 'apply') {
			$count++;
			check($button->getElementsByTagName('svg')->length === 1 && $button->getAttribute('type') === 'button', 'Head/footer not sharing the same Apply presentation');
		}
		check($count === 2 && substr_count($page['html'], 'class="dw-icon-symbols"') === 1, 'Apply duplicated/missing or symbols outside document scope');
	}
	$ajax = toolbarPage($locale, $module, 'search', 'plain', ['view', 'edit'], false, 'select');
	check(array_keys($ajax) === ['content', 'toolbar'] && !str_contains($ajax['content'] . $ajax['toolbar'], '<symbol'), 'Selection AJAX contract/symbol scope changed');
	check(substr_count($ajax['toolbar'], '#dw-icon-apply') === 1 && substr_count($ajax['content'], '#dw-icon-apply') === 1, 'Search lost Apply in head/footer');
	foreach ([['view'], [], ['add']] as $rights) {
		$page = toolbarPage($locale, $module, 'select', 'plain', $rights);
		check(!str_contains($page['toolbar'] . $page['bottom'], 'name="apply"'), 'Apply bypassed its existing edit permission');
	}
	$page = toolbarPage($locale, $module, 'select', 'plain', ['view', 'edit'], true);
	foreach (buttonDom($page['toolbar'] . $page['bottom'])->getElementsByTagName('button') as $button) check($button->hasAttribute('disabled'), 'Readonly selection action enabled');
	toolbarContext($module, $controller, 'select');
	$adapter->permissions[0]['userid'] = 99;
	check((new $class())->renderElement('apply') === '', 'Foreign user rights enabled Apply');
	foreach ([['select', 'index'], ['search', 'select'], ['search', 'index'], ['index', 'select'], ['edit', 'select']] as [$action, $context]) {
		toolbarContext($module, $controller, $action);
		Zend_Controller_Front::getInstance()->getRequest()->setParam('context_action', $context);
		$inlineClass = ucfirst($module) . '_Form_ToolbarInline';
		$legacyClass = 'Apply' . ucfirst($module) . 'InlineSubclassFixture';
		$inline = new $inlineClass();
		$legacy = new $legacyClass();
		check(!isset($legacy->getElement('apply')['iconFormat']), 'Inline subclass inherited Apply opt-in');
		$enabled = $action === 'select' || ($action === 'search' && $context === 'select');
		foreach ($legacy->getElements() as $name => $old) {
			$expected = $name === 'apply' && $enabled ? array_replace($old, ['label' => 'TOOLBAR_APPLY', 'icon' => 'apply', 'iconFormat' => 'svg', 'iconOnly' => true, 'size' => 'sm']) : $old;
			if ($action === 'search' && $context === 'index' && in_array($name, $module === 'contacts' ? ['view', 'edit', 'copy', 'delete'] : ['edit', 'copy', 'delete'], true)) {
				$label = 'TOOLBAR_' . strtoupper($name);
				$expected = array_replace($old, ['label' => $label, 'icon' => $name, 'iconFormat' => 'svg', 'iconOnly' => true, 'size' => 'sm', 'attribs' => $old['attribs'] + ($name === 'delete' ? [] : ['title' => $inline->translate($label)])]);
			}
			check($inline->getElement($name) === $expected, 'Inline opt-in changed another action/context');
		}
		$rowAttributes = ['data-id' => '18', 'data-module' => $module, 'data-controller' => $controller, 'data-action' => 'apply', 'value' => '0'];
		$html = $inline->renderElementWithAttribs('apply', $rowAttributes);
		$button = buttonDom($html)->firstChild;
		$oldButton = buttonDom($legacy->renderElementWithAttribs('apply', $rowAttributes))->firstChild;
		check($button->getAttribute('data-id') === '18' && $button->getAttribute('data-module') === $module && $button->getAttribute('data-controller') === $controller && $button->getAttribute('name') === 'apply' && $button->getAttribute('type') === 'button' && $button->getAttribute('value') === $oldButton->getAttribute('value') && $button->hasAttribute('value') === $oldButton->hasAttribute('value'), 'Inline Apply changed native attributes/row target: ' . $action . '/' . $context);
		check($button->getElementsByTagName('svg')->length === (int)$enabled, 'Inline SVG escaped its selection scope');
		if ($enabled) check($button->getAttribute('aria-label') === Zend_Registry::get('DEEC_Translate')->t('TOOLBAR_APPLY') && trim($button->textContent) === '', 'Icon-only row action lacks a translated name');
	}
	foreach ([false, true] as $readonly) {
		$page = toolbarPage($locale, $module, 'select', 'plain', ['view', 'edit'], $readonly, 'index', false, true);
		$ids = [];
		foreach (buttonDom($page['content'])->getElementsByTagName('button') as $button) if ($button->getAttribute('name') === 'apply' && $button->hasAttribute('data-id')) {
			$ids[] = $button->getAttribute('data-id');
			check($button->getElementsByTagName('svg')->length === 1 && $button->hasAttribute('disabled') === $readonly, 'List row Apply SVG/readonly state changed');
		}
		check($ids === ['17', '18'], 'List lost exact row selection targets');
	}
	$page = toolbarPage($locale, $module, 'select', 'plain', ['view'], false, 'index', false, true);
	check(!str_contains($page['content'] . $page['toolbar'], 'name="apply"'), 'Row Apply bypassed existing edit permission');
}

Zend_Layout::startMvc(['layoutPath' => $root . '/application/layouts/scripts']);
foreach (['contacts' => 'contact', 'items' => 'item'] as $module => $controller) {
	toolbarContext($module, $controller, 'search');
	$request = Zend_Controller_Front::getInstance()->getRequest();
	$request->setParam('parent', 'processes|process|501');
	$action = new ApplySearchContextFixture($request, new Zend_Controller_Response_Http());
	$action->searchAction();
	check($request->getParam('context_action') === 'select' && $action->elements['apply']['iconFormat'] === 'svg' && $action->inlineElements['apply']['iconFormat'] === 'svg', 'Real searchAction lost parent-only selection context');
}

// Verify the concrete existing popup caller without loading any business data.
require_once $root . '/application/modules/processes/views/helpers/Popup.php';
toolbarContext('processes', 'process', 'edit');
$form = new DEEC_Form();
$form->addElement(['name' => 'id', 'type' => 'hidden', 'value' => 501]);
$form->addElement(['name' => 'contactid', 'type' => 'hidden', 'value' => 0]);
$view = new Zend_View();
$view->assign(['module' => 'processes', 'controller' => 'process', 'form' => $form]);
foreach ([0, 117] as $contactId) {
	$form->setValue('contactid', $contactId);
	$frames = buttonDom((new Zend_View_Helper_Popup())->setView($view)->Popup())->getElementsByTagName('iframe');
	check($frames->length === 2, 'Existing popup caller changed its selector count');
	check(str_contains(urldecode($frames->item(0)->getAttribute('src')), '/contacts/contact/select/') && str_contains(urldecode($frames->item(0)->getAttribute('src')), '/parent/processes|process'), 'Contact caller selection route changed');
	check(str_contains(urldecode($frames->item(1)->getAttribute('src')), '/items/item/select/parent/processes|process|501'), 'Item caller parent ID changed');
}

foreach ([['contacts', 'email'], ['contacts', 'contactperson'], ['items', 'inventory'], ['items', 'itemlist'], ['items', 'stock'], ['items', 'ledger'], ['admin', 'export'], ['shops', 'category'], ['tasks', 'task']] as [$module, $controller]) foreach (['select', 'search'] as $action) {
	toolbarContext($module, $controller, $action);
	Zend_Controller_Front::getInstance()->getRequest()->setParam('context_action', 'select');
	foreach (['Contacts_Form_Toolbar', 'Items_Form_Toolbar', 'Contacts_Form_ToolbarInline', 'Items_Form_ToolbarInline'] as $class) check(!isset((new $class())->getElement('apply')['iconFormat']), 'Excluded caller/row action migrated');
}
toolbarContext('items', 'item', 'select');
foreach (['Items_Form_ToolbarStock', 'Items_Form_ToolbarMovements', 'Items_Form_ToolbarItemlist', 'Items_Form_ToolbarStockplanning'] as $class) check(!isset((new $class())->getElement('apply')['iconFormat']), 'Subclass inherited Apply opt-in');
foreach (['Contacts_Form_Email', 'Contacts_Form_ToolbarInline', 'Application_Form_Toolbar', 'Tasks_Form_Toolbar', 'Admin_Form_Toolbar'] as $class) foreach ((new $class())->getElements() as $element) check(!isset($element['iconFormat']), 'Special/dynamic/form action migrated');

echo $applyBaseline;
echo 'PASS: ' . $checks . " selection Apply metadata, native attributes, head/footer, AJAX, permissions, readonly and legacy checks\n";
