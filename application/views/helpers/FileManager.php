<?php
/**
* Class inserts neccery code for initialize file manager elFinder
*/
class Zend_View_Helper_FileManager extends Zend_View_Helper_Abstract{

	public function FileManager() {
        $defaultNamespace = new Zend_Session_Namespace('RF');
        if($this->view->controller == 'item') {
            $defaultNamespace->view_type = '0'; //boxes
            $defaultNamespace->subfolder = 'images/';
        } elseif ($this->view->controller == 'contact') {
            $defaultNamespace->view_type = '1'; //detailed list
            $defaultNamespace->subfolder = 'contacts/'.substr($this->view->id, 0, 1).'/'.$this->view->id.'/';
        }
        //print_r($_SESSION);
        //print_r(session_id());
	}
}
