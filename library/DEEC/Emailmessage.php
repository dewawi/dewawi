<?php

class DEEC_Emailmessage {

	protected $basePath;

	protected $connection;

	protected $query;

	public function __construct($basePath, $host, $username, $password, $dbname) {
		$this->basePath = $basePath;
		$this->connection = mysqli_connect($host, $username, $password, $dbname);
		require_once(BASE_PATH.'/library/DEEC/Query.php');
		$this->query = new DEEC_Query();
	}

	public function addEmailmessage($data) {
		$columns = implode(", ", array_keys($data));
		$escaped_values = array_map(array($this->connection, 'real_escape_string'), array_values($data));

		$values = implode("', '", $escaped_values);
		$query = "INSERT INTO `emailmessage`($columns) VALUES ('$values')";

		//echo $query;
		if(mysqli_query($this->connection, $query)) {
			return mysqli_insert_id($this->connection);
		} else{
			return false;
		}
	}

	public function updateEmailmessage($id, $data) {
		$id = (int)$id;
		$allowed = ['response', 'providermessageid', 'deliverystatus', 'deliverydate', 'deliveryresponse'];
		$values = [];

		foreach($allowed as $field) {
			if(!array_key_exists($field, $data)) continue;

			if($data[$field] === null) {
				$values[] = '`'.$field.'` = NULL';
			} else {
				$values[] = '`'.$field.'` = "'.$this->connection->real_escape_string((string)$data[$field]).'"';
			}
		}

		if(!$values) return false;

		$query = 'UPDATE emailmessage SET '.implode(', ', $values).' WHERE id = '.$id;

		return mysqli_query($this->connection, $query) ? true : false;
	}

	public function getEmailmessage($id) {
		$where = 'id = '.$id.' AND deleted = 0';
		$query = '
				SELECT
					* FROM emailmessage
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

	public function getEmailmessages($contactid, $parentid, $module, $controller, $clientid) {
		$where = 'parentid = "'.$parentid.'" AND module = "'.$module.'" AND controller = "'.$controller.'"';
		if($where) {
			$where .= ' AND clientid = '.$clientid;
			$where .= ' AND deleted = 0';
		} else {
			$where = 'clientid = '.$clientid;
			$where .= ' AND deleted = 0';
		}
		$query = '
				SELECT
					* FROM emailmessage
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

	public function getCampaignStatus($campaignid, $clientid) {
		$campaignid = (int)$campaignid;
		$clientid = (int)$clientid;

		$query = '
			SELECT
				SUM(response = "sent") AS sent,
				SUM(response = "pending") AS pending,
				SUM(response != "sent" AND response != "pending") AS failed
			FROM emailmessage
			WHERE parentid = '.$campaignid.'
				AND module = "campaigns"
				AND controller = "campaign"
				AND clientid = '.$clientid.'
				AND deleted = 0
		';

		$result = mysqli_query($this->connection, $query);

		if (!$result) {
			return [
				'sent' => 0,
				'pending' => 0,
				'failed' => 0,
				'total' => 0,
			];
		}

		$status = mysqli_fetch_assoc($result);
		$sent = (int)$status['sent'];
		$pending = (int)$status['pending'];
		$failed = (int)$status['failed'];

		return [
			'sent' => $sent,
			'pending' => $pending,
			'failed' => $failed,
			'total' => $sent + $pending + $failed,
		];
	}
}
