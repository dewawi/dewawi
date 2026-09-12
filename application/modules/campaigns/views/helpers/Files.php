<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_Files extends Zend_View_Helper_Abstract
{
	public function Files()
	{
		$directory = trim((string)($this->view->contactUrl ?? ''));

		return '<iframe src="'
			. $this->view->baseUrl()
			. '/library/FileManager/dialog.php?lang=de&type=0&directory='
			. $directory
			. '" width="100%" height="700px"></iframe>';
	}
}
