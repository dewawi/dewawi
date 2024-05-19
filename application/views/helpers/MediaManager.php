<?php
/**
* Class inserts neccery code for initialize media manager
*/
class Zend_View_Helper_MediaManager extends Zend_View_Helper_Abstract{

	public function MediaManager() {

		$client = Zend_Registry::get('Client');
		$defaultNamespace = new Zend_Session_Namespace('RF');

		$defaultNamespace->fldr = '';
		$defaultNamespace->writable = true;

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
		} elseif($this->view->module == 'campaigns') {

			$clientid = $client['id'];
			$dir1 = substr($clientid, 0, 1);
			if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
			else $dir2 = '0';

			if($this->view->id) {
				$id = $this->view->id;
				$dir3 = substr($id, 0, 1);
				if(strlen($id) > 1) $dir4 = substr($id, 1, 1);
				else $dir4 = '0';
				$url = 'campaigns/'.$dir1.'/'.$dir2.'/'.$clientid.'/'.$dir3.'/'.$dir4.'/'.$id;
			} else {
				$url = 'uploads';
				$defaultNamespace->writable = false;
			}

			$defaultNamespace->view_type = '1'; //detailed list
			$defaultNamespace->subfolder = $url;

		} elseif($this->view->module == 'sales' || $this->view->module == 'purchases' || $this->view->module == 'processes') {

			$clientid = $client['id'];
			$dir1 = substr($clientid, 0, 1);
			if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
			else $dir2 = '0';

			if(isset($this->view->contact['id']) && $this->view->contact['id']) {
				$id = $this->view->contact['id'];
				$dir3 = substr($id, 0, 1);
				if(strlen($id) > 1) $dir4 = substr($id, 1, 1);
				else $dir4 = '0';
				$url = 'contacts/'.$dir1.'/'.$dir2.'/'.$clientid.'/'.$dir3.'/'.$dir4.'/'.$id;
			} else {
				$url = 'uploads';
				$defaultNamespace->writable = false;
			}

			$defaultNamespace->view_type = '1'; //detailed list
			$defaultNamespace->subfolder = $url;

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

		} elseif($this->view->module == 'admin') {

			$clientid = $client['id'];
			$dir1 = substr($clientid, 0, 1);
			if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
			else $dir2 = '0';

			$url = $dir1.'/'.$dir2.'/'.$clientid.'/';

			$defaultNamespace->view_type = '0'; //boxes
			$defaultNamespace->subfolder = $url;
		}
	}
}



