<?php

class DEEC_Address {

	protected $basePath;

	protected $connection;

	public function __construct($basePath, $host, $username, $password, $dbname) {
		$this->basePath = $basePath;
		$this->connection = mysqli_connect($host, $username, $password, $dbname);
	}

	public function getAddress($id) {
		$where = 'id = '.$id.' AND deleted = 0';
		$query = '
				SELECT
					* FROM address
				WHERE
					'.$where.'
				ORDER
					BY id;';
		//echo $query;
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_assoc($result);
		} else {
		    return false;
		}
	}

	public function getLatestAddress() {
		$where = 'deleted = 0 AND street IS NOT NULL';
		$query = '
				SELECT
					* FROM address
				WHERE
					'.$where.' AND clientid = 100
				ORDER
					BY geoupdated ASC, modified DESC, created DESC
				LIMIT 1';
		//echo $query;
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_assoc($result);
		} else {
		    return false;
		}
	}

	public function updateAddress($id, $data) {
    	$query = 'UPDATE address SET latitude = '.$data['latitude'].', longitude = '.$data['longitude'].', geoupdated = "'.$data['geoupdated'].'" WHERE id = "'.$id.'";';

		//echo $query;
		if(mysqli_query($this->connection, $query)) {
		    return true;
		} else{
		    return false;
		}
	}
}
