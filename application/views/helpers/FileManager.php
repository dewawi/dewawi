<?php
/**
* Class inserts neccery code for initialize file manager
*/
class Zend_View_Helper_FileManager extends Zend_View_Helper_Abstract{

	public function FileManager() {

		$client = Zend_Registry::get('Client');
		$defaultNamespace = new Zend_Session_Namespace('RF');

		if($this->view->user['permissions']) $defaultNamespace->writable = true;
		else $defaultNamespace->writable = false;

		if($this->view->module == 'contacts') {

			$clientid = $client['id'];
			$dir1 = substr($clientid, 0, 1);
			if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
			else $dir2 = '0';

			$id = $this->view->id;
			$dir3 = substr($id, 0, 1);
			if(strlen($id) > 1) $dir4 = substr($id, 1, 1);
			else $dir4 = '0';

			$url = $dir1.'/'.$dir2.'/'.$clientid.'/'.$dir3.'/'.$dir4.'/'.$id;

			$defaultNamespace->view_type = '1'; //detailed list
			$defaultNamespace->subfolder = 'contacts/'.$url;
		} elseif($this->view->module == 'sales' || $this->view->module == 'purchases' || $this->view->module == 'processes') {

			$clientid = $client['id'];
			$dir1 = substr($clientid, 0, 1);
			if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
			else $dir2 = '0';

			$id = $this->view->contact['id'];
			$dir3 = substr($id, 0, 1);
			if(strlen($id) > 1) $dir4 = substr($id, 1, 1);
			else $dir4 = '0';

			$url = $dir1.'/'.$dir2.'/'.$clientid.'/'.$dir3.'/'.$dir4.'/'.$id;

			$defaultNamespace->view_type = '1'; //detailed list
			$defaultNamespace->subfolder = 'contacts/'.$url;
		} elseif($this->view->module == 'items') {

			$clientid = $client['id'];
			$dir1 = substr($clientid, 0, 1);
			if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
			else $dir2 = '0';

			$id = $this->view->id;
			$dir3 = substr($id, 0, 1);
			if(strlen($id) > 1) $dir4 = substr($id, 1, 1);
			else $dir4 = '0';

			$url = $dir1.'/'.$dir2.'/'.$clientid.'/'.$dir3.'/'.$dir4.'/'.$id;

			$defaultNamespace->view_type = '0'; //boxes
			$defaultNamespace->subfolder = 'items/'.$url;
		}
		//error_log($url);
		//if($this->view->id == 39053) print_r($defaultNamespace->subfolder);
	}
}



