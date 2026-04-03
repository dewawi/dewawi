<?php

class Zend_View_Helper_ToolbarPositionSets extends Zend_View_Helper_Abstract
{
	public function toolbarPositionSets($count = 0, $i = 0, $class = null)
	{
		$view = $this->view;
		$toolbar = $view->toolbarPositions;

		$classes = 'toolbar positionsets';
		if (!empty($class)) {
			$classes .= ' ' . $class;
		}

		$html = '<div class="' . $classes . '" style="float:right;">';

		$html .= $toolbar->renderElement('addset');
		$html .= $toolbar->renderElement('copyset');
		$html .= $toolbar->renderElement('deleteset');

		if ($i > 1) {
			$html .= $toolbar->renderElement('sortup');
		}

		if ($i < $count) {
			$html .= $toolbar->renderElement('sortdown');
		}

		$html .= '</div>';

		return $html;
	}
}
