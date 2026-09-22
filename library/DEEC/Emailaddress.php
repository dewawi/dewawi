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
		$contactsubcat,
		$campaignid,
		$categories,
		$limit = 1
	) {
		$clientid = (int)$clientid;
		$campaignid = (int)$campaignid;
		$contactsubcat = (bool)$contactsubcat;
		$limit = max(1, (int)$limit);

		if ((string)$contactcatid === '0') {
			$contactcatid = 'all';
		} else {
			$contactcatid = (int)$contactcatid;
		}

		$where = '';

		$where = $this->query->getQueryCategory(
			$where,
			$contactcatid,
			$categories,
			'c',
			$contactsubcat
		);

		if ($where) {
			$where .= ' AND ';
		}

		$where .= 'c.clientid = '.$clientid;
		$where .= ' AND c.deleted = 0';

		$query = '
			SELECT
				r.emailid,
				r.password,
				r.email,
				r.contactid,
				r.contactpersonid,
				r.salutation,
				r.name1,
				r.name2,
				r.department
			FROM (
				SELECT
					e.id AS emailid,
					e.password,
					LOWER(TRIM(e.email)) AS email,
					c.id AS contactid,
					NULL AS contactpersonid,
					NULL AS salutation,
					NULL AS name1,
					NULL AS name2,
					NULL AS department
				FROM contact AS c
				INNER JOIN email AS e
					ON e.parentid = c.id
					AND e.clientid = c.clientid
					AND e.module = "contacts"
					AND e.controller = "contact"
					AND e.deleted = 0
				WHERE '.$where.'
					AND e.email IS NOT NULL
					AND TRIM(e.email) != ""

				UNION ALL

				SELECT
					e.id AS emailid,
					e.password,
					LOWER(TRIM(e.email)) AS email,
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
					AND e.clientid = cp.clientid
					AND e.module = "contacts"
					AND e.controller = "contactperson"
					AND e.deleted = 0
				WHERE '.$where.'
					AND e.email IS NOT NULL
					AND TRIM(e.email) != ""
			) AS r
			LEFT JOIN (
				SELECT LOWER(TRIM(email)) AS email
				FROM email
				WHERE clientid = '.$clientid.'
					AND deleted = 0
					AND suppressed = 1
					AND email IS NOT NULL
					AND TRIM(email) != ""
				GROUP BY LOWER(TRIM(email))
			) AS s
				ON s.email = r.email
			LEFT JOIN (
				SELECT
					LOWER(TRIM(recipient)) AS email,
					MAX(response = "sent") AS sent,
					MAX(response = "pending" AND messagesent >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)) AS pending,
					SUM(response != "sent" AND response != "pending") AS failed
				FROM emailmessage
				WHERE parentid = '.$campaignid.'
					AND module = "campaigns"
					AND controller = "campaign"
					AND clientid = '.$clientid.'
					AND deleted = 0
				GROUP BY LOWER(TRIM(recipient))
			) AS h
				ON h.email = r.email
			WHERE s.email IS NULL
				AND COALESCE(h.sent, 0) = 0
				AND COALESCE(h.pending, 0) = 0
				AND COALESCE(h.failed, 0) < 3
		';

		$recipients = [];
		$seen = [];
		$offset = 0;
		$chunkSize = max(10, $limit);

		do {
			$result = mysqli_query($this->connection, $query.' ORDER BY contactid, contactpersonid, email LIMIT '.$chunkSize.' OFFSET '.$offset);

			if (!$result || mysqli_num_rows($result) === 0) {
				break;
			}

			$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
			$rowCount = count($rows);

			foreach($rows as $recipient) {
				$email = strtolower(trim($recipient['email']));

				if(isset($seen[$email])) {
					continue;
				}

				$seen[$email] = true;
				$recipient['email'] = $email;
				$recipients[] = $recipient;

				if(count($recipients) >= $limit) {
					break 2;
				}
			}

			$offset += $rowCount;
		} while($rowCount === $chunkSize);

		return $recipients;
	}

	public function getCampaignRecipientStatus(
		$clientid,
		$contactcatid,
		$contactsubcat,
		$campaignid,
		$categories
	) {
		$clientid = (int)$clientid;
		$campaignid = (int)$campaignid;
		$contactsubcat = (bool)$contactsubcat;

		if ((string)$contactcatid === '0') {
			$contactcatid = 'all';
		} else {
			$contactcatid = (int)$contactcatid;
		}

		$where = $this->query->getQueryCategory(
			'',
			$contactcatid,
			$categories,
			'c',
			$contactsubcat
		);

		if ($where) {
			$where .= ' AND ';
		}

		$where .= 'c.clientid = '.$clientid;
		$where .= ' AND c.deleted = 0';

		$query = '
			SELECT
				SUM(CASE
					WHEN r.suppressed = 0
						AND COALESCE(h.sent, 0) = 0
						AND COALESCE(h.pending, 0) = 0
						AND COALESCE(h.failed, 0) < 3
					THEN 1 ELSE 0
				END) AS open,
				SUM(CASE
					WHEN r.suppressed = 0
						AND COALESCE(h.sent, 0) = 0
						AND COALESCE(h.pending, 0) > 0
					THEN 1 ELSE 0
				END) AS pending,
				SUM(CASE
					WHEN COALESCE(h.sent, 0) > 0
						AND COALESCE(h.delivered, 0) = 0
						AND COALESCE(h.bounce, 0) = 0
						AND COALESCE(h.complaint, 0) = 0
					THEN 1 ELSE 0
				END) AS sent,
				SUM(CASE
					WHEN COALESCE(h.delivered, 0) > 0
						AND COALESCE(h.bounce, 0) = 0
						AND COALESCE(h.complaint, 0) = 0
					THEN 1 ELSE 0
				END) AS delivered,
				SUM(CASE
					WHEN COALESCE(h.bounce, 0) > 0
						AND COALESCE(h.complaint, 0) = 0
					THEN 1 ELSE 0
				END) AS bounce,
				SUM(CASE
					WHEN COALESCE(h.complaint, 0) > 0
					THEN 1 ELSE 0
				END) AS complaint,
				SUM(CASE
					WHEN COALESCE(h.sent, 0) = 0
						AND COALESCE(h.pending, 0) = 0
						AND COALESCE(h.failed, 0) >= 3
					THEN 1 ELSE 0
				END) AS failed,
				COUNT(*) AS total
			FROM (
				SELECT email, MAX(suppressed) AS suppressed
				FROM (
					SELECT
						LOWER(TRIM(e.email)) AS email,
						EXISTS (
							SELECT 1
							FROM email AS suppressedemail
							WHERE suppressedemail.clientid = e.clientid
								AND suppressedemail.deleted = 0
								AND suppressedemail.suppressed = 1
								AND LOWER(TRIM(suppressedemail.email)) = LOWER(TRIM(e.email))
						) AS suppressed
					FROM contact AS c
					INNER JOIN email AS e
						ON e.parentid = c.id
						AND e.module = "contacts"
						AND e.controller = "contact"
						AND e.clientid = c.clientid
						AND e.deleted = 0
					WHERE '.$where.'
						AND e.email IS NOT NULL
						AND TRIM(e.email) != ""

					UNION ALL

					SELECT
						LOWER(TRIM(e.email)) AS email,
						EXISTS (
							SELECT 1
							FROM email AS suppressedemail
							WHERE suppressedemail.clientid = e.clientid
								AND suppressedemail.deleted = 0
								AND suppressedemail.suppressed = 1
								AND LOWER(TRIM(suppressedemail.email)) = LOWER(TRIM(e.email))
						) AS suppressed
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
						AND TRIM(e.email) != ""
				) AS addresses
				GROUP BY email
			) AS r
			LEFT JOIN (
				SELECT
					LOWER(TRIM(recipient)) AS email,
					COUNT(*) AS attempts,
					MAX(response = "sent") AS sent,
					MAX(response = "pending" AND messagesent >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)) AS pending,
					SUM(
						response IS NOT NULL
						AND response != ""
						AND response != "sent"
						AND response != "pending"
					) AS failed,
					MAX(deliverystatus = "delivered") AS delivered,
					MAX(deliverystatus = "bounce") AS bounce,
					MAX(deliverystatus = "complaint") AS complaint
				FROM emailmessage
				WHERE parentid = '.$campaignid.'
					AND module = "campaigns"
					AND controller = "campaign"
					AND clientid = '.$clientid.'
					AND deleted = 0
				GROUP BY LOWER(TRIM(recipient))
			) AS h ON h.email = r.email
			WHERE r.suppressed = 0 OR COALESCE(h.attempts, 0) > 0
		';

		$result = mysqli_query($this->connection, $query);

		if (!$result) {
			return [
				'open' => 0,
				'pending' => 0,
				'sent' => 0,
				'delivered' => 0,
				'bounce' => 0,
				'complaint' => 0,
				'failed' => 0,
				'total' => 0,
			];
		}

		$status = mysqli_fetch_assoc($result);

		return [
			'open' => (int)$status['open'],
			'pending' => (int)$status['pending'],
			'sent' => (int)$status['sent'],
			'delivered' => (int)$status['delivered'],
			'bounce' => (int)$status['bounce'],
			'complaint' => (int)$status['complaint'],
			'failed' => (int)$status['failed'],
			'total' => (int)$status['total'],
		];
	}
}
