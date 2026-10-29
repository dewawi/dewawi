<?php

class Application_Controller_Action_Helper_Email extends Zend_Controller_Action_Helper_Abstract
{
	public function sendEmail($module, $controller, $redirect = false, $items = null)
	{
		$request = $this->getRequest();
		if (!$request->isPost()) {
			return ['ok' => false, 'errors' => ['Not a POST']];
		}

		$data = $request->getPost();

		if ($module === 'shops' && ($controller === 'inquiry' || $controller === 'offer')) {
			$formDataSession = new Zend_Session_Namespace('MultiStepForm');

			if (!empty($formDataSession->formData)) {
				foreach ($formDataSession->formData as $step => $stepData) {
					if (is_array($stepData)) {
						$data = array_merge($stepData, $data); // Priorität für aktuelle POST-Daten
					}
				}
			}

			// Optional: Subject und Nachricht aus einem freien Feld ergänzen (z. B. wenn letzte Eingabeseite)
			if (!isset($data['subject'])) {
				$data['subject'] = 'Anfrageformular';
			}
			if (!isset($data['message'])) {
				$data['message'] = 'Dies ist eine automatisch generierte Anfrage.';
			}
		}
		if(true) {
			$messageid = $request->getParam('messageid', 0);
			$contactid = $request->getParam('contactid', 0);
			$documentid = $request->getParam('documentid', 0);
			$campaignid = $request->getParam('campaignid', 0);

			if($module == 'shops') {
				if($controller == 'checkout') {
					$form = new Shops_Form_Checkout();
				} else {
					$form = new Shops_Form_Contact();
				}

				$emailmessageDb = new Shops_Model_DbTable_Emailmessage();

				$recipients = array();
				$recipients[0]['email'] = $data['email'];
				$recipients[0]['contactid'] = 0;
			} else {
				$form = new Contacts_Form_Contact();

				$emailmessageDb = new Contacts_Model_DbTable_Emailmessage();

				$recipients = array();

				if($messageid) {
					$emailmessage = $emailmessageDb->getById((int)$messageid);
					if(!$emailmessage) throw new RuntimeException('Email message is not available');
					$contactid = (int)($emailmessage['contactid'] ?? 0);
					$documentid = (int)($emailmessage['documentid'] ?? 0);
					$campaignid = (int)($emailmessage['campaignid'] ?? 0);

					unset($emailmessage['id'], $emailmessage['messagesent'], $emailmessage['messagesentby'], $emailmessage['response']);

					$data = $emailmessage;
				}

				$quoteRecipient = $this->getQuoteRecipient($data, (int)$documentid, (bool)$messageid, (int)$campaignid);

				if($quoteRecipient !== null) {
					$recipients[0] = $quoteRecipient;
				} elseif($messageid) {
					$recipients[0]['email'] = $data['recipient'];
					$recipients[0]['contactid'] = $contactid;
				} else {
					$emailDb = new Contacts_Model_DbTable_Email();
					$emailArray = $emailDb->getById((int)($data['recipient'] ?? 0));
					if(!$emailArray) throw new RuntimeException('Email recipient is not available');
					$recipients[0]['email'] = $emailArray['email'];

					if($emailArray['controller'] == 'contact') {
						$recipients[0]['contactid'] = $emailArray['parentid'];
					} elseif($emailArray['controller'] == 'contactperson') {
						$contactpersonDb = new Contacts_Model_DbTable_Contactperson();
						$contactpersonDb->setClientId((int)$emailArray['clientid']);
						$contactperson = $contactpersonDb->getById($emailArray['parentid']);
						if(!$contactperson) throw new RuntimeException('Contact person is not available');

						$recipients[0]['contactid'] = (int)$contactperson['parentid'];
						foreach(['salutation', 'title', 'firstname', 'lastname', 'position', 'department'] as $field) {
							$recipients[0][$field] = (string)($contactperson[$field] ?? '');
						}
					}
				}
			}

			if($form->isValid($data) || true) {
				// Get form data
				if($controller == 'inquiry' || $controller === 'offer') {
					$formData = $data;
				} else {
					$formData = $form->getValues();
				}

				if($form->isValid($data) || true) {
					// Get SMTP settings
					list($smtpConfig, $fromEmail, $fromName) = $this->getEmailConfig($module);

					return $this->sendEmails($module, $controller, $recipients, $smtpConfig, $fromEmail, $fromName, $formData, $data, $emailmessageDb, $documentid, $campaignid, $items);
				}
			}
		} else {
			$flashMessengerHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
			$flashMessengerHelper->addMessage('MESSAGES_FORM_INVALID');
		}
	}

