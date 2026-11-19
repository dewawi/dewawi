<?php

// Reuse the complete B5 baseline and shared render fixture, without application bootstrap.
ob_start();
$tasksArguments = $argv;
$argv = [$argv[0]];
require __DIR__ . '/toolbar-buttons.php';
$argv = $tasksArguments;
$tasksBaseline = ob_get_clean();
$checks = 0;

class TasksToolbarSubclassFixture extends Tasks_Form_Toolbar {}

if (($argv[1] ?? '') === 'snapshot') {
	$results = [];
	foreach ([['tasks', 'task'], ['tasks', 'position'], ['contacts', 'contact'], ['items', 'item'], ['sales', 'quote'], ['shops', 'order'], ['admin', 'export']] as [$module, $controller]) foreach (['index', 'search', 'select', 'edit', 'view', 'get', 'add', 'copy', 'save', 'export'] as $action) {
		toolbarContext($module, $controller, $action);
		foreach (['Tasks_Form_Toolbar', 'TasksToolbarSubclassFixture', 'Tasks_Form_ToolbarInline', 'Tasks_Form_ToolbarPositions', 'Tasks_Form_Task'] as $class) $results[$module . '/' . $controller . '/' . $action . '/' . $class] = (new $class())->getElements();
	}
	echo json_encode($results, JSON_THROW_ON_ERROR);
	exit;
}

foreach (['de_DE', 'en_US'] as $locale) {
	buttonTranslations($locale);
	foreach (['index', 'search', 'select', 'edit', 'view'] as $action) {
		toolbarContext('tasks', 'task', $action);
		$modern = new Tasks_Form_Toolbar();
		$legacy = new TasksToolbarSubclassFixture();
		foreach ($legacy->getElements() as $name => $old) {
			$new = $modern->getElement($name);
			if (in_array($name, ['add', 'edit', 'copy', 'delete', 'filter', 'reset', 'clear'], true)) {
				check(($new['iconFormat'] ?? '') === 'svg' && $new['icon'] === $name, 'Task standard action missing explicit SVG');
				$a = buttonDom($modern->renderElement($name))->firstChild;
				$b = buttonDom($legacy->renderElement($name))->firstChild;
				check($a->getAttribute('type') === 'button' && $a->textContent === $b->textContent, 'Native type or visible label changed');
				$attrs = static function (DOMElement $node): array { $values = []; foreach ($node->attributes as $attribute) if ($attribute->name !== 'class') $values[$attribute->name] = $attribute->value; ksort($values); return $values; };
				check($attrs($a) === $attrs($b), 'Task SVG changed name/value, data attributes or accessible name');
				foreach (explode(' ', $b->getAttribute('class')) as $class) check(in_array($class, explode(' ', $a->getAttribute('class')), true), 'Task SVG removed a legacy class');
				check($a->getElementsByTagName('svg')->item(0)->getAttribute('aria-hidden') === 'true', 'Task SVG exposed duplicate name');
				if ($name === 'clear') check($a->getAttribute('aria-label') === Zend_Registry::get('DEEC_Translate')->t('CLEAR') && $a->getAttribute('rel') === 'keyword', 'Clear name/target changed');
			} else check($new === $old, 'Task filter or special action configuration changed: ' . $name);
		}
		check($modern->getDefault('states') === ['100', '101', '102', '103', '104'] && $modern->getDefault('state') === '100' && $modern->getDefault('daterange') === 'all', 'Task status/date defaults changed');
	}
	foreach (['index', 'select', 'edit', 'view'] as $action) foreach (['layout', 'plain'] as $layoutName) {
		$page = toolbarPage($locale, 'tasks', $action, $layoutName);
		check(substr_count($page['html'], 'class="dw-icon-symbols"') === 1 && substr_count($page['html'], 'dw-btn--control') === ($action === 'index' ? 11 : ($action === 'edit' ? 2 : 1)), 'Task layout or migrated control scope changed');
		$readonly = toolbarPage($locale, 'tasks', $action, $layoutName, [], true);
		foreach (buttonDom($readonly['toolbar'] . $readonly['bottom'])->getElementsByTagName('button') as $button) check($button->hasAttribute('disabled'), 'Readonly Task toolbar enabled');
	}
	foreach (['index', 'select'] as $context) {
		$ajax = toolbarPage($locale, 'tasks', 'search', 'layout', [], false, $context);
		check(array_keys($ajax) === ['content', 'toolbar'] && !str_contains($ajax['content'] . $ajax['toolbar'], '<symbol'), 'Tasks AJAX changed contract or duplicated symbols');
		check(substr_count($ajax['toolbar'], 'dw-btn--control') === ($context === 'index' ? 7 : 1) && substr_count($ajax['content'], 'dw-btn--control') === ($context === 'index' ? 4 : 0), 'Tasks AJAX footer lost selection context');
	}
}

check(!(new DEEC_Permission(['id' => 42, 'clientid' => 70]))->hasModule('tasks'), 'Task permission schema changed; review its migration independently');
toolbarContext('tasks', 'task', 'index', []);
$form = new Tasks_Form_Toolbar();
check($form->renderElement('edit') !== '' && (new TasksToolbarSubclassFixture())->renderElement('edit') !== '', 'Legacy unsupported-module visibility changed');
check($form->renderElementWithAttribs('edit', ['data-module' => 'contacts', 'data-controller' => 'contact']) === '', 'Task SVG bypassed an explicitly protected target');
$adapter->permissions[0]['userid'] = 99;
$adapter->permissions[0]['contacts'] = json_encode(['contact' => ['view', 'add', 'edit', 'delete']]);
$form = new Tasks_Form_Toolbar();
check($form->renderElementWithAttribs('copy', ['data-module' => 'contacts', 'data-controller' => 'contact']) === '', 'Foreign-user rights exposed a protected target');

foreach ([['tasks', 'position'], ['contacts', 'contact'], ['items', 'item'], ['sales', 'quote'], ['shops', 'order']] as [$module, $controller]) foreach (['index', 'edit', 'select'] as $action) {
	toolbarContext($module, $controller, $action);
	foreach ((new Tasks_Form_Toolbar())->getElements() as $element) check(!isset($element['iconFormat']), 'Task toolbar escaped module/controller boundary');
}
foreach (['get', 'add', 'copy', 'save', 'export'] as $action) {
	toolbarContext('tasks', 'task', $action);
	Zend_Controller_Front::getInstance()->getRequest()->setParam('sourcequote', 1);
	foreach ((new Tasks_Form_Toolbar())->getElements() as $element) check(!isset($element['iconFormat']), 'Special Task action/follow-up context migrated');
}
toolbarContext('tasks', 'task', 'index');
foreach (['TasksToolbarSubclassFixture', 'Tasks_Form_ToolbarInline', 'Tasks_Form_ToolbarPositions', 'Tasks_Form_Task'] as $class) foreach ((new $class())->getElements() as $name => $element) check(isset($element['iconFormat']) === ($class === 'Tasks_Form_ToolbarInline' && in_array($name, ['view', 'edit', 'copy', 'delete'], true)), 'Task subclass, row, position or form escaped its explicit scope');

echo $tasksBaseline;
echo 'PASS: ' . $checks . " Tasks toolbar, filter/status defaults, legacy, layout, AJAX and permission compatibility checks\n";
