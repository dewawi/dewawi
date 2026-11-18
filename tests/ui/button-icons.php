<?php

// Reuse the B2 memory adapter, real forms and compatibility assertions; no application bootstrap.
require __DIR__ . '/buttons.php';
$checks = 0;
$form = new DEEC_Form();
$map = ['add' => 'add', 'edit' => 'edit', 'save' => 'save', 'delete' => 'delete', 'copy' => 'copy', 'view' => 'view', 'download' => 'download', 'upload' => 'upload', 'send' => 'send', 'search' => 'search', 'close' => 'close', 'clear' => 'close', 'filter' => 'filter', 'reset' => 'reset', 'apply' => 'apply'];

$symbols = new DOMDocument();
check($symbols->loadXML($form->renderButtonSymbols(), LIBXML_NONET), 'Local symbol file is not valid XML');
check(!$symbols->doctype, 'Symbol file must not have a DTD');
$allowed = [
	'svg' => ['class', 'width', 'height', 'aria-hidden', 'focusable'],
	'symbol' => ['id', 'viewBox'],
	'path' => ['d'], 'line' => ['x1', 'x2', 'y1', 'y2'], 'polyline' => ['points'],
	'polygon' => ['points'], 'circle' => ['cx', 'cy', 'r'], 'rect' => ['x', 'y', 'width', 'height', 'rx', 'ry'],
];
$ids = [];
foreach ($symbols->getElementsByTagName('*') as $node) {
	check(isset($allowed[$node->tagName]), 'Unexpected SVG element: ' . $node->tagName);
	foreach ($node->attributes as $attribute) {
		check(in_array($attribute->name, $allowed[$node->tagName], true), 'Unexpected SVG attribute: ' . $attribute->name);
		if (!in_array($node->tagName, ['svg', 'symbol'], true)) check((bool)preg_match('/^[-+.0-9 eEaAcChHlLmMqQsStTvVzZ,]+$/D', $attribute->value), 'Geometry contains non-geometric data');
	}
	if ($node->tagName === 'symbol') {
		$ids[] = $node->getAttribute('id');
		check($node->getAttribute('viewBox') === '0 0 24 24' && $node->childElementCount > 0, 'Empty symbol or inconsistent coordinate system');
	}
}
check(count($ids) === 14 && count(array_unique($ids)) === 14, 'Unexpected or duplicate symbols');
check($symbols->documentElement->getAttribute('aria-hidden') === 'true' && $symbols->documentElement->getAttribute('focusable') === 'false', 'Symbol definitions must be decorative');

foreach ($map as $action => $symbol) {
	$html = $form->renderButtonHtml(['text' => 'A & B <' . $action . '>', 'icon' => $action, 'iconFormat' => 'svg'], ['name' => 'unrelated', 'data-action' => 'custom', 'data-id' => '17']);
	$button = buttonDom($html)->firstChild;
	$svg = $button->getElementsByTagName('svg')->item(0);
	check($svg && $svg->getAttribute('aria-hidden') === 'true' && $svg->getAttribute('focusable') === 'false' && !$svg->textContent, 'SVG leaked a duplicate accessible name');
	check($svg->getElementsByTagName('use')->item(0)->getAttribute('href') === '#dw-icon-' . $symbol && in_array('dw-icon-' . $symbol, $ids, true), 'Semantic mapping or local reference missing');
	check($button->textContent === 'A & B <' . $action . '>' && $button->getAttribute('name') === 'unrelated' && $button->getAttribute('data-action') === 'custom', 'Icon altered text or behavior');
	check(!str_contains($html, '<symbol') && !str_contains($html, 'style=') && !str_contains($html, 'xlink:'), 'Fragment duplicated definitions or requires inline styles/legacy namespace');
}

