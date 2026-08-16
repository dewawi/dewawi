<?php

class Zend_View_Helper_Variants extends Zend_View_Helper_Abstract
{
	public function Variants()
	{
		return
			'<div class="dw-positions" '
			. 'data-section="variants" '
			. 'data-refresh="positions" '
			. 'data-parent="item" '
			. 'data-type="opt"></div>';
	}
}
