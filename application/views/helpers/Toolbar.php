<?php

class Zend_View_Helper_Toolbar extends Zend_View_Helper_Abstract
{
	public function toolbar()
	{
		$view = $this->view;
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
		$html = '<input class="id" type="hidden" value="' . $id . '" name="id"/>';

		$html .= $toolbar->renderElement('copy');
		$html .= $toolbar->renderElement('delete');
		$html .= $toolbar->renderElement('state');

		return $html;
	}

	protected function renderViewToolbar($toolbar, int $id): string
	{
		$html = '<input class="id" type="hidden" value="' . $id . '" name="id"/>';
		$html .= $toolbar->renderElement('copy');

		return $html;
	}

	protected function renderIndexToolbar($toolbar): string
	{
		$html = '';

		$html .= $this->renderToolbarArea($toolbar, 'actions');
		$html .= $this->renderToolbarArea($toolbar, 'search');
		$html .= $this->renderToolbarArea($toolbar, 'meta');

		$html .= $this->renderFilterBlock($toolbar);

		$html .= $this->renderToolbarArea($toolbar, 'category');

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

		if ($label) {
			$html .= '<h4 class="dw-filter-card__title">' . $view->translate($label) . '</h4>';
		}

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
}
