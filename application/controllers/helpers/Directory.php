<?php

class Application_Controller_Action_Helper_Directory extends Zend_Controller_Action_Helper_Abstract
{
	public function isWritable($id, $type, $flashMessenger) {
		$this->_user = Zend_Registry::get('User');
        //error_log($id.'/'.$type.'/'.$this->_user['clientid']);

        $clientid = $this->_user['clientid'];
        $dir1 = substr($clientid, 0, 1);
        if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
        else $dir2 = '0';

        $dir3 = substr($id, 0, 1);
        if(strlen($id) > 1) $dir4 = substr($id, 1, 1);
        else $dir4 = '0';

        $url = $dir1.'/'.$dir2.'/'.$clientid.'/'.$dir3.'/'.$dir4.'/'.$id;

        if($type == 'item') {
		    //Create contact folder if does not already exists
            $path = BASE_PATH.'/files/items/';
            if(file_exists($path.$url) && is_dir($path.$url) && is_writable($path.$url)) {
                return true;
            } elseif(is_writable($path)) {
                $response = mkdir($path.$url, 0777, true);
                if($response === false) $flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
			    return $response;
            } else {
                $flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
			    return false;
            }
        } elseif($type == 'contact') {
		    //Create contact folder if does not already exists
            $path = BASE_PATH.'/files/contacts/';
            if(file_exists($path.$url) && is_dir($path.$url) && is_writable($path.$url)) {
                return true;
            } elseif(is_writable($path)) {
                $response = mkdir($path.$url, 0777, true);
                if($response === false) $flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
			    return $response;
            } else {
                $flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
			    return false;
            }
        } else {
		    //Create cache folder if does not already exists
            $cache = BASE_PATH.'/cache/';
            if(!file_exists($cache.$type.'/') && is_writable($cache)) mkdir($cache.$type.'/', 0777, true);
		    //Create contact folder if does not already exists
            $path = BASE_PATH.'/files/contacts/';
            if(file_exists($path.$url) && is_dir($path.$url) && is_writable($path.$url)) {
                return true;
            } elseif(is_writable($path)) {
                $response = mkdir($path.$url, 0777, true);
                if($response === false) $flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
			    return $response;
            } else {
                $flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
			    return false;
            }
        }
	    return false;
	}
}
