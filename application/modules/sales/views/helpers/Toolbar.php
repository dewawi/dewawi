<?php

class Zend_View_Helper_Toolbar extends Zend_View_Helper_Abstract
{
	public function toolbar()
	{
		$view = $this->view;
		$toolbar = $view->toolbar;
		$html = '';

		if ($view->action === 'edit') {

			$html .= '<input class="id" type="hidden" value="' . (int)$view->id . '" name="id"/>';

			$html .= $toolbar->renderElement('copy');
			$html .= $toolbar->renderElement('delete');
			$html .= $toolbar->renderElement('state');

		} elseif ($view->action === 'view') {

			$html .= '<input class="id" type="hidden" value="' . (int)$view->id . '" name="id"/>';

			$html .= $toolbar->renderElement('copy');

		} elseif ($view->action === 'index') {

			$html .= $toolbar->renderElement('add');
			$html .= $toolbar->renderElement('edit');
			$html .= $toolbar->renderElement('copy');
			$html .= $toolbar->renderElement('delete');
			$html .= $toolbar->renderElement('filter');
			$html .= $toolbar->renderElement('keyword');
			$html .= $toolbar->renderElement('clear');
			$html .= $toolbar->renderElement('reset');
			$html .= $toolbar->renderElement('limit');

			// Filter Block
			$html .= '<div id="filter">';
			$html .= '<form>';
			$html .= '<table><tr>';

			// STATE
			$html .= '<td id="state" class="space">';
			$html .= '<h4>' . $view->translate('TOOLBAR_STATE') . '</h4>';
			$html .= '<a class="all">' . $view->translate('TOOLBAR_ALL') . '</a> | ';
			$html .= '<a class="none">' . $view->translate('TOOLBAR_NONE') . '</a><br>';
			$html .= $toolbar->renderElement('states');
			$html .= '</td>';

			// ORDER + COUNTRY
			$html .= '<td class="space">';
			$html .= '<h4>' . $view->translate('TOOLBAR_ORDERING') . '</h4>';
			$html .= $toolbar->renderElement('order') . '<br>';
			$html .= $toolbar->renderElement('sort') . '<br>';

			$html .= '<h4>' . $view->translate('TOOLBAR_COUNTRY') . '</h4>';
			$html .= $toolbar->renderElement('country');
			$html .= '</td>';

			// DATERANGE
			$html .= '<td id="daterange" class="space">';
			$html .= '<h4>' . $view->translate('TOOLBAR_DATE_RANGE') . '</h4>';
			$html .= $toolbar->renderElement('daterange');
			$html .= '</td>';

			// FROM
			$display = ($toolbar->getValue('daterange') !== 'custom') ? ' style="display:none;"' : '';

			$html .= '<td class="daterange"' . $display . '>';
			$html .= '<div style="margin-top:0;">';
			$html .= $view->translate('TOOLBAR_FROM');
			$html .= $toolbar->renderElement('from');
			$html .= '<div id="fromDatePicker"></div>';
			$html .= '</div></td>';

			// TO
			$html .= '<td class="daterange"' . $display . '>';
			$html .= '<div style="margin-top:0;">';
			$html .= $view->translate('TOOLBAR_TO');
			$html .= $toolbar->renderElement('to');
			$html .= '<div id="toDatePicker"></div>';
			$html .= '</div></td>';

			$html .= '</tr></table>';
			$html .= '</form>';
			$html .= '</div>';

			$html .= $toolbar->renderElement('catid');
		}

		return $html;
	}
}
