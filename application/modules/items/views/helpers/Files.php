<?php
/**
* Class inserts necessary code for Files	
*/
class Zend_View_Helper_Files extends Zend_View_Helper_Abstract{

	public function Files() {
		$v = $this->view;

		if (empty($v->dirwritable)) {
			return '';
		}

		$src = $v->baseUrl() . '/library/FileManager/dialog.php?lang=de&type=0';

		return
			'<iframe src="' . $v->escape($src) . '" ' .
			'width="100%" height="700px"></iframe>';
	}
}
