<?php
/**
* Class inserts neccery code for Toolbar
*/class Zend_View_Helper_Toolbar extends Zend_View_Helper_Abstract
{
	public function Toolbar(): string
	{
		$v = $this->view;
		$tb = $v->toolbar;

		if (!$tb || !method_exists($tb, 'renderElement')) {
			return '';
		}

		$html = '';

		if ($v->action === 'edit') {
			$html .= '<input class="id" type="hidden" value="'.htmlspecialchars((string)$v->id).'" name="id"/>';
			$html .= $tb->renderElement('copy');
			$html .= $tb->renderElement('delete');
			return $html;
		}

		if ($v->action === 'add') {
			$html .= $tb->renderElement('save');
			return $html;
		}

		if ($v->action === 'select') {
			$html .= '<span>'.htmlspecialchars($v->translate('TOOLBAR_SEARCH')).'</span>';
			$html .= $tb->renderElement('keyword');
			$html .= $tb->renderElement('clear');
			$html .= $tb->renderElement('limit');
			$html .= $tb->renderElement('catid');
			$html .= '<input id="type" type="hidden" name="type" value="select"/>';
			return $html;
		}

		if ($v->action === 'index') {

			// item: currently commented out in legacy
			if ($v->controller === 'item') {
				$html .= $tb->renderElement('add');
				$html .= $tb->renderElement('edit');
				$html .= $tb->renderElement('copy');
				$html .= $tb->renderElement('delete');
				$html .= $tb->renderElement('keyword');
				$html .= $tb->renderElement('clear');
				$html .= $tb->renderElement('reset');
				$html .= $tb->renderElement('order');
				$html .= $tb->renderElement('sort');
				$html .= $tb->renderElement('manufacturerid');
				$html .= $tb->renderElement('limit');
				$html .= $tb->renderElement('catid');
				$html .= $tb->renderElement('tagid');
				return $html;
			}

			if ($v->controller === 'pricerule') {
				$html .= $tb->renderElement('add');
				$html .= $tb->renderElement('edit');
				$html .= $tb->renderElement('copy');
				$html .= $tb->renderElement('delete');
				$html .= $tb->renderElement('keyword');
				$html .= $tb->renderElement('clear');
				$html .= $tb->renderElement('reset');
				$html .= $tb->renderElement('limit');
				return $html;
			}

			// default
			$html .= $tb->renderElement('add');
			$html .= $tb->renderElement('edit');
			$html .= $tb->renderElement('copy');
			$html .= $tb->renderElement('delete');
			$html .= $tb->renderElement('keyword');
			$html .= $tb->renderElement('clear');
			$html .= $tb->renderElement('reset');
			$html .= $tb->renderElement('order');
			$html .= $tb->renderElement('sort');
			$html .= $tb->renderElement('limit');
			return $html;
		}

		return $html;
	}
}
