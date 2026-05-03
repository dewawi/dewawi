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
			$html .= $this->renderIndexToolbar($toolbar);
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

	protected function renderIndexToolbar($toolbar): string
	{
		if (!$toolbar) {
			return '';
		}

		$html = '';

		$html .= '<div class="dw-toolbar__main">';
		$html .= $this->renderToolbarArea($toolbar, 'actions');
		$html .= $this->renderToolbarArea($toolbar, 'search');
		$html .= $this->renderToolbarArea($toolbar, 'meta');
		$html .= $this->renderToolbarArea($toolbar, 'category');
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
			$html .= '<a class="all">' . $view->translate('TOOLBAR_ALL') . '</a> | ';
			$html .= '<a class="none">' . $view->translate('TOOLBAR_NONE') . '</a>';
			$html .= '</div>';
		}

		$html .= '<div class="dw-filter-card__body">';
		$html .= $toolbar->renderElement($name);

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
		$filters = $toolbar->getToolbarElements('filters');
		$html = '';

		foreach ($filters as $name => $element) {
			$value = $toolbar->getValue($name);
			$default = $toolbar->getDefault($name);

			if ($value === null || $value === '' || $value === '0' || $value == $default) {
				continue;
			}

			if (is_array($value) && $value == $default) {
				continue;
			}

			$label = $element['label'] ?? strtoupper($name);

			$html .= '<button type="button" class="dw-filter-chip"'
				. ' data-action="clear-filter"'
				. ' data-filter="' . htmlspecialchars($name) . '">'
				. htmlspecialchars($this->view->translate($label))
				. '<span aria-hidden="true"> ×</span>'
				. '</button>';
		}

		if ($html === '') {
			return '';
		}

		return '<div class="dw-active-filters">' . $html . '</div>';
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
