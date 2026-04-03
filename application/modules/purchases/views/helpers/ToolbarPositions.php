<?php

class Zend_View_Helper_ToolbarPositions extends Zend_View_Helper_Abstract
{
	public function toolbarPositions($class = null)
	{
		$view = $this->view;
		$toolbar = $view->toolbarPositions;

		$classes = 'toolbar positions';
		if (!empty($class)) {
			$classes .= ' ' . $class;
		}

		$html = '<div class="' . $classes . '">';
		$html .= $toolbar->renderElement('add');
		$html .= $toolbar->renderElement('select');
		$html .= $toolbar->renderElement('copy');
		$html .= $toolbar->renderElement('delete');
		$html .= '</div>';

		return $html;
	}
}
