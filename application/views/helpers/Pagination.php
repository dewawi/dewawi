<?php
/**
* Class inserts neccery code for Toolbar	
*/
class Zend_View_Helper_Pagination extends Zend_View_Helper_Abstract
{
	public function Pagination(): string
	{
		$p = (array)($this->view->pagination ?? []);

		$count = (int)($p['count'] ?? 0);
		$start = (int)($p['start'] ?? 0);
		$end = (int)($p['end'] ?? 0);
		$records = (int)($p['records'] ?? 0);

		$pageSelect = '';
		if (isset($this->view->toolbar) && $this->view->toolbar instanceof DEEC_Form) {
			$pageSelect = $this->view->toolbar->renderElement('page');
		}

		return
			'<div id="pagination" class="toolbar">'
				. '<span>Angezeigt: ' . $count . '</span>'
				. '<span>(' . $start . ':' . $end . ')</span>'
				. '<span>|</span>'
				. '<span>Insgesamt: ' . $records . '</span>'
				. '<span>|</span>'
				. '<span>Seite:' . $pageSelect . '</span>'
			. '</div>';
		return '';
	}
}
