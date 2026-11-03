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
		require_once(BASE_PATH.'/library/DEEC/ContactpersonFormatter.php');
	}

	private function getSmtpConfig($clientid) {
		$clientid = (int)$clientid;

		$query = '
			SELECT smtphost, smtpport, smtpauth, smtpsecure, smtpuser, smtppass, unsubscribeurl
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

		return mysqli_fetch_assoc($result);
	}

	public static function buildSmtpConfig(array $config): ?array
	{
		$host = trim((string)($config['smtphost'] ?? ''));
		$port = (int)($config['smtpport'] ?? 0);
		$auth = (bool)($config['smtpauth'] ?? false);
		$secure = trim((string)($config['smtpsecure'] ?? ''));
		$username = trim((string)($config['smtpuser'] ?? ''));
		$password = (string)($config['smtppass'] ?? '');

		if($host === '' && $port === 0 && $secure === '' && $username === '' && $password === '') return null;
		if($host === '' || $port <= 0) throw new InvalidArgumentException('SMTP configuration is incomplete');
		if($auth && ($username === '' || $password === '')) throw new InvalidArgumentException('SMTP authentication configuration is incomplete');

		return [
			'host' => $host,
			'auth' => $auth,
			'username' => $username,
			'password' => $password,
			'secure' => $secure,
			'port' => $port,
		];
	}

	public static function prepareMessageData(array $message): array
	{
		$replyTo = trim((string)($message['replyto'] ?? ''));
		$attachments = array_values(array_filter(array_map('trim', (array)($message['attachments'] ?? []))));

		return [
			'contactid' => (int)($message['contactid'] ?? 0),
			'documentid' => (int)($message['documentid'] ?? 0),
			'campaignid' => !empty($message['campaignid']) ? (int)$message['campaignid'] : null,
			'feedbackid' => !empty($message['feedbackid']) ? (int)$message['feedbackid'] : null,
			'module' => (string)($message['module'] ?? ''),
			'controller' => (string)($message['controller'] ?? ''),
			'sender' => trim((string)($message['sender'] ?? '')),
			'recipient' => trim((string)($message['recipient'] ?? '')),
			'cc' => self::normalizeAddressString($message['cc'] ?? ''),
			'bcc' => self::normalizeAddressString($message['bcc'] ?? ''),
			'replyto' => $replyTo !== '' ? $replyTo : null,
			'subject' => (string)($message['subject'] ?? ''),
			'body' => (string)($message['body'] ?? ''),
			'attachment' => implode(',', $attachments),
			'response' => 'pending',
		];
	}

	public static function sendMessage(array $smtp, array $message): array
	{
		require_once(BASE_PATH.'/library/PHPMailer/Exception.php');
		require_once(BASE_PATH.'/library/PHPMailer/PHPMailer.php');
		require_once(BASE_PATH.'/library/PHPMailer/SMTP.php');

		$mail = new PHPMailer\PHPMailer\PHPMailer();
		$mail->SMTPDebug = (int)($smtp['debug'] ?? 0);
		$mail->isSMTP();
		$mail->Host = (string)($smtp['host'] ?? '');
		$mail->SMTPAuth = array_key_exists('auth', $smtp) ? (bool)$smtp['auth'] : true;
		$mail->Username = (string)($smtp['username'] ?? '');
		$mail->Password = (string)($smtp['password'] ?? '');
		$mail->SMTPSecure = (string)($smtp['secure'] ?? '');
		$mail->Port = (int)($smtp['port'] ?? 465);

		$mail->setFrom((string)($message['fromEmail'] ?? ''), (string)($message['fromName'] ?? ''));

		foreach(self::normalizeAddresses($message['to'] ?? []) as $address) $mail->addAddress($address);
		foreach(self::normalizeAddresses($message['cc'] ?? []) as $address) $mail->addCC($address);
		foreach(self::normalizeAddresses($message['bcc'] ?? []) as $address) $mail->addBCC($address);

		$replyTo = trim((string)($message['replyTo'] ?? ''));
		if($replyTo !== '') $mail->addReplyTo($replyTo);

		foreach((array)($message['attachments'] ?? []) as $attachment) {
			$path = is_array($attachment) ? (string)($attachment['path'] ?? '') : (string)$attachment;
			$name = is_array($attachment) ? (string)($attachment['name'] ?? '') : '';
			if($path === '' || !is_file($path) || !is_readable($path) || is_link($path)) throw new RuntimeException('Selected email attachment is unavailable');
			if($name !== '' && !DEEC_Emailattachment::validFilename($name)) throw new RuntimeException('Selected email attachment filename is invalid');
			$mail->addAttachment($path, $name);
		}

		foreach((array)($message['embeddedImages'] ?? []) as $image) {
			if(!empty($image['path']) && !empty($image['cid'])) $mail->addEmbeddedImage($image['path'], $image['cid']);
		}

		$emailmessageId = (int)($message['emailmessageId'] ?? 0);
		if($emailmessageId > 0) $mail->addCustomHeader('X-DEWAWI-Emailmessage-ID', (string)$emailmessageId);

		foreach((array)($message['headers'] ?? []) as $name => $value) {
			if($name !== '' && $value !== '') $mail->addCustomHeader($name, $value);
		}

		$mail->isHTML(true);
		$mail->Subject = (string)($message['subject'] ?? '');
		$mail->Body = (string)($message['body'] ?? '');

		if(array_key_exists('altBody', $message)) $mail->AltBody = (string)$message['altBody'];

		$mail->CharSet = (string)($message['charset'] ?? 'UTF-8');
		$mail->Encoding = (string)($message['encoding'] ?? 'base64');

		if(array_key_exists('xMailer', $message)) $mail->XMailer = (string)$message['xMailer'];

		if(!$mail->send()) {
			return [
				'sent' => false,
				'error' => $mail->ErrorInfo,
			];
		}

		return [
			'sent' => true,
			'error' => '',
		];
	}

	private static function normalizeAddresses($addresses): array
	{
		if(!is_array($addresses)) $addresses = explode(',', (string)$addresses);

		$result = [];

		foreach($addresses as $address) {
			$address = trim((string)$address);
			if($address !== '') $result[] = $address;
		}

		return array_values(array_unique($result));
	}

	private static function normalizeAddressString($addresses): ?string
	{
		$addresses = self::normalizeAddresses($addresses);
		return $addresses ? implode(',', $addresses) : null;
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
		if(true) {
			if($campaign) {
				$smtp = $this->getSmtpConfig($campaign['clientid']);

				$unsubscribeBaseUrl = trim((string)($campaign['unsubscribeurl'] ?? ''));

				if($unsubscribeBaseUrl === '' && $smtp) {
					$unsubscribeBaseUrl = trim((string)($smtp['unsubscribeurl'] ?? ''));
				}

				if($unsubscribeBaseUrl === '') {
					throw new Exception('Campaign unsubscribe URL is missing');
				}

				if(!filter_var($unsubscribeBaseUrl, FILTER_VALIDATE_URL)) {
					throw new Exception('Campaign unsubscribe URL is invalid');
				}

				$smtpConfig = $smtp ? self::buildSmtpConfig($smtp) : null;

				if($smtpConfig) {
					if(empty($user['email'])) throw new Exception('Campaign sender email is missing');
					$fromEmail = $user['email'];
				} else {
					$smtpConfig = [
						'host' => $user['smtphost'],
						'auth' => true,
						'username' => $user['smtpuser'],
						'password' => $user['smtppass'],
						'secure' => 'ssl',
						'port' => 465,
					];
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
				$smtpConfig = [
					'host' => $user['smtphost'],
					'auth' => true,
					'username' => $user['smtpuser'],
					'password' => $user['smtppass'],
					'secure' => 'ssl',
					'port' => 465,
				];
			}
//print_r($recipients);

			//Add email signature
			$data['body'] = str_replace('[SIGNATURE]', $user['emailsignature'], $data['body']);
			/*if($campaignid) {
				$data['body'] = str_replace('[BODY]', $data['body'], $template);
			}*/

			$attachmentsSent = array();
			$attachmentSources = array();
			$emailattachmentArray = $this->emailattachment->getEmailattachments($campaign['id'], 'campaigns', 'campaign', $campaign['clientid']);

			if($emailattachmentArray && count($emailattachmentArray)) {
				foreach($emailattachmentArray as $file) {
					$attachmentSources[] = ['path' => DEEC_Emailattachment::getPath($file), 'filename' => $file['filename'], 'emailattachmentid' => (int)$file['id']];
					$attachmentsSent[] = $file['filename'];
				}
			}

			$result = [
				'attempted' => count($recipients),
				'sent' => 0,
			];

			foreach($recipients as $recipient) {
				$data['cc'] = $campaign['emailcc'];
				$data['bcc'] = $campaign['emailbcc'];
				$data['replyto'] = trim((string)($campaign['emailreplyto'] ?? ''));
				$data['subject'] = $campaign['emailsubject'];

				if(empty($recipient['emailid']) || empty($recipient['password'])) {
					throw new Exception('Campaign recipient unsubscribe data is missing');
				}

				$unsubscribeToken = hash(
					'sha256',
					(int)$recipient['emailid'].'|'.(int)$campaign['clientid'].'|'.$recipient['password']
				);

				$separator = strpos($unsubscribeBaseUrl, '?') === false ? '?' : '&';

				$unsubscribeUrl = $unsubscribeBaseUrl
					.$separator
					.'id='.(int)$recipient['emailid']
					.'&token='.urlencode($unsubscribeToken);

				// personalize for this recipient
				$body = $this->personalizeBody($data['body'], $recipient);

				$unsubscribeLink = '<a href="'
					.htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8')
					.'">Ich möchte keine weiteren E-Mails erhalten</a>';

				if(strpos($body, '[UNSUBSCRIBE]') !== false) {
					$body = str_replace('[UNSUBSCRIBE]', $unsubscribeLink, $body);
				} else {
					$body .= '<p style="font-size:12px;margin-top:24px;">'.$unsubscribeLink.'</p>';
				}

				//Save email message to the db
				$emailmessage = self::prepareMessageData([
					'contactid' => $recipient['contactid'],
					'documentid' => $documentid,
					'campaignid' => $campaign['id'],
					'module' => $data['module'],
					'controller' => $data['controller'],
					'sender' => $fromEmail,
					'recipient' => $recipient['email'],
					'cc' => $data['cc'],
					'bcc' => $data['bcc'],
					'replyto' => $data['replyto'],
					'subject' => $data['subject'],
					'body' => $body,
					'attachments' => $attachmentsSent,
				]);

				$emailmessage['clientid'] = $campaign['clientid'];
				$emailmessage['deliverystatus'] = 'pending';

				$messageid = $this->emailmessage->addEmailmessage($emailmessage);

				try {
					$attachmentPaths = $this->storeMessageAttachments((int)$messageid, (int)$campaign['clientid'], (int)$user['id'], $attachmentSources);
					$sendResult = self::sendMessage($smtpConfig, [
						'fromEmail' => $emailmessage['sender'], 'fromName' => $fromName, 'to' => $emailmessage['recipient'],
						'cc' => $emailmessage['cc'], 'bcc' => $emailmessage['bcc'], 'replyTo' => $emailmessage['replyto'],
						'subject' => $emailmessage['subject'], 'body' => $emailmessage['body'], 'attachments' => $attachmentPaths,
						'emailmessageId' => $messageid, 'headers' => ['List-Unsubscribe' => '<'.$unsubscribeUrl.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'],
					]);
				} catch (Throwable $exception) {
					$sendResult = ['sent' => false, 'error' => $exception->getMessage()];
				}

				if(!$sendResult['sent']) {
					$this->emailmessage->updateEmailmessage($messageid, [
						'response' => $sendResult['error'],
						'deliverystatus' => 'failed',
					]);

					continue;
				}

				$this->emailmessage->updateEmailmessage($messageid, [
					'response' => 'sent',
					'deliverystatus' => 'accepted',
					'messagesent' => date('Y-m-d H:i:s'),
					'messagesentby' => $user['id'],
				]);

				$result['sent']++;
			}
		}

		return $result;
	}

	private function storeMessageAttachments(int $messageId, int $clientId, int $userId, array $sources): array
	{
		$result = [];
		$client = (string)$clientId;
		$clientShard = $client[0] . '/' . ($client[1] ?? '0') . '/' . $client;
		foreach ($sources as $index => $source) {
			$ordering = $index + 1;
			$filename = (string)$source['filename'];
			$sourcePath = (string)$source['path'];
			if (!DEEC_Emailattachment::validFilename($filename) || !is_file($sourcePath) || !is_readable($sourcePath) || is_link($sourcePath)) throw new RuntimeException('Selected email attachment is unavailable');
			$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
			if (!preg_match('/^[a-z0-9]{1,16}$/D', $extension)) $extension = 'bin';
			$relative = 'files/attachments/emailmessage/' . $clientShard . '/' . intdiv($messageId, 1000) . '/' . $messageId . '/' . $ordering . '.' . $extension;
			$target = BASE_PATH . '/' . $relative;
			DEEC_Emailattachment::checkPath($target);
			$this->ensureMessageAttachmentDirectory(dirname($target));
			$output = @fopen($target, 'x+b');
			if (!$output) throw new RuntimeException('Email attachment copy target is occupied');
			$input = @fopen($sourcePath, 'rb');
			try {
				$size = filesize($sourcePath);
				if (!$input || stream_copy_to_stream($input, $output) !== $size || !fflush($output) || (function_exists('fsync') && !fsync($output))) throw new RuntimeException('Email attachment copy failed');
			} catch (Throwable $exception) {
				fclose($output); if ($input) fclose($input); unlink($target); throw $exception;
			}
			fclose($output); if ($input) fclose($input);
			$sha = hash_file('sha256', $sourcePath);
			if (filesize($target) !== $size || !hash_equals($sha, hash_file('sha256', $target))) { unlink($target); throw new RuntimeException('Email attachment copy verification failed'); }
			$type = (string)((new finfo(FILEINFO_MIME_TYPE))->file($target) ?: 'application/octet-stream');
			$sourceId = (int)$source['emailattachmentid'];
			$created = date('Y-m-d H:i:s');
			$insert = $this->connection->prepare('INSERT INTO emailmessageattachment (emailmessageid, documentarchiveid, emailattachmentid, filename, filetype, filesize, sha256, path, ordering, clientid, created, createdby) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
			if (!$insert) { unlink($target); throw new RuntimeException('Email attachment copy metadata could not be prepared'); }
			$insert->bind_param('iississiisi', $messageId, $sourceId, $filename, $type, $size, $sha, $relative, $ordering, $clientId, $created, $userId);
			try { if (!$insert->execute()) throw new RuntimeException('Email attachment copy metadata could not be stored'); }
			catch (Throwable $exception) { $insert->close(); unlink($target); throw $exception; }
			$insert->close();
			$result[] = ['path' => $target, 'name' => $filename];
		}
		return $result;
	}

	private function ensureMessageAttachmentDirectory(string $directory): void
	{
		$base = realpath(BASE_PATH);
		if (!$base || !str_starts_with($directory, $base . '/')) throw new RuntimeException('Invalid email attachment directory');
		$current = $base;
		foreach (explode('/', substr($directory, strlen($base) + 1)) as $part) {
			$current .= '/' . $part;
			if (is_link($current)) throw new RuntimeException('Email attachment directory contains a symlink');
			if (file_exists($current)) { if (!is_dir($current)) throw new RuntimeException('Email attachment directory collision'); continue; }
			if (!mkdir($current, 0775)) throw new RuntimeException('Email attachment directory is unavailable');
		}
	}

	private function personalizeBody(string $body, array $recipient): string {
		if (strpos($body, '[SALUTATION]') !== false) {
			$salutation = DEEC_ContactpersonFormatter::formalSalutation($recipient);
			$body = str_replace('[SALUTATION]', $salutation, $body);
		}

		return $body;
	}
}
