<?php

class Zend_View_Helper_Toolbar extends Zend_View_Helper_Abstract
{
	public function Toolbar()
	{
		$view = $this->view;
		$toolbar = $view->toolbar;
		$html = '';

		if (!$toolbar || !is_object($toolbar) || !method_exists($toolbar, 'renderElement')) {
			return '';
		}

		if ($view->action === 'edit') {
			$html .= '<input class="id" type="hidden" value="' . $view->escape($view->id) . '" name="id" />';
			$html .= $toolbar->renderElement('copy');
			$html .= $toolbar->renderElement('delete');

			return $html;
		}

		if (
			$view->controller !== 'index'
			&& $view->controller !== 'media'
			&& $view->controller !== 'export'
		) {
			if (!empty($view->user['admin'])) {
				$html .= $toolbar->renderElement('copy');
				$html .= $toolbar->renderElement('delete');
			}

			if (
				$view->controller === 'category'
				|| $view->controller === 'page'
				|| $view->controller === 'tag'
			) {
				$html .= $toolbar->renderElement('type');
			}

			if ($toolbar->getValue('type') === 'shop') {
				$html .= $toolbar->renderElement('shopid');
			}
		}

		return $html;
	}
}
