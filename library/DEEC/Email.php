<?php

class DEEC_Email {

	protected $basePath;

	protected $connection;

	protected $contact;

	protected $category;

	protected $emailmessage;

	protected $emailaddress;

	public function __construct($basePath, $host, $username, $password, $dbname) {
		$this->basePath = $basePath;
		$this->connection = mysqli_connect($host, $username, $password, $dbname);
		require_once(BASE_PATH.'/library/DEEC/Directory.php');
		$this->directory = new DEEC_Directory();
		require_once(BASE_PATH.'/library/DEEC/Contact.php');
		$this->contact = new DEEC_Contact($basePath, $host, $username, $password, $dbname);
		require_once(BASE_PATH.'/library/DEEC/Contactperson.php');
		$this->contactperson = new DEEC_Contactperson($basePath, $host, $username, $password, $dbname);
		require_once(BASE_PATH.'/library/DEEC/Category.php');
		$this->category = new DEEC_Category($basePath, $host, $username, $password, $dbname);
		require_once(BASE_PATH.'/library/DEEC/Emailmessage.php');
		$this->emailmessage = new DEEC_Emailmessage($basePath, $host, $username, $password, $dbname);
		require_once(BASE_PATH.'/library/DEEC/Emailaddress.php');
		$this->emailaddress = new DEEC_Emailaddress($basePath, $host, $username, $password, $dbname);
		require_once(BASE_PATH.'/library/DEEC/Emailattachment.php');
		$this->emailattachment = new DEEC_Emailattachment($basePath, $host, $username, $password, $dbname);
	}

	private function getSmtpConfig($clientid) {
		$clientid = (int)$clientid;

		$query = '
			SELECT smtphost, smtpport, smtpauth, smtpsecure, smtpuser, smtppass
			FROM config
			WHERE clientid = '.$clientid.'
			LIMIT 1
		';

		$result = mysqli_query($this->connection, $query);

		if(!$result) {
			throw new Exception('SMTP configuration could not be loaded: '.mysqli_error($this->connection));
		}

		if(mysqli_num_rows($result) === 0) {
			return null;
		}

		$config = mysqli_fetch_assoc($result);
		$configured = !empty($config['smtphost']) || !empty($config['smtpport']) || !empty($config['smtpsecure']) || !empty($config['smtpuser']) || !empty($config['smtppass']);

		if(!$configured) {
			return null;
		}

		if(empty($config['smtphost']) || empty($config['smtpport']) || empty($config['smtpsecure']) || empty($config['smtpuser']) || empty($config['smtppass'])) {
			throw new Exception('SMTP configuration is incomplete');
		}

		return $config;
	}

	public function getCampaignRecipientStatus($campaign) {
		$categories = $this->category->getCategories('contact', $campaign['clientid']);

		return $this->emailaddress->getCampaignRecipientStatus(
			$campaign['clientid'],
			$campaign['contactcatid'],
			$campaign['contactsubcat'],
			$campaign['id'],
			$categories
		);
	}

