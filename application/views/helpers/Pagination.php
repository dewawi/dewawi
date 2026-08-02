<?php

class Zend_View_Helper_Pagination extends Zend_View_Helper_Abstract
{
	public function Pagination(): string
	{
		$pagination = (array)($this->view->pagination ?? []);

		$count = (int)($pagination['count'] ?? 0);
		$start = (int)($pagination['start'] ?? 0);
		$end = (int)($pagination['end'] ?? 0);
		$records = (int)($pagination['records'] ?? 0);
		$page = max(1, (int)($pagination['page'] ?? 1));
		$pages = max(1, (int)($pagination['pages'] ?? 1));
		$limit = (int)($pagination['limit'] ?? 25);

		$html = '<div id="pagination" class="dw-pagination">';

		$html .= '<div class="dw-pagination__summary">';
		$html .= '<span>'
			. $this->escape(
				$this->view->translate('PAGINATION_DISPLAYED')
			)
			. ': '
			. $count
			. '</span>';

		$html .= '<span>'
			. $start
			. '–'
			. $end
			. '</span>';

		$html .= '<span>'
			. $this->escape(
				$this->view->translate('PAGINATION_TOTAL')
			)
			. ': '
			. $records
			. '</span>';
		$html .= '</div>';

		$html .= '<div class="dw-pagination__controls">';

		$html .= '<label'
			. ' class="dw-pagination__field"'
			. ' for="pagination-limit"'
			. '>';

		$html .= '<span>'
			. $this->escape(
				$this->view->translate('PAGINATION_SHOW')
			)
			. '</span>';

		$html .= '<select'
			. ' name="limit"'
			. ' id="pagination-limit"'
			. ' class="dw-select"'
			. '>';

		foreach ([10, 25, 50, 100] as $option) {
			$html .= '<option'
				. ' value="' . $option . '"'
				. ($option === $limit ? ' selected' : '')
				. '>'
				. $option
				. '</option>';
		}

		$html .= '</select>';
		$html .= '</label>';

		$html .= '<label'
			. ' class="dw-pagination__field"'
			. ' for="pagination-page"'
			. '>';

		$html .= '<span>'
			. $this->escape(
				$this->view->translate('PAGINATION_PAGE')
			)
			. '</span>';

		$html .= '<select'
			. ' name="page"'
			. ' id="pagination-page"'
			. ' class="dw-select"'
			. '>';

		for ($option = 1; $option <= $pages; $option++) {
			$html .= '<option'
				. ' value="' . $option . '"'
				. ($option === $page ? ' selected' : '')
				. '>'
				. $option
				. '</option>';
		}

		$html .= '</select>';

		$html .= '<span>'
			. $this->escape(
				$this->view->translate('PAGINATION_OF')
			)
			. ' '
			. $pages
			. '</span>';

		$html .= '</label>';

		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	protected function escape($value): string
	{
		return htmlspecialchars(
			(string)$value,
			ENT_QUOTES,
			'UTF-8'
		);
	}
}