foreach ([['iconFormat' => 'url', 'icon' => 'save'], ['iconFormat' => 'svg'], ['iconFormat' => 'svg', 'icon' => null], ['iconFormat' => 'svg', 'icon' => 'settings'], ['iconFormat' => 'svg', 'icon' => '../save'], ['iconFormat' => 'svg', 'icon' => 'https://example.org/x'], ['iconFormat' => 'svg', 'icon' => '<svg onload="alert(1)">'], ['iconFormat' => 'svg', 'icon' => ['save']], ['iconFormat' => 'svg', 'icon' => 'save" onload="x'], ['iconFormat' => 'svg', 'icon' => false, 'iconOnly' => true], ['iconFormat' => 'svg', 'icon' => 'save', 'iconOnly' => true]] as $invalid) {
	try {
		$form->renderButtonHtml($invalid + ['text' => ''], ['class' => 'save']);
		check(false, 'Invalid SVG presentation was accepted');
	} catch (InvalidArgumentException $error) {
		check(true, 'Invalid SVG rejected');
	}
}
check(!str_contains($form->renderButtonHtml(['text' => 'Save', 'icon' => false, 'iconFormat' => 'svg'], ['class' => 'save']), 'dw-btn__icon'), 'Text-only control contains an empty icon placeholder');
check(str_contains($form->renderButtonHtml(['text' => 'Save'], ['class' => 'save']), 'dw-btn__icon--save'), 'PNG class inference changed');
check(!str_contains($form->renderButtonHtml(['text' => 'Legacy', 'legacy' => true], ['class' => 'save']), '<svg'), 'Legacy controls were migrated');

foreach (['button', 'submit'] as $type) {
	$control = new DEEC_Form();
	$control->addElement(['name' => 'operation', 'type' => $type, 'label' => 'Save', 'icon' => 'save', 'iconFormat' => 'svg', 'value' => '0', 'wrap' => false, 'attribs' => ['data-context' => 'unchanged']]);
	$node = buttonDom($control->renderElement('operation'))->firstChild;
	check($node->getElementsByTagName('svg')->length === 1 && $node->getAttribute('value') === '0' && $node->getAttribute('type') === $type && $node->getAttribute('data-context') === 'unchanged', 'SVG metadata did not survive normalization/storage');
}
$link = buttonDom($helper->renderButton(['tag' => 'a', 'text' => 'Download', 'icon' => 'download', 'iconFormat' => 'svg'], ['href' => '/file.pdf', 'type' => 'application/pdf', 'target' => '_blank', 'rel' => 'noopener', 'disabled' => true]))->firstChild;
check($link->getAttribute('href') === '/file.pdf' && $link->getAttribute('type') === 'application/pdf' && $link->getAttribute('target') === '_blank' && $link->getAttribute('rel') === 'noopener' && $link->getAttribute('aria-disabled') === 'true' && $link->getAttribute('tabindex') === '-1', 'SVG link lost native or disabled semantics');

foreach (['de_DE', 'en_US'] as $locale) {
	buttonTranslations($locale);
	foreach ([['view', 'edit'], ['view'], []] as $rights) {
		buttonPermissions($rights);
		$png = buttonPilot();
		$svg = buttonPilot('svg');
		foreach (['toolbar', 'row', 'form'] as $part) {
			$old = buttonDom($png[$part]);
			$new = buttonDom($svg[$part]);
			$oldButtons = $old->getElementsByTagName('button');
			$newButtons = $new->getElementsByTagName('button');
			check($oldButtons->length === $newButtons->length, 'SVG changed permission visibility');
			foreach ($oldButtons as $i => $oldButton) {
				$newButton = $newButtons->item($i);
				check($oldButton->textContent === $newButton->textContent && $oldButton->attributes->length === $newButton->attributes->length, 'SVG changed translated label or button attributes');
				foreach ($oldButton->attributes as $attribute) check($newButton->getAttribute($attribute->name) === $attribute->value, 'SVG changed attribute: ' . $attribute->name);
				check($newButton->getElementsByTagName('svg')->length === 1, 'Pilot icon missing');
			}
		}
	}
}
foreach ([[99, 70, false], [42, 99, true]] as [$user, $client, $visible]) {
	buttonPermissions(['view', 'edit'], $user, $client);
	$inline = new Contacts_Form_InquiryToolbarInline();
	$inline->setElementData('edit', ['iconFormat' => 'svg']);
	check(($inline->renderElement('edit') !== '') === $visible, 'SVG changed user permission or authorized shared context');
}
buttonPermissions(['view', 'edit']);
$inline->setMode('readonly');
check(buttonDom($inline->renderElement('edit'))->firstChild->hasAttribute('disabled'), 'SVG bypassed readonly');
check($inline->renderElementWithAttribs('edit', ['data-module' => 'sales', 'data-controller' => 'invoice']) === '', 'SVG bypassed explicit target authorization');

echo 'PASS: ' . $checks . " SVG catalog, safety, semantics, translation, permissions and compatibility checks\n";
