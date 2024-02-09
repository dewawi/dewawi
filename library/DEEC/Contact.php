<?php

class DEEC_Contact {

	protected $basePath;

	protected $connection;

	protected $query;

	public function __construct($basePath, $host, $username, $password, $dbname) {
		$this->basePath = $basePath;
		$this->connection = mysqli_connect($host, $username, $password, $dbname);
		require_once(BASE_PATH.'/library/DEEC/Query.php');
		$this->query = new DEEC_Query();
	}

	public function getContact($id) {
		$where = 'id = '.$id.' AND deleted = 0';
		$query = '
				SELECT
					* FROM user
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

	public function getContacts($catid, $clientid, $categories) {
		$where = '';
		$where = $this->query->getQueryCategory($where, $catid, $categories);
		if($where) {
			$where .= ' AND clientid = '.$clientid;
			$where .= ' AND deleted = 0';
		} else {
			$where = 'clientid = '.$clientid;
			$where .= ' AND deleted = 0';
		}
		$query = '
				SELECT
					* FROM contact
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
