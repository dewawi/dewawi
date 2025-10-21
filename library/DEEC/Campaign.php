<?php

class DEEC_Campaign {

	protected $basePath;

	protected $connection;

	public function __construct($basePath, $host, $username, $password, $dbname) {
		$this->basePath = $basePath;
		$this->connection = mysqli_connect($host, $username, $password, $dbname);
	}

	public function getCampaigns() {
		$where = 'deleted = 0';
		$query = '
				SELECT
					* FROM campaign
				WHERE
					'.$where.'
				ORDER
					BY id;';
		//echo $query;
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_all($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function isCampaigns($id, $module, $controller, $type, $flashMessenger) {

		$url = $this->getUrl($id);

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

	public function getUrl($id, $clientid) {

		$dir1 = substr($clientid, 0, 1);
		if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
		else $dir2 = '0';

		$dir3 = substr($id, 0, 1);
		if(strlen($id) > 1) $dir4 = substr($id, 1, 1);
		else $dir4 = '0';

		$url = $dir1.'/'.$dir2.'/'.$clientid.'/'.$dir3.'/'.$dir4.'/'.$id;

		return $url;
	}

	public function getShortUrl($clientid) {

		$dir1 = substr($clientid, 0, 1);
		if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
		else $dir2 = '0';

		$url = $dir1.'/'.$dir2.'/'.$clientid;

		return $url;
	}

	public function touchLastSent($id, DateTime $when) {
		$id = (int)$id;
		$ts = $when->format('Y-m-d H:i:s');
		$sql = "UPDATE campaign SET lastsent = '$ts' WHERE id = $id";
		mysqli_query($this->connection, $sql);
	}
}