	public function send($user, $contactid, $documentid, $campaign = null) {

		//PHPMailer
		require_once(BASE_PATH.'/library/PHPMailer/Exception.php');
		require_once(BASE_PATH.'/library/PHPMailer/PHPMailer.php');
		require_once(BASE_PATH.'/library/PHPMailer/SMTP.php');

		if(true) {
			$mail = new PHPMailer\PHPMailer\PHPMailer();

			$mail->SMTPDebug = 0;
			$mail->isSMTP();
			$mail->SMTPAuth = true;

			if($campaign) {
				$smtp = $this->getSmtpConfig($campaign['clientid']);

				if($smtp) {
					$mail->Host = $smtp['smtphost'];
					$mail->SMTPAuth = (bool)$smtp['smtpauth'];
					$mail->Username = $smtp['smtpuser'];
					$mail->Password = $smtp['smtppass'];
					$mail->SMTPSecure = $smtp['smtpsecure'];
					$mail->Port = (int)$smtp['smtpport'];

					if(empty($user['email'])) {
						throw new Exception('Campaign sender email is missing');
					}

					$fromEmail = $user['email'];
				} else {
					$mail->Host = $user['smtphost'];
					$mail->SMTPAuth = true;
					$mail->Username = $user['smtpuser'];
					$mail->Password = $user['smtppass'];
					$mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
					$mail->Port = 465;
					$fromEmail = $user['smtpuser'];
				}

				$fromName = $user['emailsender'];

				$categories = $this->category->getCategories(
					'contact',
					$campaign['clientid']
				);

				$batchSize = max(1, (int)($campaign['batchsize'] ?? 1));

				$recipients = $this->emailaddress->getCampaignRecipients(
					$campaign['clientid'],
					$campaign['contactcatid'],
					$campaign['contactsubcat'],
					$campaign['id'],
					$categories,
					$batchSize
				);

				$data = array();
				$data['cc'] = $campaign['emailcc'];
				$data['bcc'] = $campaign['emailbcc'];
				$data['subject'] = $campaign['emailsubject'];
				$data['body'] = $campaign['emailbody'];
				$data['module'] = 'campaigns';
				$data['controller'] = 'campaign';
			} else {
				$mail->Host = $user['smtphost'];
				$mail->Username = $user['smtpuser'];
				$mail->Password = $user['smtppass'];
				$mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
				$mail->Port = 465;
			}
//print_r($recipients);

			//Add email signature
			$data['body'] = str_replace('[SIGNATURE]', $user['emailsignature'], $data['body']);
			/*if($campaignid) {
				$data['body'] = str_replace('[BODY]', $data['body'], $template);
			}*/

			$attachmentsSent = array();
			$emailattachmentArray = $this->emailattachment->getEmailattachments($campaign['id'], 'campaigns', 'campaign', $campaign['clientid']);

			if($emailattachmentArray && count($emailattachmentArray)) {
				foreach($emailattachmentArray as $file) {
					$path = $file['location'].'/'.$file['filename'];

					if(file_exists($path)) {
						$attachmentsSent[] = $file['filename'];
						$mail->addAttachment($path);
					}
				}
			}

			$result = [
				'attempted' => count($recipients),
				'sent' => 0,
			];

			foreach($recipients as $recipient) {
				//Recipients
				$mail->clearAllRecipients();											// clear all
				$mail->setFrom($fromEmail, $fromName);
				$mail->addAddress($recipient['email']);									// Add a recipient
				/*$data['replyto'] = str_replace(' ', '', $data['replyto']);			// Remove spaces
				if($data['replyto']) $mail->addReplyTo($data['replyto']);				// Add reply to
				$data['cc'] = str_replace(' ', '', $data['cc']);						// Remove spaces
				if($data['cc']) {														// Add copy recipients
					if(strpos($data['cc'], ',') !== false) {
						$ccs = explode(',', $data['cc']);
						foreach($ccs as $cc) {
							$mail->addCC($cc);
						}
					} else {
						$mail->addCC($data['cc']);
					}
				}
				$data['bcc'] = str_replace(' ', '', $data['bcc']);
				if($data['bcc']) $mail->addBCC($data['bcc']);*/

				// personalize for this recipient
				$body = $this->personalizeBody($data['body'], $recipient);

				//Save email message to the db
				$emailmessage = array();
				$emailmessage['contactid'] = $recipient['contactid'];
				$emailmessage['documentid'] = $documentid;
				$emailmessage['parentid'] = $campaign['id'];
				$emailmessage['module'] = $data['module'];
				$emailmessage['controller'] = $data['controller'];
				$emailmessage['recipient'] = $recipient['email'];
				$emailmessage['cc'] = $data['cc'];
				$emailmessage['bcc'] = $data['bcc'];
				$emailmessage['subject'] = $data['subject'];
				$emailmessage['body'] = $body;
				$emailmessage['clientid'] = $campaign['clientid'];
				$emailmessage['messagesent'] = date('Y-m-d H:i:s');
				$emailmessage['messagesentby'] = $user['id'];
				$emailmessage['attachment'] = implode(',', $attachmentsSent);
				$emailmessage['response'] = 'pending';
				$messageid = $this->emailmessage->addEmailmessage($emailmessage);

				//Get portal TODO
				/*$portalDb = new Portals_Model_DbTable_Portal();
				$portal = $portalDb->getPortal($email['clientid']);
				if($portal) {
					$key = hash('sha256', $email['id'].$email['contactid'].$email['clientid'].hash('sha256', $email['password']));
					$url = $portal->url.'/portals';
					$link = $url.'/auth/login/target/download/key/'.$key;
					$html = '<a href="'.$link.'">'.$link.'</a>';
					$data['body'] = str_replace('[LINK]', $html, $data['body']);

					$hash = hash('sha256', $messageid.$contactid.$email['clientid']);
					$data['body'] .= '<img src="'.$url.'/email/view/key/'.$hash.'" border="0" width="1" height="1">';
				}*/

				//Content
				$mail->isHTML(true);									// Set email format to HTML
				$mail->Subject = $data['subject'];
				$mail->Body	= $body;
				//$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
//echo $data['body'];
				//Content
				$mail->CharSet	= 'UTF-8';
				$mail->Encoding = 'base64';

				//Send the message, check for errors
				if (!$mail->send()) {
					$this->emailmessage->updateEmailmessage($messageid, [
						'response' => $mail->ErrorInfo,
					]);

					continue;
				}

				$this->emailmessage->updateEmailmessage($messageid, [
					'response' => 'sent',
				]);

				$result['sent']++;
			}
		}

		return $result;
	}

	private function buildSalutation(array $recipient): string {
		//derive from gender + name
		$gender = trim($recipient['salutation'] ?? '');
		$name = trim($recipient['name2'] ?? '');

		if ($gender && $name) {
			return 'Guten Tag ' . $gender . ' ' . $name . ',';
		}

		// default fallback
		return 'Sehr geehrte Damen und Herren,';
	}

	private function personalizeBody(string $body, array $recipient): string {
		// only replace if placeholder is present
		if (strpos($body, '[SALUTATION]') !== false) {
			$salutation = $this->buildSalutation($recipient);
			$body = str_replace('[SALUTATION]', $salutation, $body);
		}

		return $body;
	}
}
