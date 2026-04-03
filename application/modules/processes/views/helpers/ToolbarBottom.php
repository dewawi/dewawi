<?php

class Zend_View_Helper_ToolbarBottom extends Zend_View_Helper_Abstract
{
	public function toolbarBottom()
	{
		$toolbar = $this->view->toolbar;

		$html = '';
		$html .= $toolbar->renderElement('add');
		$html .= $toolbar->renderElement('edit');
		$html .= $toolbar->renderElement('copy');
		$html .= $toolbar->renderElement('delete');

		return $html;
	}
}
