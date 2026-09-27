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
					$emailmessage = $emailmessageDb->getEmailmessage($messageid);
					$contactid = (int)($emailmessage['contactid'] ?? 0);
					$documentid = (int)($emailmessage['documentid'] ?? 0);
					$campaignid = (int)($emailmessage['campaignid'] ?? 0);

					unset($emailmessage['id'], $emailmessage['messagesent'], $emailmessage['messagesentby'], $emailmessage['response']);

					$data = $emailmessage;
					$recipients[0]['email'] = $data['recipient'];
					$recipients[0]['contactid'] = $contactid;
				} else {
					$emailDb = new Contacts_Model_DbTable_Email();
					$emailArray = $emailDb->getEmail($data['recipient']);
					$recipients[0]['email'] = $emailArray['email'];

					if($emailArray['controller'] == 'contact') {
						$recipients[0]['contactid'] = $emailArray['parentid'];
					} elseif($emailArray['controller'] == 'contactperson') {
						$recipients[0]['contactid'] = $emailArray['parentid'];

						$contactpersonDb = new Contacts_Model_DbTable_Contactperson();
						$contactperson = $contactpersonDb->getById($emailArray['parentid']);

						$recipients[0]['salutation'] = $contactperson['salutation'];
						$recipients[0]['name2'] = $contactperson['name2'];
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

					$this->sendEmails($module, $controller, $recipients, $smtpConfig, $fromEmail, $fromName, $formData, $data, $emailmessageDb, $documentid, $campaignid, $items);
				}
			}
		} else {
			$flashMessengerHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
			$flashMessengerHelper->addMessage('MESSAGES_FORM_INVALID');
		}
	}

	private function sendEmails($module, $controller, $recipients, array $smtpConfig, $fromEmail, $fromName, $formData, $data, $emailmessageDb, $documentid, $campaignid, $items)
	{
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
					$directoryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Directory');
					if($data['module'] == 'contacts') $url = $directoryHelper->getUrl($recipient['contactid']);
					else $url = $directoryHelper->getUrl($documentid);
					foreach($data['files'] as $file) {
						if(file_exists($attachmentsAvailable[$file]['location'].'/'.$attachmentsAvailable[$file]['filename'])) {
							array_push($attachmentsSent, $attachmentsAvailable[$file]['filename']);
							$attachmentPaths[] = $attachmentsAvailable[$file]['location'].'/'.$attachmentsAvailable[$file]['filename'];
						}
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

			if($feedback['service'] && $feedback['request']) {
				$feedback['service']->attachEmailMessage($feedback['request'], (int)$messageid);
			}

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
				$emailmessageDb->updateEmailmessage($messageid, array('response' => $sendResult['error']));

				if($feedback['service'] && $feedback['request'] && $feedback['created']) {
					$feedback['service']->deleteRequest($feedback['request']);
				}

				$flashMessengerHelper->addMessage('MESSAGES_EMAIL_SENT_ERROR');
			} else {
				$emailmessageDb->updateEmailmessage($messageid, array('response' => 'sent'));
				$flashMessengerHelper->addMessage('MESSAGES_EMAIL_SENT_SUCCESS');
			}
		}
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

			$configDb = new Application_Model_DbTable_Config();
			$config = $configDb->getByClientId((int)$shop['clientid']);
			$smtpConfig = $config ? DEEC_Email::buildSmtpConfig($config) : null;

			if(!$smtpConfig) {
				$smtpConfig = [
					'host' => $shop['smtphost'],
					'auth' => true,
					'username' => $shop['smtpuser'],
					'password' => $shop['smtppass'],
					'secure' => 'ssl',
					'port' => 465,
				];
			}

			return [$smtpConfig, (string)$shop['emailsender'], (string)$shop['title']];
		}

		$user = Zend_Registry::get('User');
		$config = Zend_Registry::get('Config');
		$smtpConfig = DEEC_Email::buildSmtpConfig($config);

		if(!$smtpConfig) {
			$smtpConfig = [
				'host' => $user['smtphost'],
				'auth' => true,
				'username' => $user['smtpuser'],
				'password' => $user['smtppass'],
				'secure' => 'ssl',
				'port' => 465,
			];

			return [$smtpConfig, (string)$user['smtpuser'], (string)$user['emailsender']];
		}

		if(empty($user['email'])) throw new Exception('Email sender address is missing');

		return [$smtpConfig, (string)$user['email'], (string)$user['emailsender']];
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
				'subject', 'message'
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
