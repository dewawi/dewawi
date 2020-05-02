<?php
/**
* Class inserts neccery code for initialize file manager
*/
class Zend_View_Helper_FileManager extends Zend_View_Helper_Abstract{

	public function FileManager() {
        $defaultNamespace = new Zend_Session_Namespace('RF');

        if($this->view->user['permissions']) $defaultNamespace->writable = true;
        else $defaultNamespace->writable = false;

        if($this->view->controller == 'contact') {
            $dir1 = substr($this->view->id, 0, 1).'/';
            if(strlen($this->view->id) > 1) $dir2 = substr($this->view->id, 1, 1).'/';
            else $dir2 = '0/';
            $defaultNamespace->view_type = '1'; //detailed list
            $defaultNamespace->subfolder = 'contacts/'.$dir1.$dir2.$this->view->id.'/';
        } elseif($this->view->controller == 'quote') {
            $dir1 = substr($this->view->contact['id'], 0, 1).'/';
            if(strlen($this->view->contact['id']) > 1) $dir2 = substr($this->view->contact['id'], 1, 1).'/';
            else $dir2 = '0/';
            $defaultNamespace->view_type = '1'; //detailed list
            $defaultNamespace->subfolder = 'contacts/'.$dir1.$dir2.$this->view->contact['id'].'/';
        } elseif($this->view->controller == 'salesorder') {
            $dir1 = substr($this->view->contact['id'], 0, 1).'/';
            if(strlen($this->view->contact['id']) > 1) $dir2 = substr($this->view->contact['id'], 1, 1).'/';
            else $dir2 = '0/';
            $defaultNamespace->view_type = '1'; //detailed list
            $defaultNamespace->subfolder = 'contacts/'.$dir1.$dir2.$this->view->contact['id'].'/';
        } elseif($this->view->controller == 'invoice') {
            $dir1 = substr($this->view->contact['id'], 0, 1).'/';
            if(strlen($this->view->contact['id']) > 1) $dir2 = substr($this->view->contact['id'], 1, 1).'/';
            else $dir2 = '0/';
            $defaultNamespace->view_type = '1'; //detailed list
            $defaultNamespace->subfolder = 'contacts/'.$dir1.$dir2.$this->view->contact['id'].'/';
        } elseif($this->view->controller == 'deliveryorder') {
            $dir1 = substr($this->view->contact['id'], 0, 1).'/';
            if(strlen($this->view->contact['id']) > 1) $dir2 = substr($this->view->contact['id'], 1, 1).'/';
            else $dir2 = '0/';
            $defaultNamespace->view_type = '1'; //detailed list
            $defaultNamespace->subfolder = 'contacts/'.$dir1.$dir2.$this->view->contact['id'].'/';
        } elseif($this->view->controller == 'creditnote') {
            $dir1 = substr($this->view->contact['id'], 0, 1).'/';
            if(strlen($this->view->contact['id']) > 1) $dir2 = substr($this->view->contact['id'], 1, 1).'/';
            else $dir2 = '0/';
            $defaultNamespace->view_type = '1'; //detailed list
            $defaultNamespace->subfolder = 'contacts/'.$dir1.$dir2.$this->view->contact['id'].'/';
        } elseif($this->view->controller == 'quoterequest') {
            $dir1 = substr($this->view->contact['id'], 0, 1).'/';
            if(strlen($this->view->contact['id']) > 1) $dir2 = substr($this->view->contact['id'], 1, 1).'/';
            else $dir2 = '0/';
            $defaultNamespace->view_type = '1'; //detailed list
            $defaultNamespace->subfolder = 'contacts/'.$dir1.$dir2.$this->view->contact['id'].'/';
        } elseif($this->view->controller == 'purchaseorder') {
            $dir1 = substr($this->view->contact['id'], 0, 1).'/';
            if(strlen($this->view->contact['id']) > 1) $dir2 = substr($this->view->contact['id'], 1, 1).'/';
            else $dir2 = '0/';
            $defaultNamespace->view_type = '1'; //detailed list
            $defaultNamespace->subfolder = 'contacts/'.$dir1.$dir2.$this->view->contact['id'].'/';
        } elseif($this->view->controller == 'item') {
            $dir1 = substr($this->view->id, 0, 1).'/';
            if(strlen($this->view->id) > 1) $dir2 = substr($this->view->id, 1, 1).'/';
            else $dir2 = '0/';
            $defaultNamespace->view_type = '0'; //boxes
            $defaultNamespace->subfolder = 'items/'.$dir1.$dir2.$this->view->id.'/';
        }
        if(is_link(BASE_PATH.'/library')) {
            $path = explode('/', BASE_PATH);
            $defaultNamespace->extrapath = end($path);
        }
        //print_r(session_id());
	}
}



