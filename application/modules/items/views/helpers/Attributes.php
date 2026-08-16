<?php

class Zend_View_Helper_Attributes extends Zend_View_Helper_Abstract
{
	public function Attributes()
	{
		return
			'<div class="dw-positions" '
			. 'data-section="attributes" '
			. 'data-refresh="positions" '
			. 'data-parent="item" '
			. 'data-type="atr"></div>';
	}
}
