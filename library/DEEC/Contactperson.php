<?php

class DEEC_Contactperson {

	protected $basePath;

	protected $connection;

	protected $query;

	public function __construct($basePath, $host, $username, $password, $dbname) {
		$this->basePath = $basePath;
		$this->connection = mysqli_connect($host, $username, $password, $dbname);
		require_once(BASE_PATH.'/library/DEEC/Query.php');
		$this->query = new DEEC_Query();
	}

	public function getContactperson($id, $clientid) {
		$id = (int)$id;
		$clientid = (int)$clientid;

		$query = '
			SELECT *
			FROM contactperson
			WHERE id = '.$id.'
				AND clientid = '.$clientid.'
				AND deleted = 0
			ORDER BY id;
		';

		$result = mysqli_query($this->connection, $query);

		if($result && mysqli_num_rows($result) > 0) {
			return mysqli_fetch_all($result, MYSQLI_ASSOC);
		}

		return false;
	}

	public function getContactpersons($contactid, $clientid) {
		$contactid = (int)$contactid;
		$clientid = (int)$clientid;

		$query = '
			SELECT *
			FROM contactperson
			WHERE parentid = '.$contactid.'
				AND clientid = '.$clientid.'
				AND deleted = 0
			ORDER BY id;
		';

		$result = mysqli_query($this->connection, $query);

		if($result && mysqli_num_rows($result) > 0) {
			return mysqli_fetch_all($result, MYSQLI_ASSOC);
		}

		return false;
	}
}
