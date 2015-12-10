<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_Ordering extends Zend_View_Helper_Abstract{

	public function Ordering() { ?>
		<div id="ordering">
			<span id="asc">&uarr;</span>
			<span id="desc">&darr;</span>
		</div><?php
	}
}
