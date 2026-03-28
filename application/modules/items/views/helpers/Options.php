<?php
/**
* Class inserts necessary code for Options	
*/
class Zend_View_Helper_Options extends Zend_View_Helper_Abstract{

	public function Options() {
		return
			'<div class="positionsContainer" ' .
			'data-parent="item" data-type="opt"></div>';
	}
}
