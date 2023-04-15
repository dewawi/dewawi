<?php
/**
* Class inserts neccery code for Messages	
*/
class Zend_View_Helper_HumanFileSize extends Zend_View_Helper_Abstract{

	public function HumanFileSize($bytes, $dec = 2) {
		$size = array(' B', ' kB', ' MB', ' GB', ' TB', ' PB', ' EB', ' ZB', ' YB');
		$factor = floor((strlen($bytes) - 1) / 3);

		return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
	}
}