	private function getQuoteRecipient(array &$data, int $documentId, bool $resend, int $campaignId): ?array
	{
		$module = $data['module'] ?? 'contacts';
		$controller = $data['controller'] ?? 'contact';
		if(!is_string($module) || !is_string($controller)) throw new InvalidArgumentException('Invalid email context');
		$module = strtolower(trim($module));
		$controller = strtolower(trim($controller));
		if(!preg_match('/^[a-z][a-z0-9-]*$/D', $module) || !preg_match('/^[a-z][a-z0-9-]*$/D', $controller)) throw new InvalidArgumentException('Invalid email context');
		if($module !== 'sales' || $controller !== 'quote') return null;

		$data['module'] = $module;
		$data['controller'] = $controller;
		$user = Zend_Registry::get('User');
		if(empty($user['id']) || empty($user['clientid'])) throw new RuntimeException('Quote email client context is missing');
		$permission = new DEEC_Permission($user);
		if(!$permission->hasActionPermission('sales', 'quote', 'view') || !$permission->hasActionPermission('sales', 'quote', 'send')) throw new RuntimeException('Quote email permission denied');
		if($documentId <= 0 || $campaignId !== 0) throw new InvalidArgumentException('Invalid quote email context');

		$quoteDb = new Sales_Model_DbTable_Quote();
		$quoteDb->setClientId((int)$user['clientid']);
		$quote = $quoteDb->getById($documentId);
		if(!$quote || empty($quote['contactid'])) throw new RuntimeException('Quote is not available');

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID((int)$quote['contactid']);
		if(!$contact) throw new RuntimeException('Quote contact is not available');

		$recipient = $data['recipient'] ?? null;
		$recipientId = $resend ? null : filter_var($recipient, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if(($resend && !is_string($recipient)) || (!$resend && !$recipientId)) throw new InvalidArgumentException('Invalid quote email recipient');

		$emailDb = new Contacts_Model_DbTable_Email();
		$emailDb->setClientId((int)$contact['clientid']);
		foreach($emailDb->getContactRecipients((int)$contact['id']) as $email) {
			$matches = $resend ? strcasecmp(trim((string)$email['email']), trim($recipient)) === 0 : (int)$email['id'] === $recipientId;
			if(!$matches) continue;
			return [
				'email' => (string)$email['email'],
				'contactid' => (int)$email['contactid'],
				'salutation' => (string)($email['salutation'] ?? ''),
				'title' => (string)($email['title'] ?? ''),
				'firstname' => (string)($email['firstname'] ?? ''),
				'lastname' => (string)($email['lastname'] ?? ''),
				'position' => (string)($email['position'] ?? ''),
				'department' => (string)($email['department'] ?? ''),
			];
		}

		throw new RuntimeException('Email recipient does not belong to the quote contact');
	}

	private function sendEmails($module, $controller, $recipients, array $smtpConfig, $fromEmail, $fromName, $formData, $data, $emailmessageDb, $documentid, $campaignid, $items)
	{
		$result = ['ok' => true, 'sent' => 0, 'failed' => 0];

		foreach($recipients as $recipient) {
			$to = [$recipient['email']];
			if($module == 'shops') $to[] = $fromEmail;

			if(isset($data['replyto']) && $data['replyto']) $data['replyto'] = str_replace(' ', '', $data['replyto']);
			if(isset($data['cc']) && $data['cc']) $data['cc'] = str_replace(' ', '', $data['cc']);
			if(isset($data['bcc']) && $data['bcc']) $data['bcc'] = str_replace(' ', '', $data['bcc']);

			$attachmentsSent = array();
			$attachmentPaths = array();
			if($module == 'contacts') {
				//Get email attachments
				$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
				if($campaignid) $attachmentsObject = $emailattachmentDb->getEmailattachments($campaignid, $data['module'], $data['controller']);
				elseif($data['module'] == 'contacts') $attachmentsObject = $emailattachmentDb->getEmailattachments($recipient['contactid'], $data['module'], $data['controller']);
				else $attachmentsObject = $emailattachmentDb->getEmailattachments($documentid, $data['module'], $data['controller']);
				$attachmentsAvailable = array();
				foreach($attachmentsObject as $attachment) $attachmentsAvailable[$attachment['id']] = $attachment;

				if(isset($data['files'])) {
					if(!is_array($data['files'])) throw new InvalidArgumentException('Invalid email attachments');
					foreach($data['files'] as $file) {
						$id = filter_var($file, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
						if(!$id || !isset($attachmentsAvailable[$id])) throw new RuntimeException('Email attachment is not available');
						$attachmentPaths[] = DEEC_Emailattachment::getPath($attachmentsAvailable[$id]);
						$attachmentsSent[] = $attachmentsAvailable[$id]['filename'];
					}
				}
			}

			$feedback = $this->prepareFeedback($module, $controller, $data, (int)$documentid);

			if($feedback['service']) {
				$data['body'] = $feedback['body'];
			}

			// Get email body and subject
			list($body, $subject) = $this->getEmailBody($module, $controller, $formData, $data, $items);

			// personalize for this recipient
			$body = $this->personalizeBody($body, $recipient);

			//Save email message to the db
			$emailmessage = DEEC_Email::prepareMessageData([
				'contactid' => $recipient['contactid'],
				'documentid' => $documentid,
				'campaignid' => $campaignid,
				'feedbackid' => !empty($feedback['request']['id']) ? (int)$feedback['request']['id'] : null,
				'module' => $data['module'] ?? $module,
				'controller' => $data['controller'] ?? $controller,
				'sender' => $fromEmail,
				'recipient' => $recipient['email'],
				'cc' => $data['cc'] ?? '',
				'bcc' => $data['bcc'] ?? '',
				'replyto' => $data['replyto'] ?? '',
				'subject' => $subject ? $subject : 'Anfrageformular',
				'body' => $body,
				'attachments' => $attachmentsSent,
			]);

			$messageid = $emailmessageDb->addEmailmessage($emailmessage);

			if(!empty($formData['__attach_paths']) && is_array($formData['__attach_paths'])) {
				foreach($formData['__attach_paths'] as $path) {
					if($path && file_exists($path)) $attachmentPaths[] = $path;
				}
				unset($formData['__attach_paths']);
			}

			$embeddedImages = [];
			if($module === 'shops') {
				$shop = Zend_Registry::get('Shop');
				$clientid = $shop['clientid'];
				$dir1 = substr($clientid, 0, 1);
				$dir2 = strlen($clientid) > 1 ? substr($clientid, 1, 1) : '0';
				$embeddedImages[] = [
					'path' => BASE_PATH.'/media/'.$dir1.'/'.$dir2.'/'.$clientid.'/header/'.$shop['logo'],
					'cid' => 'logo_cid',
				];
			}

			$sendResult = DEEC_Email::sendMessage($smtpConfig, [
				'fromEmail' => $emailmessage['sender'],
				'fromName' => $fromName,
				'to' => $to,
				'cc' => $emailmessage['cc'],
				'bcc' => $emailmessage['bcc'],
				'replyTo' => $emailmessage['replyto'],
				'subject' => $emailmessage['subject'],
				'body' => $emailmessage['body'],
				'altBody' => html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], "\n", $emailmessage['body'])), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				'attachments' => $attachmentPaths,
				'embeddedImages' => $embeddedImages,
				'emailmessageId' => $messageid,
				'xMailer' => '',
			]);

			$flashMessengerHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
			$redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');

			if(!$sendResult['sent']) {
				$result['ok'] = false;
				$result['failed']++;
				$emailmessageDb->updateEmailmessage($messageid, array('response' => $sendResult['error']));

				if($feedback['service'] && $feedback['request'] && $feedback['created']) {
					$feedback['service']->deleteRequest($feedback['request']);
				}

				if($module !== 'shops') $flashMessengerHelper->addMessage('MESSAGES_EMAIL_SENT_ERROR');
			} else {
				$result['sent']++;
				$emailmessageDb->updateEmailmessage($messageid, ['response' => 'sent']);

				if($feedback['service'] && $feedback['request']) {
					$feedback['service']->attachEmailMessage($feedback['request'], (int)$messageid);
				}

				if($module !== 'shops') $flashMessengerHelper->addMessage('MESSAGES_EMAIL_SENT_SUCCESS');
			}
		}

