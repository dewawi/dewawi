<?php

class Zend_View_Helper_Toolbar extends Zend_View_Helper_Abstract
{
	public function Toolbar()
	{
		$html = '';

		if ($this->view->action === 'edit') {
			$html .= '<input class="id" type="hidden" value="' . $this->view->escape($this->view->id) . '" name="id" />';
			$html .= $this->view->toolbar->renderElement('copy');
			$html .= $this->view->toolbar->renderElement('delete');

			return $html;
		}

		if (
			$this->view->controller !== 'index'
			&& $this->view->controller !== 'media'
			&& $this->view->controller !== 'export'
		) {
			if (!empty($this->view->user['admin'])) {
				$html .= $this->view->toolbar->renderElement('copy');
				$html .= $this->view->toolbar->renderElement('delete');
			}

			if (
				$this->view->controller === 'category'
				|| $this->view->controller === 'page'
				|| $this->view->controller === 'tag'
			) {
				$html .= $this->view->toolbar->renderElement('type');
			}

			if ($this->view->toolbar->getValue('type') === 'shop') {
				$html .= $this->view->toolbar->renderElement('shopid');
			}
		}

		return $html;
	}
}
