<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Run complete original MVC pages through the existing read-only synthetic fixture.
$checks = 0;
function dashboardCheck(bool $condition, string $message): void {
	global $checks;
	$checks++;
	if (!$condition) throw new RuntimeException($message);
}
foreach (['de_DE' => 'Bearbeiten', 'en_US' => 'Edit'] as $locale => $label) {
	foreach (['standard', 'all', 'no-contacts', 'no-items', 'view-only'] as $scenario) {
		$url = '/?' . http_build_query(['fixture_locale' => $locale, 'fixture_dashboard' => $scenario]);
		$process = proc_open([PHP_BINARY, __DIR__ . '/button-page-integration.php', $url], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		$html = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);
		fclose($pipes[1]); fclose($pipes[2]);
		dashboardCheck(proc_close($process) === 0 && $errors === '', $locale . '/' . $scenario . ': MVC fixture failed: ' . $errors);
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
		libxml_clear_errors();
		$xpath = new DOMXPath($dom);
		dashboardCheck($xpath->query('//symbol')->length === 14 && $xpath->query('//svg[@class="dw-icon-symbols"]')->length === 1, 'Full page symbol bank missing');
		$buttons = $xpath->query('//button[@data-action="edit"]');
		$expected = ['standard' => 26, 'all' => 27, 'no-contacts' => 2, 'no-items' => 25, 'view-only' => 27][$scenario];
		dashboardCheck($buttons->length === $expected, $locale . '/' . $scenario . ': dashboard visibility changed');
		foreach ($buttons as $button) {
			$module = $button->getAttribute('data-module');
			$controller = $button->getAttribute('data-controller');
			$id = $button->getAttribute('data-id');
			$classes = explode(' ', $button->getAttribute('class'));
			dashboardCheck($button->getAttribute('type') === 'button' && ctype_digit($id), 'Native type or row ID changed');
			dashboardCheck(in_array([$module, $controller], [['contacts', 'contact'], ['items', 'item'], ['items', 'ledger']], true), 'Another dashboard action migrated');
			dashboardCheck(!array_diff(['dw-btn', 'dw-btn--icon', 'edit', 'dw-btn--control', 'dw-btn--sm'], $classes), 'Shared presentation or original class hook missing');
			dashboardCheck($button->getAttribute('aria-label') === $label, 'Translated accessible name changed');
			foreach (['id', 'name', 'value', 'href', 'rel', 'onclick', 'title', 'disabled'] as $attribute) dashboardCheck(!$button->hasAttribute($attribute), 'Unexpected new action attribute: ' . $attribute);
			$svg = $button->getElementsByTagName('svg')->item(0);
			dashboardCheck($svg !== null && $svg->getAttribute('aria-hidden') === 'true' && $svg->getAttribute('focusable') === 'false', 'Decorative SVG missing');
			dashboardCheck($svg->getElementsByTagName('use')->item(0)->getAttribute('href') === '#dw-icon-edit', 'Wrong semantic edit icon');
			$row = $button->parentNode->parentNode->parentNode;
			dashboardCheck($xpath->query('.//a[contains(@href,"/' . $module . '/' . $controller . '/edit/id/' . $id . '")]', $row)->length === 2, 'Button target differs from the original row links');
		}
		foreach ($xpath->query('//use[starts-with(@href,"#dw-icon-")]') as $use) dashboardCheck($xpath->query('//symbol[@id="' . substr($use->getAttribute('href'), 1) . '"]')->length === 1, 'Unresolved SVG reference');
		foreach ($xpath->query('//button[@name]') as $legacy) dashboardCheck($legacy->getElementsByTagName('svg')->length === 0 && !str_contains($legacy->getAttribute('class'), 'dw-btn--control'), 'Legacy task/document toolbar migrated');
		if ($scenario === 'all') {
			dashboardCheck($xpath->query('//button[@name="pdf"]')->length === 5, 'All five document dashboard groups were not exercised');
			dashboardCheck($xpath->query('//button[@name="edit"]')->length === 6 && $xpath->query('//button[@name="copy"]')->length === 5, 'Legacy toolbar visibility changed');
			foreach ($xpath->query('//button[@name]') as $legacy) dashboardCheck(!$legacy->hasAttribute('data-action') && !$legacy->hasAttribute('data-id'), 'Legacy targeting was silently repaired');
		}
	}
}
echo 'PASS: ' . $checks . " dashboard PHP assertions\n";
