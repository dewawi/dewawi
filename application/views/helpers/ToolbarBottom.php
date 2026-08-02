<?php

class Zend_View_Helper_ToolbarBottom extends Zend_View_Helper_Abstract
{
	public function ToolbarBottom(): string
	{
		$toolbar = $this->view->toolbar ?? null;

		if (
			!is_object($toolbar)
			|| !method_exists($toolbar, 'renderElement')
		) {
			return '';
		}

		$html = '<div class="dw-toolbar dw-toolbar--bottom">';
		$html .= '<div class="dw-toolbar__main">';

		if ($this->view->action === 'select') {
			$html .= $toolbar->renderElement('apply');
		} else {
			foreach (['add', 'edit', 'copy', 'delete'] as $name) {
				if ($toolbar->getElement($name)) {
					$html .= $toolbar->renderElement($name);
				}
			}
		}

		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}
}