		if ($result['sent'] === 0) $result['ok'] = false;

		return $result;
	}

	private function prepareFeedback($module, $controller, array $data, int $documentid): array
	{
		$result = [
			'service' => null,
			'request' => null,
			'body' => (string)($data['body'] ?? ''),
		];

		$documentModule = (string)($data['module'] ?? $module);
		$documentController = (string)($data['controller'] ?? $controller);

		if($documentModule !== 'sales' || $documentController !== 'quote' || $documentid <= 0) return $result;

		$service = new DEEC_Feedback();
		$result['service'] = $service;

		try {
			$user = Zend_Registry::get('User');

			$quoteDb = new Sales_Model_DbTable_Quote();
			$quoteDb->setClientId((int)$user['clientid']);

			$quote = $quoteDb->getById($documentid);
			if(!$quote) return $result;

			$result['locale'] = (string)($quote['language'] ?? '');

			$prepared = $service->prepareRequest(
				'sales',
				'quote',
				$documentid,
				(int)$quote['clientid'],
				DEEC_Feedback::TYPE_QUOTE,
				[
					'contactid' => (int)($quote['contactid'] ?? 0),
				],
				!empty($data['feedback'])
			);

			$request = $prepared['request'];

			if(!$request) {
				$result['body'] = $service->removeEmailBlock($result['body']);
				return $result;
			}

			$block = $service->buildEmailBlock(
				$request,
				$this->getPublicBaseUrl(),
				$result['locale']
			);

			$result['body'] = $service->replaceEmailBlock($result['body'], $block);
			$result['request'] = $request;
			$result['created'] = !empty($prepared['created']);
		} catch(Exception $e) {
			error_log('Quote feedback could not be created: ' . $e->getMessage());
		}

		return $result;
	}

	private function getPublicBaseUrl(): string
	{
		$request = $this->getRequest();
		$basePath = rtrim((string)Zend_Controller_Front::getInstance()->getBaseUrl(), '/');

		return rtrim($request->getScheme() . '://' . $request->getHttpHost() . $basePath, '/');
	}

	private function getEmailConfig($module): array
	{
		if($module === 'shops') {
			$shop = Zend_Registry::get('Shop');
			$smtpConfig = $this->getDirectSmtpConfig($shop, 'Shop');
			$fromEmail = DEEC_Filter::email((string)($shop['emailsender'] ?? ''));
			if(!DEEC_Filter::isValidEmail($fromEmail)) throw new RuntimeException('Shop sender email is missing or invalid');

			return [$smtpConfig, $fromEmail, (string)($shop['title'] ?? '')];
		}

		$user = Zend_Registry::get('User');
		$smtpConfig = $this->getDirectSmtpConfig($user, 'User');
		$fromEmail = DEEC_Filter::email((string)($user['smtpuser'] ?? ''));
		if(!DEEC_Filter::isValidEmail($fromEmail)) throw new RuntimeException('User SMTP username must be a valid sender email address');

		return [$smtpConfig, $fromEmail, (string)($user['emailsender'] ?? '')];
	}

	private function getDirectSmtpConfig(array $source, string $context): array
	{
		$host = trim((string)($source['smtphost'] ?? ''));
		$username = trim((string)($source['smtpuser'] ?? ''));
		$password = (string)($source['smtppass'] ?? '');

		if($host === '') throw new RuntimeException($context.' SMTP host is missing');
		if(strlen($host) > 255 || preg_match('/[\x00-\x20\x7f]/', $host)) throw new RuntimeException($context.' SMTP host is invalid');
		if($username === '') throw new RuntimeException($context.' SMTP username is missing');
		if($password === '') throw new RuntimeException($context.' SMTP password is missing');

		return [
			'host' => $host,
			'auth' => true,
			'username' => $username,
			'password' => $password,
			'secure' => 'ssl',
			'port' => 465,
		];
	}

	private function getEmailBody($module, $controller, $formData, $data, $items)
	{
		if ($module == 'shops') {
			$templateDb = new Shops_Model_DbTable_Emailtemplate();
			$template = $templateDb->getEmailtemplate($module, $controller);

			$body = $template['body'];
			$shop = Zend_Registry::get('Shop');

			$viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer');

			if(!isset($data['name'])) $data['name'] = '';

			// Replace dynamic placeholders with values
			$dynamicPlaceholders = [
				'#HELLO_NAME#' => sprintf(
					$viewRenderer->view->translate('MESSAGES_HELLO_NAME'),
					htmlspecialchars($data['name'])
				),
				'#SHOP_TITLE#' => htmlspecialchars($shop['title']),
				'#SHOP_URL#' => htmlspecialchars($shop['url']),
				'#SHOP_EMAIL#' => htmlspecialchars($shop['emailsender']),
				'#MESSAGES_SUBJECT#' => htmlspecialchars($data['subject']),
				'#MESSAGES_MESSAGE#' => nl2br(htmlspecialchars($data['message'])),
				'#MESSAGES_BEST_REGARDS#' => htmlspecialchars($shop['emailsender']) . '<br>' . htmlspecialchars($shop['title']),
				'#MESSAGES_ALL_RIGHTS_RESERVED#' => date('Y') . ' ' . htmlspecialchars($shop['title'])
			];

			if ($controller == 'checkout') {
				if($formData['differentshippingaddress'] == 0) {
					$formData['shippingcompany'] = $formData['billingcompany'];
					$formData['shippingdepartment'] = $formData['billingdepartment'];
					$formData['shippingname'] = $formData['billingname'];
					$formData['shippingstreet'] = $formData['billingstreet'];
					$formData['shippingcity'] = $formData['billingcity'];
					$formData['shippingpostcode'] = $formData['billingpostcode'];
					$formData['shippingcountry'] = $formData['billingcountry'];
					$formData['shippingphone'] = $formData['billingphone'];
				}

				// Tabelle der Bestellartikel erstellen
				$orderItemsHtml = '';
				if($items) {
					foreach($items as $item) {
						$orderItemsHtml .= '
						<tr>
							<td>' . htmlspecialchars($item['title']) . '<br>' . htmlspecialchars($item['sku']) . '</td>
							<td>' . htmlspecialchars($item['quantity']) . '</td>
							<td>' . number_format($item['price'], 2, ',', '.') . ' €</td>
							<td>' . number_format($item['price'] * $item['quantity'], 2, ',', '.') . ' €</td>
						</tr>';
					}
				}
				$body = str_replace('#ORDER_ITEMS#', $orderItemsHtml, $body);

				// Replace dynamic placeholders with values
				$dynamicPlaceholders = [
					'#HELLO_NAME#' => sprintf(
						$viewRenderer->view->translate('MESSAGES_HELLO_NAME'),
						htmlspecialchars($formData['billingname'])
					),
					'#SHOP_TITLE#' => htmlspecialchars($shop['title']),
					'#SHOP_URL#' => htmlspecialchars($shop['url']),
					'#SHOP_EMAIL#' => htmlspecialchars($shop['emailsender']),
					'#MESSAGES_SUBJECT#' => htmlspecialchars($formData['subject']),
					'#MESSAGES_MESSAGE#' => nl2br(htmlspecialchars($formData['message'])),
					'#MESSAGES_BEST_REGARDS#' => htmlspecialchars($shop['emailsender']) . '<br>' . htmlspecialchars($shop['title']),
					'#MESSAGES_ALL_RIGHTS_RESERVED#' => date('Y') . ' ' . htmlspecialchars($shop['title']),
					'#ORDER_NUMBER#' => htmlspecialchars($data['orderid']),
					'#ORDER_DATE#' => htmlspecialchars($data['orderdate']),
					'#PAYMENT_METHOD#' => htmlspecialchars('Überweisung'),
					'#BILLING_COMPANY#' => htmlspecialchars($formData['billingcompany']),
					'#BILLING_DEPARTMENT#' => htmlspecialchars($formData['billingdepartment']),
					'#BILLING_NAME#' => htmlspecialchars($formData['billingname']),
					'#BILLING_ADDRESS#' => htmlspecialchars($formData['billingstreet']),
					'#BILLING_CITY#' => htmlspecialchars($formData['billingcity']),
					'#BILLING_POSTCODE#' => htmlspecialchars($formData['billingpostcode']),
					'#BILLING_COUNTRY#' => htmlspecialchars($formData['billingcountry']),
					'#BILLING_PHONE#' => htmlspecialchars($formData['billingphone']),
					'#SHIPPING_COMPANY#' => htmlspecialchars($formData['shippingcompany']),
					'#SHIPPING_DEPARTMENT#' => htmlspecialchars($formData['shippingdepartment']),
					'#SHIPPING_NAME#' => htmlspecialchars($formData['shippingname']),
					'#SHIPPING_ADDRESS#' => htmlspecialchars($formData['shippingstreet']),
					'#SHIPPING_CITY#' => htmlspecialchars($formData['shippingcity']),
					'#SHIPPING_POSTCODE#' => htmlspecialchars($formData['shippingpostcode']),
					'#SHIPPING_COUNTRY#' => htmlspecialchars($formData['shippingcountry']),
					'#SHIPPING_PHONE#' => htmlspecialchars($formData['shippingphone'])
				];
			}

			//print_r($formData);

			// Strip internal/technical fields before rendering
			$internalKeys = [
				'csrf', 'csrf_token', '_csrf', 'g-recaptcha-response',
				'step', 'next', 'back', 'submit', 'controller', 'module', 'action',
				'subject', 'message', 'inquiry_token', 'fax_number', 'privacy'
			];
			$internalPrefixes = ['_', '__']; // e.g. __meta, _internal

			$cleanData = [];
			foreach ($formData as $k => $v) {
				// skip explicit internal keys
				if (in_array($k, $internalKeys, true)) continue;
				// skip keys starting with internal prefixes
				foreach ($internalPrefixes as $pref) {
					if (strpos($k, $pref) === 0) { continue 2; }
				}
				$cleanData[$k] = $v;
			}

			// build details table from $cleanData
			$userDetailsHtml = '<table style="width: 100%; border-collapse: collapse;">';
			foreach ($cleanData as $key => $value) {
				// Escape key and value
				$label = ucfirst(str_replace('_', ' ', htmlspecialchars($key)));
				$content = nl2br(htmlspecialchars($value));

				$userDetailsHtml .= "<tr>
					<td style=\"padding: 5px; font-weight: bold; border-bottom: 1px solid #ddd;\">{$label}</td>
					<td style=\"padding: 5px; border-bottom: 1px solid #ddd;\">{$content}</td>
				</tr>";
			}
			$userDetailsHtml .= '</table>';

			$dynamicPlaceholders['#USER_DETAILS#'] = $userDetailsHtml;
		
			// Replace placeholders in the template
			foreach ($dynamicPlaceholders as $key => $value) {
				$body = str_replace($key, $value, $body);
			}

			// Translate email template
			$body = preg_replace_callback(
				'/t#t(.*?)t#t/', // Regex to match text between t#t and t#t
				function ($matches) use ($viewRenderer) {
					return $viewRenderer->view->translate($matches[1]);
				},
				$body
			);

			$subject = $template['subject'];
			return [$body, $subject];
		} else {
			$body = $data['body'];
			$user = Zend_Registry::get('User');
			//Add email signature
			$body = str_replace('[SIGNATURE]', $user['emailsignature'], $body);
			/*if($campaignid) {
				$data['body'] = str_replace('[BODY]', $data['body'], $template);
			}*/

			$subject = $data['subject'];
			return [$body, $subject];
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
