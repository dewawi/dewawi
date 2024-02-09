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
		require_once(BASE_PATH.'/library/DEEC/Category.php');
		$this->category = new DEEC_Category($basePath, $host, $username, $password, $dbname);
		require_once(BASE_PATH.'/library/DEEC/Emailmessage.php');
		$this->emailmessage = new DEEC_Emailmessage($basePath, $host, $username, $password, $dbname);
		require_once(BASE_PATH.'/library/DEEC/Emailaddress.php');
		$this->emailaddress = new DEEC_Emailaddress($basePath, $host, $username, $password, $dbname);
		require_once(BASE_PATH.'/library/DEEC/Emailattachment.php');
		$this->emailattachment = new DEEC_Emailattachment($basePath, $host, $username, $password, $dbname);
	}

	public function send($user, $contactid, $documentid, $campaign = null) {

		//PHPMailer
		require_once(BASE_PATH.'/library/PHPMailer/Exception.php');
		require_once(BASE_PATH.'/library/PHPMailer/PHPMailer.php');
		require_once(BASE_PATH.'/library/PHPMailer/SMTP.php');

		if(true) {
			//echo $campaign['title'];
			$mail = new PHPMailer\PHPMailer\PHPMailer();

			//Server settings
			$mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;				// Enable verbose debug output
			$mail->isSMTP();														// Send using SMTP
			$mail->Host		= $user['smtphost'];									// Set the SMTP server to send through
			$mail->SMTPAuth	= true;													// Enable SMTP authentication
			$mail->Username	= $user['smtpuser'];									// SMTP username
			$mail->Password	= $user['smtppass'];									// SMTP password
			$mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;	// Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
			$mail->Port		= 465;													// TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

			$emails = array();
			if($campaign) {
				//Get contacts
				$categories = $this->category->getCategories('contact', $campaign['clientid']);
				$contacts = $this->contact->getContacts($campaign['contactcatid'], $campaign['clientid'], $categories);

				//Get already sent emails on campaign
				$emailmessageArray = $this->emailmessage->getEmailmessages(NULL, $campaign['id'], 'campaigns', 'campaign', $campaign['clientid']);
				$emailmessages = array();
				if($emailmessageArray) {
					foreach($emailmessageArray as $emailmessage) {
						$emailmessages[$emailmessage['contactid']] = $emailmessage;
					}
				}
//echo count($emailmessages);

				$i = 0;
				$limit = 1;
				$recipients = array();
//print_r($contacts);
//echo 1;
				foreach($contacts as $contact) {
					if(($i < $limit) && !isset($emailmessages[$contact['id']])) {
						$emailaddressArray = $this->emailaddress->getEmailaddresses($contact['id'], $contact['clientid']);
						if($emailaddressArray) {
							foreach($emailaddressArray as $emailaddress) {
								if($emailaddress['email']) {
									$recipients[$i]['email'] = $emailaddress['email'];
									$recipients[$i]['contactid'] = $contact['id'];
									++$i;
								}
							}
						}

						/*if(strpos($contact->emails, ',') !== false) {
							$recipientEmails = explode(',', $contact->emails);
							foreach($recipientEmails as $recipientEmail) {
								$recipients[$i]['email'] = $recipientEmail;
								$recipients[$i]['contactid'] = $contact->id;
								++$i;
							}
						} elseif($contact->emails) {
							$recipients[$i]['email'] = $contact->emails;
							$recipients[$i]['contactid'] = $contact->id;
							++$i;
						}*/

						/*$recipients[$i]['email'] = 'remzi@bergcold.com';
						$recipients[$i]['contactid'] = $contact['id'];
						++$i;*/
					}
				}
				$data = array();
				$data['cc'] = $campaign['emailcc'];
				$data['bcc'] = $campaign['emailbcc'];
				$data['subject'] = $campaign['emailsubject'];
				$data['body'] = $campaign['emailbody'];
				$data['module'] = 'campaigns';
				$data['controller'] = 'campaign';
			} else {
				//Get email
				//$emailDb = new Contacts_Model_DbTable_Email();
				//$emailArray = $emailDb->getEmail($data['recipient']);
				//$recipients[0]['email'] = $emailArray['email'];
				//if($emailArray['controller'] == 'contact') $recipients[0]['contactid'] = $emailArray['parentid'];
			}
//print_r($recipients);
/*
$template = '
<head>
<style>
body, table{
font-size: 14px;
line-height: 1.4;
font-family: sans-serif;
}
h2{
font-size: 20px;
line-height: 1.3;
}
h3{
font-size: 17px;
line-height: 1.4;
}
img{
max-width: 100%;
height: auto;
}
</style>
</head>
<body width="100%" style="margin: 0; padding: 0;">
<div style="max-width: 600px; margin: 0; padding: 1em; min-width: 300px; background-color: #ffffff;">
[BODY]
</div>
</body>
';*/

			//Add email signature
			$data['body'] = str_replace('[SIGNATURE]', $user['emailsignature'], $data['body']);
			/*if($campaignid) {
				$data['body'] = str_replace('[BODY]', $data['body'], $template);
			}*/

			foreach($recipients as $recipient) {
				//echo $recipient['email'];
				//Recipients
				$mail->clearAllRecipients();											// clear all
				$mail->setFrom($user['smtpuser'], $user['emailsender']);
				$mail->addAddress($recipient['email']);												// Add a recipient
				/*$data['replyto'] = str_replace(' ', '', $data['replyto']);				// Remove spaces
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

				//Get email attachments
				$emailattachmentArray = $this->emailattachment->getEmailattachments($campaign['id'], 'campaigns', 'campaign', $campaign['clientid']);

				$attachmentsSent = array();
				if($emailattachmentArray && isset($emailattachmentArray) && count($emailattachmentArray)) {
					$url = $this->directory->getUrl($campaign['id'], $campaign['clientid']);
					foreach($emailattachmentArray as $file) {
						if(file_exists($file['location'].'/'.$file['filename'])) {
							array_push($attachmentsSent, $file['filename']);
							$mail->addAttachment($file['location'].'/'.$file['filename']);
						}
					}
				}

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
				$emailmessage['body'] = $data['body'];
				$emailmessage['clientid'] = $campaign['clientid'];
				$emailmessage['messagesent'] = date('Y-m-d H:i:s');
				$emailmessage['messagesentby'] = $user['id'];
				$emailmessage['attachment'] = implode(',', $attachmentsSent);
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
				$mail->Body	= $data['body'];
				//$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
//echo $data['body'];
				//Content
				$mail->CharSet	= 'UTF-8';
				$mail->Encoding = 'base64';

				//Send the message, check for errors
				if(!$mail->send()) {
					//Save errors to the db
					$this->emailmessage->updateEmailmessage($messageid, array('response' => $mail->ErrorInfo));
				}
			}
		}
	}
}
