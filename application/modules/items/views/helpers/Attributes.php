<?php
/**
* Class inserts necessary code for Attributes	
*/
class Zend_View_Helper_Attributes extends Zend_View_Helper_Abstract{

	public function Attributes() {
		return
			'<div class="positionsContainer" ' .
			'data-parent="item" data-type="atr"></div>';
	}
}
