<?php

class Zend_View_Helper_ToolbarPositions extends Zend_View_Helper_Abstract
{
	public function toolbarPositions($class = null)
	{
		$view = $this->view;
		$toolbar = $view->toolbarPositions;

		if (!$toolbar || !is_object($toolbar) || !method_exists($toolbar, 'renderElement')) {
			return '';
		}

		$classes = 'toolbar positions';
		if (!empty($class)) {
			$classes .= ' ' . $class;
		}

		$html = '<div class="' . $classes . '">';
		$html .= $toolbar->renderElement('add-position');
		$html .= $toolbar->renderElement('select-position');
		$html .= $toolbar->renderElement('copy-selected-position');
		$html .= $toolbar->renderElement('delete-selected-position');
		$html .= '</div>';

		return $html;
	}
}
