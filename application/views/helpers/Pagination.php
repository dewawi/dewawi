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

		$page = (int)($p['page'] ?? 1);
		$pages = (int)($p['pages'] ?? 1);
		$limit = (int)($p['limit'] ?? 25);

		$html = '<div id="pagination" class="dw-pagination toolbar">';

		$html .= '<span>Angezeigt: ' . $count . '</span>';
		$html .= '<span>(' . $start . ':' . $end . ')</span>';
		$html .= '<span>|</span>';
		$html .= '<span>Insgesamt: ' . $records . '</span>';

		$html .= '<span>|</span>Zeige: <select name="limit" id="pagination-limit" class="dw-select">';
		foreach ([10, 25, 50, 100] as $option) {
			$selected = $option === $limit ? ' selected="selected"' : '';
			$html .= '<option value="' . $option . '"' . $selected . '>' . $option . '</option>';
		}
		$html .= '</select>';

		$html .= '<span>|</span><span>Seite:</span>';
		$html .= '<select name="page" id="pagination-page" class="dw-select">';
		for ($i = 1; $i <= $pages; $i++) {
			$selected = $i === $page ? ' selected="selected"' : '';
			$html .= '<option value="' . $i . '"' . $selected . '>' . $i . '</option>';
		}
		$html .= '</select>';

		$html .= '<span>/ ' . $pages . '</span>';
		$html .= '</div>';

		return $html;
	}
}
