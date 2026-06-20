<?php

class Zend_View_Helper_Toolbar extends Zend_View_Helper_Abstract
{
	public function toolbar()
	{
		$view = $this->view;

		if (empty($view->toolbar)) {
			return '';
		}

		$toolbar = $view->toolbar;
		$html = '';

		if ($view->action === 'edit') {
			$html .= $this->renderEditToolbar($toolbar, (int)$view->id);
		} elseif ($view->action === 'view') {
			$html .= $this->renderViewToolbar($toolbar, (int)$view->id);
		} elseif ($view->action === 'index' || $view->action === 'select') {
			$html .= $this->renderIndexToolbar($toolbar, $view->controller);
		}

		return $html;
	}

	protected function renderEditToolbar($toolbar, int $id): string
	{
		$target = $this->getCurrentTarget($id);

		$html = '';
		$html .= $toolbar->renderElementWithAttribs('copy', $target);
		$html .= $toolbar->renderElementWithAttribs('delete', $target);
		$html .= $toolbar->renderElement('state');

		return $html;
	}

	protected function renderViewToolbar($toolbar, int $id): string
	{
		return $toolbar->renderElementWithAttribs('copy', $this->getCurrentTarget($id));
	}

	protected function renderIndexToolbar($toolbar, $controller): string
	{
		if (!$toolbar) {
			return '';
		}

		$html = '';

		$html .= '<div class="dw-toolbar__main">';
		$html .= $this->renderToolbarArea($toolbar, 'actions');
		$html .= $this->renderToolbarArea($toolbar, 'search');
		$html .= $this->renderToolbarArea($toolbar, 'meta');
		$html .= $this->renderToolbarArea($toolbar, $controller);
		$html .= '</div>';

		$html .= $this->renderActiveFilters($toolbar);
		$html .= $this->renderFilterBlock($toolbar);

		return $html;
	}

	protected function renderToolbarArea($toolbar, string $area): string
	{
		$html = '';

		foreach ($toolbar->getToolbarElements($area) as $name => $element) {
			$html .= $toolbar->renderElement($name);
		}

		return $html;
	}

	protected function renderFilterBlock($toolbar): string
	{
		$filters = $toolbar->getToolbarElements('filters');

		if (!$filters) {
			return '';
		}

		$html = '<div class="dw-filter-panel" data-role="filter-panel">';
		$html .= '<form class="dw-filter-form">';
		$html .= '<div class="dw-filter-grid">';

		foreach ($filters as $name => $element) {
			$html .= $this->renderFilterElement($toolbar, $name, $element);
		}

		$html .= '</div>';
		$html .= '</form>';
		$html .= '</div>';

		return $html;
	}

