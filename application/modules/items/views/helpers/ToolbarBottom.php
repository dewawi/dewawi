<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_ToolbarBottom extends Zend_View_Helper_Abstract
{
	public function ToolbarBottom(): string
	{
		$toolbar = $this->view->toolbar;

		$out = '';

		foreach (['add', 'edit', 'copy', 'delete'] as $name) {
			$out .= $toolbar->renderElement($name);
		}

		return $out;
	}
}
