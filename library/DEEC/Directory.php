<?php

class DEEC_Directory
{
	public static function validUploadName(string $name): bool
	{
		return DEEC_Emailattachment::validFilename($name) && $name[0] !== '.' && rtrim($name, '. ') === $name
			&& !preg_match('/\.(?:php[0-9]*|phtml|pht|phar|cgi|pl|py|sh|exe|dll|com|bat|cmd|msi|htaccess|user\.ini)(?:\.|$)/i', $name);
	}

	public static function validStoredUpload(string $name, string $path): bool
	{
		if (!self::validUploadName($name) || !is_file($path) || filesize($path) < 1 || filesize($path) > 10240000) return false;
		$type = (new finfo(FILEINFO_MIME_TYPE))->file($path);
		if (in_array($type, ['text/x-php', 'application/x-httpd-php', 'application/x-executable', 'application/x-dosexec', 'application/x-sharedlib', 'application/x-shellscript'], true)) return false;
		$images = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'webp' => 'image/webp'];
		$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		return !isset($images[$extension]) || $images[$extension] === $type;
	}

	public static function storedPath(string $relative): string
	{
		if (!preg_match('~^(?:files|media|cache)/.+~D', $relative)) throw new InvalidArgumentException('Invalid stored file path');
		$path = realpath(BASE_PATH) . '/' . $relative;
		DEEC_Emailattachment::checkPath($path);
		if (!is_file($path) || !is_readable($path) || realpath($path) !== $path) throw new RuntimeException('Stored file is unavailable');
		return $path;
	}
	public function isWritable($id, $module, $controller, $type, $flashMessenger) {

		$url = $this->getUrl($id);

		$path = BASE_PATH.'/files/';

		if($type == 'item') {
			//Create contact folder if does not already exists
			$dir = 'items/';
			DEEC_Emailattachment::checkPath($path.$dir.$url);
			if(file_exists($path.$dir.$url) && is_dir($path.$dir.$url) && is_writable($path.$dir.$url)) {
				return true;
			} elseif(is_writable($path)) {
				$response = mkdir($path.$dir.$url, 0775, true);
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
			DEEC_Emailattachment::checkPath($path.$dir.$url);
			if(file_exists($path.$dir.$url) && is_dir($path.$dir.$url) && is_writable($path.$dir.$url)) {
				return true;
			} elseif(is_writable($path)) {
				$response = mkdir($path.$dir.$url, 0775, true);
				if($response === false) $flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
				return $response;
			} else {
				$flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
				return false;
			}
		} elseif($type == 'contact') {
			//Create contact folder if does not already exists
			$dir = 'contacts/';
			DEEC_Emailattachment::checkPath($path.$dir.$url);
			if(file_exists($path.$dir.$url) && is_dir($path.$dir.$url) && is_writable($path.$dir.$url)) {
				return true;
			} elseif(is_writable($path)) {
				$response = mkdir($path.$dir.$url, 0775, true);
				if($response === false) $flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
				return $response;
			} else {
				$flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
				return false;
			}
		} elseif($type == 'attachment') {
			//Create cache folder if does not already exists
			DEEC_Emailattachment::checkPath(BASE_PATH.'/cache/'.$controller);
			if(!file_exists(BASE_PATH.'/cache/'.$controller.'/')) {
				mkdir(BASE_PATH.'/cache/'.$controller.'/');
				chmod(BASE_PATH.'/cache/'.$controller.'/', 0775);
			}
			//Create attachments folder if does not already exists
			$dir = 'attachments/'.$module.'/'.$controller.'/';
			DEEC_Emailattachment::checkPath($path.$dir.$url);
			if(file_exists($path.$dir.$url) && is_dir($path.$dir.$url) && is_writable($path.$dir.$url)) {
				return true;
			} elseif(is_writable($path)) {
				//error_log($path.$dir.$url);
				$response = mkdir($path.$dir.$url, 0775, true);
				if($response === false) $flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
				return $response;
			} else {
				$flashMessenger->addMessage('MESSAGES_DIRECTORY_IS_NOT_WRITABLE');
				return false;
			}
		} else {
			//Create cache folder if does not already exists
			$cache = BASE_PATH.'/cache/';
			if(!file_exists($cache.$type.'/') && is_writable($cache)) mkdir($cache.$type.'/', 0775, true);
			//Create contact folder if does not already exists
			$path = BASE_PATH.'/files/contacts/';
			DEEC_Emailattachment::checkPath($path.$dir.$url);
			if(file_exists($path.$dir.$url) && is_dir($path.$dir.$url) && is_writable($path.$dir.$url)) {
				return true;
			} elseif(is_writable($path)) {
				$response = mkdir($path.$dir.$url, 0775, true);
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
		if (!filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) || !filter_var($clientid, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) throw new InvalidArgumentException('Invalid directory context');

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
		if (!filter_var($clientid, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) throw new InvalidArgumentException('Invalid directory context');

		$dir1 = substr($clientid, 0, 1);
		if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
		else $dir2 = '0';

		$url = $dir1.'/'.$dir2.'/'.$clientid;

		return $url;
	}
}
