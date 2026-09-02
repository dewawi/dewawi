<?php

class DEEC_Emailaddress {

	protected $basePath;

	protected $connection;

	protected $query;

	public function __construct($basePath, $host, $username, $password, $dbname) {
		$this->basePath = $basePath;
		$this->connection = mysqli_connect($host, $username, $password, $dbname);
		require_once(BASE_PATH.'/library/DEEC/Query.php');
		$this->query = new DEEC_Query();
	}

	public function getEmailaddress($id) {
		$where = 'id = '.$id.' AND deleted = 0';
		$query = '
				SELECT
					* FROM email
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

	public function getEmailaddresses($parentid, $clientid) {
		$where = 'parentid = '.$parentid;
		if($where) {
			$where .= ' AND clientid = '.$clientid;
			$where .= ' AND deleted = 0';
		} else {
			$where = 'clientid = '.$clientid;
			$where .= ' AND deleted = 0';
		}
		$query = '
				SELECT
					* FROM email
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

	public function getCampaignRecipients(
		$clientid,
		$contactcatid,
		$campaignid,
		$categories,
		$limit = 1
	) {
		$clientid = (int)$clientid;
		$contactcatid = (int)$contactcatid;
		$campaignid = (int)$campaignid;
		$limit = max(1, (int)$limit);

		$where = '';

		$where = $this->query->getQueryCategory(
			$where,
			$contactcatid,
			$categories,
			'c'
		);

		if ($where) {
			$where .= ' AND ';
		}

		$where .= 'c.clientid = '.$clientid;
		$where .= ' AND c.deleted = 0';

		$query = '
			SELECT
				e.email,
				c.id AS contactid,
				NULL AS contactpersonid,
				NULL AS salutation,
				NULL AS name1,
				NULL AS name2,
				NULL AS department
			FROM contact AS c
			INNER JOIN email AS e
				ON e.parentid = c.id
				AND e.module = "contacts"
				AND e.controller = "contact"
				AND e.clientid = c.clientid
				AND e.deleted = 0
			WHERE '.$where.'
				AND e.email IS NOT NULL
				AND e.email != ""
				AND NOT EXISTS (
					SELECT 1
					FROM emailmessage AS em
					WHERE em.parentid = '.$campaignid.'
						AND em.module = "campaigns"
						AND em.controller = "campaign"
						AND em.clientid = '.$clientid.'
						AND em.deleted = 0
						AND em.recipient = e.email
						AND (
							em.response IS NULL
							OR em.response = ""
						)
				)

			UNION ALL

			SELECT
				e.email,
				c.id AS contactid,
				cp.id AS contactpersonid,
				cp.salutation,
				cp.name1,
				cp.name2,
				cp.department
			FROM contact AS c
			INNER JOIN contactperson AS cp
				ON cp.parentid = c.id
				AND cp.clientid = c.clientid
				AND cp.deleted = 0
			INNER JOIN email AS e
				ON e.parentid = cp.id
				AND e.module = "contacts"
				AND e.controller = "contactperson"
				AND e.clientid = cp.clientid
				AND e.deleted = 0
			WHERE '.$where.'
				AND e.email IS NOT NULL
				AND e.email != ""
				AND NOT EXISTS (
					SELECT 1
					FROM emailmessage AS em
					WHERE em.parentid = '.$campaignid.'
						AND em.module = "campaigns"
						AND em.controller = "campaign"
						AND em.clientid = '.$clientid.'
						AND em.deleted = 0
						AND em.recipient = e.email
						AND (
							em.response IS NULL
							OR em.response = ""
						)
				)

			LIMIT '.$limit.'
		';

		$result = mysqli_query($this->connection, $query);

		if (!$result || mysqli_num_rows($result) === 0) {
			return [];
		}

		return mysqli_fetch_all($result, MYSQLI_ASSOC);
	}
}