	protected function renderFilterElement($toolbar, string $name, array $element): string
	{
		$view = $this->view;
		$type = $element['type'] ?? 'text';
		$label = $element['label'] ?? strtoupper($name);

		$classes = ['dw-filter-card'];

		if ($name === 'from' || $name === 'to') {
			$classes[] = 'daterange';

			if ($toolbar->getValue('daterange') !== 'custom') {
				$classes[] = 'is-hidden';
			}
		}

		$html = '<div class="' . implode(' ', $classes) . '">';

		if ($type === 'multicheckbox') {
			$html .= '<div class="dw-filter-card__actions">';
			$html .= '<a href="#" data-action="filter-check-all">' . $view->translate('TOOLBAR_ALL') . '</a>';

			if ($name === 'states') {
				$html .= ' | <a href="#" data-action="filter-check-values" data-values="100,101,102,103,104">' . $view->translate('TOOLBAR_OPEN') . '</a>';
				$html .= ' | <a href="#" data-action="filter-check-values" data-values="105">' . $view->translate('TOOLBAR_COMPLETED') . '</a>';
				$html .= ' | <a href="#" data-action="filter-check-values" data-values="106">' . $view->translate('TOOLBAR_CANCELLED') . '</a>';
			}

			$html .= '</div>';
		}

		$html .= '<div class="dw-filter-card__body">';
		$html .= $toolbar->renderElement($name);

		if ($type === 'multicheckbox') {
			$html .= '<script>
				document.querySelectorAll(".dw-filter-card input[name=\'' . $this->escapeAttr($name) . '[]\']").forEach(function(input) {
					var link = document.createElement("a");
					link.href = "#";
					link.className = "dw-choice-only";
					link.dataset.action = "filter-check-only";
					link.dataset.value = input.value;
					link.textContent = "nur";
					input.closest("label").appendChild(link);
				});
			</script>';
		}

		if ($name === 'from') {
			$html .= '<div id="fromDatePicker"></div>';
		}

		if ($name === 'to') {
			$html .= '<div id="toDatePicker"></div>';
		}

		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	protected function renderActiveFilters($toolbar): string
	{
		$filters = $this->getActiveFilters($toolbar);

		if (!$filters) {
			return '';
		}

		$html = '<div class="dw-active-filters">';
		$html .= '<span class="dw-active-filters__label">'
			. $this->escape($this->view->translate('TOOLBAR_SELECTED_FILTER'))
			. ':</span>';

		foreach ($filters as $filter) {
			$html .= '<button type="button" class="dw-filter-chip"'
				. ' data-action="clear-filter"'
				. ' data-filter="' . $this->escapeAttr($filter['name']) . '">'
				. '<strong>' . $this->escape($filter['label']) . ':</strong> '
				. $this->escape($filter['value'])
				. '<span aria-hidden="true"> ×</span>'
				. '</button>';
		}

		$html .= '</div>';

		return $html;
	}

	protected function getActiveFilters($toolbar): array
	{
		$out = [];
		$options = isset($this->view->options) && is_array($this->view->options)
			? $this->view->options
			: [];

		foreach ($toolbar->getToolbarElements('filters') as $name => $element) {
			if (in_array($name, ['from', 'to'], true)) {
				continue;
			}

			$value = $toolbar->getValue($name);
			$default = $toolbar->getDefault($name);

			if (!$this->isActiveFilterValue($value, $default)) {
				continue;
			}

			$displayValue = $this->getFilterDisplayValue($toolbar, $name, $value, $options);

			if ($displayValue === '') {
				continue;
			}

			$out[] = [
				'name' => $name,
				'label' => $this->getFilterLabel($name, $element),
				'value' => $displayValue,
			];
		}

		return $out;
	}

	protected function isActiveFilterValue($value, $default): bool
	{
		$value = $this->normalizeFilterValue($value);
		$default = $this->normalizeFilterValue($default);

		if ($value === null || $value === '' || $value === [] || $value === '0' || $value === 'all') {
			return false;
		}

		return $value != $default;
	}

	protected function normalizeFilterValue($value)
	{
		if (is_array($value)) {
			$value = array_values(array_filter($value, static function ($item) {
				return $item !== null && $item !== '';
			}));

			sort($value);
		}

		return $value;
	}

	protected function getFilterDisplayValue($toolbar, string $name, $value, array $options): string
	{
		if ($name === 'daterange') {
			return $this->getDateRangeDisplayValue($toolbar, $value, $options);
		}

		$optionSet = $this->getOptionSet($name, $options);

		if (is_array($value)) {
			$labels = [];

			foreach ($value as $item) {
				$labels[] = $this->resolveOptionLabel($item, $optionSet);
			}

			return implode(', ', array_filter($labels));
		}

		return $this->resolveOptionLabel($value, $optionSet);
	}

	protected function getDateRangeDisplayValue($toolbar, $value, array $options): string
	{
		if ($value === 'custom') {
			$from = (string)$toolbar->getValue('from');
			$to = (string)$toolbar->getValue('to');

			return trim($from . ' - ' . $to);
		}

		$optionSet = $this->getOptionSet('daterange', $options);

		return $this->resolveOptionLabel($value, $optionSet);
	}

	protected function getOptionSet(string $name, array $options): array
	{
		if (isset($options[$name]) && is_array($options[$name])) {
			return $options[$name];
		}

		$legacyMap = [
			'catid' => 'categories',
			'tagid' => 'tags',
			'country' => 'countries',
		];

		if (isset($legacyMap[$name]) && isset($options[$legacyMap[$name]]) && is_array($options[$legacyMap[$name]])) {
			return $options[$legacyMap[$name]];
		}

		return [];
	}

	protected function resolveOptionLabel($value, array $optionSet): string
	{
		if (isset($optionSet[$value])) {
			$label = $optionSet[$value];

			if (is_array($label) && isset($label['title'])) {
				return (string)$label['title'];
			}

			if (is_string($label)) {
				return (string)$this->view->translate($label);
			}

			return (string)$label;
		}

		return (string)$value;
	}

	protected function getFilterLabel(string $name, array $element): string
	{
		if (!empty($element['label'])) {
			return (string)$this->view->translate($element['label']);
		}

		$labels = [
			'keyword' => 'TOOLBAR_KEYWORD',
			'catid' => 'TOOLBAR_CATEGORY',
			'tagid' => 'TOOLBAR_TAG',
			'country' => 'TOOLBAR_COUNTRY',
			'states' => 'TOOLBAR_STATE',
			'daterange' => 'TOOLBAR_DATE_RANGE',
			'paymentstatus' => 'PROCESSES_PAYMENT_STATUS',
		];

		return (string)$this->view->translate($labels[$name] ?? strtoupper($name));
	}

	protected function escape($value): string
	{
		return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	}

	protected function escapeAttr($value): string
	{
		return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

	protected function getCurrentTarget(int $id): array
	{
		return [
			'data-id' => $id,
			'data-module' => $this->view->module,
			'data-controller' => $this->view->controller,
		];
	}
}
