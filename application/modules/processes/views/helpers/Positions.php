<?php
/**
* Class inserts neccery code for Toolbar	
*/
class Zend_View_Helper_Positions extends Zend_View_Helper_Abstract
{
	public function Positions($parent)
	{
		return '<div class="dw-positions" data-parent="'.$parent.'" data-type="pos"></div>';
	}
}
