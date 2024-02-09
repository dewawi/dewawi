<?php

class DEEC_Emailattachment {

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

		$values  = implode("', '", $escaped_values);
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
