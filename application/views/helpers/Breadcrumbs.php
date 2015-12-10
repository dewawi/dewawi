<?php
/**
* Class inserts neccery code for Breadcrumbs	
*/
class Zend_View_Helper_Breadcrumbs extends Zend_View_Helper_Abstract{

	public function Breadcrumbs() {
		$breadcrumbs = '<span><a href="'.$this->view->url(array('module'=>'default', 'controller'=>'index'), null, TRUE).'">'.$this->view->translate('DEWAWI').'</a></span>';
		if($this->view->module != 'default')
			$breadcrumbs .= '<span> &raquo; </span><span><a href="'.$this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller), null, TRUE).'">'.$this->view->translate(strtoupper($this->view->module)).'</a></span>';
		if($this->view->controller != 'index')
			$breadcrumbs .= '<span> &raquo; </span><span><a href="'.$this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller), null, TRUE).'">'.$this->view->translate(strtoupper($this->view->controller)).'</a></span>';
		if($this->view->action != 'index')
			$breadcrumbs .= '<span> &raquo; </span><span>'.$this->view->translate(strtoupper($this->view->action)).'</span>';
		return $breadcrumbs;
	}
}
