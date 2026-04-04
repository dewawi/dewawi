<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_Finish extends Zend_View_Helper_Abstract
{
	public function finish()
	{
		$html = '';

		$html .= '<form id="quote-form" enctype="application/x-www-form-urlencoded" action="" method="post">';
		$html .= '<div class="dw-form-layout">';
		$html .= '<div class="dw-form-row">';
		$html .= '<div class="dw-field dw-field--col-3">';
		$html .= $this->view->form->renderElement('templateid');
		$html .= '</div>';
		$html .= '<div class="dw-field dw-field--col-3">';
		$html .= $this->view->form->renderElement('language');
		$html .= '</div>';
		$html .= '</div>';
		$html .= '<div class="dw-form-row">';
		$html .= '<div class="dw-field dw-field--col-12">';
		$html .= '<div id="output"></div>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</form>';

		return $html;
	}
}
