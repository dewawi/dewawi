<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_Downloads extends Zend_View_Helper_Abstract
{
	public function Downloads()
	{
		return $this->view->partial('partials/downloads.phtml', [
			'downloads'         => $this->view->downloads,
			'downloadtrackings' => $this->view->downloadtrackings,
			'downloadsurl'      => $this->view->downloadsurl,
		]);
	}
}
