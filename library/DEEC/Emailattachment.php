<?php

class DEEC_Emailattachment {

	public static function validFilename(string $filename): bool
	{
		return $filename !== '' && strlen($filename) <= 255 && !in_array($filename, ['.', '..'], true)
			&& preg_match('//u', $filename) === 1 && !preg_match('/[\x00-\x1f\x7f\\\\\/]/u', $filename);
	}

	public static function getDirectory(string $module, string $controller, int $documentId, int $clientId): string
	{
		if(!preg_match('/^[a-z][a-z0-9-]*$/D', $module) || !preg_match('/^[a-z][a-z0-9-]*$/D', $controller) || $documentId <= 0 || $clientId <= 0) {
			throw new InvalidArgumentException('Invalid attachment context');
		}
		$directory = (new DEEC_Directory())->getUrl((string)$documentId, (string)$clientId);
		$path = realpath(BASE_PATH) . '/files/attachments/' . $module . '/' . $controller . '/' . $directory;
		self::checkPath($path);
		return $path;
	}

	public static function checkPath(string $path): void
	{
		$base = realpath(BASE_PATH);
		if(!$base || !str_starts_with($path, $base . '/') || str_contains($path, '\\') || preg_match('~/(?:\.{1,2})(?:/|$)|[\x00-\x1f\x7f]~', $path)) {
			throw new InvalidArgumentException('Invalid attachment path');
		}
		$current = $base;
		foreach(explode('/', substr($path, strlen($base) + 1)) as $part) {
			if($part === '') throw new InvalidArgumentException('Invalid attachment path');
			$current .= '/' . $part;
			if(is_link($current)) throw new InvalidArgumentException('Invalid attachment path');
		}
	}

	public static function getPath(array $attachment, bool $mustExist = true): string
	{
		$filename = (string)($attachment['filename'] ?? '');
		if(!self::validFilename($filename)) throw new InvalidArgumentException('Invalid attachment filename');
		$directory = self::getDirectory((string)$attachment['module'], (string)$attachment['controller'], (int)$attachment['documentid'], (int)$attachment['clientid']);
		if(rtrim((string)($attachment['location'] ?? ''), '/') !== $directory) throw new InvalidArgumentException('Invalid attachment location');
		$path = $directory . '/' . $filename;
		self::checkPath($path);
		if($mustExist && (!is_file($path) || !is_readable($path) || realpath($path) !== $path)) throw new RuntimeException('Attachment file is not available');
		return $path;
	}

	public static function validUpload(string $filename, string $path): bool
	{
		$types = [
			'pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
			'png' => ['image/png'], 'gif' => ['image/gif'], 'csv' => ['text/plain', 'text/csv', 'application/csv'],
			'zip' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed'],
		];
		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		return self::validFilename($filename) && $filename[0] !== '.' && !str_contains($filename, ',')
			&& rtrim($filename, '. ') === $filename && isset($types[$extension]) && is_file($path)
			&& filesize($path) > 0 && filesize($path) <= 10240000
			&& in_array((new finfo(FILEINFO_MIME_TYPE))->file($path), $types[$extension], true);
	}

	protected $basePath;

	protected $connection;

	protected $query;

	public function __construct($basePath, $host, $username, $password, $dbname) {
		$this->basePath = $basePath;
		$this->connection = mysqli_connect($host, $username, $password, $dbname);
		require_once(BASE_PATH.'/library/DEEC/Query.php');
		$this->query = new DEEC_Query();
	}

	public function addEmailattachment($data) {
		$columns = implode(", ", array_keys($data));
		$escaped_values = array_map(array($this->connection, 'real_escape_string'), array_values($data));

		$values = implode("', '", $escaped_values);
		$query = "INSERT INTO `emailattachment`($columns) VALUES ('$values')";

		//echo $query;
		if(mysqli_query($this->connection, $query)) {
			return mysqli_insert_id($this->connection);
		} else{
			return false;
		}
	}

	public function updateEmailattachment($id, $data) {
		$query = 'UPDATE emailattachment SET response = "'.$data['response'].'" WHERE id = "'.$id.'";';

		//echo $query;
		if(mysqli_query($this->connection, $query)) {
			return true;
		} else{
			return false;
		}
	}

	public function getEmailattachment($id) {
		$where = 'id = '.$id.' AND deleted = 0';
		$query = '
				SELECT
					* FROM emailattachment
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

	public function getEmailattachments($documentid, $module, $controller, $clientid) {
		$where = 'documentid = "'.$documentid.'" AND module = "'.$module.'" AND controller = "'.$controller.'"';
		if($where) {
			$where .= ' AND clientid = '.$clientid;
			$where .= ' AND deleted = 0';
		} else {
			$where = 'clientid = '.$clientid;
			$where .= ' AND deleted = 0';
		}
		$query = '
				SELECT
					* FROM emailattachment
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
}
