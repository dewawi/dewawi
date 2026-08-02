<?php

class Zend_View_Helper_ToolbarPositions extends Zend_View_Helper_Abstract
{
	public function toolbarPositions(
		string $location,
		int $setId = 0
	): string {
		$toolbar = $this->view->toolbarPositions;

		if (
			!is_object($toolbar)
			|| !method_exists($toolbar, 'renderElement')
		) {
			return '';
		}

		$searchId = 'search-position-'
			. $location
			. '-'
			. $setId;

		$html = '<div'
			. ' class="dw-toolbar dw-toolbar--positions"'
			. ' data-location="' . $this->escape($location) . '"'
			. '>';

		$html .= $toolbar->renderElement('add-position');

		$html .= $toolbar->renderElementWithAttribs(
			'search-position',
			[
				'id' => $searchId,
			]
		);

		$html .= $toolbar->renderElement('select-position');
		$html .= $toolbar->renderElement('copy-selected-position');
		$html .= $toolbar->renderElement('delete-selected-position');

		$html .= '</div>';

		return $html;
	}

	protected function escape(string $value): string
	{
		return htmlspecialchars(
			$value,
			ENT_QUOTES,
			'UTF-8'
		);
	}
}
