<?php

class Zend_View_Helper_ToolbarBottom extends Zend_View_Helper_Abstract
{
	public function ToolbarBottom(): string
	{
		$toolbar = $this->view->toolbar;

		if ($this->view->action === 'select') {
			return $toolbar->renderElement('apply');
		}

		$out = '';

		foreach (['add', 'edit', 'copy', 'delete'] as $name) {
			$out .= $toolbar->renderElement($name);
		}

		return $out;
	}
}
