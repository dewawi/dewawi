<?php

class Zend_View_Helper_Files extends Zend_View_Helper_Abstract
{
	public function Files(): string
	{
		return '<iframe src="' . $this->view->baseUrl() . '/library/FileManager/dialog.php?lang=de&type=0" width="100%" height="700px"></iframe>';
	}
}
