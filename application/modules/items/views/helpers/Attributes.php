<?php
/**
* Class inserts necessary code for Attributes	
*/
class Zend_View_Helper_Attributes extends Zend_View_Helper_Abstract{

	public function Attributes() {
		return
			'<div class="dw-positions" ' .
			'data-parent="item" data-type="atr"></div>';
	}
}
