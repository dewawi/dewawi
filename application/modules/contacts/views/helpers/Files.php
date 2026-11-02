<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_Files extends Zend_View_Helper_Abstract
{
	public function Files()
	{
		$documents = $this->view->partial('contact/document-files.phtml', [
			'documentFiles' => (array)($this->view->documentFiles ?? []),
		]);

		return $documents . '<iframe src="'
			. $this->view->baseUrl() 
			. '/library/FileManager/dialog.php?lang=de&type=0" width="100%" height="700px"></iframe>';
	}
}
