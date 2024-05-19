<?php

class Application_Controller_Action_Helper_Directory extends Zend_Controller_Action_Helper_Abstract
{
	public function isWritable($id, $type, $flashMessenger) {

		$url = $this->getUrl($id);

		$request  = $this->getRequest();
		$module = $request->getParam('module', null);
		$controller = $request->getParam('controller', null);

		$path = BASE_PATH.'/files/';

		if($type == 'item') {
			//Create contact folder if does not already exists
			$dir = 'items/';
			if(file_exists($path.$dir.$url) && is_dir($path.$dir.$url) && is_writable($path.$dir.$url)) {
				return true;
			} elseif(is_writable($path)) {
				$response = mkdir($path.$dir.$url, 0777, true);
				if($response === false) $flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
				return $response;
			} else {
				$flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
				return false;
			}
		} elseif($type == 'media') {
			//Create media folder if does not already exists
			$url = $this->getShortUrl();
			$path = BASE_PATH.'/media/';
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
		} elseif($type == 'export') {
			//Create export folder if does not already exists
			$url = $this->getShortUrl();
			$dir = 'export/';
			if(file_exists($path.$dir.$url) && is_dir($path.$dir.$url) && is_writable($path.$dir.$url)) {
				return true;
			} elseif(is_writable($path)) {
				$response = mkdir($path.$dir.$url, 0777, true);
				if($response === false) $flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
				return $response;
			} else {
				$flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
				return false;
			}
		} elseif($type == 'contact') {
			//Create contact folder if does not already exists
			$dir = 'contacts/';
			if(file_exists($path.$dir.$url) && is_dir($path.$dir.$url) && is_writable($path.$dir.$url)) {
				return true;
			} elseif(is_writable($path)) {
				$response = mkdir($path.$dir.$url, 0777, true);
				if($response === false) $flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
				return $response;
			} else {
				$flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
				return false;
			}
		} elseif($type == 'campaign') {
			//Create campaign folder if does not already exists
			$dir = 'campaigns/';
			if(file_exists($path.$dir.$url) && is_dir($path.$dir.$url) && is_writable($path.$dir.$url)) {
				return true;
			} elseif(is_writable($path)) {
				$response = mkdir($path.$dir.$url, 0777, true);
				if($response === false) $flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
				return $response;
			} else {
				$flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
				return false;
			}
		} elseif($type == 'attachment') {
			//Create cache folder if does not already exists
			if(!file_exists(BASE_PATH.'/cache/'.$controller.'/')) {
				mkdir(BASE_PATH.'/cache/'.$controller.'/');
				chmod(BASE_PATH.'/cache/'.$controller.'/', 0777);
			}
			//Create attachments folder if does not already exists
			$dir = 'attachments/'.$module.'/'.$controller.'/';
			if(file_exists($path.$dir.$url) && is_dir($path.$dir.$url) && is_writable($path.$dir.$url)) {
				return true;
			} elseif(is_writable($path)) {
				//error_log($path.$dir.$url);
				$response = mkdir($path.$dir.$url, 0777, true);
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
			if(file_exists($path.$dir.$url) && is_dir($path.$dir.$url) && is_writable($path.$dir.$url)) {
				return true;
			} elseif(is_writable($path)) {
				$response = mkdir($path.$dir.$url, 0777, true);
				if($response === false) $flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
				return $response;
			} else {
				$flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
				return false;
			}
		}
		return false;
	}

	public function getUrl($id) {

		$client = Zend_Registry::get('Client');

		$clientid = $client['id'];
		$dir1 = substr($clientid, 0, 1);
		if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
		else $dir2 = '0';

		$url = $dir1.'/'.$dir2.'/'.$clientid;

		if($id) {
			$dir3 = substr($id, 0, 1);
			if(strlen($id) > 1) $dir4 = substr($id, 1, 1);
			else $dir4 = '0';

			$url .= '/'.$dir3.'/'.$dir4.'/'.$id;
		}

		return $url;
	}

	public function getShortUrl() {

		$client = Zend_Registry::get('Client');

		$clientid = $client['id'];
		$dir1 = substr($clientid, 0, 1);
		if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
		else $dir2 = '0';

		$url = $dir1.'/'.$dir2.'/'.$clientid;

		return $url;
	}
}
