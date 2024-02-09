<?php

class DEEC_Category {

	protected $basePath;

	protected $connection;

	public function __construct($basePath, $host, $username, $password, $dbname) {
		$this->basePath = $basePath;
		$this->connection = mysqli_connect($host, $username, $password, $dbname);
	}

	public function getCategory($id) {
		$where = 'id = '.$id.' AND deleted = 0';
		$query = '
				SELECT
					* FROM category
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

	public function getCategories($type, $clientid) {
		$where = 'type = "'.$type.'" AND clientid = '.$clientid.' AND deleted = 0';
		$query = '
				SELECT
					* FROM category
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
