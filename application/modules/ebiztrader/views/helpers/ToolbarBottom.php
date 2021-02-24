<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_ToolbarBottom extends Zend_View_Helper_Abstract
{
	public function ToolbarBottom() {
		echo $this->view->toolbar->add;
		echo $this->view->toolbar->edit;
		echo $this->view->toolbar->copy;
		echo $this->view->toolbar->delete;
	}
}
