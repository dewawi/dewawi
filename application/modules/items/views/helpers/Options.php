<?php

class Zend_View_Helper_Options extends Zend_View_Helper_Abstract
{
	public function Options()
	{
		return
			'<div class="dw-positions" '
			. 'data-section="options" '
			. 'data-refresh="positions" '
			. 'data-parent="item" '
			. 'data-type="opt"></div>';
	}
}
